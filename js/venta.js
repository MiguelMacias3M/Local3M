// Elementos del DOM
const scanInput = document.getElementById('scanInput');
const searchInput = document.getElementById('searchInput');
const productsGrid = document.getElementById('productsGrid');

let productosActuales = [];

document.addEventListener('DOMContentLoaded', () => {
    cargarProductos(); 
    if(scanInput) scanInput.focus();
});

// Evento Escáner (Escucha al Robot o a la Pistola física)
if(scanInput) {
    scanInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault(); 
            buscarPorCodigo(scanInput.value.trim());
            scanInput.value = '';
        }
    });
}

// Evento Búsqueda Manual (Teclado)
let debounceTimer;
if(searchInput) {
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            cargarProductos(searchInput.value.trim());
        }, 300);
    });
}

// Cargar Productos desde la API
async function cargarProductos(query = '') {
    try {
        const res = await fetch(`/local3M/api/procesar_venta.php?action=buscar&q=${encodeURIComponent(query)}`);
        if (!res.ok) throw new Error(`Error HTTP: ${res.status}`);
        
        const json = await res.json();
        if (json.success) {
            productosActuales = json.data;
            renderProductos(json.data);
        }
    } catch (e) { 
        console.error("Error al cargar productos:", e); 
    }
}

// Dibujar las Tarjetas Liquid Glass
function renderProductos(productos) {
    productsGrid.innerHTML = '';
    if (!productos || productos.length === 0) {
        productsGrid.innerHTML = '<div style="grid-column: 1 / -1; text-align:center; padding:40px; color:#86868b; background: rgba(255,255,255,0.5); border-radius: 20px; border: 1px dashed rgba(0,0,0,0.1);">No se encontraron productos.</div>';
        return;
    }

    productos.forEach(p => {
        const div = document.createElement('div');
        div.className = 'glass-product-card';
        div.onclick = () => procesarProductoHaciaGlobal(p); 
        
        const stockVal = parseInt(p.cantidad_piezas) || 0;
        const isLowStock = stockVal < 5;
        // Colores inteligentes para el stock
        const stockColor = isLowStock ? '#ff3b30' : '#34c759';
        const stockBg = isLowStock ? 'rgba(255, 59, 48, 0.15)' : 'rgba(52, 199, 89, 0.15)';
        const iconStock = isLowStock ? 'fa-exclamation-triangle' : 'fa-box-open';
        
        const precio = parseFloat(p.precio_producto || 0).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        
        div.innerHTML = `
            <div class="prod-info">
                <div class="prod-code"><i class="fas fa-barcode"></i> ${p.codigo_barras || 'S/C'}</div>
                <div class="prod-name">${p.nombre_producto}</div>
            </div>
            <div class="prod-footer">
                <div class="prod-price">$${precio}</div>
                <div class="prod-stock" style="color: ${stockColor}; background: ${stockBg};">
                    <i class="fas ${iconStock}"></i> ${stockVal}
                </div>
            </div>
        `;
        productsGrid.appendChild(div);
    });
}

// Si llega un código exacto por pistola o robot
async function buscarPorCodigo(codigo) {
    if (!codigo) return;
    
    const res = await fetch(`/local3M/api/procesar_venta.php?action=buscar&q=${encodeURIComponent(codigo)}`);
    const json = await res.json();
    
    if (json.success && json.data.length > 0) {
        // Buscamos coincidencia exacta de código de barras
        const prod = json.data.find(p => p.codigo_barras == codigo);
        if (prod) {
            procesarProductoHaciaGlobal(prod);
            // Notificación discreta (Toast)
            const toast = Swal.mixin({toast: true, position: 'top-end', showConfirmButton: false, timer: 1200});
            toast.fire({icon: 'success', title: 'Añadido: ' + prod.nombre_producto});
            
            // Regresamos el cursor al input para seguir escaneando a máxima velocidad
            setTimeout(() => { scanInput.focus(); }, 100);
        } else {
            renderProductos(json.data); 
        }
    } else {
        Swal.fire({toast: true, position: 'top-end', icon: 'error', title: 'Producto no encontrado', showConfirmButton: false, timer: 1500});
    }
}

// Enviar al Carrito
function procesarProductoHaciaGlobal(productoBD) {
    const itemGlobal = {
        id: productoBD.id_productos,
        tipo: 'producto',
        nombre: productoBD.nombre_producto,
        precio: parseFloat(productoBD.precio_producto || 0),
        cantidad: 1
    };

    if (typeof agregarAlCarritoGlobal === 'function') {
        agregarAlCarritoGlobal(itemGlobal);
    } else {
        Swal.fire('Error', 'El carrito global no está conectado.', 'error');
    }
}