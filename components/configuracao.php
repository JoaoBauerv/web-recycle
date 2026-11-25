<?php
/**
 * Configurações do sistema guardadas no banco (tabela configuracao).
 *
 * É chave/valor: cada parâmetro é uma linha, identificada por uma chave com
 * prefixo do módulo (balanca.url, balanca.rota_peso, ...). O Admin altera pela
 * tela /configuracoes; o resto do sistema só lê, por configObter().
 *
 * A leitura é tolerante a falha DE PROPÓSITO: se a tabela ainda não existe
 * (banco sem a migration 2026-11-18_configuracao_sistema.sql), configObter()
 * devolve o padrão em vez de derrubar a tela. Isso importa porque a balança é
 * lida nas telas de compra e de venda: uma configuração ausente não pode
 * impedir o operador de pesar.
 */

/**
 * Todas as configurações salvas, como [chave => valor].
 * Lidas uma vez por requisição; configSalvar() limpa o cache.
 */
function configTodas(PDO $pdo, bool $recarregar = false): array
{
    static $cache = null;

    if ($cache !== null && !$recarregar) {
        return $cache;
    }

    try {
        $cache = $pdo->query("SELECT chave, valor FROM configuracao")
                     ->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        error_log('Configurações indisponíveis (rodou a migration 2026-11-18_configuracao_sistema.sql?): ' . $e->getMessage());
        $cache = [];
    }

    return $cache;
}

/** A chave foi salva alguma vez? Diferencia "salvo vazio" de "nunca salvo". */
function configExiste(PDO $pdo, string $chave): bool
{
    return array_key_exists($chave, configTodas($pdo));
}

/** Valor salvo da chave, ou $padrao quando ela nunca foi salva. */
function configObter(PDO $pdo, string $chave, ?string $padrao = null): ?string
{
    $todas = configTodas($pdo);
    return array_key_exists($chave, $todas) ? $todas[$chave] : $padrao;
}

/**
 * Grava várias chaves de uma vez, numa transação: ou entram todas, ou nenhuma.
 *
 * @param array $valores    [chave => valor]
 * @param int   $id_usuario Admin que fez a alteração
 */
function configSalvar(PDO $pdo, array $valores, int $id_usuario): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO configuracao (chave, valor, data_atualizacao, id_usuario)
         VALUES (:chave, :valor, now(), :id_usuario)
         ON CONFLICT (chave) DO UPDATE
            SET valor = EXCLUDED.valor,
                data_atualizacao = EXCLUDED.data_atualizacao,
                id_usuario = EXCLUDED.id_usuario"
    );

    $pdo->beginTransaction();
    try {
        foreach ($valores as $chave => $valor) {
            $stmt->execute([
                ':chave'      => $chave,
                ':valor'      => $valor === null ? null : (string) $valor,
                ':id_usuario' => $id_usuario,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    configTodas($pdo, true);
}

/** Quando e por quem as chaves com o prefixo foram alteradas pela última vez. */
function configUltimaAlteracao(PDO $pdo, string $prefixo): ?array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT c.data_atualizacao, u.nome
               FROM configuracao c
               LEFT JOIN usuario u ON u.id_usuario = c.id_usuario
              WHERE c.chave LIKE :prefixo
              ORDER BY c.data_atualizacao DESC
              LIMIT 1"
        );
        $stmt->execute([':prefixo' => $prefixo . '%']);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
}
