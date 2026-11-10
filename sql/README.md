# Banco de dados

Tudo o que é necessário para recriar o banco em outra máquina.

## Instalação do zero

Pré-requisitos: PostgreSQL 14 ou superior (instalação conferida no 16), e um
banco vazio já criado. O schema `reciclagem` é criado pelo primeiro arquivo —
não precisa criá-lo à mão.

```bash
createdb reciclagem

# 1. estrutura completa e final
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/0000_baseline_schema.sql

# 2. seed único: os dados de referência e o administrador
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/0001_seed_inicial.sql

# 3. (opcional, escolha UMA) dados fictícios -- nunca em produção
#    demonstração pequena (75 dias):
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/0002_seed_demo.sql
#    ou dois anos de operação, para testar relatórios com volume:
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/0003_seed_massa.sql
```

São **dois arquivos obrigatórios**: a estrutura e o seed. Desde 05/11/2026 a
baseline já é o schema final — não há migration para aplicar depois dela (ver
[Como a baseline chegou aqui](#como-a-baseline-chegou-aqui)). Cada um roda em
uma transação: ou entra inteiro, ou não entra.

Os arquivos estão em UTF-8. A baseline e o seed declaram a codificação no
topo (`SET client_encoding`), então não é preciso ajustar o terminal — sem isso
o psql assumiria a codificação do cliente, que no Windows em português é
WIN1252, e abortaria na categoria `Água`.

Testado: instalar com o cliente em WIN1252 e com o cliente em UTF-8 produz
exatamente os mesmos dados e os mesmos comentários de tabela e de coluna.

Depois, copie `.env.example` para `.env` e preencha `DB_HOST`, `DB_PORT`,
`DB_NAME`, `DB_USER`, `DB_PASS` e `URL_BASE`.

### No Neon

O banco também roda no Neon (projeto `gentle-heart-58184220`). Os mesmos dois
arquivos são aplicados com a URL direta que o `neon init` grava no `.env`:

```bash
psql -v ON_ERROR_STOP=1 -f sql/0000_baseline_schema.sql "$DATABASE_URL_UNPOOLED"
psql -v ON_ERROR_STOP=1 -f sql/0001_seed_inicial.sql    "$DATABASE_URL_UNPOOLED"
```

No psql do Windows as opções vêm **antes** da URL. Com `DATABASE_URL_UNPOOLED`
preenchida, o `banco.php` conecta no Neon e ignora as `DB_*`.

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
| `0000_baseline_schema.sql` | **A estrutura, completa e final:** 19 tabelas, 175 colunas, 68 constraints (19 PK, 3 UNIQUE, 28 FK e 18 CHECK), 26 índices próprios e 24 comentários de tabela e coluna |
| `0001_seed_inicial.sql` | **O seed único.** Dados de referência e o administrador |
| `0002_seed_demo.sql` | **Opcional.** Massa pequena de demonstração em todas as tabelas — ver abaixo |
| `0003_seed_massa.sql` | **Opcional, alternativa à 0002.** Dois anos de operação simulada, para testar volume — ver abaixo |
| `seed_funcoes.sql` | Funções comuns às duas seeds; incluído por elas, não se roda sozinho |
| `_arquivo/*.sql` | As 15 migrations já incorporadas na baseline — **histórico**, fora da instalação |

As contagens somam PK, UNIQUE, FK e CHECK. O PostgreSQL 17 e superiores também
registram cada `NOT NULL` em `pg_constraint`; esses ficam fora da conta.

## Como a baseline chegou aqui

O schema foi construído em três fases, e as migrations de cada uma estão em
`_arquivo/`:

| Fase | Migrations | O que fizeram |
|---|---|---|
| Até 01/10/2026 | 8 arquivos `2026-09-*` e `2026-10-01_*` | Tara, importação de vendas, estoque mínimo, financeiro, cancelamento. As tabelas centrais ainda não tinham DDL versionado |
| 14/10/2026 | `2026-10-14_padronizar_nomenclatura.sql` | Tira o prefixo `tb_` das tabelas e padroniza as PKs como `id_<entidade>` |
| | `2026-10-14_endereco_unico_e_documento.sql` | Unifica o endereço numa tabela só, remove `documento` (o CPF/CNPJ vira coluna da entidade) e `public.produtos` |
| | `2026-10-14_remove_colunas_sem_uso.sql` | Remove 8 colunas: deriváveis por `COUNT`, auditoria que `registro_movimento` já guarda, e uma nunca escrita nem lida |
| 04-05/11/2026 | `2026-11-04_registro_movimento_tipo.sql` | `registro_movimento.tipo` para `varchar(40)`: `Relacionamento Material` não cabia em 20 e quebrava a tela de relacionamentos |
| | `2026-11-04_registro_movimento_descricao.sql` | `registro_movimento.descricao` para `varchar(255)`: descrições de despesa e motivos de cancelamento estouravam 100 caracteres depois da operação já gravada |
| | `2026-11-05_remove_recorrencia_despesa.sql` | Remove as colunas de recorrência de `contas_pagar`, gravadas e nunca usadas |
| | `2026-11-05_conta_cancelada_com_pagamento.sql` | Só dados: devolve a `ativa` as despesas canceladas que tinham parcela paga, que sumiam dos relatórios |

Em 14/10/2026 o banco em produção foi exportado com `pg_dump --schema-only` e
virou a primeira baseline; as migrations daquela data e as de novembro eram
aplicadas depois dela. Em **05/11/2026 a baseline foi regerada** a partir do
banco resultante de todo esse caminho, e passou a ser o schema final.

**Conferido:** instalar pela baseline nova e instalar pelo caminho antigo
(baseline de 14/10 + as sete migrations) produzem `pg_dump --schema-only`
idênticos, linha por linha — mesmas tabelas, colunas, tipos, padrões,
constraints, índices, sequências e comentários.

### Atualizar um banco existente

Instalação nova **não** usa nada de `_arquivo/`. As migrations de lá servem
para atualizar um banco instalado antes de 05/11/2026: aplique, em ordem, as que
ele ainda não tem.

```bash
# banco instalado entre 14/10 e 04/11/2026, por exemplo:
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/_arquivo/2026-11-04_registro_movimento_tipo.sql
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/_arquivo/2026-11-04_registro_movimento_descricao.sql
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/_arquivo/2026-11-05_remove_recorrencia_despesa.sql
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/_arquivo/2026-11-05_conta_cancelada_com_pagamento.sql
```

Para saber em que ponto um banco está: se `registro_movimento.descricao` ainda
tem 100 caracteres, faltam as de novembro; se ainda existe `tb_usuario`, faltam
também as de 14/10.

As migrations de 14/10 e a de recorrência **não** são idempotentes — uma
segunda passada falha, porque tentaria renomear tabela que já tem o nome novo
ou remover coluna que já saiu. É de propósito: numa migração de estrutura,
falhar alto é melhor que seguir em silêncio sobre um banco em estado
inesperado. As de 04/11 e a de despesas canceladas podem ser reaplicadas.

As 8 migrations anteriores a 14/10/2026 só valem para o schema daquela época:
7 delas mexem em `tb_material`, `tb_usuario` e outras tabelas que a
padronização renomeou, e falham de saída num banco atual
(`ERRO: relação "tb_material" não existe`). São registro histórico.

## Massas de demonstração

`seed_dados_demo.php`, `seed_operacao.php` e `seed_teste.php` geram massa para
validar relatórios. Nunca foram versionados — são volumosos, descartáveis e
pressupõem um banco já povoado — e em 30/10/2026 saíram do diretório do projeto
para `C:\backups\web-recycle\sql\`. Para instalação nova, só o
`0001_seed_inicial.sql` é necessário.

### `0002_seed_demo.sql` — massa de demonstração versionada

**Opcional, nunca em produção.** Preenche todas as tabelas com dados fictícios
coerentes entre si, para apresentar o sistema ou testar telas e relatórios. Roda
depois do seed inicial:

```bash
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/0002_seed_demo.sql
```

| Tabela | Linhas | O que entra |
|---|---|---|
| `usuario` | +3 | operador (`Usuario`), gerente (`Admin`) e um inativo |
| `clientes` / `fornecedores` | 8 / 4 | catadores, cooperativas e sucateiros; indústrias recicladoras |
| `endereco` | 13 | dos usuários, clientes e fornecedores |
| `pesagem` / `pesagem_material` | 32 / 62 | compras dos últimos 75 dias, uma cancelada |
| `vendas` / `vendas_itens` | 9 / 17 | manuais e importadas, uma cancelada |
| `estoque_movimentacoes` | 83 | compras, vendas, estornos, uma perda e um ajuste |
| `importacao_*`, `material_relacao` | 1 / 3 / 4 | um modelo de planilha; importações concluída, com avisos e com erro |
| `contas_pagar` / `_parcelas` | 9 / 39 | pagas, vencidas, a vencer, parcelada (aluguel e internet em 12x) e cancelada |
| `evento` / `registro_movimento` | 5 / 79 | agenda e trilha de auditoria |

Usuários de demonstração, todos com a senha `admin@123` e **sem** troca
obrigatória: `fernanda.oliveira@example.com` (Admin) e
`carlos.ramos@example.com` (Usuario).

- **Datas relativas a `CURRENT_DATE`**: em qualquer dia em que rodar, o painel
  mostra compras recentes e contas vencidas e a vencer.
- **Estoque coerente**: o movimento é aplicado em ordem cronológica pelas mesmas
  regras do PHP, então `saldo_apos` é real e `material.qt_estoque` bate com a
  trilha. O arquivo confere isso no fim e aborta se divergir.
- **Dados fictícios**: CPF e CNPJ gerados com dígito verificador válido, e-mails
  em `example.com`. Também define `estoque_minimo` em quatro materiais, para o
  alerta de estoque baixo aparecer.
- **Só em banco recém-instalado**: aborta sem gravar nada se já houver cliente,
  compra ou venda. Para recomeçar, recrie o banco e rode a instalação de novo.

### `0003_seed_massa.sql` — dois anos de operação, para testar volume

**Opcional, nunca em produção, e alternativa à 0002**: rode uma ou a outra (a
0003 recusa banco que já tenha a 0002). Simula 730 dias de operação, dia a dia,
para testar relatórios, filtros, paginação e desempenho. Leva cerca de 15
segundos:

```bash
psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/0003_seed_massa.sql
```

| Tabela | Linhas (aprox.) | O que entra |
|---|---|---|
| `clientes` / `fornecedores` | 60 / 9 | 6 cooperativas, 8 sucatarias e 46 catadores, que entram e saem ao longo do período (5 inativados); compradores por grupo de material |
| `usuario` | +5 | gerente (Admin) e quatro operadores com períodos diferentes (um inativo) |
| `pesagem` / `pesagem_material` | 6.400 / 13.300 | compras de segunda a sábado, ~100 canceladas |
| `vendas` / `vendas_itens` | 1.000 / 2.400 | terças e sextas, por grupo; ~50 importadas por planilha, ~20 canceladas |
| `estoque_movimentacoes` | 16.000 | compras, vendas, estornos, perdas de inventário e sobras |
| `contas_pagar` / `_parcelas` | 280 / 440 | 24 meses de despesas, com atrasos e juros, vencidas em aberto e cancelamentos |
| `registro_movimento` | 8.500 | auditoria de tudo, inclusive 8 reajustes de preço |

O que a simulação reproduz:

- **Sazonalidade e crescimento**: dezembro forte, fevereiro fraco, volume ~50%
  maior no fim do período que no começo.
- **Clientes desiguais**: cooperativas e sucatarias trazem mais material e com
  mais frequência que catadores (sorteio ponderado).
- **Vendas por lote**: cada grupo (papel, plástico, metal, cobre, sucata) é
  vendido quando o estoque passa do lote mínimo; parte do papel chega por
  importação de planilha.
- **Preços que mudam**: começam 14% abaixo do catálogo e são reajustados a cada
  91 dias (com auditoria), então a margem varia no tempo.
- **Financeiro realista**: salários de quem trabalhava no mês, energia mais
  cara no verão, impostos de 3% sobre as vendas do mês anterior, contratos
  anuais lançados em 12x, equipamentos parcelados, contrato encerrado no meio
  (parcelas canceladas) e despesa cancelada por inteiro.

Além das conferências da 0002 (saldo igual à trilha), a 0003 confere que o
`saldo_apos` de cada movimentação é a soma acumulada em ordem cronológica — o
extrato do estoque conta a história na ordem certa — e que nenhuma despesa
cancelada tem parcela paga. `setseed()` torna a massa reproduzível.

Usuários: os mesmos da 0002 (`fernanda.oliveira@example.com` Admin,
`carlos.ramos@example.com`) mais `juliana.prado@example.com` e
`marcos.vieira@example.com`, todos com a senha `admin@123`.

### `seed_funcoes.sql`

Funções auxiliares comuns às duas seeds (compra, venda, estorno, movimentação
de estoque, conta a pagar, geração de CPF/CNPJ). **Não se roda sozinho**: cada
seed o inclui com `\ir seed_funcoes.sql`, por isso as seeds precisam ser
executadas pelo `psql`.

## Backup

```bash
pg_dump --no-owner --no-privileges -f backup-$(date +%Y%m%d).sql reciclagem
```

Os dumps também saíram do projeto, para `C:\backups\web-recycle\sql\backup\`.
Um dump carrega os dados reais do banco, inclusive documento, telefone e
e-mail de quem está cadastrado, e por isso nunca pode entrar no Git. O
`.gitignore` mantém `/sql/backup/` fechado mesmo com o caminho vazio hoje, para
o caso de alguém gerar um dump ali.
