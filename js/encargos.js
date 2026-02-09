document.addEventListener('DOMContentLoaded', function() {
    cargarEncargos();

    // Evento para agregar nuevo encargo con Enter
    document.getElementById('nuevo-encargo').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            agregarEncargo();
        }
    });
});

function cargarEncargos() {
    fetch('api/encargos.php?action=listar')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderizarLista(data.pendientes, 'lista-pendientes', false);
                renderizarLista(data.completados, 'lista-completados', true);
                
                // --- AQUÍ ESTÁ LA CORRECCIÓN DEL CONTADOR ---
                actualizarContador(data.pendientes.length);
            }
        });
}

function actualizarContador(cantidad) {
    const badge = document.getElementById('contador-pendientes');
    if (cantidad === 0) {
        badge.textContent = "¡Todo listo!";
        badge.style.background = "#198754"; // Verde cuando no hay pendientes
    } else {
        badge.textContent = cantidad + " Pendientes";
        badge.style.background = "linear-gradient(45deg, #0d6efd, #0dcaf0)"; // Azul normal
    }
}

function renderizarLista(lista, contenedorId, esCompletado) {
    const contenedor = document.getElementById(contenedorId);
    contenedor.innerHTML = '';

    if (lista.length === 0) {
        if (!esCompletado) {
            contenedor.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-mug-hot fa-3x mb-3" style="opacity:0.2"></i><br>
                    ¡Nada pendiente! Tómate un café ☕
                </div>`;
        }
        return;
    }

    lista.forEach(item => {
        const li = document.createElement('li');
        li.className = `list-group-item d-flex justify-content-between align-items-center ${esCompletado ? 'list-group-item-success' : ''}`;
        
        const icono = esCompletado ? '<i class="fas fa-check-circle"></i>' : '<i class="far fa-circle"></i>';
        const textoClase = esCompletado ? 'text-decoration-line-through text-muted' : '';
        const accion = esCompletado ? `eliminarEncargo(${item.id})` : `completarEncargo(${item.id})`;
        
        // Formatear fecha para que se vea amigable (ej: "Hace 5 min")
        // O simplemente mostrar la hora si es de hoy
        
        li.innerHTML = `
            <div class="d-flex align-items-center flex-grow-1" style="cursor:pointer;" onclick="${accion}">
                <span class="mr-3 h4 mb-0 icon-check">${icono}</span>
                <div class="ms-3">
                    <span class="${textoClase}" style="font-size:1.1rem;">${item.descripcion}</span>
                    <br>
                    <small class="text-muted">
                        <i class="fas fa-user-circle"></i> ${item.usuario} &bull; ${item.fecha_registro}
                    </small>
                </div>
            </div>
        `;
        contenedor.appendChild(li);
    });
}

function agregarEncargo() {
    const input = document.getElementById('nuevo-encargo');
    const descripcion = input.value.trim();

    if (!descripcion) return;

    fetch('api/encargos.php?action=crear', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ descripcion: descripcion })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            cargarEncargos();
        } else {
            Swal.fire('Error', data.error || 'No se pudo guardar', 'error');
        }
    });
}

function completarEncargo(id) {
    fetch('api/encargos.php?action=completar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            cargarEncargos();
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: 'Encargo completado'
            });
        }
    });
}

function eliminarEncargo(id) {
    fetch('api/encargos.php?action=eliminar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            cargarEncargos();
        }
    });
}