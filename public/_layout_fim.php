
            <footer class="footer">
                FOOD HUB · Sistema de Gestão
                <span>© <?= date('Y') ?></span>
            </footer>
        </section><!-- /.dashboard-content -->
    </main>
</div><!-- /.app-container -->

<!-- Toasts e modal de confirmação (RF12/RNF07: nada de alert() nativo) -->
<div class="fh-toast-area" id="fhToastArea" aria-live="polite" aria-atomic="true"></div>
<div class="fh-overlay" id="fhConfirmOverlay" role="dialog" aria-modal="true" aria-labelledby="fhConfirmTitulo">
    <div class="fh-modal">
        <h3 id="fhConfirmTitulo">Confirmar</h3>
        <p id="fhConfirmTexto"></p>
        <div class="fh-modal-actions">
            <button type="button" class="btn-fh-outline" id="fhConfirmCancelar">Cancelar</button>
            <button type="button" class="btn-fh" id="fhConfirmOk">Confirmar</button>
        </div>
    </div>
</div>

<script src="assets/js/sidebar.js"></script>
<script src="assets/js/app.js"></script>
<?php if (SessionService::ehMaster() && !empty($_SESSION['mostrar_avisos'])): unset($_SESSION['mostrar_avisos']); ?>
<script src="assets/js/avisos.js"></script>
<?php endif; ?>
<?php foreach (($layoutScripts ?? []) as $_src): ?>
<script src="<?= h($_src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
