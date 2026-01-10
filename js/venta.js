// Elementos del DOM
const scanInput = document.getElementById('scanInput');
const searchInput = document.getElementById('searchInput');
const productsGrid = document.getElementById('productsGrid');
const cartBody = document.getElementById('cartBody');
const cartTotalDisplay = document.getElementById('cartTotalDisplay');
const btnFinalizar = document.getElementById('btnFinalizar');

// Cargar estado inicial
document.addEventListener('DOMContentLoaded', () => {
    cargarProductos(); // Carga productos iniciales
    actualizarCarrito(); // Carga carrito de sesión
    if(scanInput) scanInput.focus();
});

// Evento Escáner (Al presionar Enter)
if(scanInput) {
    scanInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault(); // Evitar submit del form si lo hubiera
            buscarPorCodigo(scanInput.value.trim());
            scanInput.value = '';
        }
    });
}

// Evento Búsqueda Manual (Input con retraso "debounce")
let debounceTimer;
if(searchInput) {
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            cargarProductos(searchInput.value.trim());
        }, 300);
    });
}

// 1. Cargar Productos (API)
async function cargarProductos(query = '') {
    try {
        // Ruta absoluta
        const res = await fetch(`/api/procesar_venta.php?action=buscar&q=${encodeURIComponent(query)}`);
        
        if (!res.ok) throw new Error(`Error HTTP: ${res.status}`);
        
        const json = await res.json();
        
        if (json.success) {
            renderProductos(json.data);
        } else {
            console.error('Error API:', json.error);
        }
    } catch (e) { 
        console.error("Error al cargar productos:", e); 
        productsGrid.innerHTML = '<p style="text-align:center; color:#dc3545;">Error de conexión con el servidor.</p>';
    }
}

// 2. Renderizar Grid de Productos
function renderProductos(productos) {
    productsGrid.innerHTML = '';
    if (!productos || productos.length === 0) {
        productsGrid.innerHTML = '<p style="text-align:center; width:100%; color:#888; margin-top:20px;">No se encontraron productos.</p>';
        return;
    }

    productos.forEach(p => {
        const div = document.createElement('div');
        div.className = 'product-card';
        div.onclick = () => agregarAlCarrito(p.id_productos); // Click agrega 1
        
        // Alerta visual de stock bajo
        const stockClass = parseInt(p.cantidad_piezas) < 5 ? 'low' : '';
        const precio = parseFloat(p.precio_producto || 0).toFixed(2);
        
        div.innerHTML = `
            <div class="prod-name">${p.nombre_producto}</div>
            <div class="prod-code">${p.codigo_barras || '--'}</div>
            <div class="prod-price">$${precio}</div>
            <div class="prod-stock ${stockClass}">Stock: ${p.cantidad_piezas}</div>
        `;
        productsGrid.appendChild(div);
    });
}

// 3. Buscar por código exacto (para el escáner)
async function buscarPorCodigo(codigo) {
    if (!codigo) return;
    
    // Usamos la misma API de búsqueda
    const res = await fetch(`/api/procesar_venta.php?action=buscar&q=${encodeURIComponent(codigo)}`);
    const json = await res.json();
    
    if (json.success && json.data.length > 0) {
        // Buscamos coincidencia exacta de código de barras
        const prod = json.data.find(p => p.codigo_barras == codigo);
        if (prod) {
            agregarAlCarrito(prod.id_productos);
            // Feedback visual sutil
            const toast = Swal.mixin({toast: true, position: 'bottom-start', showConfirmButton: false, timer: 1000});
            toast.fire({icon: 'success', title: 'Escaneado: ' + prod.nombre_producto});
        } else {
            Swal.fire({toast:true, position:'top-end', icon:'warning', title:'Código no exacto, revisa la lista', timer:2000, showConfirmButton:false});
            renderProductos(json.data); // Mostramos lo que encontró
        }
    } else {
        Swal.fire({toast:true, position:'top-end', icon:'error', title:'Producto no encontrado', timer:1500, showConfirmButton:false});
    }
}

// Agregar al Carrito
async function agregarAlCarrito(id) {
    const formData = new FormData();
    formData.append('id', id);
    formData.append('cantidad', 1);

    const res = await fetch('/api/procesar_venta.php?action=agregar', {
        method: 'POST',
        body: formData
    });
    const json = await res.json();

    if (json.success) {
        actualizarCarrito();
    } else {
        Swal.fire({icon: 'error', title: 'Error', text: json.error, timer: 1500, showConfirmButton: false});
    }
}

// 4. Actualizar Carrito
async function actualizarCarrito() {
    const res = await fetch('/api/procesar_venta.php?action=get_carrito');
    const json = await res.json();
    
    if (json.success) {
        renderCarrito(json.carrito);
    }
}

function renderCarrito(items) {
    cartBody.innerHTML = '';
    let total = 0;

    if(items.length === 0) {
        cartBody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px; color:#aaa;">Carrito vacío</td></tr>';
        cartTotalDisplay.textContent = '$0.00';
        btnFinalizar.disabled = true;
        return;
    }

    items.forEach((item, index) => {
        const subtotal = item.precio * item.cantidad;
        total += subtotal;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <div style="font-weight:600; font-size:0.85rem;">${item.nombre}</div>
                <div style="font-size:0.75rem; color:#888;">$${parseFloat(item.precio).toFixed(2)}</div>
            </td>
            <td style="text-align:center;">${item.cantidad}</td>
            <td style="text-align:right;">$${subtotal.toFixed(2)}</td>
            <td style="text-align:right;">
                <button class="btn-remove" onclick="eliminarDelCarrito(${index})">&times;</button>
            </td>
        `;
        cartBody.appendChild(tr);
    });

    cartTotalDisplay.textContent = `$${total.toFixed(2)}`;
    btnFinalizar.disabled = false;
}

async function eliminarDelCarrito(index) {
    const formData = new FormData();
    formData.append('index', index);
    await fetch('/api/procesar_venta.php?action=eliminar', { method: 'POST', body: formData });
    actualizarCarrito();
}

async function limpiarCarrito() {
    await fetch('/api/procesar_venta.php?action=limpiar');
    actualizarCarrito();
}

// 5. Finalizar Venta
async function finalizarVenta() {
    const totalTexto = document.getElementById('cartTotalDisplay').textContent;
    
    const result = await Swal.fire({
        title: 'Total: ' + totalTexto,
        text: '¿Confirmar venta?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Cobrar e Imprimir',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#28a745'
    });

    if (!result.isConfirmed) return;

    btnFinalizar.disabled = true;
    btnFinalizar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

    try {
        const res = await fetch('/api/procesar_venta.php?action=finalizar', { method: 'POST' });
        const json = await res.json();

        if (json.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Venta Exitosa!',
                showConfirmButton: false,
                timer: 1500
            });
            
            // Abrir ticket de venta
            window.open('/generar_ticket_venta.php?id_transaccion=' + json.id_transaccion, '_blank');
            
            actualizarCarrito();
            cargarProductos(); // Recargar inventario visual
        } else {
            Swal.fire('Error', json.error || 'Error desconocido', 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Error de conexión', 'error');
    } finally {
        btnFinalizar.disabled = false;
        btnFinalizar.innerHTML = '<i class="fas fa-check-circle"></i> COBRAR';
    }
}