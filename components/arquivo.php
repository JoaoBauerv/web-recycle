<?php
/**
 * Armazenamento dos arquivos enviados pelos usuários, na tabela `arquivo`.
 *
 * Na Vercel o disco é somente leitura e o /tmp some junto com a instância, então
 * fotos de perfil, comprovantes de venda e a planilha de uma importação em
 * andamento ficam no banco. O nome é a chave, sempre com o prefixo da origem:
 * 'user/<foto>', 'comprovante_venda/<arquivo>' ou 'tmp/<planilha>'.
 */

function arquivoSalvar(PDO $pdo, string $nome, string $caminhoOrigem, string $mime): bool
{
    $conteudo = file_get_contents($caminhoOrigem);
    if ($conteudo === false) {
        return false;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO arquivo (nome, mime, conteudo) VALUES (:nome, :mime, :conteudo)
         ON CONFLICT (nome) DO UPDATE SET mime = EXCLUDED.mime, conteudo = EXCLUDED.conteudo, criado_em = now()"
    );
    $stmt->bindValue(':nome', $nome);
    $stmt->bindValue(':mime', $mime);
    $stmt->bindValue(':conteudo', $conteudo, PDO::PARAM_LOB);
    return $stmt->execute();
}

/** @return array{mime: string, conteudo: string}|null */
function arquivoLer(PDO $pdo, string $nome): ?array
{
    $stmt = $pdo->prepare("SELECT mime, conteudo FROM arquivo WHERE nome = ?");
    $stmt->execute([$nome]);
    $linha = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$linha) {
        return null;
    }
    // O pdo_pgsql devolve bytea como stream.
    if (is_resource($linha['conteudo'])) {
        $linha['conteudo'] = stream_get_contents($linha['conteudo']);
    }
    return $linha;
}

function arquivoExiste(PDO $pdo, string $nome): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM arquivo WHERE nome = ?");
    $stmt->execute([$nome]);
    return (bool) $stmt->fetchColumn();
}

function arquivoApagar(PDO $pdo, string $nome): void
{
    $pdo->prepare("DELETE FROM arquivo WHERE nome = ?")->execute([$nome]);
}

/**
 * Garante que um arquivo guardado também exista no disco local, em $caminho —
 * para bibliotecas que só leem de arquivo (PhpSpreadsheet). Quando a requisição
 * cai numa instância que não tem a cópia, ela é recriada a partir do banco.
 */
function arquivoGarantirLocal(PDO $pdo, string $nome, string $caminho): bool
{
    if (is_file($caminho)) {
        return true;
    }
    $arquivo = arquivoLer($pdo, $nome);
    return $arquivo !== null && file_put_contents($caminho, $arquivo['conteudo']) !== false;
}

/** Envia o arquivo guardado como resposta HTTP. */
function arquivoEnviar(array $arquivo, string $disposicao = 'inline', ?string $nomeDownload = null): void
{
    header('Content-Type: ' . $arquivo['mime']);
    if ($nomeDownload !== null) {
        header('Content-Disposition: ' . $disposicao . '; filename="' . $nomeDownload . '"');
    }
    header('Content-Length: ' . strlen($arquivo['conteudo']));
    header('X-Content-Type-Options: nosniff');
    echo $arquivo['conteudo'];
}
