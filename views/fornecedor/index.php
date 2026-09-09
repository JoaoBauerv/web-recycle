<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}
$sql = "SELECT * FROM fornecedores WHERE status = 1 ORDER BY nome_razao_social ASC";
$stmt = $pdo->query($sql);
$fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <div class="card bg-secondary text-light shadow-lg p-4 rounded-4">
        <h2 class="text-center mb-4"><i class="bi bi-truck"></i> Fornecedores Cadastrados</h2>

        <?php require_once __DIR__ . '/../../components/alert.php'; ?>

        <?php if (count($fornecedores) > 0): ?>
            <div class="table-responsive">
                <table id="fornecedoresTable" class="table table-dark table-hover align-middle text-center rounded-3 overflow-hidden">
                    <thead class="table-primary text-dark">
                        <tr>
                            <th>Nome / Razão Social</th>
                            <th>CNPJ/CPF</th>
                            <th>Telefone</th>
                            <th>Cidade/UF</th>
                            <th>E-mail</th>
                            <?php if (($_SESSION['permissao'] ?? '') === 'Admin'): ?>
                            <th>Ações</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($fornecedores as $f): ?>
                        <tr>
                            <td><?= htmlspecialchars($f['nome_razao_social']) ?></td>
                            <td><?= htmlspecialchars($f['cnpj_cpf'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($f['telefone'] ?: '-') ?></td>
                            <td><?= htmlspecialchars(trim(($f['cidade'] ?? '') . '/' . ($f['estado'] ?? ''))) ?></td>
                            <td><?= htmlspecialchars($f['email'] ?: '-') ?></td>
                            <?php if (($_SESSION['permissao'] ?? '') === 'Admin'): ?>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="<?=$url_base?>/fornecedores/editar?id=<?= $f['id_fornecedor'] ?>"
                                       class="btn btn-warning btn-sm"
                                       title="Editar Fornecedor">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <a href="<?=$url_base?>/functions/fornecedor/registrar.php?id=<?= $f['id_fornecedor'] ?>&acao=excluir"
                                       onclick="return confirm('Tem certeza que deseja excluir este fornecedor?')"
                                       class="btn btn-danger btn-sm"
                                       title="Excluir Fornecedor">
                                        <i class="fas fa-trash"></i> Excluir
                                    </a>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center"><i class="bi bi-exclamation-triangle-fill"></i> Nenhum fornecedor cadastrado.</div>
        <?php endif; ?>

        <?php if (($_SESSION['permissao'] ?? '') === 'Admin'): ?>
        <div class="d-flex justify-content-between mt-3">
            <a href="<?=$url_base?>/fornecedores/novo" class="btn btn-success">
                <i class="fas fa-plus"></i> Novo Fornecedor
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#fornecedoresTable').DataTable({
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json"
        },
        "pageLength": 10,
        "lengthMenu": [5, 10, 25, 50, 100],
        "order": [[0, "asc"]],
        "responsive": true,
        "dom": "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
               "<'row'<'col-sm-12'tr>>" +
               "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
    });
});
</script>
