<?php
include_once '../../db/db.php';
include '../../absoluto.php';
include(HEADER_FILE);

// Conexão com o banco de dados
$conn = getDatabaseConnection();

// Processar o envio do formulário (registro global)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $meta_diaria  = intval($_POST['meta_diaria']);
  $meta_semanal = intval($_POST['meta_semanal']);
  $meta_mensal  = intval($_POST['meta_mensal']);

  // Verifica se já existe um registro na tabela de metas globais
  $sqlCheck = "SELECT id FROM metas_global_credesh LIMIT 1";
  $resultCheck = $conn->query($sqlCheck);
  if ($resultCheck->num_rows > 0) {
    // Atualiza o registro existente
    $sqlUpdate = "UPDATE metas_global_credesh SET meta_diaria = $meta_diaria, meta_semanal = $meta_semanal, meta_mensal = $meta_mensal";
    $conn->query($sqlUpdate);
  } else {
    // Insere um novo registro
    $sqlInsert = "INSERT INTO metas_global_credesh (meta_diaria, meta_semanal, meta_mensal) VALUES ($meta_diaria, $meta_semanal, $meta_mensal)";
    $conn->query($sqlInsert);
  }
  // Redireciona para evitar reenvio do formulário
  header("Location: metas_backoffice.php?success=1");
  exit;
}

// Buscar o registro de metas globais (se existir)
$sqlMetaFetch = "SELECT meta_diaria, meta_semanal, meta_mensal FROM metas_global_credesh LIMIT 1";
$resultMetaFetch = $conn->query($sqlMetaFetch);
if ($resultMetaFetch->num_rows > 0) {
  $globalMeta = $resultMetaFetch->fetch_assoc();
} else {
  $globalMeta = ['meta_diaria' => '', 'meta_semanal' => '', 'meta_mensal' => ''];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Metas Globais do Setor</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
  <div class="container" style="margin-top:80px;">
    <h1>Definir Metas Globais do Setor</h1>
    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success">Metas atualizadas com sucesso!</div>
    <?php endif; ?>
    <form method="POST" action="metas_backoffice.php">
      <div class="mb-3">
        <label for="meta_diaria" class="form-label">Meta Diária</label>
        <input type="number" id="meta_diaria" name="meta_diaria" class="form-control" value="<?php echo $globalMeta['meta_diaria']; ?>" required>
      </div>
      <div class="mb-3">
        <label for="meta_semanal" class="form-label">Meta Semanal</label>
        <input type="number" id="meta_semanal" name="meta_semanal" class="form-control" value="<?php echo $globalMeta['meta_semanal']; ?>" required>
      </div>
      <div class="mb-3">
        <label for="meta_mensal" class="form-label">Meta Mensal</label>
        <input type="number" id="meta_mensal" name="meta_mensal" class="form-control" value="<?php echo $globalMeta['meta_mensal']; ?>" required>
      </div>
      <button type="submit" class="btn btn-primary">Salvar Metas</button>
    </form>
  </div>

  <!-- Bootstrap JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include(FOOTER_FILE); ?>

</html>