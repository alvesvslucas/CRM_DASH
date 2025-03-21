<?php
// Iniciar sessão se ainda não estiver iniciada
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Pegando as informações da sessão
$perfil   = $_SESSION['perfil']   ?? '';
$setor    = $_SESSION['setor']    ?? '';
$username = $_SESSION['username'] ?? 'Usuário';

// Definição correta do link de início baseado no perfil e setor
if ($perfil === 'admin') {
  $inicio_link = DASH;
} elseif ($perfil === 'supervisor') {
  switch ($setor) {
    case 'Cartão':
      $inicio_link = DASH_CARTAO;
      break;
    case 'Energia':
      $inicio_link = DASH_ENERGIA;
      break;
    case 'Consignado':
      $inicio_link = DASH_CONSIGNADO;
      break;
    case 'Backoffice':
      $inicio_link = DASH_BACKOFFICE;
      break;
    case 'FGTS':
      $inicio_link = DASH_FGTS;
      break;
    default:
      $inicio_link = SAIR;
  }
} else {
  $inicio_link = DASH;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CREDASH</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body {
      background-color: #f5f5f5;
    }
  </style>
</head>

<body>
  <nav class="navbar navbar-expand-lg navbar-light bg-light mb-3">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">
        <h1>Cred<span>ash</span></h1>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
        aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <!-- Link de Início -->
          <!-- <li class="nav-item">
            <a class="nav-link active" aria-current="page" href="<?= $inicio_link ?>">Início</a>
          </li> -->

          <?php if ($perfil === 'admin'): ?>
            <!-- Apenas administradores -->
            <li class="nav-item"><a class="nav-link" href="<?= HOME_ADMIN ?>">Home</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= CADASTRO_PERFIL ?>">Cadastro</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= CADASTRO_LISTAR ?>">Listar Usuários</a></li>
          <?php endif; ?>

          <?php if ($perfil === 'supervisor' && $setor === 'Cartão'): ?>
            <li class="nav-item"><a class="nav-link" href="<?= DASH_CARTAO ?>">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= AGENTES_CARTAO ?>">Novo Agente</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= DIGITACAO_CARTAO ?>">Digitação</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= METAS_CARTAO ?>">Metas</a></li>
          <?php endif; ?>

          <?php if ($perfil === 'supervisor' && $setor === 'Energia'): ?>
            <li class="nav-item"><a class="nav-link" href="<?= DASH_ENERGIA ?>">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= AGENTES_ENERGIA ?>">Cadastro</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= DIGITACAO_ENERGIA ?>">Digitação</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= GERENCIAR_ENERGIA ?>">Gerenciar Valores</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= METAS_ENERGIA ?>">Metas Time</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= EXPORT_ENERGIA ?>">Exportar</a></li>
          <?php endif; ?>
          <?php if ($perfil === 'supervisor' && $setor === 'FGTS'): ?>
            <li class="nav-item"><a class="nav-link" href="<?= DASH_FGTS ?>">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= CADASTRO_FGTS ?>">Cadastro</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= DIGITACAO_FGTS ?>">Digitação</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= LISTA_FGTS ?>">Gerenciar Digitação</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= EXPORTAR_FGTS ?>">Exportar</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= METAS_FGTS ?>">Metas</a></li>

          <?php endif; ?>
          <?php if ($perfil === 'supervisor' && $setor === 'Consignado'): ?>
            <li class="nav-item"><a class="nav-link" href="<?= DASH_CONSIGNADO ?>">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= DIAS_CONSIGNADO ?>">Dias Trabalhados</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= RANK_CONSIGNADO ?>">Metas e Ranking</a></li>

          <?php endif; ?>
          <?php if ($perfil === 'supervisor' && $setor === 'Backoffice'): ?>
            <li class="nav-item"><a class="nav-link" href="<?= DASH_CONSIGNADO ?>">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= METAS_BACKOFFICE ?>">Metas</a></li>


          <?php endif; ?>



          <li class="nav-item">
            <a class="nav-link" href="<?= SAIR ?>">Sair</a>
          </li>
        </ul>

        <div class="d-flex align-items-center">
          <img src="<?php echo ASSET_PATH; ?>/logo.png" alt="Foto de Perfil" class="rounded-circle me-2" style="width: 40px; height: 40px;">
          <span class="fw-bold"><?= htmlspecialchars($username) ?></span>
        </div>
      </div>
    </div>
  </nav>
</body>

</html>