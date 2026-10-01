<?php
/**
 * Peças de tela do cancelamento de documento, compartilhadas por compra e venda.
 *
 * As duas telas de detalhe precisam exatamente do mesmo aviso e do mesmo modal
 * de confirmação, mudando só o rótulo, o endpoint e o nome do campo de id — daí
 * o componente, em vez de duas cópias do markup.
 */

require_once __DIR__ . '/csrf.php';

/**
 * Aviso de documento cancelado, com motivo, data e quem cancelou.
 *
 * @param string  $rotulo  'Compra' ou 'Venda', para o texto.
 * @param array   $doc     Precisa ter cancelada_em e motivo_cancelamento.
 * @param ?string $autor   Nome de quem cancelou; null mostra "usuário removido".
 */
function cancelamentoAviso(string $rotulo, array $doc, ?string $autor): void
{
    $data = !empty($doc['cancelada_em'])
        ? date('d/m/Y \à\s H:i', strtotime($doc['cancelada_em']))
        : 'data não registrada';
    ?>
    <div class="alert alert-danger d-flex gap-3 align-items-start mb-4" role="alert">
        <i class="bi bi-x-octagon-fill fs-4 flex-shrink-0" aria-hidden="true"></i>
        <div>
            <h3 class="h6 alert-heading fw-semibold mb-1"><?= htmlspecialchars($rotulo) ?> cancelada</h3>
            <p class="mb-1">
                <strong>Motivo:</strong>
                <?= htmlspecialchars((string) ($doc['motivo_cancelamento'] ?? '')) ?>
            </p>
            <p class="mb-0 small">
                Cancelada em <?= $data ?>
                por <?= htmlspecialchars($autor ?? 'usuário removido') ?>.
                O estoque foi estornado e os valores abaixo não entram em nenhum total.
            </p>
        </div>
    </div>
    <?php
}

/**
 * Botão "Cancelar" e o modal que pede o motivo.
 *
 * O motivo é obrigatório na tela (required) e no endpoint, que não confia no
 * HTML, e também no banco, por ck_pesagem_cancelamento / ck_venda_cancelamento.
 *
 * @param string $rotulo    'Compra' ou 'Venda'.
 * @param string $acao_url  Endpoint que recebe o POST.
 * @param string $campo_id  'id_pesagem' ou 'id_venda'.
 * @param int    $id        Id do documento.
 * @param string $efeito    Frase que explica o que o estorno faz com o estoque.
 */
function cancelamentoBotao(string $rotulo, string $acao_url, string $campo_id, int $id, string $efeito): void
{
    $modal = 'modalCancelar' . ucfirst(strtolower($rotulo));
    ?>
    <button type="button" class="btn btn-outline-danger"
            data-bs-toggle="modal" data-bs-target="#<?= $modal ?>">
        <i class="bi bi-x-octagon me-1" aria-hidden="true"></i>
        Cancelar <?= htmlspecialchars(strtolower($rotulo)) ?>
    </button>

    <div class="modal fade" id="<?= $modal ?>" tabindex="-1"
         aria-labelledby="<?= $modal ?>Titulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="<?= htmlspecialchars($acao_url) ?>" class="modal-content">
                <?= csrfCampo() ?>
                <input type="hidden" name="<?= htmlspecialchars($campo_id) ?>" value="<?= $id ?>">

                <div class="modal-header">
                    <h5 class="modal-title" id="<?= $modal ?>Titulo">
                        Cancelar <?= htmlspecialchars(strtolower($rotulo)) ?> #<?= $id ?>?
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-3"><?= htmlspecialchars($efeito) ?></p>

                    <label for="<?= $modal ?>Motivo" class="form-label fw-semibold">
                        Motivo do cancelamento <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <textarea class="form-control" id="<?= $modal ?>Motivo" name="motivo"
                              rows="3" maxlength="500" required
                              placeholder="Ex: pesagem lançada em duplicidade"
                              aria-describedby="<?= $modal ?>Ajuda"></textarea>
                    <div id="<?= $modal ?>Ajuda" class="form-text">
                        Fica registrado no documento e na auditoria. Máximo de 500 caracteres.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Voltar
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-octagon me-1" aria-hidden="true"></i>
                        Confirmar cancelamento
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
