<?php
/**
 * Permissões por ação do módulo de vendas.
 *
 * O sistema tem dois perfis em tb_usuario.permissao: Admin e Usuario.
 * O roteador só sabia distinguir "é Admin ou não" no nível da rota; aqui a
 * permissão é por AÇÃO, o que permite liberar uma tela e restringir o que se
 * pode fazer dentro dela.
 *
 * Esta checagem é de servidor. Esconder o botão no menu é conveniência visual —
 * quem digitar a URL direto continua batendo aqui.
 */

const PERMISSOES_POR_ACAO = [
    'venda.visualizar'     => ['Admin', 'Usuario'],
    'venda.criar'          => ['Admin', 'Usuario'],
    'venda.importar'       => ['Admin'],
    'material.relacao'     => ['Admin'],

    // Ver o estoque é consulta; mexer nele à mão altera saldo sem documento
    // por trás, então fica com quem responde pelo inventário.
    'estoque.visualizar'   => ['Admin', 'Usuario'],
    'estoque.ajustar'      => ['Admin'],

    // Financeiro: margem e despesas expõem quanto a empresa ganha e gasta,
    // então nada aqui é liberado para o perfil Usuario.
    'financeiro.margem'          => ['Admin'],
    'financeiro.margem_detalhe'  => ['Admin'],
    'financeiro.margem_exportar' => ['Admin'],
    'financeiro.contas'          => ['Admin'],
    'financeiro.conta_gerenciar' => ['Admin'],
    'financeiro.conta_cancelar'  => ['Admin'],
    'financeiro.pagamento'       => ['Admin'],
    'financeiro.categorias'      => ['Admin'],
    'financeiro.relatorios'      => ['Admin'],
];

/** O usuário logado pode executar a ação? */
function usuarioPode(string $acao): bool
{
    $perfil = $_SESSION['permissao'] ?? '';

    if (!isset(PERMISSOES_POR_ACAO[$acao])) {
        return false;   // ação desconhecida nega por padrão
    }

    return in_array($perfil, PERMISSOES_POR_ACAO[$acao], true);
}

/** Interrompe a requisição quando o usuário não tem a permissão. */
function exigirPermissao(string $acao, string $url_base): void
{
    if (usuarioPode($acao)) {
        return;
    }

    header('Location: ' . $url_base . '/vendas?msgErro=' . urlencode('Você não tem permissão para esta ação.'));
    exit;
}
