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

// Caminho para a página de login
if (!defined('HOME_ADMIN')) {
  define('HOME_ADMIN', BASE_URL . 'pages/dashboards.php');
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
if (!defined('METAS_CARTAO')) {
  define('METAS_CARTAO', BASE_URL . 'pages/cartao/gerenciar_metas_cartao.php');
}
// Caminho para o dashboard do setor Energia
if (!defined('DASH_ENERGIA')) {
  define('DASH_ENERGIA', BASE_URL . 'pages/energia/dashboard_energia.php');
}
if (!defined('AGENTES_ENERGIA')) {
  define('AGENTES_ENERGIA', BASE_URL . 'pages/energia/agentes_cadastrar.php');
}
if (!defined('DIGITACAO_ENERGIA')) {
  define('DIGITACAO_ENERGIA', BASE_URL . 'pages/energia/digitacao_energia.php');
}
if (!defined('GERENCIAR_ENERGIA')) {
  define('GERENCIAR_ENERGIA', BASE_URL . 'pages/energia/gerenciar_pendentes_energia.php');
}
if (!defined('METAS_ENERGIA')) {
  define('METAS_ENERGIA', BASE_URL . 'pages/energia/metas_energia.php');
}
if (!defined('EXPORT_ENERGIA')) {
  define('EXPORT_ENERGIA', BASE_URL . 'pages/energia/exportar_energia.php');
}
// Caminho para o dashboard do setor FGTS
if (!defined('DASH_FGTS')) {
  define('DASH_FGTS', BASE_URL . 'pages/fgts/dashboard_fgts.php');
}
if (!defined('DIGITACAO_FGTS')) {
  define('DIGITACAO_FGTS', BASE_URL . 'pages/fgts/digitacao_fgts.php');
}
if (!defined('CADASTRO_FGTS')) {
  define('CADASTRO_FGTS', BASE_URL . 'pages/fgts/cadastro_agente.php');
}
if (!defined('LISTA_FGTS')) {
  define('LISTA_FGTS', BASE_URL . 'pages/fgts/listar_metas_fgts.php');
}
if (!defined('EXPORTAR_FGTS')) {
  define('EXPORTAR_FGTS', BASE_URL . 'pages/fgts/exportar_dados_por_status.php');
}
if (!defined('METAS_FGTS')) {
  define('METAS_FGTS', BASE_URL . 'pages/fgts/cadastro_metas.php');
}
// Caminho para o dashboard do setor Consignado
if (!defined('DASH_CONSIGNADO')) {
  define('DASH_CONSIGNADO', BASE_URL . 'pages/consignado/dashboard_consignado.php');
}
if (!defined('DIAS_CONSIGNADO')) {
  define('DIAS_CONSIGNADO', BASE_URL . 'pages/consignado/dias_trabalhados.php');
}
if (!defined('RANK_CONSIGNADO')) {
  define('RANK_CONSIGNADO', BASE_URL . 'pages/consignado/metas_ranking_consignado.php');
}

// Caminho para o dashboard do setor Backoffice
if (!defined('DASH_BACKOFFICE')) {
  define('DASH_BACKOFFICE', BASE_URL . 'pages/backoffice/dashboard_backoffice.php');
}
if (!defined('METAS_BACKOFFICE')) {
  define('METAS_BACKOFFICE', BASE_URL . 'pages/backoffice/metas_backoffice.php');
}
