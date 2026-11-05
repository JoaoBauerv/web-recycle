<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$url_base = $_ENV['URL_BASE'];

// Cookie de sessão: HttpOnly impede que JavaScript leia o identificador (um
// XSS não consegue roubar a sessão), SameSite=Lax impede que outro site o
// envie em POST, e Secure entra sozinho quando o sistema roda em HTTPS.
// Precisa vir antes de qualquer session_start(), por isso mora aqui: este
// arquivo é o primeiro a ser carregado em todas as entradas do sistema.
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off'),
    ]);
    ini_set('session.use_strict_mode', '1');
}

// A conexão é aberta dentro de uma função para as credenciais não ficarem em
// variáveis globais: antes $usuario e $senha (do banco) vazavam para todo
// arquivo que incluísse este, e esses mesmos nomes são usados para outras
// coisas no resto do sistema.
$pdo = (function (): PDO {
    try {
        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', $_ENV['DB_HOST'], $_ENV['DB_PORT'], $_ENV['DB_NAME']),
            $_ENV['DB_USER'],
            $_ENV['DB_PASS'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec("SET search_path TO reciclagem");
        return $pdo;
    } catch (PDOException $e) {
        // O detalhe (host, usuário, motivo) vai para o log do servidor, não
        // para a tela de quem quer que tenha aberto a página.
        error_log('Falha ao conectar ao banco: ' . $e->getMessage());
        http_response_code(503);
        exit('Sistema temporariamente indisponível: não foi possível conectar ao banco de dados. Avise o administrador.');
    }
})();
