<?php
function getDatabaseConnection()
{
    $host     = 'localhost';
    $db       = 'credinowe_consignado_teste';
    $user     = 'root';
    $password = '';
    

    $conn = new mysqli($host, $user, $password, $db);

    if ($conn->connect_error) {
        die("Erro ao conectar ao banco de dados: " . $conn->connect_error);
    }

    // Ajuste de charset (recomendado)
    $conn->set_charset("utf8mb4");

    return $conn;
}
