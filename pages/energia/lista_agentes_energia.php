<?php
session_start();
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config1.php';
include(HEADER_FILE);

// Verifica se o usuário logado é supervisor do setor Energia
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'supervisor' || ($_SESSION['setor'] ?? '') !== 'Energia') {
  die("Acesso negado. Apenas supervisores do setor Energia podem acessar esta página.");
}

// Conecta ao banco de dados
$conn = new mysqli($host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
  die("Falha na conexão: " . $conn->connect_error);
}

// Consulta os agentes do setor Energia
$sql = "SELECT id, nome, ativo, created_at FROM agentes_energia ORDER BY nome ASC";
$result = $conn->query($sql);
$agentes = [];
if ($result) {
  while ($row = $result->fetch_assoc()) {
    $agentes[] = $row;
  }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>Lista de Agentes - Energia</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f5f5f5;
    }

    .container {
      margin-top: 2rem;
    }

    .card {
      padding: 1.5rem;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      background: #fff;
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="card">
      <h2 class="text-center mb-4">Lista de Agentes - Energia</h2>
      <?php if (count($agentes) > 0): ?>
        <div class="table-responsive">
          <table class="table table-striped table-bordered">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Status</th>
                <th>Data de Cadastro</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($agentes as $agente): ?>
                <tr>
                  <td><?= htmlspecialchars($agente['id']) ?></td>
                  <td><?= htmlspecialchars($agente['nome']) ?></td>
                  <td><?= $agente['ativo'] ? 'Ativo' : 'Inativo' ?></td>
                  <td><?= date("d/m/Y", strtotime($agente['created_at'])) ?></td>
                  <td>
                    <a href="editar_agente_energia.php?id=<?= $agente['id'] ?>" class="btn btn-sm btn-secondary">Editar</a>
                    <?php if ($agente['ativo']): ?>
                      <a href="desativar_agente_energia.php?id=<?= $agente['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja desativar este agente?');">Desativar</a>
                    <?php else: ?>
                      <a href="ativar_agente_energia.php?id=<?= $agente['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Deseja ativar este agente?');">Ativar</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-center">Nenhum agente encontrado.</p>
      <?php endif; ?>
      <div class="mt-3 d-flex justify-content-between">
        <a href="cadastrar_agente_energia.php" class="btn btn-primary">Cadastrar Novo Agente</a>
        <a href="dashboard_energia.php" class="btn btn-outline-secondary">Voltar ao Dashboard</a>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include(FOOTER_FILE); ?>

</html>