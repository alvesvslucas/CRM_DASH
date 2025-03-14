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
// Caminho para a criação de perfil de usuários
if (!defined('CADASTRO_PERFIL')) {
  define('CADASTRO_PERFIL', BASE_URL . 'pages/perfil/usuarios_cadastrar.php');
}
if (!defined('CADASTRO_LISTAR')) {
  define('CADASTRO_LISTAR', BASE_URL . 'pages/perfil/usuarios_listar.php');
}
// Caminho para o dashboard do setor Cartão
if (!defined('DASH_CARTAO')) {
  define('DASH_CARTAO', BASE_URL . 'pages/cartao/dashboard_cartao.php');
}
if (!defined('DIGITACAO_CARTAO')) {
  define('DIGITACAO_CARTAO', BASE_URL . 'pages/cartao/digitacao_cartao.php');
}
if (!defined('AGENTES_CARTAO')) {
  define('AGENTES_CARTAO', BASE_URL . 'pages/cartao/agentes_cadastrar.php');
}
