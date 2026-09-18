# FOOD HUB — RF28: Recuperação de Senha

## 1. Objetivo

Permitir que o usuário comum que esqueceu a senha valide sua identidade por uma pergunta de segurança previamente cadastrada e cadastre uma nova senha, sem autenticação automática ao final do processo.

## 2. Fluxo funcional

```text
LOGIN
  ↓
Esqueci minha senha
  ↓
recuperar_senha.php
  ↓
informa login
  ↓
pergunta de segurança sorteada
  ↓
resposta
  ├─ incorreta → conta tentativa
  │                └─ 3ª tentativa → bloqueio
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
          limpeza do estado
                 ↓
          retorno ao login
                 ↓
          login normal + 2FA
```

## 3. Regras da funcionalidade

- Disponível para usuário comum.
- Utiliza perguntas de segurança já cadastradas.
- A resposta não é armazenada na sessão.
- O hash da resposta é consultado no banco em `autenticacao_2fa`.
- Limite de 3 tentativas.
- A autorização para redefinir a senha dura 5 minutos.
- O estado da recuperação é separado do `2fa_pendente` do login.
- A nova senha é armazenada usando hash por meio do `PasswordService`.
- A autorização é removida após o uso ou a expiração.
- Não ocorre login automático após a redefinição.
- O usuário precisa realizar novamente o login e o 2FA.

## 4. Componentes envolvidos

### Public

- `public/recuperar_senha.php`
- `public/recuperar_senha_process.php`
- `public/redefinir_senha.php`
- `public/redefinir_senha_process.php`

### Arquitetura

- `src/Controllers/RecuperacaoSenhaController.php`
- `src/Services/RecuperacaoSenhaService.php`
- `src/Services/SessionService.php`
- `src/Services/PasswordService.php`
- `src/Repositories/UsuarioRepository.php`
- `src/Repositories/Autenticacao2FARepository.php`

## 5. Estado da sessão

### `recuperacao_pendente`

Guarda somente:

- `id_usuario`
- `id_2fa`
- `pergunta`
- `tentativas`

### `recuperacao_autorizada`

Guarda somente:

- `id_usuario`
- `expira_em`

A validade é de 5 minutos.

## 6. Critérios de aceite

A funcionalidade é considerada correta quando:

1. O usuário comum consegue iniciar a recuperação.
2. Uma pergunta de segurança é apresentada.
3. Resposta incorreta consome uma tentativa.
4. A terceira resposta incorreta bloqueia o fluxo.
5. Resposta correta libera a redefinição por 5 minutos.
6. Uma senha nova válida pode ser cadastrada.
7. O usuário retorna ao login.
8. A senha antiga deixa de funcionar.
9. A senha nova permite iniciar o login normal.
10. O 2FA continua sendo exigido.
11. O usuário consegue chegar à área protegida após o 2FA.

## 7. Testes realizados

Foram realizados testes manuais do fluxo completo, incluindo:

- recuperação iniciada com login existente;
- resposta correta;
- 3 respostas incorretas e bloqueio;
- expiração da autorização temporária;
- redefinição da senha;
- login com a nova senha;
- 2FA após recuperação;
- acesso ao dashboard.
