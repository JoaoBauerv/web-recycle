<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}
$sql = "SELECT * FROM fornecedores WHERE id_fornecedor = :id";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':id', (int) $_REQUEST['id'], PDO::PARAM_INT);
$stmt->execute();
$fornecedor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fornecedor) {
    header("Location: index.php?msgErro=Fornecedor não encontrado.");
    exit;
}
?>

<div class="container mt-5">
    <div class="card bg-secondary text-light shadow-lg p-4 rounded-4">
        <h2 class="text-center mb-4">Editar Fornecedor</h2>

        <?php require_once __DIR__ . '/../../components/alert.php'; ?>

        <form method="POST" action="<?=$url_base?>/functions/fornecedor/registrar.php">
            <input type="hidden" value="editar" name="acao">
            <input type="hidden" value="<?= $fornecedor['id_fornecedor'] ?>" name="id">

            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label">Nome / Razão Social</label>
                    <input type="text" name="nome_razao_social" class="form-control" value="<?= htmlspecialchars($fornecedor['nome_razao_social']) ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">CNPJ/CPF</label>
                    <input type="text" name="cnpj_cpf" class="form-control" value="<?= htmlspecialchars($fornecedor['cnpj_cpf'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Telefone</label>
                    <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($fornecedor['telefone'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($fornecedor['email'] ?? '') ?>">
                </div>
            </div>

            <hr class="border-light">
            <h5 class="mb-3">Endereço</h5>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">CEP</label>
                    <input type="text" id="cep" name="cep" class="form-control" value="<?= htmlspecialchars($fornecedor['cep'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Logradouro</label>
                    <input type="text" id="logradouro" name="logradouro" class="form-control" value="<?= htmlspecialchars($fornecedor['logradouro'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Número</label>
                    <input type="text" name="numero" class="form-control" value="<?= htmlspecialchars($fornecedor['numero'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Complemento</label>
                    <input type="text" name="complemento" class="form-control" value="<?= htmlspecialchars($fornecedor['complemento'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Bairro</label>
                    <input type="text" id="bairro" name="bairro" class="form-control" value="<?= htmlspecialchars($fornecedor['bairro'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Cidade</label>
                    <input type="text" id="cidade" name="cidade" class="form-control" value="<?= htmlspecialchars($fornecedor['cidade'] ?? '') ?>">
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">UF</label>
                    <input type="text" id="estado" name="estado" maxlength="2" class="form-control" value="<?= htmlspecialchars($fornecedor['estado'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Observações</label>
                <textarea name="observacoes" class="form-control" rows="3"><?= htmlspecialchars($fornecedor['observacoes'] ?? '') ?></textarea>
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
