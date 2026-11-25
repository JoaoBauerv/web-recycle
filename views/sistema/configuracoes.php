<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

require_once __DIR__ . '/../../components/permissoes.php';
require_once __DIR__ . '/../../components/configuracao.php';
require_once __DIR__ . '/../../components/balanca.php';
require_once __DIR__ . '/../../functions/funcoes.php';

exigirPermissao('sistema.configurar', $url_base);

$rota_tela = $url_base . '/configuracoes';

/**
 * Valida o que veio do formulário e devolve [valores para gravar, erros].
 * Os valores saem já normalizados (sem barra final na URL, rota com "/").
 */
function configuracaoBalancaValidar(array $post): array
{
    $erros = [];

    $url = rtrim(trim($post['url'] ?? ''), '/');
    if ($url !== '' && !preg_match('#^https?://[^\s/$.?\#][^\s]*$#i', $url)) {
        $erros[] = 'Endereço do programa da balança inválido. Use o formato http://127.0.0.1:5000.';
    }

    $rota = function (string $nome, string $rotulo, bool $obrigatoria) use ($post, &$erros): string {
        $valor = trim($post[$nome] ?? '');
        if ($valor === '') {
            if ($obrigatoria) {
                $erros[] = "Informe a $rotulo.";
            }
            return '';
        }
        if (preg_match('#\s|^[a-z]+://#i', $valor)) {
            $erros[] = "A $rotulo é só o caminho (ex.: /peso), sem espaço nem o endereço do programa.";
            return $valor;
        }
        return '/' . ltrim($valor, '/');
    };

    $campo = function (string $nome, string $rotulo, bool $obrigatorio) use ($post, &$erros): string {
        $valor = trim($post[$nome] ?? '');
        if ($valor === '' && $obrigatorio) {
            $erros[] = "Informe o campo de $rotulo.";
        } elseif ($valor !== '' && !preg_match('/^[A-Za-z0-9_\-]+(\.[A-Za-z0-9_\-]+)*$/', $valor)) {
            $erros[] = "Campo de $rotulo inválido: use letras, números, _ e ponto para JSON aninhado (ex.: dados.peso).";
        }
        return $valor;
    };

    $intervalo = filter_var($post['intervalo'] ?? '', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => BALANCA_INTERVALO_MIN, 'max_range' => BALANCA_INTERVALO_MAX],
    ]);
    if ($intervalo === false) {
        $erros[] = sprintf('O intervalo de leitura deve ser entre %d e %d ms.', BALANCA_INTERVALO_MIN, BALANCA_INTERVALO_MAX);
    }

    $valores = [
        'balanca.ativa'         => !empty($post['ativa']) ? '1' : '0',
        'balanca.url'           => $url,
        'balanca.rota_peso'     => $rota('rota_peso', 'rota de leitura do peso', true),
        'balanca.rota_captura'  => $rota('rota_captura', 'rota de registro da captura', false),
        'balanca.campo_sucesso' => $campo('campo_sucesso', 'sucesso', false),
        'balanca.campo_peso'    => $campo('campo_peso', 'peso', true),
        'balanca.campo_estavel' => $campo('campo_estavel', 'estabilidade', false),
        'balanca.campo_unidade' => $campo('campo_unidade', 'unidade', false),
        'balanca.campo_erro'    => $campo('campo_erro', 'mensagem de erro', false),
        'balanca.intervalo'     => (string) ($intervalo ?: BALANCA_INTERVALO_MIN),
    ];

    if ($valores['balanca.ativa'] === '1' && $url === '') {
        $erros[] = 'Para ligar a balança, informe o endereço do programa de integração.';
    }

    return [$valores, $erros];
}

// ------------------------------------------------------------------ POST
// O token CSRF já foi conferido pelo roteador (csrfExigirEmPost).
$erros = [];
$form  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['acao'] ?? '') === 'restaurar_balanca') {
        // Volta aos valores de fábrica, mantendo o endereço e o liga/desliga:
        // quem restaura quer desfazer rotas e campos, não perder a conexão.
        $atual = balancaConfig();
        $padroes = [];
        foreach (BALANCA_PADROES as $nome => $padrao) {
            $padroes['balanca.' . $nome] = $padrao;
        }
        $padroes['balanca.url']   = $atual['url'];
        $padroes['balanca.ativa'] = $atual['ativa'] ? '1' : '0';

        try {
            configSalvar($pdo, $padroes, (int) $_SESSION['id_usuario']);
            registraMovimentacao($_SESSION['id_usuario'], $_SESSION['id_usuario'],
                'Restaurou rotas e campos padrão da balança', 'Configuração', $pdo);
            header('Location: ' . $rota_tela . '?msgSucesso=' . urlencode('Rotas e campos da balança voltaram ao padrão.'));
        } catch (PDOException $e) {
            error_log('Falha ao restaurar configuração da balança: ' . $e->getMessage());
            header('Location: ' . $rota_tela . '?msgErro=' . urlencode('Não foi possível salvar. A tabela configuracao existe? Veja sql/README.md.'));
        }
        exit;
    }

    [$valores, $erros] = configuracaoBalancaValidar($_POST);

    if (!$erros) {
        try {
            configSalvar($pdo, $valores, (int) $_SESSION['id_usuario']);
            registraMovimentacao($_SESSION['id_usuario'], $_SESSION['id_usuario'],
                sprintf('Balança %s: %s%s', $valores['balanca.ativa'] === '1' ? 'ligada' : 'desligada',
                        $valores['balanca.url'] ?: '(sem endereço)', $valores['balanca.rota_peso']),
                'Configuração', $pdo);
            header('Location: ' . $rota_tela . '?msgSucesso=' . urlencode('Configurações da balança salvas.'));
            exit;
        } catch (PDOException $e) {
            error_log('Falha ao salvar configuração da balança: ' . $e->getMessage());
            $erros[] = 'Não foi possível salvar. A tabela configuracao existe? Veja sql/README.md.';
        }
    }

    // Com erro, a tela volta com o que foi digitado, não com o que está salvo.
    $form = [];
    foreach ($valores as $chave => $valor) {
        $form[substr($chave, strlen('balanca.'))] = $valor;
    }
    $form['ativa'] = $valores['balanca.ativa'] === '1';
}

// ------------------------------------------------------------------ tela
$cfg = $form ?? balancaConfig();
$url_do_env = !configExiste($pdo, 'balanca.url') && !empty($_ENV['BALANCA_API_URL']);
$ultima = configUltimaAlteracao($pdo, 'balanca.');

$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<div class="container-fluid py-4" style="max-width: 1000px;">

    <div class="mb-4">
        <h2 class="mb-1 fw-bold">
            <i class="bi bi-gear me-2" style="color: var(--color-accent);" aria-hidden="true"></i>Configurações do sistema
        </h2>
        <p class="text-muted mb-0">Parâmetros que valem para todos os usuários. Só administradores veem esta tela.</p>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <?php if ($erros): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Não foi possível salvar:</strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($erros as $erro): ?>
                    <li><?= $h($erro) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= $h($rota_tela) ?>" id="form-balanca" novalidate>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h3 class="h6 fw-semibold mb-0">
                        <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i> Balança
                    </h3>
                    <small class="text-muted">De onde as telas de compra e de venda leem o peso.</small>
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="ativa" name="ativa" value="1"
                           <?= $cfg['ativa'] ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="ativa">Usar balança</label>
                </div>
            </div>

            <div class="card-body">
                <p class="small text-muted">
                    A balança é lida por um programa de integração que roda no computador do balcão
                    (o <code>balanca-agent</code> do projeto ou outro software). O navegador de quem
                    está pesando consulta esse programa, então o endereço é o que <strong>aquele computador</strong>
                    enxerga — normalmente <code>http://127.0.0.1:porta</code>. Com a balança desligada ou fora
                    do ar, as telas continuam aceitando o peso digitado.
                </p>

                <?php if ($url_do_env): ?>
                    <div class="alert alert-info small py-2">
                        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                        O endereço abaixo vem de <code>BALANCA_API_URL</code> no <code>.env</code>. Ao salvar,
                        passa a valer o que estiver nesta tela.
                    </div>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-12">
                        <label for="url" class="form-label fw-medium">Endereço do programa de integração</label>
                        <input type="url" class="form-control" id="url" name="url" maxlength="255"
                               placeholder="http://127.0.0.1:5000" value="<?= $h($cfg['url']) ?>">
                        <div class="form-text">Sem a rota no final — ela vai no campo abaixo.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="rota_peso" class="form-label fw-medium">Rota de leitura do peso</label>
                        <div class="input-group">
                            <span class="input-group-text">GET</span>
                            <input type="text" class="form-control font-monospace" id="rota_peso" name="rota_peso"
                                   maxlength="200" placeholder="/peso" value="<?= $h($cfg['rota_peso']) ?>" required>
                        </div>
                        <div class="form-text">Consultada a cada intervalo para mostrar o peso no visor.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="rota_captura" class="form-label fw-medium">Rota de registro da captura <span class="text-muted fw-normal">(opcional)</span></label>
                        <div class="input-group">
                            <span class="input-group-text">POST</span>
                            <input type="text" class="form-control font-monospace" id="rota_captura" name="rota_captura"
                                   maxlength="200" placeholder="/captura" value="<?= $h($cfg['rota_captura']) ?>">
                        </div>
                        <div class="form-text">Avisa o programa que o peso foi usado. Deixe vazio se ele não tiver essa rota.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="intervalo" class="form-label fw-medium">Intervalo entre leituras</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="intervalo" name="intervalo"
                                   min="<?= BALANCA_INTERVALO_MIN ?>" max="<?= BALANCA_INTERVALO_MAX ?>" step="100"
                                   value="<?= (int) $cfg['intervalo'] ?>" required>
                            <span class="input-group-text">ms</span>
                        </div>
                        <div class="form-text">1000 ms = uma leitura por segundo.</div>
                    </div>
                </div>

                <hr class="my-4">

                <h4 class="h6 fw-semibold mb-1">Campos da resposta</h4>
                <p class="small text-muted mb-3">
                    Nome de cada informação no JSON que a rota de leitura devolve. Para JSON aninhado use ponto
                    (<code>dados.peso</code>). Se o programa devolver só o número, sem JSON, basta o campo de peso.
                </p>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="campo_peso" class="form-label fw-medium">Peso</label>
                        <input type="text" class="form-control font-monospace" id="campo_peso" name="campo_peso"
                               maxlength="100" placeholder="peso" value="<?= $h($cfg['campo_peso']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="campo_estavel" class="form-label fw-medium">Peso estável <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="text" class="form-control font-monospace" id="campo_estavel" name="campo_estavel"
                               maxlength="100" placeholder="estavel" value="<?= $h($cfg['campo_estavel']) ?>">
                        <div class="form-text">Vazio: todo peso conta como estável.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="campo_unidade" class="form-label fw-medium">Unidade <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="text" class="form-control font-monospace" id="campo_unidade" name="campo_unidade"
                               maxlength="100" placeholder="unidade" value="<?= $h($cfg['campo_unidade']) ?>">
                        <div class="form-text">Vazio: kg.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="campo_sucesso" class="form-label fw-medium">Leitura válida <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="text" class="form-control font-monospace" id="campo_sucesso" name="campo_sucesso"
                               maxlength="100" placeholder="sucesso" value="<?= $h($cfg['campo_sucesso']) ?>">
                        <div class="form-text">Vazio: vale sempre que vier um peso.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="campo_erro" class="form-label fw-medium">Mensagem de erro <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="text" class="form-control font-monospace" id="campo_erro" name="campo_erro"
                               maxlength="100" placeholder="erro" value="<?= $h($cfg['campo_erro']) ?>">
                    </div>
                </div>

                <!-- Teste feito pelo navegador, e não pelo servidor: é o navegador
                     do balcão que fala com o programa da balança, e o servidor
                     (ainda mais na nuvem) não alcança o 127.0.0.1 de quem pesa. -->
                <div class="border rounded p-3 mt-4 bg-light">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <strong class="d-block">Testar conexão</strong>
                            <small class="text-muted">Usa os valores preenchidos acima, mesmo antes de salvar, a partir deste computador.</small>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-testar-balanca">
                            <i class="bi bi-plug me-1" aria-hidden="true"></i> Testar agora
                        </button>
                    </div>
                    <div id="teste-balanca-resultado" class="mt-3 d-none" role="status" aria-live="polite"></div>
                </div>
            </div>

            <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <small class="text-muted">
                    <?php if ($ultima): ?>
                        Última alteração em <?= $h(date('d/m/Y H:i', strtotime($ultima['data_atualizacao']))) ?>
                        <?= $ultima['nome'] ? 'por ' . $h($ultima['nome']) : '' ?>.
                    <?php else: ?>
                        Ainda não salva por esta tela.
                    <?php endif; ?>
                </small>
                <div class="d-flex gap-2">
                    <button type="submit" form="form-restaurar-balanca" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i> Restaurar padrão
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i> Salvar
                    </button>
                </div>
            </div>
        </div>
    </form>

    <form method="POST" action="<?= $h($rota_tela) ?>" id="form-restaurar-balanca"
          onsubmit="return confirm('Voltar rotas e campos da balança para o padrão do balanca-agent? O endereço é mantido.');">
        <input type="hidden" name="acao" value="restaurar_balanca">
    </form>
</div>

<script src="<?= $h($url_base) ?>/js/balanca.js"></script>
<script>
(function () {
    var botao = document.getElementById('btn-testar-balanca');
    var saida = document.getElementById('teste-balanca-resultado');

    function valor(id) { return document.getElementById(id).value.trim(); }

    function escapar(texto) {
        var div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    function mostrar(classe, titulo, detalhe, bruto) {
        saida.className = 'mt-3 alert ' + classe + ' small mb-0';
        saida.innerHTML = '<strong>' + escapar(titulo) + '</strong>'
            + (detalhe ? '<div class="mt-1">' + detalhe + '</div>' : '')
            + (bruto !== undefined
                ? '<details class="mt-2"><summary>Resposta recebida</summary><pre class="mb-0 mt-1 small">'
                  + escapar(typeof bruto === 'string' ? bruto : JSON.stringify(bruto, null, 2)) + '</pre></details>'
                : '');
    }

    botao.addEventListener('click', async function () {
        var api = valor('url').replace(/\/+$/, '');
        if (!api) {
            mostrar('alert-warning', 'Informe o endereço do programa de integração.');
            return;
        }

        var endereco = Balanca.montarUrl(api, valor('rota_peso'));
        botao.disabled = true;
        mostrar('alert-secondary', 'Consultando ' + endereco + '...');

        var controle = new AbortController();
        var limite = setTimeout(function () { controle.abort(); }, 5000);

        try {
            var resposta = await fetch(endereco, { cache: 'no-store', signal: controle.signal });
            var bruto = await Balanca.lerCorpo(resposta);
            var leitura = Balanca.interpretar(bruto, {
                sucesso: valor('campo_sucesso'),
                peso:    valor('campo_peso'),
                estavel: valor('campo_estavel'),
                unidade: valor('campo_unidade'),
                erro:    valor('campo_erro')
            });

            if (!resposta.ok) {
                mostrar('alert-danger', 'O programa respondeu HTTP ' + resposta.status + '.',
                    'Confira a rota de leitura do peso.', bruto);
            } else if (leitura.sucesso) {
                mostrar('alert-success', 'Conectado: ' + Balanca.formatarPeso(leitura.peso, leitura.unidade, 2)
                        + (leitura.estavel ? ' (estável)' : ' (estabilizando)'),
                    'O sistema entendeu a resposta com os campos configurados.', bruto);
            } else {
                mostrar('alert-warning', 'O programa respondeu, mas sem peso válido.',
                    escapar(leitura.erro || 'Confira os nomes dos campos da resposta.'), bruto);
            }
        } catch (e) {
            mostrar('alert-danger', 'Sem resposta de ' + endereco + '.',
                e.name === 'AbortError'
                    ? 'Passou de 5 segundos sem resposta.'
                    : 'O programa está aberto neste computador? Se estiver, ele precisa liberar CORS para este site (cabeçalho Access-Control-Allow-Origin).');
        } finally {
            clearTimeout(limite);
            botao.disabled = false;
        }
    });
})();
</script>
