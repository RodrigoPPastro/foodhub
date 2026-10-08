/**
 * FOOD HUB — Barra de Acessibilidade (RF27)
 * Autor: Lucas
 *
 * Funções:
 *   - Aumentar / diminuir / restaurar o tamanho da fonte
 *   - Alternar o modo de alto contraste
 *   - Lembrar a escolha do usuário entre páginas (localStorage)
 *
 * Como usar (no <head> de cada página):
 *   <link rel="stylesheet" href="/foodhub/public/assets/css/acessibilidade.css">
 *   <script src="/foodhub/public/assets/js/acessibilidade.js"></script>
 *
 * Convivência com o tema claro/escuro da Dandara:
 *   este script NÃO mexe em data-theme, data-bs-theme nem na chave
 *   'foodhub-theme'. Ele usa a própria chave e a própria classe no <html>.
 */
(function () {
    'use strict';

    var CHAVE = 'foodhub-acessibilidade';

    // Percentuais do tamanho de fonte padrão do NAVEGADOR (100% = padrão).
    // Como o projeto usa rem, mudar o font-size do <html> escala tudo junto.
    var NIVEIS_FONTE = [100, 112.5, 125, 150, 175];

    var raiz = document.documentElement;

    /* ---------- Persistência (sempre dentro de try/catch) ---------- */

    function estadoPadrao() {
        return { nivel: 0, contraste: false };
    }

    function lerEstado() {
        try {
            var bruto = localStorage.getItem(CHAVE);
            if (!bruto) { return estadoPadrao(); }

            var salvo = JSON.parse(bruto);
            var nivel = parseInt(salvo.nivel, 10);

            // Valida o que veio do storage: nunca confie em dado externo.
            if (isNaN(nivel) || nivel < 0 || nivel >= NIVEIS_FONTE.length) {
                nivel = 0;
            }
            return { nivel: nivel, contraste: salvo.contraste === true };
        } catch (e) {
            // localStorage bloqueado ou JSON corrompido: segue com o padrão.
            return estadoPadrao();
        }
    }

    function salvarEstado() {
        try {
            localStorage.setItem(CHAVE, JSON.stringify(estado));
        } catch (e) { /* sem persistência, mas a página continua funcionando */ }
    }

    var estado = lerEstado();

    /* ---------- Aplicação das preferências na página ---------- */

    function aplicar() {
        // Nível 0 remove o estilo inline e devolve o controle ao navegador.
        raiz.style.fontSize = estado.nivel === 0
            ? ''
            : NIVEIS_FONTE[estado.nivel] + '%';

        raiz.classList.toggle('fh-ac-contraste', estado.contraste);
    }

    // Aplica já na carga do <head>, antes de a página ser desenhada,
    // para evitar o "pisca" de a página abrir normal e depois mudar.
    aplicar();

    /* ---------- Construção da barra ---------- */

    function criar(tag, atributos, texto) {
        var el = document.createElement(tag);
        Object.keys(atributos || {}).forEach(function (k) {
            el.setAttribute(k, atributos[k]);
        });
        if (texto) { el.textContent = texto; }
        return el;
    }

    function montarBarra() {
        if (document.getElementById('fh-ac-barra')) { return; } // evita duplicar

        var barra = criar('div', {
            id: 'fh-ac-barra',
            role: 'toolbar',
            'aria-label': 'Barra de acessibilidade'
        });

        var btnMenos = criar('button', {
            type: 'button', id: 'fh-ac-menos',
            'aria-label': 'Diminuir tamanho da fonte', title: 'Diminuir fonte'
        }, 'A-');

        var btnPadrao = criar('button', {
            type: 'button', id: 'fh-ac-padrao',
            'aria-label': 'Restaurar tamanho padrão da fonte', title: 'Fonte padrão'
        }, 'A');

        var btnMais = criar('button', {
            type: 'button', id: 'fh-ac-mais',
            'aria-label': 'Aumentar tamanho da fonte', title: 'Aumentar fonte'
        }, 'A+');

        var btnContraste = criar('button', {
            type: 'button', id: 'fh-ac-contraste',
            'aria-pressed': 'false', title: 'Alto contraste'
        }, 'Contraste');

        // aria-live: leitores de tela anunciam a mudança sem mover o foco.
        var status = criar('span', {
            id: 'fh-ac-status', 'aria-live': 'polite'
        });

        [btnMenos, btnPadrao, btnMais, btnContraste, status].forEach(function (el) {
            barra.appendChild(el);
        });
        document.body.appendChild(barra);

        function atualizarBarra() {
            btnMenos.disabled = estado.nivel === 0;
            btnMais.disabled = estado.nivel === NIVEIS_FONTE.length - 1;
            btnContraste.setAttribute('aria-pressed', String(estado.contraste));
            status.textContent = 'Fonte ' + NIVEIS_FONTE[estado.nivel] + '%';
        }

        function mudar(novoEstado) {
            estado = novoEstado;
            aplicar();
            salvarEstado();
            atualizarBarra();
        }

        btnMenos.addEventListener('click', function () {
            mudar({ nivel: Math.max(0, estado.nivel - 1), contraste: estado.contraste });
        });
        btnMais.addEventListener('click', function () {
            mudar({ nivel: Math.min(NIVEIS_FONTE.length - 1, estado.nivel + 1), contraste: estado.contraste });
        });
        btnPadrao.addEventListener('click', function () {
            mudar({ nivel: 0, contraste: estado.contraste });
        });
        btnContraste.addEventListener('click', function () {
            mudar({ nivel: estado.nivel, contraste: !estado.contraste });
        });

        atualizarBarra();
    }

    // Script no <head>: o <body> ainda não existe, então espera o DOM carregar.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', montarBarra);
    } else {
        montarBarra();
    }
})();
