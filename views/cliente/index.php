<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}
$sql = "SELECT * FROM clientes WHERE status = 1 ORDER BY nome ASC";
$stmt = $pdo->query($sql);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <div class="card bg-secondary text-light shadow-lg p-4 rounded-4">
        <h2 class="text-center mb-4"><i class="bi bi-person-lines-fill"></i> Clientes Cadastrados</h2>

        <?php require_once __DIR__ . '/../../components/alert.php'; ?>

        <?php if (count($clientes) > 0): ?>
            <div class="table-responsive">
                <table id="clientesTable" class="table table-dark table-hover align-middle text-center rounded-3 overflow-hidden">
                    <thead class="table-primary text-dark">
                        <tr>
                            <th>Nome</th>
                            <th>CPF/CNPJ</th>
                            <th>Telefone</th>
                            <th>Cidade/UF</th>
                            <th>Preço Especial</th>
                            <?php if (($_SESSION['permissao'] ?? '') === 'Admin'): ?>
                            <th>Ações</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($clientes as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['nome']) ?></td>
                            <td><?= htmlspecialchars($c['cpf_cnpj'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($c['telefone'] ?: $c['celular'] ?: '-') ?></td>
                            <td><?= htmlspecialchars(trim(($c['cidade'] ?? '') . '/' . ($c['estado'] ?? ''))) ?></td>
                            <td><?= $c['preco_especial'] ? '<span class="badge bg-success">Sim</span>' : '<span class="badge bg-secondary">Não</span>' ?></td>
                            <?php if (($_SESSION['permissao'] ?? '') === 'Admin'): ?>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="<?=$url_base?>/clientes/editar?id=<?= $c['id_cliente'] ?>"
                                       class="btn btn-warning btn-sm"
                                       title="Editar Cliente">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <a href="<?=$url_base?>/functions/cliente/registrar.php?id=<?= $c['id_cliente'] ?>&acao=excluir"
                                       onclick="return confirm('Tem certeza que deseja excluir este cliente?')"
                                       class="btn btn-danger btn-sm"
                                       title="Excluir Cliente">
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
            <div class="alert alert-warning text-center"><i class="bi bi-exclamation-triangle-fill"></i> Nenhum cliente cadastrado.</div>
        <?php endif; ?>

        <?php if (($_SESSION['permissao'] ?? '') === 'Admin'): ?>
        <div class="d-flex justify-content-between mt-3">
            <a href="<?=$url_base?>/clientes/novo" class="btn btn-success">
                <i class="fas fa-plus"></i> Novo Cliente
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#clientesTable').DataTable({
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
