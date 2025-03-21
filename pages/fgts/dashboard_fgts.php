<?php
// dashboard_supervisor.php

require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
// Se possuir um header padrão, descomente a linha a seguir:
include(HEADER_FILE);

// Obtém os filtros de data (default: data de hoje)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date   = isset($_GET['end_date'])   ? $_GET['end_date']   : date('Y-m-d');

// Define os limites de data para a consulta
$start_datetime = $start_date . " 00:00:00";
$end_datetime   = $end_date   . " 23:59:59";

// 1) Consulta a meta do setor (diária) na tabela metas_setor_fgts
$resSector = $conn->query("SELECT meta_diaria, meta_mensal FROM metas_setor_fgts LIMIT 1");
$meta_setor_diaria = 0;
if ($resSector && $resSector->num_rows > 0) {
    $row = $resSector->fetch_assoc();
    $meta_setor_diaria = floatval($row['meta_diaria']);
}

// 2) Consulta o total realizado (soma dos valores com status 'pago') no período
$resTotal = $conn->query("
    SELECT SUM(valor) as total_pago 
    FROM metas_fgts 
    WHERE status='pago' 
      AND created_at BETWEEN '$start_datetime' AND '$end_datetime'
");
$total_pago = 0;
if ($resTotal && $row = $resTotal->fetch_assoc()) {
   $total_pago = floatval($row['total_pago']);
}

// 3) Calcula quanto falta para bater a meta e a porcentagem abaixo da meta
$faltando = max($meta_setor_diaria - $total_pago, 0);
$percent_below = ($meta_setor_diaria > 0) 
    ? (($meta_setor_diaria - $total_pago) / $meta_setor_diaria * 100) 
    : 0;

// 4) Consulta o desempenho de cada agente (meta individual e total pago no período)
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
        SELECT SUM(valor) as total_pago 
        FROM metas_fgts 
        WHERE agente_id = $agent_id 
          AND status='pago' 
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

// 5) Calcula a quantidade de agentes que bateram (ou não) sua meta individual
$agents_met = 0;
$agents_not_met = 0;
$agents_below = []; // Armazenará os agentes abaixo da meta

foreach ($agents_data as $agent) {
    if ($agent['meta_diaria'] > 0) {
       if ($agent['total_pago'] >= $agent['meta_diaria']) {
           $agents_met++;
       } else {
           $agents_not_met++;
           $agents_below[] = $agent; // Adiciona o agente à lista de "abaixo da meta"
       }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Supervisor - Metas FGTS</title>
  <style>
    /* Estilo moderno e responsivo */
    body {
      font-family: Arial, sans-serif;
      background: #f0f2f5;
      color: #333;
      margin: 0;
      padding: 20px;
    }
    .container {
      max-width: 1200px;
      margin: auto;
    }
    h1 {
      text-align: center;
      margin-bottom: 20px;
      color: #444;
    }
    .filter-form {
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      margin-bottom: 20px;
      text-align: center;
    }
    .filter-form input[type="date"],
    .filter-form button {
      padding: 10px;
      margin: 5px;
      font-size: 1rem;
    }
    .metrics {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      margin-bottom: 20px;
      justify-content: space-around;
    }
    .metric {
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      flex: 1;
      min-width: 200px;
      text-align: center;
    }
    .metric h2 {
      font-size: 1.2rem;
      margin-bottom: 10px;
    }
    .charts {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
    }
    .chart-container {
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      flex: 1;
      min-width: 300px;
      margin-bottom: 20px;
      text-align: center;
    }
    /* Tabela de agentes abaixo da meta */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
    table thead tr {
      background: #007bff;
      color: #fff;
    }
    th, td {
      padding: 10px;
      border: 1px solid #ccc;
      text-align: left;
    }
    tr:nth-child(even) {
      background: #f9f9f9;
    }
  </style>
  <!-- Inclui Chart.js via CDN -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="container">
    <h1>Dashboard Supervisor - Metas FGTS</h1>
    
    <!-- Filtros de Data -->
    <div class="filter-form">
      <form method="GET" action="">
         <label for="start_date">Data Inicial:</label>
         <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
         <label for="end_date">Data Final:</label>
         <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
         <button type="submit">Filtrar</button>
      </form>
    </div>
    
    <!-- Métricas Principais -->
    <div class="metrics">
      <div class="metric">
         <h2>Meta Setor Diária</h2>
         <p>R$ <?php echo number_format($meta_setor_diaria, 2, ',', '.'); ?></p>
      </div>
      <div class="metric">
         <h2>Total Pago</h2>
         <p>R$ <?php echo number_format($total_pago, 2, ',', '.'); ?></p>
      </div>
      <div class="metric">
         <h2>Falta</h2>
         <p>R$ <?php echo number_format($faltando, 2, ',', '.'); ?></p>
      </div>
      <div class="metric">
         <h2>% Abaixo da Meta</h2>
         <p><?php echo number_format($percent_below, 2, ',', '.'); ?>%</p>
      </div>
     
    </div>
    
    <!-- Gráficos -->
    <div class="charts">
      <!-- Gráfico 1: Meta Setor vs Realizado -->
      <div class="chart-container">
         <h3>Meta x Realizado (Setor)</h3>
         <canvas id="sectorChart"></canvas>
      </div>
      <!-- Gráfico 2: Desempenho dos Agentes (meta vs realizado) -->
      <div class="chart-container">
         <h3>Desempenho por Agente</h3>
         <canvas id="agentsChart"></canvas>
      </div>
      <!-- Gráfico 3: Pie Chart de Agentes que bateram a meta -->
      <div class="chart-container">
         <h3>Agentes que Bateram a Meta</h3>
         <canvas id="agentsPieChart"></canvas>
      </div>
    </div>
    
    <!-- Detalhes: Agentes Abaixo da Meta -->
    <div class="chart-container">
      <h3>Agentes Abaixo da Meta</h3>
      <?php if (count($agents_below) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Agente</th>
              <th>Meta Diária</th>
              <th>Realizado</th>
              <th>Falta</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($agents_below as $ab): ?>
              <?php 
                $falta_agente = $ab['meta_diaria'] - $ab['total_pago']; 
                if ($falta_agente < 0) {
                  $falta_agente = 0;
                }
              ?>
              <tr>
                <td><?php echo htmlspecialchars($ab['nome']); ?></td>
                <td>R$ <?php echo number_format($ab['meta_diaria'], 2, ',', '.'); ?></td>
                <td>R$ <?php echo number_format($ab['total_pago'], 2, ',', '.'); ?></td>
                <td>R$ <?php echo number_format($falta_agente, 2, ',', '.'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>Nenhum agente abaixo da meta neste período.</p>
      <?php endif; ?>
    </div>
  </div>
  
  <script>
    // Gráfico 1 – Setor: Barra comparando meta do setor e total realizado
    var ctxSector = document.getElementById('sectorChart').getContext('2d');
    var sectorChart = new Chart(ctxSector, {
        type: 'bar',
        data: {
            labels: ['Meta Setor', 'Realizado'],
            datasets: [{
                label: 'Valor (R$)',
                data: [<?php echo $meta_setor_diaria; ?>, <?php echo $total_pago; ?>],
                backgroundColor: ['#007bff', '#28a745']
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
    
    // Preparação dos dados para o gráfico dos agentes
    var agentsLabels = <?php echo json_encode(array_column($agents_data, 'nome')); ?>;
    var agentsMeta = <?php echo json_encode(array_map(function($a){ return $a['meta_diaria']; }, $agents_data)); ?>;
    var agentsPaid = <?php echo json_encode(array_map(function($a){ return $a['total_pago']; }, $agents_data)); ?>;
    
    // Gráfico 2 – Agentes: Gráfico de barras agrupadas (meta vs realizado para cada agente)
    var ctxAgents = document.getElementById('agentsChart').getContext('2d');
    var agentsChart = new Chart(ctxAgents, {
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
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Gráfico 3 – Agentes: Gráfico de pizza mostrando quantidade de agentes que bateram a meta vs os que não bateram
    var ctxAgentsPie = document.getElementById('agentsPieChart').getContext('2d');
    var agentsPieChart = new Chart(ctxAgentsPie, {
        type: 'pie',
        data: {
            labels: ['Meta Atingida', 'Abaixo da Meta'],
            datasets: [{
                data: [<?php echo $agents_met; ?>, <?php echo $agents_not_met; ?>],
                backgroundColor: ['#28a745', '#dc3545']
            }]
        },
        options: {
            responsive: true
        }
    });
  </script>
</body>
<?php include(FOOTER_FILE); ?>
</html>
