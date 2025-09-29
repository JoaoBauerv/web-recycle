<?php 
require_once(__DIR__ . '/../../banco.php');

$dados = filter_input_array(INPUT_POST, FILTER_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO tb_evento (title, start, \"end\") VALUES (:title, :start, :end)");

$stmt->bindParam(':title', $dados['cad_title']);
$stmt->bindParam(':start', $dados['cad_start']);
$stmt->bindParam(':end', $dados['cad_end']);

if($stmt->execute()){
    $return = ['status'=> true , 'msg' =>'Evento cadastrado com sucesso!'];

}else{
    $return = ['status'=> false , 'msg' =>'Erro: Evento não cadastrado!'];
}


echo json_encode($return);
?>