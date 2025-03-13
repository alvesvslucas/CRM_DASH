<?php
session_start();

// Ajuste os caminhos de acordo com a estrutura do seu projeto
include '../../absoluto.php';
include '../../db/config.php';

// Verifica se o usuário logado é admin
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') == 'admin') {
  die("Acesso negado. Apenas administradores podem cadastrar usuários.");
}

// Se o formulário foi enviado (método POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Captura os campos
  $nome         = trim($_POST['nome'] ?? '');
  $username     = trim($_POST['username'] ?? '');
  $senha        = trim($_POST['senha'] ?? '');
  $perfil       = trim($_POST['perfil'] ?? '');  // Padrão 'usuario'
  $departamento = trim($_POST['departamento'] ?? '');
  $setor        = trim($_POST['setor'] ?? '');

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

    // Prepara a query para inserir
    $sql = "INSERT INTO users (nome, username, senha, perfil, departamento, setor) 
                VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $nome, $username, $senhaHash, $perfil, $departamento, $setor);

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
</head>

<body class="bg-light">

  <div class="container mt-5">
    <h2>Cadastrar Novo Usuário</h2>

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
      <div class="mb-3">
        <label for="departamento" class="form-label">Departamento</label>
        <select name="departamento" id="departamento" class="form-select">
          <option value="">-- Selecione --</option>
          <option value="Rede">Rede</option>
          <option value="Tele">Tele</option>
          <option value="Lojas">Lojas</option>
        </select>
      </div>
      <div class="mb-3">
        <label for="setor" class="form-label">Setor</label>
        <select name="setor" id="setor" class="form-select">
          <option value="">-- Selecione --</option>
          <option value="Energia">Energia</option>
          <option value="Consignado">Consignado</option>
          <option value="FGTS">FGTS</option>
          <option value="Cartão">Cartão</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Cadastrar</button>
    </form>
  </div>

  <!-- Bootstrap JS -->
  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>