<?php
session_start();
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
include(HEADER_FILE);
// Verifica se o usuário logado é supervisor e do setor Cartão
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'supervisor' || ($_SESSION['setor'] ?? '') !== 'Cartão') {
  die("Acesso negado. Apenas supervisores do setor Cartão podem acessar essa página.");
}

// Conecta ao banco
$conn = new mysqli($host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
  die("Falha na conexão: " . $conn->connect_error);
}

// --- Consulta para Venda Geral Cartão ---
$sqlGeral = "SELECT 
                SUM(vendas_total) AS total_vendas, 
                SUM(vendas_por_agente) AS vendas_por_agente 
             FROM vendas_cartao_geral 
             WHERE supervisor_id = ?";
$stmt = $conn->prepare($sqlGeral);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$resultGeral = $stmt->get_result()->fetch_assoc();
$stmt->close();

// --- Consulta para Venda por Dia Cartão ---
$sqlDia = "SELECT 
                SUM(vendas_total) AS total_vendas_dia,
                SUM(vendas_por_agente) AS vendas_por_agente_dia,
                SUM(clientes_aguardando) AS clientes_aguardando,
                SUM(clientes_finalizados) AS clientes_finalizados
           FROM vendas_cartao_dia 
           WHERE supervisor_id = ?";
$stmt = $conn->prepare($sqlDia);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$resultDia = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();

// Define valores padrão se as consultas retornarem NULL
$totalVendasGeral     = $resultGeral['total_vendas'] ?? 0;
$vendasPorAgenteGeral = $resultGeral['vendas_por_agente'] ?? 0;
$totalVendasDia       = $resultDia['total_vendas_dia'] ?? 0;
$vendasPorAgenteDia   = $resultDia['vendas_por_agente_dia'] ?? 0;
$clientesAguardando   = $resultDia['clientes_aguardando'] ?? 0;
$clientesFinalizados  = $resultDia['clientes_finalizados'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>Dashboard - Cartão</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Opcional: para gráficos, inclua Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
  <div class="container mt-5">
    <h2 class="mb-4">Dashboard - Cartão</h2>

    <div class="row">
      <!-- Cartão: Venda Geral Cartão -->
      <div class="col-md-6">
        <div class="card mb-4 shadow">
          <div class="card-header">
            <h5 class="card-title mb-0">Venda Geral Cartão</h5>
          </div>
          <div class="card-body">
            <p><strong>Vendas Total:</strong> <?php echo number_format($totalVendasGeral, 2, ',', '.'); ?></p>
            <p><strong>Vendas por Agente:</strong> <?php echo number_format($vendasPorAgenteGeral, 2, ',', '.'); ?></p>
          </div>
        </div>
      </div>

      <!-- Cartão: Venda por Dia -->
      <div class="col-md-6">
        <div class="card mb-4 shadow">
          <div class="card-header">
            <h5 class="card-title mb-0">Venda por Dia (Cartão)</h5>
          </div>
          <div class="card-body">
            <p><strong>Vendas Total:</strong> <?php echo number_format($totalVendasDia, 2, ',', '.'); ?></p>
            <p><strong>Vendas por Agente:</strong> <?php echo number_format($vendasPorAgenteDia, 2, ',', '.'); ?></p>
            <p><strong>Clientes em Rota:</strong></p>
            <ul>
              <li><strong>Aguardando atendimento:</strong> <?php echo $clientesAguardando; ?></li>
              <li><strong>Finalizados por dia:</strong> <?php echo $clientesFinalizados; ?></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
    <!-- Link para a página de digitação -->
    <!-- <div class="text-center">
      <a href="digitacao_cartao.php" class="btn btn-primary">Ir para Digitação</a>
    </div> -->
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include(FOOTER_FILE); ?>

</html>