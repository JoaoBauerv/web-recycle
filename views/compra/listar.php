<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

// JOIN com clientes: antes o nome era buscado com uma query dentro do laço,
// ou seja, uma consulta por linha da tabela.
// Compra cancelada continua na lista, com selo: ela aconteceu e a trilha tem de
// mostrá-la. O que ela não faz é entrar nas somas (painel, relatório, margem).
$compras = $pdo->query("SELECT p.id_pesagem, p.total_peso, p.total_valor, p.data_pesagem, p.status,
                               c.nome AS cliente_nome
                        FROM pesagem p
                        LEFT JOIN clientes c ON c.id_cliente = p.id_cliente
                        WHERE p.total_valor > 0
                        -- O id desempata a mesma data: sem ele o Postgres devolve a
                        -- ordem que quiser quando data_pesagem é idêntica, e ela muda
                        -- de um carregamento para o outro.
                        ORDER BY p.data_pesagem DESC, p.id_pesagem DESC")->fetchAll(PDO::FETCH_ASSOC);

$total_compras = count($compras);

// As linhas vão para o navegador como dados, e o DataTables monta a tabela lá.
// Escrever cada <tr> com os botões no HTML dava ~3,4 KB por compra: com o
// piloto (1.846 compras) a página passava de 6 MB, acima do limite de 4,5 MB
// de resposta da Vercel. Assim fica em ~0,2 KB por compra, e busca, ordenação
// e paginação continuam no navegador, sobre todas as compras, como antes.
$linhas = array_map(static function (array $c): array {
    $ts = strtotime($c['data_pesagem']);
    return [
        'id'        => (int) $c['id_pesagem'],
        'cliente'   => $c['cliente_nome'] ?: 'Cliente removido',
        'ts'        => $ts,
        'data'      => date('d/m/Y', $ts),
        'hora'      => date('H:i', $ts),
        'peso'      => (float) $c['total_peso'],
        'peso_fmt'  => number_format((float) $c['total_peso'], 2, ',', '.'),
        'valor'     => (float) $c['total_valor'],
        'valor_fmt' => number_format((float) $c['total_valor'], 2, ',', '.'),
        'cancelada' => ($c['status'] ?? 'ativa') === 'cancelada',
    ];
}, $compras);
?>

<div class="container-fluid py-4" style="max-width: 1400px;">

    <?php include 'inc_header_relatorios.php'; ?>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <?php if ($total_compras === 0): ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-inbox display-4 text-muted d-block mb-3" aria-hidden="true"></i>
                <h2 class="h5 text-muted mb-1">Nenhuma compra registrada</h2>
                <p class="text-muted mb-4">Quando você comprar material de um cliente, o histórico aparece aqui.</p>
                <a href="<?= $url_base ?>/compras" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Registrar a primeira compra
                </a>
            </div>
        </div>

    <?php else: ?>

        <!-- Lista -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="h6 fw-semibold mb-0">
                    <i class="bi bi-list-ul me-2" aria-hidden="true"></i>Compras realizadas
                </h2>
                <a href="<?= $url_base ?>/compras" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Nova compra
                </a>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="comprasTable" class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Compra</th>
                                <th>Cliente</th>
                                <th>Data</th>
                                <th class="text-end">Peso</th>
                                <th class="text-end">Valor</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<style>
.card {
    border-radius: 12px;
    overflow: hidden;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}
</style>

<script>
$(function () {
    if (!$('#comprasTable').length) return;

    // JSON_HEX_*: um nome de cliente com tag de fechamento ou aspas não fecha este bloco.
    var compras = <?= json_encode($linhas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    var urlBase = <?= json_encode($url_base, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function esc(texto) {
        return $('<div>').text(texto).html();
    }

    $('#comprasTable').DataTable({
        "data": compras,
        "deferRender": true, // só monta o HTML das linhas da página visível
        "autoWidth": false, // sem isto o DataTables grava um width inline e a tabela encolhe
        "pageLength": 10,
        "lengthMenu": [10, 25, 50, 100],
        // A ordem do SQL não sobrevive ao DataTables: ele reordena pela coluna 2
        // (Data) assim que monta a tabela. O desempate pelo nº da compra precisa
        // estar aqui também, senão as compras de um mesmo dia voltam a sair soltas.
        "order": [[2, "desc"], [0, "desc"]],
        "responsive": true,
        "dom": "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
               "<'row'<'col-sm-12'tr>>" +
               "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        // Compra cancelada sai em cinza, como antes.
        "createdRow": function (tr, c) {
            if (c.cancelada) $(tr).addClass('text-muted');
        },
        "columns": [
            // Ordena pelo id puro: como texto, "#9" viria depois de "#10" e o
            // selo "Cancelada" entraria na comparação.
            { "data": "id", "className": "ps-4 fw-semibold", "render": function (id, tipo, c) {
                if (tipo !== 'display') return tipo === 'filter' ? '#' + id + (c.cancelada ? ' Cancelada' : '') : id;
                return '#' + id + (c.cancelada
                    ? ' <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle ms-1">Cancelada</span>'
                    : '');
            } },
            { "data": "cliente", "render": function (nome, tipo) {
                return tipo === 'display' ? esc(nome) : nome;
            } },
            { "data": "ts", "render": function (ts, tipo, c) {
                if (tipo === 'display') return c.data + '<small class="text-muted d-block">' + c.hora + '</small>';
                return tipo === 'filter' ? c.data + ' ' + c.hora : ts;
            } },
            { "data": "peso", "className": "text-end", "render": function (peso, tipo, c) {
                return tipo === 'display' || tipo === 'filter' ? c.peso_fmt + ' kg' : peso;
            } },
            // Valor riscado quando cancelada: deixa claro na própria linha que
            // aquele número não entra nas somas.
            { "data": "valor", "className": "text-end fw-semibold", "render": function (valor, tipo, c) {
                if (tipo === 'filter') return 'R$ ' + c.valor_fmt;
                if (tipo !== 'display') return valor;
                return c.cancelada
                    ? '<span class="text-decoration-line-through">R$ ' + c.valor_fmt + '</span>'
                    : '<span style="color: var(--color-accent);">R$ ' + c.valor_fmt + '</span>';
            } },
            { "data": null, "orderable": false, "searchable": false, "className": "text-end pe-4", "render": function (_, tipo, c) {
                if (tipo !== 'display') return '';
                var html =
                    '<a href="' + urlBase + '/compras/detalhe?id=' + c.id + '" class="btn btn-sm btn-outline-secondary"' +
                    ' aria-label="Ver detalhes da compra #' + c.id + '"><i class="bi bi-eye me-1" aria-hidden="true"></i> Detalhes</a> ';
                // Exporta a compra no formato da importação de venda, para que o
                // parceiro que nos vendeu lance a operação no sistema dele sem
                // redigitar. Compra cancelada não aparece: o material não mudou
                // de mãos, então não vira venda de ninguém.
                if (!c.cancelada) {
                    html += '<a href="' + urlBase + '/functions/compra/exportar_planilha.php?id=' + c.id + '" class="btn btn-sm btn-outline-secondary"' +
                        ' title="Baixar planilha para o parceiro importar como venda"' +
                        ' aria-label="Exportar a compra #' + c.id + ' como planilha de venda"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i> Exportar</a> ';
                }
                html += '<a href="' + urlBase + '/compras/comprovante?id=' + c.id + '" class="btn btn-sm btn-outline-secondary"' +
                    ' aria-label="Ver comprovante da compra #' + c.id + '"><i class="bi bi-receipt me-1" aria-hidden="true"></i> Comprovante</a>';
                return html;
            } }
        ]
    });
});
</script>
