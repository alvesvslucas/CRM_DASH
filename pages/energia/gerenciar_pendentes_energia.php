<?php
session_start();
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
include(HEADER_FILE);

// Verifica se o usuário é supervisor do setor Energia
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'supervisor' || ($_SESSION['setor'] ?? '') !== 'Energia') {
  die("Acesso negado.");
}

// Conexão com o banco
$conn = new mysqli($host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
  die("Falha na conexão: " . $conn->connect_error);
}

// ------------------------------------------------------------------
// 1. Verifica se há alguma ação (aprovar, excluir, editar, salvar)
// ------------------------------------------------------------------

// A) Aprovar venda (GET: ?action=approve&id=...)
if (isset($_GET['action']) && $_GET['action'] === 'approve' && !empty($_GET['id'])) {
  $id = intval($_GET['id']);
  // Atualiza status para 'pago'
  $stmt = $conn->prepare("UPDATE vendas_energia SET status = 'pago' WHERE id = ? AND status = 'pendente'");
  $stmt->bind_param("i", $id);
  if ($stmt->execute()) {
    $msg = "Venda #$id aprovada com sucesso!";
  } else {
    $erro = "Erro ao aprovar venda: " . $stmt->error;
  }
  $stmt->close();
}

// B) Excluir venda (GET: ?action=delete&id=...)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
  $id = intval($_GET['id']);
  $stmt = $conn->prepare("DELETE FROM vendas_energia WHERE id = ? AND status = 'pendente'");
  $stmt->bind_param("i", $id);
  if ($stmt->execute()) {
    $msg = "Venda #$id excluída com sucesso!";
  } else {
    $erro = "Erro ao excluir venda: " . $stmt->error;
  }
  $stmt->close();
}

// C) Salvar edição do valor (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_save') {
  $id = intval($_POST['id'] ?? 0);
  $valor_novo = floatval($_POST['valor_venda'] ?? 0);

  if ($id > 0 && $valor_novo > 0) {
    $stmt = $conn->prepare("UPDATE vendas_energia SET valor_venda = ? WHERE id = ? AND status = 'pendente'");
    $stmt->bind_param("di", $valor_novo, $id);
    if ($stmt->execute()) {
      $msg = "Venda #$id atualizada com sucesso!";
    } else {
      $erro = "Erro ao atualizar venda: " . $stmt->error;
    }
    $stmt->close();
  } else {
    $erro = "Dados inválidos para edição.";
  }
}

// ------------------------------------------------------------------
// 2. Se a ação for 'edit', pegamos os dados para exibir no form
// ------------------------------------------------------------------
$editData = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['id'])) {
  $id = intval($_GET['id']);
  // Busca os dados da venda pendente
  $stmt = $conn->prepare("
        SELECT v.id, v.valor_venda, v.status, v.setor, a.nome AS agente
        FROM vendas_energia v
        JOIN agentes_energia a ON v.agente_id = a.id
        WHERE v.id = ? AND v.status = 'pendente'
    ");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($result) {
    $editData = $result;  // guardamos para exibir no formulário
  } else {
    $erro = "Registro não encontrado ou não está pendente.";
  }
}

// ------------------------------------------------------------------
// 3. Consulta todas as vendas pendentes para listar
// ------------------------------------------------------------------
$sql = "
  SELECT v.id, v.valor_venda, v.status, v.data_registro, v.setor, a.nome AS agente
  FROM vendas_energia v
  JOIN agentes_energia a ON v.agente_id = a.id
  WHERE v.status = 'pendente'
  ORDER BY v.id DESC
";
$result = $conn->query($sql);
$pendentes = [];
if ($result) {
  while ($row = $result->fetch_assoc()) {
    $pendentes[] = $row;
  }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>Gerenciar Vendas Pendentes - Energia</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f5f5f5;
    }

    .container {
      margin-top: 2rem;
    }

    .card-custom {
      background: #fff;
      border-radius: 8px;
      padding: 1rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      margin-bottom: 1rem;
    }
  </style>
</head>

<body>
  <div class="container">
    <h2 class="mb-4 text-center text-primary">Gerenciar Vendas Pendentes - Energia</h2>

    <!-- Mensagens de feedback -->
    <?php if (!empty($msg)): ?>
      <div class="alert alert-success text-center"><?php echo $msg; ?></div>
    <?php endif; ?>
    <?php if (!empty($erro)): ?>
      <div class="alert alert-danger text-center"><?php echo $erro; ?></div>
    <?php endif; ?>

    <!-- Se estiver em modo edição, exibimos o form de edição -->
    <?php if ($editData): ?>
      <div class="card-custom mb-4">
        <h4>Editar Venda Pendente #<?php echo $editData['id']; ?></h4>
        <form method="POST">
          <input type="hidden" name="action" value="edit_save">
          <input type="hidden" name="id" value="<?php echo $editData['id']; ?>">
          <div class="mb-3">
            <label class="form-label">Agente</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($editData['agente']); ?>" disabled>
          </div>
          <div class="mb-3">
            <label class="form-label">Valor da Venda (R$)</label>
            <input type="number" step="0.01" name="valor_venda" class="form-control"
              value="<?php echo $editData['valor_venda']; ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Setor</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($editData['setor']); ?>" disabled>
          </div>
          <button type="submit" class="btn btn-primary">Salvar Alterações</button>
          <a href="gerenciar_pendentes_energia.php" class="btn btn-secondary">Cancelar</a>
        </form>
      </div>
    <?php endif; ?>

    <!-- Lista de pendentes -->
    <div class="card-custom">
      <h4>Lista de Vendas Pendentes</h4>
      <?php if (count($pendentes) > 0): ?>
        <table class="table table-bordered table-striped">
          <thead>
            <tr>
              <th>ID</th>
              <th>Agente</th>
              <th>Valor (R$)</th>
              <th>Setor</th>
              <th>Data Registro</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendentes as $p): ?>
              <tr>
                <td><?php echo $p['id']; ?></td>
                <td><?php echo htmlspecialchars($p['agente']); ?></td>
                <td>R$ <?php echo number_format($p['valor_venda'], 2, ',', '.'); ?></td>
                <td><?php echo htmlspecialchars($p['setor']); ?></td>
                <td><?php echo $p['data_registro']; ?></td>
                <td>
                  <!-- Link para Aprovar -->
                  <a href="?action=approve&id=<?php echo $p['id']; ?>"
                    class="btn btn-sm btn-success"
                    onclick="return confirm('Deseja aprovar esta venda (#<?php echo $p['id']; ?>)?');">
                    Aprovar
                  </a>
                  <!-- Link para Editar -->
                  <a href="?action=edit&id=<?php echo $p['id']; ?>"
                    class="btn btn-sm btn-warning">
                    Editar
                  </a>
                  <!-- Link para Excluir -->
                  <a href="?action=delete&id=<?php echo $p['id']; ?>"
                    class="btn btn-sm btn-danger"
                    onclick="return confirm('Deseja excluir esta venda (#<?php echo $p['id']; ?>)?');">
                    Excluir
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-muted">Não há vendas pendentes no momento.</p>
      <?php endif; ?>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>