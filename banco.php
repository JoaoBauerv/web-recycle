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
//
// Com DATABASE_URL_UNPOOLED (ou DATABASE_URL) no .env -- o que o `neon init`
// grava --, a conexão vai para o Neon; sem elas, cai nas variáveis DB_* de um
// PostgreSQL local. A URL direta (unpooled) vem primeiro porque o pooler do
// Neon trabalha em modo transação e não preserva o SET search_path abaixo
// entre uma transação e outra.
$pdo = (function (): PDO {
    try {
        $url = $_ENV['DATABASE_URL_UNPOOLED'] ?? $_ENV['DATABASE_URL'] ?? '';
        if ($url !== '') {
            $p = parse_url($url);
            parse_str($p['query'] ?? '', $query);
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
                $p['host'],
                $p['port'] ?? 5432,
                ltrim($p['path'] ?? '', '/'),
                $query['sslmode'] ?? 'require'
            );
            // O libpq que acompanha o PHP do XAMPP é antigo e não envia SNI,
            // que o Neon usa para saber qual endpoint atender; sem SNI, o ID
            // do endpoint (primeiro pedaço do host) vai em `options`.
            if (str_ends_with($p['host'], '.neon.tech')) {
                $endpoint = preg_replace('/-pooler$/', '', explode('.', $p['host'])[0]);
                $dsn .= ";options='endpoint=$endpoint'";
            }
            $user = rawurldecode($p['user'] ?? '');
            $pass = rawurldecode($p['pass'] ?? '');
        } else {
            $dsn  = sprintf('pgsql:host=%s;port=%s;dbname=%s', $_ENV['DB_HOST'], $_ENV['DB_PORT'], $_ENV['DB_NAME']);
            $user = $_ENV['DB_USER'];
            $pass = $_ENV['DB_PASS'];
        }
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
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
