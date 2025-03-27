<?php
session_start();
// Se quiser verificar supervisor/setor, descomente:
// if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'supervisor' || ($_SESSION['setor'] ?? '') !== 'Energia') {
//   die("Acesso negado.");
// }

require_once __DIR__ . '/../../../absoluto.php';
require_once __DIR__ . '/../../../db/config.php';

// Conexão com o banco
$conn = new mysqli($host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
  die("Falha na conexão: " . $conn->connect_error);
}

// Definições de data
$currentMonth = date('Y-m');   // Ex: "2025-03"
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
 * 2) VENDA GERAL (Mês)
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
 * 6A) Metas e Faltas (Agentes)
 * --------------------------------------------------------- */
$sqlAgentesMetas = "
SELECT 
  a.id AS consultor_id,
  a.nome AS consultor,
  COALESCE(m.meta_mes, 0) AS meta_mes,
  COALESCE(m.meta_dia, 0) AS meta_dia,
  IFNULL(( SELECT SUM(v.valor_venda)
           FROM vendas_energia v
           WHERE v.status = 'pago'
             AND v.agente_id = a.id
             AND DATE_FORMAT(v.data_registro, '%Y-%m') = ?
  ), 0) AS total_pago_mes,
  IFNULL(( SELECT SUM(v.valor_venda)
           FROM vendas_energia v
           WHERE v.status = 'pago'
             AND v.agente_id = a.id
             AND DATE(v.data_registro) = ?
  ), 0) AS total_pago_dia
FROM agentes_energia a
LEFT JOIN metas_agentes_energia m 
       ON m.agente_id = a.id
      AND m.periodo = ?
WHERE a.ativo = 1
ORDER BY a.nome
";
$stmt = $conn->prepare($sqlAgentesMetas);
$stmt->bind_param("sss", $currentMonth, $filtroDia, $currentMonth);
$stmt->execute();
$resAgentes = $stmt->get_result();
$agentesMetas = [];
while ($row = $resAgentes->fetch_assoc()) {
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
 * 7) Contratos por Setor (Dia e Mês)
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

// Gerar ranking de consultores com base no total_mes
$rankingConsultores = $consultores;
usort($rankingConsultores, function($a, $b) {
  return $b['total_mes'] <=> $a['total_mes'];
});

// Estatísticas Gerais (exemplo)
$totalConsultores = count($consultores);
$percentualGeral = ($metaMes > 0) ? round(($vendaGeral['pagos_geral'] / $metaMes) * 100, 2) : 0;
$mediaConsultor = ($totalConsultores > 0) ? round($vendaGeral['pagos_geral'] / $totalConsultores, 2) : 0;
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Dashboard Energia (TV)</title>
  <!-- Atualiza a cada 60 segundos (opcional) -->
  <meta http-equiv="refresh" content="60">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- Swiper CSS -->
  <link rel="stylesheet" href="https://unpkg.com/swiper@9/swiper-bundle.min.css">
  <!-- Animate.css -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- Chart.js DataLabels Plugin -->
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
  <!-- Canvas Confetti -->
  <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
  
  <style>
    /* Fundo com gradiente */
    body {
      background: radial-gradient(circle at center, #1a1a1a, #000);
      color: #fff;
      margin: 0;
      padding: 0;
      font-family: 'Poppins', sans-serif;
      overflow: hidden;
    }
    .swiper {
      width: 100%;
      height: 100vh;
    }
    .swiper-slide {
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      padding: 20px;
    }
    .slide-title {
      font-size: 3rem;
      font-weight: 700;
      margin-bottom: 30px;
      color: #00ffcc;
      text-shadow: 2px 2px 6px rgba(0, 255, 204, 0.6);
    }
    .table {
      color: #fff;
      font-size: 1.5rem;
      border-radius: 6px;
      overflow: hidden;
    }
    .table thead th {
      color: #00ffcc;
      font-weight: bold;
      background: #333;
      border-bottom: 1px solid #444;
    }
    .table-striped > tbody > tr:nth-of-type(odd) {
      background-color: rgba(255, 255, 255, 0.05);
    }
    .table-hover tbody tr:hover {
      background-color: rgba(0, 255, 204, 0.1);
    }
    .card {
      background: #222;
      border: 1px solid #444;
      color: #fff;
      max-width: 80%;
      margin: 0 auto 20px;
      box-shadow: 0 0 15px rgba(0, 255, 204, 0.1);
    }
    .card-header {
      background: #333;
      font-size: 2rem;
      border-bottom: 1px solid #444;
    }
    .chart-container {
      width: 80%;
      max-width: 1400px;
      height: 600px;
      margin: 0 auto;
    }
    .pulse {
      animation: pulse-animation 1s ease-in-out;
    }
    @keyframes pulse-animation {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }
  </style>
</head>

<body>

  <!-- Confetti ao carregar a página -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      confetti({ particleCount: 20, spread: 70, origin: { y: 0.6 } });
    });
  </script>

  <div class="swiper mySwiper">
    <div class="swiper-wrapper">

      <!-- Slide 1: Metas do Setor -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Metas do Setor</h1>
        <div class="card border-success mb-4">
          <div class="card-header bg-success">Metas do Mês</div>
          <div class="card-body">
            <p style="font-size: 2rem;">Mensal: R$ <?= number_format($metaMes, 2, ',', '.') ?></p>
            <p style="font-size: 2rem;">Diária: R$ <?= number_format($metaDia, 2, ',', '.') ?></p>
          </div>
        </div>
        <p style="font-size: 1.8rem;">Data Filtrada: <strong><?= $filtroDia ?></strong></p>
      </div>

      <!-- Slide 2: Venda Geral (Mês) -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Venda Geral (Mês)</h1>
        <div class="table-responsive w-75 mx-auto">
          <table class="table table-dark table-striped table-hover">
            <tbody>
              <tr class="pulse">
                <td>Pagos:</td>
                <td>R$ <?= number_format($vendaGeral['pagos_geral'] ?? 0, 2, ',', '.') ?></td>
              </tr>
              <tr class="pulse">
                <td>Pendentes:</td>
                <td>R$ <?= number_format($vendaGeral['pagos_pendentes'] ?? 0, 2, ',', '.') ?></td>
              </tr>
              <tr class="pulse">
                <td>Aguardando:</td>
                <td>R$ <?= number_format($vendaGeral['aguardando_formalizacao'] ?? 0, 2, ',', '.') ?></td>
              </tr>
              <tr class="pulse">
                <td>Formalizados:</td>
                <td>R$ <?= number_format($vendaGeral['formalizados'] ?? 0, 2, ',', '.') ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Slide 3: Pendentes (Mês) -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Pendentes (Mês)</h1>
        <?php if (!empty($pendentes)): ?>
          <div class="table-responsive w-75 mx-auto">
            <table class="table table-dark table-striped table-hover">
              <thead>
                <tr>
                  <th>Agente</th>
                  <th>Total Pendente (R$)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pendentes as $p): ?>
                  <tr class="pulse">
                    <td><?= htmlspecialchars($p['agente']) ?></td>
                    <td>R$ <?= number_format($p['total_pendente'], 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p>Nenhum valor pendente neste mês.</p>
        <?php endif; ?>
      </div>

      <!-- Slide 4: Pagos (Mês) -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Pagos (Mês)</h1>
        <?php if (!empty($pagos)): ?>
          <div class="table-responsive w-75 mx-auto">
            <table class="table table-dark table-striped table-hover">
              <thead>
                <tr>
                  <th>Agente</th>
                  <th>Total Pago (R$)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pagos as $p): ?>
                  <tr class="pulse">
                    <td><?= htmlspecialchars($p['agente']) ?></td>
                    <td>R$ <?= number_format($p['total_pago'], 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p>Nenhum valor pago neste mês.</p>
        <?php endif; ?>
      </div>

      <!-- Slide 5: Venda por Consultor (Mês) -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Venda por Consultor (Mês)</h1>
        <div class="table-responsive w-75 mx-auto">
          <table class="table table-dark table-striped table-hover">
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
                <tr class="pulse">
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

      <!-- Slide 6: Ranking de Consultores -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Ranking de Consultores</h1>
        <div class="table-responsive w-75 mx-auto">
          <table class="table table-dark table-striped table-hover">
            <thead>
              <tr>
                <th>#</th>
                <th>Consultor</th>
                <th>Total (Mês)</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              $pos = 1;
              foreach ($rankingConsultores as $r): 
              ?>
              <tr class="pulse">
                <td><?= $pos++ ?></td>
                <td><?= htmlspecialchars($r['consultor']) ?></td>
                <td>R$ <?= number_format($r['total_mes'], 2, ',', '.') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Slide 7: Estatísticas Gerais -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Estatísticas Gerais</h1>
        <div class="card border-info mb-4">
          <div class="card-header bg-info">Resumo</div>
          <div class="card-body">
            <p style="font-size: 2rem;">Total de Vendas Pagas: R$ <?= number_format($vendaGeral['pagos_geral'] ?? 0, 2, ',', '.') ?></p>
            <p style="font-size: 2rem;">Meta do Mês: R$ <?= number_format($metaMes, 2, ',', '.') ?></p>
            <p style="font-size: 2rem;">% Meta Atingida: <?= $metaMes > 0 ? number_format(($vendaGeral['pagos_geral'] / $metaMes) * 100, 2, ',', '.') : 0 ?>%</p>
            <p style="font-size: 2rem;">Número de Consultores: <?= $totalConsultores ?></p>
            <p style="font-size: 2rem;">Média por Consultor: R$ <?= number_format($mediaConsultor, 2, ',', '.') ?></p>
          </div>
        </div>
      </div>

      <!-- Slide 8: Contratos por Setor (Dia) -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Contratos por Setor (Dia: <?= htmlspecialchars($filtroDia) ?>)</h1>
        <?php if (!empty($contratosDia)): ?>
          <div class="table-responsive w-75 mx-auto">
            <table class="table table-dark table-striped table-hover">
              <thead>
                <tr>
                  <th>Setor</th>
                  <th>Quantidade</th>
                  <th>Total (R$)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($contratosDia as $cd): ?>
                  <tr class="pulse">
                    <td><?= htmlspecialchars($cd['setor']) ?></td>
                    <td><?= $cd['qtd_contratos'] ?></td>
                    <td>R$ <?= number_format($cd['total_contratos'], 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p>Nenhum contrato registrado neste dia.</p>
        <?php endif; ?>
      </div>

      <!-- Slide 9: Contratos por Setor (Mês) -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Contratos por Setor (Mês: <?= htmlspecialchars($currentMonth) ?>)</h1>
        <?php if (!empty($contratosMes)): ?>
          <div class="table-responsive w-75 mx-auto">
            <table class="table table-dark table-striped table-hover">
              <thead>
                <tr>
                  <th>Setor</th>
                  <th>Quantidade</th>
                  <th>Total (R$)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($contratosMes as $cm): ?>
                  <tr class="pulse">
                    <td><?= htmlspecialchars($cm['setor']) ?></td>
                    <td><?= $cm['qtd_contratos'] ?></td>
                    <td>R$ <?= number_format($cm['total_contratos'], 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p>Nenhum contrato registrado neste mês.</p>
        <?php endif; ?>
      </div>

      <!-- Slide 10: Gráfico de Venda Geral (Mês) -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Gráfico: Venda Geral (Mês)</h1>
        <div class="chart-container">
          <canvas id="graficoVendaGeral"></canvas>
        </div>
      </div>

      <!-- Slide 11: Gráfico de Venda por Consultor (Mês) -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Gráfico: Venda por Consultor (Mês)</h1>
        <div class="chart-container">
          <canvas id="graficoConsultores"></canvas>
        </div>
      </div>

    </div> <!-- fim do swiper-wrapper -->
    <div class="swiper-pagination"></div>
  </div> <!-- fim do swiper -->

  <!-- Swiper JS -->
  <script src="https://unpkg.com/swiper@9/swiper-bundle.min.js"></script>
  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Inicialização do Swiper
    document.addEventListener('DOMContentLoaded', function() {
      const swiper = new Swiper('.mySwiper', {
        loop: true,
        effect: 'coverflow',
        coverflowEffect: {
          rotate: 40,
          slideShadows: false
        },
        autoplay: {
          delay: 8000,
          disableOnInteraction: false
        },
        pagination: {
          el: '.swiper-pagination',
          clickable: true
        },
        observer: true,
        observeParents: true
      });

      // Dispara confetes nos slides de Ranking (índice 5) e Estatísticas (índice 6) 
      // (Lembre: os índices reais com loop são acessíveis por swiper.realIndex)
      swiper.on('slideChangeTransitionEnd', function() {
        if (swiper.realIndex === 4 || swiper.realIndex === 5) {
          confetti({
            particleCount: 10,
            spread: 70,
            origin: { y: 0.6 }
          });
        }
      });

      // "Pulsa" as linhas da tabela a cada 15 segundos
      setInterval(() => {
        const elements = document.querySelectorAll('.pulse');
        elements.forEach(el => {
          el.classList.remove('pulse');
          void el.offsetWidth;
          el.classList.add('pulse');
        });
      }, 15000);
    });
  </script>

  <!-- GRÁFICOS Chart.js -->
  <script>
    Chart.register(ChartDataLabels);

    // Gráfico 1: Venda Geral
    const ctxGeral = document.getElementById('graficoVendaGeral').getContext('2d');
    new Chart(ctxGeral, {
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
        maintainAspectRatio: false,
        plugins: {
          datalabels: {
            color: '#fff',
            font: { weight: 'bold', size: 16 },
            anchor: 'end',
            align: 'top',
            formatter: (value) => 'R$ ' + parseFloat(value).toLocaleString('pt-BR', {minimumFractionDigits: 2})
          },
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(ctx) {
                const val = ctx.raw || 0;
                return 'R$ ' + parseFloat(val).toLocaleString('pt-BR', {minimumFractionDigits: 2});
              }
            }
          }
        },
        scales: {
          y: { beginAtZero: true, ticks: { color: '#fff' } },
          x: { ticks: { color: '#fff' } }
        }
      }
    });

    // Gráfico 2: Venda por Consultor
    const ctxConsultores = document.getElementById('graficoConsultores').getContext('2d');
    const consultorLabels = <?= json_encode(array_column($consultores, 'consultor'), JSON_UNESCAPED_UNICODE); ?>;
    const consultorTotal = <?= json_encode(array_map('floatval', array_column($consultores, 'total_mes'))); ?>;
    const consultorMeta  = <?= json_encode(array_map('floatval', array_column($consultores, 'meta_mes'))); ?>;

    new Chart(ctxConsultores, {
      type: 'bar',
      data: {
        labels: consultorLabels,
        datasets: [{
            label: 'Total (Mês)',
            data: consultorTotal,
            backgroundColor: '#3498db'
          },
          {
            label: 'Meta (Mês)',
            data: consultorMeta,
            backgroundColor: '#95a5a6'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          datalabels: {
            color: '#fff',
            font: { weight: 'bold', size: 14 },
            anchor: 'end',
            align: 'top',
            formatter: (value) => 'R$ ' + parseFloat(value).toLocaleString('pt-BR', {minimumFractionDigits: 2})
          },
          tooltip: {
            callbacks: {
              label: function(ctx) {
                const val = ctx.raw || 0;
                return ctx.dataset.label + ': R$ ' + parseFloat(val).toLocaleString('pt-BR', {minimumFractionDigits: 2});
              }
            }
          },
          legend: {
            labels: { color: '#fff', font: { size: 16 } }
          }
        },
        scales: {
          y: { beginAtZero: true, ticks: { color: '#fff' } },
          x: { ticks: { color: '#fff' } }
        }
      }
    });
  </script>

</body>
</html>
