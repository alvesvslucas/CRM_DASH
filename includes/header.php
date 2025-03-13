<?php include '../absoluto.php'; ?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - Exemplo</title>

  <!-- Bootstrap CSS -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet" />

  <!-- Estilos adicionais -->
  <style>
    body {
      background-color: #f5f5f5;
    }

    /* Ajuste de espaçamento entre cards e seções */
    .dashboard-section {
      margin-bottom: 30px;
    }

    .card-custom {
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      border: none;
      border-radius: 8px;
      margin-bottom: 20px;
    }

    .card-custom h5 {
      margin: 0;
    }

    .chart-container {
      background-color: #fff;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .filter-section {
      background-color: #e9ecef;
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 20px;
    }

    /* Tabela estilizada */
    .table-custom {
      background-color: #fff;
      border-radius: 8px;
      padding: 15px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .table-custom table {
      width: 100%;
    }

    h1 {
      color: #2c3e50;
    }

    h1 span {
      color: red;
    }
  </style>
</head>

<body>

  <nav class="navbar navbar-expand-lg navbar-light bg-light mb-3">
    <div class="container-fluid">
      <!-- Marca ou título do dashboard -->
      <a class="navbar-brand" href="#">
        <h1>Cred<span>esh</span></h1>
      </a>

      <!-- Botão para menu responsivo no mobile -->
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
        aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <!-- Menu (links) -->
      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <!-- Exemplo de itens de menu -->
          <li class="nav-item">
            <a class="nav-link active" aria-current="page" href="#">Início</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Relatórios</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Configurações</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?= SAIR ?>">Sair</a>

          </li>
        </ul>

        <!-- Perfil do usuário -->
        <div class="d-flex align-items-center">
          <!-- Exemplo de imagem de perfil (ajuste a rota de acordo com seu projeto) -->
          <img src="<?php echo ASSET_PATH; ?>/logo.png" alt="Foto de Perfil"
            class="rounded-circle me-2" style="width: 40px; height: 40px;">
          <!-- Nome do usuário ou ícone -->
          <span class="fw-bold">Usuário</span>
        </div>
      </div>
    </div>
  </nav>