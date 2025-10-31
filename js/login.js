// Espera a que todo el HTML esté cargado
document.addEventListener('DOMContentLoaded', () => {

    // Seleccionamos el formulario y el botón
    const loginForm = document.getElementById('loginForm');
    const loginButton = document.getElementById('loginButton');

    // Escuchamos el evento 'submit' del formulario
    loginForm.addEventListener('submit', (e) => {
        // Prevenimos que el formulario se envíe de la forma tradicional
        e.preventDefault();

        // --- INICIO: Mostramos el spinner ---
        // Añadimos la clase '.loading' al botón
        loginButton.classList.add('loading');
        // Deshabilitamos el botón para evitar múltiples clics
        loginButton.disabled = true;
        // --- FIN: Mostramos el spinner ---

        // Capturamos los datos del formulario
        const formData = new FormData(loginForm);

        // Enviamos los datos al backend (api/login.php)
        fetch('api/login.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json()) // Esperamos una respuesta JSON
        .then(data => {
            
            // --- INICIO: Ocultamos el spinner ---
            loginButton.classList.remove('loading');
            loginButton.disabled = false;
            // --- FIN: Ocultamos el spinner ---

            // Procesamos la respuesta del servidor
            if (data.success) {
                // Si el login fue exitoso (success: true)
                // Redirigimos al dashboard
                window.location.href = 'dashboard.php';
            } else {
                // Si el login falló (success: false)
                // Mostramos un alerta de error con SweetAlert2
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message, // El mensaje viene de api/login.php
                    confirmButtonColor: '#004e92'
                });
            }
        })
        .catch(error => {
            // --- INICIO: Ocultamos el spinner (en caso de error) ---
            loginButton.classList.remove('loading');
            loginButton.disabled = false;
            // --- FIN: Ocultamos el spinner ---

            // Mostramos un error genérico si el servidor no responde
            console.error('Error en fetch:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo conectar con el servidor. Inténtalo de nuevo.',
                confirmButtonColor: '#004e92'
            });
        });
    });
});

