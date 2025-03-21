<?php
// Configurações do Banco 1: "credesh"
$db1 = [
    'host'     => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'credesh'
];

// Configurações do Banco 2: "credinowe_consignado_teste"
$db2 = [
    'host'     => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'credinowe_consignado_teste'
];

// Cria a conexão com o Banco 1 (credesh)
$conn1 = new mysqli($db1['host'], $db1['username'], $db1['password'], $db1['database']);
if ($conn1->connect_error) {
    die("Falha na conexão com o banco 'credesh': " . $conn1->connect_error);
}
$conn1->set_charset("utf8mb4");

// Cria a conexão com o Banco 2 (credinowe_consignado_teste)
$conn2 = new mysqli($db2['host'], $db2['username'], $db2['password'], $db2['database']);
if ($conn2->connect_error) {
    die("Falha na conexão com o banco 'credinowe_consignado_teste': " . $conn2->connect_error);
}
$conn2->set_charset("utf8mb4");

/*
 * Funções auxiliares para obter as conexões, se necessário.
 * Assim, você pode chamar getCredeshConnection() ou getConsignadoConnection() em outros arquivos.
 */
function getCredeshConnection() {
    global $conn1;
    return $conn1;
}

function getConsignadoConnection() {
    global $conn2;
    return $conn2;
}

// Exemplo de mensagem (opcional)
// echo "Conexão com 'credesh' estabelecida com sucesso.<br>";
// echo "Conexão com 'credinowe_consignado_teste' estabelecida com sucesso.<br>";
?>
