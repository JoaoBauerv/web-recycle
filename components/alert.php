
<?php

if (isset($_GET['msgSucesso'])) {
$mensagem = htmlspecialchars($_GET['msgSucesso']);
echo "<div class='alert alert-success mx-auto w-100 text-center'>$mensagem</div>";
}

if (isset($_GET['msgErro'])) {
$mensagem = htmlspecialchars($_GET['msgErro']);
echo "<div class='alert alert-danger mx-auto w-100 text-center'>$mensagem</div>";
} 

if (isset($_GET['msgAviso'])) {
$mensagem = htmlspecialchars($_GET['msgAviso']);
echo "<div class='alert alert-primary mx-auto w-100 text-center'>$mensagem</div>";
} 

if (isset($_GET['msg_sucesso'])) {
$mensagem = htmlspecialchars($_GET['msg_sucesso']);
echo "<div class='alert alert-success mx-auto w-100 text-center'>$mensagem</div>";
}
?>  
