<?php
session_start();
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';

// Verifica se o usuário logado é supervisor do setor Energia
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'supervisor' || ($_SESSION['setor'] ?? '') !== 'Energia') {
    die("Acesso negado. Apenas supervisores do setor Energia podem acessar esta página.");
}

$agentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($agentId <= 0) {
    die("ID inválido.");
}

$conn = new mysqli($host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$stmt = $conn->prepare("UPDATE agentes_energia SET ativo = 0 WHERE id = ?");
$stmt->bind_param("i", $agentId);
if ($stmt->execute()) {
    header("Location: lista_agentes_energia.php?msg=desativado");
    exit;
} else {
    die("Erro ao desativar agente: " . $stmt->error);
}
$stmt->close();
$conn->close();
?>
