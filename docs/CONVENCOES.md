# FOOD HUB — Convenções Técnicas e Contrato de Integração
### (Rodrigo — Arquitetura Técnica e Autenticação)

Este documento existe pra todo mundo do grupo conseguir plugar o próprio
módulo sem precisar entender os detalhes internos da autenticação.

---

## 1. Estrutura de pastas proposta

```
foodhub/
├── config/
│   └── database.php        <- conexão PDO única (Database::getConnection())
├── src/
│   ├── Services/            <- regras de negócio reaproveitáveis
│   ├── Models/               <- uma classe por tabela, só SQL aqui dentro
│   └── (cada módulo pode criar Models/Services próprios seguindo o mesmo padrão)
├── public/                  <- ÚNICA pasta exposta pelo servidor web (document root)
│   ├── login.php, 2fa.php, logout.php, erro.php  (meus)
│   ├── dashboard.php         (Dandara)
│   ├── cardapio.php, estoque.php  (Fernanda)
│   ├── pedidos.php            (Pedro)
│   └── financeiro.php         (Lucas)
└── database/
    └── schema.sql            <- script único e definitivo (Fernanda consolida aqui)
```

**Regra de ouro:** cada Model só conversa com o banco (sem `echo`, sem HTML).
Cada página em `public/` só orquestra: chama Service/Model e imprime.

## 2. Como proteger uma página (todo módulo deve fazer isso)

```php
<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';

AuthGuard::requireLogin();   // qualquer usuário logado (ex.: tela de cardápio)
// ou
AuthGuard::requireMaster();  // só master (ex.: estoque, financeiro, pedidos, log)
// ou
AuthGuard::requireComum();   // só usuário comum (ex.: alterar a própria senha)
```

Depois disso, em qualquer lugar da página:

```php
SessionService::usuarioIdAtual();   // int|null
SessionService::nomeUsuarioAtual(); // string|null (login do usuário)
SessionService::ehMaster();         // bool
```

Isso já resolve o **RF10** (mostrar login no canto superior direito) — cada
página só precisa chamar `SessionService::nomeUsuarioAtual()` no cabeçalho.

## 3. Tabelas que eu (Rodrigo) já criei e mantenho

- `usuario`
- `autenticacao_2fa`
- `log_acesso`

Se seu módulo precisa de uma FK pra `usuario` (ex.: `pedido.id_usuario_cliente`,
`fechamento_caixa.id_usuario_responsavel`), é só referenciar `usuario.id_usuario`
normalmente — a tabela já existe a partir da Semana 1/2.

## 4. Cadastro de usuário (RF02) — ponto em aberto

Na divisão de tarefas, **ninguém ficou com o back-end do RF02** (Dandara tem a
tela, mas não a gravação/validação). Como isso usa `PasswordService::hash()` e
grava em `autenticacao_2fa` (que são meus), vou assumir esse back-end também,
a não ser que o grupo decida diferente — avisar na próxima reunião.

## 5. Convenções de código

- **Nomenclatura:** classes em `PascalCase`, métodos/variáveis em `camelCase`,
  nomes de tabelas/colunas em `snake_case` (já seguido no schema).
- **Banco:** sempre PDO com *prepared statements* (nunca concatenar SQL com
  variável do usuário — proteção contra SQL Injection).
- **Senhas/respostas do 2FA:** sempre via `PasswordService` — nunca
  `md5()`, nunca texto puro.
- **Sessão:** sempre via `SessionService`/`AuthGuard` — não ler `$_SESSION`
  direto fora dessas classes, pra manter uma fonte única de verdade.
- **Erros:** nunca usar `alert()` nativo (regra do enunciado); Dandara define
  o padrão visual de modal/toast e cada módulo reaproveita o mesmo componente.
- **Git:** 1 branch por pessoa/módulo (`feature/estoque`, `feature/pedidos`...),
  merge na `main` só depois de revisão de outra pessoa.

## 6. Sobre o script SQL oficial

Este pacote traz `database/auth_schema.sql` só com as 3 tabelas de
autenticação — **a Fernanda consolida isso no script único do projeto**,
junto com as tabelas do restante do DER (produto, insumo, pedido, mesa,
pagamento etc.), pra não termos dois scripts divergentes.

## 7. Criando o usuário master (exigência do enunciado)

O master é criado direto no banco, não pela tela de cadastro. Passo a passo:

```
php database/gerar_hash.php "SuaSenhaAqui"
```

Copia o hash gerado e usa no `INSERT` comentado dentro de `auth_schema.sql`.

## Fluxo Git e Pull Request

Alterações de código devem ser feitas em branches de funcionalidade e integradas à branch principal por Pull Request após revisão.
