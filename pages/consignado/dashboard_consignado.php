<?php
session_start(); // Importante para usar $_SESSION

require_once '../../db/db.php';
include '../../absoluto.php';
include(HEADER_FILE);

// Conexão MySQLi
$conn = getDatabaseConnection();

/* ==========================================================
   1) Processamento de Atualização de Metas via Modal
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_metas'])) {
  $selectedPeriod = $_POST['periodo'] ?? date('Y-m');
  $periodo = $selectedPeriod . "-01";

  $sqlConsultores = "SELECT id FROM users WHERE tipo = 'consultor'";
  $resultCons = $conn->query($sqlConsultores);
  $consultores = $resultCons->fetch_all(MYSQLI_ASSOC);

  foreach ($consultores as $cons) {
    $id = $cons['id'];
    $metaDiaria  = isset($_POST["meta_diaria_$id"]) ? floatval($_POST["meta_diaria_$id"]) : 0;
    $metaSemanal = isset($_POST["meta_semanal_$id"]) ? floatval($_POST["meta_semanal_$id"]) : 0;
    $metaMensal  = isset($_POST["meta_mensal_$id"]) ? floatval($_POST["meta_mensal_$id"]) : 0;

    $sqlCheck = "SELECT id FROM metas_consultores_consignado WHERE consultor_id = ? AND periodo = ?";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bind_param("is", $id, $periodo);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();
    if ($row = $resCheck->fetch_assoc()) {
      $sqlUpdate = "UPDATE metas_consultores_consignado SET meta_diaria = ?, meta_semanal = ?, meta_mensal = ? WHERE id = ?";
      $stmtUpdate = $conn->prepare($sqlUpdate);
      $stmtUpdate->bind_param("dddi", $metaDiaria, $metaSemanal, $metaMensal, $row['id']);
      $stmtUpdate->execute();
      $stmtUpdate->close();
    } else {
      $sqlInsert = "INSERT INTO metas_consultores_consignado (consultor_id, meta_diaria, meta_semanal, meta_mensal, periodo) VALUES (?, ?, ?, ?, ?)";
      $stmtInsert = $conn->prepare($sqlInsert);
      $stmtInsert->bind_param("iddds", $id, $metaDiaria, $metaSemanal, $metaMensal, $periodo);
      $stmtInsert->execute();
      $stmtInsert->close();
    }
    $stmtCheck->close();
  }
  $message = "Metas atualizadas para o período " . $periodo;
}

/* ==========================================================
   2) Buscar metas dos consultores para o período
   ========================================================== */
$metaPeriodo = date('Y-m-01');
$sqlMetas = "SELECT * FROM metas_consultores_consignado WHERE periodo = ?";
$stmtMetas = $conn->prepare($sqlMetas);
$stmtMetas->bind_param("s", $metaPeriodo);
$stmtMetas->execute();
$resMetas = $stmtMetas->get_result();
$metasConsultores = [];
while ($row = $resMetas->fetch_assoc()) {
  $metasConsultores[$row['consultor_id']] = $row;
}
$stmtMetas->close();

/* ==========================================================
   3) Buscar total pago de cada consultor no período
   ========================================================== */
$sqlTotais = "SELECT consultor_id, SUM(valor_liquido) as total_pago FROM digitacoes WHERE status = 'PAGO' AND data_pagamento BETWEEN ? AND ? GROUP BY consultor_id";
$stmtTot = $conn->prepare($sqlTotais);
$periodStart = date('Y-m-01');
$periodEnd = date('Y-m-t');
$stmtTot->bind_param("ss", $periodStart, $periodEnd);
$stmtTot->execute();
$resTot = $stmtTot->get_result();
$totaisConsultores = [];
while ($row = $resTot->fetch_assoc()) {
  $totaisConsultores[$row['consultor_id']] = floatval($row['total_pago']);
}
$stmtTot->close();

/* ==========================================================
   4) Preparar ranking e cálculos para cada consultor
   ========================================================== */
$ranking = [];
foreach ($metasConsultores as $cid => $meta) {
  $nome = "Consultor $cid";
  $totalPago = isset($totaisConsultores[$cid]) ? $totaisConsultores[$cid] : 0;
  $metaValor = $meta['meta_mensal'];
  $percent = ($metaValor > 0) ? round(($totalPago / $metaValor) * 100, 2) : 0;
  $ranking[] = [
    'consultor_id'   => $cid,
    'consultor_nome' => $nome,
    'total_pago'     => $totalPago,
    'meta'           => $metaValor,
    'percent'        => $percent
  ];
}

// Converte os dados para JSON para usar no gráfico
$rankingLabels = array_column($ranking, 'consultor_nome');
$rankingTotals = array_column($ranking, 'total_pago');
$rankingMetas  = array_column($ranking, 'meta');
$rankingPerc   = array_column($ranking, 'percent');

/* ==========================================================
   1) FORM: SALVAR MODELO E CARREGAR MODELO
   ========================================================== */

// Salvar modelo
if (isset($_POST['salvar_preset'])) {
  $presetName = $_POST['preset_name'] ?? 'Sem Nome';
  $consultoresSelecionados = $_POST['consultores'] ?? []; // array de IDs
  $consultoresJson = json_encode($consultoresSelecionados);

  $sqlInsert = "INSERT INTO dashboard_presets (preset_name, consultores_json) VALUES (?, ?)";
  $stmt = $conn->prepare($sqlInsert);
  $stmt->bind_param('ss', $presetName, $consultoresJson);
  $stmt->execute();
  $stmt->close();

  // Redirecionar para evitar re-post
  header("Location: " . $_SERVER['PHP_SELF'] . "?msg=Modelo+salvo");
  exit;
}

// Carregar modelo
if (isset($_POST['load_preset'])) {
  $presetId = $_POST['preset_id'] ?? 0;
  $sqlLoad = "SELECT * FROM dashboard_presets WHERE id = ?";
  $stmtL = $conn->prepare($sqlLoad);
  $stmtL->bind_param('i', $presetId);
  $stmtL->execute();
  $resL = $stmtL->get_result();
  $rowPreset = $resL->fetch_assoc();
  $stmtL->close();

  if ($rowPreset) {
    // Guardar em $_SESSION
    $consultoresDoModelo = json_decode($rowPreset['consultores_json'], true) ?: [];
    $_SESSION['consultores_model'] = $consultoresDoModelo;
  }
  header("Location: " . $_SERVER['PHP_SELF'] . "?msg=Modelo+carregado");
  exit;
}

// Limpar modelo
if (isset($_POST['clear_preset'])) {
  unset($_SESSION['consultores_model']);
  header("Location: " . $_SERVER['PHP_SELF'] . "?msg=Modelo+limpo");
  exit;
}

/* ==========================================================
   2) FILTROS VIA GET (exceto consultores, pois usamos modelo)
   ========================================================== */
$filterStatus  = isset($_GET['status'])  ? $_GET['status']  : '';
$filterProduto = isset($_GET['produto']) ? $_GET['produto'] : '';
$dataInicio    = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$dataFim       = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';

// Se não houver datas, filtrar pelo mês vigente
if (empty($dataInicio) && empty($dataFim)) {
  $dataInicio = date('Y-m-01');
  $dataFim    = date('Y-m-t');
}

/* ==========================================================
   3) CONSULTOR(ES) DO MODELO (array)
   ========================================================== */
$consultoresModel = isset($_SESSION['consultores_model']) ? $_SESSION['consultores_model'] : [];

/* ==========================================================
   4) PEGAR TODOS OS CONSULTORES (para exibir em forms e etc.)
   ========================================================== */
$queryAllConsultores = "
    SELECT id AS consultor_id, nome AS consultor_nome
    FROM users
    WHERE tipo = 'consultor'
    ORDER BY nome
";
$resAll = $conn->query($queryAllConsultores);
$allConsultores = $resAll->fetch_all(MYSQLI_ASSOC);

/* ==========================================================
   5) DIGITAÇÕES (FILTRADAS) => USAMOS MULTI-CONSULTOR
   ========================================================== */
$queryDigitacoes = "
    SELECT d.id,
           d.nome AS nome_cliente,
           d.data_pagamento,
           d.produto,
           d.valor_liquido,
           d.status,
           c.id AS consultor_id,
           c.nome AS consultor_nome
    FROM digitacoes d
    LEFT JOIN users c ON d.consultor_id = c.id
    WHERE c.tipo = 'consultor'
";
$params = [];
$types  = "";

// Se temos consultores no modelo, filtrar c.id IN (...)
if (!empty($consultoresModel)) {
  $inPlaceholders = implode(',', array_fill(0, count($consultoresModel), '?'));
  $queryDigitacoes .= " AND c.id IN ($inPlaceholders) ";
  $params = array_merge($params, $consultoresModel);
  $types .= str_repeat('i', count($consultoresModel));
}

// Status
if (!empty($filterStatus)) {
  $queryDigitacoes .= " AND d.status = ? ";
  $params[] = $filterStatus;
  $types   .= "s";
} else {
  // Se não selecionou status, forçar 3
  $queryDigitacoes .= " AND d.status IN ('PAGO','AG Formalizado','Formalizado') ";
}

// Produto
if (!empty($filterProduto)) {
  $queryDigitacoes .= " AND d.produto LIKE ? ";
  $params[] = '%' . $filterProduto . '%';
  $types   .= "s";
}

// Datas
if (!empty($dataInicio)) {
  $queryDigitacoes .= " AND d.data_pagamento >= ? ";
  $params[] = $dataInicio;
  $types   .= "s";
}
if (!empty($dataFim)) {
  $queryDigitacoes .= " AND d.data_pagamento <= ? ";
  $params[] = $dataFim;
  $types   .= "s";
}

$stmtDig = $conn->prepare($queryDigitacoes);
if (!empty($params)) {
  $stmtDig->bind_param($types, ...$params);
}
$stmtDig->execute();
$resDig = $stmtDig->get_result();
$digitacoes = $resDig->fetch_all(MYSQLI_ASSOC);
$stmtDig->close();

/* ==========================================================
   6) SOMA (TOTAL PAGO) POR CONSULTOR
   ========================================================== */
$querySoma = "
    SELECT c.id AS consultor_id,
           c.nome AS consultor_nome,
           COALESCE(SUM(d.valor_liquido), 0) AS total_pago
    FROM users c
    LEFT JOIN digitacoes d
        ON d.consultor_id = c.id
        AND d.status IN ('PAGO','AG Formalizado','Formalizado')
        AND d.data_pagamento >= ?
        AND d.data_pagamento <= ?
";
$paramsSoma = [$dataInicio, $dataFim];
$typesSoma  = "ss";

// Se temos consultores no modelo
if (!empty($consultoresModel)) {
  $inPlaceholders = implode(',', array_fill(0, count($consultoresModel), '?'));
  $querySoma .= " AND c.id IN ($inPlaceholders) ";
  foreach ($consultoresModel as $cid) {
    $paramsSoma[] = $cid;
    $typesSoma   .= "i";
  }
}

$querySoma .= "
    WHERE c.tipo = 'consultor'
    GROUP BY c.id
    ORDER BY c.nome
";

$stmtSoma = $conn->prepare($querySoma);
$stmtSoma->bind_param($typesSoma, ...$paramsSoma);
$stmtSoma->execute();
$resSoma = $stmtSoma->get_result();
$consultoresComSoma = $resSoma->fetch_all(MYSQLI_ASSOC);
$stmtSoma->close();

/* -- Preparar arrays p/ gráfico "Total Pago" */
$labels = [];
$valores = [];
foreach ($consultoresComSoma as $cs) {
  $labels[]  = $cs['consultor_nome'];
  $valores[] = (float) $cs['total_pago'];
}

/* ==========================================================
   7) GRÁFICO ABC (ordem decrescente)
   ========================================================== */
$consultoresOrdenados = $consultoresComSoma;
usort($consultoresOrdenados, function ($a, $b) {
  return $b['total_pago'] <=> $a['total_pago'];
});
$abcLabels  = array_column($consultoresOrdenados, 'consultor_nome');
$abcValores = array_map('floatval', array_column($consultoresOrdenados, 'total_pago'));

/* ==========================================================
   8) GRÁFICO DE DISTRIBUIÇÃO DE STATUS
   ========================================================== */
$queryStatus = "
    SELECT d.status, COUNT(*) as total
    FROM digitacoes d
    LEFT JOIN users c ON d.consultor_id = c.id
    WHERE c.tipo = 'consultor'
";
$paramsSt = [];
$typesSt  = "";

// Filtrar consultores do modelo
if (!empty($consultoresModel)) {
  $inPlaceholders = implode(',', array_fill(0, count($consultoresModel), '?'));
  $queryStatus .= " AND c.id IN ($inPlaceholders) ";
  $paramsSt = array_merge($paramsSt, $consultoresModel);
  $typesSt .= str_repeat('i', count($consultoresModel));
}

// Status
if (!empty($filterStatus)) {
  $queryStatus .= " AND d.status = ? ";
  $paramsSt[] = $filterStatus;
  $typesSt   .= "s";
} else {
  $queryStatus .= " AND d.status IN ('PAGO','AG Formalizado','Formalizado') ";
}

// Produto
if (!empty($filterProduto)) {
  $queryStatus .= " AND d.produto LIKE ? ";
  $paramsSt[] = '%' . $filterProduto . '%';
  $typesSt   .= "s";
}

// Datas
if (!empty($dataInicio)) {
  $queryStatus .= " AND d.data_pagamento >= ? ";
  $paramsSt[] = $dataInicio;
  $typesSt   .= "s";
}
if (!empty($dataFim)) {
  $queryStatus .= " AND d.data_pagamento <= ? ";
  $paramsSt[] = $dataFim;
  $typesSt   .= "s";
}

$queryStatus .= " GROUP BY d.status ";

$stmtSt = $conn->prepare($queryStatus);
if (!empty($paramsSt)) {
  $stmtSt->bind_param($typesSt, ...$paramsSt);
}
$stmtSt->execute();
$resSt = $stmtSt->get_result();
$statusData = $resSt->fetch_all(MYSQLI_ASSOC);
$stmtSt->close();

$statusLabels = array_column($statusData, 'status');
$statusValues = array_map('intval', array_column($statusData, 'total'));

/* ==========================================================
   9) METAS (dias trabalhados)
   ========================================================== */
$queryConfig = "SELECT * FROM dias_trabalho ORDER BY id DESC LIMIT 1";
$resConfig   = $conn->query($queryConfig);
$config      = $resConfig->fetch_assoc();

if ($config) {
  // Ex.: se $config['mes'] = "2025-03"
  $anoMes = explode('-', $config['mes']);
  // $anoMes[0] = "2025", $anoMes[1] = "03"
  $ano    = (int)$anoMes[0];
  $mesNum = (int)$anoMes[1];

  // Construímos a data do último dia do mês, mas definimos "23:59:59"
  // para capturar todo o último dia.
  $dataFimConfig = date("Y-m-t 23:59:59", strtotime("$ano-$mesNum-01"));

  // Captura o timestamp atual (incluindo hora e minuto exatos).
  $agora = time();

  // Calcula a diferença em segundos e converte para dias com floor (truncar).
  $diferencaSegundos = strtotime($dataFimConfig) - $agora;
  $diasRestantes = floor($diferencaSegundos / 86400);

  // Se o resultado ficar negativo (já passou da dataFimConfig), forçamos zero.
  if ($diasRestantes < 0) {
    $diasRestantes = 0;
  }
} else {
  // Se não há registro em dias_trabalho, define 0 ou outro valor padrão.
  $diasRestantes = 0;
}


/* ==========================================================
   10) BUSCAR TODOS OS PRESETS
   ========================================================== */
$sqlAll = "SELECT * FROM dashboard_presets ORDER BY created_at DESC";
$resAll = $conn->query($sqlAll);
$allPresets = $resAll->fetch_all(MYSQLI_ASSOC);

// Fechar a conexão
$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <title>Dashboard - Consignado</title>
  <!-- Bootstrap 5 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      background-color: #f8f9fa;
    }

    .card-consultor {
      min-height: 150px;
      cursor: pointer;
    }

    .card-consultor:hover {
      background-color: #f1f3f5;
      transition: 0.2s;
    }

    .table thead th {
      background-color: #f1f3f5;
    }

    .modal-lg {
      max-width: 90%;
    }
  </style>
</head>

<body>
  <div class="container my-4">

    <h1 class="mb-4">Dashboard - Consignado</h1>

    <!-- FILTROS + DIAS TRABALHADOS NO TOPO -->
    <div class="row">
      <!-- Coluna da esquerda: Filtros -->
      <div class="col-md-9">
        <div class="card mb-3">
          <div class="card-body">
            <h5 class="card-title">Filtros</h5>
            <form class="row g-3" method="GET" action="">
              <!-- Status -->
              <div class="col-md-4">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select">
                  <option value="">Status</option>
                  <option value="PAGO" <?php if ($filterStatus == 'PAGO') echo 'selected'; ?>>PAGO</option>
                  <option value="AG Formalizado" <?php if ($filterStatus == 'AG Formalizado') echo 'selected'; ?>>AG Formalizado</option>
                  <option value="Formalizado" <?php if ($filterStatus == 'Formalizado') echo 'selected'; ?>>Formalizado</option>
                </select>
              </div>

              <!-- Produto -->
              <div class="col-md-4">
                <label for="produto" class="form-label">Produto</label>
                <input type="text" name="produto" id="produto" class="form-control"
                  value="<?php echo htmlspecialchars($filterProduto); ?>"
                  placeholder="Ex: MARGEM, PESSOAL..." />
              </div>

              <!-- Data Início -->
              <div class="col-md-4">
                <label for="data_inicio" class="form-label">Data Início</label>
                <input type="date" name="data_inicio" id="data_inicio" class="form-control"
                  value="<?php echo htmlspecialchars($dataInicio); ?>">
              </div>

              <!-- Data Fim -->
              <div class="col-md-4">
                <label for="data_fim" class="form-label">Data Fim</label>
                <input type="date" name="data_fim" id="data_fim" class="form-control"
                  value="<?php echo htmlspecialchars($dataFim); ?>">
              </div>

              <div class="col-12">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <!-- Botão para limpar modelo e filtros -->
                <form method="POST" action="" class="d-inline">
                  <button type="submit" name="clear_preset" class="btn btn-warning ms-2">
                    Limpar Modelo
                  </button>
                </form>
              </div>
            </form>
            <p class="mt-2">
              <strong>Observação:</strong> Se nenhuma data for selecionada, usamos o mês vigente (<?php echo date('m/Y'); ?>).
            </p>
          </div>
        </div>
      </div>
      <!-- Coluna da direita: Pequeno card Dias Trabalhados -->
      <div class="col-md-3">
        <div class="card">
          <div class="card-body text-center">
            <h6 class="card-title">Dias Trabalhados</h6>
            <p class="card-text mb-1">
              <small>Dias Restantes para o término do período:</small>
            </p>
            <h4 class="text-primary">
              <?php echo $diasRestantes; ?>
            </h4>
          </div>
        </div>
      </div>
    </div><!-- row -->

    <!-- GERENCIAR MODELOS -->
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">Gerenciar Modelos de Consultores</h5>
        <div class="row">
          <!-- Criar/Salvar -->
          <div class="col-md-6">
            <h6>Criar/Salvar um Modelo</h6>
            <form method="POST" action="">
              <div class="mb-3">
                <label for="preset_name" class="form-label">Nome do Modelo</label>
                <input type="text" name="preset_name" id="preset_name" class="form-control" required placeholder="Ex: Equipe X">
              </div>
              <p>Selecione os consultores:</p>
              <div class="row">
                <?php foreach ($allConsultores as $c): ?>
                  <div class="col-md-6 mb-2">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox"
                        name="consultores[]" value="<?php echo $c['consultor_id']; ?>"
                        id="chk_consultor_<?php echo $c['consultor_id']; ?>">
                      <label class="form-check-label" for="chk_consultor_<?php echo $c['consultor_id']; ?>">
                        <?php echo $c['consultor_nome']; ?>
                      </label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <button type="submit" name="salvar_preset" class="btn btn-success mt-3">Salvar Modelo</button>
            </form>
          </div>
          <!-- Carregar -->
          <div class="col-md-6">
            <h6>Carregar Modelo Existente</h6>
            <form method="POST" action="">
              <div class="mb-3">
                <label for="preset_id" class="form-label">Selecione o Modelo</label>
                <select name="preset_id" id="preset_id" class="form-select">
                  <option value="">-- Selecione --</option>
                  <?php foreach ($allPresets as $p): ?>
                    <option value="<?php echo $p['id']; ?>">
                      <?php echo $p['preset_name']; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <button type="submit" name="load_preset" class="btn btn-primary">Carregar</button>
            </form>

            <?php if (!empty($consultoresModel)): ?>
              <p class="mt-3">
                <strong>Consultores Carregados:</strong><br>
                <?php echo implode(', ', $consultoresModel); ?>
              </p>
            <?php else: ?>
              <p class="mt-3">Nenhum modelo carregado no momento.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- BOTÃO PARA MOSTRAR/ESCONDER OS CARDS DE CONSULTORES -->
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="mb-0">Consultores e seus Valores</h5>
      <button class="btn btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#consultoresCollapse" aria-expanded="false" aria-controls="consultoresCollapse">
        Mostrar/Esconder
      </button>
    </div>
    <div class="collapse" id="consultoresCollapse">
      <div class="row row-cols-1 row-cols-md-3 g-4 mb-4">
        <?php
        // Agrupar digitações por consultor_id
        $digitacoesPorConsultor = [];
        foreach ($digitacoes as $dig) {
          $cid = $dig['consultor_id'] ?? 0;
          if (!isset($digitacoesPorConsultor[$cid])) {
            $digitacoesPorConsultor[$cid] = [];
          }
          $digitacoesPorConsultor[$cid][] = $dig;
        }
        ?>
        <?php foreach ($consultoresComSoma as $cs): ?>
          <div class="col">
            <div class="card card-consultor shadow-sm" data-bs-toggle="modal" data-bs-target="#modalConsultor-<?php echo $cs['consultor_id']; ?>">
              <div class="card-body">
                <h5 class="card-title"><?php echo $cs['consultor_nome']; ?></h5>
                <p class="card-text mb-0">
                  <strong>Total Pago:</strong><br>
                  R$ <?php echo number_format($cs['total_pago'], 2, ',', '.'); ?>
                </p>
                <p class="mt-2 text-muted" style="font-size: 0.9rem;">
                  Clique para ver detalhes
                </p>
              </div>
            </div>
          </div>

          <!-- MODAL -->
          <div class="modal fade" id="modalConsultor-<?php echo $cs['consultor_id']; ?>" tabindex="-1" aria-labelledby="modalLabel-<?php echo $cs['consultor_id']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title" id="modalLabel-<?php echo $cs['consultor_id']; ?>">
                    Digitações de <?php echo $cs['consultor_nome']; ?>
                  </h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                  <?php
                  $cid = $cs['consultor_id'];
                  $digConsultor = $digitacoesPorConsultor[$cid] ?? [];
                  ?>
                  <?php if (count($digConsultor) > 0): ?>
                    <div class="table-responsive">
                      <table class="table table-bordered align-middle">
                        <thead>
                          <tr>
                            <th>Nome do Cliente</th>
                            <th>Data Pagamento</th>
                            <th>Produto</th>
                            <th>Valor Líquido</th>
                            <th>Status</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($digConsultor as $dig): ?>
                            <tr>
                              <td><?php echo $dig['nome_cliente']; ?></td>
                              <td><?php echo $dig['data_pagamento']; ?></td>
                              <td><?php echo $dig['produto']; ?></td>
                              <td>R$ <?php echo number_format($dig['valor_liquido'], 2, ',', '.'); ?></td>
                              <td><?php echo $dig['status']; ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php else: ?>
                    <p>Nenhuma digitação para este consultor.</p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <!-- FIM MODAL -->
        <?php endforeach; ?>
      </div>
    </div><!-- /.collapse -->

    <!-- GRÁFICOS: 5 TIPOS DIFERENTES -->
    <h1>Dashboard - Metas dos Consultores</h1>
    <canvas id="graficoComparativo"></canvas>

    <h5 class="mt-4 mb-3">Gráficos de Estatísticas (5 tipos)</h5>
    <div class="row">
      <!-- 1) Gráfico BAR (Total Pago) -->
      <div class="col-md-4 mb-4">
        <div class="card">
          <div class="card-header">
            Total Pago (R$) - Bar
          </div>
          <div class="card-body">
            <canvas id="chartTotalPago"></canvas>
          </div>
        </div>
      </div>

      <!-- 2) Gráfico BAR (ABC) horizontal -->
      <div class="col-md-4 mb-4">
        <div class="card">
          <div class="card-header">
            ABC (Ordem Decrescente)
          </div>
          <div class="card-body">
            <canvas id="chartABC"></canvas>
          </div>
        </div>
      </div>

      <!-- 3) Gráfico DOUGHNUT (Status) -->
      <div class="col-md-4 mb-4">
        <div class="card">
          <div class="card-header">
            Distribuição de Status (Doughnut)
          </div>
          <div class="card-body">
            <canvas id="chartStatus"></canvas>
          </div>
        </div>
      </div>
    </div><!-- row -->

    <div class="row">
      <!-- 4) Gráfico LINE (Total Pago) -->
      <div class="col-md-6 mb-4">
        <div class="card">
          <div class="card-header">
            Total Pago (R$) - Line
          </div>
          <div class="card-body">
            <canvas id="chartLine"></canvas>
          </div>
        </div>
      </div>

      <!-- 5) Gráfico POLAR AREA (ABC) -->
      <div class="col-md-6 mb-4">
        <div class="card">
          <div class="card-header">
            ABC (Polar Area)
          </div>
          <div class="card-body">
            <canvas id="chartPolar"></canvas>
          </div>
        </div>
      </div>
    </div><!-- row -->

  </div><!-- /.container -->

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const ctxComp = document.getElementById('graficoComparativo').getContext('2d');
    new Chart(ctxComp, {
      type: 'bar',
      data: {
        labels: <?php echo json_encode($rankingLabels, JSON_UNESCAPED_UNICODE); ?>,
        datasets: [{
            label: 'Total Pago',
            data: <?php echo json_encode($rankingTotals); ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.8)'
          },
          {
            label: 'Meta',
            data: <?php echo json_encode($rankingMetas); ?>,
            backgroundColor: 'rgba(255, 99, 132, 0.8)'
          }
        ]
      },
      options: {
        responsive: true
      }
    });
  </script>
  <script>
    // 1) chartTotalPago (Bar)
    const consultoresLabels = <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>;
    const consultoresValores = <?php echo json_encode($valores, JSON_UNESCAPED_UNICODE); ?>;

    const ctx1 = document.getElementById('chartTotalPago').getContext('2d');
    const chartTotalPago = new Chart(ctx1, {
      type: 'bar',
      data: {
        labels: consultoresLabels,
        datasets: [{
          label: 'Total Pago (R$)',
          data: consultoresValores,
          backgroundColor: 'rgba(54, 162, 235, 0.6)',
          borderColor: 'rgba(54, 162, 235, 1)',
          borderWidth: 1,
          borderRadius: 5
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return value.toLocaleString('pt-BR', {
                  style: 'currency',
                  currency: 'BRL'
                });
              }
            }
          }
        }
      }
    });

    // 2) chartABC (Bar) horizontal
    const abcLabels = <?php echo json_encode($abcLabels, JSON_UNESCAPED_UNICODE); ?>;
    const abcValores = <?php echo json_encode($abcValores, JSON_UNESCAPED_UNICODE); ?>;

    const ctx2 = document.getElementById('chartABC').getContext('2d');
    const chartABC = new Chart(ctx2, {
      type: 'bar',
      data: {
        labels: abcLabels,
        datasets: [{
          label: 'Total Pago (R$) [Desc]',
          data: abcValores,
          backgroundColor: 'rgba(255, 159, 64, 0.6)',
          borderColor: 'rgba(255, 159, 64, 1)',
          borderWidth: 1,
          borderRadius: 5
        }]
      },
      options: {
        responsive: true,
        indexAxis: 'y', // Barras horizontais
        scales: {
          x: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return value.toLocaleString('pt-BR', {
                  style: 'currency',
                  currency: 'BRL'
                });
              }
            }
          }
        }
      }
    });

    // 3) chartStatus (Doughnut)
    const statusLabels = <?php echo json_encode($statusLabels, JSON_UNESCAPED_UNICODE); ?>;
    const statusValues = <?php echo json_encode($statusValues, JSON_UNESCAPED_UNICODE); ?>;

    const ctx3 = document.getElementById('chartStatus').getContext('2d');
    const chartStatus = new Chart(ctx3, {
      type: 'doughnut',
      data: {
        labels: statusLabels,
        datasets: [{
          data: statusValues,
          backgroundColor: [
            'rgba(75, 192, 192, 0.6)',
            'rgba(255, 99, 132, 0.6)',
            'rgba(255, 205, 86, 0.6)',
            'rgba(153, 102, 255, 0.6)',
            'rgba(201, 203, 207, 0.6)'
          ],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true
      }
    });

    // 4) chartLine (Line) - mesmo data do Total Pago
    const ctx4 = document.getElementById('chartLine').getContext('2d');
    const chartLine = new Chart(ctx4, {
      type: 'line',
      data: {
        labels: consultoresLabels,
        datasets: [{
          label: 'Total Pago (R$) [Line]',
          data: consultoresValores,
          fill: false,
          borderColor: 'rgba(75, 192, 192, 1)',
          tension: 0.1
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return value.toLocaleString('pt-BR', {
                  style: 'currency',
                  currency: 'BRL'
                });
              }
            }
          }
        }
      }
    });

    // 5) chartPolar (PolarArea) - usando ABC
    const ctx5 = document.getElementById('chartPolar').getContext('2d');
    const chartPolar = new Chart(ctx5, {
      type: 'polarArea',
      data: {
        labels: abcLabels,
        datasets: [{
          label: 'ABC (Polar)',
          data: abcValores,
          backgroundColor: [
            'rgba(255, 99, 132, 0.6)',
            'rgba(54, 162, 235, 0.6)',
            'rgba(255, 205, 86, 0.6)',
            'rgba(75, 192, 192, 0.6)',
            'rgba(153, 102, 255, 0.6)',
            'rgba(201, 203, 207, 0.6)'
          ]
        }]
      },
      options: {
        responsive: true
      }
    });
  </script>

</body>
<?php include(FOOTER_FILE); ?>

</html>