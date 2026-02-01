document.addEventListener('DOMContentLoaded', cargarUsuarios);

function togglePassword() {
    const input = document.getElementById('password');
    const icon = document.querySelector('.toggle-pass');
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = "password";
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function cargarUsuarios() {
    fetch('api/usuarios.php?action=listar')
    .then(r => r.json())
    .then(data => {
        const tbody = document.getElementById('tablaUsuarios');
        tbody.innerHTML = '';
        if(data.success && data.data.length > 0) {
            data.data.forEach(u => {
                // Generamos la fila SIN la fecha
                tbody.innerHTML += `
                    <tr>
                        <td>${u.id}</td>
                        <td><strong>${u.nombre}</strong></td>
                        <td><span class="status status-ready">${u.rol}</span></td>
                        <td>
                            <button class="btn-small" style="background:#007bff" onclick="editarUsuario('${u.id}', '${u.nombre}', '${u.rol}')"><i class="fas fa-edit"></i></button>
                            <button class="btn-small" style="background:#dc3545" onclick="eliminarUsuario(${u.id})"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="4" align="center">No hay usuarios.</td></tr>';
        }
    });
}

function abrirModal() {
    document.getElementById('modalUsuario').style.display = 'block';
    document.getElementById('formUsuario').reset();
    document.getElementById('userId').value = '';
    document.getElementById('modalTitulo').innerText = 'Nuevo Usuario';
}

function cerrarModal() {
    document.getElementById('modalUsuario').style.display = 'none';
}

function editarUsuario(id, nombre, rol) {
    document.getElementById('modalUsuario').style.display = 'block';
    document.getElementById('userId').value = id;
    document.getElementById('nombre').value = nombre;
    document.getElementById('rol').value = rol;
    document.getElementById('password').value = ''; 
    document.getElementById('password').placeholder = '(Sin cambios)';
    document.getElementById('modalTitulo').innerText = 'Editar Usuario';
}

document.getElementById('formUsuario').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData();
    formData.append('action', 'guardar');
    formData.append('id', document.getElementById('userId').value);
    formData.append('nombre', document.getElementById('nombre').value);
    formData.append('rol', document.getElementById('rol').value);
    formData.append('password', document.getElementById('password').value);
    
    fetch('api/usuarios.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            Swal.fire('Guardado', 'Usuario actualizado', 'success');
            cerrarModal();
            cargarUsuarios();
        } else { Swal.fire('Error', data.message, 'error'); }
    });
});

function eliminarUsuario(id) {
    Swal.fire({
        title: '¿Eliminar?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'eliminar');
            formData.append('id', id);
            fetch('api/usuarios.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(d => {
                if(d.success) { cargarUsuarios(); Swal.fire('Eliminado', '', 'success'); }
                else { Swal.fire('Error', d.message, 'error'); }
            });
        }
    });
}