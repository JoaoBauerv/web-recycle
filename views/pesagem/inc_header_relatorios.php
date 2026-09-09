            <!-- Header da página -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item">
                                <a href="<?=$url_base?>/balanca/listar" class="text-decoration-none">
                                    <i class="bi bi-house-door me-1"></i>
                                    Pesagens 
                                </a>
                                
                            </li>
                            <?php if(!empty($_REQUEST['tipo'])){
                                    echo '<li class="breadcrumb-item active" aria-current="page"> Relátorio '.$_REQUEST['tipo'].'</li>';
                                } ?>
                        </ol>
                    </nav>
                    <br>
                    <h2 class="mb-1 text-dark fw-bold"><?php if(empty($_REQUEST['tipo'])){ ?>Lista de Pesagens <?php }else{?>Relatório <?php echo $_REQUEST['tipo']; }?></h2>
                    
                    <br>
                    <div class="mb-3">
                        <div class="d-flex flex-wrap gap-2">

                            <a href="<?=$url_base?>/balanca/relatorio?tipo=geral" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-bar-chart-line me-1"></i>
                                Geral
                            </a>

                            <a href="<?=$url_base?>/balanca/relatorio?tipo=cliente" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-person me-1"></i>
                                Por Cliente
                            </a>

                            <a href="<?=$url_base?>/balanca/relatorio?tipo=periodo" class="btn btn-sm btn-outline-warning">
                                <i class="bi bi-calendar-range me-1"></i>
                                Por Período
                            </a>

                            <a href="<?=$url_base?>/balanca/relatorio?tipo=produto" class="btn btn-sm btn-outline-info">
                                <i class="bi bi-box-seam me-1"></i>
                                Por Produto
                            </a>

                        </div>
                    </div>
                </div>
            </div>