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

    // Corrige comportamento das setas dos submenus
    document.querySelectorAll('.toggle-arrow').forEach(icon => {
        const targetSel = icon.getAttribute('data-bs-target');
        const target = document.querySelector(targetSel);
        if (!target) return;

        const collapse = bootstrap.Collapse.getOrCreateInstance(target, { toggle: false });

        // Clique na seta abre/fecha o submenu
        icon.addEventListener('click', e => {
            e.preventDefault();
            e.stopPropagation();
            collapse.toggle();
        });

        // Atualiza rotação conforme o estado real
        target.addEventListener('shown.bs.collapse', () => icon.classList.add('rotate'));
        target.addEventListener('hidden.bs.collapse', () => icon.classList.remove('rotate'));
    });
</script>

<?php
// ===========================
// Oculta o Tawk.to apenas no módulo PDV
// ===========================
$currentPath = $_SERVER['REQUEST_URI'];
$isPDV = (strpos($currentPath, '/modulos/pdv') !== false);

if (!$isPDV):
?>
<!--Start of Tawk.to Script-->
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
s1.async=true;
s1.src='https://embed.tawk.to/68f411030524d4194f53317d/1j7sm4tub';
s1.charset='UTF-8';
s1.setAttribute('crossorigin','*');
s0.parentNode.insertBefore(s1,s0);
})();
</script>
<!--End of Tawk.to Script-->
<?php endif; ?>

</body>
</html>