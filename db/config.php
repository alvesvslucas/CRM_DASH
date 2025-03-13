<?php
// Configurações do banco de dados
$host        = 'localhost';
$db_username = 'root';
$db_password = '';
$db_name     = 'tony';

// Cria a conexão com o banco de dados usando MySQLi
$conn = new mysqli($host, $db_username, $db_password, $db_name);

// Verifica se houve algum erro na conexão
if ($conn->connect_error) {
  die("Falha na conexão: " . $conn->connect_error);
}
