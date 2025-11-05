</div> <!-- Cierra el .container de header.php -->

    <script>
    // SOLUCIÓN ANTI-CACHÉ (BOTÓN "ATRÁS")
    //
    // Tu análisis es correcto. Esto detecta si el navegador cargó la página 
    // desde su caché "rápida" (bfcache) al presionar "atrás".
    //
    // event.persisted será 'true' si la página viene del caché.
    //
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            // Si la página vino del caché (botón "atrás"), 
            // forzamos una recarga completa desde el servidor.
            //
            // El servidor (header.php) verá que la sesión ya no
            // existe y nos redirigirá al index.
            window.location.reload();
        }
    });
    </script>

</body>
</html>