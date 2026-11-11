
document.addEventListener('DOMContentLoaded', function () {
    const app = document.getElementById('appContainer');
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');

    if (!app || !sidebar) return;

    const chaveMenu = 'foodhub_sidebar_fixada';
    const larguraMobile = 850;

    const areaRodape = sidebar.querySelector('.sidebar-bottom');

    // Remove eventual botão antigo.
    document.getElementById('sidebarCollapse')?.remove();

    // Cria o botão de fixação apenas uma vez.
    let botaoFixar = sidebar.querySelector('.sidebar-pin');

    if (!botaoFixar && areaRodape) {
        botaoFixar = document.createElement('button');
        botaoFixar.type = 'button';
        botaoFixar.className = 'sidebar-pin';

        botaoFixar.innerHTML = `
            <svg class="icon" xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round"
                aria-hidden="true">
                <path d="M16 3l5 5-4 1-4 4 1 4-2 2-3-6-6-3 2-2 4 1 4-4z"/>
                <path d="m9 15-6 6"/>
            </svg>
            <span>Fixar menu</span>
        `;

        areaRodape.insertBefore(botaoFixar, areaRodape.firstChild);
    }

    function estaNoDesktop() {
        return window.innerWidth > larguraMobile;
    }

    function estaFixado() {
        return app.classList.contains('sidebar-pinned');
    }

    function atualizarBotaoFixar() {
        if (!botaoFixar) return;

        const fixado = estaFixado();

        botaoFixar.querySelector('span').textContent =
            fixado ? 'Desafixar menu' : 'Fixar menu';

        botaoFixar.setAttribute('aria-pressed', String(fixado));
        botaoFixar.title = fixado ? 'Desafixar menu' : 'Fixar menu';
    }

    function configurarDesktop() {
        app.classList.add('sidebar-collapsed');
        app.classList.remove('sidebar-expanded');

        const fixado = localStorage.getItem(chaveMenu) === 'true';

        app.classList.toggle('sidebar-pinned', fixado);

        sidebar.classList.remove('open');

        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'false');
        }

        atualizarBotaoFixar();
    }

    function configurarMobile() {
        app.classList.remove(
            'sidebar-collapsed',
            'sidebar-expanded',
            'sidebar-pinned'
        );

        sidebar.classList.remove('open');

        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'false');
        }

        atualizarBotaoFixar();
    }

    // Inicializa o estado conforme o tamanho da tela.
    if (estaNoDesktop()) {
        configurarDesktop();
    } else {
        configurarMobile();
    }

    // Expansão automática ao passar o mouse.
    sidebar.addEventListener('mouseenter', function () {
        if (!estaNoDesktop() || estaFixado()) return;

        app.classList.add('sidebar-expanded');
    });

    sidebar.addEventListener('mouseleave', function () {
        if (!estaNoDesktop() || estaFixado()) return;

        app.classList.remove('sidebar-expanded');
    });

    // Fixar ou desafixar o menu.
    if (botaoFixar) {
        botaoFixar.addEventListener('click', function () {
            if (!estaNoDesktop()) return;

            const fixado = !estaFixado();

            app.classList.toggle('sidebar-pinned', fixado);
            app.classList.remove('sidebar-expanded');

            localStorage.setItem(chaveMenu, String(fixado));

            atualizarBotaoFixar();
        });
    }

    // Abrir e fechar o menu no celular.
    if (menuToggle) {
        menuToggle.addEventListener('click', function () {
            if (estaNoDesktop()) return;

            sidebar.classList.toggle('open');

            const aberto = sidebar.classList.contains('open');

            menuToggle.setAttribute('aria-expanded', String(aberto));
        });
    }

    // Fecha o menu mobile ao clicar fora.
    document.addEventListener('click', function (event) {
        if (estaNoDesktop()) return;

        const clicouNoMenu = sidebar.contains(event.target);
        const clicouNoBotao = menuToggle &&
            menuToggle.contains(event.target);

        if (!clicouNoMenu && !clicouNoBotao) {
            sidebar.classList.remove('open');

            if (menuToggle) {
                menuToggle.setAttribute('aria-expanded', 'false');
            }
        }
    });

    // Fecha o menu mobile com ESC.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !estaNoDesktop()) {
            sidebar.classList.remove('open');

            if (menuToggle) {
                menuToggle.setAttribute('aria-expanded', 'false');
            }
        }
    });

    // Reconfigura ao redimensionar a janela.
    window.addEventListener('resize', function () {
        if (estaNoDesktop()) {
            configurarDesktop();
        } else {
            configurarMobile();
        }
    });
});
