<?php
session_start();
include '../db/config.php'; // Aqui esperamos que a conexão com o banco esteja disponível, por exemplo em $conn
include '../absoluto.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = trim($_POST['password'] ?? '');
  $remember_me = isset($_POST['remember_me']);

  // Verifica se os campos não estão vazios
  if (empty($username) || empty($password)) {
    die("Por favor, preencha os campos de usuário e senha.");
  }

  // Prepara a query para buscar o usuário
  $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();

  // Verifica se o usuário existe
  if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    // Verifica a senha utilizando password_verify (assumindo que o password no BD é hash)
    if (password_verify($password, $user['password'])) {
      // Autenticação bem-sucedida: define as variáveis de sessão
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['username'] = $user['username'];

      // Se "Mantenha-me conectado" estiver marcado, podemos definir um cookie com um token único (a implementação pode variar)
      if ($remember_me) {
        // Exemplo simples (lembre-se de aprimorar para produção):
        setcookie("remember_me", $user['id'], time() + (86400 * 30), "/"); // Expira em 30 dias
      }

      // Redireciona para o dashboard
      header("Location: " . DASH);
      exit;
    } else {
      echo '<script>
        alert("Senha incorreta.");
        window.location.href = "../index.php";
      </script>';
      exit;
    }
  } else {
    echo "Usuário não encontrado.";
  }

  $stmt->close();
  $conn->close();
}
