<?php
/**
 * Endereço: um único ponto de leitura e gravação.
 *
 * Até 14/10/2026 o endereço tinha três representações no banco: uma tabela
 * satélite exclusiva de usuário, colunas embutidas em clientes e outras iguais
 * em fornecedores. Hoje existe uma só tabela 'endereco', e cada entidade a
 * referencia por id_endereco.
 *
 * Toda tela que lê ou grava endereço passa por aqui — é isso que mantém as três
 * entidades consistentes sem repetir SQL em cada formulário.
 */

/** Colunas de conteúdo da tabela, na ordem em que aparecem nos formulários. */
function enderecoColunas(): array
{
    return ['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'referencia'];
}

/**
 * Lista de colunas para o SELECT, prefixada pelo alias da tabela.
 *
 * Os nomes saem sem prefixo no resultado (e.cidade vira 'cidade'), de propósito:
 * é a mesma chave que as telas já usavam quando o endereço morava dentro de
 * clientes e fornecedores.
 */
function enderecoSelect(string $alias = 'e'): string
{
    return implode(', ', array_map(fn($c) => "$alias.$c", enderecoColunas()));
}

/** JOIN que pendura o endereço na entidade. LEFT porque endereço é opcional. */
function enderecoJoin(string $entidade, string $alias = 'e'): string
{
    return "LEFT JOIN endereco $alias ON $alias.id_endereco = $entidade.id_endereco";
}

/**
 * Normaliza os campos de endereço vindos de um formulário.
 *
 * O CEP só é aceito com os 8 dígitos; qualquer coisa diferente vira vazio, para
 * não gravar um CEP pela metade.
 */
function enderecoSanitizar(array $dados): array
{
    $limpo = [];

    $cep = preg_replace('/[^0-9]/', '', $dados['cep'] ?? '');
    $limpo['cep'] = strlen($cep) === 8 ? $cep : '';

    foreach (['logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'referencia'] as $campo) {
        $limpo[$campo] = htmlspecialchars(trim($dados[$campo] ?? ''), ENT_QUOTES, 'UTF-8');
    }

    $limpo['estado'] = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $dados['estado'] ?? ''), 0, 2));

    return $limpo;
}

/** Verdadeiro quando não há nada que valha a pena guardar. */
function enderecoVazio(array $endereco): bool
{
    foreach (enderecoColunas() as $coluna) {
        if (trim((string) ($endereco[$coluna] ?? '')) !== '') {
            return false;
        }
    }
    return true;
}

/**
 * Grava o endereço e devolve o id_endereco que a entidade deve apontar.
 *
 * Devolve null quando o formulário veio sem endereço nenhum — e nesse caso
 * remove a linha que existia, para a tabela não acumular endereços vazios. A FK
 * das entidades é ON DELETE SET NULL, então a remoção não derruba o cadastro.
 *
 * @param int|null $id_endereco id atual da entidade, ou null se ela não tem endereço
 */
function enderecoSalvar(PDO $pdo, ?int $id_endereco, array $dados): ?int
{
    $endereco = enderecoSanitizar($dados);

    if (enderecoVazio($endereco)) {
        if ($id_endereco) {
            $pdo->prepare("DELETE FROM endereco WHERE id_endereco = :id")
                ->execute([':id' => $id_endereco]);
        }
        return null;
    }

    $colunas = enderecoColunas();

    if ($id_endereco) {
        $atribuicoes = implode(', ', array_map(fn($c) => "$c = :$c", $colunas));
        $stmt = $pdo->prepare("UPDATE endereco SET $atribuicoes WHERE id_endereco = :id_endereco");
        $stmt->execute($endereco + ['id_endereco' => $id_endereco]);
        return $id_endereco;
    }

    $marcadores = implode(', ', array_map(fn($c) => ":$c", $colunas));
    $stmt = $pdo->prepare("INSERT INTO endereco (" . implode(', ', $colunas) . ")
                           VALUES ($marcadores) RETURNING id_endereco");
    $stmt->execute($endereco);

    return (int) $stmt->fetchColumn();
}

/**
 * Endereço em uma linha, para comprovante e listagem.
 *
 * Aceita tanto a linha de endereco quanto uma entidade já trazida com o JOIN —
 * em ambos os casos as chaves são as mesmas.
 */
function enderecoLinha(?array $endereco): string
{
    if (!$endereco) {
        return '';
    }

    $partes = [];

    $rua = trim(($endereco['logradouro'] ?? '') . (!empty($endereco['numero']) ? ', ' . $endereco['numero'] : ''));
    if ($rua !== '') {
        $partes[] = $rua;
    }
    if (!empty($endereco['complemento'])) {
        $partes[] = $endereco['complemento'];
    }
    if (!empty($endereco['bairro'])) {
        $partes[] = $endereco['bairro'];
    }

    $cidadeUf = trim(($endereco['cidade'] ?? '') . (!empty($endereco['estado']) ? '/' . $endereco['estado'] : ''));
    if ($cidadeUf !== '') {
        $partes[] = $cidadeUf;
    }
    if (!empty($endereco['cep'])) {
        $partes[] = 'CEP ' . $endereco['cep'];
    }

    return implode(' - ', $partes);
}

/**
 * Remove endereços que nenhuma entidade referencia mais, e devolve quantos.
 *
 * Todo endereço pertence a exatamente um usuário, cliente ou fornecedor, então
 * uma linha sem ninguém apontando para ela é sobra — tipicamente de um cadastro
 * apagado direto no banco ou pela limpeza de um seed. O fluxo normal do sistema
 * não deixa órfão: enderecoSalvar() apaga a linha quando o formulário vem vazio.
 */
function enderecoRemoverOrfaos(PDO $pdo): int
{
    return $pdo->exec("DELETE FROM endereco
                        WHERE id_endereco NOT IN (SELECT id_endereco FROM usuario      WHERE id_endereco IS NOT NULL)
                          AND id_endereco NOT IN (SELECT id_endereco FROM clientes     WHERE id_endereco IS NOT NULL)
                          AND id_endereco NOT IN (SELECT id_endereco FROM fornecedores WHERE id_endereco IS NOT NULL)");
}

/** Cidade/UF, usado nas listagens e nos seletores de cliente e fornecedor. */
function enderecoCidadeUf(?array $endereco): string
{
    if (!$endereco) {
        return '';
    }
    return trim(($endereco['cidade'] ?? '') . '/' . ($endereco['estado'] ?? ''), '/');
}
