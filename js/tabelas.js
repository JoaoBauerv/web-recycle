/**
 * Ajustes comuns das tabelas (DataTables).
 *
 * Carregado uma vez pelo layout, depois do dataTables.js e antes das telas, e
 * aplicado via $.fn.dataTable.defaults -- entao cada tela so declara o que lhe
 * e proprio (ordem inicial, colunas sem ordenacao, tamanho da pagina).
 *
 * POR QUE O IDIOMA ESTA AQUI, E NAO VEM DO CDN
 *
 * O arquivo pt-BR.json oficial traz "decimal": "," e "thousands": ".". Essas
 * duas chaves nao mudam o texto da tela: elas dizem ao DataTables COMO LER um
 * numero na hora de ordenar. Com elas, o tipo da coluna vira 'num,' e o ponto
 * passa a ser lido como separador de milhar.
 *
 * O problema e que os data-order deste projeto sao numeros do PHP, com ponto
 * decimal. Com o ponto valendo como milhar, "23190.2" virava 231.902 e
 * "3419.094" virava 3.419.094 -- a ordem passava a sair pelo numero de casas
 * decimais, nao pelo valor. Na tela de estoque isso punha um material de
 * R$ 3.419,09 acima de um de R$ 23.190,20.
 *
 * Sem as duas chaves, o DataTables usa o ponto como decimal, que e o que os
 * data-order realmente trazem. Nenhuma coluna depende do texto formatado para
 * ordenar: as que exibem "R$ 1.234,56" todas tem data-order proprio, e a unica
 * sem data-order (ESTOQUE, em materiais) mostra o numero cru, tambem com ponto.
 *
 * De quebra, o idioma deixa de depender da rede: antes, sem internet, toda
 * tabela do sistema esperava um arquivo que nunca chegava.
 */
(function () {
    'use strict';

    if (!window.jQuery || !jQuery.fn || !jQuery.fn.dataTable) {
        return;
    }

    var IDIOMA_PT_BR = {
            "aria": {
                    "sortAscending": ": Ordenar colunas de forma ascendente",
                    "sortDescending": ": Ordenar colunas de forma descendente"
            },
            "emptyTable": "Nenhum registro encontrado",
            "info": "Mostrando de _START_ até _END_ de _TOTAL_ registros",
            "infoEmpty": "Mostrando 0 até 0 de 0 registro(s)",
            "infoFiltered": "(Filtrados de _MAX_ registros)",
            "infoThousands": ".",
            "lengthMenu": "Exibir _MENU_ resultados por página",
            "loadingRecords": "Carregando...",
            "paginate": {
                    "first": "Primeiro",
                    "last": "Último",
                    "next": "Próximo",
                    "previous": "Anterior"
            },
            "processing": "Carregando...",
            "search": "Pesquisar",
            "searchPlaceholder": "Buscar registros",
            "zeroRecords": "Nenhum registro encontrado"
    };

    jQuery.extend(true, jQuery.fn.dataTable.defaults, {
        language: IDIOMA_PT_BR
    });
}());
