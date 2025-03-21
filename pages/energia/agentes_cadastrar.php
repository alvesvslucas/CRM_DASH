<?php
session_start();
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
include(HEADER_FILE);

// Verifica se o usuário logado é supervisor do setor Energia
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'supervisor' || ($_SESSION['setor'] ?? '') !== 'Energia') {
  die("Acesso negado. Apenas supervisores do setor Energia podem acessar esta página.");
}

$erro = $mensagem = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome = trim($_POST['nome'] ?? '');
  $ativo = isset($_POST['ativo']) ? 1 : 0;

  if (empty($nome)) {
    $erro = "Por favor, preencha o nome do agente.";
  } else {
    $conn = new mysqli($host, $db_username, $db_password, $db_name);
    if ($conn->connect_error) {
      die("Falha na conexão: " . $conn->connect_error);
    }

    $sql = "INSERT INTO agentes_energia (nome, ativo) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
      $stmt->bind_param("si", $nome, $ativo);
      if ($stmt->execute()) {
        $mensagem = "Agente cadastrado com sucesso!";
      } else {
        $erro = "Erro ao cadastrar agente: " . $stmt->error;
      }
      $stmt->close();
    } else {
      $erro = "Erro na preparação da query: " . $conn->error;
    }
    $conn->close();
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>Cadastrar Agente - Energia</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f5f5f5;
    }

    .card {
      margin-top: 2rem;
      padding: 1.5rem;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      background: #fff;
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="card">
      <h2 class="card-title text-center mb-4">Cadastrar Novo Agente - Energia</h2>

      <?php if (!empty($erro)): ?>
        <div class="alert alert-danger"><?php echo $erro; ?></div>
      <?php endif; ?>
      <?php if (!empty($mensagem)): ?>
        <div class="alert alert-success"><?php echo $mensagem; ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="mb-3">
          <label for="nome" class="form-label">Nome do Agente</label>
          <input type="text" class="form-control" id="nome" name="nome" required>
        </div>
        <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="ativo" name="ativo" checked>
          <label class="form-check-label" for="ativo">Ativo</label>
        </div>
        <button type="submit" class="btn btn-primary">Cadastrar</button>
      </form>

      <div class="mt-3">
        <a href="lista_agentes_energia.php" class="btn btn-secondary">Ver Lista de Agentes</a>
        <a href="dashboard_energia.php" class="btn btn-outline-secondary">Voltar ao Dashboard</a>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>