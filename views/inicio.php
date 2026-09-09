<div class="container-fluid min-vh-100 d-flex justify-content-center align-items-center bg-light">
    <div class="card shadow-lg border-0 p-4 w-100" style="max-width: 900px; border-radius: 1rem;">
        
        <!-- Cabeçalho de boas-vindas -->
        <div class="text-center mb-4">
            <h1 class="fw-bold text-success">
                Bem-vindo, <?= htmlspecialchars($dados_usuario['nome'] ?? 'Visitante') ?>!
            </h1>
            <p class="text-muted fs-5">Gerencie suas vendas e acompanhe seu impacto no meio ambiente</p>
        </div>

        <!-- Área de métricas / atalhos -->
        <div class="row g-4 text-center">
            <!-- Total de vendas -->
            <div class="col-md-4">
                <div class="p-3 bg-white rounded shadow-sm h-100 border-start border-4 border-success">
                    <i class="bi bi-cash-stack fs-2 text-success"></i>
                    <h5 class="mt-2">Minhas Vendas</h5>
                    <p class="text-muted mb-0">Consulte valores e histórico</p>
                </div>
            </div>

            <!-- Materiais reciclados -->
            <div class="col-md-4">
                <div class="p-3 bg-white rounded shadow-sm h-100 border-start border-4 border-primary">
                    <i class="bi bi-recycle fs-2 text-primary"></i>
                    <h5 class="mt-2">Materiais</h5>
                    <p class="text-muted mb-0">Controle de recicláveis</p>
                </div>
            </div>

            <!-- Relatórios -->
            <div class="col-md-4">
                <div class="p-3 bg-white rounded shadow-sm h-100 border-start border-4 border-warning">
                    <i class="bi bi-graph-up-arrow fs-2 text-warning"></i>
                    <h5 class="mt-2">Relatórios</h5>
                    <p class="text-muted mb-0">Veja seu desempenho mensal</p>
                </div>
            </div>
        </div>

        <!-- Linha extra com atalhos administrativos (opcional, só se for admin) -->
        <?php if ($dados_usuario['permissao'] == 'Admin'): ?>
        <div class="row g-4 text-center mt-3">
            <div class="col-md-6">
                <div class="p-3 bg-white rounded shadow-sm h-100 border-start border-4 border-info">
                    <i class="bi bi-people-fill fs-2 text-info"></i>
                    <h5 class="mt-2">Usuários</h5>
                    <p class="text-muted mb-0">Gerencie contas do sistema</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-white rounded shadow-sm h-100 border-start border-4 border-danger">
                    <i class="bi bi-gear-fill fs-2 text-danger"></i>
                    <h5 class="mt-2">Configurações</h5>
                    <p class="text-muted mb-0">Ajuste preferências do sistema</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Botão de ação -->
         <?php if ($dados_usuario['permissao'] == 'Admin'){ ?>
        <div class="text-center mt-5">
            <a href="<?=$url_base?>/usuarios" class="btn btn-success btn-lg shadow-sm px-4">
                <i class="bi bi-arrow-right-circle me-2"></i> Entrar no Painel
            </a>
        </div>
        <?php }else{ ?>
        <div class="text-center mt-5">
            <a href="<?=$url_base?>/views/user/login.php" class="btn btn-success btn-lg shadow-sm px-4">
                <i class="bi bi-arrow-right-circle me-2"></i> Realize login
            </a>
        </div>
        <?php } ?>
    </div>
</div>

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
