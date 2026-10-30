-- =====================================================================
-- Remove o perfil "Gerente" — o sistema passa a ter só Admin e Usuario
-- =====================================================================
-- Quem estava como Gerente vira Usuario, e não Admin: rebaixar tira acesso,
-- promover concede mais do que a pessoa tinha. Entre errar para menos ou para
-- mais em controle de acesso, erra-se para menos — e promover é um clique na
-- tela de edição de usuários, caso seja essa a intenção.
--
-- A validação no PHP (functions/funcoes.php) já recusa qualquer valor fora de
-- Admin/Usuario, e usuarioPode() nega por padrão qualquer perfil desconhecido.
-- A constraint abaixo fecha o cerco também no banco.
-- =====================================================================

BEGIN;

UPDATE tb_usuario
SET permissao = 'Usuario'
WHERE permissao = 'Gerente';

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_usuario_permissao') THEN
        ALTER TABLE tb_usuario
            ADD CONSTRAINT ck_usuario_permissao CHECK (permissao IN ('Admin', 'Usuario'));
    END IF;
END $$;

COMMENT ON COLUMN tb_usuario.permissao IS
    'Perfil de acesso: Admin ou Usuario. As permissões por ação ficam em components/permissoes.php.';

COMMIT;
