<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}
unset($_SESSION['msg_erro']);
unset($_SESSION['msg_sucesso']);

// A rota 'usuarios' já exige Admin no roteador (index2.php). Esta checagem é a
// segunda camada — não remover: o acesso não pode depender só do menu escondido.
if (($dados_usuario['permissao'] ?? '') !== 'Admin') {
    ?>
    <div class="container-fluid py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm text-center p-4 p-md-5">
                    <i class="bi bi-shield-lock display-4 text-danger mb-3" aria-hidden="true"></i>
                    <h2 class="h4 fw-bold mb-2">Acesso negado</h2>
                    <p class="text-muted mb-4">Esta página está disponível apenas para administradores.</p>
                    <a href="<?= $url_base ?>/index2.php" class="btn btn-primary">
                        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Voltar ao início
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
    return;
}

$status = $_REQUEST['status'] ?? '1';
if (!in_array($status, ['1', '0'], true)) {
    $status = '1';
}

$stmt = $pdo->prepare("SELECT * FROM tb_usuario WHERE status = :status ORDER BY nome");
$stmt->execute([':status' => $status]);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_ativos   = (int) $pdo->query("SELECT COUNT(*) FROM tb_usuario WHERE status = 1")->fetchColumn();
$total_inativos = (int) $pdo->query("SELECT COUNT(*) FROM tb_usuario WHERE status = 0")->fetchColumn();
$total_admins   = (int) $pdo->query("SELECT COUNT(*) FROM tb_usuario WHERE status = 1 AND permissao = 'Admin'")->fetchColumn();
?>

<div class="container-fluid py-4" style="max-width: 1400px;">

    <div class="pagina-cabecalho d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h2 class="fw-bold">Administração</h2>
            <p>Gerencie as contas de acesso ao sistema</p>
        </div>
        <a href="<?= $url_base ?>/usuarios/novo" class="btn btn-primary">
            <i class="bi bi-person-plus me-1" aria-hidden="true"></i> Novo usuário
        </a>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <!-- Indicadores -->
    <div class="row g-3 mb-4">
        <div class="col-md-4 col-6">
            <div class="card card-indicador h-100 border-0 shadow-sm">
                <div class="card-body">
                    <span class="indicador-icone"><i class="bi bi-people" aria-hidden="true"></i></span>
                    <div>
                        <span class="indicador-rotulo">Usuários ativos</span>
                        <span class="indicador-valor"><?= $total_ativos ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-6">
            <div class="card card-indicador h-100 border-0 shadow-sm">
                <div class="card-body">
                    <span class="indicador-icone"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
                    <div>
                        <span class="indicador-rotulo">Administradores</span>
                        <span class="indicador-valor"><?= $total_admins ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-6">
            <div class="card card-indicador h-100 border-0 shadow-sm">
                <div class="card-body">
                    <span class="indicador-icone"><i class="bi bi-person-dash" aria-hidden="true"></i></span>
                    <div>
                        <span class="indicador-rotulo">Inativos</span>
                        <span class="indicador-valor"><?= $total_inativos ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de usuários -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h3 class="h6 fw-semibold mb-0">
                <i class="bi bi-person-lines-fill me-2" aria-hidden="true"></i>
                Usuários <?= $status === '1' ? 'ativos' : 'inativos' ?>
            </h3>

            <div class="btn-group btn-group-sm" role="group" aria-label="Filtrar usuários por situação">
                <a href="<?= $url_base ?>/usuarios?status=1"
                   class="btn <?= $status === '1' ? 'btn-primary' : 'btn-outline-secondary' ?>"
                   <?= $status === '1' ? 'aria-current="page"' : '' ?>>
                    Ativos
                </a>
                <a href="<?= $url_base ?>/usuarios?status=0"
                   class="btn <?= $status === '0' ? 'btn-primary' : 'btn-outline-secondary' ?>"
                   <?= $status === '0' ? 'aria-current="page"' : '' ?>>
                    Inativos
                </a>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (empty($usuarios)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-4 text-muted d-block mb-3" aria-hidden="true"></i>
                    <h4 class="h6 text-muted mb-1">Nenhum usuário <?= $status === '1' ? 'ativo' : 'inativo' ?></h4>
                    <p class="text-muted mb-0">Cadastre um novo usuário para começar.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="usuariosTable" class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Nome</th>
                                <th>Usuário</th>
                                <th>E-mail</th>
                                <th>Permissão</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= $url_base ?>/images/user/<?= htmlspecialchars($u['foto'] ?: 'padrao.png') ?>"
                                             alt="" width="34" height="34" class="rounded-circle" style="object-fit: cover;"
                                             onerror="this.src='<?= $url_base ?>/images/user/padrao.png'">
                                        <span class="fw-medium"><?= htmlspecialchars($u['nome']) ?></span>
                                    </div>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($u['usuario']) ?></td>
                                <td class="text-muted"><?= htmlspecialchars($u['email'] ?: '—') ?></td>
                                <td>
                                    <?php if ($u['permissao'] === 'Admin'): ?>
                                        <span class="badge bg-primary-subtle text-primary">
                                            <i class="bi bi-shield-check me-1" aria-hidden="true"></i>Administrador
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary">
                                            <?= htmlspecialchars($u['permissao'] ?: 'Usuário') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="<?= $url_base ?>/usuarios/editar?id=<?= (int) $u['id_usuario'] ?>"
                                       class="btn btn-sm btn-outline-secondary"
                                       aria-label="Editar <?= htmlspecialchars($u['nome']) ?>">
                                        <i class="bi bi-pencil me-1" aria-hidden="true"></i> Editar
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-resetar-senha"
                                            data-id="<?= (int) $u['id_usuario'] ?>"
                                            data-nome="<?= htmlspecialchars($u['nome']) ?>"
                                            aria-label="Resetar senha de <?= htmlspecialchars($u['nome']) ?>">
                                        <i class="bi bi-key me-1" aria-hidden="true"></i> Resetar senha
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Confirmação de reset de senha (substitui o confirm() do navegador) -->
<div class="modal fade" id="modalResetarSenha" tabindex="-1" aria-labelledby="tituloResetarSenha" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tituloResetarSenha">Resetar senha?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">
                    A senha de <strong id="nomeResetarSenha"></strong> será substituída por uma senha temporária.
                </p>
                <p class="text-muted mb-0 small">
                    No próximo acesso o usuário será obrigado a definir uma nova senha.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="confirmarResetarSenha" class="btn btn-primary">
                    <i class="bi bi-key me-1" aria-hidden="true"></i> Resetar senha
                </a>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    if ($('#usuariosTable').length) {
        $('#usuariosTable').DataTable({
        "autoWidth": false, // sem isto o DataTables grava um width inline e a tabela encolhe
            "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json" },
            "pageLength": 10,
            "lengthMenu": [10, 25, 50],
            "order": [[0, "asc"]],
            "columnDefs": [{ "orderable": false, "targets": [4] }]
        });
    }

    var modal = new bootstrap.Modal(document.getElementById('modalResetarSenha'));

    $(document).on('click', '.btn-resetar-senha', function () {
        var id = $(this).data('id');
        $('#nomeResetarSenha').text($(this).data('nome'));
        $('#confirmarResetarSenha').attr('href', '<?= $url_base ?>/functions/user/resetarsenha.php?id=' + id);
        modal.show();
    });
});
</script>
