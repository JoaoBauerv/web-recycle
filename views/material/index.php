<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}
require_once __DIR__ . '/../../components/csrf.php';
require_once __DIR__ . '/../../components/permissoes.php';

// Consultar a lista é liberado; os botões de manutenção só aparecem para quem
// o endpoint vai deixar executar a ação, senão o usuário clica e leva erro.
$pode_gerenciar = usuarioPode('material.gerenciar');

$sql = "SELECT * FROM tb_material WHERE status = 1 ORDER BY nm_material ASC";
$stmt = $pdo->query($sql);
$materiais = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4" style="max-width: 1400px;">
    <div class="card bg-secondary text-light shadow-lg p-4 rounded-4">
        <h2 class="text-center mb-4"><i class="bi bi-box-seam-fill"></i> Materiais Cadastrados</h2>

        <?php require_once __DIR__ . '/../../components/alert.php'; ?>

        <?php if (count($materiais) > 0): ?>
            <div class="table-responsive">
                <table id="materiaisTable" class="table table-dark table-hover align-middle text-center rounded-3 overflow-hidden">
                    <thead class="table-primary text-dark">
                        <tr>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Preço Normal <i class="bi bi-cash"></i></th>
                            <th>Preço Especial <i class="bi bi-cash-coin"></i></th>
                            <th>Estoque</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($materiais as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['nm_material']) ?></td>
                            <td><?= htmlspecialchars($p['tipo']) ?></td>
                            <td data-order="<?= $p['preco_compra'] ?>">R$ <?= number_format($p['preco_compra'], 2, ',', '.') ?></td>
                            <td data-order="<?= $p['preco_especial'] ?>">R$ <?= number_format($p['preco_especial'], 2, ',', '.') ?></td>
                            <td><?= $p['qt_estoque'] ?></td>
                            <td>
                                <?php if ($pode_gerenciar): ?>
                                <!-- d-flex em vez de btn-group: o excluir virou <form>,
                                     e o btn-group só alinha botões irmãos diretos. -->
                                <div class="d-flex gap-1">
                                    <a href="<?=$url_base?>/materiais/editar?id=<?=$p['id_material']?>"
                                       class="btn btn-warning btn-sm"
                                       title="Editar Material">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <!-- POST, e não link: excluir altera dados, e por GET
                                         a ação anda sem token e pode ser disparada por
                                         pré-carregamento do navegador. -->
                                    <form method="POST" action="<?=$url_base?>/functions/material/registrar.php"
                                          class="d-inline"
                                          onsubmit="return confirm('Tem certeza que deseja excluir este material?')">
                                        <?= csrfCampo() ?>
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?=$p['id_material']?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Excluir Material">
                                            <i class="fas fa-trash"></i> Excluir
                                        </button>
                                    </form>
                                </div>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center"><i class="bi bi-exclamation-triangle-fill"></i> Nenhum material cadastrado.</div>
        <?php endif; ?>

        <div class="d-flex justify-content-between mt-3">
            <?php if ($pode_gerenciar): ?>
                <a href="<?=$url_base?>/materiais/novo" class="btn btn-success">
                    <i class="fas fa-plus"></i> Novo Material
                </a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#imprimirModal">
                <i class="bi bi-envelope-paper"></i> Imprimir tabela de preços
            </button>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="imprimirModal" tabindex="-1" aria-labelledby="imprimirModal" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="exampleModalLabel">Imprimessão preços</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form action="<?=$url_base?>/views/material/pdf.php" method="POST" target="_blank">
            <?= csrfCampo() ?>
            <label>Selecione o tipo de preço:</label>
                <div>
                    <input type="radio" id="normal" name="preco" value="normal" checked />
                    <label for="normal">Normal</label>
                </div>

                <div>
                    <input type="radio" id="especial" name="preco" value="especial" />
                    <label for="especial">Especial</label>
                </div>

                <div>
                    <select class="form-select mt-2" name="tipo" id="tipo">
                        <option value="todos">Todos</option>
                        <?php
                        $sql = "SELECT DISTINCT tipo FROM tb_material WHERE status = 1 ORDER BY tipo ASC";
                        $stmt = $pdo->query($sql);
                        $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($tipos as $t):
                            echo '<option value="'.htmlspecialchars($t['tipo']).'">'.htmlspecialchars($t['tipo']).'</option>';
                        endforeach;
                        ?>
                    </select>
                </div>
        
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        <button type="submit" class="btn btn-primary">Imprimir</button>
        </form>
      </div>
    </div>
  </div>
</div>

</style>

<script>
$(document).ready(function() {
    $('#materiaisTable').DataTable({
        "autoWidth": false, // sem isto o DataTables grava um width inline e a tabela encolhe
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json"
        },
        "pageLength": 5,
        "lengthMenu": [5, 10, 25, 50, 100],
        "order": [[3, "desc"]],
        "responsive": true,
        "dom": "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
               "<'row'<'col-sm-12'tr>>" +
               "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        "columnDefs": [
            { "orderable": true, "targets": [0, 1, 2, 3] },
            { "searchable": true, "targets": [0, 3] },
            { "className": "text-center", "targets": [1, 2] }
        ],
        "drawCallback": function() {
            // Adiciona animação suave após cada redraw
            $('tbody tr').css('opacity', '0').animate({ opacity: 1 }, 300);
        }
    });

    // Animação de entrada
    $('tbody tr').css('opacity', '0').each(function(i) {
        $(this).delay(i * 50).animate({ opacity: 1 }, 300);
    });
});
</script>