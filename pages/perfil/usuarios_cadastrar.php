<?php
session_start();

// Ajuste os caminhos de acordo com a estrutura do seu projeto
include '../../absoluto.php';
include '../../db/config.php';
// require_once __DIR__ . '/../../db/config.php';



// Verifica se o usuário logado é admin
// Note que usamos !== 'admin' para BLOQUEAR quem não é admin.
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'admin') {
  die("Acesso negado. Apenas administradores podem cadastrar usuários.");
}
include(HEADER_FILE);
// Se o formulário foi enviado (método POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Captura os campos
  $nome     = trim($_POST['nome'] ?? '');
  $username = trim($_POST['username'] ?? '');
  $senha    = trim($_POST['senha'] ?? '');
  $perfil   = trim($_POST['perfil'] ?? 'usuario');  // Padrão 'usuario'
  $setor    = trim($_POST['setor'] ?? '');

  // Valida se não estão vazios
  if (empty($nome) || empty($username) || empty($senha)) {
    $erro = "Por favor, preencha nome, username e senha.";
  } else {
    // Gera o hash da senha
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    // Conecta ao banco
    $conn = new mysqli($host, $db_username, $db_password, $db_name);
    if ($conn->connect_error) {
      die("Falha na conexão: " . $conn->connect_error);
    }

    // Se a tabela 'users' ainda tiver a coluna 'departamento',
    // e você NÃO quiser preenchê-la, pode usar um valor nulo ou default:
    // $sql = "INSERT INTO users (nome, username, senha, perfil, setor, departamento)
    //         VALUES (?, ?, ?, ?, ?, NULL)";

    // Caso sua tabela seja somente (nome, username, senha, perfil, setor):
    $sql = "INSERT INTO users (nome, username, senha, perfil, setor) 
                VALUES (?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $nome, $username, $senhaHash, $perfil, $setor);

    if ($stmt->execute()) {
      $sucesso = "Usuário cadastrado com sucesso!";
    } else {
      $erro = "Erro ao cadastrar usuário: " . $stmt->error;
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
  <title>Cadastrar Usuários</title>
  <!-- Bootstrap CSS -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet" />
  <link rel="icon" href="<?= ICON_PATH ?>" type="image/x-icon">
  <link rel="shortcut icon" href="<?= ICON_PATH ?>" type="image/x-icon">
</head>

<body class="bg-light">
  <div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh;">
    <div class="card shadow p-4" style="max-width: 480px; width: 100%;">
      <h3 class="mb-4 text-center">Cadastrar Usuário</h3>

      <!-- Exibe mensagem de erro ou sucesso, se existirem -->
      <?php if (!empty($erro)): ?>
        <div class="alert alert-danger"><?php echo $erro; ?></div>
      <?php endif; ?>

      <?php if (!empty($sucesso)): ?>
        <div class="alert alert-success"><?php echo $sucesso; ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="mb-3">
          <label for="nome" class="form-label">Nome Completo</label>
          <input type="text" name="nome" id="nome" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="username" class="form-label">Username (login)</label>
          <input type="text" name="username" id="username" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="senha" class="form-label">Senha</label>
          <input type="password" name="senha" id="senha" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="perfil" class="form-label">Perfil</label>
          <select name="perfil" id="perfil" class="form-select">
            <option value="usuario">Usuário (padrão)</option>
            <option value="admin">Administrador</option>
            <option value="supervisor">Supervisor</option>
            <option value="tv_indoor">TV Indoor</option>
          </select>
        </div>
        <!-- Se quiser manter Setor no cadastro de usuário, deixe essa parte -->
        <div class="mb-3">
          <label for="setor" class="form-label">Setor</label>
          <select name="setor" id="setor" class="form-select">
            <option value="">-- Selecione --</option>
            <option value="Energia">Energia</option>
            <option value="Consignado">Consignado</option>
            <option value="Cartão">Backoffice</option>
            <option value="FGTS">FGTS</option>
            <option value="Cartão">Cartão</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary w-100">Cadastrar</button>
      </form>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include(FOOTER_FILE); ?>

</html>