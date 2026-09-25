            <!-- Header da página -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item">
                                <a href="<?=$url_base?>/vendas/listar" class="text-decoration-none">
                                    <i class="bi bi-house-door me-1" aria-hidden="true"></i>
                                    Vendas
                                </a>
                            </li>
                            <?php if (!empty($tela_relatorio)): ?>
                                <li class="breadcrumb-item active" aria-current="page">Relatório</li>
                            <?php endif; ?>
                        </ol>
                    </nav>
                    <h2 class="mb-1 text-dark fw-bold">
                        <?= !empty($tela_relatorio) ? 'Relatório de Vendas' : 'Lista de Vendas' ?>
                    </h2>
                    <div class="mb-3">
                        <?php if (!empty($tela_relatorio)): ?>
                            <a href="<?=$url_base?>/vendas/listar" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-list-ul me-1" aria-hidden="true"></i>
                                Ver lista de vendas
                            </a>
                        <?php else: ?>
                            <a href="<?=$url_base?>/vendas/relatorio" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-bar-chart-line me-1" aria-hidden="true"></i>
                                Ver relatório
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
