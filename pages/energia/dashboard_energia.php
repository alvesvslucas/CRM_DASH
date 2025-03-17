<?php
session_start();
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
include(HEADER_FILE);

// Verifica se o usuário logado é supervisor do setor Energia
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'supervisor' || ($_SESSION['setor'] ?? '') !== 'Energia') {
    die("Acesso negado. Apenas supervisores do setor Energia podem acessar esta página.");
}

// Conecta ao banco
$conn = new mysqli($host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Determina mês e dia atuais
$currentMonth = date('Y-m');  // Ex: "2023-03"
$currentDay   = date('Y-m-d'); // Ex: "2023-03-28"

// Verifica se a supervisora quer exibir os gráficos
$showCharts = isset($_GET['show_charts']) && $_GET['show_charts'] === '1';

// 1. Meta do Setor Energia
$stmt = $conn->prepare("SELECT meta_mes, meta_dia FROM metas_setor_energia WHERE periodo = ?");
$stmt->bind_param("s", $currentMonth);
$stmt->execute();
$resultSector = $stmt->get_result()->fetch_assoc();
$stmt->close();

$metaSetorMes = $resultSector['meta_mes'] ?? 0;
$metaSetorDia = $resultSector['meta_dia'] ?? 0;

// 2. Dados dos Agentes (somamos apenas valor_passar das rotas concluídas)
$sqlAgents = "
  SELECT a.id AS agente_id,
         a.nome AS agente_nome,
         IFNULL((SELECT SUM(valor_passar)
                 FROM vendas_energia_dia
                 WHERE agente_id = a.id
                   AND DATE_FORMAT(data_registro, '%Y-%m') = ?), 0) AS total_mes,
         IFNULL((SELECT SUM(valor_passar)
                 FROM vendas_energia_dia
                 WHERE agente_id = a.id
                   AND DATE(data_registro) = ?), 0) AS total_dia,
         IFNULL(m.meta_mes, 0) AS meta_mes,
         IFNULL(m.meta_dia, 0) AS meta_dia
  FROM agentes_energia a
  LEFT JOIN metas_energia m ON a.id = m.agente_id AND m.periodo = ?
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

// 3. Lista de “Clientes” ou “Instalações” em Rota (hoje)
$sqlClients = "
  SELECT 
    id,
    agente_id,
    cliente_nome,
    cliente_endereco,
    cliente_numero,
    cliente_referencia,
    valor_passar,
    valor_pendente,
    rota_status,
    rota_ordem,
    valor_recebido,
    parcelas,
    fonte,
    turno,
    horario,
    pagamento,
    data_registro
  FROM vendas_energia_dia
  WHERE supervisor_id = ?
    AND DATE(data_registro) = ?
  ORDER BY id ASC
";
$stmt = $conn->prepare($sqlClients);
$stmt->bind_param("is", $_SESSION['user_id'], $currentDay);
$stmt->execute();
$resultClients = $stmt->get_result();
$clients = [];
while ($row = $resultClients->fetch_assoc()) {
  $clients[] = $row;
}
$stmt->close();
$conn->close();

// Cálculo dos resumos para exibir nos cards
$somaMes = 0;
$somaDia = 0;
foreach ($agentsData as $a) {
  $somaMes += floatval($a['total_mes']);
  $somaDia += floatval($a['total_dia']);
}
$faltaMes = $metaSetorMes - $somaMes;
$faltaDia = $metaSetorDia - $somaDia;

// Preparar arrays para gráficos por agente
$agentsLabel   = [];
$agentsDiaMeta = [];
$agentsDiaReal = [];
$agentsMesMeta = [];
$agentsMesReal = [];

foreach ($agentsData as $agent) {
  $agentsLabel[]   = $agent['agente_nome'];
  $agentsDiaMeta[] = floatval($agent['meta_dia']);
  $agentsDiaReal[] = floatval($agent['total_dia']);
  $agentsMesMeta[] = floatval($agent['meta_mes']);
  $agentsMesReal[] = floatval($agent['total_mes']);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Energia - Visual Melhorado</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Leaflet CSS para o mapa -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      background-color: #f9f9f9;
    }
    /* Cabeçalho */
    .header-bar {
      background-color: #fff;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      padding: 1rem;
      margin-bottom: 1rem;
    }
    .header-bar h1 {
      font-size: 1.5rem;
      margin: 0;
      font-weight: 600;
    }

    /* Cartões e containers */
    .card-custom {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      margin-bottom: 1rem;
      padding: 1rem;
    }
    .card-custom h4 {
      margin-bottom: 1rem;
      font-weight: 600;
    }
    .chart-canvas {
      height: 200px !important;
    }
    #map {
      height: 350px;
    }
  </style>
</head>
<body>

<!-- Topo -->
<div class="header-bar d-flex align-items-center justify-content-between">
  <div>
    <h1 class="text-success">Credesh - Performance Energia</h1>
    <small class="text-muted">Painel de acompanhamento e métricas (Energia)</small>
  </div>
  <div>
    <!-- Exemplo de datas e "exibir gráficos" -->
    <form class="d-flex gap-2" method="GET">
      <div>
        <label for="dataInicio" class="form-label mb-0"><small>Data Início</small></label>
        <input type="date" id="dataInicio" name="dataInicio" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>">
      </div>
      <div>
        <label for="dataFim" class="form-label mb-0"><small>Data Fim</small></label>
        <input type="date" id="dataFim" name="dataFim" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
      </div>
      <div>
        <label for="showChartsCheck" class="form-label mb-0"><small>Exibir Gráficos</small></label><br>
        <input class="form-check-input" type="checkbox" name="show_charts" value="1" id="showChartsCheck"
          <?php if($showCharts) echo "checked"; ?>>
      </div>
      <button class="btn btn-success btn-sm align-self-end">Aplicar</button>
    </form>
  </div>
</div>

<div class="container-fluid">
  <div class="row">
    <!-- Coluna da esquerda (cards) -->
    <div class="col-md-3">
      <!-- Card: Meta do Setor Energia -->
      <div class="card-custom">
        <h4>Meta do Setor (<?= $currentMonth ?>)</h4>
        <p><strong>Meta Mensal:</strong> R$ <?= number_format($metaSetorMes, 2, ',', '.') ?></p>
        <p><strong>Meta Diária:</strong> R$ <?= number_format($metaSetorDia, 2, ',', '.') ?></p>
      </div>

      <!-- Card: Resumo (Mês) -->
      <div class="card-custom">
        <h4>Resumo (Mês)</h4>
        <p><strong>Total Mês (Agentes):</strong> R$ <?= number_format($somaMes, 2, ',', '.') ?></p>
        <p><strong>Falta (Mês):</strong> R$ <?= number_format($faltaMes, 2, ',', '.') ?></p>
      </div>

      <!-- Card: Resumo (Dia) -->
      <div class="card-custom">
        <h4>Resumo (Dia)</h4>
        <p><strong>Total Dia (Agentes):</strong> R$ <?= number_format($somaDia, 2, ',', '.') ?></p>
        <p><strong>Falta (Dia):</strong> R$ <?= number_format($faltaDia, 2, ',', '.') ?></p>
      </div>
    </div>

    <!-- Coluna principal -->
    <div class="col-md-9">
      <!-- Seção de desempenho dos Agentes -->
      <div class="card-custom">
        <h4>Desempenho dos Agentes (Energia)</h4>
        <div class="table-responsive">
          <table class="table table-bordered table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>Agente</th>
                <th>Meta Mensal</th>
                <th>Total (Mês)</th>
                <th>Falta (Mês)</th>
                <th>Meta Diária</th>
                <th>Total (Dia)</th>
                <th>Falta (Dia)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($agentsData as $agent):
                $metaMesA   = floatval($agent['meta_mes']);
                $totalMesA  = floatval($agent['total_mes']);
                $faltamMesA = $metaMesA - $totalMesA;

                $metaDiaA   = floatval($agent['meta_dia']);
                $totalDiaA  = floatval($agent['total_dia']);
                $faltamDiaA = $metaDiaA - $totalDiaA;
              ?>
                <tr>
                  <td><?= htmlspecialchars($agent['agente_nome']) ?></td>
                  <td>R$ <?= number_format($metaMesA, 2, ',', '.') ?></td>
                  <td>R$ <?= number_format($totalMesA, 2, ',', '.') ?></td>
                  <td>R$ <?= number_format($faltamMesA, 2, ',', '.') ?></td>
                  <td>R$ <?= number_format($metaDiaA, 2, ',', '.') ?></td>
                  <td>R$ <?= number_format($totalDiaA, 2, ',', '.') ?></td>
                  <td>R$ <?= number_format($faltamDiaA, 2, ',', '.') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Se a supervisora escolheu exibir gráficos -->
      <?php if ($showCharts): ?>
        <div class="row">
          <!-- Gráfico (Setor): Meta x Realizado (Mês) -->
          <div class="col-md-4">
            <div class="card-custom">
              <h4>Setor (Mês)</h4>
              <canvas id="barChart" class="chart-canvas"></canvas>
            </div>
          </div>
          <!-- Gráfico por Agente (Mês) -->
          <div class="col-md-4">
            <div class="card-custom">
              <h4>Agente (Mês)</h4>
              <canvas id="barMesAgente" class="chart-canvas"></canvas>
            </div>
          </div>
          <!-- Gráfico por Agente (Dia) -->
          <div class="col-md-4">
            <div class="card-custom">
              <h4>Agente (Dia)</h4>
              <canvas id="barDiaAgente" class="chart-canvas"></canvas>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="card-custom">
          <p class="text-muted">Gráficos não exibidos. Marque "Exibir Gráficos" e clique em "Aplicar" para vê-los.</p>
        </div>
      <?php endif; ?>

      <!-- Lista de “Clientes/Instalações” (Hoje) -->
      <div class="card-custom mt-3">
        <h4>Clientes/Instalações (Hoje)</h4>
        <?php if (count($clients) > 0): ?>
          <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle">
              <thead class="table-light">
                <tr>
                  <th>Ordem/ID</th>
                  <th>Cliente</th>
                  <th>Endereço</th>
                  <th>Número</th>
                  <th>Referência</th>
                  <th>Valor</th>
                  <th>Status</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($clients as $client): ?>
                  <?php
                    // Se rota está concluída, exibe valor_passar; senão, exibe valor_pendente
                    $valorExibir = ($client['rota_status'] === 'concluida')
                                  ? $client['valor_passar']
                                  : $client['valor_pendente'];
                  ?>
                  <tr>
                    <td><?= $client['rota_ordem'] . " / #" . $client['id']; ?></td>
                    <td><?= htmlspecialchars($client['cliente_nome']); ?></td>
                    <td><?= htmlspecialchars($client['cliente_endereco']); ?></td>
                    <td><?= htmlspecialchars($client['cliente_numero']); ?></td>
                    <td><?= htmlspecialchars($client['cliente_referencia']); ?></td>
                    <td>R$ <?= number_format($valorExibir, 2, ',', '.'); ?></td>
                    <td>
                      <?php if ($client['rota_status'] === 'concluida'): ?>
                        <span class="badge bg-success">Concluída</span>
                      <?php else: ?>
                        <span class="badge bg-warning text-dark">Pendente</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <!-- Botão Visualizar -->
                      <button class="btn btn-sm btn-info"
                        data-bs-toggle="modal"
                        data-bs-target="#viewModal"
                        data-client='<?php echo json_encode($client, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                        Visualizar
                      </button>
                      <!-- Botão Editar -->
                      <button class="btn btn-sm btn-secondary"
                        data-bs-toggle="modal"
                        data-bs-target="#editModal"
                        data-id="<?php echo $client['id']; ?>"
                        data-endereco="<?php echo addslashes($client['cliente_endereco']); ?>"
                        data-numero="<?php echo addslashes($client['cliente_numero']); ?>"
                        data-ref="<?php echo addslashes($client['cliente_referencia']); ?>"
                        data-ordem="<?php echo (int)$client['rota_ordem']; ?>">
                        Editar
                      </button>
                      <!-- Botão Concluir -->
                      <?php if ($client['rota_status'] !== 'concluida'): ?>
                        <button class="btn btn-sm btn-success"
                          onclick="marcarConcluida(<?php echo $client['id']; ?>)">Concluir</button>
                      <?php endif; ?>
                      <!-- Botão Mapa (Google Maps) -->
                      <button class="btn btn-sm btn-warning"
                        onclick="verRota('<?php echo addslashes($client['cliente_endereco']); ?>')">
                        Mapa
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-muted">Nenhum cliente hoje.</p>
        <?php endif; ?>
      </div>

      <!-- Mapa -->
      <div class="card-custom mt-3">
        <h4>Mapa das Instalações</h4>
        <div id="map"></div>
      </div>
    </div> <!-- fim col-md-9 -->
  </div> <!-- fim row -->
</div> <!-- fim container-fluid -->

<!-- MODAL VISUALIZAR -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewModalLabel">Detalhes do Cliente/Instalação</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p><strong>Cliente:</strong> <span id="viewClienteNome"></span></p>
        <p><strong>Endereço:</strong> <span id="viewClienteEndereco"></span></p>
        <p><strong>Número:</strong> <span id="viewClienteNumero"></span></p>
        <p><strong>Referência:</strong> <span id="viewClienteReferencia"></span></p>
        <p><strong>Valor (R$):</strong> <span id="viewValor"></span></p>
        <p><strong>Valor Recebido (R$):</strong> <span id="viewValorRecebido"></span></p>
        <p><strong>Parcelas:</strong> <span id="viewParcelas"></span></p>
        <p><strong>Fonte:</strong> <span id="viewFonte"></span></p>
        <p><strong>Turno:</strong> <span id="viewTurno"></span></p>
        <p><strong>Horário:</strong> <span id="viewHorario"></span></p>
        <p><strong>Pagamento:</strong> <span id="viewPagamento"></span></p>
        <p><strong>Status:</strong> <span id="viewRotaStatus"></span></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL EDITAR ROTA -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="editForm" method="POST" action="editar_rota_energia.php">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editModalLabel">Editar Rota/Instalação</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="rota_id" id="rota_id">
          <div class="mb-3">
            <label for="rota_ordem" class="form-label">Ordem</label>
            <input type="number" class="form-control" name="rota_ordem" id="rota_ordem">
          </div>
          <div class="mb-3">
            <label for="cliente_endereco_modal" class="form-label">Endereço</label>
            <input type="text" class="form-control" name="cliente_endereco" id="cliente_endereco_modal">
          </div>
          <div class="mb-3">
            <label for="cliente_numero_modal" class="form-label">Número</label>
            <input type="text" class="form-control" name="cliente_numero" id="cliente_numero_modal">
          </div>
          <div class="mb-3">
            <label for="cliente_referencia_modal" class="form-label">Referência</label>
            <input type="text" class="form-control" name="cliente_referencia" id="cliente_referencia_modal">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
// Inicializa o mapa
var map = L.map('map').setView([-15.7801, -47.9292], 4);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 18,
  attribution: '© OpenStreetMap'
}).addTo(map);

// Geocodifica e adiciona marcadores
function geocodeAddress(address, callback) {
  fetch("https://nominatim.openstreetmap.org/search?format=json&q=" + encodeURIComponent(address))
    .then(r => r.json())
    .then(data => {
      if(data && data.length > 0) {
        callback(data[0].lat, data[0].lon);
      } else {
        callback(null, null);
      }
    })
    .catch(err => {
      console.error(err);
      callback(null, null);
    });
}
function addMarker(lat, lng, popupContent) {
  var marker = L.marker([lat, lng]).addTo(map);
  marker.bindPopup(popupContent);
}

// Carrega clientes e só exibe no mapa se rota_status != 'concluida'
var clientsJS = <?php echo json_encode($clients, JSON_UNESCAPED_UNICODE); ?>;
clientsJS.forEach(function(c) {
  if (c.rota_status !== 'concluida') {
    var address = c.cliente_endereco + ", " + (c.cliente_numero || "");
    geocodeAddress(address, function(lat, lng) {
      if (lat && lng) {
        var valorExibir = (c.rota_status === 'concluida') ? c.valor_passar : c.valor_pendente;
        var popup = "<strong>" + c.cliente_nome + "</strong><br>" +
                    "Endereço: " + address + "<br>" +
                    "Referência: " + (c.cliente_referencia || '') + "<br>" +
                    "Valor: R$ " + parseFloat(valorExibir).toFixed(2);
        addMarker(lat, lng, popup);
      }
    });
  }
});

// Botão Mapa (Google Maps)
function verRota(endereco) {
  window.open("https://www.google.com/maps/search/?api=1&query=" + encodeURIComponent(endereco), "_blank");
}

// Botão Concluir
function marcarConcluida(rotaId) {
  if(confirm("Deseja marcar esta rota como concluída?")) {
    window.location.href = "concluir_rota_energia.php?id=" + rotaId;
  }
}

// Modal Editar
var editModal = document.getElementById('editModal');
editModal.addEventListener('show.bs.modal', function(event) {
  var button = event.relatedTarget;
  var rotaId = button.getAttribute('data-id');
  var endereco = button.getAttribute('data-endereco');
  var numero = button.getAttribute('data-numero');
  var ref = button.getAttribute('data-ref');
  var ordem = button.getAttribute('data-ordem');

  document.getElementById('rota_id').value = rotaId;
  document.getElementById('rota_ordem').value = ordem;
  document.getElementById('cliente_endereco_modal').value = endereco;
  document.getElementById('cliente_numero_modal').value = numero;
  document.getElementById('cliente_referencia_modal').value = ref;
});

// Modal Visualizar
var viewModal = document.getElementById('viewModal');
viewModal.addEventListener('show.bs.modal', function(event) {
  var button = event.relatedTarget;
  var dataStr = button.getAttribute('data-client');
  var c = JSON.parse(dataStr);

  // Se concluída => valor_passar, senão => valor_pendente
  var valorExibir = (c.rota_status === 'concluida') ? c.valor_passar : c.valor_pendente;

  document.getElementById('viewClienteNome').textContent      = c.cliente_nome;
  document.getElementById('viewClienteEndereco').textContent  = c.cliente_endereco;
  document.getElementById('viewClienteNumero').textContent    = c.cliente_numero;
  document.getElementById('viewClienteReferencia').textContent= c.cliente_referencia;
  document.getElementById('viewValor').textContent            = "R$ " + parseFloat(valorExibir).toFixed(2);
  // Ajuste se quiser exibir mais campos (valor_recebido, etc.)
});
</script>

<!-- Gráficos Chart.js -->
<?php if ($showCharts): ?>
<script>
  // Gráfico 1: Setor - Meta x Realizado (Mês)
  var ctxBar = document.getElementById('barChart').getContext('2d');
  var totalMes = <?= json_encode($somaMes) ?>;
  var metaMes  = <?= json_encode($metaSetorMes) ?>;
  new Chart(ctxBar, {
    type: 'bar',
    data: {
      labels: ['Realizado', 'Meta'],
      datasets: [{
        label: 'Valor (R$)',
        data: [totalMes, metaMes],
        backgroundColor: ['#27ae60', '#2ecc71']
      }]
    },
    options: {
      responsive: true,
      scales: { y: { beginAtZero: true } }
    }
  });

  // Gráfico 2: Por Agente (Mês)
  var ctxMes = document.getElementById('barMesAgente').getContext('2d');
  var agentsLabel   = <?= json_encode($agentsLabel, JSON_UNESCAPED_UNICODE) ?>;
  var agentsMesMeta = <?= json_encode($agentsMesMeta) ?>;
  var agentsMesReal = <?= json_encode($agentsMesReal) ?>;
  new Chart(ctxMes, {
    type: 'bar',
    data: {
      labels: agentsLabel,
      datasets: [
        { label: 'Meta (Mês)', data: agentsMesMeta, backgroundColor: '#bdc3c7' },
        { label: 'Real (Mês)', data: agentsMesReal, backgroundColor: '#27ae60' }
      ]
    },
    options: {
      responsive: true,
      scales: { y: { beginAtZero: true } }
    }
  });

  // Gráfico 3: Por Agente (Dia)
  var ctxDia = document.getElementById('barDiaAgente').getContext('2d');
  var agentsDiaMeta = <?= json_encode($agentsDiaMeta) ?>;
  var agentsDiaReal = <?= json_encode($agentsDiaReal) ?>;
  new Chart(ctxDia, {
    type: 'bar',
    data: {
      labels: agentsLabel,
      datasets: [
        { label: 'Meta (Dia)', data: agentsDiaMeta, backgroundColor: '#2ecc71' },
        { label: 'Real (Dia)', data: agentsDiaReal, backgroundColor: '#16a085' }
      ]
    },
    options: {
      responsive: true,
      scales: { y: { beginAtZero: true } }
    }
  });
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include(FOOTER_FILE); ?>
</html>
