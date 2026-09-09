        <div class="d-flex">
        <div class="sidebar d-flex flex-column p-3 text-white bg-dark" style="width: 250px; height: 100vh;">

            <h5 class="mb-4 text-center text-uppercase fw-bold border-bottom pb-2"><?=$_ENV['APP_NAME']?></h5>

            <ul class="nav nav-pills flex-column mb-auto">
                                <li class="nav-item mb-2">
                                    <?php 
                                    if(!empty($_SESSION['logado'])){
                                        $index = 'index2';
                                    }else{
                                        $index = 'index';
                                    }
                                    ?>
                                    <a href="<?=$url_base?>/<?=$index?>.php" class="nav-link active text-white bg-primary rounded-3">
                                        <i class="bi bi-house-door me-2"></i> Inicio
                                    </a>
                                </li>
                                
                                <?php if(!empty($_SESSION['logado'])){?>
                                <li class="nav-item mb-2">
                                    <a href="<?=$url_base?>/materiais" class="nav-link active text-white bg-secondary rounded-3">
                                        <i class="bi bi-shop me-2"></i> Materiais
                                    </a>
                                </li>

                                <li class="nav-item mb-2">
                                    <a href="<?=$url_base?>/clientes" class="nav-link active text-white bg-secondary rounded-3">
                                        <i class="bi bi-person-lines-fill me-2"></i> Clientes
                                    </a>
                                </li>

                                <li class="nav-item mb-2">
                                    <a href="<?=$url_base?>/fornecedores" class="nav-link active text-white bg-secondary rounded-3">
                                        <i class="bi bi-truck me-2"></i> Fornecedores
                                    </a>
                                </li>

                                <li class="nav-item mb-2">
                                    <a href="<?=$url_base?>/balanca" class="nav-link active text-white bg-secondary rounded-3">
                                        <i class="bi bi-calculator-fill"></i> Balança
                                    </a>
                                </li> 

                                <li class="nav-item mb-2">
                                   <a href="<?=$url_base?>/balanca/listar" class="nav-link active text-white bg-secondary rounded-3">
                                      <i class="bi bi-clipboard-data"></i> Pesagens 
                                    </a>
                                </li>

                                <li class="nav-item mb-2">
                                   <a href="<?=$url_base?>/calendario" class="nav-link active text-white bg-secondary rounded-3">
                                      <i class="bi bi-calendar3"></i> Calendário 
                                    </a>
                                </li>
                                <?php }?>

                                
    
                                <!-- <li class="nav-item">
                                    <button class="btn btn-outline-light w-100 text-start" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#sidebarMenuLinks"
                                            aria-expanded="false" aria-controls="sidebarMenuLinks">
                                        #
                                    </button>

                                    <div class="collapse mt-2" id="sidebarMenuLinks">
                                        <ul class="nav flex-column">
                                            <li class="nav-item">
                                                <a href="#" class="nav-link text-white ps-4">#</a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="#" class="nav-link text-white ps-4">#</a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="#" class="nav-link text-white ps-4">#</a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="#" class="nav-link text-white ps-4">#</a>
                                            </li>
                                        </ul>
                                    </div>
                                </li> -->
                            </ul>

                        <div class="ms-3">

                        <?php if (!empty($_SESSION['usuario'])){ ?>
                            <div class="dropdown ">

                                    <?php
                                        $usuario = $_SESSION['usuario'];

                                        // Buscar todas as informações do usuário no banco
                                        $stmt = $pdo->prepare("SELECT * FROM tb_usuario WHERE usuario = :usuario");
                                        $stmt->bindParam(':usuario', $usuario);
                                        $stmt->execute();
                                        $dados_usuario = $stmt->fetch(PDO::FETCH_ASSOC); // $dados_usuario será um array associativo com os dados do usuário

                                        // Buscar a foto do usuário no banco
                                        $stmt = $pdo->prepare("SELECT foto FROM tb_usuario WHERE usuario = :usuario");
                                        $stmt->bindParam(':usuario', $dados_usuario['usuario']);
                                        $stmt->execute();
                                        $foto = $stmt->fetchColumn(); // Retorna só o valor da coluna

                                        // Caminho padrão se não houver foto no banco
                                        $foto_usuario = !empty($foto) ? $foto : $url_base.'/images/user/padrao.png';
                                    
                                    ?>
                                

                               <a href="#" class="d-flex align-items-center gap-2 text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                                    <img src="<?=$url_base?>/images/user/<?= $foto_usuario ?>" alt="Foto"
                                        class="rounded-circle border border-2 border-light" width="40" height="40" style="object-fit: cover;">
                                    <span class="fw-semibold text-truncate" style="max-width: 140px;">
                                        <?php echo htmlspecialchars($dados_usuario['nome']); ?>
                                    </span>
                                </a>

                                <ul class="dropdown-menu dropdown-menu-dark shadow-sm mt-2">
                                    <?php if ($dados_usuario['permissao'] == 'Admin'){ ?>    
                                        <li><a class="dropdown-item" href="<?=$url_base?>/usuarios"><i class="bi bi-gear me-2"></i> Admin</a></li>
                                    <?php } ?> 
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-sliders me-2"></i> Settings</a></li>
                                    <li><a class="dropdown-item" href="<?=$url_base?>/perfil"><i class="bi bi-person-circle me-2"></i> Profile</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href='<?=$url_base?>/functions/user/logout.php'><i class="bi bi-box-arrow-right me-2"></i> Sign out</a></li>
                                </ul>
                            </div>

                        <?php }else{ ?>
                            <div class="d-grid gap-2">
                                <a href="<?=$url_base?>/views/user/login.php" class="btn btn-primary btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i> Login</a>
                                <!--a href="<?=$url_base?>/usuarios/novo" class="btn btn-warning btn-sm"><i class="bi bi-person-plus me-1"></i> Registrar-se</!--a -->
                            </div>
                        <?php }; ?>
                        
                        </div>
                    </div>
                    </div>

                    <div class="content d-flex justify-content-center" style="flex: 1;">