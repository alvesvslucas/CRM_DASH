<?php
// metas_distribuicao.php

require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
// Se você possui um header padrão, descomente a linha a seguir:
include(HEADER_FILE);

$mensagem = "";

// Função auxiliar para remover formatação de Real e converter para float
function converterValor($valorStr) {
  // Remove "R$", espaços e pontos (separadores de milhar)
  $valorStr = str_replace(['R$', ' ', '.'], '', $valorStr);
  // Substitui a vírgula pelo ponto decimal
  $valorStr = str_replace(',', '.', $valorStr);
  return floatval($valorStr);
}

// Processa os formulários enviados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {
  $formType = $_POST['form_type'];

  if ($formType === 'setor') {
    // Atualiza ou insere as metas do setor
    $meta_diaria = $_POST['meta_diaria'] ?? 0;
    $meta_mensal = $_POST['meta_mensal'] ?? 0;
    $meta_diaria = converterValor($meta_diaria);
    $meta_mensal = converterValor($meta_mensal);

    // Verifica se já existe um registro na tabela metas_setor_fgts
    $resSetor = $conn->query("SELECT id FROM metas_setor_fgts LIMIT 1");
    if ($resSetor->num_rows > 0) {
      // Atualiza o registro existente
      $row = $resSetor->fetch_assoc();
      $id_setor = $row['id'];
      $stmt = $conn->prepare("UPDATE metas_setor_fgts SET meta_diaria = ?, meta_mensal = ? WHERE id = ?");
      $stmt->bind_param("ddi", $meta_diaria, $meta_mensal, $id_setor);
      if ($stmt->execute()) {
        $mensagem = "Metas do setor atualizadas com sucesso!";
      } else {
        $mensagem = "Erro ao atualizar metas do setor: " . $stmt->error;
      }
      $stmt->close();
    } else {
      // Insere um novo registro
      $stmt = $conn->prepare("INSERT INTO metas_setor_fgts (meta_diaria, meta_mensal) VALUES (?, ?)");
      $stmt->bind_param("dd", $meta_diaria, $meta_mensal);
      if ($stmt->execute()) {
        $mensagem = "Metas do setor inseridas com sucesso!";
      } else {
        $mensagem = "Erro ao inserir metas do setor: " . $stmt->error;
      }
      $stmt->close();
    }
  } elseif ($formType === 'agente') {
    // Atualiza ou insere as metas de um agente específico
    $agente_id   = $_POST['agente_id'] ?? 0;
    $meta_diaria = $_POST['meta_diaria'] ?? 0;
    $meta_mensal = $_POST['meta_mensal'] ?? 0;
    $meta_diaria = converterValor($meta_diaria);
    $meta_mensal = converterValor($meta_mensal);

    // Verifica se já existe um registro para o agente
    $stmt = $conn->prepare("SELECT id FROM metas_agentes_fgts WHERE agente_id = ?");
    $stmt->bind_param("i", $agente_id);
    $stmt->execute();
    $resultCheck = $stmt->get_result();
    $stmt->close();

    if ($resultCheck->num_rows > 0) {
      // Atualiza o registro existente
      $row = $resultCheck->fetch_assoc();
      $id_meta = $row['id'];
      $stmt = $conn->prepare("UPDATE metas_agentes_fgts SET meta_diaria = ?, meta_mensal = ? WHERE id = ?");
      $stmt->bind_param("ddi", $meta_diaria, $meta_mensal, $id_meta);
      if ($stmt->execute()) {
        $mensagem = "Metas do agente atualizadas com sucesso!";
      } else {
        $mensagem = "Erro ao atualizar metas do agente: " . $stmt->error;
      }
      $stmt->close();
    } else {
      // Insere um novo registro para o agente
      $stmt = $conn->prepare("INSERT INTO metas_agentes_fgts (agente_id, meta_diaria, meta_mensal) VALUES (?, ?, ?)");
      $stmt->bind_param("idd", $agente_id, $meta_diaria, $meta_mensal);
      if ($stmt->execute()) {
        $mensagem = "Metas do agente inseridas com sucesso!";
      } else {
        $mensagem = "Erro ao inserir metas do agente: " . $stmt->error;
      }
      $stmt->close();
    }
  }
}

// Consulta a meta do setor (se houver)
$resSetor = $conn->query("SELECT meta_diaria, meta_mensal FROM metas_setor_fgts LIMIT 1");
$meta_setor = array('meta_diaria' => '', 'meta_mensal' => '');
if ($resSetor->num_rows > 0) {
  $meta_setor = $resSetor->fetch_assoc();
}

// Consulta os agentes e suas metas (se existirem)
$sql = "SELECT a.id, a.nome, ma.meta_diaria, ma.meta_mensal
        FROM agentes_fgts a
        LEFT JOIN metas_agentes_fgts ma ON a.id = ma.agente_id
        ORDER BY a.nome ASC";
$resultAgents = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Distribuição de Metas - Setor e Agentes</title>
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
      padding: 20px;
    }
    .container {
      max-width: 900px;
      margin: 0 auto;
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }
    h1, h2 {
      text-align: center;
      margin-bottom: 20px;
      color: #444;
    }
    form {
      margin-bottom: 20px;
    }
    label {
      display: block;
      margin-top: 10px;
      font-weight: bold;
    }
    input[type="text"] {
      width: 100%;
      padding: 8px;
      margin-top: 5px;
      border: 1px solid #ccc;
      border-radius: 4px;
    }
    button {
      margin-top: 10px;
      background: #007bff;
      color: #fff;
      border: none;
      padding: 10px 15px;
      border-radius: 4px;
      cursor: pointer;
      transition: background 0.3s ease;
    }
    button:hover {
      background: #0056b3;
    }
    .mensagem {
      text-align: center;
      margin-bottom: 20px;
      font-weight: bold;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
    th, td {
      padding: 10px;
      border: 1px solid #ccc;
      text-align: center;
    }
    th {
      background: #007bff;
      color: #fff;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Distribuição de Metas</h1>
    <?php if (!empty($mensagem)): ?>
      <p class="mensagem"><?php echo $mensagem; ?></p>
    <?php endif; ?>

    <!-- Seção: Metas do Setor -->
    <h2>Metas do Setor</h2>
    <form method="POST" action="">
      <input type="hidden" name="form_type" value="setor">
      <label for="meta_diaria_setor">Meta Diária (Setor):</label>
      <input type="text" id="meta_diaria_setor" name="meta_diaria" class="valor-real" placeholder="Ex: R$ 1.000,00" value="<?php echo ($meta_setor['meta_diaria'] !== '') ? 'R$ ' . number_format($meta_setor['meta_diaria'], 2, ',', '.') : ''; ?>" required>

      <label for="meta_mensal_setor">Meta Mensal (Setor):</label>
      <input type="text" id="meta_mensal_setor" name="meta_mensal" class="valor-real" placeholder="Ex: R$ 30.000,00" value="<?php echo ($meta_setor['meta_mensal'] !== '') ? 'R$ ' . number_format($meta_setor['meta_mensal'], 2, ',', '.') : ''; ?>" required>

      <button type="submit">Salvar Metas do Setor</button>
    </form>

    <!-- Seção: Metas por Agente -->
    <h2>Metas por Agente</h2>
    <table>
      <thead>
        <tr>
          <th>Agente</th>
          <th>Meta Diária</th>
          <th>Meta Mensal</th>
          <th>Ação</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($resultAgents && $resultAgents->num_rows > 0): ?>
          <?php while ($agent = $resultAgents->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($agent['nome']); ?></td>
              <td>
                <form method="POST" action="">
                  <input type="hidden" name="form_type" value="agente">
                  <input type="hidden" name="agente_id" value="<?php echo $agent['id']; ?>">
                  <input type="text" name="meta_diaria" class="valor-real" placeholder="Ex: R$ 100,00" value="<?php echo isset($agent['meta_diaria']) ? 'R$ ' . number_format($agent['meta_diaria'], 2, ',', '.') : ''; ?>" required>
              </td>
              <td>
                  <input type="text" name="meta_mensal" class="valor-real" placeholder="Ex: R$ 3.000,00" value="<?php echo isset($agent['meta_mensal']) ? 'R$ ' . number_format($agent['meta_mensal'], 2, ',', '.') : ''; ?>" required>
              </td>
              <td>
                  <button type="submit">Salvar</button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="4">Nenhum agente encontrado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <script>
    // Função para formatar o valor em Real (R$)
    function formatarReal(valor) {
      // Remove todos os caracteres não numéricos
      valor = valor.replace(/\D/g, "");
      // Converte para número e formata para ter duas casas decimais
      valor = (parseInt(valor, 10) / 100).toFixed(2) + "";
      // Troca ponto decimal por vírgula
      valor = valor.replace(".", ",");
      // Adiciona pontos para separar milhares
      valor = valor.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
      return "R$ " + valor;
    }

    // Aplica a formatação em todos os inputs com a classe "valor-real"
    document.querySelectorAll('.valor-real').forEach(function(input) {
      // Formata ao sair do campo
      input.addEventListener('blur', function(e) {
        let value = e.target.value;
        if (value.trim() === "") return;
        // Remove formatação anterior para evitar duplicidade
        value = value.replace(/[R$\s.]/g, "");
        e.target.value = formatarReal(value);
      });
      // Opcional: ao focar, remove a formatação para facilitar a edição
      input.addEventListener('focus', function(e) {
        let value = e.target.value;
        e.target.value = value.replace("R$ ", "").replace(/\./g, "").replace(",", ".");
      });
    });
  </script>
</body>
</html>
