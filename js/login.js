// Esperar a que todo el HTML esté cargado
document.addEventListener('DOMContentLoaded', function() {
    
    const loginForm = document.getElementById('loginForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            // 1. Prevenir que el formulario se envíe de la forma tradicional
            e.preventDefault();
            
            // 2. Obtener los datos del formulario
            const formData = new FormData(loginForm);
            
            // 3. Enviar los datos a la API usando fetch
            fetch('api/login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json()) // 4. Convertir la respuesta de la API a JSON
            .then(data => {
                // 5. Manejar la respuesta
                if (data.success) {
                    // Éxito: Mostrar SweetAlert y redirigir
                    Swal.fire({
                        icon: 'success',
                        title: '¡Conectado!',
                        text: data.message,
                        timer: 1500,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => {
                        // Redirigir a la página que dijo la API
                        window.location.href = data.redirect;
                    });
                } else {
                    // Error: Mostrar SweetAlert de error
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: data.message,
                        showConfirmButton: true
                    });
                }
            })
            .catch(error => {
                // Error de red o del servidor
                console.error('Error en fetch:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo conectar con el servidor.',
                    showConfirmButton: true
                });
            });
        });
    }
});