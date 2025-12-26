document.addEventListener('DOMContentLoaded', () => {
    cargarProductos();
});

const modal = document.getElementById('modalProducto');
const form = document.getElementById('formProducto');
const tbody = document.getElementById('tablaProductosBody');
const inputBuscar = document.getElementById('buscar');

// Búsqueda en tiempo real
inputBuscar.addEventListener('input', () => {
    cargarProductos(inputBuscar.value);
});

async function cargarProductos(query = '') {
    try {
        const res = await fetch(`/local3M/api/productos.php?action=listar&q=${encodeURIComponent(query)}`);
        const json = await res.json();
        
        tbody.innerHTML = '';
        if (json.success) {
            if (json.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No hay productos.</td></tr>';
                return;
            }
            json.data.forEach(p => {
                const stockClass = p.cantidad_piezas < 5 ? 'stock-low' : 'stock-ok';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <strong>${p.nombre_producto}</strong><br>
                        <small style="color:#666">${p.ubicacion || ''}</small>
                    </td>
                    <td><code style="background:#eee; padding:2px 5px; border-radius:3px;">${p.codigo_barras || '--'}</code></td>
                    <td><strong>$${parseFloat(p.precio_producto).toFixed(2)}</strong></td>
                    <td><span class="stock-badge ${stockClass}">${p.cantidad_piezas}</span></td>
                    <td class="text-right">
                        <button class="btn-action btn-edit" onclick="editarProducto(${p.id_productos})"><i class="fas fa-edit"></i></button>
                        <button class="btn-action btn-delete" onclick="eliminarProducto(${p.id_productos})"><i class="fas fa-trash"></i></button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (e) { console.error(e); }
}

// Abrir Modal (Nuevo)
function abrirModal() {
    form.reset();
    document.getElementById('id_productos').value = '';
    document.getElementById('modalTitle').textContent = 'Nuevo Producto';
    modal.style.display = 'flex';
}

// Abrir Modal (Editar)
async function editarProducto(id) {
    try {
        const res = await fetch(`/local3M/api/productos.php?action=obtener&id=${id}`);
        const json = await res.json();
        if (json.success) {
            const p = json.data;
            document.getElementById('id_productos').value = p.id_productos;
            document.getElementById('nombre_producto').value = p.nombre_producto;
            document.getElementById('codigo_barras').value = p.codigo_barras;
            document.getElementById('precio_producto').value = p.precio_producto;
            document.getElementById('cantidad_piezas').value = p.cantidad_piezas;
            document.getElementById('ubicacion').value = p.id_ubicacion || ''; // Ajusta si el campo en BD es diferente
            
            document.getElementById('modalTitle').textContent = 'Editar Producto';
            modal.style.display = 'flex';
        }
    } catch (e) { Swal.fire('Error', 'No se pudo cargar el producto', 'error'); }
}

function cerrarModal() {
    modal.style.display = 'none';
}

// Guardar
async function guardarProducto() {
    const formData = new FormData(form);
    formData.append('action', 'guardar');

    try {
        const res = await fetch('/local3M/api/productos.php', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        if (json.success) {
            Swal.fire({icon: 'success', title: 'Guardado', showConfirmButton: false, timer: 1000});
            cerrarModal();
            cargarProductos(inputBuscar.value);
        } else {
            Swal.fire('Error', json.error || 'Error desconocido', 'error');
        }
    } catch (e) { Swal.fire('Error', 'Error de conexión', 'error'); }
}

// Eliminar
function eliminarProducto(id) {
    Swal.fire({
        title: '¿Eliminar producto?',
        text: "No se podrá recuperar.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'eliminar');
            formData.append('id', id);
            
            await fetch('/local3M/api/productos.php', { method: 'POST', body: formData });
            cargarProductos(inputBuscar.value);
            Swal.fire('Eliminado', '', 'success');
        }
    });
}

function generarCodigoAleatorio() {
    const random = 'PROD' + Math.floor(Math.random() * 1000000);
    document.getElementById('codigo_barras').value = random;
}