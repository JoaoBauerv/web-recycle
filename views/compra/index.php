<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION['compra_itens'])) {
    $_SESSION['compra_itens'] = [];
}
if (!isset($_SESSION['compra_cliente'])) {
    $_SESSION['compra_cliente'] = 0;
}

$erro_form = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['selecionar_cliente'])) {
        $_SESSION['compra_cliente'] = (int) $_POST['cliente'];
    }

    if (isset($_POST['remover_cliente'])) {
        $_SESSION['compra_cliente'] = 0;
    }

    if (isset($_POST['adicionar_material'])) {
        $id_material   = (int) $_POST['id_material'];
        $peso_bruto    = (float) str_replace(',', '.', $_POST['peso'] ?? '0');
        $tara          = (float) str_replace(',', '.', $_POST['tara'] ?? '0');
        $preco_un      = (float) str_replace(',', '.', $_POST['preco_un'] ?? '0');
        $usar_especial = isset($_POST['preco_especial']);

        $stmt = $pdo->prepare("SELECT nm_material, tipo, unidade_medida, preco_especial
                               FROM tb_material
                               WHERE id_material = :id AND status = 1");
        $stmt->execute([':id' => $id_material]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);

        $peso = $peso_bruto - $tara;

        if (!$material) {
            $erro_form = 'Material não encontrado.';
        } elseif ($peso_bruto <= 0) {
            $erro_form = 'Informe um peso maior que zero.';
        } elseif ($tara < 0) {
            $erro_form = 'A tara não pode ser negativa.';
        } elseif ($peso <= 0) {
            $erro_form = 'A tara não pode ser maior ou igual ao peso pesado.';
        } elseif ($preco_un <= 0) {
            $erro_form = 'Informe o valor por ' . htmlspecialchars($material['unidade_medida']) . '.';
        } else {
            // O valor vem pré-preenchido da tabela de materiais (preço especial ou normal),
            // mas o operador pode editar antes de adicionar — preco_un é o que realmente vale.
            $tem_especial = $usar_especial && !empty($material['preco_especial']);

            $_SESSION['compra_itens'][] = [
                'item_id'     => uniqid('compra_', true),
                'id_material' => $id_material,
                'nome'        => $material['nm_material'],
                'tipo'        => $material['tipo'],
                'unidade'     => $material['unidade_medida'],
                'peso_bruto'  => $peso_bruto,
                'tara'        => $tara,
                'peso'        => $peso,
                'preco_un'    => $preco_un,
                'valor_total' => $peso * $preco_un,
                'especial'    => $tem_especial,
                'observacoes' => trim($_POST['observacoes'] ?? ''),
            ];
        }
    }

    if (isset($_POST['remover_item'])) {
        $remover = $_POST['item_id'];
        $_SESSION['compra_itens'] = array_values(array_filter(
            $_SESSION['compra_itens'],
            fn($item) => $item['item_id'] !== $remover
        ));
    }

    if (isset($_POST['limpar_compra'])) {
        $_SESSION['compra_itens'] = [];
    }
}

$clientes = $pdo->query("SELECT id_cliente, nome, cidade, estado, preco_especial
                         FROM clientes
                         WHERE status = 1
                         ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$materiais = $pdo->query("SELECT id_material, nm_material, tipo, unidade_medida, preco_compra, preco_especial
                          FROM tb_material
                          WHERE status = 1
                          ORDER BY nm_material")->fetchAll(PDO::FETCH_ASSOC);

$cliente_selecionado = null;
if (!empty($_SESSION['compra_cliente'])) {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id_cliente = :id AND status = 1");
    $stmt->execute([':id' => $_SESSION['compra_cliente']]);
    $cliente_selecionado = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$total_itens = count($_SESSION['compra_itens']);
$total_peso  = array_sum(array_column($_SESSION['compra_itens'], 'peso'));
$total_valor = array_sum(array_column($_SESSION['compra_itens'], 'valor_total'));
$pronto_para_finalizar = $cliente_selecionado && $total_itens > 0;
?>

<div class="container-fluid py-3" style="max-width: 1400px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="mb-1 fw-bold">
                <i class="bi bi-cart-plus me-2" style="color: var(--color-accent);"></i>Nova Compra
                <span class="badge align-middle ms-1" style="background: var(--color-accent); font-size:.8rem;">
                    Entra no estoque
                </span>
            </h2>
            <p class="text-muted mb-0">Você compra do cliente e paga por isso</p>
        </div>
        <a href="<?= $url_base ?>/compras/listar" class="btn btn-outline-secondary">
            <i class="bi bi-clock-history me-1"></i> Compras realizadas
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

        <!-- Coluna esquerda: cliente + adicionar material -->
        <div class="col-lg-5">

            <div class="card mb-3">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-person me-2"></i>1. Cliente</h5>
                </div>
                <div class="card-body">
                    <?php if ($cliente_selecionado): ?>
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center">
                                <div class="d-flex align-items-center justify-content-center me-3 text-white"
                                     style="width:48px;height:48px;border-radius:12px;background:var(--color-accent);font-weight:700;">
                                    <?= htmlspecialchars(strtoupper(substr($cliente_selecionado['nome'], 0, 1))) ?>
                                </div>
                                <div>
                                    <strong class="d-block"><?= htmlspecialchars($cliente_selecionado['nome']) ?></strong>
                                    <small class="text-muted d-block">
                                        <?= htmlspecialchars(trim(($cliente_selecionado['cidade'] ?? '') . '/' . ($cliente_selecionado['estado'] ?? ''), '/')) ?: 'Sem cidade cadastrada' ?>
                                    </small>
                                    <?php if ($cliente_selecionado['preco_especial']): ?>
                                        <span class="badge bg-success mt-1 d-inline-block">
                                            <i class="bi bi-star-fill me-1"></i>Preço especial
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <form method="POST">
                                <button type="submit" name="remover_cliente" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-x-lg me-1"></i> Trocar
                                </button>
                            </form>
                        </div>
                    <?php elseif (empty($clientes)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-person-x fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-3">Nenhum cliente cadastrado ainda.</p>
                            <a href="<?= $url_base ?>/clientes/novo" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-lg me-1"></i> Cadastrar cliente
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="cliente" class="form-label">De quem você está comprando?</label>
                                <select name="cliente" id="cliente" class="form-select" required>
                                    <option value="">Selecione o cliente...</option>
                                    <?php foreach ($clientes as $c): ?>
                                        <option value="<?= (int) $c['id_cliente'] ?>">
                                            <?= htmlspecialchars($c['nome']) ?>
                                            <?php $local = trim(($c['cidade'] ?? '') . '/' . ($c['estado'] ?? ''), '/'); ?>
                                            <?= $local ? ' — ' . htmlspecialchars($local) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="selecionar_cliente" class="btn btn-primary w-100">
                                <i class="bi bi-check-lg me-1"></i> Selecionar cliente
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-box-seam me-2"></i>2. Pesar material</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($materiais)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-3">Nenhum material cadastrado.</p>
                            <a href="<?= $url_base ?>/materiais/novo" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-plus-lg me-1"></i> Cadastrar material
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="mb-2">
                                <label for="id_material" class="form-label">Material</label>
                                <select name="id_material" id="id_material" class="form-select" required>
                                    <option value="">Selecione o material...</option>
                                    <?php foreach ($materiais as $m): ?>
                                        <option value="<?= (int) $m['id_material'] ?>"
                                                data-preco="<?= htmlspecialchars($m['preco_compra']) ?>"
                                                data-preco-especial="<?= htmlspecialchars($m['preco_especial'] ?? '') ?>"
                                                data-unidade="<?= htmlspecialchars($m['unidade_medida']) ?>">
                                            <?= htmlspecialchars($m['nm_material']) ?> / <?= htmlspecialchars($m['tipo']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" id="preco_especial" name="preco_especial"
                                       <?= ($cliente_selecionado && $cliente_selecionado['preco_especial']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="preco_especial">Usar preço especial</label>
                            </div>

                            <?php if (!empty($_ENV['BALANCA_API_URL'])): ?>
                            <!-- Leitura da balança (agente local em Python). Se o agente estiver
                                 fora do ar, o painel some e o peso continua sendo digitado à mão. -->
                            <div id="painel-balanca" class="border rounded p-2 mb-2 bg-light d-none">
                                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                    <div class="d-flex align-items-center gap-2">
                                        <span id="balanca-sinal" class="d-inline-block rounded-circle"
                                              style="width:10px;height:10px;background:#dc3545;"></span>
                                        <small class="text-muted" id="balanca-status">Procurando balança...</small>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <strong id="balanca-peso" class="fs-5">--,-- kg</strong>
                                        <button type="button" id="btn-capturar-peso" class="btn btn-sm btn-outline-primary" disabled>
                                            <i class="bi bi-download me-1"></i> Capturar Peso
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="row g-2">
                                <div class="col-6 col-md-3">
                                    <label for="peso" class="form-label">Peso bruto</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0.01" class="form-control"
                                               name="peso" id="peso" placeholder="0,00" required
                                               aria-describedby="peso-unidade">
                                        <span class="input-group-text" id="peso-unidade">kg</span>
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
                                    <label class="form-label">Peso líquido</label>
                                    <input type="text" class="form-control" id="peso_liquido" placeholder="0,00 kg" readonly>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="preco_un" class="form-label">Valor un.</label>
                                    <div class="input-group">
                                        <span class="input-group-text">R$</span>
                                        <input type="number" step="0.01" min="0.01" class="form-control"
                                               name="preco_un" id="preco_un" placeholder="0,00" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-2 mt-2">
                                <label for="observacoes" class="form-label">Observações (opcional)</label>
                                <input type="text" name="observacoes" id="observacoes" class="form-control"
                                       placeholder="Ex: material com umidade">
                            </div>

                            <div class="d-flex justify-content-between align-items-center bg-light rounded p-2 mb-2">
                                <span class="text-muted">Subtotal</span>
                                <strong class="fs-5" id="subtotal">R$ 0,00</strong>
                            </div>

                            <button type="submit" name="adicionar_material" class="btn btn-primary w-100">
                                <i class="bi bi-plus-lg me-1"></i> Adicionar à compra
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
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-receipt me-2"></i>3. Resumo da compra</h5>
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
                                <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">Valor a pagar</small>
                                <strong class="fs-4" style="color: var(--color-accent);">R$ <?= number_format($total_valor, 2, ',', '.') ?></strong>
                            </div>
                        </div>
                    </div>

                    <?php if ($total_itens === 0): ?>
                        <div class="text-center text-muted py-5 flex-grow-1 d-flex flex-column justify-content-center">
                            <i class="bi bi-cart-x display-4 d-block mb-3"></i>
                            <h6 class="mb-1">Nenhum material pesado</h6>
                            <p class="mb-0">Use o painel de materiais para montar a compra.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive flex-grow-1" style="max-height: 340px; overflow-y: auto;">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Material</th>
                                        <th class="text-end">Peso</th>
                                        <th class="text-end">Preço</th>
                                        <th class="text-end">Subtotal</th>
                                        <th><span class="visually-hidden">Ações</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($_SESSION['compra_itens'] as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($item['nome']) ?></strong>
                                            <small class="text-muted d-block"><?= htmlspecialchars($item['tipo']) ?></small>
                                            <?php if (!empty($item['especial'])): ?>
                                                <span class="badge bg-success-subtle text-success">preço especial</span>
                                            <?php endif; ?>
                                            <?php if (!empty($item['observacoes'])): ?>
                                                <small class="text-muted d-block fst-italic">
                                                    <?= htmlspecialchars($item['observacoes']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?= number_format($item['peso'], 2, ',', '.') ?>
                                            <small class="text-muted"><?= htmlspecialchars($item['unidade']) ?></small>
                                            <?php if (!empty($item['tara'])): ?>
                                                <small class="text-muted d-block">
                                                    bruto <?= number_format($item['peso_bruto'], 2, ',', '.') ?>
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
                                                        title="Remover <?= htmlspecialchars($item['nome']) ?> da compra"
                                                        aria-label="Remover <?= htmlspecialchars($item['nome']) ?> da compra">
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

                    <form method="POST" action="<?= $url_base ?>/functions/compra/registrar.php" class="mt-auto pt-3 border-top">
                        <input type="hidden" name="acao" value="finalizar">

                        <?php if (!$pronto_para_finalizar): ?>
                            <p class="text-muted small mb-2">
                                <i class="bi bi-info-circle me-1"></i>
                                <?php if (!$cliente_selecionado && $total_itens === 0): ?>
                                    Selecione um cliente e pese os materiais para finalizar.
                                <?php elseif (!$cliente_selecionado): ?>
                                    Selecione o cliente para finalizar a compra.
                                <?php else: ?>
                                    Pese pelo menos um material para finalizar.
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary w-100 btn-lg"
                                <?= $pronto_para_finalizar ? '' : 'disabled' ?>
                                onclick="return confirm('Confirmar a compra e dar entrada no estoque?')">
                            <i class="bi bi-check2-circle me-1"></i> Finalizar compra
                        </button>
                    </form>

                    <?php if ($total_itens > 0): ?>
                        <form method="POST" class="mt-2">
                            <button type="submit" name="limpar_compra" class="btn btn-outline-secondary w-100"
                                    onclick="return confirm('Remover todos os materiais da compra?')">
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
    $('#cliente, #id_material').select2({
        width: '100%',
        language: 'pt-BR'
    });

    const selectMaterial = document.getElementById('id_material');
    if (!selectMaterial) return;

    const especial = document.getElementById('preco_especial');
    const peso = document.getElementById('peso');
    const tara = document.getElementById('tara');
    const pesoLiquido = document.getElementById('peso_liquido');
    const precoUn = document.getElementById('preco_un');
    const subtotal = document.getElementById('subtotal');
    const unidadeLabel = document.getElementById('peso-unidade');

    function formatarReal(valor) {
        return 'R$ ' + valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalcularSubtotal() {
        const pesoBruto = parseFloat(peso.value) || 0;
        const taraValor = Math.max(parseFloat(tara.value) || 0, 0);
        const liquido = Math.max(pesoBruto - taraValor, 0);
        const preco = parseFloat(precoUn.value) || 0;

        pesoLiquido.value = liquido.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kg';
        subtotal.textContent = formatarReal(preco * liquido);
    }

    // Sugere o preço vindo do cadastro do material (normal ou especial, conforme o checkbox),
    // mas o campo continua editável — o valor digitado é o que vale.
    function sugerirPreco() {
        const opcao = selectMaterial.options[selectMaterial.selectedIndex];
        if (!selectMaterial.value) return;

        const precoEspecial = parseFloat(opcao.dataset.precoEspecial);
        const precoSugerido = (especial.checked && precoEspecial > 0) ? precoEspecial : (parseFloat(opcao.dataset.preco) || 0);
        precoUn.value = precoSugerido ? precoSugerido.toFixed(2) : '';
        recalcularSubtotal();
    }

    // O Select2 dispara "change" via jQuery, então o listener precisa ser
    // registrado com $(...).on() — addEventListener nativo não é acionado.
    $(selectMaterial).on('change', function () {
        const opcao = this.options[this.selectedIndex];
        if (this.value) {
            unidadeLabel.textContent = opcao.dataset.unidade || 'kg';
            peso.focus();
        }
        sugerirPreco();
    });

    especial.addEventListener('change', sugerirPreco);
    peso.addEventListener('input', recalcularSubtotal);
    tara.addEventListener('input', recalcularSubtotal);
    precoUn.addEventListener('input', recalcularSubtotal);
});
</script>

<?php if (!empty($_ENV['BALANCA_API_URL'])): ?>
<script>
/**
 * Integração com o agente de balança (projeto balanca-agent, em Python).
 *
 * Tudo aqui é aditivo: se o agente não estiver rodando, o painel some e a tela
 * continua funcionando exatamente como antes, com o peso digitado à mão.
 * A captura só preenche o campo "Peso bruto" e dispara o evento 'input', que o
 * cálculo já existente (peso líquido / subtotal) escuta.
 */
(function () {
    const API = <?= json_encode(rtrim($_ENV['BALANCA_API_URL'], '/')) ?>;

    const painel  = document.getElementById('painel-balanca');
    const campoPeso = document.getElementById('peso');
    if (!painel || !campoPeso) return;

    const sinal   = document.getElementById('balanca-sinal');
    const txt     = document.getElementById('balanca-status');
    const visor   = document.getElementById('balanca-peso');
    const botao   = document.getElementById('btn-capturar-peso');

    let ultima = null;

    function formatar(valor, unidade) {
        if (valor === null || valor === undefined) return '--,-- ' + (unidade || 'kg');
        return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            + ' ' + (unidade || 'kg');
    }

    function offline(motivo) {
        ultima = null;
        painel.classList.add('d-none');
        botao.disabled = true;
        if (motivo) txt.textContent = motivo;
    }

    async function consultar() {
        let dados;
        try {
            const resposta = await fetch(API + '/peso', { cache: 'no-store' });
            dados = await resposta.json();
        } catch (e) {
            offline();               // agente fora do ar: volta ao fluxo manual
            return;
        }

        painel.classList.remove('d-none');

        if (!dados.sucesso) {
            ultima = null;
            sinal.style.background = '#dc3545';
            txt.textContent = dados.erro || 'Balança não conectada';
            visor.textContent = '--,-- kg';
            botao.disabled = true;
            return;
        }

        ultima = dados;
        visor.textContent = formatar(dados.peso, dados.unidade);
        botao.disabled = false;

        if (dados.estavel) {
            sinal.style.background = '#198754';
            txt.textContent = 'Balança conectada — peso estável';
        } else {
            sinal.style.background = '#ffc107';
            txt.textContent = 'Balança conectada — estabilizando...';
        }
    }

    botao.addEventListener('click', function () {
        if (!ultima || ultima.peso === null) return;

        campoPeso.value = Number(ultima.peso).toFixed(2);
        // Avisa o cálculo já existente (peso líquido, subtotal).
        campoPeso.dispatchEvent(new Event('input', { bubbles: true }));

        // Só para o agente registrar a captura no log dele; falha aqui é irrelevante.
        fetch(API + '/captura', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ peso: ultima.peso, origem: 'tela de compra' })
        }).catch(function () {});
    });

    consultar();
    setInterval(consultar, 1000);
})();
</script>
<?php endif; ?>
