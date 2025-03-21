<?php
session_start();

// Ajuste o caminho para seus arquivos de configuração
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
include(HEADER_FILE);

// Verifica se o usuário logado é admin
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'admin') {
  die("Acesso negado. Apenas administradores podem ver esta página.");
}

// Conecta ao banco
$conn = new mysqli($host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
  die("Falha na conexão: " . $conn->connect_error);
}

/* ------------------------------------------------------------------
   1) DESATIVAR OU ATIVAR USUÁRIO
   ------------------------------------------------------------------ */
if (isset($_GET['acao']) && isset($_GET['id'])) {
  $id = (int)$_GET['id'];

  if ($_GET['acao'] === 'desativar') {
    $updateSql = "UPDATE users SET status = 0 WHERE id = ?";
  } elseif ($_GET['acao'] === 'ativar') {
    $updateSql = "UPDATE users SET status = 1 WHERE id = ?";
  }

  if (!empty($updateSql)) {
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
  }
  // Redireciona para limpar a URL
  header("Location: usuarios_listar.php");
  exit;
}

/* ------------------------------------------------------------------
   2) EDITAR USUÁRIO (via POST) - incluindo opção de alterar senha
   ------------------------------------------------------------------ */
if (isset($_POST['editar_usuario'])) {
  // Campos enviados pelo formulário do modal
  $id       = (int)$_POST['edit_id'];
  $nome     = trim($_POST['edit_nome']);
  $username = trim($_POST['edit_username']);
  $perfil   = trim($_POST['edit_perfil']);
  $setor    = trim($_POST['edit_setor']);

  // Nova senha (opcional)
  $novaSenha = trim($_POST['edit_senha'] ?? '');

  // Se a nova senha estiver preenchida, atualiza a coluna 'senha' também
  if (!empty($novaSenha)) {
    $novaSenhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
    $sqlUpdate = "UPDATE users
                      SET nome = ?, username = ?, perfil = ?, setor = ?, senha = ?
                      WHERE id = ?";
    $stmt = $conn->prepare($sqlUpdate);
    $stmt->bind_param("sssssi", $nome, $username, $perfil, $setor, $novaSenhaHash, $id);
  } else {
    // Se não preencheu, mantém a senha anterior
    $sqlUpdate = "UPDATE users
                      SET nome = ?, username = ?, perfil = ?, setor = ?
                      WHERE id = ?";
    $stmt = $conn->prepare($sqlUpdate);
    $stmt->bind_param("ssssi", $nome, $username, $perfil, $setor, $id);
  }

  if ($stmt->execute()) {
    $mensagemSucesso = "Usuário atualizado com sucesso!";
  } else {
    $mensagemErro = "Erro ao atualizar usuário: " . $stmt->error;
  }
  $stmt->close();
}

/* ------------------------------------------------------------------
   3) BUSCAR TODOS OS USUÁRIOS
   ------------------------------------------------------------------ */
$sql = "SELECT id, nome, username, perfil, setor, status FROM users";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>Listar Usuários</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet" />
</head>

<body>
  <div class="container mt-5">
    <h2>Lista de Usuários</h2>

    <!-- Mensagens de sucesso/erro (opcional) -->
    <?php if (!empty($mensagemSucesso)): ?>
      <div class="alert alert-success"><?php echo $mensagemSucesso; ?></div>
    <?php endif; ?>
    <?php if (!empty($mensagemErro)): ?>
      <div class="alert alert-danger"><?php echo $mensagemErro; ?></div>
    <?php endif; ?>

    <table class="table table-bordered table-striped align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nome</th>
          <th>Username</th>
          <th>Perfil</th>
          <th>Setor</th>
          <th>Status</th>
          <th width="220">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($user = $result->fetch_assoc()): ?>
          <tr>
            <td><?php echo $user['id']; ?></td>
            <td><?php echo $user['nome']; ?></td>
            <td><?php echo $user['username']; ?></td>
            <td><?php echo $user['perfil']; ?></td>
            <td><?php echo $user['setor']; ?></td>
            <td>
              <?php echo ($user['status'] == 1)
                ? '<span class="text-success">Ativo</span>'
                : '<span class="text-danger">Inativo</span>'; ?>
            </td>
            <td>
              <!-- Botão Editar (abre modal) -->
              <button
                class="btn btn-warning btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#modalEdit"
                data-id="<?php echo $user['id']; ?>"
                data-nome="<?php echo $user['nome']; ?>"
                data-username="<?php echo $user['username']; ?>"
                data-perfil="<?php echo $user['perfil']; ?>"
                data-setor="<?php echo $user['setor']; ?>">
                Editar
              </button>

              <!-- Botão Desativar ou Ativar -->
              <?php if ($user['status'] == 1): ?>
                <a href="?acao=desativar&id=<?php echo $user['id']; ?>"
                  class="btn btn-danger btn-sm"
                  onclick="return confirm('Tem certeza que deseja DESATIVAR este usuário?');">
                  Desativar
                </a>
              <?php else: ?>
                <a href="?acao=ativar&id=<?php echo $user['id']; ?>"
                  class="btn btn-success btn-sm"
                  onclick="return confirm('Tem certeza que deseja ATIVAR este usuário?');">
                  Ativar
                </a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- MODAL EDITAR USUÁRIO -->
  <div class="modal fade" id="modalEdit" tabindex="-1" aria-labelledby="modalEditLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" action="">
          <div class="modal-header">
            <h5 class="modal-title" id="modalEditLabel">Editar Usuário</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <!-- Campo oculto para o ID -->
            <input type="hidden" name="edit_id" id="edit_id">

            <div class="mb-3">
              <label for="edit_nome" class="form-label">Nome</label>
              <input type="text" name="edit_nome" id="edit_nome" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="edit_username" class="form-label">Username</label>
              <input type="text" name="edit_username" id="edit_username" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="edit_perfil" class="form-label">Perfil</label>
              <select name="edit_perfil" id="edit_perfil" class="form-select">
                <option value="usuario">Usuário (padrão)</option>
                <option value="admin">Administrador</option>
                <option value="supervisor">Supervisor</option>
                <option value="tv_indoor">TV Indoor</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="edit_setor" class="form-label">Setor</label>
              <select name="edit_setor" id="edit_setor" class="form-select">
                <option value="">-- Selecione --</option>
                <option value="Energia">Energia</option>
                <option value="Consignado">Consignado</option>
                <option value="Backoffice">Backoffice</option>
                <option value="FGTS">FGTS</option>
                <option value="Cartão">Cartão</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="edit_senha" class="form-label">Nova Senha (opcional)</label>
              <input type="password" name="edit_senha" id="edit_senha" class="form-control"
                placeholder="Se preenchido, a senha será alterada">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" name="editar_usuario" class="btn btn-primary">Salvar Alterações</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
  </script>

  <script>
    // Ao clicar em "Editar", preenche os campos do modal com os valores do usuário
    var modalEdit = document.getElementById('modalEdit');
    modalEdit.addEventListener('show.bs.modal', function(event) {
      var button = event.relatedTarget; // Botão que acionou o modal

      // Extrai os atributos data-*
      var id = button.getAttribute('data-id');
      var nome = button.getAttribute('data-nome');
      var username = button.getAttribute('data-username');
      var perfil = button.getAttribute('data-perfil');
      var setor = button.getAttribute('data-setor');

      // Preenche os campos do modal
      document.getElementById('edit_id').value = id;
      document.getElementById('edit_nome').value = nome;
      document.getElementById('edit_username').value = username;
      document.getElementById('edit_perfil').value = perfil;
      document.getElementById('edit_setor').value = setor;

      // Limpa o campo de senha, caso tenha sido preenchido antes
      document.getElementById('edit_senha').value = '';
    });
  </script>
</body>

</html>
<?php
$result->free();
$conn->close();
include(FOOTER_FILE);
?>