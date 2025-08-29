
<?php 
include '../../components/sidebar.php'; 
require_once (__DIR__ . '/../../components/middleware.php');
unset($_SESSION['msg_erro']);
unset($_SESSION['msg_sucesso']);

$status = isset($_REQUEST['status']) ? $_REQUEST['status'] : '1';
$status_permitidos = ['1', '0'];

if(!in_array($status, $status_permitidos)){
  $status = '1';
}

?>

<?php if ($dados_usuario['permissao']=== 'Admin'): ?>
<div class="container py-5">

  
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-semibold text-dark">👤 Painel de Administração</h2>
    <a href="<?=$url_base?>/views/user/register.php" class="btn btn-success shadow-sm">
      <i class="bi bi-person-plus-fill me-1"></i> Novo Usuário
    </a>
  </div>

  <!-- importar o component de alertas -->
  <?php require_once '../../components/alert.php'; ?>

  <style>
  table.dataTable {
      border-collapse: separate !important;
      /* border-spacing: 0 0.5rem !important;  */
  }

  table.dataTable tbody tr {
      background: #fff;
      border-radius: 0.5rem;
      overflow: hidden;
      box-shadow: 0 1px 2px rgba(0,0,0,0.05);
  }

  table.dataTable td, 
  table.dataTable th {
      border: none !important;
  }
  </style>

  <div class="card shadow-sm">
    <div class="card-body p-3">

      <div class="row d-flex justify-content-end mb-3">
          <div class="col-auto" >
              <div class="form-check mb-2">
                  <input type="radio" class="form-check-input" name="status" 
                          id="status1" value="1" onchange="atualizarPagina(this.value)"
                          <?= ($status === '1') ? 'checked' : '' ?>>
                  <label class="form-check-label" for="status1">
                      <i class="fa-solid fa-circle-check text-success me-1"></i>Ativo
                  </label>
              </div>
          </div>
          <div class="col-auto">
              <div class="form-check mb-2">
                  <input type="radio" class="form-check-input" name="status" 
                          id="status0" value="0" onchange="atualizarPagina(this.value)"
                          <?= ($status === '0') ? 'checked' : '' ?>>
                  <label class="form-check-label" for="status0">
                      <i class="fa-solid fa-circle-xmark text-danger me-1"></i>Inativo
                  </label>
              </div>
          </div>
      </div>

      <script>
        function atualizarPagina(valor) {
            // Obtém a URL atual
            const url = new URL(window.location);
            
            // Atualiza/adiciona o parâmetro
            url.searchParams.set('status', valor);
            
            // Redireciona para a nova URL
            window.location.href = url.toString();
        }
      </script>

      
      
      <?php
        $stmt = $pdo->prepare("SELECT * FROM tb_usuario WHERE status = $status ORDER BY id_usuario");
        $stmt->execute();
        $rowCount = $stmt->rowCount();

        if ($rowCount > 0):
      ?>
      <div class="table-responsive">
        <table id="usuariosTable" class="table table-striped table-hover align-middle mb-0">
          <thead class="table-primary">
            <tr>
              <th>#</th>
              <th>Usuário</th>
              <th>Nome</th>
              <th class="text-center">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
            <tr>
              <td class="fw-medium "><?= htmlspecialchars($row["id_usuario"]) ?></td>
              <td><?= htmlspecialchars($row["usuario"]) ?></td>
              <td><?= htmlspecialchars($row["nome"]) ?></td>
              <td class="text-center">
                <a href="<?=$url_base?>/views/user/edit.php?id=<?= $row["id_usuario"] ?>" 
                   class="btn btn-sm btn-warning me-1" title="Editar">
                  <i class="bi bi-pencil"></i>
                </a>
                <a href="<?=$url_base?>/functions/user/resetarsenha.php?id=<?= $row["id_usuario"] ?>" 
                   class="btn btn-sm btn-primary me-1" title="Resetar Senha">
                  <i class="bi bi-key"></i>
                </a>
                <!-- <form action="<?=$url_base?>/functions/user/deletar.php?id=<?= $row["id_usuario"] ?>" 
                      method="post" class="d-inline" onsubmit="return confirm('Deseja realmente excluir este usuário?')">
                  <button type="submit" class="btn btn-sm btn-danger" title="Excluir">
                    <i class="bi bi-trash3"></i>
                  </button>
                </form> -->
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <div class="text-center p-4 text-muted">Nenhum usuário encontrado.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- datatable -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>



<script>
$(document).ready(function() {
  $('#usuariosTable').DataTable({
    "language": {
      "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json"
    },
    "pageLength": 5, // Quantos registros por página
    "lengthMenu": [5, 10, 25, 50], // Opções de quantidade
    "order": [[0, "asc"]] // Ordenar pela primeira coluna
  });
});
</script>

<?php else: ?>
  
<!-- nao admin -->
<div class="container-fluid min-vh-100 d-flex justify-content-center align-items-center bg-light">
  <div class="text-center bg-white p-5 shadow rounded" style="max-width: 500px;">
    <h4 class="text-danger mb-3"><i class="bi bi-shield-lock-fill"></i> Acesso Negado</h4>
    <p class="text-muted">Essa página não está disponível para o seu usuário.</p>
    <a href="<?=$url_base?>/index2.php" class="btn btn-primary mt-3">Voltar</a>
  </div>
</div>
<?php endif; ?>
