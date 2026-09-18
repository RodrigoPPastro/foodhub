# FOOD HUB — Convenções Técnicas e Contrato de Integração
### (Rodrigo — Arquitetura Técnica e Autenticação)

Este documento define as convenções para que todos os módulos do grupo sigam a mesma arquitetura e consigam integrar sem depender dos detalhes internos de outro módulo.

---

## 1. Estrutura de pastas

```text
foodhub/
├── config/
│   └── database.php              <- conexão PDO única
├── src/
│   ├── Controllers/              <- entrada/orquestração das requisições
│   ├── Services/                 <- regras de negócio
│   ├── Repositories/             <- acesso ao banco e SQL
│   └── Models/                   <- representação de entidades/dados
├── public/                       <- páginas e endpoints HTTP
│   ├── login.php
│   ├── 2fa.php
│   ├── recuperar_senha.php
│   ├── recuperar_senha_process.php
│   ├── redefinir_senha.php
│   ├── redefinir_senha_process.php
│   ├── dashboard.php
│   └── ...
└── database/
    └── auth_schema.sql           <- autenticação; Fernanda consolida no SQL oficial
```

**Regra de ouro:** o `Repository` é responsável pelo SQL/acesso a dados; `Service` concentra as regras de negócio; `Controller` orquestra a requisição; páginas em `public/` cuidam da apresentação/entrada HTTP.

---

## 2. Sessão e proteção de páginas

Todo módulo deve usar `SessionService` e `AuthGuard` para saber quem está autenticado e qual é o perfil. Não ler/gravar `$_SESSION` diretamente nas páginas ou módulos.

Exemplo:

```php
require_once __DIR__ . '/../src/Services/AuthGuard.php';

AuthGuard::requireLogin();
// ou
AuthGuard::requireMaster();
// ou, quando aplicável,
AuthGuard::requireComum();
```

Para dados do usuário autenticado:

```php
SessionService::usuarioIdAtual();
SessionService::nomeUsuarioAtual();
SessionService::ehMaster();
```

---

## 3. Tabelas de autenticação

O módulo de autenticação trabalha com:

- `usuario`
- `autenticacao_2fa`
- `log_acesso`

Qualquer outro módulo que precise relacionar dados ao usuário deve usar `usuario.id_usuario` como referência.

---

## 4. Cadastro de usuário (RF02)

O cadastro de usuário comum utiliza as mesmas regras de segurança do módulo de autenticação. A senha é transformada em hash antes de ser persistida e as respostas das perguntas de segurança também são armazenadas com hash.

---

## 5. Convenções de código

- Classes em `PascalCase` e métodos/variáveis em `camelCase`.
- Tabelas e colunas do banco em `snake_case`.
- SQL sempre com PDO e *prepared statements*.
- Senhas e respostas de segurança somente por meio do `PasswordService`.
- Sessão somente por `SessionService`/`AuthGuard`.
- Feedback visual sem `alert()` nativo; o padrão visual será definido no front-end do grupo.
- Cada integrante trabalha em sua branch e o merge em `main` ocorre somente após revisão de outra pessoa.

---

## 6. Recuperação de senha (RF28)

A recuperação de senha foi adicionada ao módulo de autenticação após a necessidade identificada pelo professor.

### Objetivo

Permitir que o usuário comum que esqueceu a senha recupere o acesso validando uma das perguntas de segurança previamente cadastradas e, depois, cadastrando uma nova senha.

### Fluxo

```text
LOGIN
  ↓
Esqueci minha senha
  ↓
recuperar_senha.php
  ↓
informa login
  ↓
pergunta de segurança
  ↓
resposta
  ├─ incorreta → conta tentativa
  │                └─ 3ª tentativa → bloqueia o fluxo
  └─ correta → autorização temporária de 5 minutos
                 ↓
          redefinir_senha.php
                 ↓
          nova senha + confirmação
                 ↓
          PasswordService::hash()
                 ↓
          UsuarioRepository::atualizarSenha()
                 ↓
          limpa estado da recuperação
                 ↓
          retorna ao login
                 ↓
          login normal + 2FA
```

### Regras

- Destinada ao usuário comum.
- O estado de recuperação é separado do estado do 2FA do login.
- A resposta da pergunta nunca é armazenada na sessão.
- O hash da resposta permanece no banco em `autenticacao_2fa`.
- São permitidas no máximo 3 tentativas.
- Após resposta correta, a autorização para redefinir a senha dura 5 minutos.
- A autorização é removida ao expirar ou após o uso.
- A nova senha é armazenada somente como hash.
- Após a redefinição, o sistema não autentica automaticamente o usuário.
- O usuário deve entrar novamente e concluir o 2FA normal.

### Arquivos

```text
public/recuperar_senha.php
public/recuperar_senha_process.php
public/redefinir_senha.php
public/redefinir_senha_process.php
src/Controllers/RecuperacaoSenhaController.php
src/Services/RecuperacaoSenhaService.php
src/Services/SessionService.php
```

A implementação reutiliza `UsuarioRepository`, `Autenticacao2FARepository` e `PasswordService`, sem criar uma nova tabela no banco.

---

## 7. Testes realizados do RF28

O fluxo foi validado manualmente com um usuário comum existente no banco.

- início da recuperação por login;
- exibição e resposta da pergunta de segurança;
- resposta correta;
- 1ª, 2ª e 3ª tentativas incorretas;
- bloqueio após a 3ª tentativa;
- autorização temporária de 5 minutos;
- redefinição da senha;
- retorno ao login;
- login com a senha nova;
- 2FA normal após a recuperação;
- acesso ao dashboard.

---

## 8. Script SQL oficial

O `auth_schema.sql` contém as tabelas do módulo de autenticação. O script SQL definitivo do projeto deve ser consolidado pela responsável pelo banco, junto com as demais tabelas do DER, evitando scripts divergentes.

---

## 9. Usuário master

O usuário master é criado diretamente no banco, não pela tela de cadastro.

Para gerar um hash de senha:

```text
php database/gerar_hash.php "SuaSenhaAqui"
```

Depois, utilizar o hash gerado no `INSERT` correspondente do banco.
