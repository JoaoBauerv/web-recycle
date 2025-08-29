<?php
include '../../components/sidebar.php'; 
require_once (__DIR__ . '/../../components/middleware.php');

// Inicializa a lista de materiais na sessão se não existir
if (!isset($_SESSION['materiais'])) {
    $_SESSION['materiais'] = [];
}

// Processa a adição de materiais
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['adicionar_material'])) {
        // Busca o preço do material no banco
        $sql_preco = "SELECT preco_compra FROM tb_material WHERE nm_material = ? AND status = 1";
        $stmt_preco = $pdo->prepare($sql_preco);
        $stmt_preco->execute([$_POST['tipo_material']]);
        $material_data = $stmt_preco->fetch(PDO::FETCH_ASSOC);
        
        $preco_unitario = $material_data ? floatval($material_data['preco_compra']) : 0;
        $peso = floatval($_POST['peso']);
        $valor_total = $preco_unitario * $peso;
        
        $material = [
            'id' => uniqid(),
            'tipo' => $_POST['tipo_material'],
            'peso' => $peso,
            'preco_unitario' => $preco_unitario,
            'valor_total' => $valor_total,
            'data_hora' => date('d/m/Y H:i:s'),
            'observacoes' => $_POST['observacoes'] ?? ''
        ];
        $_SESSION['materiais'][] = $material;
    }
    
    // Remove material específico
    if (isset($_POST['remover_material'])) {
        $id_remover = $_POST['material_id'];
        $_SESSION['materiais'] = array_filter($_SESSION['materiais'], function($item) use ($id_remover) {
            return $item['id'] !== $id_remover;
        });
        $_SESSION['materiais'] = array_values($_SESSION['materiais']); // Reindexar array
    }
    
    // Limpa toda a lista
    if (isset($_POST['limpar_lista'])) {
        $_SESSION['materiais'] = [];
    }
    
    // Salva os dados (aqui você pode implementar salvamento em banco de dados)
    if (isset($_POST['salvar_pesagem'])) {
        // Exemplo de salvamento em arquivo JSON
        $dados_salvamento = [
            'data_pesagem' => date('Y-m-d H:i:s'),
            'materiais' => $_SESSION['materiais'],
            'total_peso' => array_sum(array_column($_SESSION['materiais'], 'peso')),
            'total_valor' => array_sum(array_column($_SESSION['materiais'], 'valor_total'))

        ];
        
        $arquivo = 'pesagens_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($arquivo, json_encode($dados_salvamento, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $mensagem_sucesso = "Pesagem salva com sucesso no arquivo: " . $arquivo;
        $_SESSION['materiais'] = []; // Limpa a lista após salvar
    }
}

// Calcula totais
$total_peso = array_sum(array_column($_SESSION['materiais'], 'peso'));
$total_valor = array_sum(array_column($_SESSION['materiais'], 'valor_total'));
$total_itens = count($_SESSION['materiais']);
?>


    <style>
        .container-pesagem {
            display: flex;
            gap: 20px;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .painel-pesagem {
            flex: 1;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .lista-materiais {
            flex: 1;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-height: 600px;
            overflow-y: auto;
        }
        
        .form-pesagem {
            display: grid;
            gap: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #28a745;
            outline: none;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #28a745;
            color: white;
        }
        
        .btn-primary:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            font-size: 14px;
            padding: 6px 12px;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-success {
            background: #17a2b8;
            color: white;
        }
        
        .btn-success:hover {
            background: #138496;
        }
        
        .material-item {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid #28a745;
            position: relative;
        }
        
        .material-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .material-tipo {
            font-weight: bold;
            color: #28a745;
            font-size: 18px;
        }
        
        .material-peso {
            background: #28a745;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .material-valor {
            background: #17a2b8;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-weight: bold;
        }
        
        .valor-display {
            font-size: 18px !important;
            text-align: center;
            font-weight: bold;
            color: #17a2b8;
            background-color: #e9ecef;
        }
        
        .valor-total {
            color: #28a745 !important;
            font-size: 24px !important;
        }
        
        .material-info {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .totals-panel {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .totals-panel h3 {
            margin: 0 0 10px 0;
        }
        
        .total-item {
            display: inline-block;
            margin: 0 20px;
        }
        
        .total-number {
            font-size: 24px;
            font-weight: bold;
            display: block;
        }
        
        .total-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .actions-panel {
            display: flex;
            gap: 10px;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .peso-input {
            font-size: 24px !important;
            text-align: center;
            font-weight: bold;
            color: #28a745;
        }
        
        .header-pesagem {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }
        
        .lista-vazia {
            text-align: center;
            color: #888;
            font-style: italic;
            padding: 40px;
        }
    </style>

    <div class="container-pesagem" style="height: 850px; width:1000px">
        <!-- Painel de Pesagem -->
        <div class="painel-pesagem">
            <div class="header-pesagem">
                <h2>🏭 Sistema de Pesagem</h2>
                <p>Adicione materiais recicláveis à lista</p>
            </div>
            
            <?php if (isset($mensagem_sucesso)): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($mensagem_sucesso) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="form-pesagem">
                <?php 
                    $sql = "SELECT * FROM tb_material WHERE status = 1 ORDER BY nm_material ASC";
                    $stmt = $pdo->query($sql);
                    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <div class="form-group">
                    <label for="tipo_material">Material:</label>
                    <select name="tipo_material" id="tipo_material" required>
                        <option value="">Selecione o material...</option>
                        <?php foreach ($options as $option): ?>
                            <option value="<?= htmlspecialchars($option['nm_material']) ?>" 
                                    data-preco="<?= $option['preco_compra'] ?>">
                                <?= htmlspecialchars($option['nm_material']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
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
                    ➕ Adicionar à Lista
                </button>
            </form>
        </div>
        
        <!-- Lista de Materiais -->
        <div class="lista-materiais" style="height: 850px; width:1200px">
            <div class="totals-panel">
                <h3>📊 Resumo da Pesagem</h3>
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
                    <p>🔍 Nenhum material pesado ainda.</p>
                    <p>Adicione materiais usando o painel ao lado.</p>
                </div>
            <?php else: ?>
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
                            📅 <?= htmlspecialchars($material['data_hora']) ?>
                            | 💰 R$ <?= number_format($material['preco_unitario'], 2, ',', '.') ?>/kg
                        </div>
                        <?php if (!empty($material['observacoes'])): ?>
                            <div class="material-info">
                                💭 <?= htmlspecialchars($material['observacoes']) ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="material_id" value="<?= $material['id'] ?>">
                            <button type="submit" name="remover_material" class="btn btn-danger" 
                                    onclick="return confirm('Remover este item?')">
                                🗑️ Remover
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($_SESSION['materiais'])): ?>
                <div class="actions-panel">
                    <form method="POST" style="flex: 1;">
                        <button type="submit" name="limpar_lista" class="btn btn-warning" 
                                onclick="return confirm('Limpar toda a lista?')" style="width: 100%;">
                            🧹 Limpar Lista
                        </button>
                    </form>
                    <form method="POST" style="flex: 1;">
                        <button type="submit" name="salvar_pesagem" class="btn btn-success" 
                                style="width: 100%;">
                            💾 Salvar Pesagem
                        </button>
                    </form>
                </div>
            <?php endif; ?>
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
            
            const selectedOption = select.options[select.selectedIndex];
            const precoUnitario = selectedOption ? parseFloat(selectedOption.getAttribute('data-preco')) || 0 : 0;
            const peso = parseFloat(pesoInput.value) || 0;
            
            // Atualiza valor unitário
            valorUnitarioInput.value = formatarReal(precoUnitario);
            
            // Calcula e atualiza valor total
            const valorTotal = precoUnitario * peso;
            valorTotalInput.value = formatarReal(valorTotal);
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
        
        // Limpa o formulário após adicionar (se a página recarregar)
        <?php if (isset($_POST['adicionar_material'])): ?>
        document.getElementById('peso').value = '';
        document.getElementById('observacoes').value = '';
        document.getElementById('valor_unitario').value = '';
        document.getElementById('valor_total').value = '';
        document.getElementById('tipo_material').focus();
        <?php endif; ?>
    </script>
