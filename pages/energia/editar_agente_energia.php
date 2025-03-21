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
$agentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($agentId <= 0) {
    die("ID inválido.");
}

$conn = new mysqli($host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Se o formulário foi enviado, atualiza o agente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    if (empty($nome)) {
        $erro = "O nome não pode ser vazio.";
    } else {
        $stmt = $conn->prepare("UPDATE agentes_energia SET nome = ?, ativo = ? WHERE id = ?");
        $stmt->bind_param("sii", $nome, $ativo, $agentId);
        if ($stmt->execute()) {
            $mensagem = "Agente atualizado com sucesso.";
        } else {
            $erro = "Erro ao atualizar agente: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Seleciona o agente para preencher o formulário
$stmt = $conn->prepare("SELECT id, nome, ativo FROM agentes_energia WHERE id = ?");
$stmt->bind_param("i", $agentId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Agente não encontrado.");
}
$agent = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Editar Agente - Energia</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f5f5f5; }
    .container { margin-top: 2rem; }
    .card {
      padding: 1.5rem;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      background: #fff;
    }
  </style>
</head>
<body>
<div class="container">
  <div class="card">
    <h2 class="text-center mb-4">Editar Agente - Energia</h2>
    <?php if (!empty($erro)): ?>
      <div class="alert alert-danger"><?= $erro ?></div>
    <?php endif; ?>
    <?php if (!empty($mensagem)): ?>
      <div class="alert alert-success"><?= $mensagem ?></div>
    <?php endif; ?>
    <form method="POST" action="">
      <div class="mb-3">
        <label for="nome" class="form-label">Nome do Agente</label>
        <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($agent['nome']) ?>" required>
      </div>
      <div class="mb-3 form-check">
        <input type="checkbox" class="form-check-input" id="ativo" name="ativo" <?= $agent['ativo'] ? "checked" : "" ?>>
        <label class="form-check-label" for="ativo">Ativo</label>
      </div>
      <button type="submit" class="btn btn-primary">Atualizar</button>
      <a href="lista_agentes_energia.php" class="btn btn-secondary">Voltar à Lista</a>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include(FOOTER_FILE); ?>
</html>
