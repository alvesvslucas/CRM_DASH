<?php
session_start();
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
include(HEADER_FILE);

// Verifica se o usuário é supervisor do setor Energia
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'supervisor' || ($_SESSION['setor'] ?? '') !== 'Energia') {
  die("Acesso negado.");
}

// Conexão com o banco de dados
$conn = new mysqli($host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
  die("Falha na conexão: " . $conn->connect_error);
}

// Obtém a lista de agentes ativos (para o setor Energia)
$sqlAgentes = "SELECT id, nome FROM agentes_energia WHERE ativo = 1 ORDER BY nome ASC";
$resultAgentes = $conn->query($sqlAgentes);
$agentes = [];
while ($row = $resultAgentes->fetch_assoc()) {
  $agentes[] = $row;
}

// Variáveis de feedback
$mensagem = "";
$erro     = "";

// Processa o envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {
  $formType = $_POST['form_type'];

  if ($formType === 'venda') {
    // Registro de Venda
    $agente_id           = intval($_POST['agente_id'] ?? 0);
    $valor_venda         = floatval($_POST['valor_venda'] ?? 0);
    // A coluna quantidade_contratos precisa existir na tabela vendas_energia
    $quantidade_contratos = intval($_POST['quantidade_contratos'] ?? 1);
    $setor               = $_POST['setor'] ?? '';

    // Valida os campos (ajuste os valores possíveis conforme seu BD)
    if ($agente_id > 0 && $valor_venda > 0 && $quantidade_contratos > 0 && in_array($setor, ['rede', 'tele', 'loja'])) {
      $stmt = $conn->prepare("
                INSERT INTO vendas_energia (agente_id, valor_venda, quantidade_contratos, status, setor, data_registro)
                VALUES (?, ?, ?, 'pendente', ?, NOW())
            ");
      $stmt->bind_param("idis", $agente_id, $valor_venda, $quantidade_contratos, $setor);

      if ($stmt->execute()) {
        $mensagem = "Venda registrada com sucesso! Aguardando confirmação.";
      } else {
        $erro = "Erro ao registrar venda: " . $stmt->error;
      }
      $stmt->close();
    } else {
      $erro = "Preencha todos os campos corretamente para a venda.";
    }
  } elseif ($formType === 'contrato') {
    // Registro de Contrato Vendido
    $valor_contrato = floatval($_POST['valor_contrato'] ?? 0);
    $setor_contrato = $_POST['setor_contrato'] ?? '';

    if ($valor_contrato > 0 && in_array($setor_contrato, ['rede', 'tele', 'loja'])) {
      $stmt = $conn->prepare("
                INSERT INTO contratos_energia (valor_contrato, setor, data_registro)
                VALUES (?, ?, NOW())
            ");
      $stmt->bind_param("ds", $valor_contrato, $setor_contrato);

      if ($stmt->execute()) {
        $mensagem = "Contrato registrado com sucesso!";
      } else {
        $erro = "Erro ao registrar contrato: " . $stmt->error;
      }
      $stmt->close();
    } else {
      $erro = "Preencha todos os campos corretamente para o contrato.";
    }
  }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>Digitação de Vendas - Energia</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f5f5f5;
    }

    .header-title {
      color: #007bff;
      font-weight: 600;
      margin-bottom: 1rem;
    }

    .card-custom {
      background: #fff;
      border-radius: 8px;
      padding: 1.5rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      margin-bottom: 1rem;
    }

    .card-custom h4 {
      margin-bottom: 1rem;
      font-weight: 600;
    }

    .btn-primary {
      background-color: #007bff;
      border-color: #007bff;
    }

    .btn-primary:hover {
      background-color: #0069d9;
      border-color: #0062cc;
    }

    .btn-secondary {
      background-color: #6c757d;
      border-color: #6c757d;
    }

    .btn-secondary:hover {
      background-color: #5a6268;
      border-color: #545b62;
    }
  </style>
</head>

<body>
  <div class="container mt-5">
    <h2 class="text-center header-title">Digitação de Vendas - Energia</h2>

    <!-- Mensagens de Sucesso/Erro -->
    <?php if (!empty($mensagem)): ?>
      <div class="alert alert-success text-center"><?php echo $mensagem; ?></div>
    <?php endif; ?>
    <?php if (!empty($erro)): ?>
      <div class="alert alert-danger text-center"><?php echo $erro; ?></div>
    <?php endif; ?>

    <div class="row justify-content-center">
      <!-- Registro de Venda -->
      <div class="col-md-5">
        <div class="card-custom">
          <h4>Registrar Venda</h4>
          <form method="POST">
            <input type="hidden" name="form_type" value="venda">
            <div class="mb-3">
              <label for="agente_id" class="form-label">Agente</label>
              <select name="agente_id" id="agente_id" class="form-select" required>
                <option value="">-- Selecione o Agente --</option>
                <?php foreach ($agentes as $agente): ?>
                  <option value="<?php echo $agente['id']; ?>"><?php echo htmlspecialchars($agente['nome']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label for="valor_venda" class="form-label">Valor da Venda (R$)</label>
              <input type="number" step="0.01" name="valor_venda" id="valor_venda" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="quantidade_contratos" class="form-label">Quantidade de Contratos</label>
              <input type="number" name="quantidade_contratos" id="quantidade_contratos" class="form-control" min="1" value="1" required>
            </div>
            <div class="mb-3">
              <label for="setor" class="form-label">Setor</label>
              <select name="setor" id="setor" class="form-select" required>
                <option value="rede">Rede</option>
                <option value="tele">Tele</option>
                <option value="loja">Loja</option>
              </select>
            </div>
            <button type="submit" class="btn btn-primary w-100">Registrar Venda</button>
          </form>
        </div>
      </div>

      <!-- Registro de Contrato Vendido -->
      <div class="col-md-5">
        <div class="card-custom">
          <h4>Registrar Contrato</h4>
          <form method="POST">
            <input type="hidden" name="form_type" value="contrato">
            <div class="mb-3">
              <label for="valor_contrato" class="form-label">Valor do Contrato (R$)</label>
              <input type="number" step="0.01" name="valor_contrato" id="valor_contrato" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="setor_contrato" class="form-label">Setor</label>
              <select name="setor_contrato" id="setor_contrato" class="form-select" required>
                <option value="rede">Rede</option>
                <option value="tele">Tele</option>
                <option value="loja">Loja</option>
              </select>
            </div>
            <button type="submit" class="btn btn-secondary w-100">Registrar Contrato</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <?php include(FOOTER_FILE); ?>
</body>

</html>