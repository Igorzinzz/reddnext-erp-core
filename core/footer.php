<!-- Footer global -->
<footer class="mt-auto py-3 border-top bg-white text-center text-muted small">
    <div class="container">
        <span>© <?= date('Y') ?> <?= $config['app_name'] ?> — Todos os direitos reservados.</span>
        <br>
        <span class="text-secondary">Desenvolvido por <strong>Reddnext Creative</strong></span>
    </div>
</footer>

<!-- Scripts Globais -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Alterna visibilidade da sidebar (menu lateral)
    const toggleMenu = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');

    if (toggleMenu) {
        toggleMenu.addEventListener('click', () => {
            sidebar.classList.toggle('d-none');
        });
    }

    // Ícones de setas animadas (submenu Financeiro, etc.)
    const toggles = document.querySelectorAll('.toggle-arrow');
    toggles.forEach(toggle => {
        toggle.addEventListener('click', () => {
            toggle.classList.toggle('rotate');
        });
    });
</script>