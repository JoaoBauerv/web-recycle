# Banco de dados

Tudo o que é necessário para recriar o banco em outra máquina.

## Instalação do zero

Pré-requisitos: PostgreSQL 14 ou superior, e um banco vazio já criado.

```bash
createdb reciclagem

# 1. estrutura (ponto zero)
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/0000_baseline_schema.sql

# 2. migrations posteriores à baseline, em ordem cronológica
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/2026-10-14_padronizar_nomenclatura.sql
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/2026-10-14_endereco_unico_e_documento.sql
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/2026-10-14_remove_colunas_sem_uso.sql

# 3. dados de referência e usuário administrador
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/0001_seed_inicial.sql
```

A ordem importa: o seed usa os nomes de tabela e coluna já padronizados, então
ele é o **último** passo.

Depois, copie `.env.example` para `.env` e preencha `DB_HOST`, `DB_PORT`,
`DB_NAME`, `DB_USER`, `DB_PASS` e `URL_BASE`.

### Primeiro acesso

| | |
|---|---|
| e-mail | `admin@reciclagem.local` |
| senha | `admin@123` |

O sistema **exige a troca da senha no primeiro acesso** — o campo
`usuario.precisa_alterar_senha` vem com `1` e bloqueia a navegação até a
troca. Troque antes de usar.

## O que cada arquivo é

| Arquivo | Papel |
|---|---|
| `0000_baseline_schema.sql` | Estrutura completa no estado de 14/10/2026: 20 tabelas, 52 constraints, 23 índices. É o ponto zero — até essa data as tabelas centrais não tinham DDL versionado |
| `2026-10-14_padronizar_nomenclatura.sql` | Tira o prefixo `tb_` das tabelas e padroniza as PKs como `id_<entidade>` |
| `2026-10-14_endereco_unico_e_documento.sql` | Unifica o endereço numa tabela só, remove `documento` (o CPF/CNPJ vira coluna da entidade) e `public.produtos` |
| `2026-10-14_remove_colunas_sem_uso.sql` | Remove 8 colunas: deriváveis por `COUNT`, auditoria que `registro_movimento` já guarda, e uma nunca escrita nem lida |
| `0001_seed_inicial.sql` | Dados de referência: 33 materiais, 16 categorias de despesa, 7 centros de custo e o usuário administrador |
| `2026-*.sql` (demais) | Migrations datadas anteriores, em ordem cronológica |

**As migrations anteriores a 14/10/2026 já estão refletidas na baseline.** Ficam
no repositório como histórico. São idempotentes (`IF EXISTS` / `IF NOT EXISTS`),
então reaplicá-las não quebra nada — mas numa instalação do zero elas são
desnecessárias.

As três migrations **de** 14/10/2026 em diante **são necessárias**: a baseline
reflete o estado anterior a elas. O passo 2 da instalação acima aplica as três,
em ordem; migrations novas entram no fim dessa lista.

As migrations de 14/10/2026 **não** são idempotentes — uma segunda passada falha,
porque tentaria renomear tabela que já tem o nome novo ou remover coluna que já
saiu. Isso é de propósito: numa migração de estrutura, falhar alto é melhor que
seguir em silêncio sobre um banco em estado inesperado.

Cada uma roda inteira em uma transação e aborta tudo se a conferência dos dados
divergir — a de endereço compara campo por campo antes de derrubar coluna, e a
de remoção recalcula cada valor derivável a partir da origem.

Instalar do zero por este caminho produz **exatamente** o mesmo schema que
migrar um banco existente: 19 tabelas, 180 colunas, 69 constraints.

## O que o seed inicial não traz

Apenas dados de referência. Nada transacional: compras, vendas, estoque, contas
a pagar e importações começam vazios. O saldo de estoque de todo material entra
em zero — ele só se forma por compra registrada ou por inventário.

Materiais inativos não são exportados: dos 35 cadastrados na base de origem, o
seed leva os 33 ativos.

## Massas de demonstração

Os arquivos `seed_dados_demo.php`, `seed_operacao.php` e `seed_teste.php` geram
massa para validar relatórios. **Não são versionados**: são volumosos,
descartáveis e pressupõem um banco já povoado. Para instalação nova, só o
`0001_seed_inicial.sql` é necessário.

## Backup

```bash
pg_dump --no-owner --no-privileges -f backup-$(date +%Y%m%d).sql reciclagem
```

A pasta `sql/backup/` não é versionada.
