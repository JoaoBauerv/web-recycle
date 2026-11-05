<?php 
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}
require_once __DIR__ . '/../../components/csrf.php';
require_once __DIR__ . '/../../functions/material/material_lib.php';

$sql = "SELECT * FROM material WHERE id_material = :id";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':id', (int) $_REQUEST['id'], PDO::PARAM_INT);
$stmt->execute();
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) {
    header('Location: ' . $url_base . '/materiais?msgErro=' . urlencode('Material não encontrado.'));
    exit;
}
?>

<div class="container mt-5">
    <div class="card bg-secondary text-light shadow-lg p-4 rounded-4">
        <h2 class="text-center mb-4">Editar Material</h2>

        <?php require_once __DIR__ . '/../../components/alert.php'; ?>

        <form method="POST" action="<?=$url_base?>/functions/material/registrar.php">
            <div class="mb-3">
                <label class="form-label">Nome do material</label>
                <input type="text" name="nome" class="form-control" placeholder="Ex: Alumínio" maxlength="40" value="<?= htmlspecialchars($material['nm_material']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Categoria material</label>
                <select name="categoria" class="form-select" required>
                    <?php foreach (materialTiposPara($material['tipo']) as $valor => $rotulo): ?>
                        <option value="<?= htmlspecialchars($valor) ?>"
                                <?= $material['tipo'] === $valor ? 'selected' : '' ?>>
                            <?= htmlspecialchars($rotulo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <input type="hidden" value="editar" name="acao">
            <?= csrfCampo() ?>
            <input type="hidden" value="<?= (int) $material['id_material'] ?>" name="id">

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


