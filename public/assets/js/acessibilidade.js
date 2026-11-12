/* ============================================================================
   FOOD HUB — acessibilidade em TODO o sistema (RF27)
   Um único script, usado por todas as telas (login, 2FA, cadastro, recuperação
   de senha, erro e o sistema logado). Controla três preferências, lembradas no
   navegador (localStorage) e aplicadas a qualquer página que carregue este arquivo:
     • tema ......... claro | escuro        (chave fh-tema)
     • contraste .... alto contraste ligado (chave fh-contraste)
     • fonte ........ 80% a 150%            (chave fh-fonte)
   Como as telas antigas usavam atributos diferentes (data-bs-theme do Bootstrap
   e data-theme), os DOIS são mantidos em sincronia com a escolha do usuário.
   ============================================================================ */
(function () {
    'use strict';
    var html = document.documentElement;
    var K_TEMA = 'fh-tema', K_CONTRASTE = 'fh-contraste', K_FONTE = 'fh-fonte';
    var estado = { escuro: false, contraste: false, fonte: 100 };

    function ler(chave) { try { return localStorage.getItem(chave); } catch (e) { return null; } }
    function gravar(chave, valor) { try { localStorage.setItem(chave, valor); } catch (e) { /* storage indisponível */ } }

    /* Preferências salvas (e migração das chaves antigas das telas de login). */
    var tema = ler(K_TEMA) || (ler('foodhub-tema') === 'dark' || ler('foodhub-theme') === 'dark' ? 'escuro' : 'claro');
    estado.escuro = tema === 'escuro';
    estado.contraste = ler(K_CONTRASTE) === '1';
    var f = parseInt(ler(K_FONTE), 10);
    if (!isNaN(f)) { estado.fonte = Math.min(150, Math.max(80, f)); }

    /* Aplica o estado na página (e atualiza os botões que existirem). */
    function aplicar() {
        var nome = estado.escuro ? 'dark' : 'light';
        html.setAttribute('data-bs-theme', nome);
        html.setAttribute('data-theme', nome);
        html.classList.toggle('tema-escuro', estado.escuro);
        html.style.fontSize = estado.fonte + '%';
        if (document.body) { document.body.classList.toggle('alto-contraste', estado.contraste); }

        marcar('btnTema', estado.escuro, estado.escuro ? 'Modo claro' : 'Modo escuro');
        marcar('btnContraste', estado.contraste);

        /* botões antigos de tema das telas públicas */
        var texto = document.getElementById('textoTema');
        if (texto) { texto.textContent = estado.escuro ? 'Modo claro' : 'Modo escuro'; }
        var antigo = document.getElementById('themeToggle');
        if (antigo) { antigo.textContent = estado.escuro ? 'Modo claro' : 'Modo escuro'; }
    }
    function marcar(id, ativo, rotulo) {
        var b = document.getElementById(id);
        if (!b) { return; }
        b.classList.toggle('ativo', ativo);
        b.setAttribute('aria-pressed', ativo ? 'true' : 'false');
        if (rotulo) { b.textContent = rotulo; }
    }

    function alternarTema()     { estado.escuro = !estado.escuro; gravar(K_TEMA, estado.escuro ? 'escuro' : 'claro'); aplicar(); notificar(); }
    function alternarContraste() { estado.contraste = !estado.contraste; gravar(K_CONTRASTE, estado.contraste ? '1' : '0'); aplicar(); notificar(); }
    function mudarFonte(novo)   { estado.fonte = Math.min(150, Math.max(80, novo)); gravar(K_FONTE, String(estado.fonte)); aplicar(); }
    /* Gráficos (Chart.js) e outros scripts podem ouvir este evento e se redesenhar. */
    function notificar() {
        document.dispatchEvent(new CustomEvent('fh:contraste', { detail: estado.contraste }));
        document.dispatchEvent(new CustomEvent('fh:tema', { detail: estado.escuro ? 'escuro' : 'claro' }));
    }

    /* Telas públicas não têm a barra do sistema: injeta uma barra flutuante com os mesmos botões. */
    function garantirBarra() {
        if (document.querySelector('.acessib-bar')) { return; }
        var barra = document.createElement('div');
        barra.className = 'acessib-bar acessib-flutuante';
        barra.setAttribute('role', 'toolbar');
        barra.setAttribute('aria-label', 'Opções de acessibilidade');
        barra.innerHTML =
            '<button type="button" id="btnTema" aria-pressed="false" title="Alternar modo escuro">Modo escuro</button>' +
            '<button type="button" id="btnContraste" aria-pressed="false" title="Alternar alto contraste">Alto contraste</button>' +
            '<button type="button" id="btnFonteM" aria-label="Diminuir fonte" title="Diminuir fonte">A−</button>' +
            '<button type="button" id="btnFonteR" aria-label="Tamanho padrão da fonte" title="Restaurar fonte">A</button>' +
            '<button type="button" id="btnFonteP" aria-label="Aumentar fonte" title="Aumentar fonte">A+</button>';
        document.body.appendChild(barra);
    }

    function ligar(id, fn) { var b = document.getElementById(id); if (b) { b.addEventListener('click', fn); } }

    function iniciar() {
        garantirBarra();
        ligar('btnTema', alternarTema);
        ligar('btnContraste', alternarContraste);
        ligar('btnFonteM', function () { mudarFonte(estado.fonte - 10); });
        ligar('btnFonteP', function () { mudarFonte(estado.fonte + 10); });
        ligar('btnFonteR', function () { mudarFonte(100); });
        ligar('alternarTema', alternarTema);   /* botão antigo de login/cadastro */
        ligar('themeToggle', alternarTema);    /* botão antigo de 2FA/recuperar senha */
        aplicar();
        notificar();
    }

    /* Tema e fonte entram já no <head>/início para evitar "piscar" de tema claro. */
    html.setAttribute('data-bs-theme', estado.escuro ? 'dark' : 'light');
    html.setAttribute('data-theme', estado.escuro ? 'dark' : 'light');
    html.classList.toggle('tema-escuro', estado.escuro);
    html.style.fontSize = estado.fonte + '%';

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', iniciar); } else { iniciar(); }

    window.FHAcessibilidade = { estado: estado, alternarTema: alternarTema, alternarContraste: alternarContraste };
})();
