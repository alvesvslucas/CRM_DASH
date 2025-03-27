<?php
session_start();
require_once __DIR__ . '/../../../absoluto.php';
require_once __DIR__ . '/../../../db/config.php';

// Validação de acesso para usuários com perfil TV Indoor do setor Cartão
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'tv_indoor' || ($_SESSION['setor'] ?? '') !== 'Cartão') {
  die("Acesso negado. Apenas usuários com perfil TV Indoor do setor Cartão podem acessar esta página.");
}

// Conexão com o banco de dados
$conn = new mysqli($host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
  die("Falha na conexão: " . $conn->connect_error);
}

$currentMonth = date('Y-m');
$currentDay   = date('Y-m-d');

// 1. Meta do Setor Cartão
$stmt = $conn->prepare("SELECT meta_mes, meta_dia FROM metas_setor_cartao WHERE periodo = ?");
$stmt->bind_param("s", $currentMonth);
$stmt->execute();
$resultSector = $stmt->get_result()->fetch_assoc();
$stmt->close();
$metaSetorMes = $resultSector['meta_mes'] ?? 0;
$metaSetorDia = $resultSector['meta_dia'] ?? 0;

// 2. Dados dos Agentes (consultores)
$sqlAgents = "
    SELECT a.id AS agente_id,
           a.nome AS agente_nome,
           IFNULL((SELECT SUM(valor_passar)
                   FROM vendas_cartao_dia
                   WHERE agente_id = a.id AND DATE_FORMAT(data_registro, '%Y-%m') = ?), 0) AS total_mes,
           IFNULL((SELECT SUM(valor_passar)
                   FROM vendas_cartao_dia
                   WHERE agente_id = a.id AND DATE(data_registro) = ?), 0) AS total_dia,
           IFNULL(m.meta_mes, 0) AS meta_mes,
           IFNULL(m.meta_dia, 0) AS meta_dia
    FROM agentes_cartao a
    LEFT JOIN metas_cartao m ON a.id = m.agente_id AND m.periodo = ?
    WHERE a.ativo = 1
    ORDER BY a.nome ASC
";
$stmt = $conn->prepare($sqlAgents);
$stmt->bind_param("sss", $currentMonth, $currentDay, $currentMonth);
$stmt->execute();
$resultAgents = $stmt->get_result();
$agentsData = [];
while ($row = $resultAgents->fetch_assoc()) {
  $agentsData[] = $row;
}
$stmt->close();

// 3. Última venda (para exibir o nome do consultor)
$sqlLastSale = "
    SELECT a.nome AS agente_nome, v.data_registro 
    FROM vendas_cartao_dia v 
    JOIN agentes_cartao a ON v.agente_id = a.id 
    ORDER BY v.data_registro DESC LIMIT 1
";
$resultLastSale = $conn->query($sqlLastSale);
$lastSale = $resultLastSale->fetch_assoc();
$lastConsultant = $lastSale['agente_nome'] ?? 'N/A';

// 4. Clientes em Rota (hoje) – para mapa
$sqlClients = "
    SELECT id, cliente_nome, cliente_endereco, cliente_numero
    FROM vendas_cartao_dia
    WHERE DATE(data_registro) = ?
";
$stmt = $conn->prepare($sqlClients);
$stmt->bind_param("s", $currentDay);
$stmt->execute();
$resultClients = $stmt->get_result();
$clients = [];
while ($row = $resultClients->fetch_assoc()) {
  $clients[] = $row;
}
$stmt->close();
$conn->close();

// Cálculos de totais
$somaMes = $somaDia = 0;
foreach ($agentsData as $a) {
  $somaMes += floatval($a['total_mes']);
  $somaDia += floatval($a['total_dia']);
}
$faltaMes = $metaSetorMes - $somaMes;
$faltaDia = $metaSetorDia - $somaDia;

// Ranking dos consultores (por total do mês)
$rankingAgents = $agentsData;
usort($rankingAgents, function ($a, $b) {
  return floatval($b['total_mes']) <=> floatval($a['total_mes']);
});
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>Dashboard TV Cartão - TV Indoor</title>
  <!-- Atualiza a cada 60 segundos para dados em tempo real -->
  <meta http-equiv="refresh" content="60">
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Swiper.js CSS -->
  <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css">
  <!-- Animate.css -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <!-- Chart.js + Plugin DataLabels -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <!-- Paleta de Cores para personalização -->
  <style>
    /* Paleta de cores (para fundo, bordas, etc.) */
    .color1 {
      background-color: #fc7c04;
    }

    .color2 {
      background-color: #fcbc7c;
    }

    .color3 {
      background-color: #1d5cab;
    }

    .color4 {
      background-color: #71696c;
    }

    .color5 {
      background-color: #954c03;
    }
  </style>
  <!-- Custom CSS -->
  <style>
    body {
      background-color: #000;
      /*Fundo principal */
      /* background: linear-gradient(to top, #954c03, #fc7c04); */

      color: #fff;
      font-family: 'Poppins', sans-serif;
      margin: 0;
      padding: 0;
    }

    /* Logo no canto superior direito */
    .logo-container {
      position: absolute;
      top: 30px;
      right: 30px;
      z-index: 9999;
    }

    .logo-container img {
      max-width: 200px;
      /* Ajuste conforme desejar */
      height: auto;
    }

    /* Swiper full-screen */
    .swiper {
      width: 100%;
      height: 100vh;
      /* ocupa a tela toda */
    }

    .swiper-slide {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 20px;
    }

    .slide-title {
      font-size: 3rem;
      margin-bottom: 20px;
      text-transform: uppercase;
    }

    .slide-content {
      font-size: 2rem;
    }

    .ranking-list {
      list-style: none;
      padding: 0;
      font-size: 2rem;
    }

    .ranking-list li {
      margin: 10px 0;
    }

    .chart-container {
      width: 90%;
      height: 400px;
      margin: 0 auto;
    }

    /* Mapa do slide */
    #mapRoutes {
      width: 90%;
      height: 500px;
      margin: 0 auto;
      margin-top: 10px;
      border: 3px solid #954c03;
      /* color5 */
      border-radius: 8px;
    }
  </style>
</head>

<body>

  <!-- Logo da Credinowe (ajuste o caminho da imagem) -->
  <div class="logo-container">
    <img src="../../../assets/img/logo1.png" alt="Credinowe Logo">
  </div>

  <!-- Swiper container para slides -->
  <div class="swiper mySwiper">
    <div class="swiper-wrapper">
      <!-- Slide 1: Última Venda -->
      <div class="swiper-slide animate__animated animate__fadeIn">
        <div class="slide-title">Última Venda</div>
        <div class="slide-content">
          Consultor(a): <strong><?= htmlspecialchars($lastConsultant) ?></strong>
        </div>
      </div>

      <!-- Slide 2: Metas do Setor -->
      <div class="swiper-slide animate__animated animate__fadeIn">
        <div class="slide-title">Metas do Setor</div>
        <div class="slide-content">
          <p>Mês: Meta R$ <?= number_format($metaSetorMes, 2, ',', '.') ?> | Total R$ <?= number_format($somaMes, 2, ',', '.') ?> | Falta R$ <?= number_format($faltaMes, 2, ',', '.') ?></p>
          <p>Dia: Meta R$ <?= number_format($metaSetorDia, 2, ',', '.') ?> | Total R$ <?= number_format($somaDia, 2, ',', '.') ?> | Falta R$ <?= number_format($faltaDia, 2, ',', '.') ?></p>
        </div>
      </div>

      <!-- Slide 3: Rotas Registradas Hoje (apenas contagem) -->
      <div class="swiper-slide animate__animated animate__fadeIn">
        <div class="slide-title">Rotas Hoje</div>
        <div class="slide-content">
          <p>Total de rotas registradas: <strong><?= count($clients) ?></strong></p>
        </div>
      </div>

      <!-- Slide 4: Mapa das Rotas (apenas se houver clientes) -->
      <div class="swiper-slide animate__animated animate__fadeIn">
        <div class="slide-title">Mapa de Rotas</div>
        <?php if (count($clients) > 0): ?>
          <div class="slide-content" style="font-size:1.5rem;">Localizando clientes...</div>
          <div id="mapRoutes"></div>
        <?php else: ?>
          <div class="slide-content">
            <p>Sem rotas para exibir.</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Slide 5: Ranking de Vendas (Mês) -->
      <div class="swiper-slide animate__animated animate__fadeIn">
        <div class="slide-title">Ranking de Vendas (Mês)</div>
        <ul class="ranking-list">
          <?php foreach ($rankingAgents as $index => $agent): ?>
            <li><?= ($index + 1) ?>º - <?= htmlspecialchars($agent['agente_nome']) ?>: R$ <?= number_format($agent['total_mes'], 2, ',', '.') ?></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Slide 6: Gráficos de Desempenho -->
      <div class="swiper-slide animate__animated animate__fadeIn">
        <div class="slide-title">Desempenho dos Agentes</div>
        <div class="chart-container">
          <canvas id="chartMes"></canvas>
        </div>
        <div class="chart-container mt-5">
          <canvas id="chartDia"></canvas>
        </div>
      </div>
    </div>
    <!-- Navegação opcional (setas e paginação) -->
    <div class="swiper-button-next"></div>
    <div class="swiper-button-prev"></div>
    <div class="swiper-pagination"></div>
  </div>

  <!-- Scripts -->
  <!-- Bootstrap Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Swiper.js -->
  <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
  <!-- Leaflet.js -->
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

  <script>
    // Inicialização do Swiper (carrossel) com autoplay
    var swiper = new Swiper(".mySwiper", {
      autoplay: {
        delay: 10000, // 10 segundos por slide
        disableOnInteraction: false,
      },
      loop: true,
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
      },
      navigation: {
        nextEl: ".swiper-button-next",
        prevEl: ".swiper-button-prev",
      },
    });
  </script>

  <!-- Configuração dos Gráficos Chart.js com DataLabels -->
  <script>
    // Dados do PHP
    var agentsData = <?= json_encode($agentsData, JSON_UNESCAPED_UNICODE) ?>;

    // Prepara arrays
    var labelsAgents = agentsData.map(a => a.agente_nome);
    var metaMes = agentsData.map(a => parseFloat(a.meta_mes));
    var realMes = agentsData.map(a => parseFloat(a.total_mes));
    var metaDia = agentsData.map(a => parseFloat(a.meta_dia));
    var realDia = agentsData.map(a => parseFloat(a.total_dia));

    // Formatação do valor (R$)
    function formatCurrency(value) {
      return value.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL'
      });
    }

    // Gráfico Mês
    var ctxMes = document.getElementById('chartMes').getContext('2d');
    var chartMes = new Chart(ctxMes, {
      type: 'bar',
      data: {
        labels: labelsAgents,
        datasets: [{
          label: 'Meta (Mês)',
          data: metaMes,
          backgroundColor: "#fcbc7c", // color2
          borderColor: "#954c03", // color5
          borderWidth: 2
        }, {
          label: 'Real (Mês)',
          data: realMes,
          backgroundColor: "#fc7c04", // color1
          borderColor: "#1d5cab", // color3
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
          duration: 2000
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              color: '#fff'
            },
            grid: {
              color: 'rgba(255,255,255,0.2)'
            }
          },
          x: {
            ticks: {
              color: '#fff'
            },
            grid: {
              display: false
            }
          }
        },
        plugins: {
          legend: {
            labels: {
              color: '#fff',
              font: {
                size: 16
              }
            }
          },
          datalabels: {
            anchor: 'end',
            align: 'end',
            color: '#fff',
            font: {
              size: 14
            },
            formatter: function(value) {
              return formatCurrency(value);
            }
          }
        }
      },
      plugins: [ChartDataLabels]
    });

    // Gráfico Dia
    var ctxDia = document.getElementById('chartDia').getContext('2d');
    var chartDia = new Chart(ctxDia, {
      type: 'bar',
      data: {
        labels: labelsAgents,
        datasets: [{
          label: 'Meta (Dia)',
          data: metaDia,
          backgroundColor: "#71696c", // color4
          borderColor: "#954c03", // color5
          borderWidth: 2
        }, {
          label: 'Real (Dia)',
          data: realDia,
          backgroundColor: "#1d5cab", // color3
          borderColor: "#fc7c04", // color1
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
          duration: 2000
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              color: '#fff'
            },
            grid: {
              color: 'rgba(255,255,255,0.2)'
            }
          },
          x: {
            ticks: {
              color: '#fff'
            },
            grid: {
              display: false
            }
          }
        },
        plugins: {
          legend: {
            labels: {
              color: '#fff',
              font: {
                size: 16
              }
            }
          },
          datalabels: {
            anchor: 'end',
            align: 'end',
            color: '#fff',
            font: {
              size: 14
            },
            formatter: function(value) {
              return formatCurrency(value);
            }
          }
        }
      },
      plugins: [ChartDataLabels]
    });
  </script>

  <!-- Script para Mapa e Geocodificação -->
  <script>
    <?php if (count($clients) > 0): ?>
      var clients = <?= json_encode($clients, JSON_UNESCAPED_UNICODE) ?>;
      var mapRoutes = L.map('mapRoutes').setView([-15.7801, -47.9292], 4);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
      }).addTo(mapRoutes);

      var latLngs = [];
      var totalClients = clients.length;
      var completedCount = 0;

      function geocodeAddress(address, callback) {
        fetch("https://nominatim.openstreetmap.org/search?format=json&q=" + encodeURIComponent(address))
          .then(response => response.json())
          .then(data => {
            if (data && data.length > 0) {
              callback(parseFloat(data[0].lat), parseFloat(data[0].lon));
            } else {
              callback(null, null);
            }
          })
          .catch(error => {
            console.error(error);
            callback(null, null);
          });
      }

      clients.forEach(function(client) {
        var address = (client.cliente_endereco || '') + ' ' + (client.cliente_numero || '');
        geocodeAddress(address, function(lat, lon) {
          if (lat && lon) {
            latLngs.push([lat, lon]);
            L.marker([lat, lon]).addTo(mapRoutes)
              .bindPopup("<strong>" + client.cliente_nome + "</strong><br>" + address);
          }
          completedCount++;
          if (completedCount === totalClients) {
            if (latLngs.length > 0) {
              mapRoutes.fitBounds(latLngs, {
                maxZoom: 14
              });
            }
          }
        });
      });
    <?php endif; ?>
  </script>
</body>

</html>