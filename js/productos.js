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

let timeoutBusqueda; // Variable para controlar la velocidad del escáner

if (inputBuscar) {
    // 1. Freno para escritura manual (espera 300ms a que dejes de escribir)
    inputBuscar.addEventListener('input', () => {
        clearTimeout(timeoutBusqueda);
        timeoutBusqueda = setTimeout(() => {
            cargarProductos(inputBuscar.value.trim());
        }, 300); 
    });

    // 2. Freno automático para el disparo rápido del escáner de código de barras
    inputBuscar.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            clearTimeout(timeoutBusqueda);
            cargarProductos(inputBuscar.value.trim());
        }
    });
}
async function cargarProductos(query = '') {
    try {
        const res = await fetch(`/local3M/api/productos.php?action=listar&q=${encodeURIComponent(query)}`);
        const json = await res.json();
        
        tbody.innerHTML = '';
        if (json.success) {
            if (json.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:#86868b;">No se encontraron productos.</td></tr>';
                return;
            }
            json.data.forEach(p => {
                const stockClass = p.cantidad_piezas < 5 ? 'badge-red' : 'badge-green';
                const tr = document.createElement('tr');
                
                // Hacemos que toda la fila abra el modal de editar
                tr.onclick = () => editarProducto(p.id_productos);
                tr.style.cursor = 'pointer'; // Cursor de manita
                
                let rawUbi = p.id_ubicacion || p.ubicacion || ''; 
                let textoUbi = String(rawUbi).trim();
                let ubicacionTexto = '<span style="color: #c7c7cc; font-style: italic;">Sin asignar</span>';
                
                if (textoUbi !== '' && textoUbi !== 'null' && textoUbi !== 'undefined') {
                    ubicacionTexto = `<span style="font-weight:500; color:#48484a;">${textoUbi}</span>`;
                }

                // Quitamos el botón de editar y agregamos event.stopPropagation() a los demás
                // para que al hacer clic en Eliminar o Imprimir, no se abra el modal de editar.
                tr.innerHTML = `
                    <td>
                        <strong style="color: #1d1d1f; font-size: 15px;">${p.nombre_producto}</strong>
                    </td>
                    <td><span class="badge-code">${p.codigo_barras || '--'}</span></td>
                    
                    <td>
                        <i class="fas fa-map-marker-alt" style="margin-right: 6px; color: #007aff; opacity:0.7;"></i>${ubicacionTexto}
                    </td>

                    <td style="color: #007aff; font-weight: 700; font-size: 16px;">
                        $${parseFloat(p.precio_producto).toFixed(2)}
                    </td>
                    
                    <td><span class="${stockClass}">${p.cantidad_piezas} unds.</span></td>
                    
                    <td class="text-right" style="white-space: nowrap;">
                        <button class="btn-icon print" onclick="event.stopPropagation(); imprimirEtiqueta('${p.codigo_barras}', '${p.nombre_producto}')" title="Imprimir Etiqueta">
                            <i class="fas fa-print"></i>
                        </button>
                        <button class="btn-icon delete" onclick="event.stopPropagation(); eliminarProducto(${p.id_productos})" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (e) { console.error("Error al cargar productos:", e); }
}

function imprimirInventario() {
    window.open('/local3M/imprimir_inventario.php', '_blank');
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
    setTimeout(() => document.getElementById('nombre_producto').focus(), 100);
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
            document.getElementById('ubicacion').value = p.ubicacion || '';            
            document.getElementById('modalTitle').textContent = 'Editar Producto';
            generarPrevisualizacion();
            modal.style.display = 'flex';
        }
    } catch (e) { 
        Swal.fire('Error', 'No se pudo cargar el producto', 'error'); 
    }
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
            Swal.fire({
                icon: 'success', 
                title: 'Guardado correctamente', 
                showConfirmButton: false, 
                timer: 1200
            });
            cerrarModal();
            cargarProductos(inputBuscar.value);
        } else {
            Swal.fire('Error', json.error || 'Error desconocido', 'error');
        }
    } catch (e) { 
        Swal.fire('Error', 'Error de conexión', 'error'); 
    }
}

function eliminarProducto(id) {
    Swal.fire({
        title: '¿Eliminar producto?',
        text: "Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff3b30',
        cancelButtonColor: '#86868b',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'eliminar');
            formData.append('id', id);
            
            await fetch('/local3M/api/productos.php', { method: 'POST', body: formData });
            cargarProductos(inputBuscar.value);
            Swal.fire('Eliminado', 'El producto ha sido borrado.', 'success');
        }
    });
}

function generarCodigoAleatorio() {
    const random = 'PROD' + Math.floor(Math.random() * 1000000);
    document.getElementById('codigo_barras').value = random;
    generarPrevisualizacion();
}

function generarPrevisualizacion() {
    const valor = document.getElementById('codigo_barras').value.trim();
    const contenedor = document.getElementById('barcodePreviewContainer');
    
    if (valor.length > 0) {
        if(contenedor) contenedor.style.display = 'block';
        try {
            JsBarcode("#barcodePreview", valor, {
                format: "CODE128", 
                lineColor: "#1d1d1f",
                width: 2.5,         
                height: 70,        
                displayValue: true, 
                fontSize: 18,      
                background: "transparent",
                font: "Poppins"
            });
        } catch (e) {
            console.warn("Código no válido para generar barras aún");
        }
    } else {
        if(contenedor) contenedor.style.display = 'none';
    }
}