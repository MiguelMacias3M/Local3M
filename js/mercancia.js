document.addEventListener('DOMContentLoaded', () => {
    cargarMercancia();
});

const modal = document.getElementById('modalMercancia');
const form = document.getElementById('formMercancia');
const tbody = document.getElementById('tablaMercanciaBody');
const inputBuscar = document.getElementById('buscar');

// Búsqueda
inputBuscar.addEventListener('input', () => {
    cargarMercancia(inputBuscar.value);
});

async function cargarMercancia(query = '') {
    try {
        const res = await fetch(`/local3M/api/mercancia.php?action=listar&q=${encodeURIComponent(query)}`);
        const json = await res.json();
        
        tbody.innerHTML = '';
        if (json.success) {
            if (json.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding:20px;">No se encontró mercancía.</td></tr>';
                return;
            }
            json.data.forEach(p => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <strong>${p.marca} ${p.modelo}</strong><br>
                        <code style="color:#666; font-size:0.8rem;">${p.codigo_barras || '--'}</code>
                    </td>
                    <td>${p.compatibilidad || '-'}</td>
                    <td>${p.ubicacion || 'Sin asignar'}</td>
                    <td><strong>$${parseFloat(p.costo).toFixed(2)}</strong></td>
                    <td class="text-center">
                        <div class="stock-control">
                            <button class="btn-mini btn-minus" onclick="modificarStock(${p.id}, 'restar')">-</button>
                            <span class="stock-val">${p.cantidad}</span>
                            <button class="btn-mini btn-plus" onclick="modificarStock(${p.id}, 'sumar')">+</button>
                        </div>
                    </td>
                    <td class="text-right">
                        <button class="btn-action btn-edit" onclick="editarMercancia(${p.id})"><i class="fas fa-edit"></i></button>
                        <button class="btn-action btn-delete" onclick="eliminarMercancia(${p.id})"><i class="fas fa-trash"></i></button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (e) { console.error(e); }
}

// Modificar Stock Rápido
async function modificarStock(id, tipo) {
    try {
        const formData = new FormData();
        formData.append('action', 'stock');
        formData.append('id', id);
        formData.append('tipo', tipo);

        await fetch('/local3M/api/mercancia.php', { method: 'POST', body: formData });
        cargarMercancia(inputBuscar.value);
    } catch (e) { Swal.fire('Error', 'No se pudo actualizar stock', 'error'); }
}

// Modal Nuevo
function abrirModal() {
    form.reset();
    document.getElementById('id_mercancia').value = '';
    document.getElementById('modalTitle').textContent = 'Nueva Mercancía';
    document.getElementById('barcode-preview').style.display = 'none';
    modal.style.display = 'flex';
}

// Modal Editar
async function editarMercancia(id) {
    try {
        const res = await fetch(`/local3M/api/mercancia.php?action=obtener&id=${id}`);
        const json = await res.json();
        if (json.success) {
            const p = json.data;
            document.getElementById('id_mercancia').value = p.id;
            document.getElementById('marca').value = p.marca;
            document.getElementById('modelo').value = p.modelo;
            document.getElementById('compatibilidad').value = p.compatibilidad;
            document.getElementById('costo').value = p.costo;
            document.getElementById('cantidad').value = p.cantidad;
            document.getElementById('ubicacion').value = p.ubicacion || '';
            document.getElementById('codigo_barras').value = p.codigo_barras;
            
            document.getElementById('modalTitle').textContent = 'Editar Mercancía';
            
            if(p.codigo_barras){
                try {
                    JsBarcode("#barcode-svg", p.codigo_barras, {format:"CODE128", height:40, displayValue:true});
                    document.getElementById('barcode-preview').style.display = 'block';
                } catch(e){}
            } else {
                document.getElementById('barcode-preview').style.display = 'none';
            }

            modal.style.display = 'flex';
        }
    } catch (e) { Swal.fire('Error', 'Error al cargar datos', 'error'); }
}

function cerrarModal() { modal.style.display = 'none'; }

// Guardar
async function guardarMercancia() {
    const formData = new FormData(form);
    formData.append('action', 'guardar');

    try {
        const res = await fetch('/local3M/api/mercancia.php', { method: 'POST', body: formData });
        const json = await res.json();

        if (json.success) {
            Swal.fire({icon: 'success', title: 'Guardado', showConfirmButton: false, timer: 1000});
            cerrarModal();
            cargarMercancia(inputBuscar.value);
        } else {
            Swal.fire('Error', json.error || 'Error desconocido', 'error');
        }
    } catch (e) { Swal.fire('Error', 'Error de conexión', 'error'); }
}

// Eliminar
function eliminarMercancia(id) {
    Swal.fire({
        title: '¿Eliminar mercancía?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'eliminar');
            formData.append('id', id);
            
            await fetch('/local3M/api/mercancia.php', { method: 'POST', body: formData });
            cargarMercancia(inputBuscar.value);
            Swal.fire('Eliminado', '', 'success');
        }
    });
}

function generarCodigo() {
    // Generar MER + fecha + aleatorio
    const d = new Date();
    const codigo = 'MER' + d.getFullYear().toString().substr(-2) + 
                   (d.getMonth()+1).toString().padStart(2,'0') + 
                   Math.floor(Math.random() * 1000);
    document.getElementById('codigo_barras').value = codigo;
}