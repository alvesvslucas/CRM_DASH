<?php
session_start();
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
include(HEADER_FILE);

// Verifica se o usuário logado tem permissão (admin ou supervisor do setor Cartão)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['perfil'] ?? '', ['admin', 'supervisor']) || ($_SESSION['setor'] ?? '') !== 'Cartão') {
    die("Acesso negado. Apenas administradores ou supervisores do setor Cartão podem acessar esta página.");
}

$periodo = date('Y-m'); // Período atual (ex: 2023-03)

// 1. Processa a atualização da meta do setor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_meta_setor'])) {
    $meta_setor_mes = floatval($_POST['meta_setor_mes'] ?? 0);
    $meta_setor_dia = floatval($_POST['meta_setor_dia'] ?? 0);
    
    // Conecta ao banco
    $conn = new mysqli($host, $db_username, $db_password, $db_name);
    if ($conn->connect_error) {
        die("Erro de conexão: " . $conn->connect_error);
    }
    
    // Verifica se já existe uma meta para o setor no período atual
    $stmt = $conn->prepare("SELECT id FROM metas_setor_cartao WHERE periodo = ?");
    $stmt->bind_param("s", $periodo);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        // Atualiza a meta existente
        $stmt->bind_result($meta_setor_id);
        $stmt->fetch();
        $stmt->close();
        $stmt = $conn->prepare("UPDATE metas_setor_cartao SET meta_mes = ?, meta_dia = ? WHERE id = ?");
        $stmt->bind_param("ddi", $meta_setor_mes, $meta_setor_dia, $meta_setor_id);
        if ($stmt->execute()) {
            $mensagemSetor = "Meta do setor atualizada com sucesso!";
        } else {
            $erroSetor = "Erro ao atualizar meta do setor: " . $stmt->error;
        }
    } else {
        $stmt->close();
        // Insere nova meta para o setor
        $stmt = $conn->prepare("INSERT INTO metas_setor_cartao (meta_mes, meta_dia, periodo) VALUES (?, ?, ?)");
        $stmt->bind_param("dds", $meta_setor_mes, $meta_setor_dia, $periodo);
        if ($stmt->execute()) {
            $mensagemSetor = "Meta do setor definida com sucesso!";
        } else {
            $erroSetor = "Erro ao definir meta do setor: " . $stmt->error;
        }
    }
    $stmt->close();
    $conn->close();
}

// 2. Processa a atualização da meta dos agentes (via modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_meta'])) {
    $meta_id  = intval($_POST['meta_id'] ?? 0);
    $meta_mes = floatval($_POST['meta_mes'] ?? 0);
    $meta_dia = floatval($_POST['meta_dia'] ?? 0);
    
    $conn = new mysqli($host, $db_username, $db_password, $db_name);
    if ($conn->connect_error) {
        die("Erro de conexão: " . $conn->connect_error);
    }
    if ($meta_id > 0) {
        $stmt = $conn->prepare("UPDATE metas_cartao SET meta_mes = ?, meta_dia = ? WHERE id = ?");
        $stmt->bind_param("ddi", $meta_mes, $meta_dia, $meta_id);
        if ($stmt->execute()) {
            $mensagemAgente = "Meta do agente atualizada com sucesso!";
        } else {
            $erroAgente = "Erro ao atualizar meta do agente: " . $stmt->error;
        }
        $stmt->close();
    } else {
        // Se não existir meta para o agente, insere nova
        $agente_id = intval($_POST['agente_id'] ?? 0);
        $stmt = $conn->prepare("INSERT INTO metas_cartao (agente_id, meta_mes, meta_dia, periodo) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("idds", $agente_id, $meta_mes, $meta_dia, $periodo);
        if ($stmt->execute()) {
            $mensagemAgente = "Meta do agente definida com sucesso!";
        } else {
            $erroAgente = "Erro ao definir meta do agente: " . $stmt->error;
        }
        $stmt->close();
    }
    $conn->close();
}

// 3. Busca a meta do setor para o período atual
$conn = new mysqli($host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}
$stmt = $conn->prepare("SELECT meta_mes, meta_dia FROM metas_setor_cartao WHERE periodo = ?");
$stmt->bind_param("s", $periodo);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->bind_result($meta_setor_mes_val, $meta_setor_dia_val);
    $stmt->fetch();
} else {
    $meta_setor_mes_val = 0;
    $meta_setor_dia_val = 0;
}
$stmt->close();
$conn->close();

// 4. Busca a lista de agentes do Cartão com as metas individuais
$conn = new mysqli($host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}
$sql = "SELECT a.id AS agente_id, a.nome, m.id AS meta_id, m.meta_mes, m.meta_dia
        FROM agentes_cartao a
        LEFT JOIN metas_cartao m ON a.id = m.agente_id AND m.periodo = ?
        WHERE a.ativo = 1
        ORDER BY a.nome ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $periodo);
$stmt->execute();
$result = $stmt->get_result();
$agentes = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $agentes[] = $row;
    }
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Gerenciar Metas - Cartão</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f5f5f5; }
  </style>
</head>
<body>
  <div class="container mt-5">
    <h2 class="mb-4 text-center">Gerenciar Metas - Cartão</h2>
    
    <!-- Seção de Meta do Setor -->
    <div class="card mb-4">
      <div class="card-header">
        <h5>Meta do Setor - Cartão (Período: <?php echo $periodo; ?>)</h5>
      </div>
      <div class="card-body">
        <?php if (!empty($mensagemSetor)): ?>
          <div class="alert alert-success"><?php echo $mensagemSetor; ?></div>
        <?php endif; ?>
        <?php if (!empty($erroSetor)): ?>
          <div class="alert alert-danger"><?php echo $erroSetor; ?></div>
        <?php endif; ?>
        <form method="POST" action="">
          <input type="hidden" name="update_meta_setor" value="1">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="meta_setor_mes" class="form-label">Meta Mensal do Setor</label>
              <input type="number" step="0.01" name="meta_setor_mes" id="meta_setor_mes" class="form-control" value="<?php echo $meta_setor_mes_val; ?>" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="meta_setor_dia" class="form-label">Meta Diária do Setor</label>
              <input type="number" step="0.01" name="meta_setor_dia" id="meta_setor_dia" class="form-control" value="<?php echo $meta_setor_dia_val; ?>" required>
            </div>
          </div>
          <button type="submit" class="btn btn-primary w-100">Salvar Meta do Setor</button>
        </form>
      </div>
    </div>
    
    <!-- Tabela de Metas dos Agentes -->
    <table class="table table-bordered table-striped">
      <thead>
        <tr>
          <th>Agente</th>
          <th>Meta Mensal</th>
          <th>Meta Diária</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($agentes as $agent): ?>
          <tr>
            <td><?php echo htmlspecialchars($agent['nome']); ?></td>
            <td><?php echo isset($agent['meta_mes']) ? number_format($agent['meta_mes'], 2, ',', '.') : 'N/A'; ?></td>
            <td><?php echo isset($agent['meta_dia']) ? number_format($agent['meta_dia'], 2, ',', '.') : 'N/A'; ?></td>
            <td>
              <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editMetaModal"
                data-meta-id="<?php echo $agent['meta_id'] ?? 0; ?>"
                data-agente="<?php echo htmlspecialchars($agent['nome']); ?>"
                data-meta-mes="<?php echo $agent['meta_mes'] ?? ''; ?>"
                data-meta-dia="<?php echo $agent['meta_dia'] ?? ''; ?>"
                data-agente-id="<?php echo $agent['agente_id']; ?>">
                <?php echo isset($agent['meta_mes']) ? 'Editar Meta' : 'Definir Meta'; ?>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Modal para definir/editar meta dos agentes -->
  <div class="modal fade" id="editMetaModal" tabindex="-1" aria-labelledby="editMetaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" action="">
          <div class="modal-header">
            <h5 class="modal-title" id="editMetaModalLabel">Definir/Editar Meta do Agente</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="meta_id" id="meta_id">
            <!-- Campo oculto para o agente, para inserção caso a meta ainda não exista -->
            <input type="hidden" name="agente_id" id="agente_id_modal">
            <div class="mb-3">
              <label for="agente_nome" class="form-label">Agente</label>
              <input type="text" class="form-control" id="agente_nome" readonly>
            </div>
            <div class="mb-3">
              <label for="meta_mes" class="form-label">Meta Mensal</label>
              <input type="number" step="0.01" class="form-control" name="meta_mes" id="meta_mes" required>
            </div>
            <div class="mb-3">
              <label for="meta_dia" class="form-label">Meta Diária</label>
              <input type="number" step="0.01" class="form-control" name="meta_dia" id="meta_dia" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" name="update_meta" class="btn btn-primary">Salvar Alterações</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Preenche os campos do modal com os dados do agente e meta
    var editMetaModal = document.getElementById('editMetaModal');
    editMetaModal.addEventListener('show.bs.modal', function(event) {
      var button = event.relatedTarget;
      var metaId = button.getAttribute('data-meta-id');
      var agente = button.getAttribute('data-agente');
      var metaMes = button.getAttribute('data-meta-mes');
      var metaDia = button.getAttribute('data-meta-dia');
      var agenteId = button.getAttribute('data-agente-id');
      
      document.getElementById('meta_id').value = metaId;
      document.getElementById('agente_nome').value = agente;
      document.getElementById('meta_mes').value = metaMes;
      document.getElementById('meta_dia').value = metaDia;
      document.getElementById('agente_id_modal').value = agenteId;
    });
  </script>
</body>
</html>
<?php include(FOOTER_FILE); ?>
