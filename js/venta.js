// Elementos del DOM
const scanInput = document.getElementById('scanInput');
const searchInput = document.getElementById('searchInput');
const productsGrid = document.getElementById('productsGrid');

// Variable para almacenar temporalmente los productos
let productosActuales = [];

// Cargar estado inicial
document.addEventListener('DOMContentLoaded', () => {
    cargarProductos(); 
    if(scanInput) scanInput.focus();
});

// Evento Escáner
if(scanInput) {
    scanInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault(); 
            buscarPorCodigo(scanInput.value.trim());
            scanInput.value = '';
        }
    });
}

// Evento Búsqueda Manual
let debounceTimer;
if(searchInput) {
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            cargarProductos(searchInput.value.trim());
        }, 300);
    });
}

// Cargar Productos (API)
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

// Renderizar Grid de Productos
function renderProductos(productos) {
    productsGrid.innerHTML = '';
    if (!productos || productos.length === 0) {
        productsGrid.innerHTML = '<p style="text-align:center; width:100%; color:#888; margin-top:20px;">No se encontraron productos.</p>';
        return;
    }

    productos.forEach(p => {
        const div = document.createElement('div');
        div.className = 'product-card';
        div.onclick = () => procesarProductoHaciaGlobal(p); 
        
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

// Buscar por código exacto
async function buscarPorCodigo(codigo) {
    if (!codigo) return;
    
    const res = await fetch(`/local3M/api/procesar_venta.php?action=buscar&q=${encodeURIComponent(codigo)}`);
    const json = await res.json();
    
    if (json.success && json.data.length > 0) {
        const prod = json.data.find(p => p.codigo_barras == codigo);
        if (prod) {
            procesarProductoHaciaGlobal(prod);
            const toast = Swal.mixin({toast: true, position: 'bottom-start', showConfirmButton: false, timer: 1000});
            toast.fire({icon: 'success', title: 'Agregado: ' + prod.nombre_producto});
        } else {
            renderProductos(json.data); 
        }
    }
}

// ==========================================
// EL PUENTE AL CARRITO GLOBAL
// ==========================================
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