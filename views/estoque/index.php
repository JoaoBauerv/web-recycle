<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

require_once __DIR__ . '/../../components/permissoes.php';
require_once __DIR__ . '/../../functions/estoque/estoque_lib.php';

exigirPermissao('estoque.visualizar', $url_base);

$pode_ajustar = usuarioPode('estoque.ajustar');
$acoes = $url_base . '/functions/estoque/ajustar.php';

$materiais = estoquePosicao($pdo);
$abaixo_do_minimo = estoqueAbaixoDoMinimo($pdo);

// Indicadores. O valor imobilizado usa preco_compra: é quanto de dinheiro está
// parado no pátio. preco_venda não serve aqui — ele é o último preço praticado,
// não uma tabela, e nem todo material já foi vendido.
$com_saldo = $zerados = $inconsistentes = 0;
$peso_total = $valor_imobilizado = 0.0;

foreach ($materiais as $m) {
    if ($m['situacao'] === 'inconsistente') { $inconsistentes++; }
    elseif ($m['saldo'] == 0.0)             { $zerados++; }
    else                                     { $com_saldo++; }

    $peso_total += $m['saldo'];
    $valor_imobilizado += $m['valor_imobilizado'];
}
?>

<div class="container-fluid py-4" style="max-width: 1400px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="mb-1 fw-bold">
                <i class="bi bi-boxes me-2" style="color: var(--color-accent);" aria-hidden="true"></i>Estoque
            </h2>
            <p class="text-muted mb-0">Posição atual e histórico de cada material</p>
        </div>
        <?php if ($pode_ajustar): ?>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalInventario">
                    <i class="bi bi-clipboard-check me-1" aria-hidden="true"></i> Contagem
                </button>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjuste">
                    <i class="bi bi-plus-slash-minus me-1" aria-hidden="true"></i> Lançamento manual
                </button>
            </div>
        <?php endif; ?>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <!-- Indicadores -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card card-indicador h-100 border-0 shadow-sm">
                <div class="card-body">
                    <span class="indicador-icone"><i class="bi bi-box-seam" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <span class="indicador-rotulo">Materiais com saldo</span>
                        <span class="indicador-valor"><?= $com_saldo ?></span>
                        <small class="text-muted"><?= $zerados ?> zerado(s) de <?= count($materiais) ?></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-indicador h-100 border-0 shadow-sm">
                <div class="card-body">
                    <span class="indicador-icone"><i class="bi bi-speedometer2" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <span class="indicador-rotulo">Peso em estoque</span>
                        <span class="indicador-valor"><?= number_format($peso_total, 2, ',', '.') ?> kg</span>
                        <small class="text-muted">soma de todos os materiais</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-indicador h-100 border-0 shadow-sm">
                <div class="card-body">
                    <span class="indicador-icone"><i class="bi bi-cash-stack" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <span class="indicador-rotulo">Valor imobilizado</span>
                        <span class="indicador-valor" style="color: var(--color-accent);">
                            R$ <?= number_format($valor_imobilizado, 2, ',', '.') ?>
                        </span>
                        <small class="text-muted">saldo × preço de compra</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-indicador h-100 border-0 shadow-sm">
                <div class="card-body">
                    <span class="indicador-icone"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <span class="indicador-rotulo">Abaixo do mínimo</span>
                        <span class="indicador-valor" <?= $abaixo_do_minimo ? 'style="color:#d97706;"' : '' ?>>
                            <?= count($abaixo_do_minimo) ?>
                        </span>
                        <small class="text-muted">
                            <?php
                            $com_minimo = count(array_filter($materiais, fn($m) => $m['estoque_minimo'] !== null));
                            echo $com_minimo > 0
                                ? $com_minimo . ' material(is) com mínimo definido'
                                : 'nenhum mínimo configurado ainda';
                            ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerta de estoque baixo -->
    <?php if ($abaixo_do_minimo): ?>
        <div class="card border-0 shadow-sm mb-4" style="border-left: 4px solid #d97706 !important;">
            <div class="card-header bg-white border-bottom">
                <h3 class="h6 fw-semibold mb-0">
                    <i class="bi bi-exclamation-triangle me-2" style="color:#d97706;" aria-hidden="true"></i>
                    Materiais no limite ou abaixo do mínimo
                </h3>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <?php foreach ($abaixo_do_minimo as $b):
                        $minimo = (float) $b['estoque_minimo'];
                        $saldo = (float) $b['saldo'];
                        $percentual = $minimo > 0 ? min(($saldo / $minimo) * 100, 100) : 0;
                    ?>
                        <div class="col-md-6 col-xl-4">
                            <a href="<?= $url_base ?>/estoque/extrato?id=<?= (int) $b['id_material'] ?>"
                               class="d-block border rounded-3 p-3 text-decoration-none text-body h-100">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <strong class="text-truncate"><?= htmlspecialchars($b['nm_material']) ?></strong>
                                    <span class="badge bg-warning-subtle text-warning-emphasis flex-shrink-0">
                                        <?= number_format($saldo, 2, ',', '.') ?> / <?= number_format($minimo, 2, ',', '.') ?>
                                    </span>
                                </div>
                                <div class="progress mt-2" style="height:6px;">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: <?= $percentual ?>%; background-color:#d97706;"
                                         aria-valuenow="<?= round($percentual) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <small class="text-muted">
                                    <?= $saldo == 0.0
                                        ? 'Sem estoque'
                                        : 'Faltam ' . number_format(max($minimo - $saldo, 0), 2, ',', '.') . ' ' . htmlspecialchars($b['unidade_medida']) . ' para o mínimo' ?>
                                </small>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Posição -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h3 class="h6 fw-semibold mb-0">
                <i class="bi bi-list-ul me-2" aria-hidden="true"></i>Posição por material
            </h3>
            <div class="btn-group btn-group-sm" role="group" aria-label="Filtrar por situação">
                <button type="button" class="btn btn-outline-secondary active" data-filtro="">Todos</button>
                <button type="button" class="btn btn-outline-secondary" data-filtro="normal">Em estoque</button>
                <button type="button" class="btn btn-outline-secondary" data-filtro="baixo">Baixo</button>
                <button type="button" class="btn btn-outline-secondary" data-filtro="zerado">Zerados</button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="estoqueTable" class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Material</th>
                            <th>Tipo</th>
                            <th class="text-end">Saldo</th>
                            <th class="text-end">Mínimo</th>
                            <th class="text-end">Preço compra</th>
                            <th class="text-end">Valor imobilizado</th>
                            <th>Última movimentação</th>
                            <th>Situação</th>
                            <th class="text-end pe-4">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($materiais as $m):
                        [$classe, $icone, $rotulo] = ESTOQUE_SELOS[$m['situacao']];
                    ?>
                        <tr data-situacao="<?= $m['situacao'] ?>">
                            <td class="ps-4 fw-medium"><?= htmlspecialchars($m['nm_material']) ?></td>
                            <td><small class="text-muted"><?= htmlspecialchars($m['tipo']) ?></small></td>

                            <td class="text-end fw-semibold" data-order="<?= $m['saldo'] ?>">
                                <?= number_format($m['saldo'], 2, ',', '.') ?>
                                <small class="text-muted"><?= htmlspecialchars($m['unidade_medida']) ?></small>
                            </td>

                            <td class="text-end" data-order="<?= $m['estoque_minimo'] ?? -1 ?>">
                                <?php if ($pode_ajustar): ?>
                                    <form method="POST" action="<?= $acoes ?>" class="d-inline-flex gap-1 justify-content-end">
                                        <input type="hidden" name="acao" value="minimo">
                                        <input type="hidden" name="id_material" value="<?= (int) $m['id_material'] ?>">
                                        <input type="text" name="estoque_minimo" class="form-control form-control-sm text-end"
                                               style="width:80px;" inputmode="decimal"
                                               value="<?= $m['estoque_minimo'] !== null ? number_format($m['estoque_minimo'], 2, ',', '') : '' ?>"
                                               placeholder="—"
                                               aria-label="Estoque mínimo de <?= htmlspecialchars($m['nm_material']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Salvar mínimo">
                                            <i class="bi bi-check-lg" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <?= $m['estoque_minimo'] !== null ? number_format($m['estoque_minimo'], 2, ',', '.') : '<span class="text-muted">—</span>' ?>
                                <?php endif; ?>
                            </td>

                            <td class="text-end" data-order="<?= (float) $m['preco_compra'] ?>">
                                R$ <?= number_format((float) $m['preco_compra'], 2, ',', '.') ?>
                            </td>

                            <td class="text-end fw-semibold" style="color: var(--color-accent);"
                                data-order="<?= $m['valor_imobilizado'] ?>">
                                R$ <?= number_format($m['valor_imobilizado'], 2, ',', '.') ?>
                            </td>

                            <td data-order="<?= $m['ultima_movimentacao'] ? strtotime($m['ultima_movimentacao']) : 0 ?>">
                                <?php if ($m['ultima_movimentacao']): ?>
                                    <?= date('d/m/Y', strtotime($m['ultima_movimentacao'])) ?>
                                    <small class="text-muted d-block">
                                        <?= (int) $m['total_movimentacoes'] ?> movimentação(ões)
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">nunca movimentado</span>
                                <?php endif; ?>
                            </td>

                            <td><span class="badge <?= $classe ?>"><i class="bi <?= $icone ?> me-1" aria-hidden="true"></i><?= $rotulo ?></span></td>

                            <td class="text-end pe-4">
                                <a href="<?= $url_base ?>/estoque/extrato?id=<?= (int) $m['id_material'] ?>"
                                   class="btn btn-sm btn-outline-secondary"
                                   aria-label="Ver extrato de <?= htmlspecialchars($m['nm_material']) ?>">
                                    <i class="bi bi-clock-history me-1" aria-hidden="true"></i> Extrato
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($pode_ajustar): ?>
    <?php
    // Os dois modais compartilham a mesma lista de materiais ativos.
    $materiais_select = array_map(fn($m) => [
        'id' => (int) $m['id_material'],
        'nome' => $m['nm_material'],
        'saldo' => $m['saldo'],
        'unidade' => $m['unidade_medida'],
    ], $materiais);
    ?>

    <!-- Lançamento manual -->
    <div class="modal fade" id="modalAjuste" tabindex="-1" aria-labelledby="tituloAjuste" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="<?= $acoes ?>">
                    <input type="hidden" name="acao" value="movimentar">
                    <input type="hidden" name="voltar_para" value="index">

                    <div class="modal-header">
                        <h5 class="modal-title" id="tituloAjuste">
                            <i class="bi bi-plus-slash-minus me-2" aria-hidden="true"></i>Lançamento manual
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>

                    <div class="modal-body">
                        <p class="text-muted small">
                            Use quando o saldo muda sem compra nem venda por trás. O lançamento fica
                            no extrato do material com o seu nome e o motivo.
                        </p>

                        <div class="mb-3">
                            <label for="ajuste-material" class="form-label">Material <span class="text-danger">*</span></label>
                            <select name="id_material" id="ajuste-material" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($materiais_select as $m): ?>
                                    <option value="<?= $m['id'] ?>" data-saldo="<?= $m['saldo'] ?>" data-unidade="<?= htmlspecialchars($m['unidade']) ?>">
                                        <?= htmlspecialchars($m['nome']) ?>
                                        — <?= number_format($m['saldo'], 2, ',', '.') ?> <?= htmlspecialchars($m['unidade']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted" id="ajuste-saldo">Saldo atual aparece aqui.</small>
                        </div>

                        <div class="mb-3">
                            <span class="form-label d-block">Tipo <span class="text-danger">*</span></span>
                            <?php foreach (ESTOQUE_TIPOS_MANUAIS as $valor => $info): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo"
                                           id="tipo-<?= $valor ?>" value="<?= $valor ?>" required
                                           <?= $valor === 'entrada' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="tipo-<?= $valor ?>">
                                        <i class="bi <?= $info['icone'] ?> me-1" aria-hidden="true"></i>
                                        <?= $info['rotulo'] ?>
                                        <small class="text-muted d-block"><?= $info['ajuda'] ?></small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mb-3">
                            <label for="ajuste-quantidade" class="form-label">Quantidade <span class="text-danger">*</span></label>
                            <input type="text" name="quantidade" id="ajuste-quantidade" class="form-control"
                                   inputmode="decimal" placeholder="0,00" required>
                        </div>

                        <div class="mb-2">
                            <label for="ajuste-motivo" class="form-label">Motivo <span class="text-danger">*</span></label>
                            <input type="text" name="observacoes" id="ajuste-motivo" class="form-control"
                                   maxlength="200" required placeholder="Ex: fardo molhado na chuva">
                            <small class="form-text text-muted">
                                Obrigatório: é o que explica o saldo em uma auditoria futura.
                            </small>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Lançar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Contagem de inventário -->
    <div class="modal fade" id="modalInventario" tabindex="-1" aria-labelledby="tituloInventario" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="<?= $acoes ?>">
                    <input type="hidden" name="acao" value="inventariar">
                    <input type="hidden" name="voltar_para" value="index">

                    <div class="modal-header">
                        <h5 class="modal-title" id="tituloInventario">
                            <i class="bi bi-clipboard-check me-2" aria-hidden="true"></i>Contagem de inventário
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>

                    <div class="modal-body">
                        <p class="text-muted small">
                            Informe o que você contou no pátio. O sistema calcula a diferença e lança
                            só o que falta ou sobra — se bater, nada é gravado.
                        </p>

                        <div class="mb-3">
                            <label for="inv-material" class="form-label">Material <span class="text-danger">*</span></label>
                            <select name="id_material" id="inv-material" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($materiais_select as $m): ?>
                                    <option value="<?= $m['id'] ?>" data-saldo="<?= $m['saldo'] ?>" data-unidade="<?= htmlspecialchars($m['unidade']) ?>">
                                        <?= htmlspecialchars($m['nome']) ?>
                                        — <?= number_format($m['saldo'], 2, ',', '.') ?> <?= htmlspecialchars($m['unidade']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="inv-contado" class="form-label">Saldo contado <span class="text-danger">*</span></label>
                            <input type="text" name="saldo_contado" id="inv-contado" class="form-control"
                                   inputmode="decimal" placeholder="0,00" required>
                            <small class="form-text text-muted" id="inv-diferenca">
                                A diferença para o sistema aparece aqui.
                            </small>
                        </div>

                        <div class="mb-2">
                            <label for="inv-motivo" class="form-label">Observação</label>
                            <input type="text" name="observacoes" id="inv-motivo" class="form-control"
                                   maxlength="200" placeholder="Ex: contagem mensal do pátio 2">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Lançar contagem</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
$(function () {
    const tabela = $('#estoqueTable').DataTable({
        "autoWidth": false,
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json" },
        "pageLength": 25,
        "lengthMenu": [10, 25, 50, 100],
        "order": [[5, "desc"]],
        "columnDefs": [{ "orderable": false, "targets": [3, 8] }]
    });

    // Filtro por situação: lê o data-situacao da linha, não o texto do selo,
    // para não depender de como o rótulo está escrito.
    let situacaoAtiva = '';
    $.fn.dataTable.ext.search.push(function (settings, dados, indice, linha, contador) {
        if (settings.nTable.id !== 'estoqueTable' || situacaoAtiva === '') { return true; }
        return $(tabela.row(contador).node()).data('situacao') === situacaoAtiva;
    });

    $('[data-filtro]').on('click', function () {
        situacaoAtiva = $(this).data('filtro');
        $('[data-filtro]').removeClass('active');
        $(this).addClass('active');
        tabela.draw();
    });

    $('#ajuste-material, #inv-material').select2({
        width: '100%',
        language: 'pt-BR',
        dropdownParent: $('#modalAjuste, #modalInventario').first()
    });

    function saldoSelecionado(select) {
        const opcao = select.options[select.selectedIndex];
        if (!select.value || !opcao) { return null; }
        return {
            saldo: parseFloat(opcao.getAttribute('data-saldo')) || 0,
            unidade: opcao.getAttribute('data-unidade') || 'kg'
        };
    }

    function formatar(valor) {
        return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    const ajusteMaterial = document.getElementById('ajuste-material');
    if (ajusteMaterial) {
        $(ajusteMaterial).on('change', function () {
            const dados = saldoSelecionado(this);
            document.getElementById('ajuste-saldo').textContent = dados
                ? 'Saldo atual: ' + formatar(dados.saldo) + ' ' + dados.unidade
                : 'Saldo atual aparece aqui.';
        });
    }

    // Mostra a diferença da contagem antes de enviar, para evitar erro de digitação.
    const invMaterial = document.getElementById('inv-material');
    const invContado = document.getElementById('inv-contado');
    if (invMaterial && invContado) {
        function atualizarDiferenca() {
            const dados = saldoSelecionado(invMaterial);
            const texto = (invContado.value || '').replace(/\./g, '').replace(',', '.');
            const contado = parseFloat(texto);
            const alvo = document.getElementById('inv-diferenca');

            if (!dados || isNaN(contado)) {
                alvo.textContent = 'A diferença para o sistema aparece aqui.';
                alvo.className = 'form-text text-muted';
                return;
            }

            const diferenca = contado - dados.saldo;
            if (Math.abs(diferenca) < 0.005) {
                alvo.textContent = 'Confere com o sistema (' + formatar(dados.saldo) + ' ' + dados.unidade + '). Nada será lançado.';
                alvo.className = 'form-text text-success';
            } else {
                alvo.textContent = 'Sistema: ' + formatar(dados.saldo) + ' ' + dados.unidade +
                    ' · diferença ' + (diferenca > 0 ? '+' : '') + formatar(diferenca) + ' ' + dados.unidade;
                alvo.className = diferenca > 0 ? 'form-text text-primary' : 'form-text text-danger';
            }
        }

        $(invMaterial).on('change', atualizarDiferenca);
        invContado.addEventListener('input', atualizarDiferenca);
    }
});
</script>
