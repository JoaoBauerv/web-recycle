<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

$id_pesagem = (int) ($_GET['id'] ?? 0);
if (!$id_pesagem) {
    header('Location: ' . $url_base . '/compras/listar');
    exit;
}

// Toda a informação do comprovante vem do banco a partir do ID da compra —
// nunca da sessão/formulário — para garantir que o documento reflita
// exatamente o que foi efetivamente salvo.
$stmt = $pdo->prepare("SELECT * FROM tb_pesagem WHERE id_pesagem = ?");
$stmt->execute([$id_pesagem]);
$pesagem = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pesagem) {
    header('Location: ' . $url_base . '/compras/listar');
    exit;
}

$stmt_cliente = $pdo->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
$stmt_cliente->execute([$pesagem['id_cliente']]);
$cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

$stmt_itens = $pdo->prepare("
    SELECT a.*, b.nm_material, b.unidade_medida, b.tipo AS material_tipo
    FROM tb_pesagem_material a
    LEFT JOIN tb_material b ON a.id_material = b.id_material
    WHERE a.id_pesagem = ?
    ORDER BY a.id
");
$stmt_itens->execute([$id_pesagem]);
$itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

$cliente_nome = $cliente['nome'] ?? 'Cliente não encontrado';
$eh_novo = !empty($_GET['novo']);

function comprovanteEndereco(?array $c): string
{
    if (!$c) {
        return '';
    }
    $partes = [];

    $rua = trim(($c['logradouro'] ?? '') . (!empty($c['numero']) ? ', ' . $c['numero'] : ''));
    if ($rua !== '') {
        $partes[] = $rua;
    }
    if (!empty($c['complemento'])) {
        $partes[] = $c['complemento'];
    }
    if (!empty($c['bairro'])) {
        $partes[] = $c['bairro'];
    }
    $cidadeUf = trim(($c['cidade'] ?? '') . (!empty($c['estado']) ? '/' . $c['estado'] : ''));
    if ($cidadeUf !== '') {
        $partes[] = $cidadeUf;
    }
    if (!empty($c['cep'])) {
        $partes[] = 'CEP ' . $c['cep'];
    }

    return implode(' - ', $partes);
}

$cliente_endereco = comprovanteEndereco($cliente);
?>
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <!-- Barra de ações (não imprime) -->
            <div class="no-print mb-3">
                <?php if ($eh_novo): ?>
                    <div class="alert alert-success d-flex align-items-center">
                        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                        <div>Compra #<?= $id_pesagem ?> registrada com sucesso! Confira o comprovante abaixo.</div>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="<?= $url_base ?>/compras/listar" class="text-decoration-none">
                                    <i class="bi bi-house-door me-1"></i>Compras
                                </a>
                            </li>
                            <li class="breadcrumb-item active">Comprovante #<?= $id_pesagem ?></li>
                        </ol>
                    </nav>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body d-flex flex-wrap align-items-center gap-3">
                        <div class="btn-group" role="group" aria-label="Formato de impressão">
                            <input type="radio" class="btn-check" name="formato_impressao" id="formatoA4" value="a4" checked>
                            <label class="btn btn-outline-secondary btn-sm" for="formatoA4">A4</label>

                            <input type="radio" class="btn-check" name="formato_impressao" id="formato80" value="80mm">
                            <label class="btn btn-outline-secondary btn-sm" for="formato80">Térmica 80mm</label>

                            <input type="radio" class="btn-check" name="formato_impressao" id="formato58" value="58mm">
                            <label class="btn btn-outline-secondary btn-sm" for="formato58">Térmica 58mm</label>
                        </div>

                        <div class="d-flex gap-2 ms-auto flex-wrap">
                            <button type="button" id="btnImprimir" class="btn btn-primary">
                                <i class="bi bi-printer me-1"></i> Imprimir Comprovante
                            </button>
                            <a href="<?= $url_base ?>/views/compra/pdf.php?id=<?= $id_pesagem ?>" target="_blank" class="btn btn-outline-primary">
                                <i class="bi bi-file-earmark-pdf me-1"></i> Baixar PDF
                            </a>
                            <a href="<?= $url_base ?>/compras/detalhe?id=<?= $id_pesagem ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-eye me-1"></i> Ver Detalhes
                            </a>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mb-3">
                    <a href="<?= $url_base ?>/compras" class="btn btn-success">
                        <i class="bi bi-plus-lg me-1"></i> Nova Compra
                    </a>
                    <a href="<?= $url_base ?>/compras/listar" class="btn btn-outline-secondary">
                        <i class="bi bi-list-ul me-1"></i> Ver Lista de Compras
                    </a>
                </div>
            </div>

            <!-- Comprovante (isto é o que imprime) -->
            <div id="comprovante" class="recibo formato-a4">

                <div class="recibo-header text-center">
                    <img src="<?= $url_base ?>/images/logo.png" alt="Logo" class="recibo-logo">
                    <h5 class="fw-bold mb-0 mt-2"><?= htmlspecialchars($_ENV['APP_NAME'] ?? '') ?></h5>
                    <?php if (!empty($_ENV['APP_END'])): ?>
                        <small class="text-muted d-block"><?= htmlspecialchars($_ENV['APP_END']) ?></small>
                    <?php endif; ?>

                    <div class="recibo-titulo mt-3">
                        <strong>COMPROVANTE DE COMPRA DE MATERIAIS RECICLÁVEIS</strong>
                    </div>
                    <div class="small text-muted">
                        Nº <?= str_pad($id_pesagem, 8, '0', STR_PAD_LEFT) ?>
                        &nbsp;•&nbsp;
                        <?= date('d/m/Y H:i', strtotime($pesagem['data_pesagem'])) ?>
                    </div>
                </div>

                <hr>

                <div class="recibo-secao">
                    <div class="recibo-secao-titulo">CLIENTE / FORNECEDOR</div>
                    <div><?= htmlspecialchars($cliente_nome) ?></div>
                    <?php if (!empty($cliente['cpf_cnpj'])): ?>
                        <div>CPF/CNPJ: <?= htmlspecialchars($cliente['cpf_cnpj']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($cliente['telefone']) || !empty($cliente['celular'])): ?>
                        <div>Tel: <?= htmlspecialchars($cliente['telefone'] ?: $cliente['celular']) ?></div>
                    <?php endif; ?>
                    <?php if ($cliente_endereco !== ''): ?>
                        <div><?= htmlspecialchars($cliente_endereco) ?></div>
                    <?php endif; ?>
                </div>

                <hr>

                <div class="recibo-secao">
                    <div class="recibo-secao-titulo">MATERIAIS COMPRADOS</div>
                    <table class="recibo-tabela">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th class="text-end">Qtd.</th>
                                <th class="text-end">Unit.</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($itens as $item):
                            $peso  = (float) $item['peso_material'];
                            $preco = (float) $item['preco_un'];
                            $unidade = $item['unidade_medida'] ?: 'kg';
                        ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($item['nm_material'] ?? 'Material removido') ?>
                                    <?php if (!empty($item['tara'])): ?>
                                        <div class="recibo-obs">
                                            bruto <?= number_format((float) $item['peso_bruto'], 2, ',', '.') ?><?= htmlspecialchars($unidade) ?>
                                            − tara <?= number_format((float) $item['tara'], 2, ',', '.') ?><?= htmlspecialchars($unidade) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['obs'])): ?>
                                        <div class="recibo-obs fst-italic"><?= htmlspecialchars($item['obs']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= number_format($peso, 2, ',', '.') ?><?= htmlspecialchars($unidade) ?></td>
                                <td class="text-end">R$ <?= number_format($preco, 2, ',', '.') ?></td>
                                <td class="text-end">R$ <?= number_format($peso * $preco, 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <hr>

                <div class="recibo-total d-flex justify-content-between">
                    <span>TOTAL DA COMPRA</span>
                    <strong>R$ <?= number_format((float) $pesagem['total_valor'], 2, ',', '.') ?></strong>
                </div>
                <div class="d-flex justify-content-between small text-muted">
                    <span>Peso total</span>
                    <span><?= number_format((float) $pesagem['total_peso'], 2, ',', '.') ?> kg</span>
                </div>
                <div class="d-flex justify-content-between small text-muted">
                    <span>Quantidade de itens</span>
                    <span><?= count($itens) ?></span>
                </div>

                <hr>

                <div class="recibo-assinatura">
                    <div class="recibo-linha-assinatura"></div>
                    <div class="small text-center text-muted">Assinatura do Cliente/Fornecedor</div>
                </div>

                <div class="recibo-footer text-center small text-muted mt-4">
                    Obrigado!
                </div>

            </div>

        </div>
    </div>
</div>

<style id="print-page-size">
@page { size: A4; margin: 10mm; }
</style>

<style>
.recibo {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 2rem;
    margin: 0 auto;
    max-width: 720px;
}

.recibo-logo {
    height: 56px;
}

.recibo-titulo {
    font-size: 1rem;
    letter-spacing: .03em;
}

.recibo-secao-titulo {
    font-size: .75rem;
    font-weight: 700;
    letter-spacing: .05em;
    color: var(--color-accent, #059669);
    text-transform: uppercase;
    margin-bottom: .35rem;
}

.recibo-tabela {
    width: 100%;
    border-collapse: collapse;
}

.recibo-tabela th, .recibo-tabela td {
    padding: .4rem .25rem;
    border-bottom: 1px solid #eee;
    font-size: .9rem;
}

.recibo-tabela th {
    text-transform: uppercase;
    font-size: .7rem;
    color: #6c757d;
    border-bottom: 2px solid #dee2e6;
}

.recibo-obs {
    font-size: .75rem;
    color: #6c757d;
}

.recibo-total {
    font-size: 1.1rem;
    padding: .5rem 0;
}

.recibo-assinatura {
    margin-top: 2.5rem;
}

.recibo-linha-assinatura {
    border-top: 1px solid #333;
    width: 80%;
    margin: 0 auto 1.5rem auto;
}

/* Pré-visualização em tela aproximando o formato térmico selecionado */
.recibo.formato-80mm {
    max-width: 302px;
    padding: .75rem;
    font-size: .8rem;
}

.recibo.formato-58mm {
    max-width: 219px;
    padding: .5rem;
    font-size: .7rem;
}

.recibo.formato-80mm .recibo-tabela th,
.recibo.formato-58mm .recibo-tabela th {
    font-size: .6rem;
}

.recibo.formato-80mm .recibo-logo,
.recibo.formato-58mm .recibo-logo {
    height: 36px;
}

@media print {
    .no-print,
    .sidebar,
    .sidebar-toggle,
    .sidebar-backdrop {
        display: none !important;
    }

    body, main {
        background: #fff !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .recibo {
        border: none !important;
        box-shadow: none !important;
        margin: 0 !important;
    }

    /* Cada formato define sua própria largura de impressão — precisa vir depois
       da regra genérica acima e usar !important para realmente valer na hora de imprimir. */
    .recibo.formato-a4 {
        max-width: 100% !important;
        padding: 0 !important;
        font-size: inherit !important;
    }

    .recibo.formato-80mm {
        width: 80mm !important;
        max-width: 80mm !important;
        padding: 2mm !important;
        font-size: 11px !important;
    }

    .recibo.formato-58mm {
        width: 58mm !important;
        max-width: 58mm !important;
        padding: 1mm !important;
        font-size: 9px !important;
    }

    .recibo.formato-80mm .recibo-tabela th,
    .recibo.formato-58mm .recibo-tabela th {
        font-size: 8px !important;
    }

    .recibo.formato-80mm .recibo-logo,
    .recibo.formato-58mm .recibo-logo {
        height: 32px !important;
    }
}
</style>

<script>
(function () {
    const recibo = document.getElementById('comprovante');
    const pageStyle = document.getElementById('print-page-size');
    const radios = document.querySelectorAll('input[name="formato_impressao"]');

    const paginas = {
        a4:   '@page { size: A4; margin: 10mm; }',
        '80mm': '@page { size: 80mm auto; margin: 2mm; }',
        '58mm': '@page { size: 58mm auto; margin: 1mm; }'
    };

    radios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            recibo.classList.remove('formato-a4', 'formato-80mm', 'formato-58mm');
            recibo.classList.add('formato-' + this.value);
            pageStyle.textContent = paginas[this.value] || paginas.a4;
        });
    });

    document.getElementById('btnImprimir').addEventListener('click', function () {
        window.print();
    });
})();
</script>
