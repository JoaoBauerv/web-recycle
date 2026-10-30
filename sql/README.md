# Banco de dados

Tudo o que é necessário para recriar o banco em outra máquina.

## Instalação do zero

Pré-requisitos: PostgreSQL 14 ou superior, e um banco vazio já criado. O schema
`reciclagem` é criado pelo primeiro arquivo — não precisa criá-lo à mão.

```bash
createdb reciclagem

# 1. estrutura (ponto zero)
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/0000_baseline_schema.sql

# 2. migrations posteriores à baseline, em ordem cronológica
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/2026-10-14_padronizar_nomenclatura.sql
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/2026-10-14_endereco_unico_e_documento.sql
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/2026-10-14_remove_colunas_sem_uso.sql

# 3. seed único: os dados de referência e o administrador
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/0001_seed_inicial.sql
```

São cinco arquivos, e **um só de dados**. A ordem importa: o seed usa os nomes de
tabela e coluna já padronizados, então ele é o **último** passo.

Os cinco arquivos estão em UTF-8. A baseline e o seed declaram a codificação no
topo (`SET client_encoding TO 'UTF8'`), então não é preciso ajustar o terminal —
sem isso o psql assumiria a codificação do cliente, que no Windows em português
é WIN1252, e abortaria na categoria `Água`. As duas migrations de 14/10 que têm
texto acentuado também declaram; `2026-10-14_padronizar_nomenclatura.sql` não
precisa, porque não tem um único caractere fora do ASCII.

Testado: instalar com o cliente em WIN1252 e com o cliente em UTF-8 produz
exatamente os mesmos dados e os mesmos comentários de tabela e de coluna.

Depois, copie `.env.example` para `.env` e preencha `DB_HOST`, `DB_PORT`,
`DB_NAME`, `DB_USER`, `DB_PASS` e `URL_BASE`.

### Conferência

Ao final, o banco tem 19 tabelas e apenas quatro delas com conteúdo:

```bash
psql -d reciclagem -c "SELECT relname, n_live_tup FROM pg_stat_user_tables
                        WHERE schemaname='reciclagem' AND n_live_tup > 0 ORDER BY 1"
```

```
 centro_custo       |  7
 despesa_categoria  | 16
 material           | 33
 usuario            |  1
```

### Primeiro acesso

| | |
|---|---|
| e-mail | `admin@reciclagem.local` |
| senha | `admin@123` |

O sistema **exige a troca da senha no primeiro acesso** — o campo
`usuario.precisa_alterar_senha` vem com `1` e bloqueia a navegação até a
troca. Troque antes de usar.

## O que o seed único cria

| Tabela | Linhas | O que é |
|---|---|---|
| `usuario` | 1 | o administrador do primeiro acesso, com a senha em hash bcrypt e a troca obrigatória marcada |
| `material` | 33 | o catálogo: nome, tipo, preço de compra, preço especial, preço de venda de referência e unidade |
| `despesa_categoria` | 16 | categorias do contas a pagar; `Funcionários` é a única do tipo `funcionario`, que habilita o vínculo com um usuário |
| `centro_custo` | 7 | Administrativo, Comercial, Logística, Manutenção, Operacional, Reciclagem e Recursos Humanos |

Nenhuma das quatro tem chave estrangeira para as outras, então a ordem entre
elas é livre. O seed as grava numa transação só: ou entra completo, ou não entra.

**Reaplicar é seguro, inclusive sobre um banco já em uso.** Cada bloco testa a
própria pré-condição com `NOT EXISTS` em vez de depender de índice único, porque
dois dos índices de nome são **parciais** (`WHERE status = 1`) e não protegem
uma linha que foi inativada pela exclusão lógica. O do administrador pergunta se
já existe algum Admin ativo, não se aquele e-mail existe — assim trocar o e-mail
do administrador não faz o seed criar um segundo.

No fim do arquivo há uma conferência que aborta a transação se o catálogo, as
categorias, os centros ou o administrador não estiverem lá. Seed que entra pela
metade deixaria o sistema abrindo sem catálogo ou sem acesso.

## O que o seed não traz

Apenas dados de referência. Nada transacional: compras, vendas, estoque, contas
a pagar e importações começam vazios.

O saldo de estoque de todo material entra em **NULL**, não em zero — o código
trata `NULL` como zero (`COALESCE(qt_estoque,0)`), e zero gravado afirmaria um
inventário que nunca houve. O saldo só se forma por compra registrada ou por
inventário.

`material.preco_venda` é o último preço praticado na venda ao fornecedor. No
seed ele entra como preço de referência do catálogo; a primeira venda de cada
material o sobrescreve.

Duas correções de catálogo em 30/10/2026, sobre dados herdados da base de
origem: **Lata** passou de compra 8,00 / especial 9,00 / venda 3,50 para
**10,00 / 11,00 / 12,00** — era o único material que vendia por menos da metade
do que comprava, e com a compra em 10,00 o preço especial precisava subir junto,
senão ficaria abaixo do normal e o benefício do cliente viraria penalidade
(11,00 é o mesmo padrão de `Roda`, que também compra a 10,00) —, e **Cobre
Misto** e **Cobre Vermelho** passaram de `tipo = 'Aluminio'` para `'Cobre'`.
Nenhuma das duas mexe em histórico: compra e venda guardam o preço praticado em
`pesagem_material.preco_un` e `vendas_itens.preco_un`, linha por linha.

Materiais inativos não são exportados: dos 35 cadastrados na base de origem, o
seed leva os 33 ativos.

## O que cada arquivo é

| Arquivo | Papel |
|---|---|
| `0000_baseline_schema.sql` | Estrutura completa no estado de 14/10/2026: 20 tabelas, 203 colunas, 71 constraints e 23 índices próprios. É o ponto zero — até essa data as tabelas centrais não tinham DDL versionado |
| `2026-10-14_padronizar_nomenclatura.sql` | Tira o prefixo `tb_` das tabelas e padroniza as PKs como `id_<entidade>` |
| `2026-10-14_endereco_unico_e_documento.sql` | Unifica o endereço numa tabela só, remove `documento` (o CPF/CNPJ vira coluna da entidade) e `public.produtos` |
| `2026-10-14_remove_colunas_sem_uso.sql` | Remove 8 colunas: deriváveis por `COUNT`, auditoria que `registro_movimento` já guarda, e uma nunca escrita nem lida |
| `0001_seed_inicial.sql` | **O seed único.** Dados de referência e o administrador |
| `_arquivo/2026-*.sql` | As 8 migrations anteriores a 14/10/2026 — **registro histórico**, já incorporadas na baseline e fora da instalação |

As contagens acima e as da seção seguinte somam PK, UNIQUE, FK e CHECK. O
PostgreSQL 17 e superiores também registram cada `NOT NULL` em `pg_constraint`;
são 112 no estado final e ficam fora da conta.

## As migrations antigas, em `_arquivo/`

**As 8 migrations anteriores a 14/10/2026 são registro histórico: já estão
incorporadas na baseline e não fazem parte da instalação.** Por isso saíram da
raiz de `sql/` e foram para `sql/_arquivo/` em 30/10/2026 — continuam
versionadas, só não se confundem mais com os arquivos que se roda.

Elas foram escritas para serem idempotentes (`IF EXISTS` / `IF NOT EXISTS`), mas
isso valia sobre o schema **daquela época**. Hoje 7 das 8 falham de saída, porque
mexem em `tb_material`, `tb_usuario`, `tb_pesagem` e `tb_pesagem_material` —
nomes que a migration de nomenclatura aposentou:

```
ERRO:  relação "tb_material" não existe
```

A única que ainda roda é
`_arquivo/2026-09-24_comprovante_venda_importado.sql`, que toca em `vendas`,
tabela que nunca teve o prefixo. Não é defeito: são registros de um schema que
já não existe, e falhar é melhor que aplicar meia alteração.

Duas delas trazem dados junto da estrutura, e por isso parecem concorrer com o
seed: `_arquivo/2026-09-25_financeiro.sql` insere as 16 categorias e os 7
centros de custo, e `_arquivo/2026-09-24_limpeza_estrutura.sql` recria como
inativos os materiais que compras antigas referenciavam. Numa instalação do zero
nenhuma das duas roda, então não há duplicação — e, pelo parágrafo anterior, hoje
as duas falham na primeira linha de qualquer jeito. O seed é a única origem
desses dados.

## As três migrations de 14/10

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
migrar um banco existente: 19 tabelas, 180 colunas, 69 constraints (19 PK,
3 UNIQUE, 28 FK e 19 CHECK) e 26 índices próprios.

## Massas de demonstração

`seed_dados_demo.php`, `seed_operacao.php` e `seed_teste.php` geram massa para
validar relatórios. Nunca foram versionados — são volumosos, descartáveis e
pressupõem um banco já povoado — e em 30/10/2026 saíram do diretório do projeto
para `C:\backups\web-recycle\sql\`. Para instalação nova, só o
`0001_seed_inicial.sql` é necessário.

## Backup

```bash
pg_dump --no-owner --no-privileges -f backup-$(date +%Y%m%d).sql reciclagem
```

Os dumps também saíram do projeto, para `C:\backups\web-recycle\sql\backup\`.
Um dump carrega os dados reais do banco, inclusive documento, telefone e
e-mail de quem está cadastrado, e por isso nunca pode entrar no Git. O
`.gitignore` mantém `/sql/backup/` fechado mesmo com o caminho vazio hoje, para
o caso de alguém gerar um dump ali.
