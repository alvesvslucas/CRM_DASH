<?php
session_start();
include '../db/config.php';  // arquivo de conexão com o banco
include '../absoluto.php';   // arquivo que retorna o caminho absoluto
// include '(header)';
include HEADER_FILE; // inclui o arquivo de cabeçalho

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
      if ($user['perfil'] === 'admin') {
        header("Location: " . DASH); // Dashboard do admin
      } elseif ($user['perfil'] === 'supervisor') {
        // Redireciona para páginas de supervisor com base no setor
        switch ($user['setor']) {
          case 'Cartão':
            header("Location: " . DASH_CARTAO);
            break;
          case 'Energia':
            header("Location: " . DASH_ENERGIA);
            break;
          case 'Consignado':
            header("Location: " . DASH_CONSIGNADO);
            break;
          case 'Backoffice':
            header("Location: " . DASH_BACKOFFICE);
            break;
          case 'FGTS':
            header("Location: " . DASH_FGTS);
            break;
          default:
            header("Location: " . SAIR);
        }
      } elseif ($user['perfil'] === 'tv_indoor') {
        // Redireciona para páginas da TV Indoor com base no setor
        switch ($user['setor']) {
          case 'Cartão':
            header("Location: " . DASH_TV_CARTAO);
            break;
          case 'Energia':
            header("Location: " . DASH_TV_ENERGIA);
            break;
          case 'Consignado':
            header("Location: " . DASH_TV_CONSIGNADO);
            break;
          case 'Backoffice':
            header("Location: " . DASH_TV_BACKOFFICE);
            break;
          case 'FGTS':
            header("Location: " . DASH_TV_FGTS);
            break;
          default:
            // header("Location: " . DASH_TV_DEFAULT);
        }
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
