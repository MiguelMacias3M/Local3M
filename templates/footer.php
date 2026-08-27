<script>
    // Buscamos el botón y el menú por sus ID
    const toggleButton = document.getElementById('menu-toggle');
    const menu = document.getElementById('navbar-menu');

    // Si existen, agregamos el evento clic
    if (toggleButton && menu) {
        toggleButton.addEventListener('click', () => {
            // Esto quita o pone la clase 'active' que definimos en el CSS
            menu.classList.toggle('active');
        });
    }

    // Tu script anti-caché original
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>
<!-- Librería QZ Tray para Impresión Silenciosa -->
    <script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.2/qz-tray.min.js"></script>

    <!-- CONFIGURACIÓN VIP PARA IMPRESIÓN SILENCIOSA (Rutas Absolutas) -->
    