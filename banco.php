<?php

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();


// Pega do .env
$endereco = $_ENV['DB_HOST'];
$porta    = $_ENV['DB_PORT'];
$banco    = $_ENV['DB_NAME'];
$usuario  = $_ENV['DB_USER'];
$senha    = $_ENV['DB_PASS'];
$url_base = $_ENV['URL_BASE'];

try {
    $pdo = new PDO("mysql:host=$endereco;port=$porta;dbname=$banco", $usuario, $senha, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    //echo "Conectado ao banco de dados com sucesso!";
} catch (PDOException $e) {
    //echo "Falha ao conectar ao banco de dados.<br/>";
    die($e->getMessage());
}

?>
