# Banco de dados

Tudo o que é necessário para recriar o banco em outra máquina.

## Instalação do zero

Pré-requisitos: PostgreSQL 14 ou superior, e um banco vazio já criado.

```bash
createdb reciclagem

# 1. estrutura
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/0000_baseline_schema.sql

# 2. dados de referência e usuário administrador
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/0001_seed_inicial.sql
```

Depois, copie `.env.example` para `.env` e preencha `DB_HOST`, `DB_PORT`,
`DB_NAME`, `DB_USER`, `DB_PASS` e `URL_BASE`.

### Primeiro acesso

| | |
|---|---|
| e-mail | `admin@reciclagem.local` |
| senha | `admin@123` |

O sistema **exige a troca da senha no primeiro acesso** — o campo
`tb_usuario.precisa_alterar_senha` vem com `1` e bloqueia a navegação até a
troca. Troque antes de usar.

## O que cada arquivo é

| Arquivo | Papel |
|---|---|
| `0000_baseline_schema.sql` | Estrutura completa: 20 tabelas, 52 constraints, 23 índices. É o ponto zero — até 14/10/2026 as tabelas centrais não tinham DDL versionado |
| `0001_seed_inicial.sql` | Dados de referência: 33 materiais, 16 categorias de despesa, 7 centros de custo e o usuário administrador |
| `2026-*.sql` | Migrations datadas, em ordem cronológica |

**As migrations anteriores a 14/10/2026 já estão refletidas na baseline.** Ficam
no repositório como histórico. São idempotentes (`IF EXISTS` / `IF NOT EXISTS`),
então reaplicá-las não quebra nada — mas numa instalação do zero elas são
desnecessárias.

Migrations criadas **depois** dessa data precisam ser aplicadas em ordem, após a
baseline.

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
