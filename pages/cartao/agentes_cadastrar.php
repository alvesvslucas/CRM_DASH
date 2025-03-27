<?php
session_start();

// Verifique se o usuário logado tem permissão para cadastrar agentes.
// Por exemplo, só admin ou supervisor do setor Cartão podem acessar esta página.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['perfil'] ?? '', ['admin', 'supervisor']) || ($_SESSION['setor'] ?? '') !== 'Cartão') {
  die("Acesso negado. Apenas administradores ou supervisores do setor Cartão podem cadastrar agentes.");
}


// Inclua os arquivos de configuração (ajuste os caminhos conforme sua estrutura)
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
include(HEADER_FILE);
// Processa o cadastro do agente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome = trim($_POST['nome'] ?? '');
  if (empty($nome)) {
    $erro = "Por favor, informe o nome do agente.";
  } else {
    // Conecta ao banco
    $conn = new mysqli($host, $db_username, $db_password, $db_name);
    if ($conn->connect_error) {
      die("Falha na conexão: " . $conn->connect_error);
    }
    $sql = "INSERT INTO agentes_cartao (nome) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nome);
    if ($stmt->execute()) {
      $sucesso = "Agente cadastrado com sucesso!";
    } else {
      $erro = "Erro ao cadastrar agente: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>Cadastrar Agentes</title>
  <link rel="icon" href="<?= ICON_PATH ?>" type="image/x-icon">
  <link rel="shortcut icon" href="<?= ICON_PATH ?>" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f5f5f5;
    }
  </style>
</head>

<body class="bg-light">
  <div class="container mt-5">
    <h2 class="mb-4">Cadastrar Agente do Cartão</h2>
    <?php if (!empty($erro)): ?>
      <div class="alert alert-danger"><?php echo $erro; ?></div>
    <?php endif; ?>
    <?php if (!empty($sucesso)): ?>
      <div class="alert alert-success"><?php echo $sucesso; ?></div>
    <?php endif; ?>
    <form method="POST" action="">
      <div class="mb-3">
        <label for="nome" class="form-label">Nome do Agente</label>
        <input type="text" name="nome" id="nome" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary">Cadastrar</button>
    </form>

    <hr>
    <h3 class="mt-4">Agentes Cadastrados</h3>
    <?php
    // Conecta e lista os agentes cadastrados
    $conn = new mysqli($host, $db_username, $db_password, $db_name);
    if ($conn->connect_error) {
      die("Falha na conexão: " . $conn->connect_error);
    }
    $sql = "SELECT id, nome, ativo FROM agentes_cartao ORDER BY nome ASC";
    $result = $conn->query($sql);
    if ($result->num_rows > 0):
    ?>
      <table class="table table-bordered mt-3">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Status</th>
            <th width="150">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($agent = $result->fetch_assoc()): ?>
            <tr>
              <td><?php echo $agent['id']; ?></td>
              <td><?php echo $agent['nome']; ?></td>
              <td><?php echo ($agent['ativo'] == 1) ? '<span class="text-success">Ativo</span>' : '<span class="text-danger">Inativo</span>'; ?></td>
              <td>
                <?php if ($agent['ativo'] == 1): ?>
                  <a href="agentes_status.php?acao=desativar&id=<?php echo $agent['id']; ?>" class="btn btn-danger btn-sm"
                    onclick="return confirm('Tem certeza que deseja DESATIVAR este agente?');">Desativar</a>
                <?php else: ?>
                  <a href="agentes_status.php?acao=ativar&id=<?php echo $agent['id']; ?>" class="btn btn-success btn-sm"
                    onclick="return confirm('Tem certeza que deseja ATIVAR este agente?');">Ativar</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="mt-3">Nenhum agente cadastrado.</p>
    <?php endif; ?>
    <?php $conn->close(); ?>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include(FOOTER_FILE); ?>

</html>