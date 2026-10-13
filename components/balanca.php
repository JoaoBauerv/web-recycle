<?php
/**
 * Ponto único de integração da balança digital.
 *
 * Antes desta extração, o painel e o JavaScript viviam dentro de
 * views/compra/index.php, o que obrigaria a copiar as duas coisas em qualquer
 * tela nova que precisasse pesar. Agora a tela só chama balancaPainel().
 *
 * Para usar numa tela:
 *
 *     require_once __DIR__ . '/../../components/balanca.php';
 *     ...
 *     <input type="text" id="peso" name="peso">
 *     <?php balancaPainel('peso', 'tela de compra'); ?>
 *
 * A integração é ADITIVA por princípio: sem BALANCA_API_URL no .env, ou com o
 * agente fora do ar, nada é renderizado e a tela segue com o peso digitado à
 * mão. Nenhuma tela deve depender da balança para operar.
 */

/** Há agente de balança configurado neste ambiente? */
function balancaDisponivel(): bool
{
    return !empty($_ENV['BALANCA_API_URL']);
}

/** URL do agente, sem barra final. Vazia quando não configurado. */
function balancaUrl(): string
{
    return balancaDisponivel() ? rtrim($_ENV['BALANCA_API_URL'], '/') : '';
}

/**
 * Renderiza o painel de leitura e o liga a um campo de peso.
 *
 * @param string $campo_destino id do <input> que recebe o peso capturado.
 * @param string $origem        texto livre que identifica a tela, gravado no
 *                              log do agente a cada captura.
 * @param array  $opcoes        'casas' (decimais, padrão 2),
 *                              'intervalo' (ms entre leituras, padrão 1000),
 *                              'classe' (classes extras no contêiner).
 */
function balancaPainel(string $campo_destino, string $origem, array $opcoes = []): void
{
    if (!balancaDisponivel()) {
        return;
    }

    // Permite mais de um painel na mesma página — por exemplo, uma tela com
    // pesagem de entrada e de saída, ou um modal além do formulário principal.
    static $sequencia = 0;
    $sequencia++;
    $id = 'balanca-' . $sequencia;

    $casas     = $opcoes['casas']     ?? 2;
    $intervalo = $opcoes['intervalo'] ?? 1000;
    $classe    = $opcoes['classe']    ?? '';

    // O script é emitido uma vez só, mesmo com vários painéis na página.
    static $script_incluido = false;
    if (!$script_incluido) {
        $script_incluido = true;
        $url_base = $_ENV['URL_BASE'] ?? '';
        echo '<script src="' . htmlspecialchars($url_base, ENT_QUOTES) . '/js/balanca.js"></script>' . "\n";
    }
    ?>
    <div id="<?= $id ?>" class="border rounded p-2 mb-2 bg-light d-none <?= htmlspecialchars($classe, ENT_QUOTES) ?>">
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <div class="d-flex align-items-center gap-2">
                <span data-balanca="sinal" class="d-inline-block rounded-circle"
                      style="width:10px;height:10px;background:#dc3545;" aria-hidden="true"></span>
                <small class="text-muted" data-balanca="status" role="status" aria-live="polite">
                    Procurando balança...
                </small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <strong data-balanca="visor" class="fs-5">--,-- kg</strong>
                <button type="button" data-balanca="botao" class="btn btn-sm btn-outline-primary" disabled>
                    <i class="bi bi-download me-1" aria-hidden="true"></i> Capturar Peso
                </button>
            </div>
        </div>
    </div>

    <script>
    (function () {
        // A inicialização espera o DOM ficar pronto porque o painel costuma ser
        // renderizado ANTES do campo de peso no formulário. Sem esta espera,
        // getElementById do campo devolveria null e a conexão seria abortada em
        // silêncio — o painel nunca sairia do estado oculto.
        function iniciar() {
            // Guarda a referência para que a tela possa chamar .parar() se
            // precisar, por exemplo ao fechar um modal que contenha o painel.
            window.balancas = window.balancas || {};
            window.balancas[<?= json_encode($id) ?>] = Balanca.conectar({
                api:       <?= json_encode(balancaUrl()) ?>,
                painel:    <?= json_encode($id) ?>,
                campo:     <?= json_encode($campo_destino) ?>,
                origem:    <?= json_encode($origem) ?>,
                casas:     <?= (int) $casas ?>,
                intervalo: <?= (int) $intervalo ?>
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', iniciar);
        } else {
            iniciar();
        }
    })();
    </script>
    <?php
}
