<?php
session_start();
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
include(HEADER_FILE);

// Verifica permissão (exemplo: supervisores do setor Energia)
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'supervisor' || ($_SESSION['setor'] ?? '') !== 'Energia') {
  die("Acesso negado.");
}

// Conexão
$conn = new mysqli($host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
  die("Falha na conexão: " . $conn->connect_error);
}

// Define período atual
$periodo = date('Y-m'); // ex: "2025-03"

// Variáveis de feedback
$msg  = "";
$erro = "";

/* 
   Carrega lista de agentes para exibir no <select>
   Ajuste a query se precisar filtrar somente agentes ativos
*/
$sqlAgentes = "SELECT id, nome FROM agentes_energia ORDER BY nome";
$resultA = $conn->query($sqlAgentes);
$listaAgentes = [];
if ($resultA) {
  while ($rowA = $resultA->fetch_assoc()) {
    $listaAgentes[] = $rowA;
  }
}

/* 
   Carrega lista de departamentos (Tele, Rede, Lojas) 
   (Pode ser fixo, pois é um ENUM, mas se tiver tabela separada, busque)
*/
$listaDepartamentos = ['Tele', 'Rede', 'Lojas'];

// ------------------------------------------------------------------
// 1. Tratamento das ações (CRUD) - Metas dos Agentes
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // A) Criar nova meta de agente
  if (isset($_POST['action']) && $_POST['action'] === 'add_agent_save') {
    $agente_id = intval($_POST['agente_id'] ?? 0);
    $meta_mes  = floatval($_POST['meta_mes'] ?? 0);
    $meta_dia  = floatval($_POST['meta_dia'] ?? 0);
    $periodo   = $_POST['periodo'] ?? date('Y-m');

    if ($agente_id > 0 && $meta_mes > 0 && $meta_dia > 0) {
      $stmt = $conn->prepare("
                INSERT INTO metas_agentes_energia (agente_id, meta_mes, meta_dia, periodo)
                VALUES (?, ?, ?, ?)
            ");
      $stmt->bind_param("idds", $agente_id, $meta_mes, $meta_dia, $periodo);
      if ($stmt->execute()) {
        $msg = "Meta de agente criada com sucesso!";
      } else {
        $erro = "Erro ao criar meta de agente: " . $stmt->error;
      }
      $stmt->close();
    } else {
      $erro = "Preencha todos os campos corretamente (metas > 0).";
    }
  }

  // B) Editar meta de agente
  if (isset($_POST['action']) && $_POST['action'] === 'edit_agent_save') {
    $id        = intval($_POST['id'] ?? 0);
    $agente_id = intval($_POST['agente_id'] ?? 0);
    $meta_mes  = floatval($_POST['meta_mes'] ?? 0);
    $meta_dia  = floatval($_POST['meta_dia'] ?? 0);

    if ($id > 0 && $agente_id > 0 && $meta_mes > 0 && $meta_dia > 0) {
      $stmt = $conn->prepare("
                UPDATE metas_agentes_energia
                SET agente_id = ?, meta_mes = ?, meta_dia = ?
                WHERE id = ?
            ");
      $stmt->bind_param("iddi", $agente_id, $meta_mes, $meta_dia, $id);
      if ($stmt->execute()) {
        $msg = "Meta de agente #$id atualizada com sucesso!";
      } else {
        $erro = "Erro ao atualizar meta de agente: " . $stmt->error;
      }
      $stmt->close();
    } else {
      $erro = "Dados inválidos para edição.";
    }
  }
}

// C) Excluir meta de agente (GET)
if (isset($_GET['action']) && $_GET['action'] === 'delete_agent' && !empty($_GET['id'])) {
  $id = intval($_GET['id']);
  $stmt = $conn->prepare("DELETE FROM metas_agentes_energia WHERE id = ?");
  $stmt->bind_param("i", $id);
  if ($stmt->execute()) {
    $msg = "Meta de agente #$id excluída com sucesso!";
  } else {
    $erro = "Erro ao excluir meta de agente: " . $stmt->error;
  }
  $stmt->close();
}

// ------------------------------------------------------------------
// 2. Tratamento das ações (CRUD) - Metas dos Departamentos
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // A) Criar nova meta de departamento
  if (isset($_POST['action']) && $_POST['action'] === 'add_dept_save') {
    $departamento = $_POST['departamento'] ?? '';
    $meta_mes     = floatval($_POST['meta_mes'] ?? 0);
    $meta_dia     = floatval($_POST['meta_dia'] ?? 0);
    $periodo      = $_POST['periodo'] ?? date('Y-m');

    if (in_array($departamento, $listaDepartamentos) && $meta_mes > 0 && $meta_dia > 0) {
      $stmt = $conn->prepare("
                INSERT INTO metas_energia (departamento, meta_mes, meta_dia, periodo)
                VALUES (?, ?, ?, ?)
            ");
      $stmt->bind_param("sdds", $departamento, $meta_mes, $meta_dia, $periodo);
      if ($stmt->execute()) {
        $msg = "Meta do departamento '$departamento' criada com sucesso!";
      } else {
        $erro = "Erro ao criar meta de departamento: " . $stmt->error;
      }
      $stmt->close();
    } else {
      $erro = "Preencha todos os campos corretamente (metas > 0, departamento válido).";
    }
  }

  // B) Editar meta de departamento
  if (isset($_POST['action']) && $_POST['action'] === 'edit_dept_save') {
    $id         = intval($_POST['id'] ?? 0);
    $meta_mes   = floatval($_POST['meta_mes'] ?? 0);
    $meta_dia   = floatval($_POST['meta_dia'] ?? 0);

    if ($id > 0 && $meta_mes > 0 && $meta_dia > 0) {
      $stmt = $conn->prepare("
                UPDATE metas_energia
                SET meta_mes = ?, meta_dia = ?
                WHERE id = ?
            ");
      $stmt->bind_param("ddi", $meta_mes, $meta_dia, $id);
      if ($stmt->execute()) {
        $msg = "Meta de departamento #$id atualizada com sucesso!";
      } else {
        $erro = "Erro ao atualizar meta de departamento: " . $stmt->error;
      }
      $stmt->close();
    } else {
      $erro = "Dados inválidos para edição.";
    }
  }
}

// C) Excluir meta de departamento (GET)
if (isset($_GET['action']) && $_GET['action'] === 'delete_dept' && !empty($_GET['id'])) {
  $id = intval($_GET['id']);
  $stmt = $conn->prepare("DELETE FROM metas_energia WHERE id = ?");
  $stmt->bind_param("i", $id);
  if ($stmt->execute()) {
    $msg = "Meta de departamento #$id excluída com sucesso!";
  } else {
    $erro = "Erro ao excluir meta de departamento: " . $stmt->error;
  }
  $stmt->close();
}

// ------------------------------------------------------------------
// 3. Se a ação for 'edit_agent' ou 'edit_dept', pegamos dados para exibir no form
// ------------------------------------------------------------------
$editAgent = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit_agent' && !empty($_GET['id'])) {
  $id = intval($_GET['id']);
  $stmt = $conn->prepare("
        SELECT m.id, m.agente_id, m.meta_mes, m.meta_dia, m.periodo, a.nome AS agente
        FROM metas_agentes_energia m
        JOIN agentes_energia a ON m.agente_id = a.id
        WHERE m.id = ?
    ");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($res) {
    $editAgent = $res;
  }
}

$editDept = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit_dept' && !empty($_GET['id'])) {
  $id = intval($_GET['id']);
  $stmt = $conn->prepare("
        SELECT id, departamento, meta_mes, meta_dia, periodo
        FROM metas_energia
        WHERE id = ?
    ");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($res) {
    $editDept = $res;
  }
}

// ------------------------------------------------------------------
// 4. Lista metas de agentes e departamentos para exibir
// ------------------------------------------------------------------
$periodoAtual = date('Y-m'); // ou use $periodo se quiser sempre o mesmo

// Metas dos Agentes
$stmt = $conn->prepare("
    SELECT m.id, m.agente_id, m.meta_mes, m.meta_dia, m.periodo, a.nome AS agente
    FROM metas_agentes_energia m
    JOIN agentes_energia a ON m.agente_id = a.id
    WHERE m.periodo = ?
");
$stmt->bind_param("s", $periodoAtual);
$stmt->execute();
$result = $stmt->get_result();
$metasAgentes = [];
while ($row = $result->fetch_assoc()) {
  $metasAgentes[] = $row;
}
$stmt->close();

// Metas dos Departamentos
$stmt = $conn->prepare("
    SELECT id, departamento, meta_mes, meta_dia, periodo
    FROM metas_energia
    WHERE periodo = ?
      AND departamento IN ('Tele','Rede','Lojas')
");
$stmt->bind_param("s", $periodoAtual);
$stmt->execute();
$result = $stmt->get_result();
$metasDepartamentos = [];
while ($row = $result->fetch_assoc()) {
  $metasDepartamentos[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>CRUD Metas - Agentes e Departamentos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f9f9f9;
    }

    .card-custom {
      background: #fff;
      border-radius: 8px;
      padding: 1rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      margin-bottom: 1rem;
    }

    .card-custom h4 {
      margin-bottom: 1rem;
      font-weight: 600;
    }
  </style>
</head>

<body>
  <div class="container mt-4">
    <h2 class="text-center text-primary mb-4">Metas - Agentes e Departamentos (<?= $periodoAtual ?>)</h2>

    <!-- Mensagens de feedback -->
    <?php if (!empty($msg)): ?>
      <div class="alert alert-success text-center"><?= $msg; ?></div>
    <?php endif; ?>
    <?php if (!empty($erro)): ?>
      <div class="alert alert-danger text-center"><?= $erro; ?></div>
    <?php endif; ?>

    <!-- Formulário para criar nova meta de Agente (somente se não estiver editando) -->
    <?php if (!$editAgent): ?>
      <div class="card-custom">
        <h4>Criar Meta para Agente</h4>
        <form method="POST" class="row g-3">
          <input type="hidden" name="action" value="add_agent_save">
          <!-- Select de Agentes -->
          <div class="col-md-3">
            <label class="form-label">Agente</label>
            <select name="agente_id" class="form-select" required>
              <option value="">-- Selecione o Agente --</option>
              <?php foreach ($listaAgentes as $ag): ?>
                <option value="<?= $ag['id'] ?>"><?= htmlspecialchars($ag['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Meta Mensal (R$)</label>
            <input type="number" step="0.01" name="meta_mes" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Meta Diária (R$)</label>
            <input type="number" step="0.01" name="meta_dia" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Período (YYYY-MM)</label>
            <input type="text" name="periodo" class="form-control" value="<?= $periodoAtual ?>" required>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-primary">Criar Meta (Agente)</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <!-- Formulário de Edição de Agente -->
    <?php if ($editAgent): ?>
      <div class="card-custom mb-4">
        <h4>Editar Meta do Agente #<?= $editAgent['id'] ?> (<?= htmlspecialchars($editAgent['agente']) ?>)</h4>
        <form method="POST">
          <input type="hidden" name="action" value="edit_agent_save">
          <input type="hidden" name="id" value="<?= $editAgent['id'] ?>">

          <div class="row g-3">
            <!-- Selecionar outro agente se quiser permitir reatribuir a meta a outro agente -->
            <div class="col-md-4">
              <label class="form-label">Agente</label>
              <select name="agente_id" class="form-select" required>
                <?php foreach ($listaAgentes as $ag): ?>
                  <option value="<?= $ag['id'] ?>"
                    <?= ($ag['id'] == $editAgent['agente_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ag['nome']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Meta Mensal (R$)</label>
              <input type="number" step="0.01" name="meta_mes" class="form-control"
                value="<?= $editAgent['meta_mes'] ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Meta Diária (R$)</label>
              <input type="number" step="0.01" name="meta_dia" class="form-control"
                value="<?= $editAgent['meta_dia'] ?>" required>
            </div>
          </div>
          <div class="mt-3">
            <button type="submit" class="btn btn-success">Salvar Alterações</button>
            <a href="metas_energia.php" class="btn btn-secondary">Cancelar</a>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <!-- Formulário para criar nova meta de Departamento -->
    <?php if (!$editDept): ?>
      <div class="card-custom">
        <h4>Criar Meta para Departamento</h4>
        <form method="POST" class="row g-3">
          <input type="hidden" name="action" value="add_dept_save">
          <div class="col-md-3">
            <label class="form-label">Departamento</label>
            <select name="departamento" class="form-select" required>
              <?php foreach ($listaDepartamentos as $dept): ?>
                <option value="<?= $dept ?>"><?= $dept ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Meta Mensal (R$)</label>
            <input type="number" step="0.01" name="meta_mes" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Meta Diária (R$)</label>
            <input type="number" step="0.01" name="meta_dia" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Período (YYYY-MM)</label>
            <input type="text" name="periodo" class="form-control" value="<?= $periodoAtual ?>" required>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-primary">Criar Meta (Departamento)</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <!-- Formulário de Edição de Departamento -->
    <?php if ($editDept): ?>
      <div class="card-custom mb-4">
        <h4>Editar Meta do Departamento #<?= $editDept['id'] ?> (<?= htmlspecialchars($editDept['departamento']) ?>)</h4>
        <form method="POST">
          <input type="hidden" name="action" value="edit_dept_save">
          <input type="hidden" name="id" value="<?= $editDept['id'] ?>">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Meta Mensal (R$)</label>
              <input type="number" step="0.01" name="meta_mes" class="form-control"
                value="<?= $editDept['meta_mes'] ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Meta Diária (R$)</label>
              <input type="number" step="0.01" name="meta_dia" class="form-control"
                value="<?= $editDept['meta_dia'] ?>" required>
            </div>
          </div>
          <div class="mt-3">
            <button type="submit" class="btn btn-success">Salvar Alterações</button>
            <a href="metas_energia.php" class="btn btn-secondary">Cancelar</a>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <!-- Listagem de Metas dos Agentes -->
    <div class="card-custom">
      <h4>Metas dos Agentes (Período: <?= $periodoAtual ?>)</h4>
      <?php if (count($metasAgentes) > 0): ?>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>ID</th>
              <th>Agente</th>
              <th>Meta Mensal (R$)</th>
              <th>Meta Diária (R$)</th>
              <th>Período</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($metasAgentes as $ma): ?>
              <tr>
                <td><?= $ma['id'] ?></td>
                <td><?= htmlspecialchars($ma['agente']) ?></td>
                <td>R$ <?= number_format($ma['meta_mes'], 2, ',', '.') ?></td>
                <td>R$ <?= number_format($ma['meta_dia'], 2, ',', '.') ?></td>
                <td><?= $ma['periodo'] ?></td>
                <td>
                  <a href="?action=edit_agent&id=<?= $ma['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                  <a href="?action=delete_agent&id=<?= $ma['id'] ?>"
                    class="btn btn-sm btn-danger"
                    onclick="return confirm('Deseja excluir a meta #<?= $ma['id'] ?>?');">
                    Excluir
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-muted">Nenhuma meta de agente encontrada para o período <?= $periodoAtual ?>.</p>
      <?php endif; ?>
    </div>

    <!-- Listagem de Metas dos Departamentos -->
    <div class="card-custom">
      <h4>Metas dos Departamentos (Tele, Rede, Lojas) - Período: <?= $periodoAtual ?></h4>
      <?php if (count($metasDepartamentos) > 0): ?>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>ID</th>
              <th>Departamento</th>
              <th>Meta Mensal (R$)</th>
              <th>Meta Diária (R$)</th>
              <th>Período</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($metasDepartamentos as $md): ?>
              <tr>
                <td><?= $md['id'] ?></td>
                <td><?= htmlspecialchars($md['departamento']) ?></td>
                <td>R$ <?= number_format($md['meta_mes'], 2, ',', '.') ?></td>
                <td>R$ <?= number_format($md['meta_dia'], 2, ',', '.') ?></td>
                <td><?= $md['periodo'] ?></td>
                <td>
                  <a href="?action=edit_dept&id=<?= $md['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                  <a href="?action=delete_dept&id=<?= $md['id'] ?>"
                    class="btn btn-sm btn-danger"
                    onclick="return confirm('Deseja excluir a meta #<?= $md['id'] ?>?');">
                    Excluir
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-muted">Nenhuma meta de departamento encontrada para o período <?= $periodoAtual ?>.</p>
      <?php endif; ?>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <?php include(FOOTER_FILE); ?>
</body>

</html>