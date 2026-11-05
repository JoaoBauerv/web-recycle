<?php
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../../components/permissoes.php');
require_once(__DIR__ . '/../funcoes.php');
require_once(__DIR__ . '/material_lib.php');

exigirPermissao('material.gerenciar', $url_base);
csrfExigir($url_base . '/materiais');

$acao = $_POST['acao'] ?? '';
$id   = (int) ($_POST['id'] ?? 0);

/** Volta com a mensagem no padrão do components/alert.php. */
function materialVoltar(string $destino, string $tipo, string $mensagem): void
{
    header("Location: $destino" . (str_contains($destino, '?') ? '&' : '?') . "msg{$tipo}=" . urlencode($mensagem));
    exit;
}

/** "10,50" ou "10.50" -> 10.5; vazio ou inválido -> null. */
function materialPreco($valor): ?float
{
    $valor = str_replace(',', '.', trim((string) $valor));
    return is_numeric($valor) ? round((float) $valor, 2) : null;
}

/**
 * Confere os campos do formulário. Devolve os dados limpos ou encerra com a
 * mensagem de erro. Antes nada era conferido: um preço vazio ou um nome
 * repetido derrubava o INSERT, a mensagem ia para uma variável de sessão que
 * nenhuma tela exibia, e o usuário voltava para a lista sem saber de nada.
 */
function materialValidar(PDO $pdo, int $id, ?string $tipo_atual, string $voltar): array
{
    $nome           = nomeProprio($_POST['nome'] ?? '');
    $tipo           = $_POST['categoria'] ?? '';
    $preco_compra   = materialPreco($_POST['preco_compra'] ?? '');
    $preco_especial = materialPreco($_POST['preco_especial'] ?? '');

    if ($nome === '' || mb_strlen($nome) > 40) {
        materialVoltar($voltar, 'Erro', 'Informe o nome do material (até 40 caracteres).');
    }
    if (!isset(materialTiposPara($tipo_atual)[$tipo])) {
        materialVoltar($voltar, 'Erro', 'Selecione a categoria do material.');
    }
    if ($preco_compra === null || $preco_compra <= 0) {
        materialVoltar($voltar, 'Erro', 'Informe um preço normal maior que zero.');
    }
    if ($preco_especial === null || $preco_especial <= 0) {
        materialVoltar($voltar, 'Erro', 'Informe um preço especial maior que zero.');
    }

    // unique_nm_material vale para ativos e inativos: o nome não pode repetir.
    $dup = $pdo->prepare("SELECT status FROM material WHERE LOWER(nm_material) = LOWER(:nome) AND id_material <> :id");
    $dup->execute([':nome' => $nome, ':id' => $id]);
    $status_dup = $dup->fetchColumn();
    if ($status_dup !== false) {
        materialVoltar($voltar, 'Erro', (int) $status_dup === 1
            ? "Já existe um material chamado \"$nome\"."
            : "Já existe um material inativo chamado \"$nome\". Use outro nome.");
    }

    return [$nome, $tipo, $preco_compra, $preco_especial];
}

try {
    switch ($acao) {

        case 'cadastrar':
            $voltar = $url_base . '/materiais/novo';
            [$nome, $tipo, $preco_compra, $preco_especial] = materialValidar($pdo, 0, null, $voltar);

            // qt_estoque fica NULL, como no seed: material novo não tem saldo, e
            // zero gravado afirmaria um inventário que nunca houve.
            $stmt = $pdo->prepare("INSERT INTO material (nm_material, tipo, preco_compra, preco_especial)
                                   VALUES (:nome, :tipo, :preco, :especial)
                                   RETURNING id_material");
            $stmt->execute([':nome' => $nome, ':tipo' => $tipo, ':preco' => $preco_compra, ':especial' => $preco_especial]);
            $id_novo = (int) $stmt->fetchColumn();

            registraMovimentacao($_SESSION['id_usuario'], $_SESSION['id_usuario'],
                sprintf('Material #%d cadastrado: %s, compra R$ %s / especial R$ %s', $id_novo, $nome,
                    number_format($preco_compra, 2, ',', '.'), number_format($preco_especial, 2, ',', '.')),
                'Cadastro Material', $pdo);

            materialVoltar($url_base . '/materiais', 'Sucesso', "Material \"$nome\" cadastrado com sucesso!");

        case 'editar':
            $voltar = $url_base . '/materiais/editar?id=' . $id;

            $stmt = $pdo->prepare("SELECT nm_material, tipo, preco_compra, preco_especial FROM material WHERE id_material = ?");
            $stmt->execute([$id]);
            $antes = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$antes) {
                materialVoltar($url_base . '/materiais', 'Erro', 'Material não encontrado.');
            }

            [$nome, $tipo, $preco_compra, $preco_especial] = materialValidar($pdo, $id, $antes['tipo'], $voltar);

            $pdo->prepare("UPDATE material
                           SET nm_material = :nome, tipo = :tipo, preco_compra = :preco, preco_especial = :especial
                           WHERE id_material = :id")
                ->execute([':nome' => $nome, ':tipo' => $tipo, ':preco' => $preco_compra,
                           ':especial' => $preco_especial, ':id' => $id]);

            // O preço de compra é a base de toda pesagem e do cálculo de margem:
            // mudança de preço precisa deixar rastro de quem mudou, quando e de quanto.
            $mudancas = [];
            if ($antes['nm_material'] !== $nome) {
                $mudancas[] = "nome {$antes['nm_material']} -> $nome";
            }
            if ($antes['tipo'] !== $tipo) {
                $mudancas[] = "tipo {$antes['tipo']} -> $tipo";
            }
            if (round((float) $antes['preco_compra'], 2) !== $preco_compra) {
                $mudancas[] = sprintf('compra R$ %s -> %s', number_format((float) $antes['preco_compra'], 2, ',', '.'),
                    number_format($preco_compra, 2, ',', '.'));
            }
            if (round((float) $antes['preco_especial'], 2) !== $preco_especial) {
                $mudancas[] = sprintf('especial R$ %s -> %s', number_format((float) $antes['preco_especial'], 2, ',', '.'),
                    number_format($preco_especial, 2, ',', '.'));
            }
            if ($mudancas) {
                registraMovimentacao($_SESSION['id_usuario'], $_SESSION['id_usuario'],
                    sprintf('Material #%d editado: %s', $id, implode('; ', $mudancas)), 'Material editado', $pdo);
            }

            materialVoltar($url_base . '/materiais', 'Sucesso', "Material \"$nome\" atualizado com sucesso!");

        case 'excluir':
            $stmt = $pdo->prepare("UPDATE material SET status = 0 WHERE id_material = :id RETURNING nm_material");
            $stmt->execute([':id' => $id]);
            $nome = $stmt->fetchColumn();
            if ($nome === false) {
                materialVoltar($url_base . '/materiais', 'Erro', 'Material não encontrado.');
            }

            registraMovimentacao($_SESSION['id_usuario'], $_SESSION['id_usuario'],
                sprintf('Material #%d inativado: %s', $id, $nome), 'Material inativado', $pdo);

            materialVoltar($url_base . '/materiais', 'Sucesso', "Material \"$nome\" excluído com sucesso!");

        default:
            materialVoltar($url_base . '/materiais', 'Erro', 'Ação inválida.');
    }
} catch (Exception $e) {
    error_log('Erro ao salvar material: ' . $e->getMessage());
    materialVoltar($url_base . '/materiais', 'Erro', 'Não foi possível salvar o material. Tente novamente.');
}
