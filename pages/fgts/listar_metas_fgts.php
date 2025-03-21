<?php
// Carrega configuração do projeto e conexão com o banco
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';

// (Opcional) Inclui o cabeçalho padrão do seu projeto, se existir
if (defined('HEADER_FILE')) {
    include(HEADER_FILE);
}

// Mensagem de retorno (sucesso ou erro)
$mensagem = "";

// Se for POST, significa que o usuário clicou em "Atualizar Status" em alguma linha
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pegamos o ID do registro de metas e o novo status
    $metasId = isset($_POST['metas_id']) ? (int)$_POST['metas_id'] : 0;
    $novoStatus = $_POST['status'] ?? "";

    // Validações simples
    if ($metasId > 0 && !empty($novoStatus)) {
        // Atualiza o status no banco
        $stmt = $conn->prepare("UPDATE metas_fgts SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $novoStatus, $metasId);
        
        if ($stmt->execute()) {
            $mensagem = "Status atualizado com sucesso!";
        } else {
            $mensagem = "Erro ao atualizar status: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $mensagem = "Dados inválidos para atualizar o status.";
    }
}

// Consulta todos os registros de metas, trazendo também o nome do agente
$sql = "
    SELECT m.id, m.valor, m.status, m.quantidade, m.created_at,
           a.nome AS agente_nome
    FROM metas_fgts m
    JOIN agentes_fgts a ON m.agente_id = a.id
    ORDER BY m.created_at DESC
";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Listar Metas FGTS</title>
  <style>
    /* Reset básico */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: Arial, sans-serif;
      background: #f5f5f5;
      color: #333;
    }
    .container {
      max-width: 900px;
      margin: 40px auto;
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    h1 {
      margin-bottom: 20px;
      text-align: center;
      color: #444;
    }
    .mensagem {
      text-align: center;
      margin-bottom: 15px;
      font-weight: bold;
    }
    .mensagem.sucesso {
      color: #2e7d32; /* Verde */
    }
    .mensagem.erro {
      color: #c62828; /* Vermelho */
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
    thead tr {
      background: #007bff;
      color: #fff;
    }
    th, td {
      padding: 10px;
      text-align: left;
      border-bottom: 1px solid #ccc;
    }
    tr:hover {
      background: #f9f9f9;
    }
    form {
      display: inline-block;
      margin: 0; /* Remover margens padrão */
    }
    select {
      padding: 4px;
    }
    button {
      background: #007bff;
      color: #fff;
      border: none;
      padding: 6px 10px;
      border-radius: 4px;
      cursor: pointer;
      margin-left: 5px;
      transition: background 0.3s ease;
    }
    button:hover {
      background: #0056b3;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Lista de Metas FGTS</h1>

    <!-- Exibe mensagem de sucesso ou erro, se houver -->
    <?php if (!empty($mensagem)): ?>
      <p class="mensagem <?php echo (strpos($mensagem, 'sucesso') !== false) ? 'sucesso' : 'erro'; ?>">
        <?php echo $mensagem; ?>
      </p>
    <?php endif; ?>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Agente</th>
          <th>Valor (R$)</th>
          <th>Status</th>
          <th>Quantidade</th>
          <th>Data/Hora</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?php echo $row['id']; ?></td>
              <td><?php echo htmlspecialchars($row['agente_nome']); ?></td>
              <td>
                <?php
                  // Formata o valor no padrão brasileiro
                  echo "R$ " . number_format($row['valor'], 2, ',', '.');
                ?>
              </td>
              <td><?php echo htmlspecialchars($row['status']); ?></td>
              <td><?php echo (int)$row['quantidade']; ?></td>
              <td><?php echo $row['created_at']; ?></td>
              <td>
                <!-- Formulário para atualizar o status deste registro -->
                <form method="POST" action="">
                  <input type="hidden" name="metas_id" value="<?php echo $row['id']; ?>">
                  <select name="status">
                    <option value="pago" <?php if($row['status'] === 'pago') echo 'selected'; ?>>Pago</option>
                    <option value="ag-formalizacao" <?php if($row['status'] === 'ag-formalizacao') echo 'selected'; ?>>AG Formalização</option>
                    <option value="pendente" <?php if($row['status'] === 'pendente') echo 'selected'; ?>>Pendente</option>
                  </select>
                  <button type="submit">Atualizar</button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="7">Nenhum registro encontrado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
