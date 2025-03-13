<?php

/**
 * absoluto.php
 * 
 * Arquivo de configuração com constantes para caminhos absolutos
 * e URLs do projeto. Garante que as constantes não sejam
 * redefinidas se este arquivo for incluído mais de uma vez.
 */

// Garante que as constantes só sejam definidas se ainda não existirem
if (!defined('BASE_PATH')) {
  // Caminho físico (no servidor) da raiz do projeto
  define('BASE_PATH', __DIR__);
}

if (!defined('BASE_URL')) {
  // URL base do seu projeto
  define('BASE_URL', 'http://localhost/tony/');
}

if (!defined('ASSET_PATH')) {
  // Caminho para a pasta de imagens, CSS, etc. (URL para o navegador)
  define('ASSET_PATH', BASE_URL . 'assets/img');
}

// Se você tiver um header e footer em arquivos separados, pode definir:
if (!defined('HEADER_FILE')) {
  define('HEADER_FILE', BASE_PATH . '/includes/header.php');
}
if (!defined('FOOTER_FILE')) {
  define('FOOTER_FILE', BASE_PATH . '/includes/footer.php');
}

// Caminho (URL) para a página de logout
if (!defined('SAIR')) {
  define('SAIR', BASE_URL . 'pages/logout.php');
}
if (!defined('LOGIN')) {
  define('LOGIN', BASE_URL . 'pages/login.php');
}
if (!defined('DASH')) {
  define('DASH', BASE_URL . 'pages/dashboards.php');
}
