<?php
date_default_timezone_set('America/Sao_Paulo');
require_once (__DIR__ . '/../../components/middleware.php');
include '../../components/sidebar.php'; 

if(!isset($_SESSION['cliente'])){
    $_SESSION['cliente']= 0;
}
// Inicializa a lista de materiais na sessão se não existir
if (!isset($_SESSION['materiais'])) {
    $_SESSION['materiais'] = [];
}

// Processa a adição de materiais
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['adicionar_material'])) {
        // Busca o preço do material no banco (incluindo preço especial)
        $sql_preco = "SELECT preco_compra, preco_especial FROM tb_material WHERE id_material = ? AND status = 1";
        $stmt_preco = $pdo->prepare($sql_preco);
        $stmt_preco->execute([$_POST['tipo_material']]);
        $material_data = $stmt_preco->fetch(PDO::FETCH_ASSOC);
        
        // Determina qual preço usar baseado no checkbox e se existe preço especial
        $usar_preco_especial = isset($_POST['preco_especial']) && $_POST['preco_especial'] === 'on';
        
        if ($material_data) {
            // Se deve usar preço especial E existe preço especial no banco
            if ($usar_preco_especial && !empty($material_data['preco_especial'])) {
                $preco_unitario = floatval($material_data['preco_especial']);
            } else {
                $preco_unitario = floatval($material_data['preco_compra']);
            }
        } else {
            $preco_unitario = 0;
        }
        
        $peso = floatval($_POST['peso']);
        $valor_total = $preco_unitario * $peso;
        
        $item_id = uniqid('item_', true);
                
        $material = [
            'item_id' => $item_id, // ID único para o item na sessão
            'id' => $_POST['tipo_material'], // ID do material no banco
            'tipo' => $_POST['nome_material'],
            'peso' => $peso,
            'preco_unitario' => $preco_unitario,
            'valor_total' => $valor_total,
            'data_hora' => date('d/m/Y H:i:s'),
            'observacoes' => $_POST['observacoes'] ?? '' // Corrigido o nome do campo
        ];
        $_SESSION['materiais'][] = $material;
    }
    
    // Remove material específico
    if (isset($_POST['remover_material'])) {
        $item_id_remover = $_POST['material_item_id']; // Mudança no nome
        $_SESSION['materiais'] = array_filter($_SESSION['materiais'], function($item) use ($item_id_remover) {
            return $item['item_id'] !== $item_id_remover; // Usar item_id
        });
        $_SESSION['materiais'] = array_values($_SESSION['materiais']);
    }
    
    // Limpa toda a lista
    if (isset($_POST['limpar_lista'])) {
        $_SESSION['materiais'] = [];
    }
    
    // Salva os dados (aqui você pode implementar salvamento em banco de dados)
    if (isset($_POST['salvar_pesagem'])) {
        try {
            // Inicia uma transação para garantir consistência dos dados
            $pdo->beginTransaction();
            
            // Calcula os totais
            $total_peso = array_sum(array_column($_SESSION['materiais'], 'peso'));
            $total_valor = array_sum(array_column($_SESSION['materiais'], 'valor_total'));
            
            // Insere na tabela tb_pesagem
            $sql_pesagem = "INSERT INTO tb_pesagem (id_cliente, total_valor, total_peso, data_pesagem) 
                            VALUES (:id_cliente, :total_valor, :total_peso, NOW()) 
                            RETURNING id_pesagem";
            
            $stmt_pesagem = $pdo->prepare($sql_pesagem);
            $stmt_pesagem->bindParam(':id_cliente', $_SESSION['cliente'], PDO::PARAM_INT);
            $stmt_pesagem->bindParam(':total_valor', $total_valor, PDO::PARAM_STR);
            $stmt_pesagem->bindParam(':total_peso', $total_peso, PDO::PARAM_STR);
            $stmt_pesagem->execute();
            
            // Pega o ID da pesagem inserida
            $id_pesagem = $stmt_pesagem->fetchColumn();
            
            // Prepara a query para inserir os materiais
            $sql_material = "INSERT INTO tb_pesagem_material (id_pesagem, id_material, preco_un, peso_material, obs) 
                            VALUES (:id_pesagem, :id_material, :preco_un, :peso_material, :obs)";
            
            $stmt_material = $pdo->prepare($sql_material);
            
            // Insere cada material na tabela tb_pesagem_material
            foreach ($_SESSION['materiais'] as $material) {
                $stmt_material->bindParam(':id_pesagem', $id_pesagem, PDO::PARAM_INT);
                $stmt_material->bindParam(':id_material', $material['id'], PDO::PARAM_INT);
                $stmt_material->bindParam(':preco_un', $material['preco_unitario'], PDO::PARAM_STR);
                $stmt_material->bindParam(':peso_material', $material['peso'], PDO::PARAM_STR);
                
                // CORREÇÃO: Usar 'observacoes' em vez de 'obs'
                $obs = isset($material['observacoes']) ? $material['observacoes'] : null;
                $stmt_material->bindParam(':obs', $obs, PDO::PARAM_STR);
                
                $stmt_material->execute();
            }
            
            // Confirma a transação
            $pdo->commit();
            
            $mensagem_sucesso = "Pesagem salva com sucesso no banco de dados! ID da pesagem: " . $id_pesagem;
            
            // Limpa a sessão após salvar
            $_SESSION['materiais'] = [];
            $_SESSION['cliente'] = 0;
            
        } catch (PDOException $e) {
            // Em caso de erro, desfaz a transação
            $pdo->rollBack();
            //$_SESSION['erro']=$mensagem_erro = "Erro ao salvar pesagem: " . $e->getMessage();
        } catch (Exception $e) {
                // Em caso de outros erros
                $pdo->rollBack();
                $mensagem_erro = "Erro inesperado: " . $e->getMessage();
            }
    }

    if (isset($_POST['selecionar_cliente'])) {
        $_SESSION['cliente'] = $_POST['cliente'];
    }

    if (isset($_POST['remover_cliente'])) {
        $_SESSION['cliente'] = 0;
    }
    
}

// Calcula totais
$total_peso = array_sum(array_column($_SESSION['materiais'], 'peso'));
$total_valor = array_sum(array_column($_SESSION['materiais'], 'valor_total'));
$total_itens = count($_SESSION['materiais']);

//var_dump($_SESSION['erro']);

?>

    <link rel="stylesheet" href="../../css/pesagem.css">

    <script>
    $(document).ready(function() {
        $('#cliente').select2({
        placeholder: "Digite para buscar...",
        allowClear: true
        });
    });
    </script>

     <?php 
        $sql = "SELECT * FROM tb_usuario WHERE status = 1 AND cliente is not null ORDER BY nome";
        $stmt = $pdo->query($sql);
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div  style="display: flex;flex-direction: row;">
        <div class="container-cliente"  >
            <div class="painel-pesagem">
                
                <div class="header-pesagem">
                    <h2><i class="bi bi-person-fill"></i> Painel de cliente</h2>
                </div>

                <form method="POST" class="form-cliente">
                    <div class="form-group">
                            <label for="cliente">Selecione um cliente caso necessário:</label>
                                <select name="cliente" id="cliente" required>
                                    <option value="">Selecione o cliente...</option>
                                    <?php foreach ($options as $option): ?>
                                        <option value="<?= htmlspecialchars($option['id_usuario']) ?>">
                                            <?= htmlspecialchars($option['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <br>
                            <button type="submit" name="selecionar_cliente" class="btn btn-success">
                                + Selecionar cliente
                            </button>
                    </div>
                </form>
                    
                    <?php 
                    $sql = "SELECT * FROM tb_usuario WHERE status = 1 AND id_usuario = '".$_SESSION['cliente']."' ORDER BY nome ";
                    $stmt = $pdo->query($sql);
                    $cliente = $stmt->fetch(PDO::FETCH_ASSOC); 
                    ?>
                        
            </div>
            
        </div>

    
        <div class="container-pesagem" style="height: 850px; width:1000px">
            <!-- Painel de Pesagem -->
            <div class="painel-pesagem">
                <div class="header-pesagem">
                    <h2><i class="bi bi-receipt"></i> Sistema de Pesagem</h2>
                    <p>Adicione materiais recicláveis à lista</p>
                </div>
                
                <?php if (isset($mensagem_sucesso)): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($mensagem_sucesso) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="form-pesagem">
                    <?php 
                        $sql = "SELECT * FROM tb_material WHERE status = 1 ORDER BY tipo ASC";
                        $stmt = $pdo->query($sql);
                        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <script>
                    $(document).ready(function() {
                        $('#tipo_material').select2({
                        placeholder: "Digite para buscar...",
                        allowClear: true
                        });
                    });
                    </script>

                    
                    <div class="form-group">
                        <label for="tipo_material">Material:</label>
                        <select name="tipo_material" id="tipo_material" required>
                            <option value="">Selecione o material...</option>
                            <?php foreach ($options as $option): ?>
                                <option value="<?= htmlspecialchars($option['id_material']) ?>" 
                                        data-preco="<?= $option['preco_compra'] ?>"
                                        data-preco-especial="<?= !empty($option['preco_especial']) ? $option['preco_especial'] : '' ?>"
                                        data-nome="<?= $option['nm_material']?>">
                                    <?= htmlspecialchars($option['nm_material']) . ' / ' . htmlspecialchars($option['tipo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <label><input type="checkbox" id="preco_especial" name="preco_especial"<?php                  
                    if(!empty($_SESSION['cliente']) && $cliente['cliente'] == true){
                    echo 'checked';
                    }?>
                    > Preço especial
                    </label>
                    
                    <input type="hidden" id="nome_material" name="nome_material">
                    
                    <div class="form-group">
                        <label for="peso">Peso (kg):</label>
                        <input type="number" name="peso" id="peso" step="0.01" min="0.01" 
                            class="peso-input" placeholder="0.00" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="valor_unitario">Valor por KG:</label>
                        <input type="text" id="valor_unitario" class="valor-display" 
                            placeholder="R$ 0,00" readonly>
                    </div>
                    

                    <div class="form-group">
                        <label for="valor_total">Valor Total:</label>
                        <input type="text" id="valor_total" class="valor-display valor-total" 
                            placeholder="R$ 0,00" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="observacoes">Observações (opcional):</label>
                        <textarea name="observacoes" id="observacoes" rows="3" 
                                placeholder="Comentários adicionais sobre o material..."></textarea>
                    </div>
                    
                    <button type="submit" name="adicionar_material" class="btn btn-primary">
                        + Adicionar à Lista
                    </button>
                </form>
            </div>
            <!-- Lista de Materiais -->
            <div class="lista-materiais" style="height: 850px; width:1200px">
                <div class="totals-panel">
                    <h3><i class="bi bi-person-lines-fill"></i> Resumo da Pesagem</h3>
                    <?php if(!empty($_SESSION['cliente'])){?>
                    <h4><?=$cliente['nome']?> <form method="post"><button type="submit" name="remover_cliente" class="btn btn-danger btn-sm"> Remvoer </button></form></h4> 
                    <?php }?>
                    <div class="total-item">
                        <span class="total-number"><?= $total_itens ?></span>
                        <span class="total-label">Itens</span>
                    </div>
                    <div class="total-item">
                        <span class="total-number"><?= number_format($total_peso, 2, ',', '.') ?> kg</span>
                        <span class="total-label">Peso Total</span>
                    </div>
                    <div class="total-item">
                        <span class="total-number">R$ <?= number_format($total_valor, 2, ',', '.') ?></span>
                        <span class="total-label">Valor Total</span>
                    </div>
                </div>
                
                <?php if (empty($_SESSION['materiais'])): ?>
                    <div class="lista-vazia">
                        <p><i class="bi bi-search"></i> Nenhum material pesado ainda.</p>
                        <p>Adicione materiais usando o painel ao lado.</p>
                    </div>
                <?php else: ?>
                <div style="height: 270px;overflow-y: auto;">
                    <?php foreach ($_SESSION['materiais'] as $material): ?>
                        <div class="material-item">
                            <div class="material-header">
                                <span class="material-tipo"><?= htmlspecialchars($material['tipo']) ?></span>
                                <div>
                                    <span class="material-peso"><?= number_format($material['peso'], 2, ',', '.') ?> kg</span>
                                    <span class="material-valor">R$ <?= number_format($material['valor_total'], 2, ',', '.') ?></span>
                                </div>
                            </div>
                            <div class="material-info">
                                <i class="bi bi-calendar"></i> <?= htmlspecialchars($material['data_hora']) ?>
                                | <i class="bi bi-cash-coin"></i> R$ <?= number_format($material['preco_unitario'], 2, ',', '.') ?>/kg
                            </div>
                            <?php if (!empty($material['observacoes'])): ?>
                                <div class="material-info">
                                    <?= htmlspecialchars($material['observacoes']) ?>
                                </div>
                            <?php endif; ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="material_item_id" value="<?=  $material['item_id'] ?>">
                                <button type="submit" name="remover_material" class="btn btn-danger" 
                                        onclick="return confirm('Remover este item?')">
                                    <i class="bi bi-trash3"></i> Remover
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($_SESSION['materiais'])): ?>
                    <div class="actions-panel">
                        <form method="POST" style="flex: 1;">
                            <button type="submit" name="limpar_lista" class="btn btn-warning" 
                                    onclick="return confirm('Limpar toda a lista?')" style="width: 100%;">
                                <i class="bi bi-backspace"></i> Limpar Lista
                            </button>
                        </form>
                        <form method="POST" style="flex: 1;">
                            <button type="submit" name="salvar_pesagem" class="btn btn-primary" 
                                    style="width: 100%;">
                                ✓Salvar Pesagem
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script>
        // Função para formatar valor em Real
            function formatarReal(valor) {
                return 'R$ ' + valor.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }

            // Função para calcular valor total
            function calcularValorTotal() {
                const select = document.getElementById('tipo_material');
                const pesoInput = document.getElementById('peso');
                const valorUnitarioInput = document.getElementById('valor_unitario');
                const valorTotalInput = document.getElementById('valor_total');
                const nomeMaterialInput = document.getElementById('nome_material');
                
                const selectedOption = select.options[select.selectedIndex];
                const precoUnitario = selectedOption ? parseFloat(selectedOption.getAttribute('data-preco')) || 0 : 0;
                const peso = parseFloat(pesoInput.value) || 0;
                
                // Atualiza valor unitário
                valorUnitarioInput.value = formatarReal(precoUnitario);
                
                // Calcula e atualiza valor total
                const valorTotal = precoUnitario * peso;
                valorTotalInput.value = formatarReal(valorTotal);

                //Adiciona nome do material ao input hidden
                const nomeMaterial = selectedOption ? selectedOption.getAttribute('data-nome') || '' : '';
                nomeMaterialInput.value = nomeMaterial;
            }

            // Event listeners
            document.getElementById('tipo_material').addEventListener('change', function() {
                calcularValorTotal();
                if (this.value) {
                    document.getElementById('peso').focus();
                }
            });

            document.getElementById('peso').addEventListener('input', calcularValorTotal);

            // Permite usar Enter para adicionar rapidamente
            document.getElementById('peso').addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && this.value && document.getElementById('tipo_material').value) {
                    e.preventDefault();
                    document.querySelector('button[name="adicionar_material"]').click();
                }
            });

            document.addEventListener('DOMContentLoaded', function() {
                const precoEspecialCheckbox = document.getElementById('preco_especial');
                const tipoMaterialSelect = document.getElementById('tipo_material');
                
                function atualizarPrecos() {
                    const isPrecoEspecial = precoEspecialCheckbox.checked;
                    
                    // Atualiza todos os options do select
                    Array.from(tipoMaterialSelect.options).forEach(option => {
                        if (option.value !== '') { // Não processar o option vazio
                            const precoNormal = option.getAttribute('data-preco');
                            const precoEspecial = option.getAttribute('data-preco-especial');
                            
                            if (isPrecoEspecial && precoEspecial && precoEspecial !== '') {
                                option.setAttribute('data-preco', precoEspecial);
                            } else {
                                // Restaura o preço original (assumindo que você tem um backup)
                                const precoOriginal = option.getAttribute('data-preco-original') || precoNormal;
                                option.setAttribute('data-preco', precoOriginal);
                            }
                        }
                    });
                    
                    // IMPORTANTE: Recalcula os valores após alterar os preços
                    calcularValorTotal();
                }
                
                // Salva os preços originais na primeira execução
                Array.from(tipoMaterialSelect.options).forEach(option => {
                    if (option.value !== '') {
                        const precoOriginal = option.getAttribute('data-preco');
                        option.setAttribute('data-preco-original', precoOriginal);
                    }
                });
                
                // Aplica a lógica inicial baseada no estado atual do checkbox
                atualizarPrecos();
                
                // Escuta mudanças no checkbox
                precoEspecialCheckbox.addEventListener('change', atualizarPrecos);
            });
                    
            // Limpa o formulário após adicionar (se a página recarregar)
            <?php if (isset($_POST['adicionar_material'])): ?>
            document.getElementById('peso').value = '';
            document.getElementById('observacoes').value = '';
            document.getElementById('valor_unitario').value = '';
            document.getElementById('valor_total').value = '';
            document.getElementById('tipo_material').focus();
            <?php endif; ?>
    </script>
