document.addEventListener('DOMContentLoaded', () => {
    cargarProductos();
    
    const inputCodigo = document.getElementById('codigo_barras');
    if (inputCodigo) {
        inputCodigo.addEventListener('input', generarPrevisualizacion);
    }
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
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">No hay productos.</td></tr>';
                return;
            }
            json.data.forEach(p => {
                const stockClass = p.cantidad_piezas < 5 ? 'stock-low' : 'stock-ok';
                const tr = document.createElement('tr');
                
                // ==========================================
                // LECTURA EXACTA COMO EN LA FUNCIÓN EDITAR
                // ==========================================
                // Leemos 'id_ubicacion' igual que lo haces abajo en tu código
                let rawUbi = p.id_ubicacion || p.ubicacion || ''; 
                let textoUbi = String(rawUbi).trim();

                let ubicacionTexto = '<span style="color: #ccc; font-style: italic;">Sin asignar</span>';
                
                // Si sí tiene texto y no es un nulo raro, lo mostramos
                if (textoUbi !== '' && textoUbi !== 'null' && textoUbi !== 'undefined') {
                    ubicacionTexto = textoUbi;
                }

                tr.innerHTML = `
                    <td>
                        <strong style="color: #2c3e50;">${p.nombre_producto}</strong>
                    </td>
                    <td><code style="background:#f1f1f1; padding:3px 6px; border-radius:4px; color: #e83e8c;">${p.codigo_barras || '--'}</code></td>
                    
                    <td style="font-weight: 500; color: #666;">
                        <i class="fas fa-map-marker-alt" style="margin-right: 5px; color: #adb5bd; font-size: 0.9em;"></i>${ubicacionTexto}
                    </td>

                    <td><strong>$${parseFloat(p.precio_producto).toFixed(2)}</strong></td>
                    <td><span class="stock-badge ${stockClass}">${p.cantidad_piezas}</span></td>
                    <td class="text-right" style="white-space: nowrap;">
                        <button class="btn-action" style="background-color: #17a2b8; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer;" onclick="imprimirEtiqueta('${p.codigo_barras}', '${p.nombre_producto}')" title="Imprimir Etiqueta"><i class="fas fa-print"></i></button>
                        <button class="btn-action btn-edit" onclick="editarProducto(${p.id_productos})"><i class="fas fa-edit"></i></button>
                        <button class="btn-action btn-delete" onclick="eliminarProducto(${p.id_productos})"><i class="fas fa-trash"></i></button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (e) { console.error(e); }
}

function imprimirEtiqueta(codigo, nombre) {
    if (!codigo || codigo === '--' || codigo === 'null' || codigo === 'undefined') {
        Swal.fire('Atención', 'Este producto no tiene código de barras asignado.', 'warning');
        return;
    }
    const url = `/local3M/imprimir_etiqueta.php?codigo=${encodeURIComponent(codigo)}&nombre=${encodeURIComponent(nombre)}`;
    window.open(url, '_blank', 'width=400,height=300');
}

function abrirModal() {
    form.reset();
    document.getElementById('id_productos').value = '';
    document.getElementById('modalTitle').textContent = 'Nuevo Producto';
    
    const contenedor = document.getElementById('barcodePreviewContainer');
    if(contenedor) contenedor.style.display = 'none';
    
    modal.style.display = 'flex';
}

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
            document.getElementById('ubicacion').value = p.id_ubicacion || ''; 
            
            document.getElementById('modalTitle').textContent = 'Editar Producto';
            
            generarPrevisualizacion();
            
            modal.style.display = 'flex';
        }
    } catch (e) { Swal.fire('Error', 'No se pudo cargar el producto', 'error'); }
}

function cerrarModal() {
    modal.style.display = 'none';
}

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
    generarPrevisualizacion();
}

// ==========================================
// FUNCIÓN: PREVISUALIZACIÓN DE CÓDIGO DE BARRAS (MODO GRANDE)
// ==========================================
function generarPrevisualizacion() {
    const valor = document.getElementById('codigo_barras').value.trim();
    const contenedor = document.getElementById('barcodePreviewContainer');
    
    if (valor.length > 0) {
        if(contenedor) contenedor.style.display = 'block';
        try {
            JsBarcode("#barcodePreview", valor, {
                format: "CODE128", 
                lineColor: "#000",
                width: 3,          // Hacemos las barras más gruesas
                height: 80,        // Hacemos el código más alto
                displayValue: true, 
                fontSize: 22,      // Letra más grande
                background: "transparent"
            });
        } catch (e) {
            console.warn("Código no válido para generar barras aún");
        }
    } else {
        if(contenedor) contenedor.style.display = 'none';
    }
}