// js/carrito_global.js

// 1. Inicializar el carrito desde la memoria del navegador o crear uno vacío
let carritoGlobal = JSON.parse(localStorage.getItem('carritoGlobal')) || [];
let totalCarrito = 0.00;

// Renderizar el carrito al cargar la página en cualquier sección
document.addEventListener("DOMContentLoaded", () => {
    renderizarCarrito();
});

// 2. Función para guardar y actualizar la vista
function guardarCarrito() {
    localStorage.setItem('carritoGlobal', JSON.stringify(carritoGlobal));
    renderizarCarrito();
}

// 3. Función principal para agregar cualquier cosa al carrito
// Ejemplo de item: { id: 1, tipo: 'producto', nombre: 'Cable USB', precio: 150.00, cantidad: 1 }
// Ejemplo de reparacion: { id: 24, tipo: 'reparacion', nombre: 'Pantalla Moto G20', precio: 800.00, cantidad: 1 }
function agregarAlCarritoGlobal(item) {
    // Si es producto, verificamos si ya existe para sumar la cantidad
    if (item.tipo === 'producto') {
        let existe = carritoGlobal.find(p => p.id === item.id && p.tipo === 'producto');
        if (existe) {
            existe.cantidad += item.cantidad;
        } else {
            carritoGlobal.push(item);
        }
    } else {
        // Si es reparación, solo la agregamos (normalmente son únicas por folio)
        carritoGlobal.push(item);
    }
    
    guardarCarrito();
    
    // Abrimos el panel para que el usuario vea que se agregó
    const panel = document.getElementById('panel-carrito-global');
    if (!panel.classList.contains('abierto')) {
        toggleCarrito();
    }
}

// 4. Función para eliminar un item del carrito
function eliminarItemCarrito(index) {
    carritoGlobal.splice(index, 1);
    guardarCarrito();
    calcularCambio(); // Recalcular cambio por si hay dinero en "paga con"
}

// 5. Función que dibuja los items en la ventana flotante
function renderizarCarrito() {
    const lista = document.getElementById('lista-items-carrito');
    const badge = document.getElementById('badge-carrito');
    const spanTotal = document.getElementById('total-carrito');
    
    lista.innerHTML = '';
    totalCarrito = 0;
    let cantidadTotal = 0;

    if (carritoGlobal.length === 0) {
        lista.innerHTML = '<li class="item-vacio">El carrito está vacío</li>';
        badge.innerText = "0";
        spanTotal.innerText = "0.00";
        return;
    }

    carritoGlobal.forEach((item, index) => {
        let subtotal = item.precio * item.cantidad;
        totalCarrito += subtotal;
        cantidadTotal += item.cantidad;

        // Identificador visual (P = Producto, R = Reparación)
        let icono = item.tipo === 'producto' ? '<i class="fas fa-box" style="color:#007aff;"></i>' : '<i class="fas fa-tools" style="color:#ff9500;"></i>';

        lista.innerHTML += `
            <li style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.05);">
                <div style="flex-grow: 1; padding-right: 10px;">
                    <div style="font-weight: 600; color: #1d1d1f; font-size: 0.95rem;">${icono} ${item.nombre}</div>
                    <div style="color: #86868b; font-size: 0.85rem;">${item.cantidad} x $${item.precio.toFixed(2)}</div>
                </div>
                <div style="font-weight: 600; color: #1d1d1f;">$${subtotal.toFixed(2)}</div>
                <button onclick="eliminarItemCarrito(${index})" style="background:none; border:none; color:#ff3b30; cursor:pointer; margin-left: 15px; font-size: 1.1rem;">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </li>
        `;
    });

    badge.innerText = cantidadTotal;
    spanTotal.innerText = totalCarrito.toFixed(2);
    calcularCambio(); // Mantiene actualizado el cálculo de pago
}

// 6. Funciones de Interfaz que ya teníamos
function toggleCarrito() {
    const panel = document.getElementById('panel-carrito-global');
    const overlay = document.getElementById('overlay-carrito');
    
    panel.classList.toggle('abierto');
    overlay.classList.toggle('activo');
    
    if (panel.classList.contains('abierto')) {
        setTimeout(() => document.getElementById('paga-con').focus(), 300);
    }
}

function calcularCambio() {
    const pagaConInput = document.getElementById('paga-con').value;
    const pagaCon = parseFloat(pagaConInput) || 0;
    
    const cambio = pagaCon - totalCarrito;
    const spanCambio = document.getElementById('cambio-carrito');

    if (cambio < 0) {
        spanCambio.innerText = "0.00 (Faltan $" + Math.abs(cambio).toFixed(2) + ")";
        spanCambio.style.color = "#ff3b30"; // Rojo
    } else {
        spanCambio.innerText = cambio.toFixed(2);
        spanCambio.style.color = "#34c759"; // Verde
    }
}

// js/carrito_global.js (Reemplaza la función procesarCobroGlobal)

async function procesarCobroGlobal() {
    const pagaConInput = document.getElementById('paga-con').value;
    const pagaCon = parseFloat(pagaConInput) || 0;
    
    if (carritoGlobal.length === 0) {
        Swal.fire('Atención', 'El carrito está vacío.', 'warning');
        return;
    }
    
    if (pagaCon < totalCarrito) {
        Swal.fire('Atención', 'El monto pagado es menor al total a cobrar.', 'warning');
        return;
    }

    // Cambiamos el botón para que muestre que está cargando
    const btnCobrar = document.querySelector('.btn-procesar-cobro');
    const textoOriginal = btnCobrar.innerHTML;
    btnCobrar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    btnCobrar.disabled = true;

    try {
        // Enviamos el carrito completo al backend
        const response = await fetch('/local3M/api/procesar_venta.php?action=finalizar_global', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                carrito: carritoGlobal,
                paga_con: pagaCon
            })
        });

        const data = await response.json();

        if (data.success) {
            // Alerta de éxito con el cálculo del cambio
            Swal.fire({
                icon: 'success',
                title: '¡Venta Exitosa!',
                text: `Cambio a entregar: $${(pagaCon - totalCarrito).toFixed(2)}`,
                showConfirmButton: true,
                confirmButtonText: 'Abrir Ticket y Cerrar',
                confirmButtonColor: '#007aff'
            }).then(() => {
                // 1. Abrimos el ticket en una pestaña nueva
                window.open(data.ticketUrl, '_blank');
                
                // 2. Limpiamos el carrito flotante
                carritoGlobal = [];
                guardarCarrito();
                document.getElementById('paga-con').value = "";
                toggleCarrito(); // Cerramos el panel

                // 3. Si estamos en la página de ventas, recargamos el catálogo para ver el stock actualizado
                if (typeof cargarProductos === 'function') {
                    cargarProductos();
                }
            });
        } else {
            Swal.fire('Error', data.error || 'No se pudo completar la venta', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'Hubo un problema de conexión con el servidor.', 'error');
    } finally {
        // Restauramos el botón
        btnCobrar.innerHTML = textoOriginal;
        btnCobrar.disabled = false;
    }
}