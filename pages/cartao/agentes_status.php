<?php
session_start();
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';

// Verifique se o usuário tem permissão (por exemplo, admin ou supervisor do Cartão)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['perfil'] ?? '', ['admin', 'supervisor']) || ($_SESSION['setor'] ?? '') !== 'Cartão') {
  die("Acesso negado.");
}

if (isset($_GET['acao']) && isset($_GET['id'])) {
  $id = (int) $_GET['id'];
  $acao = $_GET['acao'];

  if ($acao === 'desativar') {
    $novoStatus = 0;
  } elseif ($acao === 'ativar') {
    $novoStatus = 1;
  } else {
    die("Ação inválida.");
  }

  $conn = new mysqli($host, $db_username, $db_password, $db_name);
  if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
  }

  $sql = "UPDATE agentes_cartao SET ativo = ? WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $novoStatus, $id);
  $stmt->execute();
  $stmt->close();
  $conn->close();

  header("Location: agentes_cadastrar.php");
  exit;
} else {
  die("Parâmetros inválidos.");
}
