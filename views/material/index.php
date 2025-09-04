<?php 
include '../../components/sidebar.php'; 
require_once (__DIR__ . '/../../components/middleware.php');

$sql = "SELECT * FROM tb_material WHERE status = 1 ORDER BY nm_material ASC";
$stmt = $pdo->query($sql);
$materiais = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <div class="card bg-secondary text-light shadow-lg p-4 rounded-4">
        <h2 class="text-center mb-4">📦 Materiais Cadastrados</h2>

        <?php require_once '../../components/alert.php'; ?>

        <?php if (count($materiais) > 0): ?>
            <table class="table table-dark table-hover align-middle text-center rounded-3 overflow-hidden">
                <thead class="table-primary text-dark">
                    <tr>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Preço Normal</th>
                        <th>Preço Especial</th>
                        <th>Estoque</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($materiais as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nm_material']) ?></td>
                        <td><?= htmlspecialchars($p['tipo']) ?></td>
                        <td><?= number_format($p['preco_compra'], 2, ',', '.') ?></td>
                        <td><?= number_format($p['preco_especial'], 2, ',', '.') ?> </td>
                        <td><?= $p['qt_estoque'] ?></td>
                        <td>
                            <a href="edit.php?id=<?=$p['id_material']?>" class="btn btn-warning btn-sm">Editar</a>
                            <a href="<?=$url_base?>/functions/material/registrar.php?id=<?=$p['id_material']?>&acao=excluir" 
                               onclick="return confirm('Tem certeza que deseja excluir este produto?')" 
                               class="btn btn-danger btn-sm">Excluir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-warning text-center">⚠️ Nenhum produto cadastrado.</div>
        <?php endif; ?>

        <div class="d-flex justify-content-between mt-3">
            <a href="create.php" class="btn btn-success">➕ Novo Material</a>
        </div>
    </div>
</div>