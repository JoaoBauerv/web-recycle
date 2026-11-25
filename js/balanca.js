/**
 * Integração com o agente de balança (projeto balanca-agent, em Python).
 *
 * O agente roda na máquina do operador, lê a porta serial do equipamento e
 * expõe duas rotas locais: GET /peso, que devolve a leitura atual, e
 * POST /captura, que apenas registra no log dele que o peso foi aproveitado.
 *
 * Outro software de integração pode usar rotas e nomes de campo diferentes:
 * cfg.rotaPeso, cfg.rotaCaptura e cfg.campos cobrem isso, e o Admin os ajusta
 * em Configurações do sistema (components/balanca.php repassa os valores).
 *
 * Tudo aqui é ADITIVO: se o agente não estiver no ar, o painel some e a tela
 * continua funcionando com o peso digitado à mão. Nenhuma tela deve depender
 * da balança para operar.
 *
 * Uso:
 *     const leitura = Balanca.conectar({
 *         api:    'http://127.0.0.1:5000',
 *         painel: 'painel-balanca-1',
 *         campo:  'peso',
 *         origem: 'tela de compra'
 *     });
 *     leitura.parar();   // encerra o polling (ex.: ao fechar um modal)
 */
(function (global) {
    'use strict';

    var INTERVALO_PADRAO = 1000;
    var CASAS_PADRAO = 2;

    // Formato do agente do próprio projeto (balanca-agent).
    var CAMPOS_PADRAO = {
        sucesso: 'sucesso',
        peso:    'peso',
        estavel: 'estavel',
        unidade: 'unidade',
        erro:    'erro'
    };

    /**
     * Lê um campo da resposta. Aceita caminho com ponto para JSON aninhado
     * ("dados.peso", "leituras.0.valor"). Caminho vazio devolve undefined.
     */
    function lerCampo(obj, caminho) {
        if (!caminho) return undefined;
        var partes = String(caminho).split('.');
        var atual = obj;
        for (var i = 0; i < partes.length; i++) {
            if (atual === null || atual === undefined) return undefined;
            atual = atual[partes[i]];
        }
        return atual;
    }

    /** "12,50", "12.50 kg" e 12.5 viram 12.5; o que não for número vira null. */
    function paraNumero(valor) {
        if (typeof valor === 'number') return isFinite(valor) ? valor : null;
        if (typeof valor !== 'string') return null;
        var limpo = valor.replace(/[^0-9,.\-]/g, '');
        // Com vírgula e ponto, o último dos dois é o separador decimal.
        if (limpo.indexOf(',') !== -1 && limpo.indexOf('.') !== -1) {
            limpo = limpo.lastIndexOf(',') > limpo.lastIndexOf('.')
                ? limpo.replace(/\./g, '').replace(',', '.')
                : limpo.replace(/,/g, '');
        } else {
            limpo = limpo.replace(',', '.');
        }
        var n = parseFloat(limpo);
        return isNaN(n) ? null : n;
    }

    /** true/false, 1/0, "S"/"N", "sim", "stable"... */
    function paraBooleano(valor) {
        if (typeof valor === 'boolean') return valor;
        if (typeof valor === 'number') return valor !== 0;
        if (typeof valor === 'string') {
            return /^(true|1|s|sim|y|yes|st|stable|estavel|estável|ok)$/i.test(valor.trim());
        }
        return false;
    }

    /**
     * Traduz a resposta de qualquer software para o formato interno
     * { sucesso, peso, unidade, estavel, erro }.
     *
     * Campo de sucesso vazio: a leitura vale sempre que houver peso numérico.
     * Campo de estabilidade vazio: todo peso é tratado como estável.
     * Campo de unidade vazio: kg.
     */
    function interpretar(dados, campos) {
        campos = Object.assign({}, CAMPOS_PADRAO, campos || {});

        // Software que devolve só o número no corpo, sem JSON em volta.
        if (typeof dados === 'number' || typeof dados === 'string') {
            dados = { valor: dados };
            campos = Object.assign({}, campos, { peso: 'valor', sucesso: '', estavel: '', unidade: '', erro: '' });
        }

        var peso = paraNumero(lerCampo(dados, campos.peso));
        var sucesso = campos.sucesso
            ? paraBooleano(lerCampo(dados, campos.sucesso)) && peso !== null
            : peso !== null;

        var erro = lerCampo(dados, campos.erro);
        var unidade = lerCampo(dados, campos.unidade);

        return {
            sucesso: sucesso,
            peso:    sucesso ? peso : null,
            unidade: (typeof unidade === 'string' && unidade.trim()) ? unidade.trim() : 'kg',
            estavel: campos.estavel ? paraBooleano(lerCampo(dados, campos.estavel)) : true,
            erro:    sucesso ? null
                             : (typeof erro === 'string' && erro ? erro
                                : (peso === null ? 'Resposta sem peso no campo "' + campos.peso + '"' : null))
        };
    }

    /** Junta a URL base com a rota, sem barra dobrada nem faltando. */
    function montarUrl(api, rota) {
        rota = String(rota || '');
        if (!rota) return api;
        return api.replace(/\/+$/, '') + '/' + rota.replace(/^\/+/, '');
    }

    /** Lê o corpo como JSON e, se não for JSON, como texto puro. */
    async function lerCorpo(resposta) {
        var texto = await resposta.text();
        try {
            return JSON.parse(texto);
        } catch (e) {
            return texto.trim();
        }
    }

    function elemento(ref) {
        if (!ref) return null;
        return typeof ref === 'string' ? document.getElementById(ref) : ref;
    }

    function formatarPeso(valor, unidade, casas) {
        var u = unidade || 'kg';
        if (valor === null || valor === undefined || isNaN(valor)) {
            return '--,-- ' + u;
        }
        return Number(valor).toLocaleString('pt-BR', {
            minimumFractionDigits: casas,
            maximumFractionDigits: casas
        }) + ' ' + u;
    }

    /**
     * Liga um painel de balança a um campo de peso.
     *
     * cfg.api      URL do agente, sem barra final
     * cfg.painel   id (ou elemento) do contêiner do painel
     * cfg.campo    id (ou elemento) do input que recebe o peso capturado
     * cfg.origem   texto livre identificando a tela, para o log do agente
     * cfg.sinal    id do indicador luminoso        (opcional)
     * cfg.status   id do texto de situação         (opcional)
     * cfg.visor    id do visor de peso             (opcional)
     * cfg.botao    id do botão de captura          (opcional)
     * cfg.casas    casas decimais                  (opcional, padrão 2)
     * cfg.intervalo  período do polling em ms      (opcional, padrão 1000)
     * cfg.aoCapturar  callback(peso, dados)        (opcional)
 * cfg.rotaPeso     rota de leitura              (opcional, padrão '/peso')
 * cfg.rotaCaptura  rota que registra a captura  (opcional, padrão '/captura';
 *                  vazia = não avisa o agente)
 * cfg.campos       nomes dos campos da resposta (opcional; ver CAMPOS_PADRAO)
     *
     * Devolve { parar, capturar, ultimaLeitura } ou null quando a configuração
     * mínima não está presente na página.
     */
    function conectar(cfg) {
        cfg = cfg || {};

        var api = (cfg.api || '').replace(/\/+$/, '');
        var painel = elemento(cfg.painel);
        var campo = elemento(cfg.campo);

        // Sem agente configurado a tela simplesmente segue no modo manual, e
        // isso não é erro. Já painel ou campo ausentes indicam ligação errada
        // na tela — avisa no console, porque a falha silenciosa aqui é difícil
        // de diagnosticar: o painel apenas nunca aparece.
        if (!api) return null;
        if (!painel || !campo) {
            console.warn('[balanca] não conectada:',
                !painel ? 'painel ' + JSON.stringify(cfg.painel) + ' não encontrado'
                        : 'campo ' + JSON.stringify(cfg.campo) + ' não encontrado',
                '— confira se o elemento existe e se a inicialização ocorre após o DOM pronto.');
            return null;
        }

        var sinal  = elemento(cfg.sinal  || painel.querySelector('[data-balanca="sinal"]'));
        var status = elemento(cfg.status || painel.querySelector('[data-balanca="status"]'));
        var visor  = elemento(cfg.visor  || painel.querySelector('[data-balanca="visor"]'));
        var botao  = elemento(cfg.botao  || painel.querySelector('[data-balanca="botao"]'));

        var casas = cfg.casas === undefined ? CASAS_PADRAO : cfg.casas;
        var intervalo = cfg.intervalo || INTERVALO_PADRAO;
        var rotaPeso = cfg.rotaPeso === undefined ? '/peso' : cfg.rotaPeso;
        var rotaCaptura = cfg.rotaCaptura === undefined ? '/captura' : cfg.rotaCaptura;
        var campos = cfg.campos;

        var ultima = null;
        var consultando = false;   // evita requisições sobrepostas quando o agente demora
        var timer = null;
        var encerrado = false;

        function pintar(cor) {
            if (sinal) sinal.style.background = cor;
        }

        function offline() {
            ultima = null;
            painel.classList.add('d-none');
            if (botao) botao.disabled = true;
        }

        function semPeso(motivo) {
            ultima = null;
            pintar('#dc3545');
            if (status) status.textContent = motivo || 'Balança não conectada';
            if (visor) visor.textContent = formatarPeso(null, null, casas);
            if (botao) botao.disabled = true;
        }

        async function consultar() {
            if (consultando || encerrado) return;
            consultando = true;

            var dados;
            try {
                var resposta = await fetch(montarUrl(api, rotaPeso), { cache: 'no-store' });
                dados = interpretar(await lerCorpo(resposta), campos);
            } catch (e) {
                offline();            // agente fora do ar: volta ao fluxo manual
                consultando = false;
                return;
            }

            consultando = false;
            if (encerrado) return;

            painel.classList.remove('d-none');

            if (!dados || !dados.sucesso) {
                semPeso(dados && dados.erro);
                return;
            }

            ultima = dados;
            if (visor) visor.textContent = formatarPeso(dados.peso, dados.unidade, casas);
            if (botao) botao.disabled = false;

            if (dados.estavel) {
                pintar('#198754');
                if (status) status.textContent = 'Balança conectada — peso estável';
            } else {
                pintar('#ffc107');
                if (status) status.textContent = 'Balança conectada — estabilizando...';
            }
        }

        function capturar() {
            if (!ultima || ultima.peso === null || ultima.peso === undefined) return false;

            campo.value = Number(ultima.peso).toFixed(casas);
            // Avisa os cálculos já existentes na tela (peso líquido, subtotal).
            campo.dispatchEvent(new Event('input', { bubbles: true }));

            if (typeof cfg.aoCapturar === 'function') {
                cfg.aoCapturar(ultima.peso, ultima);
            }

            // Só para o agente registrar a captura no log dele; falha é irrelevante.
            // Outro software pode não ter essa rota: vazia na configuração, pula.
            if (!rotaCaptura) return true;
            fetch(montarUrl(api, rotaCaptura), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ peso: ultima.peso, origem: cfg.origem || '' })
            }).catch(function () {});

            return true;
        }

        if (botao) botao.addEventListener('click', capturar);

        consultar();
        timer = setInterval(consultar, intervalo);

        return {
            parar: function () {
                encerrado = true;
                if (timer) clearInterval(timer);
                timer = null;
            },
            capturar: capturar,
            ultimaLeitura: function () { return ultima; }
        };
    }

    global.Balanca = {
        conectar: conectar,
        formatarPeso: formatarPeso,
        interpretar: interpretar,
        montarUrl: montarUrl,
        lerCorpo: lerCorpo
    };
})(window);
