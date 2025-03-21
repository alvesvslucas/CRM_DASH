<?php
session_start();
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
include(HEADER_FILE);

// Apenas administradores podem ativar agentes (ajuste conforme necessário)
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'supervisor' || ($_SESSION['setor'] ?? '') !== 'Energia') {
  die("Acesso negado. Apenas supervisores do setor Energia podem acessar esta página.");
}

// Conecta ao banco de dados
$conn = new mysqli($host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Se o parâmetro 'id' for enviado, ativa o agente correspondente
if (isset($_GET['id'])) {
    $agentId = intval($_GET['id']);
    $stmt = $conn->prepare("UPDATE agentes_energia SET ativo = 1 WHERE id = ?");
    $stmt->bind_param("i", $agentId);
    if ($stmt->execute()) {
        $msg = "Agente ativado com sucesso.";
    } else {
        $error = "Erro ao ativar agente: " . $stmt->error;
    }
    $stmt->close();
}

// Consulta os agentes inativos (ativo = 0) do setor Energia
$sql = "SELECT id, nome FROM agentes_energia WHERE ativo = 0 ORDER BY nome ASC";
$result = $conn->query($sql);
$inactiveAgents = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inactiveAgents[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Ativar Agente - Energia</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f9f9f9; }
    .container { margin-top: 2rem; }
  </style>
</head>
<body>
<div class="container">
  <h1 class="mb-4">Ativar Agente - Energia</h1>

  <?php if (!empty($msg)): ?>
    <div class="alert alert-success"><?php echo $msg; ?></div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
  <?php endif; ?>

  <?php if (count($inactiveAgents) > 0): ?>
    <div class="table-responsive">
      <table class="table table-striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Ação</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($inactiveAgents as $agent): ?>
            <tr>
              <td><?php echo $agent['id']; ?></td>
              <td><?php echo htmlspecialchars($agent['nome']); ?></td>
              <td>
                <a href="ativar_agente_energia.php?id=<?php echo $agent['id']; ?>"
                   class="btn btn-sm btn-primary"
                   onclick="return confirm('Deseja ativar este agente?');">
                   Ativar
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p>Todos os agentes já estão ativados.</p>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include(FOOTER_FILE); ?>
</body>
</html>
