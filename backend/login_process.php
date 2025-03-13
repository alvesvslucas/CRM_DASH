<?php
session_start();
include '../db/config.php';  // arquivo de conexão com o banco
include '../absoluto.php';   // arquivo que retorna o caminho absoluto

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = trim($_POST['password'] ?? '');

  if (empty($username) || empty($password)) {
    die("Por favor, preencha os campos de usuário e senha.");
  }

  // Ajuste a consulta para selecionar também perfil, departamento, setor
  $stmt = $conn->prepare("SELECT id, username, senha, perfil, departamento, setor 
                          FROM users 
                          WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // Verifica a senha usando password_verify
    if (password_verify($password, $user['senha'])) {
      // Armazena os dados do usuário na sessão
      $_SESSION['user_id']       = $user['id'];
      $_SESSION['username']      = $user['username'];
      $_SESSION['perfil']        = $user['perfil'];
      $_SESSION['departamento']  = $user['departamento'];
      $_SESSION['setor']         = $user['setor'];

      // Redireciona para o dashboard (ou outra página)
      header("Location: " . DASH);
      exit;
    } else {
      echo '<script>alert("Senha incorreta."); window.location.href = "login.php";</script>';
    }
  } else {
    echo '<script>alert("Usuário não encontrado."); window.location.href = "login.php";</script>';
  }

  $stmt->close();
  $conn->close();
}
