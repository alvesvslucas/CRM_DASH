<?php
session_start();
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
include(HEADER_FILE);
// Verifica se o usuário logado é supervisor do setor Cartão
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'supervisor' || ($_SESSION['setor'] ?? '') !== 'Cartão') {
  die("Acesso negado. Apenas supervisores do setor Cartão podem acessar esta página.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $formType = $_POST['form_type'] ?? '';

  // Conecta ao banco
  $conn = new mysqli($host, $db_username, $db_password, $db_name);
  if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
  }

  if ($formType === 'geral') {
    // Dados para Venda Geral Cartão
    $vendasTotal    = floatval($_POST['vendas_total_geral'] ?? 0);
    $vendasPorAgente = floatval($_POST['vendas_por_agente_geral'] ?? 0);
    $supervisor_id  = $_SESSION['user_id'];

    $sql = "INSERT INTO vendas_cartao_geral (supervisor_id, vendas_total, vendas_por_agente, data_registro)
                VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("idd", $supervisor_id, $vendasTotal, $vendasPorAgente);

    if ($stmt->execute()) {
      $mensagemGeral = "Dados de Venda Geral Cartão inseridos com sucesso!";
    } else {
      $erroGeral = "Erro ao inserir dados de Venda Geral: " . $stmt->error;
    }
    $stmt->close();
  } elseif ($formType === 'dia') {
    // Dados para Venda por Dia (Cartão)
    $vendasTotalDia      = floatval($_POST['vendas_total_dia'] ?? 0);
    $vendasPorAgenteDia  = floatval($_POST['vendas_por_agente_dia'] ?? 0);
    $clientesAguardando  = intval($_POST['clientes_aguardando'] ?? 0);
    $clientesFinalizados = intval($_POST['clientes_finalizados'] ?? 0);
    $supervisor_id       = $_SESSION['user_id'];

    $sql = "INSERT INTO vendas_cartao_dia (supervisor_id, vendas_total, vendas_por_agente, clientes_aguardando, clientes_finalizados, data_registro)
                VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("idiii", $supervisor_id, $vendasTotalDia, $vendasPorAgenteDia, $clientesAguardando, $clientesFinalizados);

    if ($stmt->execute()) {
      $mensagemDia = "Dados de Venda por Dia Cartão inseridos com sucesso!";
    } else {
      $erroDia = "Erro ao inserir dados de Venda por Dia: " . $stmt->error;
    }
    $stmt->close();
  }

  $conn->close();
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>Digitacão - Cartão</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
  <div class="container mt-5">
    <h2 class="mb-4">Digitacão de Dados - Cartão</h2>

    <!-- Mensagens -->
    <?php if (!empty($mensagemGeral)): ?>
      <div class="alert alert-success"><?php echo $mensagemGeral; ?></div>
    <?php endif; ?>
    <?php if (!empty($erroGeral)): ?>
      <div class="alert alert-danger"><?php echo $erroGeral; ?></div>
    <?php endif; ?>
    <?php if (!empty($mensagemDia)): ?>
      <div class="alert alert-success"><?php echo $mensagemDia; ?></div>
    <?php endif; ?>
    <?php if (!empty($erroDia)): ?>
      <div class="alert alert-danger"><?php echo $erroDia; ?></div>
    <?php endif; ?>

    <!-- Formulário para Venda Geral Cartão -->
    <div class="card mb-4">
      <div class="card-header">
        <h5>Venda Geral Cartão</h5>
      </div>
      <div class="card-body">
        <form method="POST" action="">
          <input type="hidden" name="form_type" value="geral">
          <div class="mb-3">
            <label for="vendas_total_geral" class="form-label">Vendas Total</label>
            <input type="number" step="0.01" name="vendas_total_geral" id="vendas_total_geral" class="form-control" required>
          </div>
          <div class="mb-3">
            <label for="vendas_por_agente_geral" class="form-label">Vendas por Agente</label>
            <input type="number" step="0.01" name="vendas_por_agente_geral" id="vendas_por_agente_geral" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary">Salvar Venda Geral</button>
        </form>
      </div>
    </div>

    <!-- Formulário para Venda por Dia Cartão -->
    <div class="card mb-4">
      <div class="card-header">
        <h5>Venda por Dia (Cartão)</h5>
      </div>
      <div class="card-body">
        <form method="POST" action="">
          <input type="hidden" name="form_type" value="dia">
          <div class="mb-3">
            <label for="vendas_total_dia" class="form-label">Vendas Total</label>
            <input type="number" step="0.01" name="vendas_total_dia" id="vendas_total_dia" class="form-control" required>
          </div>
          <div class="mb-3">
            <label for="vendas_por_agente_dia" class="form-label">Vendas por Agente</label>
            <input type="number" step="0.01" name="vendas_por_agente_dia" id="vendas_por_agente_dia" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Clientes em Rota</label>
            <div class="row">
              <div class="col">
                <label for="clientes_aguardando" class="form-label">Aguardando atendimento</label>
                <input type="number" name="clientes_aguardando" id="clientes_aguardando" class="form-control" required>
              </div>
              <div class="col">
                <label for="clientes_finalizados" class="form-label">Finalizados por dia</label>
                <input type="number" name="clientes_finalizados" id="clientes_finalizados" class="form-control" required>
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary">Salvar Venda por Dia</button>
        </form>
      </div>
    </div>

    <!-- Link para voltar ao Dashboard -->
    <!-- <div class="text-center">
      <a href="dashboard_cartao.php" class="btn btn-secondary">Voltar ao Dashboard</a>
    </div> -->
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include(FOOTER_FILE); ?>

</html>