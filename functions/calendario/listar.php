<?php
// Consumido por AJAX pelo FullCalendar: sem sessão precisa responder JSON, não
// o HTML do login.
define('MIDDLEWARE_RESPOSTA_JSON', true);
require_once(__DIR__ . '/../../components/middleware.php');

header('Content-Type: application/json; charset=utf-8');

// A chave do FullCalendar continua sendo 'id': quem muda de nome é a coluna no
// banco, não o contrato do JSON que o front-end consome.
$stmt = $pdo->prepare("SELECT id_evento, title, color, start, \"end\"
                       FROM evento ORDER BY id_evento");
$stmt->execute();

$eventos = [];

while ($e = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $eventos[] = [
        'id'    => $e['id_evento'],
        'title' => $e['title'],
        'color' => $e['color'],
        'start' => $e['start'],
        'end'   => $e['end'],
    ];
}

echo json_encode($eventos);
