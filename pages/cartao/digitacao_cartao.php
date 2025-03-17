<?php
session_start();
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
include(HEADER_FILE);

// Verifica se o usuário logado é supervisor do setor Cartão
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'supervisor' || ($_SESSION['setor'] ?? '') !== 'Cartão') {
  die("Acesso negado. Apenas supervisores do setor Cartão podem acessar esta página.");
}

// Consulta os agentes ativos
$agentConn = new mysqli($host, $db_username, $db_password, $db_name);
if ($agentConn->connect_error) {
  die("Erro ao conectar (agentes): " . $agentConn->connect_error);
}
$sqlAgent = "SELECT id, nome FROM agentes_cartao WHERE ativo = 1 ORDER BY nome ASC";
$resultAgent = $agentConn->query($sqlAgent);
$agentes = [];
while ($row = $resultAgent->fetch_assoc()) {
  $agentes[] = $row;
}
$agentConn->close();

// Processa o formulário enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $formType = $_POST['form_type'] ?? '';
  $conn = new mysqli($host, $db_username, $db_password, $db_name);
  if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
  }

  if ($formType === 'dia') {
    // Captura os campos
    $agente_id_dia      = intval($_POST['agente_id_dia'] ?? 0);
    $cliente_nome       = trim($_POST['cliente_nome'] ?? '');
    $cliente_endereco   = trim($_POST['cliente_endereco'] ?? '');
    $cliente_numero     = trim($_POST['cliente_numero'] ?? '');
    $cliente_referencia = trim($_POST['cliente_referencia'] ?? '');
    $valor_pendente     = floatval($_POST['valor_pendente'] ?? 0); // O valor digitado vai para valor_pendente
    $valor_recebido     = floatval($_POST['valor_recebido'] ?? 0);
    $parcelas           = intval($_POST['parcelas'] ?? 1);
    $fonte              = trim($_POST['fonte'] ?? '');
    $turno              = trim($_POST['turno'] ?? '');
    $pagamento          = trim($_POST['pagamento'] ?? '');
    $horario            = trim($_POST['horario'] ?? '');  // Novo campo para horário
    $supervisor_id      = $_SESSION['user_id'];

    // Ao digitar, insere: vendas_total = 0, valor_passar = 0 e valor_pendente recebe o valor digitado
    $sql = "INSERT INTO vendas_cartao_dia
      (supervisor_id, agente_id, vendas_total, valor_passar, valor_pendente,
       cliente_nome, cliente_endereco, cliente_numero, cliente_referencia,
       valor_recebido, parcelas, fonte, turno, pagamento, horario, data_registro)
      VALUES (?, ?, 0, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      die("Erro na preparação da query: " . $conn->error);
    }
    // Temos 13 placeholders (vendas_total e valor_passar são literais)
    // Ordem dos placeholders:
    // 1) supervisor_id (i)
    // 2) agente_id (i)
    // 3) valor_pendente (d)
    // 4) cliente_nome (s)
    // 5) cliente_endereco (s)
    // 6) cliente_numero (s)
    // 7) cliente_referencia (s)
    // 8) valor_recebido (d)
    // 9) parcelas (i)
    // 10) fonte (s)
    // 11) turno (s)
    // 12) pagamento (s)
    // 13) horario (s)
    // A string de tipos deve ser: "iidssssddisss" (13 caracteres)
    $stmt->bind_param(
      "iidssssdissss",
      $supervisor_id,
      $agente_id_dia,
      $valor_pendente,
      $cliente_nome,
      $cliente_endereco,
      $cliente_numero,
      $cliente_referencia,
      $valor_recebido,
      $parcelas,
      $fonte,
      $turno,
      $pagamento,
      $horario
    );

    if ($stmt->execute()) {
      $mensagem = "Cadastro realizado com sucesso (valor pendente)!";
    } else {
      $erro = "Erro ao inserir dados: " . $stmt->error;
    }
    $stmt->close();
  }
  $conn->close();
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>Digitação Cartão - Valor Pendente + CEP + Horário</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f5f5f5;
    }
  </style>
</head>

<body class="bg-light">
  <div class="container mt-5">
    <h2 class="mb-4 text-center">Digitação</h2>

    <?php if (!empty($mensagem)): ?>
      <div class="alert alert-success"><?php echo $mensagem; ?></div>
    <?php endif; ?>
    <?php if (!empty($erro)): ?>
      <div class="alert alert-danger"><?php echo $erro; ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">Venda por Dia (Cartão) - Valor Pendente</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="form_type" value="dia">

          <!-- Agente -->
          <div class="mb-3">
            <label for="agente_id_dia" class="form-label">Agente</label>
            <select name="agente_id_dia" id="agente_id_dia" class="form-select" required>
              <option value="">-- Selecione --</option>
              <?php foreach ($agentes as $agente): ?>
                <option value="<?php echo $agente['id']; ?>"><?php echo $agente['nome']; ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Valor Pendente -->
          <div class="mb-3">
            <label for="valor_pendente" class="form-label">Valor a Passar (R$)</label>
            <input type="number" step="0.01" name="valor_pendente" id="valor_pendente" class="form-control" required>
          </div>

          <!-- Valor Recebido -->
          <div class="mb-3">
            <label for="valor_recebido" class="form-label">Valor a Recebido (R$)</label>
            <input type="number" step="0.01" name="valor_recebido" id="valor_recebido" class="form-control" required>
          </div>

          <!-- Parcelas -->
          <div class="mb-3">
            <label for="parcelas" class="form-label">Parcelas</label>
            <input type="number" name="parcelas" id="parcelas" class="form-control" min="1" value="1" required>
          </div>

          <!-- Fonte -->
          <div class="mb-3">
            <label for="fonte" class="form-label">Fonte</label>
            <input type="text" name="fonte" id="fonte" class="form-control" placeholder="Ex: Indicação, Redes Sociais, etc.">
          </div>

          <!-- Turno -->
          <div class="mb-3">
            <label for="turno" class="form-label">Turno</label>
            <select name="turno" id="turno" class="form-select">
              <option value="">-- Selecione --</option>
              <option value="Manhã">Manhã</option>
              <option value="Tarde">Tarde</option>
              <option value="Noite">Noite</option>
            </select>
          </div>
          <!-- Horário -->
          <div class="mb-3">
            <label for="horario" class="form-label">Horário</label>
            <input type="text" name="horario" id="horario" class="form-control" placeholder="Ex: 14:00">
          </div>

          <!-- Pagamento -->
          <div class="mb-3">
            <label for="pagamento" class="form-label">Pagamento</label>
            <input type="text" name="pagamento" id="pagamento" class="form-control" placeholder="Ex: Cartão, Boleto, etc.">
          </div>


          <!-- CEP e Endereço -->
          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="cep" class="form-label">CEP</label>
              <input type="text" name="cep" id="cep" class="form-control" placeholder="Digite o CEP" required>
            </div>
            <div class="col-md-8 mb-3">
              <label for="cliente_endereco" class="form-label">Endereço</label>
              <input type="text" name="cliente_endereco" id="cliente_endereco" class="form-control" readonly required>
            </div>
          </div>

          <!-- Número e Referência -->
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="cliente_numero" class="form-label">Número</label>
              <input type="text" name="cliente_numero" id="cliente_numero" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
              <label for="cliente_referencia" class="form-label">Referência</label>
              <input type="text" name="cliente_referencia" id="cliente_referencia" class="form-control">
            </div>
          </div>

          <!-- Nome do Cliente -->
          <div class="mb-3">
            <label for="cliente_nome" class="form-label">Cliente</label>
            <input type="text" name="cliente_nome" id="cliente_nome" class="form-control" required>
          </div>

          <button type="submit" class="btn btn-primary w-100">Salvar (Valor Pendente)</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Script para buscar CEP via ViaCEP -->
  <script>
    document.getElementById('cep').addEventListener('blur', function() {
      var cep = this.value.replace(/\D/g, '');
      if (cep !== "") {
        var validacep = /^[0-9]{8}$/;
        if (validacep.test(cep)) {
          fetch("https://viacep.com.br/ws/" + cep + "/json/")
            .then(function(response) {
              if (!response.ok) {
                throw new Error("Erro na resposta da API");
              }
              return response.json();
            })
            .then(function(data) {
              if (!("erro" in data)) {
                document.getElementById('cliente_endereco').value =
                  data.logradouro + ", " + data.bairro + " - " + data.localidade + "/" + data.uf;
              } else {
                alert("CEP não encontrado.");
                document.getElementById('cliente_endereco').value = "";
              }
            })
            .catch(function(error) {
              console.error("Erro na consulta do CEP:", error);
            });
        } else {
          alert("Formato de CEP inválido.");
        }
      }
    });
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include(FOOTER_FILE); ?>

</html>