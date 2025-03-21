<?php
session_start();

require_once '../../db/db.php'; // Deve definir a função getDatabaseConnection()
include '../../absoluto.php';
include(HEADER_FILE);

$conn = getDatabaseConnection();

$msg = "";
$action = isset($_GET['action']) ? $_GET['action'] : '';
$editId = isset($_GET['id']) ? intval($_GET['id']) : 0;

/* ==========================================================
   1) EXCLUIR: Se ação for 'delete'
   ========================================================== */
if ($action === 'delete' && $editId > 0) {
    $stmtDel = $conn->prepare("DELETE FROM dias_trabalho WHERE id = ?");
    $stmtDel->bind_param("i", $editId);
    $stmtDel->execute();
    if ($stmtDel->affected_rows > 0) {
        $msg = "Registro ID $editId excluído com sucesso.";
    } else {
        $msg = "Não foi possível excluir o registro ID $editId.";
    }
    $stmtDel->close();
    header("Location: dias_trabalhados.php?msg=" . urlencode($msg));
    exit;
}

/* ==========================================================
   2) EDITAR: Se ação for 'edit', carregar o registro para edição
   ========================================================== */
$editRow = null;
if ($action === 'edit' && $editId > 0) {
    $stmtE = $conn->prepare("SELECT * FROM dias_trabalho WHERE id = ?");
    $stmtE->bind_param("i", $editId);
    $stmtE->execute();
    $resE = $stmtE->get_result();
    $editRow = $resE->fetch_assoc();
    $stmtE->close();
}

/* ==========================================================
   3) ATUALIZAR: Se o formulário de edição foi submetido
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_config'])) {
    $updId       = intval($_POST['id'] ?? 0);
    $mesAtualiza = $_POST['mes'] ?? date('Y-m');
    // Agora permite editar manualmente o total de dias:
    $totalDiasManual = intval($_POST['total_dias'] ?? 0);
    
    $stmtU = $conn->prepare("UPDATE dias_trabalho SET mes = ?, total_dias = ? WHERE id = ?");
    $stmtU->bind_param("sii", $mesAtualiza, $totalDiasManual, $updId);
    $stmtU->execute();
    if ($stmtU->affected_rows > 0) {
        $msg = "Registro ID $updId atualizado: $totalDiasManual dias de trabalho para $mesAtualiza.";
    } else {
        $msg = "Não foi possível atualizar ou não houve alteração.";
    }
    $stmtU->close();
    header("Location: dias_trabalhados.php?msg=" . urlencode($msg));
    exit;
}

/* ==========================================================
   4) CRIAR: Se o formulário de salvar nova configuração foi submetido
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $selectedMonth = $_POST['mes'] ?? date('Y-m');
    
    list($year, $month) = explode('-', $selectedMonth);
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $workingDays = 0;
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $d);
        $weekday = date('w', strtotime($dateStr));
        if ($weekday != 0) {
            $workingDays++;
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO dias_trabalho (mes, total_dias) VALUES (?, ?)");
    $stmt->bind_param("si", $selectedMonth, $workingDays);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $msg = "Configuração salva para o mês $selectedMonth: $workingDays dias de trabalho.";
    } else {
        $msg = "Erro ao salvar a configuração.";
    }
    $stmt->close();
}

/* ==========================================================
   5) LISTAR REGISTROS: Buscar todas as configurações salvas
   ========================================================== */
$resultList = $conn->query("SELECT * FROM dias_trabalho ORDER BY id DESC");
$listaDias  = $resultList->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Configurar Dias de Trabalho</title>
  <!-- Bootstrap 5 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
     body { background-color: #f8f9fa; }
     .config-card { max-width: 500px; margin: 20px auto; }
  </style>
</head>
<body>
<div class="container my-4">
   <h1 class="text-center mb-4">Configurar Dias de Trabalho</h1>

   <!-- Mensagem -->
   <?php if ($msg): ?>
     <div class="alert alert-info text-center"><?php echo $msg; ?></div>
   <?php endif; ?>

   <!-- Formulário para Inserir NOVA Configuração (aparece se não estiver editando) -->
   <?php if (!$editRow): ?>
   <div class="card config-card">
      <div class="card-body">
         <h5 class="card-title">Nova Configuração</h5>
         <form method="POST" action="">
            <div class="mb-3">
               <label for="mes" class="form-label">Selecione o Mês</label>
               <!-- Input do tipo month -->
               <input type="month" class="form-control" id="mes" name="mes" value="<?php echo date('Y-m'); ?>">
            </div>
            <button type="submit" name="save_config" class="btn btn-primary">Calcular e Salvar Configuração</button>
         </form>
         <hr>
         <p class="small text-muted">
            Essa configuração calcula quantos dias de trabalho (segunda a sábado) existem no mês selecionado.
         </p>
      </div>
   </div>
   <?php endif; ?>

   <!-- Formulário de Edição (aparece se action=edit) -->
   <?php if ($editRow): ?>
   <div class="card config-card">
      <div class="card-body">
         <h5 class="card-title">Editar Configuração (ID: <?php echo $editRow['id']; ?>)</h5>
         <form method="POST" action="">
            <input type="hidden" name="id" value="<?php echo $editRow['id']; ?>">
            <div class="mb-3">
               <label for="mes" class="form-label">Mês</label>
               <input type="month" class="form-control" id="mes" name="mes" value="<?php echo $editRow['mes']; ?>" required>
            </div>
            <!-- Campo editável para total de dias de trabalho -->
            <div class="mb-3">
               <label for="total_dias" class="form-label">Total de Dias de Trabalho</label>
               <input type="number" class="form-control" id="total_dias" name="total_dias" value="<?php echo $editRow['total_dias']; ?>" required>
            </div>
            <button type="submit" name="update_config" class="btn btn-success">Atualizar Configuração</button>
            <a href="dias_trabalhados.php" class="btn btn-secondary">Cancelar</a>
         </form>
      </div>
   </div>
   <?php endif; ?>

   <!-- Lista de Configurações Salvas -->
   <div class="card mt-4">
      <div class="card-header">
         <h5 class="mb-0">Configurações Salvas</h5>
      </div>
      <div class="card-body">
         <?php if (!empty($listaDias)): ?>
         <div class="table-responsive">
         <table class="table table-bordered align-middle">
            <thead class="table-light">
               <tr>
                  <th>ID</th>
                  <th>Mês</th>
                  <th>Total de Dias</th>
                  <th>Criado em</th>
                  <th>Ações</th>
               </tr>
            </thead>
            <tbody>
               <?php foreach ($listaDias as $row): ?>
               <tr>
                  <td><?php echo $row['id']; ?></td>
                  <td><?php echo $row['mes']; ?></td>
                  <td><?php echo $row['total_dias']; ?></td>
                  <td><?php echo $row['created_at']; ?></td>
                  <td>
                     <a href="?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                     <a href="?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir esse registro?');">Excluir</a>
                  </td>
               </tr>
               <?php endforeach; ?>
            </tbody>
         </table>
         </div>
         <?php else: ?>
            <p class="text-center">Nenhuma configuração encontrada.</p>
         <?php endif; ?>
      </div>
   </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
