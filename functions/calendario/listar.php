<?php 
require_once(__DIR__ . '/../../banco.php');

$stmt = $pdo->prepare("SELECT * FROM tb_evento ORDER BY id");
$stmt->execute();

$eventos = [];

while($row_events = $stmt->fetch(PDO::FETCH_ASSOC)){

    extract($row_events);

    $eventos[] = [
        'id' => $id,
        'title' => $title,
        'color' => $color,
        'start' => $start,
        'end' => $end,

    ];

}

echo json_encode($eventos);
?>