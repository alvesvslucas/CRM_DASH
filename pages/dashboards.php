<?php
// Inclui o arquivo de configurações e funções
include '../absoluto.php';         // Arquivo com constantes ou funções gerais, se houver
include '../db/dashadmin.php';         // Carrega as variáveis de conexão dos DOIS bancos
include(HEADER_FILE);               // Inclua seu header

// -----------------------------------------------------------------------------
// Consultas no BANCO 1 (credesh)
$usersResult           = $conn1->query("SELECT * FROM users");
$vendasResult          = $conn1->query("SELECT * FROM vendas");
$vendasCartaoDiaResult = $conn1->query("SELECT * FROM vendas_cartao_dia");
$vendasEnergiaResult   = $conn1->query("SELECT * FROM vendas_energia");

// Consultas no BANCO 2 (credinowe_consignado_teste)
$dashboardPresetsResult           = $conn2->query("SELECT * FROM dashboard_presets");
$diasTrabalhoResult               = $conn2->query("SELECT * FROM dias_trabalho");
$metasConsignadoResult            = $conn2->query("SELECT * FROM metas_consignado");
$metasConsultoresConsignadoResult = $conn2->query("SELECT * FROM metas_consultores_consignado");
$metasSetorConsignadoResult       = $conn2->query("SELECT * FROM metas_setor_consignado");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard Administrador - Acesso Completo</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

  <!-- Estilos adicionais -->
  <style>
    body {
      background-color: #f5f5f5;
    }
    .dashboard-section {
      margin-bottom: 30px;
    }
    .card-custom {
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      border: none;
      border-radius: 8px;
      margin-bottom: 20px;
    }
    .chart-container {
      background-color: #fff;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    .table-custom {
      background-color: #fff;
      border-radius: 8px;
      padding: 15px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      margin-bottom: 20px;
    }
    .table-custom table {
      width: 100%;
    }
    .nav-link {
      cursor: pointer;
    }
  </style>
</head>
<body>
  <div class="container-fluid">
    <div class="row">
      <!-- Sidebar de navegação -->
      <div class="col-md-2 p-3">
        <h5>Navegação</h5>
        <hr />
        <ul class="nav flex-column">
          <li class="nav-item"><a class="nav-link" href="#usuarios">Usuários (credesh)</a></li>
          <li class="nav-item"><a class="nav-link" href="#vendas">Vendas (credesh)</a></li>
          <li class="nav-item"><a class="nav-link" href="#vendas_cartao_dia">Vendas Cartão Dia (credesh)</a></li>
          <li class="nav-item"><a class="nav-link" href="#vendas_energia">Vendas Energia (credesh)</a></li>
          <li class="nav-item"><a class="nav-link" href="#dashboard_presets">Dashboard Presets (consignado)</a></li>
          <li class="nav-item"><a class="nav-link" href="#dias_trabalho">Dias de Trabalho (consignado)</a></li>
          <li class="nav-item"><a class="nav-link" href="#metas_consignado">Metas Consignado (consignado)</a></li>
          <li class="nav-item"><a class="nav-link" href="#metas_consultores_consignado">Metas Consultores (consignado)</a></li>
          <li class="nav-item"><a class="nav-link" href="#metas_setor_consignado">Metas Setor (consignado)</a></li>
        </ul>
      </div>

      <!-- Conteúdo principal -->
      <div class="col-md-10 p-4">
        <h2 class="mb-4">Dashboard Administrador - Acesso Completo</h2>

        <!-- Seção Usuários (Banco 1) -->
        <div id="usuarios" class="dashboard-section">
          <h4>Usuários (credesh)</h4>
          <div class="table-custom">
            <table class="table table-striped table-sm">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nome</th>
                  <th>Username</th>
                  <th>Perfil</th>
                  <th>Setor</th>
                  <th>Status</th>
                  <th>Criado em</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($row = $usersResult->fetch_assoc()): ?>
                  <tr>
                    <td><?= $row['id']; ?></td>
                    <td><?= $row['nome']; ?></td>
                    <td><?= $row['username']; ?></td>
                    <td><?= $row['perfil']; ?></td>
                    <td><?= $row['setor']; ?></td>
                    <td><?= ($row['status'] == 1 ? 'Ativo' : 'Inativo'); ?></td>
                    <td><?= $row['created_at']; ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Seção Vendas (Banco 1) -->
        <div id="vendas" class="dashboard-section">
          <h4>Vendas (credesh)</h4>
          <div class="table-custom">
            <table class="table table-striped table-sm">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Data de Registro</th>
                  <th>Modalidade ID</th>
                  <th>Setor</th>
                  <th>Departamento</th>
                  <th>Status</th>
                  <th>Consultor ID</th>
                  <th>Valor</th>
                  <th>Clientes em Rota</th>
                  <th>Atendimentos Finalizados</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($row = $vendasResult->fetch_assoc()): ?>
                  <tr>
                    <td><?= $row['id']; ?></td>
                    <td><?= $row['data_registro']; ?></td>
                    <td><?= $row['modalidade_id']; ?></td>
                    <td><?= $row['setor']; ?></td>
                    <td><?= $row['departamento']; ?></td>
                    <td><?= $row['status']; ?></td>
                    <td><?= $row['consultor_id']; ?></td>
                    <td><?= $row['valor']; ?></td>
                    <td><?= $row['clientes_em_rota']; ?></td>
                    <td><?= $row['atendimentos_finalizados']; ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Seção Vendas Cartão Dia (Banco 1) -->
        <div id="vendas_cartao_dia" class="dashboard-section">
          <h4>Vendas Cartão Dia (credesh)</h4>
          <div class="table-custom">
            <table class="table table-striped table-sm">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Supervisor ID</th>
                  <th>Agente ID</th>
                  <th>Vendas Total</th>
                  <th>Cliente Nome</th>
                  <th>Cliente Endereço</th>
                  <th>Cliente Número</th>
                  <th>Cliente Referência</th>
                  <th>Valor Passar</th>
                  <th>Data de Registro</th>
                  <th>Rota Status</th>
                  <th>Rota Ordem</th>
                  <th>Valor Recebido</th>
                  <th>Parcelas</th>
                  <th>Fonte</th>
                  <th>Turno</th>
                  <th>Pagamento</th>
                  <th>Horário</th>
                  <th>Valor Pendente</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($row = $vendasCartaoDiaResult->fetch_assoc()): ?>
                  <tr>
                    <td><?= $row['id']; ?></td>
                    <td><?= $row['supervisor_id']; ?></td>
                    <td><?= $row['agente_id']; ?></td>
                    <td><?= $row['vendas_total']; ?></td>
                    <td><?= $row['cliente_nome']; ?></td>
                    <td><?= $row['cliente_endereco']; ?></td>
                    <td><?= $row['cliente_numero']; ?></td>
                    <td><?= $row['cliente_referencia']; ?></td>
                    <td><?= $row['valor_passar']; ?></td>
                    <td><?= $row['data_registro']; ?></td>
                    <td><?= $row['rota_status']; ?></td>
                    <td><?= $row['rota_ordem']; ?></td>
                    <td><?= $row['valor_recebido']; ?></td>
                    <td><?= $row['parcelas']; ?></td>
                    <td><?= $row['fonte']; ?></td>
                    <td><?= $row['turno']; ?></td>
                    <td><?= $row['pagamento']; ?></td>
                    <td><?= $row['horario']; ?></td>
                    <td><?= $row['valor_pendente']; ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Seção Vendas Energia (Banco 1) -->
        <div id="vendas_energia" class="dashboard-section">
          <h4>Vendas Energia (credesh)</h4>
          <div class="table-custom">
            <table class="table table-striped table-sm">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Agente ID</th>
                  <th>Valor Venda</th>
                  <th>Quantidade Contratos</th>
                  <th>Status</th>
                  <th>Setor</th>
                  <th>Data de Registro</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($row = $vendasEnergiaResult->fetch_assoc()): ?>
                  <tr>
                    <td><?= $row['id']; ?></td>
                    <td><?= $row['agente_id']; ?></td>
                    <td><?= $row['valor_venda']; ?></td>
                    <td><?= $row['quantidade_contratos']; ?></td>
                    <td><?= $row['status']; ?></td>
                    <td><?= $row['setor']; ?></td>
                    <td><?= $row['data_registro']; ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Seção Dashboard Presets (Banco 2) -->
        <div id="dashboard_presets" class="dashboard-section">
          <h4>Dashboard Presets (consignado)</h4>
          <div class="table-custom">
            <table class="table table-striped table-sm">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Preset Name</th>
                  <th>Consultores JSON</th>
                  <th>Criado em</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($row = $dashboardPresetsResult->fetch_assoc()): ?>
                  <tr>
                    <td><?= $row['id']; ?></td>
                    <td><?= $row['preset_name']; ?></td>
                    <td><?= $row['consultores_json']; ?></td>
                    <td><?= $row['created_at']; ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Seção Dias de Trabalho (Banco 2) -->
        <div id="dias_trabalho" class="dashboard-section">
          <h4>Dias de Trabalho (consignado)</h4>
          <div class="table-custom">
            <table class="table table-striped table-sm">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Mês</th>
                  <th>Total de Dias</th>
                  <th>Criado em</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($row = $diasTrabalhoResult->fetch_assoc()): ?>
                  <tr>
                    <td><?= $row['id']; ?></td>
                    <td><?= $row['mes']; ?></td>
                    <td><?= $row['total_dias']; ?></td>
                    <td><?= $row['created_at']; ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Seção Metas Consignado (Banco 2) -->
        <div id="metas_consignado" class="dashboard-section">
          <h4>Metas Consignado (consignado)</h4>
          <div class="table-custom">
            <table class="table table-striped table-sm">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Dias Trabalhados</th>
                  <th>Data Início</th>
                  <th>Data Fim</th>
                  <th>Criado em</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($row = $metasConsignadoResult->fetch_assoc()): ?>
                  <tr>
                    <td><?= $row['id']; ?></td>
                    <td><?= $row['dias_trabalhados']; ?></td>
                    <td><?= $row['data_inicio']; ?></td>
                    <td><?= $row['data_fim']; ?></td>
                    <td><?= $row['created_at']; ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Seção Metas Consultores Consignado (Banco 2) -->
        <div id="metas_consultores_consignado" class="dashboard-section">
          <h4>Metas Consultores Consignado (consignado)</h4>
          <div class="table-custom">
            <table class="table table-striped table-sm">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Consultor ID</th>
                  <th>Meta Diária</th>
                  <th>Meta Semanal</th>
                  <th>Meta Mensal</th>
                  <th>Período</th>
                  <th>Criado em</th>
                  <th>Atualizado em</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($row = $metasConsultoresConsignadoResult->fetch_assoc()): ?>
                  <tr>
                    <td><?= $row['id']; ?></td>
                    <td><?= $row['consultor_id']; ?></td>
                    <td><?= $row['meta_diaria']; ?></td>
                    <td><?= $row['meta_semanal']; ?></td>
                    <td><?= $row['meta_mensal']; ?></td>
                    <td><?= $row['periodo']; ?></td>
                    <td><?= $row['created_at']; ?></td>
                    <td><?= $row['updated_at']; ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Seção Metas Setor Consignado (Banco 2) -->
        <div id="metas_setor_consignado" class="dashboard-section">
          <h4>Metas Setor Consignado (consignado)</h4>
          <div class="table-custom">
            <table class="table table-striped table-sm">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Meta Diária</th>
                  <th>Meta Semanal</th>
                  <th>Meta Mensal</th>
                  <th>Período</th>
                  <th>Criado em</th>
                  <th>Atualizado em</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($row = $metasSetorConsignadoResult->fetch_assoc()): ?>
                  <tr>
                    <td><?= $row['id']; ?></td>
                    <td><?= $row['meta_diaria']; ?></td>
                    <td><?= $row['meta_semanal']; ?></td>
                    <td><?= $row['meta_mensal']; ?></td>
                    <td><?= $row['periodo']; ?></td>
                    <td><?= $row['created_at']; ?></td>
                    <td><?= $row['updated_at']; ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div> <!-- Fim do conteúdo principal -->
    </div> <!-- Fim da row -->
  </div> <!-- Fim do container-fluid -->

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php
include(FOOTER_FILE); // Inclua o footer conforme sua estrutura

// Fecha as conexões com os dois bancos
$conn1->close();
$conn2->close();
?>
</html>
