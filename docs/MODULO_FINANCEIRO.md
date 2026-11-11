# FOOD HUB — Módulo Financeiro, Relatórios e Dashboard (Lucas)
RF22 (pagamento) · RF23 (fechamento de caixa + PDF) · RF24 (relatórios) · RF25 (dashboard) · RF26 (usuários em PDF)

## Endpoints (todos exigem sessão Master; erro = JSON `{sucesso:false, mensagem}`)
| Método | Rota (`public/`) | Função |
|---|---|---|
| GET | `api/dashboard.php` | indicadores + dados dos gráficos (RF25) |
| GET | `api/relatorios.php?relatorio=vendas\|produtos\|insumos\|ticket\|canais&inicio&fim&limite` | RF24 |
| GET / POST | `api/fechamento_caixa.php` | GET = prévia/consulta; POST JSON `{data, observacoes}` = fecha (RF23) |
| GET / POST | `api/pagamentos.php` | RF22 — POST JSON `{id_pedido, pagamentos:[{forma, valor}]}` |
| GET | `fechamento_caixa_pdf.php?data=` | download do PDF (RF23) |
| GET | `usuarios_pdf.php` | download do PDF (RF26) |

POST exige `Content-Type: application/json` **e** `X-Requested-With: FoodHub` (proteção CSRF). O `foodhub-comum.js` (`FH.api`) já faz isso.

## Contrato com os outros módulos
* **Pedro (Pedidos):** chamar `PagamentoService::registrar($idPedido, 'pix', 25.50)` (ou `registrarMultiplos`) no servidor — inclusive no Delivery Próprio (cliente comum, que não acessa `api/pagamentos.php`).
* **Fernanda (BD):** aplicar `database/financeiro_schema.sql` após consolidar o SQL oficial (coluna `fechamento_caixa.detalhamento`, UNIQUE em `data_referencia`, índices, formas de pagamento).
* **Strings de status** ficam em `src/Models/PedidoStatus.php`; canais em `CanalVenda.php`; formas em `FormaPagamento.php`.
* **Regra:** venda válida = pedido com status diferente de `cancelado`.

## Testes
`php tests/financeiro_smoke_test.php` (SQLite em memória; não precisa de MySQL).
