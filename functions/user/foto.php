<?php
/**
 * Entrega a foto de perfil em /images/user/<nome>.
 *
 * As fotos enviadas ficam na tabela `arquivo` (components/arquivo.php), porque
 * na Vercel o disco é somente leitura. O .htaccess e o vercel.json mandam para
 * cá todo pedido em images/user/ que não seja um arquivo real — o padrao.png,
 * que é versionado, continua saindo direto do disco.
 *
 * Sem exigir login, como antes: a foto aparece no menu e na lista de usuários,
 * e a pasta images/user/ sempre foi pública.
 */
require_once __DIR__ . '/../../banco.php';
require_once __DIR__ . '/../../components/arquivo.php';

// basename() impede que o nome escape do prefixo user/.
$nome = basename((string) ($_GET['nome'] ?? ''));
$arquivo = $nome !== '' ? arquivoLer($pdo, 'user/' . $nome) : null;

// Só imagem sai por aqui, mesmo que outra coisa tenha sido gravada com o prefixo.
if ($arquivo === null || !str_starts_with($arquivo['mime'], 'image/')) {
    header('Location: ' . $url_base . '/images/user/padrao.png');
    exit;
}

header('Cache-Control: public, max-age=86400');
arquivoEnviar($arquivo);
