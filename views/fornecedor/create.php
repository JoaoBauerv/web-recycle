<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}
?>

<div class="container mt-5">
    <div class="card bg-secondary text-light shadow-lg p-4 rounded-4">
        <h2 class="text-center mb-4">Cadastro de Fornecedor</h2>

        <?php require_once __DIR__ . '/../../components/alert.php'; ?>

        <form method="POST" action="<?=$url_base?>/functions/fornecedor/registrar.php">
            <input type="hidden" value="cadastrar" name="acao">

            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label">Nome / Razão Social</label>
                    <input type="text" name="nome_razao_social" class="form-control" placeholder="Ex: Indústria XYZ Ltda" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">CNPJ/CPF</label>
                    <input type="text" name="cnpj_cpf" class="form-control" placeholder="Somente números">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Telefone</label>
                    <input type="text" name="telefone" class="form-control" placeholder="(00) 0000-0000">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" placeholder="contato@fornecedor.com">
                </div>
            </div>

            <hr class="border-light">
            <h5 class="mb-3">Endereço</h5>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">CEP</label>
                    <input type="text" id="cep" name="cep" class="form-control" placeholder="00000-000">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Logradouro</label>
                    <input type="text" id="logradouro" name="logradouro" class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Número</label>
                    <input type="text" name="numero" class="form-control">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Complemento</label>
                    <input type="text" name="complemento" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Bairro</label>
                    <input type="text" id="bairro" name="bairro" class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Cidade</label>
                    <input type="text" id="cidade" name="cidade" class="form-control">
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">UF</label>
                    <input type="text" id="estado" name="estado" maxlength="2" class="form-control">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Observações</label>
                <textarea name="observacoes" class="form-control" rows="3" placeholder="Ex: comprador preferencial de PET transparente"></textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100">Confirmar</button>
        </form>
    </div>
</div>

<script>
document.getElementById('cep').addEventListener('blur', function() {
    const cep = this.value.replace(/\D/g, '');
    if (cep.length !== 8) return;

    fetch('https://viacep.com.br/ws/' + cep + '/json/')
        .then(resp => resp.json())
        .then(data => {
            if (!data.erro) {
                document.getElementById('logradouro').value = data.logradouro || '';
                document.getElementById('bairro').value = data.bairro || '';
                document.getElementById('cidade').value = data.localidade || '';
                document.getElementById('estado').value = data.uf || '';
            }
        })
        .catch(() => {});
});
</script>
