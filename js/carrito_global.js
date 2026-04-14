// js/carrito_global.js
let carritoGlobal = JSON.parse(localStorage.getItem('carritoGlobal')) || [];
let totalCarrito = 0.00;

document.addEventListener("DOMContentLoaded", () => {
    renderizarCarrito();
});

function guardarCarrito() {
    localStorage.setItem('carritoGlobal', JSON.stringify(carritoGlobal));
    renderizarCarrito();
}

function agregarAlCarritoGlobal(item) {
    if (item.tipo === 'producto') {
        let existe = carritoGlobal.find(p => p.id === item.id && p.tipo === 'producto');
        if (existe) {
            existe.cantidad += item.cantidad;
        } else {
            carritoGlobal.push(item);
        }
    } else if (item.tipo === 'reparacion') {
        let existe = carritoGlobal.find(r => r.id === item.id && r.tipo === 'reparacion');
        if (existe) {
            Swal.fire('Aviso', 'Esta reparación ya está en el carrito.', 'info');
            return; 
        } else {
            carritoGlobal.push(item);
        }
    }
    
    guardarCarrito();
    
    const panel = document.getElementById('panel-carrito-global');
    if (!panel.classList.contains('abierto')) {
        toggleCarrito();
    }
}

function eliminarItemCarrito(index) {
    carritoGlobal.splice(index, 1);
    guardarCarrito();
    calcularCambio();
}

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
        let subtotal = 0;
        let detalleTexto = "";

        if (item.tipo === 'producto') {
            subtotal = item.precio * item.cantidad;
            detalleTexto = `${item.cantidad} x $${item.precio.toFixed(2)}`;
        } else if (item.tipo === 'reparacion') {
            subtotal = item.a_cobrar; 
            detalleTexto = `Folio: #${item.id} | Costo total: $${item.costo_total}`;
        }

        totalCarrito += subtotal;
        cantidadTotal += (item.cantidad || 1);

        let icono = item.tipo === 'producto' ? '<i class="fas fa-box" style="color:#007aff;"></i>' : '<i class="fas fa-tools" style="color:#ff9500;"></i>';

        lista.innerHTML += `
            <li style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.05);">
                <div style="flex-grow: 1; padding-right: 10px;">
                    <div style="font-weight: 600; color: #1d1d1f; font-size: 0.95rem;">${icono} ${item.nombre}</div>
                    <div style="color: #86868b; font-size: 0.85rem;">${detalleTexto}</div>
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
    cambiarMetodoPago(); // Revisa si aplica auto-llenado
}

function toggleCarrito() {
    const panel = document.getElementById('panel-carrito-global');
    const overlay = document.getElementById('overlay-carrito');
    
    panel.classList.toggle('abierto');
    overlay.classList.toggle('activo');
    
    if (panel.classList.contains('abierto')) {
        setTimeout(() => document.getElementById('paga-con').focus(), 300);
    }
}

// NUEVA FUNCIÓN: Maneja la lógica de Efectivo vs Tarjeta/Transferencia
function cambiarMetodoPago() {
    const metodoSelect = document.getElementById('metodo-pago');
    const metodo = metodoSelect ? metodoSelect.value : 'Efectivo';
    const inputPagaCon = document.getElementById('paga-con');
    
    if (metodo !== 'Efectivo' && totalCarrito > 0) {
        inputPagaCon.value = totalCarrito.toFixed(2);
        inputPagaCon.disabled = true; // No hay cambio en transferencias
    } else {
        if(inputPagaCon.disabled) inputPagaCon.value = ''; // Limpiamos si antes estaba deshabilitado
        inputPagaCon.disabled = false;
    }
    calcularCambio();
}

function calcularCambio() {
    const pagaConInput = document.getElementById('paga-con').value;
    const pagaCon = parseFloat(pagaConInput) || 0;
    
    const cambio = pagaCon - totalCarrito;
    const spanCambio = document.getElementById('cambio-carrito');

    if (cambio < 0) {
        spanCambio.innerText = "0.00 (Faltan $" + Math.abs(cambio).toFixed(2) + ")";
        spanCambio.style.color = "#ff3b30";
    } else {
        spanCambio.innerText = cambio.toFixed(2);
        spanCambio.style.color = "#34c759";
    }
}

async function procesarCobroGlobal() {
    const pagaConInput = document.getElementById('paga-con').value;
    const pagaCon = parseFloat(pagaConInput) || 0;
    
    // Obtenemos el método de pago seleccionado
    const metodoSelect = document.getElementById('metodo-pago');
    const metodoPago = metodoSelect ? metodoSelect.value : 'Efectivo';
    
    if (carritoGlobal.length === 0) {
        Swal.fire('Atención', 'El carrito está vacío.', 'warning');
        return;
    }
    
    if (pagaCon < (totalCarrito - 0.01)) { // Margen de error por decimales
        Swal.fire('Atención', 'El monto pagado es menor al total a cobrar.', 'warning');
        return;
    }

    const btnCobrar = document.querySelector('.btn-procesar-cobro');
    const textoOriginal = btnCobrar.innerHTML;
    btnCobrar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    btnCobrar.disabled = true;

    try {
        const response = await fetch('/local3M/api/procesar_venta.php?action=finalizar_global', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                carrito: carritoGlobal,
                paga_con: pagaCon,
                metodo_pago: metodoPago // Enviamos el método a PHP
            })
        });

        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Venta Exitosa!',
                text: `Cobrado con ${metodoPago}. Cambio a entregar: $${(pagaCon - totalCarrito).toFixed(2)}`,
                showConfirmButton: true,
                confirmButtonText: 'Abrir Tickets y Cerrar',
                confirmButtonColor: '#007aff'
            }).then(() => {
                
                window.open(data.ticketUrl, '_blank');

                if (data.ticketsReparacion && data.ticketsReparacion.length > 0) {
                    data.ticketsReparacion.forEach(url => {
                        window.open(url, '_blank');
                    });
                }
                
                carritoGlobal = [];
                guardarCarrito();
                document.getElementById('paga-con').value = "";
                if(metodoSelect) metodoSelect.value = "Efectivo"; // Resetear a efectivo
                toggleCarrito(); 

                if (typeof cargarProductos === 'function') {
                    cargarProductos();
                } else {
                    setTimeout(() => { window.location.reload(); }, 500); 
                }
            });
        } else {
            Swal.fire('Error', data.error || 'No se pudo completar la venta', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'Hubo un problema de conexión con el servidor.', 'error');
    } finally {
        btnCobrar.innerHTML = textoOriginal;
        btnCobrar.disabled = false;
    }
}