<?php
session_start();
require_once '../../db/db.php';
include '../../absoluto.php';
include(HEADER_FILE);

$conn = getDatabaseConnection();
$message = "";

/* ==========================================================
   Processamento de Atualização de Metas via Modal
   (Quando o formulário de edição de metas é enviado)
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_metas'])) {
    // Recebe o período no formato YYYY-MM (input type="month")
    $selectedPeriod = $_POST['periodo'] ?? date('Y-m');
    $periodo = $selectedPeriod . "-01"; // Período para cadastro de metas

    // Obter todos os consultores (para atualizar metas)
    $sqlConsultores = "SELECT id FROM users WHERE tipo = 'consultor'";
    $resultCons = $conn->query($sqlConsultores);
    $consultores = $resultCons->fetch_all(MYSQLI_ASSOC);

    foreach ($consultores as $cons) {
        $id = $cons['id'];
        $metaDiaria  = isset($_POST["meta_diaria_$id"]) ? floatval($_POST["meta_diaria_$id"]) : 0;
        $metaSemanal = isset($_POST["meta_semanal_$id"]) ? floatval($_POST["meta_semanal_$id"]) : 0;
        $metaMensal  = isset($_POST["meta_mensal_$id"]) ? floatval($_POST["meta_mensal_$id"]) : 0;

        // Verifica se já existe registro para este consultor e período
        $sqlCheck = "SELECT id FROM metas_consultores_consignado WHERE consultor_id = ? AND periodo = ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bind_param("is", $id, $periodo);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        if ($row = $resCheck->fetch_assoc()) {
            // Atualiza registro existente
            $sqlUpdate = "UPDATE metas_consultores_consignado SET meta_diaria = ?, meta_semanal = ?, meta_mensal = ? WHERE id = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->bind_param("dddi", $metaDiaria, $metaSemanal, $metaMensal, $row['id']);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        } else {
            // Insere novo registro
            $sqlInsert = "INSERT INTO metas_consultores_consignado (consultor_id, meta_diaria, meta_semanal, meta_mensal, periodo) VALUES (?, ?, ?, ?, ?)";
            $stmtInsert = $conn->prepare($sqlInsert);
            // Ordem dos tipos: int, double, double, double, string
            $stmtInsert->bind_param("iddds", $id, $metaDiaria, $metaSemanal, $metaMensal, $periodo);
            $stmtInsert->execute();
            $stmtInsert->close();
        }
        $stmtCheck->close();
    }
    $message = "Metas atualizadas para o período " . $periodo;
}

/* ==========================================================
   1) Parâmetros via GET: meta_tipo e período para o levantamento
   ========================================================== */
$metaTipo = isset($_GET['meta_tipo']) ? $_GET['meta_tipo'] : 'diaria';
$periodoInput = isset($_GET['periodo']) ? $_GET['periodo'] : '';

if ($metaTipo === 'diaria') {
    if (empty($periodoInput)) {
        $periodoInput = date('Y-m-d');
    }
    $periodStart = $periodoInput;
    $periodEnd   = $periodoInput;
    $metaPeriodo = date('Y-m-01', strtotime($periodoInput));
} elseif ($metaTipo === 'semanal') {
    if (empty($periodoInput)) {
        $periodoInput = date('Y-m-d');
    }
    $timestamp = strtotime($periodoInput);
    $dayOfWeek = date('w', $timestamp);
    if ($dayOfWeek == 0) {
        $monday = date('Y-m-d', strtotime('+1 day', $timestamp));
    } else {
        $monday = date('Y-m-d', strtotime('-'.($dayOfWeek - 1).' days', $timestamp));
    }
    $sunday = date('Y-m-d', strtotime('+6 days', strtotime($monday)));
    $periodStart = $monday;
    $periodEnd   = $sunday;
    $metaPeriodo = date('Y-m-01', strtotime($monday));
} elseif ($metaTipo === 'mensal') {
    if (empty($periodoInput)) {
        $periodoInput = date('Y-m');
    }
    $periodStart = $periodoInput . "-01";
    $periodEnd   = date('Y-m-t', strtotime($periodStart));
    $metaPeriodo = $periodStart;
} else {
    $metaTipo = 'diaria';
    $periodoInput = date('Y-m-d');
    $periodStart = $periodoInput;
    $periodEnd   = $periodoInput;
    $metaPeriodo = date('Y-m-01', strtotime($periodoInput));
}

/* ==========================================================
   2) Buscar metas dos consultores para o período
   ========================================================== */
$sqlMetas = "SELECT * FROM metas_consultores_consignado WHERE periodo = ?";
$stmtMetas = $conn->prepare($sqlMetas);
$stmtMetas->bind_param("s", $metaPeriodo);
$stmtMetas->execute();
$resMetas = $stmtMetas->get_result();
$metasConsultores = [];
while ($row = $resMetas->fetch_assoc()) {
    $metasConsultores[$row['consultor_id']] = $row;
}
$stmtMetas->close();

/* ==========================================================
   3) Buscar total pago de cada consultor no período (somente "PAGO")
   ========================================================== */
$sqlTotais = "SELECT consultor_id, SUM(valor_liquido) as total_pago 
              FROM digitacoes 
              WHERE status = 'PAGO'
              AND data_pagamento BETWEEN ? AND ?
              GROUP BY consultor_id";
$stmtTot = $conn->prepare($sqlTotais);
$stmtTot->bind_param("ss", $periodStart, $periodEnd);
$stmtTot->execute();
$resTot = $stmtTot->get_result();
$totaisConsultores = [];
while ($row = $resTot->fetch_assoc()) {
    $totaisConsultores[$row['consultor_id']] = floatval($row['total_pago']);
}
$stmtTot->close();

/* ==========================================================
   4) Buscar consultores com metas cadastradas para o período
   ========================================================== */
if (!empty($metasConsultores)) {
    $ids = array_keys($metasConsultores);
    $idsList = implode(',', $ids);
    $sqlCons = "SELECT id, nome FROM users WHERE tipo = 'consultor' AND id IN ($idsList) ORDER BY nome";
    $resCons = $conn->query($sqlCons);
    $consultores = $resCons->fetch_all(MYSQLI_ASSOC);
} else {
    $consultores = [];
}

/* ==========================================================
   5) Preparar ranking e cálculos para cada consultor
   ========================================================== */
$ranking = [];
foreach ($consultores as $c) {
    $cid = $c['id'];
    $nome = $c['nome'];
    $totalPago = isset($totaisConsultores[$cid]) ? $totaisConsultores[$cid] : 0;
    if ($metaTipo === 'diaria') {
        $meta = isset($metasConsultores[$cid]['meta_diaria']) ? floatval($metasConsultores[$cid]['meta_diaria']) : 0;
    } elseif ($metaTipo === 'semanal') {
        $meta = isset($metasConsultores[$cid]['meta_semanal']) ? floatval($metasConsultores[$cid]['meta_semanal']) : 0;
    } else {
        $meta = isset($metasConsultores[$cid]['meta_mensal']) ? floatval($metasConsultores[$cid]['meta_mensal']) : 0;
    }
    $diff = $meta - $totalPago;
    $percent = ($meta > 0) ? round(($totalPago / $meta) * 100, 2) : 0;
    $ranking[] = [
        'consultor_id'   => $cid,
        'consultor_nome' => $nome,
        'total_pago'     => $totalPago,
        'meta'           => $meta,
        'diff'           => $diff,
        'percent'        => $percent
    ];
}

/* ==========================================================
   6) Preparar arrays para os gráficos
   ========================================================== */
$rankingLabels = array_column($ranking, 'consultor_nome');
$rankingTotals = array_map('floatval', array_column($ranking, 'total_pago'));
$rankingMetas  = array_map('floatval', array_column($ranking, 'meta'));
$rankingPerc   = array_map('floatval', array_column($ranking, 'percent'));
$rankingDiff   = array_map('floatval', array_column($ranking, 'diff'));

/* ==========================================================
   7) Preparar dados para gráfico ABC (ordenar por percentual)
   ========================================================== */
$rankingSorted = $ranking;
usort($rankingSorted, function($a, $b) {
    return $b['percent'] <=> $a['percent'];
});
$sortedLabels = array_column($rankingSorted, 'consultor_nome');
$sortedPerc   = array_map('floatval', array_column($rankingSorted, 'percent'));

/* ==========================================================
   8) Buscar dias trabalhados do mês (tabela dias_trabalho)
   ========================================================== */
$sqlDias = "SELECT * FROM dias_trabalho ORDER BY id DESC LIMIT 1";
$resDias = $conn->query($sqlDias);
$configDias = $resDias->fetch_assoc();
if ($configDias) {
    $dataFimConfig = date("Y-m-t 23:59:59", strtotime($configDias['mes'] . "-01"));
    $agora = time();
    $diasRestantes = floor((strtotime($dataFimConfig) - $agora) / 86400);
    if ($diasRestantes < 0) {
        $diasRestantes = 0;
    }
} else {
    $diasRestantes = 0;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Metas e Ranking - Consultores</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Roboto', sans-serif;
    }
    .card {
      margin-bottom: 1.5rem;
      box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    }
    .card-header {
      background-color: #ffffff;
      border-bottom: 2px solid #dee2e6;
      font-size: 1.1rem;
      font-weight: 500;
    }
    .table {
      font-size: 0.9rem;
    }
    .text-primary {
      font-weight: 600;
    }
  </style>
</head>
<body>
<div class="container my-4">
  <h1 class="mb-3">Metas e Ranking - Consultores</h1>
  <p><strong>Período:</strong> <?php echo $periodStart . " até " . $periodEnd; ?></p>
  <p><strong>Tipo de Meta:</strong> <?php echo ucfirst($metaTipo); ?></p>
  
  <!-- Botão para editar metas via modal -->
  <div class="text-end mb-3">
    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditarMetas">
      Editar Metas do Mês Vigente
    </button>
  </div>
  
  <!-- Modal de Edição de Metas -->
  <div class="modal fade" id="modalEditarMetas" tabindex="-1" aria-labelledby="modalEditarMetasLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form method="POST" action="">
          <div class="modal-header">
            <h5 class="modal-title" id="modalEditarMetasLabel">Editar Metas - Mês Vigente (<?php echo $metaPeriodo; ?>)</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>Consultor</th>
                  <th>Meta Diária (R$)</th>
                  <th>Meta Semanal (R$)</th>
                  <th>Meta Mensal (R$)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($consultores as $c): 
                    $cid = $c['id'];
                    $metaDia = isset($metasConsultores[$cid]['meta_diaria']) ? $metasConsultores[$cid]['meta_diaria'] : '';
                    $metaSem = isset($metasConsultores[$cid]['meta_semanal']) ? $metasConsultores[$cid]['meta_semanal'] : '';
                    $metaMes = isset($metasConsultores[$cid]['meta_mensal']) ? $metasConsultores[$cid]['meta_mensal'] : '';
                ?>
                <tr>
                  <td><?php echo $c['nome']; ?></td>
                  <td><input type="number" step="0.01" name="meta_diaria_<?php echo $cid; ?>" class="form-control" value="<?php echo $metaDia; ?>"></td>
                  <td><input type="number" step="0.01" name="meta_semanal_<?php echo $cid; ?>" class="form-control" value="<?php echo $metaSem; ?>"></td>
                  <td><input type="number" step="0.01" name="meta_mensal_<?php echo $cid; ?>" class="form-control" value="<?php echo $metaMes; ?>"></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <!-- Campo hidden para indicar que o formulário é de edição de metas -->
            <input type="hidden" name="editar_metas" value="1">
            <!-- Campo hidden para enviar o período selecionado -->
            <input type="hidden" name="periodo" value="<?php echo $selectedPeriod; ?>">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Salvar Metas</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- Fim do Modal de Edição -->

  <!-- Card: Ranking dos Consultores -->
  <div class="card mb-4">
    <div class="card-header">Ranking dos Consultores</div>
    <div class="card-body">
      <table class="table table-striped">
        <thead>
          <tr>
            <th>Consultor</th>
            <th>Total Pago (R$)</th>
            <th>Meta (R$)</th>
            <th>% Atingido</th>
            <th>Diferença (R$)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($ranking as $r): ?>
          <tr>
            <td><?php echo $r['consultor_nome']; ?></td>
            <td><?php echo number_format($r['total_pago'], 2, ',', '.'); ?></td>
            <td><?php echo number_format($r['meta'], 2, ',', '.'); ?></td>
            <td><?php echo $r['percent']; ?>%</td>
            <td><?php echo number_format($r['diff'], 2, ',', '.'); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  
  <!-- Card: Consultores Ativados e Suas Metas -->
  <div class="card mb-4">
    <div class="card-header">Consultores Ativados e Suas Metas (Período: <?php echo $metaPeriodo; ?>)</div>
    <div class="card-body">
      <?php if (!empty($metasConsultores)): ?>
      <table class="table table-sm table-bordered">
        <thead>
          <tr>
            <th>Consultor</th>
            <th>Meta Diária (R$)</th>
            <th>Meta Semanal (R$)</th>
            <th>Meta Mensal (R$)</th>
          </tr>
        </thead>
        <tbody>
          <?php 
            foreach ($metasConsultores as $consultor_id => $meta) {
                $nome = "";
                foreach($consultores as $c) {
                    if($c['id'] == $consultor_id) {
                        $nome = $c['nome'];
                        break;
                    }
                }
          ?>
          <tr>
            <td><?php echo $nome; ?></td>
            <td><?php echo number_format($meta['meta_diaria'], 2, ',', '.'); ?></td>
            <td><?php echo number_format($meta['meta_semanal'], 2, ',', '.'); ?></td>
            <td><?php echo number_format($meta['meta_mensal'], 2, ',', '.'); ?></td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
      <?php else: ?>
      <p class="text-muted">Nenhuma meta cadastrada para o período.</p>
      <?php endif; ?>
    </div>
  </div>
  
  <!-- Card: Gráfico Comparativo: Total Pago vs Meta -->
  <div class="card mb-4">
    <div class="card-header">Comparativo: Total Pago vs Meta (<?php echo ucfirst($metaTipo); ?>)</div>
    <div class="card-body">
      <canvas id="graficoComparativo"></canvas>
    </div>
  </div>
  
  <!-- Card: Gráfico Percentual Atingido -->
  <div class="card mb-4">
    <div class="card-header">Percentual Atingido</div>
    <div class="card-body">
      <canvas id="graficoPercentual"></canvas>
    </div>
  </div>
  
  <!-- Card: Dias Trabalhados -->
  <div class="text-end">
    <small class="text-muted">Dias Restantes: <?php echo $diasRestantes; ?></small>
  </div>
  
</div><!-- /.container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Gráfico Comparativo: Total Pago vs Meta (Bar Chart)
  const ctxComp = document.getElementById('graficoComparativo').getContext('2d');
  new Chart(ctxComp, {
    type: 'bar',
    data: {
      labels: <?php echo json_encode($rankingLabels, JSON_UNESCAPED_UNICODE); ?>,
      datasets: [
        {
          label: 'Total Pago (R$)',
          data: <?php echo json_encode($rankingTotals, JSON_UNESCAPED_UNICODE); ?>,
          backgroundColor: 'rgba(54, 162, 235, 0.8)',
          borderColor: 'rgba(54, 162, 235, 1)',
          borderWidth: 1,
          borderRadius: 4
        },
        {
          label: 'Meta (R$)',
          data: <?php echo json_encode($rankingMetas, JSON_UNESCAPED_UNICODE); ?>,
          backgroundColor: 'rgba(255, 99, 132, 0.8)',
          borderColor: 'rgba(255, 99, 132, 1)',
          borderWidth: 1,
          borderRadius: 4
        }
      ]
    },
    options: {
      plugins: {
        title: {
          display: true,
          text: 'Comparativo: Total Pago vs Meta'
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              let label = context.dataset.label || '';
              let value = context.raw || 0;
              if (label) {
                label += ': ';
              }
              label += value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
              return label;
            }
          }
        }
      },
      responsive: true,
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(value) {
              return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            }
          }
        }
      }
    }
  });

  // Gráfico Percentual: Doughnut Chart
  const ctxPerc = document.getElementById('graficoPercentual').getContext('2d');
  new Chart(ctxPerc, {
    type: 'doughnut',
    data: {
      labels: <?php echo json_encode($rankingLabels, JSON_UNESCAPED_UNICODE); ?>,
      datasets: [{
        data: <?php echo json_encode($rankingPerc, JSON_UNESCAPED_UNICODE); ?>,
        backgroundColor: [
          'rgba(75, 192, 192, 0.8)',
          'rgba(255, 99, 132, 0.8)',
          'rgba(255, 205, 86, 0.8)',
          'rgba(153, 102, 255, 0.8)',
          'rgba(201, 203, 207, 0.8)'
        ],
        borderColor: '#fff',
        borderWidth: 2
      }]
    },
    options: {
      plugins: {
        title: {
          display: true,
          text: 'Percentual Atingido'
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              return context.label + ": " + context.raw + "%";
            }
          }
        },
        legend: {
          position: 'bottom'
        }
      },
      responsive: true
    }
  });
</script>
</body>
<?php include(FOOTER_FILE); ?>
</html>
