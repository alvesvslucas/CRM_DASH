<?php
session_start();
require_once '../../../db/db.php';
require_once '../../../absoluto.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'tv_indoor') {
  die("Acesso negado. Apenas usuários autorizados.");
}

$conn = getDatabaseConnection();

$periodStart = date('Y-m-01');
$periodEnd   = date('Y-m-t');
$metaPeriodo = date('Y-m-01');

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

$sqlTotais = "
  SELECT u.id, u.nome AS consultor_nome, SUM(d.valor_liquido) as total_pago
  FROM digitacoes d
  INNER JOIN users u ON u.id = d.consultor_id
  WHERE d.status = 'PAGO'
    AND d.data_pagamento BETWEEN ? AND ?
  GROUP BY u.id
";
$stmtTot = $conn->prepare($sqlTotais);
$stmtTot->bind_param("ss", $periodStart, $periodEnd);
$stmtTot->execute();
$resTot = $stmtTot->get_result();
$ranking = [];
while ($row = $resTot->fetch_assoc()) {
  $cid = $row['id'];
  $nome = $row['consultor_nome'];
  $totalPago = floatval($row['total_pago']);
  if ($totalPago == 0) continue;
  $meta = $metasConsultores[$cid]['meta_mensal'] ?? 0;
  $percent = ($meta > 0) ? round(($totalPago / $meta) * 100, 2) : 0;
  $ranking[] = [
    'consultor_id' => $cid,
    'consultor_nome' => $nome,
    'total_pago' => $totalPago,
    'meta' => $meta,
    'percent' => $percent
  ];
}
$stmtTot->close();

$sqlLast = "
  SELECT d.valor_liquido, d.nome AS cliente_nome, u.nome AS consultor_nome, d.data_pagamento
  FROM digitacoes d
  LEFT JOIN users u ON d.consultor_id = u.id
  WHERE d.status = 'PAGO'
  ORDER BY d.data_pagamento DESC
  LIMIT 1
";
$resLast = $conn->query($sqlLast);
$last = $resLast->fetch_assoc();
$conn->close();

$rankingLabels = array_column($ranking, 'consultor_nome');
$rankingTotals = array_column($ranking, 'total_pago');
$rankingMetas  = array_column($ranking, 'meta');
$rankingPerc   = array_column($ranking, 'percent');
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Dashboard TV - Consignado</title>
  <meta http-equiv="refresh" content="60">
  <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
  <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
  <style>
    body {
      background: #000;
      color: #fff;
      margin: 0;
      padding: 0;
      font-family: 'Poppins', sans-serif;
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
      padding: 20px;
    }

    .chart-container {
      width: 90%;
      max-width: 1000px;
      height: 400px;
      margin: 0 auto;
    }

    .logo {
      position: absolute;
      top: 10px;
      right: 20px;
      width: 120px;
    }
  </style>
</head>

<body>
  <img src="../../../assets/img/logo1.png" class="logo" alt="Credinowe Logo">
  <?php if (!empty($last)): ?>
    <script>
      window.onload = function() {
        let audio = new Audio('/sounds/sucesso.mp3');
        audio.play();
        let end = Date.now() + 4000;
        (function frame() {
          confetti({
            particleCount: 3,
            angle: 60,
            spread: 55,
            origin: {
              x: 0
            }
          });
          confetti({
            particleCount: 3,
            angle: 120,
            spread: 55,
            origin: {
              x: 1
            }
          });
          if (Date.now() < end) requestAnimationFrame(frame);
        })();
      };
    </script>
  <?php endif; ?>
  <div class="swiper mySwiper">
    <div class="swiper-wrapper">
      <div class="swiper-slide animate__animated animate__fadeIn">
        <h1 class="mb-4">Última Digitação</h1>
        <?php if ($last): ?>
          <p>Consultor: <strong><?= htmlspecialchars($last['consultor_nome']) ?></strong></p>
          <p>Cliente: <strong><?= htmlspecialchars($last['cliente_nome']) ?></strong></p>
          <p>Valor: <strong>R$ <?= number_format($last['valor_liquido'], 2, ',', '.') ?></strong></p>
        <?php else: ?>
          <p>Nenhuma digitação encontrada.</p>
        <?php endif; ?>
      </div>
      <div class="swiper-slide animate__animated animate__fadeIn">
        <h1>Ranking de Consultores</h1>
        <ul class="fs-3">
          <?php
          usort($ranking, fn($a, $b) => $b['total_pago'] <=> $a['total_pago']);
          $pos = 1;
          foreach ($ranking as $r):
          ?>
            <li><?= $pos++ ?>º - <?= htmlspecialchars($r['consultor_nome']) ?>: R$ <?= number_format($r['total_pago'], 2, ',', '.') ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="swiper-slide animate__animated animate__fadeIn">
        <h1>Comparativo</h1>
        <div class="chart-container">
          <canvas id="chartComp"></canvas>
        </div>
      </div>
      <div class="swiper-slide animate__animated animate__fadeIn">
        <h1>% da Meta</h1>
        <div class="chart-container">
          <canvas id="chartMeta"></canvas>
        </div>
      </div>
    </div>
  </div>
  <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
  <script>
    new Swiper('.mySwiper', {
      autoplay: {
        delay: 10000,
        disableOnInteraction: false
      },
      loop: true
    });

    const labels = <?= json_encode($rankingLabels, JSON_UNESCAPED_UNICODE) ?>;
    const pagos = <?= json_encode($rankingTotals) ?>;
    const metas = <?= json_encode($rankingMetas) ?>;
    const percentuais = <?= json_encode($rankingPerc) ?>;

    const chartComp = new Chart(document.getElementById('chartComp'), {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
            label: 'Pago',
            data: pagos,
            backgroundColor: '#27ae60'
          },
          {
            label: 'Meta',
            data: metas,
            backgroundColor: '#e74c3c'
          }
        ]
      },
      options: {
        plugins: {
          legend: {
            labels: {
              color: '#fff'
            }
          },
          datalabels: {
            color: '#fff',
            anchor: 'end',
            align: 'end',
            formatter: v => v.toLocaleString('pt-BR', {
              style: 'currency',
              currency: 'BRL'
            })
          }
        },
        scales: {
          x: {
            ticks: {
              color: '#fff'
            }
          },
          y: {
            ticks: {
              color: '#fff'
            },
            beginAtZero: true
          }
        }
      },
      plugins: [ChartDataLabels]
    });

    const chartMeta = new Chart(document.getElementById('chartMeta'), {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: '% da Meta',
          data: percentuais,
          backgroundColor: '#f1c40f'
        }]
      },
      options: {
        plugins: {
          legend: {
            labels: {
              color: '#fff'
            }
          },
          datalabels: {
            color: '#fff',
            anchor: 'end',
            align: 'end',
            formatter: v => v + '%'
          }
        },
        scales: {
          x: {
            ticks: {
              color: '#fff'
            }
          },
          y: {
            ticks: {
              color: '#fff'
            },
            beginAtZero: true,
            max: 100
          }
        }
      },
      plugins: [ChartDataLabels]
    });
  </script>
</body>

</html>