<?php
session_start();
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';

// Verifica se o ID foi passado
if (!isset($_GET['id'])) {
  die("Parâmetro inválido.");
}
$rotaId = intval($_GET['id']);

// Conecta ao banco
$conn = new mysqli($host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
  die("Falha na conexão: " . $conn->connect_error);
}

// Faz o UPDATE: rota_status = 'concluida', valor_passar = valor_pendente, valor_pendente = 0
$sql = "UPDATE vendas_cartao_dia
        SET rota_status = 'concluida',
            valor_passar = valor_pendente,
            valor_pendente = 0
        WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $rotaId);
$stmt->execute();
$stmt->close();
$conn->close();

// Redireciona de volta ao dashboard
header("Location: dashboard_cartao.php");
exit;
