<?php
// Página atual (definida pelo roteador index2.php); em páginas fora do
// roteador (login, alterar_senha) fica vazia e nenhum item fica "ativo".
$paginaAtual = $pagina ?? '';

function nav_active($slug, $paginaAtual) {
    return $paginaAtual === $slug ? ' active' : '';
}

/**
 * Marca o item pai como ativo em todas as subrotas dele (compras/listar,
 * compras/detalhe, ...), menos nas que já possuem um item próprio no menu —
 * senão os dois acendem ao mesmo tempo.
 */
function nav_active_prefixo($prefixo, $paginaAtual, array $com_item_proprio = []) {
    if (in_array($paginaAtual, $com_item_proprio, true)) {
        return '';
    }
    return str_starts_with($paginaAtual, $prefixo) ? ' active' : '';
}

$logado = !empty($_SESSION['logado']);

// Dados do usuário logado. Ficam nestas variáveis porque o perfil, o painel de
// administração e o início já as consomem — não renomear sem ajustar lá.
$dados_usuario = $dados_usuario ?? ['nome' => '', 'permissao' => '', 'usuario' => ''];
$foto_usuario = 'padrao.png';

if (!empty($_SESSION['usuario'])) {
    $stmt = $pdo->prepare("SELECT * FROM tb_usuario WHERE usuario = :usuario");
    $stmt->bindParam(':usuario', $_SESSION['usuario']);
    $stmt->execute();
    $dados_usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: $dados_usuario;

    // Guarda só o nome do arquivo: quem exibe monta o caminho.
    if (!empty($dados_usuario['foto'])) {
        $foto_usuario = $dados_usuario['foto'];
    }
}

$eh_admin = ($dados_usuario['permissao'] ?? '') === 'Admin';
?>
<button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Abrir menu" aria-controls="appSidebar" aria-expanded="false">
    <i class="bi bi-list fs-4" aria-hidden="true"></i>
</button>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- w-100: sem isto este wrapper é um item flex sem grow dentro do <main>, encolhe
     até o tamanho do conteúdo e as telas com poucas colunas ficam espremidas à
     esquerda em vez de ocupar (e centralizar no) espaço disponível. -->
<div class="d-flex w-100">
    <nav class="sidebar d-flex flex-column p-3 text-white" id="appSidebar" aria-label="Menu principal">

        <a href="<?= $url_base ?>/<?= $logado ? 'index2' : 'index' ?>.php" class="sidebar-brand text-white text-decoration-none">
            <i class="bi bi-recycle" aria-hidden="true"></i>
            <span><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Reciclagem') ?></span>
        </a>

        <ul class="nav nav-pills flex-column mb-auto sidebar-nav">

            <li class="sidebar-grupo">Principal</li>
            <li class="nav-item">
                <a href="<?= $url_base ?>/<?= $logado ? 'index2' : 'index' ?>.php" class="nav-link rounded-3<?= nav_active('inicio', $paginaAtual) ?>">
                    <i class="bi bi-house-door" aria-hidden="true"></i> Início
                </a>
            </li>

            <?php if ($logado): ?>
                <li class="nav-item">
                    <a href="<?= $url_base ?>/calendario" class="nav-link rounded-3<?= nav_active('calendario', $paginaAtual) ?>">
                        <i class="bi bi-calendar3" aria-hidden="true"></i> Calendário
                    </a>
                </li>

                <li class="sidebar-grupo">Operação</li>
                <li class="nav-item">
                    <a href="<?= $url_base ?>/compras" class="nav-link rounded-3<?= nav_active_prefixo('compras', $paginaAtual, ['compras/relatorio']) ?>">
                        <i class="bi bi-cart-plus" aria-hidden="true"></i> Compras
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $url_base ?>/vendas" class="nav-link rounded-3<?= nav_active_prefixo('vendas', $paginaAtual) ?>">
                        <i class="bi bi-cart-check" aria-hidden="true"></i> Vendas
                    </a>
                </li>

                <li class="sidebar-grupo">Cadastros</li>
                <li class="nav-item">
                    <a href="<?= $url_base ?>/clientes" class="nav-link rounded-3<?= nav_active_prefixo('clientes', $paginaAtual) ?>">
                        <i class="bi bi-person-lines-fill" aria-hidden="true"></i> Clientes
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $url_base ?>/fornecedores" class="nav-link rounded-3<?= nav_active_prefixo('fornecedores', $paginaAtual) ?>">
                        <i class="bi bi-truck" aria-hidden="true"></i> Fornecedores
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= $url_base ?>/materiais" class="nav-link rounded-3<?= nav_active_prefixo('materiais', $paginaAtual) ?>">
                        <i class="bi bi-box-seam" aria-hidden="true"></i> Materiais
                    </a>
                </li>

                <li class="sidebar-grupo">Relatórios</li>
                <li class="nav-item">
                    <a href="<?= $url_base ?>/compras/relatorio" class="nav-link rounded-3<?= nav_active('compras/relatorio', $paginaAtual) ?>">
                        <i class="bi bi-bar-chart-line" aria-hidden="true"></i> Relatório de Compras
                    </a>
                </li>

            <?php endif; ?>
        </ul>

        <?php if ($logado): ?>
            <!-- Menu do usuário logado, no rodapé da sidebar -->
            <div class="sidebar-usuario dropdown">
                <button type="button" class="sidebar-usuario-botao dropdown-toggle" data-bs-toggle="dropdown"
                        aria-expanded="false" aria-label="Menu do usuário <?= htmlspecialchars($dados_usuario['nome'] ?? '') ?>">
                    <img src="<?= $url_base ?>/images/user/<?= htmlspecialchars($foto_usuario) ?>"
                         alt="" width="36" height="36" class="rounded-circle flex-shrink-0"
                         style="object-fit: cover;"
                         onerror="this.src='<?= $url_base ?>/images/user/padrao.png'">
                    <span class="sidebar-usuario-info">
                        <span class="sidebar-usuario-nome"><?= htmlspecialchars($dados_usuario['nome'] ?? '') ?></span>
                        <span class="sidebar-usuario-papel"><?= htmlspecialchars($dados_usuario['permissao'] ?? '') ?></span>
                    </span>
                </button>

                <ul class="dropdown-menu dropdown-menu-dark shadow w-100">
                    <li>
                        <a class="dropdown-item" href="<?= $url_base ?>/perfil">
                            <i class="bi bi-person-circle me-2" aria-hidden="true"></i> Meu perfil
                        </a>
                    </li>
                    <?php if ($eh_admin): ?>
                        <li>
                            <a class="dropdown-item" href="<?= $url_base ?>/usuarios">
                                <i class="bi bi-people me-2" aria-hidden="true"></i> Administração
                            </a>
                        </li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="<?= $url_base ?>/functions/user/logout.php">
                            <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i> Sair
                        </a>
                    </li>
                </ul>
            </div>
        <?php else: ?>
            <div class="d-grid mt-3">
                <a href="<?= $url_base ?>/views/user/login.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i> Entrar
                </a>
            </div>
        <?php endif; ?>
    </nav>

    <script>
        (function () {
            var sidebar = document.getElementById('appSidebar');
            var toggle = document.getElementById('sidebarToggle');
            var backdrop = document.getElementById('sidebarBackdrop');
            if (!sidebar || !toggle || !backdrop) return;

            function closeSidebar() {
                sidebar.classList.remove('show');
                backdrop.classList.remove('show');
                toggle.setAttribute('aria-expanded', 'false');
            }

            function openSidebar() {
                sidebar.classList.add('show');
                backdrop.classList.add('show');
                toggle.setAttribute('aria-expanded', 'true');
            }

            toggle.addEventListener('click', function () {
                sidebar.classList.contains('show') ? closeSidebar() : openSidebar();
            });
            backdrop.addEventListener('click', closeSidebar);
            sidebar.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', closeSidebar);
            });
            // Esc fecha o menu aberto no mobile.
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && sidebar.classList.contains('show')) closeSidebar();
            });
        })();
    </script>

    <div class="content d-flex justify-content-center" style="flex: 1;">
