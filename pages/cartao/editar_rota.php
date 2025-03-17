<?php
session_start();
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';

// Verifica se é POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $rotaId = intval($_POST['rota_id'] ?? 0);
  $rotaOrdem = intval($_POST['rota_ordem'] ?? 0);
  $endereco = trim($_POST['cliente_endereco'] ?? '');
  $numero = trim($_POST['cliente_numero'] ?? '');
  $referencia = trim($_POST['cliente_referencia'] ?? '');

  // Conecta ao banco
  $conn = new mysqli($host, $db_username, $db_password, $db_name);
  if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
  }

  $sql = "UPDATE vendas_cartao_dia
            SET cliente_endereco = ?, cliente_numero = ?, cliente_referencia = ?, rota_ordem = ?
            WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("sssii", $endereco, $numero, $referencia, $rotaOrdem, $rotaId);
  $stmt->execute();
  $stmt->close();
  $conn->close();
}

// Redireciona de volta ao dashboard
header("Location: dashboard_cartao.php");
exit;
