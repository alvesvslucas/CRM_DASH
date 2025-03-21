<?php
session_start();
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
include(HEADER_FILE);

// Verifica se o usuário é supervisor do setor Energia
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'supervisor' || ($_SESSION['setor'] ?? '') !== 'Energia') {
  die("Acesso negado.");
}

// Conexão com o banco
$conn = new mysqli($host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
  die("Falha na conexão: " . $conn->connect_error);
}

// Definições de data
$currentMonth = date('Y-m');  // Ex: "2025-03"
$currentDay   = date('Y-m-d');
$filtroDia    = $_GET['data_filtro'] ?? $currentDay;

/* ---------------------------------------------------------
 * 1) METAS DO SETOR (tabela metas_energia)
 * --------------------------------------------------------- */
$stmt = $conn->prepare("
  SELECT meta_mes, meta_dia 
  FROM metas_energia 
  WHERE periodo = ?
");
$stmt->bind_param("s", $currentMonth);
$stmt->execute();
$resultMeta = $stmt->get_result()->fetch_assoc();
$stmt->close();

$metaMes = $resultMeta['meta_mes'] ?? 0;
$metaDia = $resultMeta['meta_dia'] ?? 0;

/* ---------------------------------------------------------
 * 2) VENDA GERAL (Mês) - usando valor_venda e data_registro
 * --------------------------------------------------------- */
$sqlVendaGeral = "
  SELECT 
    SUM(CASE WHEN status = 'pago' THEN valor_venda END) AS pagos_geral,
    SUM(CASE WHEN status = 'pendente' THEN valor_venda END) AS pagos_pendentes,
    SUM(CASE WHEN status = 'aguardando' THEN valor_venda END) AS aguardando_formalizacao,
    SUM(CASE WHEN status = 'formalizado' THEN valor_venda END) AS formalizados
  FROM vendas_energia
  WHERE DATE_FORMAT(data_registro, '%Y-%m') = ?
";
$stmt = $conn->prepare($sqlVendaGeral);
$stmt->bind_param("s", $currentMonth);
$stmt->execute();
$vendaGeral = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ---------------------------------------------------------
 * 3) VENDA POR CONSULTOR (Mês)
 * --------------------------------------------------------- */
$sqlVendaConsultor = "
  SELECT a.nome AS consultor,
         IFNULL(m.meta_mes, 0) AS meta_mes,
         SUM(v.valor_venda) AS total_mes,
         CASE WHEN IFNULL(m.meta_mes, 0) = 0 THEN 0 
              ELSE (SUM(v.valor_venda) / m.meta_mes) * 100 END AS percentual
  FROM vendas_energia v
  JOIN agentes_energia a ON v.agente_id = a.id
  LEFT JOIN metas_agentes_energia m ON m.agente_id = a.id 
                            AND m.periodo = ?
  WHERE DATE_FORMAT(v.data_registro, '%Y-%m') = ?
  GROUP BY v.agente_id
";
$stmt = $conn->prepare($sqlVendaConsultor);
$stmt->bind_param("ss", $currentMonth, $currentMonth);
$stmt->execute();
$resultConsultores = $stmt->get_result();
$consultores = [];
while ($row = $resultConsultores->fetch_assoc()) {
  $consultores[] = $row;
}
$stmt->close();

/* ---------------------------------------------------------
 * 4) VALORES PENDENTES POR AGENTE (Mês)
 * --------------------------------------------------------- */
$sqlPendentes = "
  SELECT a.nome AS agente, SUM(v.valor_venda) AS total_pendente
  FROM vendas_energia v
  JOIN agentes_energia a ON v.agente_id = a.id
  WHERE v.status = 'pendente'
    AND DATE_FORMAT(v.data_registro, '%Y-%m') = ?
  GROUP BY a.id
";
$stmt = $conn->prepare($sqlPendentes);
$stmt->bind_param("s", $currentMonth);
$stmt->execute();
$resultPendentes = $stmt->get_result();
$pendentes = [];
while ($row = $resultPendentes->fetch_assoc()) {
  $pendentes[] = $row;
}
$stmt->close();

/* ---------------------------------------------------------
 * 5) VALORES PAGOS POR AGENTE (Mês)
 * --------------------------------------------------------- */
$sqlPagos = "
  SELECT a.nome AS agente, SUM(v.valor_venda) AS total_pago
  FROM vendas_energia v
  JOIN agentes_energia a ON v.agente_id = a.id
  WHERE v.status = 'pago'
    AND DATE_FORMAT(v.data_registro, '%Y-%m') = ?
  GROUP BY a.id
";
$stmt = $conn->prepare($sqlPagos);
$stmt->bind_param("s", $currentMonth);
$stmt->execute();
$resultPagos = $stmt->get_result();
$pagos = [];
while ($row = $resultPagos->fetch_assoc()) {
  $pagos[] = $row;
}
$stmt->close();

/* ---------------------------------------------------------
 * 6A) Metas e Faltas (Agentes) - usando agentes_energia + metas_agentes_energia
 * --------------------------------------------------------- */
$sqlAgentesMetas = "
SELECT 
  a.id AS consultor_id,
  a.nome AS consultor,
  COALESCE(m.meta_mes, 0) AS meta_mes,
  COALESCE(m.meta_dia, 0) AS meta_dia,

  -- Total pago no mês (subconsulta)
  IFNULL((
    SELECT SUM(v.valor_venda)
    FROM vendas_energia v
    WHERE v.status = 'pago'
      AND v.agente_id = a.id
      AND DATE_FORMAT(v.data_registro, '%Y-%m') = ?
  ), 0) AS total_pago_mes,

  -- Total pago no dia filtrado (subconsulta)
  IFNULL((
    SELECT SUM(v.valor_venda)
    FROM vendas_energia v
    WHERE v.status = 'pago'
      AND v.agente_id = a.id
      AND DATE(v.data_registro) = ?
  ), 0) AS total_pago_dia

FROM agentes_energia a
LEFT JOIN metas_agentes_energia m 
       ON m.agente_id = a.id
      AND m.periodo = ?  -- ex: 2025-03

WHERE a.ativo = 1
ORDER BY a.nome
";
$stmt = $conn->prepare($sqlAgentesMetas);
$stmt->bind_param("sss", $currentMonth, $filtroDia, $currentMonth);
$stmt->execute();
$resAgentes = $stmt->get_result();
$agentesMetas = [];
while ($row = $resAgentes->fetch_assoc()) {
  // Calcula o que falta para bater a meta (mês/dia)
  $row['falta_mes'] = max(0, $row['meta_mes'] - $row['total_pago_mes']);
  $row['falta_dia'] = max(0, $row['meta_dia'] - $row['total_pago_dia']);
  $agentesMetas[]   = $row;
}
$stmt->close();

/* ---------------------------------------------------------
 * 6B) Metas e Faltas (Departamentos)
 * --------------------------------------------------------- */
$sqlDeptMetas = "
SELECT 
 d.id,
 d.departamento,
 d.meta_mes,
 d.meta_dia,
 d.periodo,
 IFNULL(( 
   SELECT SUM(v.valor_venda)
   FROM vendas_energia v
   WHERE v.status = 'pago'
     AND v.setor = d.departamento
     AND DATE_FORMAT(v.data_registro, '%Y-%m') = ?
 ), 0) AS total_pago_mes,
 IFNULL(( 
   SELECT SUM(v.valor_venda)
   FROM vendas_energia v
   WHERE v.status = 'pago'
     AND v.setor = d.departamento
     AND DATE(v.data_registro) = ?
 ), 0) AS total_pago_dia
FROM metas_energia d
WHERE d.periodo = ?
  AND d.departamento IN ('Tele','Rede','Lojas')
ORDER BY d.departamento
";
$stmt = $conn->prepare($sqlDeptMetas);
$stmt->bind_param("sss", $currentMonth, $filtroDia, $currentMonth);
$stmt->execute();
$resDept = $stmt->get_result();
$deptMetas = [];
while ($row = $resDept->fetch_assoc()) {
  $row['falta_mes'] = max(0, $row['meta_mes'] - $row['total_pago_mes']);
  $row['falta_dia'] = max(0, $row['meta_dia'] - $row['total_pago_dia']);
  $deptMetas[] = $row;
}
$stmt->close();

/* ---------------------------------------------------------
 * 7) Contratos por Setor (Dia e Mês) - Tabela: contratos_energia
 * --------------------------------------------------------- */

// (A) Contratos do dia filtrado
$sqlContratosDia = "
  SELECT setor, 
         SUM(valor_contrato) AS total_contratos, 
         COUNT(*) AS qtd_contratos
  FROM contratos_energia
  WHERE DATE(data_registro) = ?
  GROUP BY setor
";
$stmt = $conn->prepare($sqlContratosDia);
$stmt->bind_param("s", $filtroDia);
$stmt->execute();
$resContratosDia = $stmt->get_result();
$contratosDia = [];
while ($row = $resContratosDia->fetch_assoc()) {
  $contratosDia[] = $row;
}
$stmt->close();

// (B) Contratos do mês atual
$sqlContratosMes = "
  SELECT setor, 
         SUM(valor_contrato) AS total_contratos, 
         COUNT(*) AS qtd_contratos
  FROM contratos_energia
  WHERE DATE_FORMAT(data_registro, '%Y-%m') = ?
  GROUP BY setor
";
$stmt = $conn->prepare($sqlContratosMes);
$stmt->bind_param("s", $currentMonth);
$stmt->execute();
$resContratosMes = $stmt->get_result();
$contratosMes = [];
while ($row = $resContratosMes->fetch_assoc()) {
  $contratosMes[] = $row;
}
$stmt->close();


$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>Dashboard Energia - Ajustado</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      background: #f9f9f9;
    }

    .card-custom {
      background: #fff;
      border-radius: 8px;
      padding: 1rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      margin-bottom: 1rem;
    }

    .chart-canvas {
      height: 200px !important;
    }

    .btn-filter {
      background: #28a745;
      color: #fff;
      border: none;
    }

    .btn-filter:hover {
      background: #218838;
    }
  </style>
</head>

<body>
  <div class="container mt-4">
    <h2 class="text-center text-success mb-4">📊 Dashboard - Energia</h2>

    <!-- Filtros e Metas -->
    <div class="row">
      <div class="col-md-3">
        <div class="card-custom">
          <h4>🎯 Metas do Setor</h4>
          <p><strong>Mensal:</strong> R$ <?= number_format($metaMes, 2, ',', '.') ?></p>
          <p><strong>Diária:</strong> R$ <?= number_format($metaDia, 2, ',', '.') ?></p>
        </div>
        <div class="card-custom">
          <h4>📅 Filtrar por Dia</h4>
          <form method="GET">
            <input type="date" name="data_filtro" value="<?= $filtroDia ?>" class="form-control mb-2">
            <button type="submit" class="btn btn-filter w-100">Filtrar</button>
          </form>
        </div>
      </div>

      <!-- Dados Gerais (Venda Geral) -->
      <div class="col-md-9">
        <div class="card-custom">
          <h4>💰 Venda Geral (Mês)</h4>
          <table class="table">
            <tr>
              <td>✅ Pagos:</td>
              <td>R$ <?= number_format($vendaGeral['pagos_geral'] ?? 0, 2, ',', '.') ?></td>
            </tr>
            <tr>
              <td>⏳ Pendentes:</td>
              <td>R$ <?= number_format($vendaGeral['pagos_pendentes'] ?? 0, 2, ',', '.') ?></td>
            </tr>
          </table>
        </div>

        <!-- PENDENTES x PAGOS POR AGENTE (Mês) -->
        <div class="row">
          <div class="col-md-6">
            <div class="card-custom">
              <h4>⏳ Pendentes por Agente (Mês)</h4>
              <?php if (!empty($pendentes)): ?>
                <table class="table table-bordered">
                  <thead>
                    <tr>
                      <th>Agente</th>
                      <th>Total Pendente (R$)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($pendentes as $p): ?>
                      <tr>
                        <td><?= htmlspecialchars($p['agente']) ?></td>
                        <td>R$ <?= number_format($p['total_pendente'], 2, ',', '.') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php else: ?>
                <p class="text-muted">Nenhum valor pendente neste mês.</p>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card-custom">
              <h4>✅ Pagos por Agente (Mês)</h4>
              <?php if (!empty($pagos)): ?>
                <table class="table table-bordered">
                  <thead>
                    <tr>
                      <th>Agente</th>
                      <th>Total Pago (R$)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($pagos as $p): ?>
                      <tr>
                        <td><?= htmlspecialchars($p['agente']) ?></td>
                        <td>R$ <?= number_format($p['total_pago'], 2, ',', '.') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php else: ?>
                <p class="text-muted">Nenhum valor pago neste mês.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- VENDA POR CONSULTOR (Agente) -->
        <div class="card-custom">
          <h4>👨‍💼 Venda por Consultor (Mês)</h4>
          <table class="table">
            <thead>
              <tr>
                <th>Consultor</th>
                <th>Meta Mês</th>
                <th>Total (Mês)</th>
                <th>%</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($consultores as $c): ?>
                <tr>
                  <td><?= htmlspecialchars($c['consultor']) ?></td>
                  <td>R$ <?= number_format($c['meta_mes'], 2, ',', '.') ?></td>
                  <td>R$ <?= number_format($c['total_mes'], 2, ',', '.') ?></td>
                  <td><?= number_format($c['percentual'], 2, ',', '.') ?>%</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Metas e Faltas (Agentes) e (Departamentos) LADO A LADO -->
    <div class="row">
      <!-- Metas e Faltas (Agentes) -->
      <div class="col-md-6">
        <div class="card-custom">
          <h4>Metas e Faltas (Agentes)</h4>
          <?php if (!empty($agentesMetas)): ?>
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>Agente</th>
                  <th>Meta Mês</th>
                  <th>Pago Mês</th>
                  <th>Falta Mês</th>
                  <th>Meta Dia</th>
                  <th>Pago Dia</th>
                  <th>Falta Dia</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($agentesMetas as $am): ?>
                  <tr>
                    <td><?= htmlspecialchars($am['consultor']) ?></td>
                    <td>R$ <?= number_format($am['meta_mes'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($am['total_pago_mes'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($am['falta_mes'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($am['meta_dia'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($am['total_pago_dia'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($am['falta_dia'], 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="text-muted">Nenhum agente ativo com meta cadastrada neste período.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Metas e Faltas (Departamentos) -->
      <div class="col-md-6">
        <div class="card-custom">
          <h4>Metas e Faltas (Departamentos)</h4>
          <?php if (!empty($deptMetas)): ?>
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>Departamento</th>
                  <th>Meta Mês</th>
                  <th>Pago Mês</th>
                  <th>Falta Mês</th>
                  <th>Meta Dia</th>
                  <th>Pago Dia</th>
                  <th>Falta Dia</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($deptMetas as $dm): ?>
                  <tr>
                    <td><?= htmlspecialchars($dm['departamento']) ?></td>
                    <td>R$ <?= number_format($dm['meta_mes'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($dm['total_pago_mes'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($dm['falta_mes'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($dm['meta_dia'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($dm['total_pago_dia'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($dm['falta_dia'], 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="text-muted">Nenhuma meta de departamento definida para este período.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Contratos por Setor (Dia e Mês) -->
    <div class="row">
      <div class="col-md-6">
        <div class="card-custom">
          <h4>Contratos por Setor (Dia: <?= htmlspecialchars($filtroDia) ?>)</h4>
          <table class="table table-bordered">
            <thead>
              <tr>
                <th>Setor</th>
                <th>Quantidade</th>
                <th>Total (R$)</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($contratosDia)): ?>
                <?php foreach ($contratosDia as $cd): ?>
                  <tr>
                    <td><?= htmlspecialchars($cd['setor']) ?></td>
                    <td><?= $cd['qtd_contratos'] ?></td>
                    <td>R$ <?= number_format($cd['total_contratos'], 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="3" class="text-muted">Nenhum contrato registrado neste dia.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card-custom">
          <h4>Contratos por Setor (Mês: <?= htmlspecialchars($currentMonth) ?>)</h4>
          <table class="table table-bordered">
            <thead>
              <tr>
                <th>Setor</th>
                <th>Quantidade</th>
                <th>Total (R$)</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($contratosMes)): ?>
                <?php foreach ($contratosMes as $cm): ?>
                  <tr>
                    <td><?= htmlspecialchars($cm['setor']) ?></td>
                    <td><?= $cm['qtd_contratos'] ?></td>
                    <td>R$ <?= number_format($cm['total_contratos'], 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="3" class="text-muted">Nenhum contrato registrado neste mês.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- GRÁFICOS -->
    <div class="row">
      <div class="col-md-6">
        <div class="card-custom">
          <h4>📈 Gráfico de Venda Geral (Mês)</h4>
          <canvas id="graficoVendaGeral" class="chart-canvas"></canvas>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card-custom">
          <h4>📊 Gráfico de Venda por Consultor (Mês)</h4>
          <canvas id="graficoConsultores" class="chart-canvas"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- SCRIPT DOS GRÁFICOS -->
  <script>
    // Gráfico de Venda Geral
    new Chart(document.getElementById('graficoVendaGeral').getContext('2d'), {
      type: 'bar',
      data: {
        labels: ['Pagos', 'Pendentes', 'Aguardando', 'Formalizados'],
        datasets: [{
          data: [
            <?= $vendaGeral['pagos_geral'] ?? 0 ?>,
            <?= $vendaGeral['pagos_pendentes'] ?? 0 ?>,
            <?= $vendaGeral['aguardando_formalizacao'] ?? 0 ?>,
            <?= $vendaGeral['formalizados'] ?? 0 ?>
          ],
          backgroundColor: ['#27ae60', '#e67e22', '#f1c40f', '#2980b9']
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true
          }
        }
      }
    });

    // Gráfico de Venda por Consultor
    new Chart(document.getElementById('graficoConsultores').getContext('2d'), {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_column($consultores, 'consultor')) ?>,
        datasets: [{
            label: 'Total (Mês)',
            data: <?= json_encode(array_map('floatval', array_column($consultores, 'total_mes'))) ?>,
            backgroundColor: '#3498db'
          },
          {
            label: 'Meta (Mês)',
            data: <?= json_encode(array_map('floatval', array_column($consultores, 'meta_mes'))) ?>,
            backgroundColor: '#95a5a6'
          }
        ]
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true
          }
        }
      }
    });
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <?php include(FOOTER_FILE); ?>
</body>

</html>
<?php include(FOOTER_FILE); ?>