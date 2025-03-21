<?php
session_start();
require_once __DIR__ . '/../../absoluto.php';   // Ajuste conforme seu projeto
require_once __DIR__ . '/../../db/config.php';  // Ajuste conforme seu projeto

// Verifica se o usuário é supervisor do setor Energia
if (!isset($_SESSION['user_id']) || ($_SESSION['perfil'] ?? '') !== 'supervisor' || ($_SESSION['setor'] ?? '') !== 'Energia') {
    die("Acesso negado.");
}

include(HEADER_FILE);  // Ajuste conforme seu projeto

// Se o formulário foi enviado (clique em "Exportar"), gera o CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Conecta ao banco de dados
    $conn = new mysqli($host, $db_username, $db_password, $db_name);
    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    // Nome do arquivo que será baixado (pode personalizar)
    $filename = "vendas_energia_" . date('Y-m-d') . ".csv";

    // Define os headers para forçar o download em CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Cria um "ponteiro" para escrever no output
    $output = fopen('php://output', 'w');

    // Cabeçalho das colunas (ajuste conforme as colunas desejadas)
    fputcsv($output, [
        'ID',
        'Agente',
        'Valor Venda',
        'Quantidade Contratos',
        'Status',
        'Setor',
        'Data Registro'
    ]);

    // Consulta (JOIN com agentes_energia para exibir nome do agente)
    $sql = "
        SELECT v.id,
               a.nome AS agente,
               v.valor_venda,
               v.quantidade_contratos,
               v.status,
               v.setor,
               v.data_registro
          FROM vendas_energia v
          JOIN agentes_energia a ON v.agente_id = a.id
      ORDER BY v.data_registro DESC
    ";

    $result = $conn->query($sql);

    // Percorre as linhas retornadas e grava no CSV
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['agente'],
                $row['valor_venda'],
                $row['quantidade_contratos'],
                $row['status'],
                $row['setor'],
                $row['data_registro']
            ]);
        }
    }

    // Fecha o "ponteiro" e a conexão
    fclose($output);
    $conn->close();

    // Encerra o script após gerar o CSV
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Exportar Vendas Energia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h2>Exportar Vendas de Energia</h2>
        <p>Clique no botão abaixo para exportar todas as vendas em um arquivo CSV.</p>

        <form method="POST">
            <button type="submit" class="btn btn-primary">Exportar CSV</button>
        </form>
    </div>
</body>
<?php include(FOOTER_FILE); ?>

</html>