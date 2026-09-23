<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION['venda_itens'])) {
    $_SESSION['venda_itens'] = [];
}
if (!isset($_SESSION['venda_fornecedor'])) {
    $_SESSION['venda_fornecedor'] = 0;
}

$erro_form = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['selecionar_fornecedor'])) {
        $_SESSION['venda_fornecedor'] = (int) $_POST['fornecedor'];
    }

    if (isset($_POST['remover_fornecedor'])) {
        $_SESSION['venda_fornecedor'] = 0;
    }

    if (isset($_POST['adicionar_material'])) {
        $id_material   = (int) $_POST['id_material'];
        $quantidade_bruta = (float) str_replace(',', '.', $_POST['quantidade'] ?? '0');
        $tara          = (float) str_replace(',', '.', $_POST['tara'] ?? '0');
        $preco_un      = (float) str_replace(',', '.', $_POST['preco_un']);

        $stmt = $pdo->prepare("SELECT nm_material, tipo, unidade_medida, COALESCE(qt_estoque, 0) AS qt_estoque
                               FROM tb_material
                               WHERE id_material = :id AND status = 1");
        $stmt->execute([':id' => $id_material]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);

        $quantidade = $quantidade_bruta - $tara;

        // Soma o que já está no carrinho: o estoque limita o total do material na venda,
        // não cada linha isoladamente.
        $ja_no_carrinho = 0;
        foreach ($_SESSION['venda_itens'] as $item) {
            if ((int) $item['id_material'] === $id_material) {
                $ja_no_carrinho += (float) $item['quantidade'];
            }
        }

        if (!$material) {
            $erro_form = 'Material não encontrado.';
        } elseif ($quantidade_bruta <= 0) {
            $erro_form = 'Informe uma quantidade maior que zero.';
        } elseif ($tara < 0) {
            $erro_form = 'A tara não pode ser negativa.';
        } elseif ($quantidade <= 0) {
            $erro_form = 'A tara não pode ser maior ou igual à quantidade pesada.';
        } elseif ($preco_un <= 0) {
            $erro_form = 'Informe o preço de venda por ' . htmlspecialchars($material['unidade_medida']) . '.';
        } elseif ($quantidade + $ja_no_carrinho > (float) $material['qt_estoque']) {
            $disponivel = number_format((float) $material['qt_estoque'], 2, ',', '.') . ' ' . $material['unidade_medida'];

            $erro_form = $ja_no_carrinho > 0
                ? sprintf(
                    'Estoque insuficiente de %s: há %s disponíveis e você já adicionou %s %s nesta venda.',
                    $material['nm_material'],
                    $disponivel,
                    number_format($ja_no_carrinho, 2, ',', '.'),
                    $material['unidade_medida']
                )
                : sprintf(
                    'Estoque insuficiente de %s: há apenas %s disponíveis.',
                    $material['nm_material'],
                    $disponivel
                );
        } else {
            $_SESSION['venda_itens'][] = [
                'item_id'     => uniqid('venda_', true),
                'id_material' => $id_material,
                'nome'        => $material['nm_material'],
                'tipo'        => $material['tipo'],
                'unidade'     => $material['unidade_medida'],
                'quantidade_bruta' => $quantidade_bruta,
                'tara'        => $tara,
                'quantidade'  => $quantidade,
                'preco_un'    => $preco_un,
                'valor_total' => $quantidade * $preco_un,
            ];
        }
    }

    if (isset($_POST['remover_item'])) {
        $remover = $_POST['item_id'];
        $_SESSION['venda_itens'] = array_values(array_filter(
            $_SESSION['venda_itens'],
            fn($item) => $item['item_id'] !== $remover
        ));
    }

    if (isset($_POST['limpar_venda'])) {
        $_SESSION['venda_itens'] = [];
    }
}

$fornecedores = $pdo->query("SELECT id_fornecedor, nome_razao_social, cidade, estado
                             FROM fornecedores
                             WHERE status = 1
                             ORDER BY nome_razao_social")->fetchAll(PDO::FETCH_ASSOC);

$materiais = $pdo->query("SELECT id_material, nm_material, tipo, unidade_medida, preco_venda, COALESCE(qt_estoque, 0) AS qt_estoque
                          FROM tb_material
                          WHERE status = 1 AND COALESCE(qt_estoque, 0) > 0
                          ORDER BY nm_material")->fetchAll(PDO::FETCH_ASSOC);

$fornecedor_selecionado = null;
if (!empty($_SESSION['venda_fornecedor'])) {
    $stmt = $pdo->prepare("SELECT * FROM fornecedores WHERE id_fornecedor = :id AND status = 1");
    $stmt->execute([':id' => $_SESSION['venda_fornecedor']]);
    $fornecedor_selecionado = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$total_itens = count($_SESSION['venda_itens']);
$total_peso  = array_sum(array_column($_SESSION['venda_itens'], 'quantidade'));
$total_valor = array_sum(array_column($_SESSION['venda_itens'], 'valor_total'));
$pronto_para_finalizar = $fornecedor_selecionado && $total_itens > 0;
?>

<div class="container-fluid py-3" style="max-width: 1400px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="mb-1 fw-bold">
                <i class="bi bi-cart-check me-2" style="color: var(--color-accent);"></i>Nova Venda
                <span class="badge align-middle ms-1" style="background: var(--color-accent); font-size:.8rem;">
                    Sai do estoque
                </span>
            </h2>
            <p class="text-muted mb-0">Você vende ao fornecedor e recebe por isso</p>
        </div>
        <a href="<?= $url_base ?>/vendas/listar" class="btn btn-outline-secondary">
            <i class="bi bi-clock-history me-1"></i> Vendas realizadas
        </a>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <?php if ($erro_form): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div><?= htmlspecialchars($erro_form) ?></div>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Coluna esquerda: fornecedor + adicionar material -->
        <div class="col-lg-5">

            <div class="card mb-3">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-truck me-2"></i>1. Fornecedor</h5>
                </div>
                <div class="card-body">
                    <?php if ($fornecedor_selecionado): ?>
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center">
                                <div class="d-flex align-items-center justify-content-center me-3 text-white"
                                     style="width:48px;height:48px;border-radius:12px;background:var(--color-accent);font-weight:700;">
                                    <?= htmlspecialchars(strtoupper(substr($fornecedor_selecionado['nome_razao_social'], 0, 1))) ?>
                                </div>
                                <div>
                                    <strong class="d-block"><?= htmlspecialchars($fornecedor_selecionado['nome_razao_social']) ?></strong>
                                    <small class="text-muted">
                                        <?= htmlspecialchars(trim(($fornecedor_selecionado['cidade'] ?? '') . '/' . ($fornecedor_selecionado['estado'] ?? ''), '/')) ?: 'Sem cidade cadastrada' ?>
                                    </small>
                                </div>
                            </div>
                            <form method="POST">
                                <button type="submit" name="remover_fornecedor" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-x-lg me-1"></i> Trocar
                                </button>
                            </form>
                        </div>
                    <?php elseif (empty($fornecedores)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-truck fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-3">Nenhum fornecedor cadastrado ainda.</p>
                            <a href="<?= $url_base ?>/fornecedores/novo" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-lg me-1"></i> Cadastrar fornecedor
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="fornecedor" class="form-label">Para quem você está vendendo?</label>
                                <select name="fornecedor" id="fornecedor" class="form-select" required>
                                    <option value="">Selecione o fornecedor...</option>
                                    <?php foreach ($fornecedores as $f): ?>
                                        <option value="<?= (int) $f['id_fornecedor'] ?>">
                                            <?= htmlspecialchars($f['nome_razao_social']) ?>
                                            <?php $local = trim(($f['cidade'] ?? '') . '/' . ($f['estado'] ?? ''), '/'); ?>
                                            <?= $local ? ' — ' . htmlspecialchars($local) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="selecionar_fornecedor" class="btn btn-primary w-100">
                                <i class="bi bi-check-lg me-1"></i> Selecionar fornecedor
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-box-seam me-2"></i>2. Adicionar material</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($materiais)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-1">Nenhum material em estoque.</p>
                            <small class="text-muted d-block mb-3">
                                O estoque é gerado pelas compras de material dos clientes.
                            </small>
                            <a href="<?= $url_base ?>/compras" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-cart-plus me-1"></i> Ir para Compras
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="POST" id="form-material">
                            <div class="mb-2">
                                <label for="id_material" class="form-label">Material</label>
                                <select name="id_material" id="id_material" class="form-select" required>
                                    <option value="">Selecione o material...</option>
                                    <?php foreach ($materiais as $m): ?>
                                        <option value="<?= (int) $m['id_material'] ?>"
                                                data-estoque="<?= htmlspecialchars($m['qt_estoque']) ?>"
                                                data-unidade="<?= htmlspecialchars($m['unidade_medida']) ?>"
                                                data-preco="<?= htmlspecialchars($m['preco_venda'] ?? '') ?>">
                                            <?= htmlspecialchars($m['nm_material']) ?> / <?= htmlspecialchars($m['tipo']) ?>
                                            (<?= number_format((float) $m['qt_estoque'], 2, ',', '.') ?> <?= htmlspecialchars($m['unidade_medida']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted" id="estoque-info">
                                    Só aparecem materiais que existem no estoque.
                                </small>
                            </div>

                            <div class="row g-2">
                                <div class="col-6 col-md-3">
                                    <label for="quantidade" class="form-label">Qtd. bruta</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0.01" class="form-control"
                                               name="quantidade" id="quantidade" placeholder="0,00" required
                                               aria-describedby="quantidade-unidade">
                                        <span class="input-group-text" id="quantidade-unidade">kg</span>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="tara" class="form-label">Tara</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" class="form-control"
                                               name="tara" id="tara" placeholder="0,00" value="0"
                                               aria-describedby="tara-unidade">
                                        <span class="input-group-text" id="tara-unidade">kg</span>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label">Qtd. líquida</label>
                                    <input type="text" class="form-control" id="quantidade_liquida" placeholder="0,00 kg" readonly>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="preco_un" class="form-label">Preço de venda</label>
                                    <div class="input-group">
                                        <span class="input-group-text">R$</span>
                                        <input type="number" step="0.01" min="0.01" class="form-control"
                                               name="preco_un" id="preco_un" placeholder="0,00" required>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center bg-light rounded p-2 my-2">
                                <span class="text-muted">Subtotal</span>
                                <strong class="fs-5" id="subtotal">R$ 0,00</strong>
                            </div>

                            <button type="submit" name="adicionar_material" class="btn btn-primary w-100">
                                <i class="bi bi-plus-lg me-1"></i> Adicionar à venda
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Coluna direita: resumo e itens -->
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-receipt me-2"></i>3. Resumo da venda</h5>
                    <?php if ($total_itens > 0): ?>
                        <span class="badge bg-secondary"><?= $total_itens ?> <?= $total_itens === 1 ? 'item' : 'itens' ?></span>
                    <?php endif; ?>
                </div>

                <div class="card-body d-flex flex-column">

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="border rounded p-3 h-100">
                                <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">Peso total</small>
                                <strong class="fs-4"><?= number_format($total_peso, 2, ',', '.') ?> kg</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3 h-100" style="border-color: var(--color-accent) !important;">
                                <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">Valor a receber</small>
                                <strong class="fs-4" style="color: var(--color-accent);">R$ <?= number_format($total_valor, 2, ',', '.') ?></strong>
                            </div>
                        </div>
                    </div>

                    <?php if ($total_itens === 0): ?>
                        <div class="text-center text-muted py-5 flex-grow-1 d-flex flex-column justify-content-center">
                            <i class="bi bi-cart-x display-4 d-block mb-3"></i>
                            <h6 class="mb-1">Nenhum material adicionado</h6>
                            <p class="mb-0">Use o painel de materiais para montar a venda.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive flex-grow-1" style="max-height: 340px; overflow-y: auto;">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Material</th>
                                        <th class="text-end">Qtd.</th>
                                        <th class="text-end">Preço</th>
                                        <th class="text-end">Subtotal</th>
                                        <th><span class="visually-hidden">Ações</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($_SESSION['venda_itens'] as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($item['nome']) ?></strong>
                                            <small class="text-muted d-block"><?= htmlspecialchars($item['tipo']) ?></small>
                                        </td>
                                        <td class="text-end">
                                            <?= number_format($item['quantidade'], 2, ',', '.') ?>
                                            <small class="text-muted"><?= htmlspecialchars($item['unidade']) ?></small>
                                            <?php if (!empty($item['tara'])): ?>
                                                <small class="text-muted d-block">
                                                    bruto <?= number_format($item['quantidade_bruta'], 2, ',', '.') ?>
                                                    − tara <?= number_format($item['tara'], 2, ',', '.') ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">R$ <?= number_format($item['preco_un'], 2, ',', '.') ?></td>
                                        <td class="text-end fw-semibold">R$ <?= number_format($item['valor_total'], 2, ',', '.') ?></td>
                                        <td class="text-end">
                                            <form method="POST">
                                                <input type="hidden" name="item_id" value="<?= htmlspecialchars($item['item_id']) ?>">
                                                <button type="submit" name="remover_item" class="btn btn-outline-danger btn-sm"
                                                        title="Remover <?= htmlspecialchars($item['nome']) ?> da venda"
                                                        aria-label="Remover <?= htmlspecialchars($item['nome']) ?> da venda">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= $url_base ?>/functions/venda/registrar.php" class="mt-auto pt-3 border-top">
                        <input type="hidden" name="acao" value="finalizar">

                        <div class="mb-2">
                            <label for="observacoes" class="form-label">Observações (opcional)</label>
                            <input type="text" name="observacoes" id="observacoes" class="form-control"
                                   placeholder="Ex: retirada agendada para sexta-feira">
                        </div>

                        <?php if (!$pronto_para_finalizar): ?>
                            <p class="text-muted small mb-2">
                                <i class="bi bi-info-circle me-1"></i>
                                <?php if (!$fornecedor_selecionado && $total_itens === 0): ?>
                                    Selecione um fornecedor e adicione materiais para finalizar.
                                <?php elseif (!$fornecedor_selecionado): ?>
                                    Selecione o fornecedor para finalizar a venda.
                                <?php else: ?>
                                    Adicione pelo menos um material para finalizar.
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1 btn-lg"
                                    <?= $pronto_para_finalizar ? '' : 'disabled' ?>
                                    onclick="return confirm('Confirmar a venda e dar baixa no estoque?')">
                                <i class="bi bi-check2-circle me-1"></i> Finalizar venda
                            </button>
                        </div>
                    </form>

                    <?php if ($total_itens > 0): ?>
                        <form method="POST" class="mt-2">
                            <button type="submit" name="limpar_venda" class="btn btn-outline-secondary w-100"
                                    onclick="return confirm('Remover todos os materiais da venda?')">
                                <i class="bi bi-backspace me-1"></i> Limpar lista
                            </button>
                        </form>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    $('#fornecedor, #id_material').select2({
        width: '100%',
        language: 'pt-BR'
    });

    const selectMaterial = document.getElementById('id_material');
    if (!selectMaterial) return;

    const quantidade = document.getElementById('quantidade');
    const tara = document.getElementById('tara');
    const quantidadeLiquida = document.getElementById('quantidade_liquida');
    const precoUn = document.getElementById('preco_un');
    const subtotal = document.getElementById('subtotal');
    const unidadeLabel = document.getElementById('quantidade-unidade');
    const estoqueInfo = document.getElementById('estoque-info');

    function formatarReal(valor) {
        return 'R$ ' + valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function atualizarSubtotal() {
        const bruto = parseFloat(quantidade.value) || 0;
        const taraValor = Math.max(parseFloat(tara.value) || 0, 0);
        const liquido = Math.max(bruto - taraValor, 0);

        quantidadeLiquida.value = liquido.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kg';
        subtotal.textContent = formatarReal(liquido * (parseFloat(precoUn.value) || 0));
    }

    // O Select2 dispara "change" via jQuery, então o listener precisa ser
    // registrado com $(...).on() — addEventListener nativo não é acionado.
    $(selectMaterial).on('change', function () {
        const opcao = this.options[this.selectedIndex];
        const estoque = parseFloat(opcao.getAttribute('data-estoque')) || 0;
        const unidade = opcao.getAttribute('data-unidade') || 'kg';
        const preco = opcao.getAttribute('data-preco');

        if (this.value) {
            unidadeLabel.textContent = unidade;
            quantidade.max = estoque;
            estoqueInfo.textContent = 'Disponível em estoque: ' +
                estoque.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + unidade;
            // preco_venda ainda não está cadastrado na maioria dos materiais, então só pré-preenche quando existe.
            if (preco && !precoUn.value) {
                precoUn.value = parseFloat(preco).toFixed(2);
            }
            quantidade.focus();
        } else {
            quantidade.removeAttribute('max');
            estoqueInfo.textContent = 'Só aparecem materiais que existem no estoque.';
        }
        atualizarSubtotal();
    });

    quantidade.addEventListener('input', atualizarSubtotal);
    tara.addEventListener('input', atualizarSubtotal);
    precoUn.addEventListener('input', atualizarSubtotal);
});
</script>
