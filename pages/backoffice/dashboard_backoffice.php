<?php
include_once '../../db/db.php';
include_once '../../absoluto.php';
include_once HEADER_FILE;
$conn = getDatabaseConnection();

// ----- Processamento dos Filtros -----
$view = isset($_GET['view']) ? $_GET['view'] : 'mensal';  // 'mensal' ou 'diario'
if ($view == 'mensal') {
    $start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $end_date   = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
} else { // visualização diária
    $start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
    $end_date   = $start_date;
}
$filter_digitador = isset($_GET['digitador_id']) ? $_GET['digitador_id'] : '';

// Condição de data para as consultas (usada em alguns queries)
if ($view == 'mensal') {
    $dateCondition = "d.data_pagamento BETWEEN '$start_date' AND '$end_date'";
} else {
    $dateCondition = "DATE(d.data_pagamento) = '$start_date'";
}
$digitadorCondition = ($filter_digitador != '') ? " AND d.digitador_id = " . intval($filter_digitador) : "";

// ----- Consulta 1: Totais Pagos (Geral) -----
$sqlTotalPaid = "SELECT COUNT(*) AS totalPaid, SUM(valor_liquido) AS totalValue 
                 FROM digitacoes d 
                 WHERE d.status = 'PAGO' AND $dateCondition $digitadorCondition";
$resultTotalPaid = $conn->query($sqlTotalPaid);
$rowTotalPaid = $resultTotalPaid->fetch_assoc();
$totalPaid = $rowTotalPaid['totalPaid'] ?? 0;
$totalValue = $rowTotalPaid['totalValue'] ?? 0;

// ----- Consulta 2: Ranking por Digitador (Pagos no período) -----
$sqlPaidByDigitador = "
    SELECT d.digitador_id, u.nome AS digitador_nome, COUNT(*) AS totalPaid, SUM(d.valor_liquido) AS totalValue
    FROM digitacoes d
    JOIN users u ON d.digitador_id = u.id
    WHERE d.status = 'PAGO' AND $dateCondition $digitadorCondition
    GROUP BY d.digitador_id
    ORDER BY totalPaid DESC
";
$resultPaidByDigitador = $conn->query($sqlPaidByDigitador);
$rankingData = [];
while ($row = $resultPaidByDigitador->fetch_assoc()) {
    $rankingData[] = $row;
}

// ----- Consulta 3: Metas Globais -----
$sqlMetaGlobal = "SELECT meta_diaria, meta_semanal, meta_mensal FROM metas_global_credesh LIMIT 1";
$resultMetaGlobal = $conn->query($sqlMetaGlobal);
if ($resultMetaGlobal->num_rows > 0) {
   $metaGlobal = $resultMetaGlobal->fetch_assoc();
} else {
   $metaGlobal = ['meta_diaria' => 0, 'meta_semanal' => 0, 'meta_mensal' => 0];
}

// ----- Consulta 4: Lista de Digitadores para o filtro -----
$sqlDigitadores = "SELECT id, nome FROM users WHERE tipo = 'digitador'";
$resultDigitadores = $conn->query($sqlDigitadores);
$digitadoresList = [];
while ($row = $resultDigitadores->fetch_assoc()) {
    $digitadoresList[] = $row;
}

// ----- Consulta 5: Dados do CRM (Valores digitados e quantidade de digitação) -----
// Aqui assume-se que a tabela "digitacoes" possui o campo "valor_liquido" com os valores digitados no CRM.
$sqlCRM = "SELECT d.digitador_id, u.nome AS digitador_nome, COUNT(*) AS totalDigitacoes, SUM(d.valor_liquido) AS totalCRMValue
           FROM digitacoes d
           JOIN users u ON d.digitador_id = u.id
           WHERE $dateCondition $digitadorCondition
           GROUP BY d.digitador_id
           ORDER BY totalDigitacoes DESC";
$resultCRM = $conn->query($sqlCRM);
$crmData = [];
while ($row = $resultCRM->fetch_assoc()) {
    $crmData[] = $row;
}

// ----- Consulta 6: Pagamentos Diário e Mensal por Digitador -----
$today = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');
$lastDayOfMonth = date('Y-m-t');

$sqlPaidDaily = "SELECT d.digitador_id, u.nome AS digitador_nome, COUNT(*) AS totalPaidDaily, SUM(d.valor_liquido) AS totalValueDaily
                 FROM digitacoes d
                 JOIN users u ON d.digitador_id = u.id
                 WHERE d.status = 'PAGO' AND DATE(d.data_pagamento) = '$today'
                 GROUP BY d.digitador_id";
$resultPaidDaily = $conn->query($sqlPaidDaily);
$paidDailyData = [];
while ($row = $resultPaidDaily->fetch_assoc()) {
    $paidDailyData[] = $row;
}

$sqlPaidMonthly = "SELECT d.digitador_id, u.nome AS digitador_nome, COUNT(*) AS totalPaidMonthly, SUM(d.valor_liquido) AS totalValueMonthly
                   FROM digitacoes d
                   JOIN users u ON d.digitador_id = u.id
                   WHERE d.status = 'PAGO' AND d.data_pagamento BETWEEN '$firstDayOfMonth' AND '$lastDayOfMonth'
                   GROUP BY d.digitador_id";
$resultPaidMonthly = $conn->query($sqlPaidMonthly);
$paidMonthlyData = [];
while ($row = $resultPaidMonthly->fetch_assoc()) {
    $paidMonthlyData[] = $row;
}

// Combinar os dados diário e mensal em um único array por digitador
$paidData = [];
foreach ($paidDailyData as $row) {
    $id = $row['digitador_id'];
    $paidData[$id] = [
        'digitador_nome' => $row['digitador_nome'],
        'totalPaidDaily' => $row['totalPaidDaily'] ?? 0,
        'totalValueDaily' => $row['totalValueDaily'] ?? 0,
        'totalPaidMonthly' => 0,
        'totalValueMonthly' => 0
    ];
}
foreach ($paidMonthlyData as $row) {
    $id = $row['digitador_id'];
    if(isset($paidData[$id])) {
        $paidData[$id]['totalPaidMonthly'] = $row['totalPaidMonthly'] ?? 0;
        $paidData[$id]['totalValueMonthly'] = $row['totalValueMonthly'] ?? 0;
    } else {
        $paidData[$id] = [
            'digitador_nome' => $row['digitador_nome'],
            'totalPaidDaily' => 0,
            'totalValueDaily' => 0,
            'totalPaidMonthly' => $row['totalPaidMonthly'] ?? 0,
            'totalValueMonthly' => $row['totalValueMonthly'] ?? 0
        ];
    }
}

// ----- Consulta 7: Totais Gerais para Metas Diária e Mensal (Overall) -----
$sqlPaidOverallDaily = "SELECT COUNT(*) AS totalPaidDaily, SUM(valor_liquido) AS totalValueDaily 
                        FROM digitacoes 
                        WHERE status = 'PAGO' AND DATE(data_pagamento) = '$today'";
$resultPaidOverallDaily = $conn->query($sqlPaidOverallDaily);
$rowPaidOverallDaily = $resultPaidOverallDaily->fetch_assoc();
$totalPaidOverallDaily = $rowPaidOverallDaily['totalPaidDaily'] ?? 0;
$totalValueOverallDaily = $rowPaidOverallDaily['totalValueDaily'] ?? 0;

$sqlPaidOverallMonthly = "SELECT COUNT(*) AS totalPaidMonthly, SUM(valor_liquido) AS totalValueMonthly 
                          FROM digitacoes 
                          WHERE status = 'PAGO' AND data_pagamento BETWEEN '$firstDayOfMonth' AND '$lastDayOfMonth'";
$resultPaidOverallMonthly = $conn->query($sqlPaidOverallMonthly);
$rowPaidOverallMonthly = $resultPaidOverallMonthly->fetch_assoc();
$totalPaidOverallMonthly = $rowPaidOverallMonthly['totalPaidMonthly'] ?? 0;
$totalValueOverallMonthly = $rowPaidOverallMonthly['totalValueMonthly'] ?? 0;

// ----- Métricas de Metas -----
$dailyGoalPerc = ($metaGlobal['meta_diaria'] > 0) ? round(($totalValueOverallDaily / $metaGlobal['meta_diaria']) * 100, 2) : 0;
$monthlyGoalPerc = ($metaGlobal['meta_mensal'] > 0) ? round(($totalValueOverallMonthly / $metaGlobal['meta_mensal']) * 100, 2) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Backoffice Moderno</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>
<body>
 
  <div class="container">
    <!-- Formulário de Filtros -->
    <div class="row mb-4">
      <div class="col-12">
        <form method="GET" class="row gy-2 gx-3 align-items-end">
          <div class="col-md-3">
            <label for="view" class="form-label">Visualização</label>
            <select name="view" id="view" class="form-select">
              <option value="mensal" <?php echo ($view == 'mensal') ? 'selected' : ''; ?>>Mensal</option>
              <option value="diario" <?php echo ($view == 'diario') ? 'selected' : ''; ?>>Diário</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="start_date" class="form-label"><?php echo ($view == 'mensal') ? 'Data Início' : 'Data'; ?></label>
            <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
          </div>
          <?php if($view == 'mensal'): ?>
          <div class="col-md-3">
            <label for="end_date" class="form-label">Data Fim</label>
            <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
          </div>
          <?php endif; ?>
          <div class="col-md-3">
            <label for="digitador_id" class="form-label">Digitador</label>
            <select name="digitador_id" id="digitador_id" class="form-select">
              <option value="">Todos</option>
              <?php foreach($digitadoresList as $d): ?>
                <option value="<?php echo $d['id']; ?>" <?php echo ($filter_digitador == $d['id']) ? 'selected' : ''; ?>>
                  <?php echo $d['nome']; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-12 text-end">
            <button type="submit" class="btn btn-primary">Filtrar</button>
          </div>
        </form>
      </div>
    </div>
    
    <!-- Cards de Informações -->
    <div class="row">
      <!-- Card 1: CRM Digitador -->
      <div class="col-md-4">
        <div class="card border-primary">
          <div class="card-header bg-primary text-white">CRM Digitador</div>
          <div class="card-body">
            <?php if(count($crmData) > 0): ?>
              <table class="table table-sm">
                <thead>
                  <tr>
                    <th>Digitador</th>
                    <th>Entradas</th>
                    <th>Valor CRM</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($crmData as $c): ?>
                    <tr>
                      <td><?php echo $c['digitador_nome']; ?></td>
                      <td><?php echo $c['totalDigitacoes']; ?></td>
                      <td>R$ <?php echo number_format($c['totalCRMValue'], 2, ',', '.'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <p class="text-muted">Nenhum dado disponível.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Card 2: Pagamentos Diário e Mensal por Digitador -->
      <div class="col-md-4">
        <div class="card border-success">
          <div class="card-header bg-success text-white">Pagamentos: Diário e Mensal</div>
          <div class="card-body">
            <?php if(count($paidData) > 0): ?>
              <table class="table table-sm">
                <thead>
                  <tr>
                    <th>Digitador</th>
                    <th>Dia (R$)</th>
                    <th>Mês (R$)</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($paidData as $p): ?>
                    <tr>
                      <td><?php echo $p['digitador_nome']; ?></td>
                      <td>R$ <?php echo number_format($p['totalValueDaily'], 2, ',', '.'); ?></td>
                      <td>R$ <?php echo number_format($p['totalValueMonthly'], 2, ',', '.'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <p class="text-muted">Nenhum dado disponível.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Card 3: Pagos Geral -->
      <div class="col-md-4">
        <div class="card border-info">
          <div class="card-header bg-info text-white">Pagos Geral</div>
          <div class="card-body">
            <h5>Total de Pagos: <?php echo $totalPaid; ?></h5>
            <p>Valor Total: R$ <?php echo number_format($totalValue, 2, ',', '.'); ?></p>
            <p>Período: <?php echo date('d/m/Y', strtotime($start_date)); ?> a <?php echo date('d/m/Y', strtotime($end_date)); ?></p>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Cards de Metas -->
    <div class="row">
      <div class="col-md-6">
        <div class="card border-warning">
          <div class="card-header bg-warning text-white">Meta Diária</div>
          <div class="card-body">
            <p>Meta: R$ <?php echo number_format($metaGlobal['meta_diaria'], 2, ',', '.'); ?></p>
            <p>Realizado Hoje: R$ <?php echo number_format($totalValueOverallDaily, 2, ',', '.'); ?></p>
            <p>Atingido: <?php echo $dailyGoalPerc; ?>%</p>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card border-warning">
          <div class="card-header bg-warning text-white">Meta Mensal</div>
          <div class="card-body">
            <p>Meta: R$ <?php echo number_format($metaGlobal['meta_mensal'], 2, ',', '.'); ?></p>
            <p>Realizado no Mês: R$ <?php echo number_format($totalValueOverallMonthly, 2, ',', '.'); ?></p>
            <p>Atingido: <?php echo $monthlyGoalPerc; ?>%</p>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Gráficos -->
    <div class="row mt-4">
      <!-- Gráfico: Entradas no CRM por Digitador -->
      <div class="col-md-6">
        <h5 class="text-center">CRM - Entradas por Digitador</h5>
        <canvas id="chartCRM"></canvas>
      </div>
      <!-- Gráfico: Pagamentos Diário vs Mensal -->
      <div class="col-md-6">
        <h5 class="text-center">Pagamentos: Diário vs Mensal</h5>
        <canvas id="chartPayments"></canvas>
      </div>
    </div>
  </div>
  
  <!-- Bootstrap JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Gráfico CRM por Digitador
    const crmLabels = <?php echo json_encode(array_column($crmData, 'digitador_nome')); ?>;
    const crmEntries = <?php echo json_encode(array_column($crmData, 'totalDigitacoes')); ?>;
    const ctxCRM = document.getElementById('chartCRM').getContext('2d');
    new Chart(ctxCRM, {
      type: 'bar',
      data: {
        labels: crmLabels,
        datasets: [{
          label: 'Entradas no CRM',
          data: crmEntries,
          backgroundColor: 'rgba(75, 192, 192, 0.5)',
          borderColor: 'rgba(75, 192, 192, 1)',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        scales: { y: { beginAtZero: true } }
      }
    });
    
    // Gráfico Pagamentos: Diário vs Mensal
    const paymentLabels = <?php echo json_encode(array_column($paidData, 'digitador_nome')); ?>;
    const dailyPayments = <?php 
      $daily = [];
      foreach($paidData as $p) {
          $daily[] = $p['totalValueDaily'];
      }
      echo json_encode($daily);
    ?>;
    const monthlyPayments = <?php 
      $monthly = [];
      foreach($paidData as $p) {
          $monthly[] = $p['totalValueMonthly'];
      }
      echo json_encode($monthly);
    ?>;
    const ctxPayments = document.getElementById('chartPayments').getContext('2d');
    new Chart(ctxPayments, {
      type: 'bar',
      data: {
        labels: paymentLabels,
        datasets: [
          {
            label: 'Diário (R$)',
            data: dailyPayments,
            backgroundColor: 'rgba(255, 99, 132, 0.5)',
            borderColor: 'rgba(255, 99, 132, 1)',
            borderWidth: 1
          },
          {
            label: 'Mensal (R$)',
            data: monthlyPayments,
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
          }
        ]
      },
      options: {
        responsive: true,
        scales: { y: { beginAtZero: true } }
      }
    });
  </script>
</body>
</html>
