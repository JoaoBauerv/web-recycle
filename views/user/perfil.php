<?php 
include '../../components/sidebar.php'; 
require_once (__DIR__ . '/../../components/middleware.php');
// Supondo que os dados do usuário estejam em $_SESSION['usuario']
?>

<div class="container-fluid p-5">
    <h3 class="fw-bold text-uppercase mb-4"><?php echo $dados_usuario['nome'];?>  <span class="fw-normal">:: Meus dados</span></h3>

    <div class="row g-4">
        <!-- Foto de perfil -->
        <div class="col-md-3 text-center">
            <div class="border p-3 bg-light rounded">

                <?php
                $foto = !empty($foto_usuario) && file_exists($_SERVER['DOCUMENT_ROOT'] . $url_base."/images/user/" . $foto_usuario) 
                    ? $foto_usuario
                    : 'padrao.png';
                ?>
                            
                <img src="<?=$url_base?>/images/user/<?= $foto_usuario; ?>" alt="Avatar" class="img-fluid rounded" onerror="this.style.display='none'">
                <!-- <div class="mt-2">
                    <button class="btn btn-outline-danger btn-sm w-100">Excluir foto</button>
                </div> -->

            </div>
            <div class="mt-3">
                <span class="badge bg-primary fs-6 px-3 py-2 text-uppercase">
                    <?= htmlspecialchars($dados_usuario['permissao']) ?>
                </span>
            </div>
        </div>

        <!-- Dados Pessoais -->
        <div class="col-md-9">
            <ul class="nav nav-tabs" id="meusDadosTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pessoais-tab" data-bs-toggle="tab" data-bs-target="#pessoais" type="button" role="tab">Dados Pessoais</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="documentos-tab" data-bs-toggle="tab" data-bs-target="#documentos" type="button" role="tab">Documentos</button>
                </li>
            </ul>
            <div class="tab-content border border-top-0 p-4 bg-white">
                <div class="tab-pane fade show active" id="pessoais" role="tabpanel">

                    <?php 

                    $sql = "SELECT * FROM tb_usuario where id_usuario = '".$dados_usuario['id_usuario']."'";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute();
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);

                    //var_dump($row);

                    if(!empty($row['data_nascimento'])){
                    $tz  = new DateTimeZone('America/Sao_Paulo');
                    $age = DateTime::createFromFormat('Y-m-d', ''.$row['data_nascimento'].'' , $tz)
                    ->diff(new DateTime('now', $tz))
                    ->y;
                    }
                    ?>
            
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" value="<?php echo $row['nome']; ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="text" class="form-control" value="<?php echo $row['email']; ?>" readonly>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Data nasc.</label>
                            <input type="text" class="form-control" value="<?php if (!empty($row['data_nascimento'])) {
                                                                                    $dataFormatada = DateTime::createFromFormat('Y-m-d', $row['data_nascimento'])->format('d/m/Y');
                                                                                    echo $dataFormatada; // Saída: ex -> 26/09/2004
                                                                                } else {
                                                                                    echo 'Data não informada';
                                                                                } ?>" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Idade</label>
                            <input type="text" class="form-control" value="<?php echo $age;
                            
                            
                             ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Telefone</label>
                            <input type="text" class="form-control" value="<?php //echo $row['']; ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Celular</label>
                            <input type="text" class="form-control" value="<?php //echo $row['']; ?>" readonly>
                        </div>
 
                    </div>

                    <hr class="my-4">

                    <h5>Endereço</h5>

                    <?php 

                    $sql = "SELECT * FROM tb_endereco where id_usuario = '".$dados_usuario['id_usuario']."'";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute();
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);

                    ?>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">CEP</label>
                            <input type="text" class="form-control" value="<?php echo $row['cep'] ?>"  readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Logradouro</label>
                            <input type="text" class="form-control" value="<?php echo $row['logradouro'] ?>"  readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Número</label>
                            <input type="text" class="form-control" value="<?php echo $row['numero'] ?>"   readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Complemento</label>
                            <input type="text" class="form-control" value="<?php echo $row['complemento'] ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cidade</label>
                            <input type="text" class="form-control" value="<?php echo $row['cidade'] ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bairro</label>
                            <input type="text" class="form-control" value="<?php echo $row['bairro'] ?>" readonly>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Referência</label>
                            <input type="text" class="form-control" value="<?php echo $row['referencia'] ?>" readonly>
                        </div>
                    </div>
                </div>
                

                <div class="tab-pane fade" id="documentos" role="tabpanel">

                        <?php 

                        $sql = "SELECT * FROM tb_documento where id_usuario = '".$dados_usuario['id_usuario']."'";

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute();
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);

                        //var_dump($row);
                        ?>
            
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">CPF</label>
                            <input type="text" class="form-control" value="<?php echo $row['cpf']; ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">RG</label>
                            <input type="text" class="form-control" value="<?php echo $row['rg']; ?>" readonly>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">CNH</label>
                            <input type="text" class="form-control" value="<?php echo $row['cnh']?>" readonly>
                        </div>
                     
 
                    </div>

                </div>
                <!-- Outras abas podem ser adicionadas aqui -->     
                                                                            
            </div>
        </div>
    </div>
</div>
