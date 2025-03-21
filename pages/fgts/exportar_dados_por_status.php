<?php
// exportar_dados_por_status.php

// Inclua os arquivos de configuração e caminhos absolutos conforme sua estrutura
require_once __DIR__ . '/../../absoluto.php';
require_once __DIR__ . '/../../db/config.php';
include(HEADER_FILE);

// Se o formulário foi enviado (método POST), processa a exportação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $status = $_POST['status'];
    
    // Monta a consulta com base no status selecionado
    if ($status === 'todos') {
        $query = "
            SELECT m.id, m.valor, m.status, m.quantidade, m.created_at, a.nome AS agente_nome
            FROM metas_fgts m
            JOIN agentes_fgts a ON m.agente_id = a.id
            ORDER BY m.created_at DESC
        ";
        $stmt = $conn->prepare($query);
    } else {
        $query = "
            SELECT m.id, m.valor, m.status, m.quantidade, m.created_at, a.nome AS agente_nome
            FROM metas_fgts m
            JOIN agentes_fgts a ON m.agente_id = a.id
            WHERE m.status = ?
            ORDER BY m.created_at DESC
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $status);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Configura os headers para download do CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="export_status_'.$status.'.csv"');
    
    $output = fopen('php://output', 'w');
    // Escreve a linha de cabeçalho do CSV
    fputcsv($output, array('ID', 'Agente', 'Valor (R$)', 'Status', 'Quantidade', 'Data/Hora'));
    
    // Escreve os dados no CSV
    while ($row = $result->fetch_assoc()) {
        // Formata o valor para o padrão brasileiro
        $row['valor'] = number_format($row['valor'], 2, ',', '.');
        fputcsv($output, array(
            $row['id'], 
            $row['agente_nome'], 
            $row['valor'], 
            $row['status'], 
            $row['quantidade'], 
            $row['created_at']
        ));
    }
    
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Exportar Dados por Status - Metas FGTS</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f5f5f5;
      color: #333;
      padding: 20px;
    }
    .container {
      max-width: 500px;
      margin: 40px auto;
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    h1 {
      text-align: center;
      margin-bottom: 20px;
    }
    form label {
      display: block;
      margin-top: 10px;
      font-weight: bold;
    }
    form select, form button {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 1rem;
    }
    form button {
      margin-top: 15px;
      background: #007bff;
      color: #fff;
      border: none;
      cursor: pointer;
      transition: background 0.3s ease;
    }
    form button:hover {
      background: #0056b3;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Exportar Dados por Status</h1>
    <form method="POST" action="">
      <label for="status">Selecione o Status:</label>
      <select id="status" name="status" required>
        <option value="">Selecione um status</option>
        <option value="todos">Todos</option>
        <option value="pago">Pago</option>
        <option value="ag-formalizacao">AG Formalização</option>
        <option value="pendente">Pendente</option>
      </select>
      <button type="submit">Exportar CSV</button>
    </form>
  </div>
</body>
</html>
