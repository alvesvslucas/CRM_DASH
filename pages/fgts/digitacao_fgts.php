<?php
// Exemplo de página para digitação de metas FGTS

// Carrega caminhos absolutos e config do banco
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
include(HEADER_FILE);


// Variável para mensagem de retorno (sucesso ou erro)
$mensagem = "";

// Se o formulário for enviado (método POST), processamos os dados
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Captura os valores enviados
  $agente_id = $_POST['agente'] ?? null;
  $valor_str = $_POST['valor'] ?? "";
  $status    = $_POST['status'] ?? "";
  $quantidade = isset($_POST['quantidade']) ? (int) $_POST['quantidade'] : 0;

  // Remove a formatação do campo "valor" (ex.: "R$ 1.234,56" -> "1234.56")
  $valor_str = str_replace("R$ ", "", $valor_str);
  $valor_str = str_replace(".", "", $valor_str);
  $valor_str = str_replace(",", ".", $valor_str);
  $valor = (float) $valor_str;

  // Validação simples (você pode adicionar mais validações conforme necessidade)
  if ($agente_id && $valor > 0 && !empty($status) && $quantidade > 0) {
    // Prepara o INSERT na tabela metas_fgts
    $stmt = $conn->prepare("
            INSERT INTO metas_fgts (agente_id, valor, status, quantidade)
            VALUES (?, ?, ?, ?)
        ");
    $stmt->bind_param("idsi", $agente_id, $valor, $status, $quantidade);

    if ($stmt->execute()) {
      $mensagem = "Registro salvo com sucesso!";
    } else {
      $mensagem = "Erro ao salvar registro: " . $stmt->error;
    }
    $stmt->close();
  } else {
    $mensagem = "Preencha todos os campos corretamente.";
  }
}

// Faz a consulta para listar os agentes cadastrados
$result = $conn->query("SELECT id, nome FROM agentes_fgts ORDER BY nome ASC");
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Página de Digitação - Metas FGTS</title>
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
      line-height: 1.6;
    }

    .container {
      max-width: 600px;
      margin: 40px auto;
      padding: 20px;
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    h1 {
      margin-bottom: 20px;
      text-align: center;
      color: #444;
    }

    form label {
      display: block;
      margin-top: 15px;
      font-weight: bold;
      color: #555;
    }

    form input,
    form select,
    form button {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 1rem;
    }

    form input:focus,
    form select:focus {
      outline: none;
      border-color: #007bff;
    }

    form button {
      margin-top: 20px;
      background: #007bff;
      color: #fff;
      border: none;
      cursor: pointer;
      font-weight: bold;
      transition: background 0.3s ease;
    }

    form button:hover {
      background: #0056b3;
    }

    .mensagem {
      text-align: center;
      margin-bottom: 15px;
      font-weight: bold;
    }

    .mensagem.sucesso {
      color: #2e7d32;
      /* Verde */
    }

    .mensagem.erro {
      color: #c62828;
      /* Vermelho */
    }
  </style>
</head>

<body>
  <div class="container">
    <h1>Registro de Metas Diárias</h1>

    <!-- Exibe mensagem de sucesso ou erro, se houver -->
    <?php if (!empty($mensagem)): ?>
      <p class="mensagem <?php echo (strpos($mensagem, 'sucesso') !== false) ? 'sucesso' : 'erro'; ?>">
        <?php echo $mensagem; ?>
      </p>
    <?php endif; ?>

    <!-- Formulário para Digitação -->
    <form id="formDigitacao" method="POST" action="">
      <!-- Selecionar o Agente -->
      <label for="agente">Selecionar Agente:</label>
      <select id="agente" name="agente" required>
        <option value="">Selecione um agente</option>
        <?php while ($row = $result->fetch_assoc()): ?>
          <option value="<?php echo $row['id']; ?>">
            <?php echo $row['nome']; ?>
          </option>
        <?php endwhile; ?>
      </select>

      <!-- Input do Valor em Real com formatação em JS -->
      <label for="valor">Valor (R$):</label>
      <input type="text" id="valor" name="valor" placeholder="R$ 0,00" autocomplete="off" required>

      <!-- Seleção de Status -->
      <label for="status">Status:</label>
      <select id="status" name="status" required>
        <option value="pago">Pago</option>
        <option value="ag-formalizacao">AG Formalização</option>
        <option value="pendente">Pendente</option>
      </select>

      <!-- Quantidade de Contrato -->
      <label for="quantidade">Quantidade de Contrato:</label>
      <input type="number" id="quantidade" name="quantidade" placeholder="Digite a quantidade" required>

      <button type="submit">Salvar Registro</button>
    </form>
  </div>

  <script>
    // Função para formatar o valor em Real (R$)
    function formatarReal(valor) {
      // Remove todos os caracteres não numéricos
      valor = valor.replace(/\D/g, "");
      // Divide por 100 para obter os centavos e fixa duas casas decimais
      valor = (parseInt(valor, 10) / 100).toFixed(2) + "";
      // Substitui o ponto decimal por vírgula
      valor = valor.replace(".", ",");
      // Adiciona pontos para separar milhares, se necessário
      valor = valor.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
      return "R$ " + valor;
    }

    const valorInput = document.getElementById('valor');

    // Atualiza a formatação do valor durante a digitação
    valorInput.addEventListener('input', function(e) {
      let value = e.target.value;
      // Remove formatações anteriores
      value = value.replace(/[R$\s.]/g, "");
      if (!value) {
        e.target.value = "";
        return;
      }
      e.target.value = formatarReal(value);
    });

    // Exemplo de como vincular a quantidade de contrato ao agente selecionado
    // (Você pode expandir essa lógica conforme a necessidade)
    const agenteSelect = document.getElementById('agente');
    const quantidadeInput = document.getElementById('quantidade');

    agenteSelect.addEventListener('change', function(e) {
      const agenteSelecionado = e.target.value;
      // Aqui você pode implementar uma lógica para buscar metas ou valores do agente
      // e, se necessário, atualizar o campo "quantidade" com base no agente selecionado.
      quantidadeInput.value = "";
    });
  </script>
</body>
<?php include(FOOTER_FILE); ?>

</html>