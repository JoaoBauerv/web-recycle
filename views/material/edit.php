<?php 
include '../../components/sidebar.php'; 
require_once (__DIR__ . '/../../components/middleware.php');

$sql = "SELECT * FROM tb_material WHERE id_material = ".$_REQUEST['id']."";
$stmt = $pdo->query($sql);
$material = $stmt->fetch(PDO::FETCH_ASSOC);

// var_dump($material);
?>

<div class="container mt-5">
    <div class="card bg-secondary text-light shadow-lg p-4 rounded-4">
        <h2 class="text-center mb-4">Editar Material</h2>

        <!-- <?php if ($mensagem): ?>
            <div class="alert alert-info"><?= $mensagem ?></div>
        <?php endif; ?> -->

        <form method="POST" action="<?=$url_base?>/functions/material/registrar.php">
            <div class="mb-3">
                <label class="form-label">Nome do material</label>
                <input type="text" name="nome" class="form-control" placeholder="Ex: Alumínio" value="<?=$material['nm_material']?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Categoria material</label>
                <select name="categoria" class="form-select" required>
                    <option value="Aluminio" <?= ($material['tipo'] == 'Aluminio')? 'Selectd' : '' ?>>Alumínio</option>
                    <option value="Cobre" <?= ($material['tipo'] == 'Outro')? 'Cobre' : '' ?>>Cobre</option>
                    <option value="Plastico" <?= ($material['tipo'] == 'Outro')? 'Plastico' : '' ?>>Plástico</option>
                    <option value="Outro" <?= ($material['tipo'] == 'Outro')? 'Selectd' : '' ?>>Outro</option>
                </select>
            </div>

            <input type="hidden" value="editar" name="acao">
            <input type="hidden" value="<?=$_REQUEST['id']?>" name ="id">

            <div class="mb-3">
                <label class="form-label">Preço Normal</label>
                <input type="number" step="0.01" name="preco_compra" class="form-control" placeholder="Ex: 10" value="<?= $material['preco_compra'] ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Preço Especial</label>
                <input type="number" step="0.01" name="preco_especial" class="form-control" placeholder="Ex: 12" value="<?= $material['preco_especial'] ?>" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">Confirmar</button>
        </form>
    </div>
</div>


