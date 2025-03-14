<?php
// Se a sessão ainda não foi iniciada, inicie-a (se já for incluído em outras páginas, pode remover)
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Certifique-se de que as constantes (DASH_ADMIN, DASH_CARTAO, DASH_ENERGIA, etc.) estejam definidas em absoluto.php
// e que esse arquivo esteja incluído antes deste header.
$perfil   = $_SESSION['perfil']   ?? '';
$setor    = $_SESSION['setor']    ?? '';
$username = $_SESSION['username'] ?? 'Usuário';

// Define o link "Início" com base no perfil e setor
if ($perfil === 'admin') {
  $inicio_link = DASH;
} elseif ($perfil === 'supervisor') {
  if ($setor === 'Cartão') {
    $inicio_link = DASH_CARTAO;
  } elseif ($setor === 'Energia') {
    // $inicio_link = DASH_ENERGIA;
  } elseif ($setor === 'Consignado') {
    // $inicio_link = DASH_CONSIGNADO;
  } elseif ($setor === 'FGTS') {
    // $inicio_link = DASH_FGTS;
  } else {
    // $inicio_link = DASH_SUPERVISOR;
  }
} else {
  $inicio_link = DASH; // Para usuários comuns, por exemplo.
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Estilos adicionais -->
  <style>
    body {
      background-color: #f5f5f5;
    }

    /* Outras classes personalizadas... */
  </style>
</head>

<body>
  <nav class="navbar navbar-expand-lg navbar-light bg-light mb-3">
    <div class="container-fluid">
      <!-- Marca ou título -->
      <a class="navbar-brand" href="#">
        <h1>Cred<span>esh</span></h1>
      </a>
      <!-- Botão responsivo -->
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
        aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <!-- Menu (links) -->
      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <!-- Link de Início sempre disponível, direcionado de acordo com perfil/setor -->
          <li class="nav-item">
            <a class="nav-link active" aria-current="page" href="<?= $inicio_link ?>">Início</a>
          </li>

          <?php if ($perfil === 'admin'): ?>
            <!-- Apenas administradores podem ver esses links -->
            <li class="nav-item">
              <a class="nav-link" href="<?= CADASTRO_PERFIL ?>">Cadastro</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?= CADASTRO_LISTAR ?>">Listar Usuários</a>
            </li>
          <?php endif; ?>

          <?php if ($perfil === 'supervisor' || $setor === 'cartao'): ?>
            <!-- Apenas administradores podem ver esses links -->
            <li class="nav-item">
              <a class="nav-link" href="<?= DASH_CARTAO ?>">Dashboard</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?= AGENTES_CARTAO ?>">Cadastro de Agentes</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?= DIGITACAO_CARTAO ?>">Digitação</a>
            </li>
          <?php endif; ?>
          <!-- Exemplo de link de Relatórios (pode ser diferente conforme o perfil) -->
          <!-- Link para Sair -->
          <li class="nav-item">
            <a class="nav-link" href="<?= SAIR ?>">Sair</a>
          </li>
        </ul>
        <!-- Perfil do usuário -->
        <div class="d-flex align-items-center">
          <!-- Imagem de perfil (ajuste o caminho conforme seu projeto) -->
          <img src="<?php echo ASSET_PATH; ?>/logo.png" alt="Foto de Perfil" class="rounded-circle me-2" style="width: 40px; height: 40px;">
          <!-- Nome do usuário -->
          <span class="fw-bold"><?= htmlspecialchars($username) ?></span>
        </div>
      </div>
    </div>
  </nav>