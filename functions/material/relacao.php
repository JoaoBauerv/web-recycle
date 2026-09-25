<?php
/**
 * Cadastro do de/para de materiais.
 *
 * Relação nunca é apagada de verdade: vira status = 0, para o histórico de
 * importações continuar explicando por que uma planilha antiga foi interpretada
 * daquele jeito — mesma regra que o sistema já usa para material.
 */
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../../components/permissoes.php');
require_once(__DIR__ . '/../funcoes.php');
require_once(__DIR__ . '/../venda/planilha.php');

exigirPermissao('material.relacao', $url_base);

$tela = $url_base . '/materiais/relacoes';

function relacaoVoltar(string $tela, string $tipo, string $mensagem, string $extra = ''): void
{
    header("Location: $tela?msg{$tipo}=" . urlencode($mensagem) . $extra);
    exit;
}

$acao = $_REQUEST['acao'] ?? '';

switch ($acao) {

    case 'criar':
    case 'editar':
        $id_relacao     = (int) ($_POST['id_relacao'] ?? 0);
        $id_material    = (int) ($_POST['id_material'] ?? 0);
        $nome_externo   = trim($_POST['nome_externo'] ?? '');
        $codigo_externo = trim($_POST['codigo_externo'] ?? '');
        $id_modelo      = (int) ($_POST['id_modelo'] ?? 0) ?: null;

        if (!$id_material) {
            relacaoVoltar($tela, 'Erro', 'Selecione o material oficial.');
        }
        if ($nome_externo === '' && $codigo_externo === '') {
            relacaoVoltar($tela, 'Erro', 'Informe o nome externo, o código externo, ou os dois.');
        }

        $existe = $pdo->prepare("SELECT 1 FROM tb_material WHERE id_material = ? AND status = 1");
        $existe->execute([$id_material]);
        if (!$existe->fetchColumn()) {
            relacaoVoltar($tela, 'Erro', 'Material inválido ou inativo.');
        }

        // Normalizado pelo PHP para bater exatamente com o que a importação procura.
        $nome_norm   = $nome_externo !== '' ? planilhaNormalizar($nome_externo) : null;
        $codigo_norm = $codigo_externo !== '' ? planilhaChave($codigo_externo) : null;

        try {
            if ($acao === 'criar') {
                $stmt = $pdo->prepare("INSERT INTO material_relacao
                                          (id_material, nome_externo, nome_normalizado,
                                           codigo_externo, codigo_normalizado, id_modelo, origem)
                                       VALUES (:id_material, :nome, :nome_norm, :codigo, :codigo_norm, :id_modelo, 'Manual')");
                $stmt->execute([
                    ':id_material' => $id_material,
                    ':nome'        => $nome_externo ?: null,
                    ':nome_norm'   => $nome_norm,
                    ':codigo'      => $codigo_externo ?: null,
                    ':codigo_norm' => $codigo_norm,
                    ':id_modelo'   => $id_modelo,
                ]);
                $mensagem = 'Relacionamento cadastrado.';
            } else {
                if (!$id_relacao) {
                    relacaoVoltar($tela, 'Erro', 'Relacionamento não informado.');
                }
                $stmt = $pdo->prepare("UPDATE material_relacao
                                       SET id_material = :id_material, nome_externo = :nome,
                                           nome_normalizado = :nome_norm, codigo_externo = :codigo,
                                           codigo_normalizado = :codigo_norm, id_modelo = :id_modelo,
                                           data_alteracao = NOW()
                                       WHERE id_relacao = :id_relacao");
                $stmt->execute([
                    ':id_material' => $id_material,
                    ':nome'        => $nome_externo ?: null,
                    ':nome_norm'   => $nome_norm,
                    ':codigo'      => $codigo_externo ?: null,
                    ':codigo_norm' => $codigo_norm,
                    ':id_modelo'   => $id_modelo,
                    ':id_relacao'  => $id_relacao,
                ]);
                $mensagem = 'Relacionamento atualizado.';
            }
        } catch (PDOException $e) {
            // 23505 = violação de índice único: o mesmo apelido já aponta para
            // algum material naquela origem.
            if ($e->getCode() === '23505') {
                relacaoVoltar($tela, 'Erro', 'Já existe um relacionamento com esse nome ou código para esta origem.');
            }
            relacaoVoltar($tela, 'Erro', 'Não foi possível salvar: ' . $e->getMessage());
        }

        registraMovimentacao(
            $_SESSION['id_usuario'], $_SESSION['id_usuario'],
            sprintf('De/para %s: "%s" -> material #%d',
                $acao === 'criar' ? 'criado' : 'editado',
                $nome_externo ?: $codigo_externo, $id_material),
            'Relacionamento Material',
            $pdo
        );

        relacaoVoltar($tela, 'Sucesso', $mensagem);

    case 'inativar':
        $id_relacao = (int) ($_POST['id_relacao'] ?? 0);
        if (!$id_relacao) {
            relacaoVoltar($tela, 'Erro', 'Relacionamento não informado.');
        }

        $pdo->prepare("UPDATE material_relacao SET status = 0, data_alteracao = NOW() WHERE id_relacao = ?")
            ->execute([$id_relacao]);

        registraMovimentacao(
            $_SESSION['id_usuario'], $_SESSION['id_usuario'],
            sprintf('De/para #%d inativado', $id_relacao),
            'Relacionamento Material',
            $pdo
        );

        relacaoVoltar($tela, 'Sucesso', 'Relacionamento removido.');

    case 'codigo_material':
        // Preencher o código oficial do material a partir desta tela evita ter
        // de abrir o cadastro só para isso.
        $id_material = (int) ($_POST['id_material'] ?? 0);
        $codigo = trim($_POST['codigo'] ?? '');

        if (!$id_material) {
            relacaoVoltar($tela, 'Erro', 'Material não informado.');
        }

        try {
            $pdo->prepare("UPDATE tb_material SET codigo = :codigo WHERE id_material = :id")
                ->execute([':codigo' => $codigo !== '' ? $codigo : null, ':id' => $id_material]);
        } catch (PDOException $e) {
            relacaoVoltar($tela, 'Erro', 'Esse código já pertence a outro material.');
        }

        relacaoVoltar($tela, 'Sucesso', 'Código do material atualizado.', '&material=' . $id_material);

    default:
        header("Location: $tela");
        exit;
}
