<?php
// Ajuste o caminho para o seu absoluto.php conforme a estrutura de pastas
require_once __DIR__ . '/../absoluto.php';
session_start();

// Limpa todas as variáveis de sessão
$_SESSION = [];

// Se estiver usando cookies de sessão, apaga o cookie também
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destrói a sessão
session_destroy();

// Redireciona para a página de login, usando a constante BASE_URL
header("Location: " . LOGIN);
exit;
