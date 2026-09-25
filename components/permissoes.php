<?php
/**
 * Permissões por ação do módulo de vendas.
 *
 * O sistema já tem os perfis Admin, Gerente e Usuario em tb_usuario.permissao.
 * O roteador só sabia distinguir "é Admin ou não", o que não basta para as ações
 * novas: importar venda e mexer no de/para são de Admin e Gerente, mas registrar
 * uma venda comum continua liberado para todo mundo que já fazia isso.
 *
 * Esta checagem é de servidor. Esconder o botão no menu é conveniência visual —
 * quem digitar a URL direto continua batendo aqui.
 */

const PERMISSOES_POR_ACAO = [
    'venda.visualizar'     => ['Admin', 'Gerente', 'Usuario'],
    'venda.criar'          => ['Admin', 'Gerente', 'Usuario'],
    'venda.importar'       => ['Admin', 'Gerente'],
    'material.relacao'     => ['Admin', 'Gerente'],

    // Ver o estoque é consulta; mexer nele à mão altera saldo sem documento
    // por trás, então fica com quem responde pelo inventário.
    'estoque.visualizar'   => ['Admin', 'Gerente', 'Usuario'],
    'estoque.ajustar'      => ['Admin', 'Gerente'],
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
