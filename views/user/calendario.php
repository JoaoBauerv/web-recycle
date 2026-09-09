<?php 
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}
?>

<link href="<?=$url_base?>/css/calendario.css" rel="stylesheet">

<script src='<?=$url_base?>/js/calendario/index.global.min.js'></script>
<script src='<?=$url_base?>/js/calendario/core/locales/pt-br.global.min.js'></script>
<script src='<?=$url_base?>/js/calendario/custom.js'></script>
<script src='<?=$url_base?>/js/calendario/bootstrap5/index.global.min.js'></script>


    <div id='calendar'  style="flex: 1; max-height: 1000px; max-width: 1700px; margin-top: 30px">


    </div>

    <!-- Modal  visualizar-->
    <div class="modal fade" id="visualizarModal" tabindex="-1" role="dialog" aria-labelledby="visualizarModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="visualizarModalLabel">Visualizar Evento</h5>
            </div>
            <div class="modal-body">
                <dl class="row">
                    <dt class="col-sm-3">ID:</dt>
                    <dd class="col-sm-9" id="visualizar_id"></dd>

                    <dt class="col-sm-3">Título:</dt>
                    <dd class="col-sm-9" id="visualizar_title"></dd>
                    
                    <dt class="col-sm-3">Início:</dt>
                    <dd class="col-sm-9" id="visualizar_start"></dd>

                    <dt class="col-sm-3">Fim:</dt>
                    <dd class="col-sm-9" id="visualizar_end"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Save changes</button>
            </div>
            </div>
        </div>
    </div>


        
    <!-- Modal cadastrar-->
    <div class="modal fade" id="cadastrarModal" tabindex="-1" role="dialog" aria-labelledby="cadastrarModalLabel" aria-modal="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cadastrarModalLabel">Cadastrar Evento</h5>
            </div>
            <div class="modal-body">

                <span id="msgCadEvento"></span>
                
                <form method="post" id="formCadEvento">
                    <div class="row mb-3">
                        <label for="cad_title" class="col-sm-2 col-form-label">Título</label>
                        <div class="col-sm-10">
                            <input type="text" name="cad_title" class="form-control" id="cad_title" placeholder="Título do evento">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label for="cad_start" class="col-sm-2 col-form-label">Início</label>
                        <div class="col-sm-10">
                            <input type="datetime-local" name="cad_start" class="form-control" id="cad_start">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label for="cad_end" class="col-sm-2 col-form-label">Fim</label>
                        <div class="col-sm-10">
                            <input type="datetime-local" name="cad_end" class="form-control" id="cad_end" >
                        </div>
                    </div>

                    <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="submit" name="btnCadEvento" id="btnCadEvento" class="btn btn-success">
                    Cadastrar
                </button>
            </div>
            </form>

            </div>

            
            </div>
        </div>
    </div>