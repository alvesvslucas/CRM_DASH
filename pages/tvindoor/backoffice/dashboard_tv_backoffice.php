<?php
// -------------------------------------------------
// Exemplo de código completo para o dashboard TV (mês vigente)
// -------------------------------------------------
include_once '../../../db/db.php';
include_once '../../../absoluto.php';

$conn = getDatabaseConnection();

// DEFINIÇÕES DE PERÍODO (mês vigente)
$today = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');
$lastDayOfMonth  = date('Y-m-t');

// 1) Última Digitação (para confetes e áudio) – apenas do mês vigente
$sqlLast = "
    SELECT d.id,
           d.nome AS cliente_nome,
           d.valor_liquido,
           d.data_pagamento,
           u.nome AS digitador_nome
      FROM digitacoes d
      JOIN users u ON d.digitador_id = u.id
     WHERE d.status = 'PAGO'
       AND d.data_pagamento BETWEEN '$firstDayOfMonth' AND '$lastDayOfMonth'
  ORDER BY d.data_pagamento DESC
     LIMIT 1
";
$resultLast = $conn->query($sqlLast);
$lastDigit = $resultLast->fetch_assoc();

// 2) Totais Pagos (Geral) – apenas do mês vigente
$sqlTotalPaid = "
    SELECT COUNT(*) AS totalPaid,
           SUM(valor_liquido) AS totalValue
      FROM digitacoes
     WHERE status = 'PAGO'
       AND data_pagamento BETWEEN '$firstDayOfMonth' AND '$lastDayOfMonth'
";
$resultTotalPaid = $conn->query($sqlTotalPaid);
$rowTotalPaid = $resultTotalPaid->fetch_assoc();
$totalPaid  = $rowTotalPaid['totalPaid']  ?? 0;
$totalValue = $rowTotalPaid['totalValue'] ?? 0;

// 3) Metas Globais (diária e mensal) – sem alteração
$sqlMetaGlobal = "
    SELECT meta_diaria, meta_mensal
      FROM metas_global_credesh
     LIMIT 1
";
$resultMetaGlobal = $conn->query($sqlMetaGlobal);
if ($resultMetaGlobal->num_rows > 0) {
  $metaGlobal = $resultMetaGlobal->fetch_assoc();
} else {
  $metaGlobal = ['meta_diaria' => 0, 'meta_mensal' => 0];
}

// 4) Totais Diário e Mensal – os filtros já estão adequados
$sqlPaidOverallDaily = "
    SELECT SUM(valor_liquido) AS totalValueDaily
      FROM digitacoes
     WHERE status = 'PAGO'
       AND DATE(data_pagamento) = '$today'
";
$resDaily = $conn->query($sqlPaidOverallDaily);
$rowDaily = $resDaily->fetch_assoc();
$totalValueOverallDaily = $rowDaily['totalValueDaily'] ?? 0;

$sqlPaidOverallMonthly = "
    SELECT SUM(valor_liquido) AS totalValueMonthly
      FROM digitacoes
     WHERE status = 'PAGO'
       AND data_pagamento BETWEEN '$firstDayOfMonth' AND '$lastDayOfMonth'
";
$resMonthly = $conn->query($sqlPaidOverallMonthly);
$rowMonthly = $resMonthly->fetch_assoc();
$totalValueOverallMonthly = $rowMonthly['totalValueMonthly'] ?? 0;

// Porcentagens das Metas
$dailyGoalPerc = ($metaGlobal['meta_diaria'] > 0)
  ? round(($totalValueOverallDaily / $metaGlobal['meta_diaria']) * 100, 2)
  : 0;
$monthlyGoalPerc = ($metaGlobal['meta_mensal'] > 0)
  ? round(($totalValueOverallMonthly / $metaGlobal['meta_mensal']) * 100, 2)
  : 0;

// 5) Ranking Mensal (Top 10 digitadores) – filtro de mês vigente
$sqlRanking = "
    SELECT d.digitador_id,
           u.nome AS digitador_nome,
           SUM(d.valor_liquido) AS totalValueMonthly,
           MAX(d.data_pagamento) AS lastPayment
      FROM digitacoes d
      JOIN users u ON d.digitador_id = u.id
     WHERE d.status = 'PAGO'
       AND d.data_pagamento BETWEEN '$firstDayOfMonth' AND '$lastDayOfMonth'
  GROUP BY d.digitador_id
  ORDER BY totalValueMonthly DESC
     LIMIT 10
";
$resRanking = $conn->query($sqlRanking);
$rankingData = [];
while ($row = $resRanking->fetch_assoc()) {
  $rankingData[] = $row;
}

// 6) CRM Digitador – filtrando apenas o mês vigente
$sqlCRM = "
    SELECT d.digitador_id,
           u.nome AS digitador_nome,
           COUNT(*) AS totalDigitacoes,
           SUM(d.valor_liquido) AS totalCRMValue
      FROM digitacoes d
      JOIN users u ON d.digitador_id = u.id
     WHERE d.status = 'PAGO'
       AND d.data_pagamento BETWEEN '$firstDayOfMonth' AND '$lastDayOfMonth'
  GROUP BY d.digitador_id
  ORDER BY totalDigitacoes DESC
";
$resCRM = $conn->query($sqlCRM);
$crmData = [];
while ($row = $resCRM->fetch_assoc()) {
  $crmData[] = $row;
}

// 7) Pagamentos Diário e Mensal por Digitador – já com filtros atuais
$sqlPaidDaily = "
    SELECT d.digitador_id,
           u.nome AS digitador_nome,
           SUM(d.valor_liquido) AS totalValueDaily
      FROM digitacoes d
      JOIN users u ON d.digitador_id = u.id
     WHERE d.status = 'PAGO'
       AND DATE(d.data_pagamento) = '$today'
  GROUP BY d.digitador_id
";
$resPaidDaily = $conn->query($sqlPaidDaily);
$paidDailyData = [];
while ($row = $resPaidDaily->fetch_assoc()) {
  $paidDailyData[] = $row;
}

$sqlPaidMonthly = "
    SELECT d.digitador_id,
           u.nome AS digitador_nome,
           SUM(d.valor_liquido) AS totalValueMonthly
      FROM digitacoes d
      JOIN users u ON d.digitador_id = u.id
     WHERE d.status = 'PAGO'
       AND d.data_pagamento BETWEEN '$firstDayOfMonth' AND '$lastDayOfMonth'
  GROUP BY d.digitador_id
";
$resPaidMonthly = $conn->query($sqlPaidMonthly);
$paidMonthlyData = [];
while ($row = $resPaidMonthly->fetch_assoc()) {
  $paidMonthlyData[] = $row;
}

// Combina dados diário e mensal
$paidData = [];
foreach ($paidDailyData as $row) {
  $id = $row['digitador_id'];
  $paidData[$id] = [
    'digitador_nome'    => $row['digitador_nome'],
    'totalValueDaily'   => $row['totalValueDaily'] ?? 0,
    'totalValueMonthly' => 0
  ];
}
foreach ($paidMonthlyData as $row) {
  $id = $row['digitador_id'];
  if (isset($paidData[$id])) {
    $paidData[$id]['totalValueMonthly'] = $row['totalValueMonthly'] ?? 0;
  } else {
    $paidData[$id] = [
      'digitador_nome'    => $row['digitador_nome'],
      'totalValueDaily'   => 0,
      'totalValueMonthly' => $row['totalValueMonthly'] ?? 0
    ];
  }
}

$conn->close();

// Dados para gráficos
$crmLabels  = array_column($crmData, 'digitador_nome');
$crmEntries = array_column($crmData, 'totalDigitacoes');

$paymentLabels   = [];
$dailyPayments   = [];
$monthlyPayments = [];
foreach ($paidData as $p) {
  $paymentLabels[]   = $p['digitador_nome'];
  $dailyPayments[]   = $p['totalValueDaily'];
  $monthlyPayments[] = $p['totalValueMonthly'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Dashboard TV Indoor - Mês Vigente</title>
  <!-- Atualiza a cada 60 segundos (opcional) -->
  <meta http-equiv="refresh" content="60">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Swiper CSS -->
  <link rel="stylesheet" href="https://unpkg.com/swiper@9/swiper-bundle.min.css">
  <!-- Animate.css -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- Plugin para DataLabels do Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
  <!-- Confetti -->
  <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>

  <style>
    body {
      background: #000;
      color: #fff;
      font-family: 'Poppins', sans-serif;
      margin: 0;
      padding: 0;
      overflow: hidden;
    }
    .swiper {
      width: 100%;
      height: 100vh;
    }
    .swiper-slide {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 30px;
      transition: all 1s ease-in-out;
    }
    .slide-title {
      font-size: 4rem;
      font-weight: 600;
      margin-bottom: 30px;
    }
    .card {
      background: #222;
      border: 1px solid #444;
      color: #fff;
      margin-bottom: 20px;
      max-width: 80%;
      margin: 0 auto 20px;
    }
    .card-header {
      background: #333;
      font-size: 2rem;
    }
    .card-body {
      font-size: 2rem;
    }
    .table {
      font-size: 2rem;
    }
    .table thead th {
      font-weight: 600;
    }
    h1, h2, h3, h4 {
      font-weight: 600;
    }
    h1 { font-size: 4rem; }
    h2 { font-size: 3rem; margin-bottom: 20px; }
    h3, h4 { font-size: 2.5rem; }
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

  <!-- Se houver última digitação, dispara áudio e confetes -->
  <?php if (!empty($lastDigit)): ?>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        try {
          let audio = new Audio('<?= AUDIO_PATH ?>');
          audio.play().catch(err => console.warn("Áudio bloqueado (autoplay):", err));
        } catch (e) {
          console.warn("Erro ao tocar áudio:", e);
        }
        let end = Date.now() + 3000;
        (function frame() {
          confetti({
            particleCount: 4,
            angle: 60,
            spread: 55,
            origin: { x: 0 }
          });
          confetti({
            particleCount: 4,
            angle: 120,
            spread: 55,
            origin: { x: 1 }
          });
          if (Date.now() < end) requestAnimationFrame(frame);
        })();
      });
    </script>
  <?php endif; ?>

  <div class="swiper mySwiper">
    <div class="swiper-wrapper">
      <!-- Slide 1: Última Digitação -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="mb-4">Última Digitação</h1>
        <?php if (!empty($lastDigit)): ?>
          <div class="card border-success">
            <div class="card-header bg-success text-white">Última Digitação</div>
            <div class="card-body">
              <p>Digitador: <strong><?= htmlspecialchars($lastDigit['digitador_nome']) ?></strong></p>
              <p>Cliente: <strong><?= htmlspecialchars($lastDigit['cliente_nome']) ?></strong></p>
              <p>Valor: <strong>R$ <?= number_format($lastDigit['valor_liquido'], 2, ',', '.') ?></strong></p>
              <p>Data: <?= date('d/m/Y H:i', strtotime($lastDigit['data_pagamento'])) ?></p>
            </div>
          </div>
        <?php else: ?>
          <p>Nenhuma digitação encontrada.</p>
        <?php endif; ?>
      </div>

      <!-- Slide 2: Ranking Mensal (Top 10) -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h2>Ranking Mensal</h2>
        <div class="table-responsive w-75 mx-auto">
          <?php if (!empty($rankingData)): ?>
            <table class="table table-dark table-striped table-hover">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Digitador</th>
                  <th>Valor (R$)</th>
                  <th>Último Pagto</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $pos = 1;
                foreach ($rankingData as $r):
                  $lastPayment = $r['lastPayment'] ? date('d/m/Y H:i', strtotime($r['lastPayment'])) : '-';
                ?>
                  <tr class="pulse">
                    <td><?= $pos++ ?></td>
                    <td><?= htmlspecialchars($r['digitador_nome']) ?></td>
                    <td><?= number_format($r['totalValueMonthly'], 2, ',', '.') ?></td>
                    <td><?= $lastPayment ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p>Nenhum dado disponível.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Slide 3: Pagos Geral -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <div class="slide-title">Pagos Geral</div>
        <div class="card border-info">
          <div class="card-header bg-info text-white">Pagos Geral</div>
          <div class="card-body">
            <p style="font-size: 2.5rem;">Total de Pagos: <span class="pulse"><?= $totalPaid ?></span></p>
            <p style="font-size: 2.5rem;">Valor Total: R$ <span class="pulse"><?= number_format($totalValue, 2, ',', '.') ?></span></p>
          </div>
        </div>
      </div>

      <!-- Slide 4: Metas (Diária e Mensal) -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <div class="slide-title">Metas</div>
        <div class="row w-100">
          <div class="col-md-6">
            <div class="card border-warning">
              <div class="card-header bg-warning text-white">Meta Diária</div>
              <div class="card-body">
                <p>Meta: R$ <?= number_format($metaGlobal['meta_diaria'], 2, ',', '.') ?></p>
                <p>Realizado Hoje: R$ <?= number_format($totalValueOverallDaily, 2, ',', '.') ?></p>
                <p>Atingido: <?= $dailyGoalPerc ?>%</p>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card border-warning">
              <div class="card-header bg-warning text-white">Meta Mensal</div>
              <div class="card-body">
                <p>Meta: R$ <?= number_format($metaGlobal['meta_mensal'], 2, ',', '.') ?></p>
                <p>Realizado no Mês: R$ <?= number_format($totalValueOverallMonthly, 2, ',', '.') ?></p>
                <p>Atingido: <?= $monthlyGoalPerc ?>%</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 5: CRM Digitador -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h2>CRM Digitador</h2>
        <div class="table-responsive w-75 mx-auto">
          <?php if (!empty($crmData)): ?>
            <table class="table table-dark table-striped table-hover">
              <thead>
                <tr>
                  <th>Digitador</th>
                  <th>Entradas</th>
                  <th>Valor CRM (R$)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($crmData as $c): ?>
                  <tr class="pulse">
                    <td><?= htmlspecialchars($c['digitador_nome']) ?></td>
                    <td><?= $c['totalDigitacoes'] ?></td>
                    <td><?= number_format($c['totalCRMValue'], 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p>Nenhum dado disponível.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Slide 6: Pagamentos: Diário vs Mensal -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h2>Pagamentos: Diário vs Mensal</h2>
        <div class="table-responsive w-75 mx-auto">
          <?php if (!empty($paidData)): ?>
            <table class="table table-dark table-striped table-hover">
              <thead>
                <tr>
                  <th>Digitador</th>
                  <th>Dia (R$)</th>
                  <th>Mês (R$)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($paidData as $p): ?>
                  <tr class="pulse">
                    <td><?= htmlspecialchars($p['digitador_nome']) ?></td>
                    <td><?= number_format($p['totalValueDaily'], 2, ',', '.') ?></td>
                    <td><?= number_format($p['totalValueMonthly'], 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p>Nenhum dado disponível.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Slide 7: Gráfico CRM -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h2>Gráfico: Entradas no CRM</h2>
        <div class="chart-container">
          <canvas id="chartCRM"></canvas>
        </div>
      </div>

      <!-- Slide 8: Gráfico Pagamentos (Diário vs Mensal) -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h2>Gráfico: Pagamentos Diário vs Mensal</h2>
        <div class="chart-container">
          <canvas id="chartPayments"></canvas>
        </div>
      </div>
    </div>
    <!-- Elemento de paginação (opcional para navegação e depuração) -->
    <div class="swiper-pagination"></div>
  </div>

  <!-- JS do Bootstrap e do Swiper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/swiper@9/swiper-bundle.min.js"></script>

  <!-- Inicialização do Swiper -->
  <script>
    window.addEventListener('load', function() {
      const swiper = new Swiper('.mySwiper', {
        loop: true,
        effect: 'slide', // Pode alterar para 'fade' se preferir
        autoplay: {
          delay: 8000, // 8 segundos por slide
          disableOnInteraction: false
        },
        pagination: {
          el: '.swiper-pagination',
          clickable: true
        },
        observer: true,
        observeParents: true
      });

      // Animação para pulsar nas tabelas a cada 15 segundos
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

  <!-- Inicialização dos Gráficos com efeitos especiais -->
  <script>
    // Registra o plugin para DataLabels
    Chart.register(ChartDataLabels);

    // Função para gerar cores com destaque para os top 3
    function gerarCores(dados) {
      return dados.map((_, i) => {
        if(i === 0) return 'gold';       // Top 1
        if(i === 1) return 'silver';     // Top 2
        if(i === 2) return '#cd7f32';    // Top 3
        return 'rgba(75, 192, 192, 0.6)'; // Demais
      });
    }

    // Gráfico 1: Entradas no CRM
    const crmLabels = <?= json_encode($crmLabels, JSON_UNESCAPED_UNICODE); ?>;
    const crmEntries = <?= json_encode($crmEntries); ?>;
    const ctxCRM = document.getElementById('chartCRM').getContext('2d');
    new Chart(ctxCRM, {
      type: 'bar',
      data: {
        labels: crmLabels,
        datasets: [{
          label: 'Entradas no CRM',
          data: crmEntries,
          backgroundColor: gerarCores(crmEntries),
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
          duration: 2000,
          easing: 'easeOutBounce'
        },
        plugins: {
          legend: { display: false },
          datalabels: {
            anchor: 'end',
            align: 'top',
            color: '#fff',
            font: { weight: 'bold', size: 16 },
            formatter: Math.round
          },
          tooltip: {
            callbacks: {
              label: function(ctx) {
                return 'Entradas: ' + ctx.raw;
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { color: '#fff', font: { size: 18 } }
          },
          x: {
            ticks: { color: '#fff', font: { size: 18 } }
          }
        }
      },
      plugins: [ChartDataLabels]
    });

    // Gráfico 2: Pagamentos (Diário vs Mensal)
    const paymentLabels = <?= json_encode($paymentLabels, JSON_UNESCAPED_UNICODE); ?>;
    const dailyPayments = <?= json_encode($dailyPayments); ?>;
    const monthlyPayments = <?= json_encode($monthlyPayments); ?>;
    const ctxPayments = document.getElementById('chartPayments').getContext('2d');
    new Chart(ctxPayments, {
      type: 'bar',
      data: {
        labels: paymentLabels,
        datasets: [{
            label: 'Diário (R$)',
            data: dailyPayments,
            backgroundColor: gerarCores(dailyPayments)
          },
          {
            label: 'Mensal (R$)',
            data: monthlyPayments,
            backgroundColor: gerarCores(monthlyPayments)
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
          duration: 2000,
          easing: 'easeOutQuart'
        },
        plugins: {
          datalabels: {
            color: '#fff',
            font: { weight: 'bold', size: 14 },
            formatter: (value) => 'R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 2}),
            anchor: 'end',
            align: 'top'
          },
          tooltip: {
            callbacks: {
              label: function(ctx) {
                return ctx.dataset.label + ': R$ ' + ctx.raw.toLocaleString('pt-BR', {minimumFractionDigits: 2});
              }
            }
          },
          legend: {
            labels: { color: '#fff', font: { size: 20 } }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { color: '#fff', font: { size: 18 } }
          },
          x: {
            ticks: { color: '#fff', font: { size: 18 } }
          }
        }
      },
      plugins: [ChartDataLabels]
    });
  </script>

</body>
</html>
