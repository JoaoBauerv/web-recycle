<?php 
require_once (__DIR__ . '/../../components/middleware.php');
include '../../components/sidebar.php'; 

?>

<div class="container mt-5">
    <div class="card bg-secondary text-light shadow-lg p-4 rounded-4">
        <h2 class="text-center mb-4">Cadastro de Material</h2>

        <!-- <?php if ($mensagem): ?>
            <div class="alert alert-info"><?= $mensagem ?></div>
        <?php endif; ?> -->

        <form method="POST" action="<?=$url_base?>/functions/material/registrar.php">
            <div class="mb-3">
                <label class="form-label">Nome do material</label>
                <input type="text" name="nome" class="form-control" placeholder="Ex: Alumínio" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Categoria material</label>
                <select name="categoria" class="form-select" required>
                    <option value="">Selecione</option>
                    <option value="Aluminio">Alumínio</option>
                    <option value="Cobre">Cobre</option>
                    <option value="Plastico">Plástico</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>

            <input type="hidden" value="cadastrar" name="acao">

            <div class="mb-3">
                <label class="form-label">Preço Normal</label>
                <input type="number" step="0.01" name="preco_compra" class="form-control" placeholder="Ex: 10" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Preço Especial</label>
                <input type="number" step="0.01" name="preco_especial" class="form-control" placeholder="Ex: 10" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">Confirmar</button>
        </form>
    </div>
</div>


