<?php
// Inclui o arquivo de configuração que contém a conexão $conn
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
include(HEADER_FILE);

// Verifica se o usuário é supervisor do setor Energia
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'supervisor' || ($_SESSION['setor'] ?? '') !== 'FGTS') {
  die("Acesso negado. Somente o Supervisor do FGTS tem permissão para acessar esta página.");
}

// Variável para exibir mensagem de sucesso ou erro
$mensagem = "";

// Verifica se o formulário foi enviado (método POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  // Recebe e sanitiza os dados do formulário
  $nome  = trim($_POST['nome']);
  $email = trim($_POST['email']);

  // Prepara o INSERT na tabela agentes_fgts
  $stmt = $conn->prepare("INSERT INTO agentes_fgts (nome, email) VALUES (?, ?)");
  $stmt->bind_param("ss", $nome, $email);

  // Executa e verifica se foi bem-sucedido
  if ($stmt->execute()) {
    $mensagem = "Agente cadastrado com sucesso!";
  } else {
    $mensagem = "Erro ao cadastrar agente: " . $stmt->error;
  }

  $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Cadastro de Agente</title>
  <style>
    .titulo {
      text-align: center;
    }

    body {
      font-family: Arial, sans-serif;
      margin: 20px;
    }

    form {
      max-width: 400px;
      margin: auto;
    }

    label {
      display: block;
      margin-top: 15px;
    }

    input,
    button {
      width: 100%;
      padding: 8px;
      margin-top: 5px;
      box-sizing: border-box;
    }
  </style>
</head>

<body>
  <h1 class="titulo">Cadastro de Agente</h1>

  <!-- Exibe a mensagem de sucesso ou erro -->
  <?php if (!empty($mensagem)): ?>
    <p><?php echo $mensagem; ?></p>
  <?php endif; ?>

  <!-- Formulário de Cadastro -->
  <form method="POST" action="">
    <label for="nome">Nome do Agente:</label>
    <input type="text" id="nome" name="nome" placeholder="Digite o nome do agente" required>

    <label for="email">Email do Agente:</label>
    <input type="email" id="email" name="email" placeholder="Digite o email do agente" required>

    <button type="submit" style="margin-top:20px;">Cadastrar Agente</button>
  </form>
</body>

</html>