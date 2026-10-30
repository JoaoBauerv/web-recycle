<?php
/**
 * Tipos de material.
 *
 * Lista única: o cadastro e a edição montam o <select> a partir dela. Antes
 * cada tela tinha a sua cópia, e a de edição ficou sem 'Papel' — editar um
 * material de papel trocava o tipo dele por 'Aluminio', a primeira opção.
 *
 * A chave é o que vai para material.tipo; o valor é o rótulo exibido. A coluna
 * não tem CHECK no banco, então é esta lista que define os tipos válidos.
 */
const MATERIAL_TIPOS = [
    'Aluminio' => 'Alumínio',
    'Cobre'    => 'Cobre',
    'Plastico' => 'Plástico',
    'Papel'    => 'Papel',
    'Outro'    => 'Outro',
];

/**
 * Opções do <select> para um material já gravado.
 *
 * Tipo que não está na lista (base antiga, carga de teste) entra como opção
 * própria. Sem isso o navegador selecionaria a primeira opção e salvar o
 * formulário trocaria o tipo do material sem ninguém pedir.
 */
function materialTiposPara(?string $tipo_atual): array
{
    $tipos = MATERIAL_TIPOS;

    if ($tipo_atual !== null && $tipo_atual !== '' && !isset($tipos[$tipo_atual])) {
        $tipos[$tipo_atual] = $tipo_atual . ' (fora da lista)';
    }

    return $tipos;
}
