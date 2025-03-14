<?php
session_start();
include '../db/config.php';  // arquivo de conexão com o banco
include '../absoluto.php';   // arquivo que retorna o caminho absoluto
include '(header)'

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = trim($_POST['password'] ?? '');

  if (empty($username) || empty($password)) {
    die("Por favor, preencha os campos de usuário e senha.");
  }

  // Consulta para selecionar id, username, senha, perfil e setor
  $stmt = $conn->prepare("SELECT id, username, senha, perfil, setor FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // Verifica a senha usando password_verify
    if (password_verify($password, $user['senha'])) {
      // Armazena os dados do usuário na sessão
      $_SESSION['user_id']  = $user['id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['perfil']   = $user['perfil'];
      $_SESSION['setor']    = $user['setor'];

      // Redireciona com base no perfil e setor
      if ($user['perfil'] === 'supervisor') {
        // Exemplo: se o supervisor for do setor "Cartão"
        if ($user['setor'] === 'Cartão') {
          header("Location: " . DASH_CARTAO); // Defina DASH_CARTAO em absoluto.php
        }
        // Você pode adicionar outros if/elseif para outros setores:
        elseif ($user['setor'] === 'Energia') {
          // header("Location: " . DASH_ENERGIA);
        } elseif ($user['setor'] === 'Consignado') {
          // header("Location: " . DASH_CONSIGNADO);
        } elseif ($user['setor'] === 'FGTS') {
          // header("Location: " . DASH_FGTS);
        } else {
          // Caso o setor não seja reconhecido, redireciona para um dashboard padrão para supervisores
          // header("Location: " . DASH_SUPERVISOR);
        }
      } elseif ($user['perfil'] === 'admin') {
        header("Location: " . DASH); // Dashboard do admin
      } else {
        // Para usuários comuns, redireciona para o dashboard padrão
        header("Location: " . DASH);
      }
      exit;
    } else {
      echo '<script>alert("Senha incorreta."); window.location.href = "' . LOGIN . '";</script>';
    }
  } else {
    echo '<script>alert("Usuário não encontrado."); window.location.href = "' . SAIR . '";</script>';
  }

  $stmt->close();
  $conn->close();
}
