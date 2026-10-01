<?php
// Consumido por AJAX pelo FullCalendar: sem sessão precisa responder JSON, não
// o HTML do login.
define('MIDDLEWARE_RESPOSTA_JSON', true);
require_once(__DIR__ . '/../../components/middleware.php');

header('Content-Type: application/json; charset=utf-8');

$stmt = $pdo->prepare("SELECT * FROM tb_evento ORDER BY id");
$stmt->execute();

$eventos = [];

while ($row_events = $stmt->fetch(PDO::FETCH_ASSOC)) {
    extract($row_events);

    $eventos[] = [
        'id'    => $id,
        'title' => $title,
        'color' => $color,
        'start' => $start,
        'end'   => $end,
    ];
}

echo json_encode($eventos);
