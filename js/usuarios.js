document.addEventListener('DOMContentLoaded', () => {
    cargarUsuarios();
});

// Registrar Usuario
async function registrarUsuario() {
    const usuario = document.getElementById('nuevo_usuario').value.trim();
    const pass1 = document.getElementById('nueva_password').value;
    const pass2 = document.getElementById('confirm_password').value;
    const adminPass = document.getElementById('admin_password').value;

    // Validaciones Frontend
    if (!usuario || !pass1 || !pass2 || !adminPass) {
        Swal.fire('Campos vacíos', 'Por favor completa todos los campos.', 'warning');
        return;
    }

    if (pass1 !== pass2) {
        Swal.fire('Error', 'Las contraseñas no coinciden.', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'registrar');
    formData.append('nuevo_usuario', usuario);
    formData.append('nueva_password', pass1);
    formData.append('confirm_password', pass2);
    formData.append('admin_password', adminPass);

    try {
        const res = await fetch('/api/usuarios.php', { method: 'POST', body: formData });
        const json = await res.json();

        if (json.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Usuario Creado!',
                text: `El usuario ${usuario} ha sido registrado.`,
                timer: 2000,
                showConfirmButton: false
            });
            // Limpiar formulario
            document.getElementById('formRegistro').reset();
            cargarUsuarios();
        } else {
            Swal.fire('Error', json.error, 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Fallo de conexión', 'error');
    }
}

// Cargar Lista
async function cargarUsuarios() {
    try {
        const res = await fetch('/api/usuarios.php?action=listar');
        const json = await res.json();
        
        const tbody = document.getElementById('tablaUsuariosBody');
        tbody.innerHTML = '';

        if (json.success && json.data.length > 0) {
            json.data.forEach(u => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${u.id}</td>
                    <td><strong>${u.nombre}</strong></td>
                    <td class="text-right">
                        <button class="btn-delete" onclick="eliminarUsuario(${u.id}, '${u.nombre}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center">No hay usuarios.</td></tr>';
        }
    } catch (e) {}
}

// Eliminar Usuario (También pide clave maestra)
function eliminarUsuario(id, nombre) {
    Swal.fire({
        title: `¿Eliminar a ${nombre}?`,
        text: 'Ingresa la clave maestra para confirmar:',
        input: 'password',
        inputPlaceholder: 'Clave Admin',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Eliminar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'eliminar');
            formData.append('id', id);
            formData.append('admin_password', result.value);

            try {
                const res = await fetch('/api/usuarios.php', { method: 'POST', body: formData });
                const json = await res.json();

                if (json.success) {
                    Swal.fire('Eliminado', 'El usuario ha sido eliminado.', 'success');
                    cargarUsuarios();
                } else {
                    Swal.fire('Error', json.error, 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'Fallo de conexión', 'error');
            }
        }
    });
}