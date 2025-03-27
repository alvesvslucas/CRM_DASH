<?php
// dashboard_tv_fgts.php

session_start();
// Se necessário, verifique a sessão ou o perfil do usuário aqui
// if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'supervisor' || ($_SESSION['setor'] ?? '') !== 'FGTS') {
//   die("Acesso negado.");
// }

require_once __DIR__ . '/../../../absoluto.php';
require_once __DIR__ . '/../../../db/config.php';

// Conexão com o banco
$conn = new mysqli($host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Para o dashboard TV, usamos a data de hoje como filtro
$start_date = date('Y-m-d');
$end_date   = date('Y-m-d');
$start_datetime = $start_date . " 00:00:00";
$end_datetime   = $end_date   . " 23:59:59";

// 1) Meta do Setor FGTS (tabela metas_setor_fgts)
$resSector = $conn->query("SELECT meta_diaria, meta_mensal FROM metas_setor_fgts LIMIT 1");
$meta_setor_diaria = 0;
$meta_setor_mensal = 0;
if ($resSector && $resSector->num_rows > 0) {
    $row = $resSector->fetch_assoc();
    $meta_setor_diaria = floatval($row['meta_diaria']);
    $meta_setor_mensal = floatval($row['meta_mensal']);
}

// 2) Total FGTS Pago no período (diário)
$resTotal = $conn->query("
    SELECT SUM(valor) AS total_pago 
    FROM metas_fgts 
    WHERE status = 'pago' 
      AND created_at BETWEEN '$start_datetime' AND '$end_datetime'
");
$total_pago = 0;
if ($resTotal && $row = $resTotal->fetch_assoc()) {
    $total_pago = floatval($row['total_pago']);
}

// 3) Cálculo: quanto falta e % abaixo da meta (diária)
$faltando = max($meta_setor_diaria - $total_pago, 0);
$percent_below = ($meta_setor_diaria > 0) 
    ? (($meta_setor_diaria - $total_pago) / $meta_setor_diaria * 100) 
    : 0;

// 4) Desempenho dos Agentes FGTS
$agents_data = [];
$sqlAgents = "
    SELECT a.id, a.nome, ma.meta_diaria 
    FROM agentes_fgts a 
    LEFT JOIN metas_agentes_fgts ma ON a.id = ma.agente_id 
    ORDER BY a.nome ASC
";
$resAgents = $conn->query($sqlAgents);
while ($agent = $resAgents->fetch_assoc()) {
    $agent_id = $agent['id'];
    $meta_agent = isset($agent['meta_diaria']) ? floatval($agent['meta_diaria']) : 0;

    // Soma do valor pago pelo agente no período
    $resAgentTotal = $conn->query("
        SELECT SUM(valor) AS total_pago 
        FROM metas_fgts 
        WHERE agente_id = $agent_id 
          AND status = 'pago' 
          AND created_at BETWEEN '$start_datetime' AND '$end_datetime'
    ");
    $total_agent = 0;
    if ($resAgentTotal && $row = $resAgentTotal->fetch_assoc()) {
        $total_agent = floatval($row['total_pago']);
    }

    $agents_data[] = [
       'id'          => $agent_id,
       'nome'        => $agent['nome'],
       'meta_diaria' => $meta_agent,
       'total_pago'  => $total_agent
    ];
}

// 5) Calcula quantidade de agentes que bateram (ou não) a meta individual
$agents_met = 0;
$agents_not_met = 0;
$agents_below = [];
foreach ($agents_data as $agent) {
    if ($agent['meta_diaria'] > 0) {
       if ($agent['total_pago'] >= $agent['meta_diaria']) {
           $agents_met++;
       } else {
           $agents_not_met++;
           $agents_below[] = $agent;
       }
    }
}

// Para ranking, ordena os agentes pelo total_pago de forma decrescente
$ranking_agents = $agents_data;
usort($ranking_agents, function($a, $b) {
    return $b['total_pago'] <=> $a['total_pago'];
});

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Dashboard TV FGTS</title>
  <!-- Atualiza a cada 60 segundos -->
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

  <!-- Dispara confetes ao carregar a página -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      confetti({ particleCount: 20, spread: 70, origin: { y: 0.6 } });
    });
  </script>

  <!-- Swiper Container -->
  <div class="swiper mySwiper">
    <div class="swiper-wrapper">

      <!-- Slide 1: Visão Geral FGTS -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Dashboard FGTS</h1>
        <div class="card border-success mb-4">
          <div class="card-header bg-success">Meta Setor Diária</div>
          <div class="card-body">
            <p style="font-size: 2rem;">Meta: R$ <?= number_format($meta_setor_diaria, 2, ',', '.') ?></p>
          </div>
        </div>
        <div class="card border-info mb-4">
          <div class="card-header bg-info">Total Pago Hoje</div>
          <div class="card-body">
            <p style="font-size: 2rem;">R$ <?= number_format($total_pago, 2, ',', '.') ?></p>
          </div>
        </div>
        <div class="card border-danger mb-4">
          <div class="card-header bg-danger">Falta</div>
          <div class="card-body">
            <p style="font-size: 2rem;">R$ <?= number_format($faltando, 2, ',', '.') ?></p>
            <p style="font-size: 1.8rem;">(<?= number_format($percent_below, 2, ',', '.') ?>% abaixo)</p>
          </div>
        </div>
        <p style="font-size: 1.8rem;">Período: <?= $start_date ?> (Hoje)</p>
      </div>

      <!-- Slide 2: Ranking de Agentes -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Ranking de Agentes</h1>
        <div class="table-responsive w-75 mx-auto">
          <table class="table table-dark table-striped table-hover">
            <thead>
              <tr>
                <th>#</th>
                <th>Agente</th>
                <th>Total Pago (R$)</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              $pos = 1;
              foreach ($ranking_agents as $r): 
              ?>
              <tr class="pulse">
                <td><?= $pos++ ?></td>
                <td><?= htmlspecialchars($r['nome']) ?></td>
                <td>R$ <?= number_format($r['total_pago'], 2, ',', '.') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Slide 3: Desempenho dos Agentes (Tabela) -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Desempenho dos Agentes</h1>
        <div class="table-responsive w-75 mx-auto">
          <table class="table table-dark table-striped table-hover">
            <thead>
              <tr>
                <th>Agente</th>
                <th>Meta Diária</th>
                <th>Realizado</th>
                <th>Falta</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($agents_data as $agent): 
                  $falta_agent = max($agent['meta_diaria'] - $agent['total_pago'], 0);
              ?>
              <tr class="pulse">
                <td><?= htmlspecialchars($agent['nome']) ?></td>
                <td>R$ <?= number_format($agent['meta_diaria'], 2, ',', '.') ?></td>
                <td>R$ <?= number_format($agent['total_pago'], 2, ',', '.') ?></td>
                <td>R$ <?= number_format($falta_agent, 2, ',', '.') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Slide 4: Estatísticas Gerais -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Estatísticas Gerais</h1>
        <?php 
          $totalAgents = count($agents_data);
          $mediaAgent = ($totalAgents > 0) ? $total_pago / $totalAgents : 0;
        ?>
        <div class="card border-info mb-4">
          <div class="card-header bg-info">Resumo FGTS</div>
          <div class="card-body">
            <p style="font-size: 2rem;">Total Pago Hoje: R$ <?= number_format($total_pago, 2, ',', '.') ?></p>
            <p style="font-size: 2rem;">Meta Setor Diária: R$ <?= number_format($meta_setor_diaria, 2, ',', '.') ?></p>
            <p style="font-size: 2rem;">% Meta Atingida: <?= $meta_setor_diaria > 0 ? number_format(($total_pago / $meta_setor_diaria)*100, 2, ',', '.') : 0 ?>%</p>
            <p style="font-size: 2rem;">Agentes: <?= $totalAgents ?></p>
            <p style="font-size: 2rem;">Média por Agente: R$ <?= number_format($mediaAgent, 2, ',', '.') ?></p>
          </div>
        </div>
      </div>

      <!-- Slide 5: Agentes Abaixo da Meta -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Agentes Abaixo da Meta</h1>
        <?php if (count($agents_below) > 0): ?>
          <div class="table-responsive w-75 mx-auto">
            <table class="table table-dark table-striped table-hover">
              <thead>
                <tr>
                  <th>Agente</th>
                  <th>Meta Diária</th>
                  <th>Realizado</th>
                  <th>Falta</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($agents_below as $ab): 
                    $falta_ab = max($ab['meta_diaria'] - $ab['total_pago'], 0);
                ?>
                <tr class="pulse">
                  <td><?= htmlspecialchars($ab['nome']) ?></td>
                  <td>R$ <?= number_format($ab['meta_diaria'], 2, ',', '.') ?></td>
                  <td>R$ <?= number_format($ab['total_pago'], 2, ',', '.') ?></td>
                  <td>R$ <?= number_format($falta_ab, 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p>Nenhum agente abaixo da meta hoje.</p>
        <?php endif; ?>
      </div>

      <!-- Slide 6: Gráfico - Meta Setor x Realizado -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Gráfico: Meta x Realizado</h1>
        <div class="chart-container">
          <canvas id="sectorChart"></canvas>
        </div>
      </div>

      <!-- Slide 7: Gráfico - Desempenho dos Agentes -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Gráfico: Desempenho dos Agentes</h1>
        <div class="chart-container">
          <canvas id="agentsChart"></canvas>
        </div>
      </div>

      <!-- Slide 8: Gráfico - Pie Chart: Agentes que Bateram vs Não Bateram -->
      <div class="swiper-slide animate__animated animate__fadeInDown">
        <h1 class="slide-title">Gráfico: Agentes - Meta Atingida</h1>
        <div class="chart-container">
          <canvas id="agentsPieChart"></canvas>
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
    // Inicialização do Swiper com efeito coverflow
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

      // Confetes nos slides de Ranking (slide 2) e Estatísticas (slide 4)
      swiper.on('slideChangeTransitionEnd', function() {
        // Exemplo: dispara confete quando o slide atual for o de Ranking (índice 1) ou Estatísticas (índice 3)
        if (swiper.realIndex === 1 || swiper.realIndex === 3) {
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

    // Gráfico 1: Setor - Meta x Realizado
    const ctxSector = document.getElementById('sectorChart').getContext('2d');
    new Chart(ctxSector, {
      type: 'bar',
      data: {
        labels: ['Meta Setor', 'Realizado'],
        datasets: [{
          label: 'Valor (R$)',
          data: [<?= $meta_setor_diaria; ?>, <?= $total_pago; ?>],
          backgroundColor: ['#007bff', '#28a745']
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

    // Gráfico 2: Desempenho dos Agentes (barras agrupadas)
    // Prepara os dados para os agentes
    const agentsLabels = <?php echo json_encode(array_column($agents_data, 'nome')); ?>;
    const agentsMeta = <?php echo json_encode(array_map(function($a){ return $a['meta_diaria']; }, $agents_data)); ?>;
    const agentsPaid = <?php echo json_encode(array_map(function($a){ return $a['total_pago']; }, $agents_data)); ?>;
    
    const ctxAgents = document.getElementById('agentsChart').getContext('2d');
    new Chart(ctxAgents, {
      type: 'bar',
      data: {
        labels: agentsLabels,
        datasets: [
          {
            label: 'Meta Agente',
            data: agentsMeta,
            backgroundColor: '#007bff'
          },
          {
            label: 'Realizado',
            data: agentsPaid,
            backgroundColor: '#28a745'
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
          legend: { labels: { color: '#fff', font: { size: 16 } } }
        },
        scales: {
          y: { beginAtZero: true, ticks: { color: '#fff' } },
          x: { ticks: { color: '#fff' } }
        }
      }
    });

    // Gráfico 3: Pizza – Agentes que bateram vs não bateram a meta
    const ctxAgentsPie = document.getElementById('agentsPieChart').getContext('2d');
    new Chart(ctxAgentsPie, {
      type: 'pie',
      data: {
        labels: ['Meta Atingida', 'Abaixo da Meta'],
        datasets: [{
          data: [<?= $agents_met; ?>, <?= $agents_not_met; ?>],
          backgroundColor: ['#28a745', '#dc3545']
        }]
      },
      options: {
        responsive: true,
        plugins: {
          datalabels: {
            color: '#fff',
            font: { weight: 'bold', size: 16 },
            formatter: (value, ctx) => {
              return ctx.chart.data.labels[ctx.dataIndex] + "\n" + value;
            }
          }
        }
      }
    });
  </script>

</body>
</html>
