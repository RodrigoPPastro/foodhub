# FOOD HUB — Sistema de Gestão para Lanchonete

Projeto da faculdade (PHP 8 + MySQL/MariaDB, sem framework). Cobre os requisitos RF01–RF28 e RNF01–RNF11 do pré-projeto.

## Como rodar (XAMPP)

1. Copie a pasta do projeto para `C:\xampp\htdocs\foodhub` (a pasta **precisa** se chamar `foodhub`: os redirecionamentos usam `/foodhub/public/`).
2. Inicie **Apache** e **MySQL** no painel do XAMPP.
3. Abra o phpMyAdmin → aba **Importar** → escolha `database/foodhub_completo.sql` (ele cria o banco `foodhub`, as tabelas e os dados iniciais). Pode ser executado de novo sem problema. Requer **MariaDB** (o do XAMPP serve); no MySQL puro, remova as duas linhas `ALTER TABLE canal_venda ADD COLUMN IF NOT EXISTS` e deixe as colunas só no `CREATE TABLE`.
4. (Opcional, para apresentação) gere pedidos de demonstração dos últimos 7 dias:
   `C:\xampp\php\php.exe database\seed_demo.php`
5. Acesse **http://localhost/foodhub** (o `index.php` da raiz abre o login; a lista de pastas nunca aparece).

**Banco criado antes desta versão?** Em vez de recriar, importe uma vez (banco `foodhub` selecionado → Importar):
`database/migracao_setor_preparo.sql` (setores Cozinha/Bar) e `database/migracao_funcionarios.sql` (perfil Funcionário e permissões).
6. Se o seu MySQL tem senha, crie `config/database.local.php` ou use as variáveis `FOODHUB_DB_HOST/PORT/NAME/USER/PASS` (padrão: `127.0.0.1`, `3306`, `foodhub`, `root`, sem senha).

## Usuários de teste

| Perfil | Login | Senha | Respostas do 2FA (sorteia 1 pergunta) |
|---|---|---|---|
| Master | `master` | `Master1!` | mãe: `nao informado` · nascimento: `01/01/1990` · CEP: `20000000` |
| Cliente | `client` | `Cliente1` | mãe: `maria souza` · nascimento: `15/03/1995` · CEP: `22790000` |

## O que cada perfil vê

* **Master** — Dashboard, Pedidos (kanban), Cozinha, Bar, Mesas, Entregadores, Cardápio, Produtos, Categorias, Estoque, Fechamento de caixa, Contas a pagar/receber, Relatórios, Usuários, Auditoria, Integrações, Modelo do BD.
* **Cliente** — Cardápio com carrinho (delivery próprio), Meus pedidos, Alterar senha, Modelo do BD.
* **Funcionário** *(extensão do grupo)* — só as telas que o Master liberar em Usuários → Permissões
  (ver / incluir / alterar / excluir por tela; modelos Garçom, Cozinha, Bar, Caixa e Gerente). O servidor confere
  a permissão em cada página e em cada chamada de API (`src/Services/PermissaoService.php`).

## Mapa dos módulos

| Módulo | Páginas (`public/`) | API (`public/api/`) | Regras (`src/Services/`) |
|---|---|---|---|
| Autenticação, usuários, auditoria | login, 2fa, cadastro, recuperar/alterar senha, consulta_usuario, log_acesso | — | AuthService, TwoFactorService, … |
| Cardápio e produtos (RF01, RF13, RF14) | cardapio, produtos, categorias | produtos, categorias | ProdutoService, CategoriaService |
| Estoque (RF15, RF16) | estoque | insumos | InsumoService, EstoqueService |
| Pedidos (RF17–RF22) | pedidos, cozinha, bar, mesas, entregadores, meus_pedidos, integracoes | pedidos, mesas, entregadores, notificacoes, pedidos_webhook | PedidoService, MesaService, EntregadorService, NotificacaoService |
| Financeiro (RF23–RF26) | dashboard, fechamento_caixa, relatorios, usuarios_pdf | dashboard, fechamento_caixa, relatorios, pagamentos | DashboardService, FechamentoCaixaService, RelatorioService, PagamentoService |
| Contas a pagar/receber (extra) | contas | contas | ContaService |

Arquitetura em camadas: `public/` (telas e endpoints) → `Controllers` → `Services` (regras de negócio) → `Repositories` (SQL com PDO) → `Models`. Integrações externas ficam atrás de `Adapters` (RNF11): hoje o WhatsApp e as plataformas de delivery são **simulados**; para integrar de verdade basta trocar a classe do adapter.

## Regras importantes (bom para a defesa)

* **Preços nunca vêm do navegador**: o servidor lê o preço do banco e recalcula o total.
* **Fluxo do pedido**: novo → em preparo → pronto → saiu → entregue (delivery) ou pronto → entregue (balcão/mesa). Cancelar é possível antes de entregue.
* **Estoque**: a baixa dos insumos (pela ficha técnica) acontece quando o pedido entra em preparo; se faltar insumo, a mudança é recusada e nada é gravado. Cancelar devolve o estoque.
* **Setores de preparo**: cada produto tem um setor — *Cozinha*, *Bar* ou *Sem preparo* (Produtos → Editar).
  A tela da Cozinha mostra só os itens da cozinha; a do Bar, só os do bar. Cada setor inicia e marca pronto a
  própria parte; o pedido fica "pronto" quando o último setor termina. Itens sem preparo (água, lata) não aparecem
  em nenhuma tela: no salão (mesa/balcão) já entram como entregues; no delivery entram como prontos e saem junto
  com o pedido. Pedido só com itens sem preparo já nasce "entregue" (salão) ou "pronto" (delivery), com baixa de
  estoque imediata. Banco já existente: importe `database/migracao_setor_preparo.sql` uma vez.
* **Atraso**: pedido em preparo há mais de 20 min (`config/integracoes.php`) fica vermelho na cozinha/kanban e o cliente recebe uma mensagem (uma vez só).
* **Pagamento dividido**: um pedido ou a conta de uma mesa pode ser paga com várias formas; a soma tem que fechar o valor exato.
* **Delivery integrado (simulado)**: `POST /foodhub/public/api/pedidos_webhook.php` com cabeçalho `X-Webhook-Token: foodhub-demo-token`. Reenviar o mesmo `id_pedido_externo` não duplica o pedido. Há um simulador na tela **Integrações**.

```bash
curl -X POST http://localhost/foodhub/public/api/pedidos_webhook.php \
  -H "Content-Type: application/json" -H "X-Webhook-Token: foodhub-demo-token" \
  -d '{"plataforma":"ifood","id_pedido_externo":"IF-1001","cliente":{"nome":"Ana","telefone":"(+55)21-988887777"},
       "endereco":"Rua das Flores, 100","itens":[{"nome":"X-Burger Clássico","quantidade":2}],"forma_pagamento":"pix"}'
```

* **Contas a pagar e a receber** (extra, além dos RF do pré-projeto): contas a pagar lançadas à mão (aluguel, energia, fornecedor…) ou geradas ao dar **entrada no estoque**; contas a receber geradas **automaticamente** para cada pedido de plataforma = total − comissão do canal, vencendo no prazo de repasse (ex.: iFood 12% / 30 dias, configurável na aba "Comissão das plataformas"). Dá para baixar uma a uma ou em lote (ajustando o valor que realmente caiu) e estornar. "Vencida" = em aberto com vencimento passado. O **fluxo de caixa** soma vendas diretas (no dia do pagamento) + recebimentos baixados − pagamentos baixados; vendas de plataforma só entram quando o repasse é baixado. O **fechamento de caixa não muda** (continua somando os pagamentos dos pedidos).
* **Segurança**: senhas com hash, 2FA, sessão protegida por perfil em toda página e API, SQL sempre com parâmetros (PDO), toda saída escapada (anti-XSS), POST exige JSON + cabeçalho `X-Requested-With` (anti-CSRF), upload de foto validado por conteúdo (não só extensão).
* **Acessibilidade (RF27)**: barra no topo com alto contraste e tamanho de fonte (lembra a escolha entre páginas).

## Testes

```bash
php tests/modulos_test.php            # integração com MySQL/MariaDB real (cria e apaga o banco foodhub_teste)
php tests/smoke_test.php              # autenticação / 2FA
php tests/financeiro_smoke_test.php   # financeiro (SQLite em memória)
```

`modulos_test.php` usa `root` sem senha em `127.0.0.1`; ajuste as variáveis `FOODHUB_DB_*` se o seu for diferente.

## Observações

* Os gráficos do dashboard usam Chart.js por CDN (precisa de internet); o resto funciona offline.
* O token do webhook e a senha do banco devem ser trocados antes de qualquer uso real.

## Novidades desta atualização

* **Acessibilidade em todas as telas (RF27)**: modo escuro, alto contraste e tamanho da fonte, inclusive em login, 2FA, cadastro, recuperação de senha e erro. Script único: `public/assets/js/acessibilidade.js` (preferências em `fh-tema`, `fh-contraste`, `fh-fonte`).
* **Taxa de serviço de 10% (garçom)**: ao fechar a conta da mesa há a opção *Incluir taxa de serviço de 10%* (escolhe-se o garçom). O pagamento passa a ser conta + 10%, integral. A taxa não é venda: fica em `taxa_servico` e **não entra no total de vendas do fechamento de caixa**.
* **Taxa dos garçons (menu Gestão, permissão "caixa")**: quanto cada garçom tem a receber por período, botão **Pagar** (marca como repassado) e cadastro de garçons. O fechamento de caixa mostra a taxa cobrada/repassada/a repassar do dia.
* **Novo pedido com fotos**: categorias em chips, clique na foto para incluir, − / + na lista ao lado, busca por nome.
* **Quadro de pedidos**: mais novo no topo de cada coluna.
* Banco já criado? Rode `database/migracao_taxa_garcom.sql` (uma vez, pode repetir sem erro).
