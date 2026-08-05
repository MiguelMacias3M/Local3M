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
    // Inicializamos el descuento en 0 al agregar un producto
    item.descuento_unitario = 0;

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
    else if (item.tipo === 'equipo') {
        let existe = carritoGlobal.find(e => String(e.id) === String(item.id) && e.tipo === 'equipo');
        if (existe) {
            Swal.fire('Aviso', 'Este equipo ya está en el carrito.', 'info');
            return; 
        } else {
            carritoGlobal.push(item);
        }
    }
    else if (item.tipo === 'abono_apartado') {
        let existe = carritoGlobal.find(a => String(a.id) === String(item.id) && a.tipo === 'abono_apartado');
        if (existe) {
            Swal.fire('Aviso', 'El abono de este cliente ya está en el carrito.', 'info');
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
}

// ==========================================
// NUEVO: SISTEMA DE DESCUENTO INDIVIDUAL (CON ESTILO PREMIUM)
// ==========================================
window.aplicarDescuentoItem = function(index) {
    const item = carritoGlobal[index];
    
    // Los abonos no se descuentan
    if(item.tipo === 'abono_apartado') {
        Swal.fire('Aviso', 'No se pueden aplicar descuentos a los abonos de clientes.', 'info');
        return;
    }

    let precioBase = parseFloat(item.tipo === 'reparacion' ? item.a_cobrar : item.precio);

    Swal.fire({
        title: '<span style="font-weight: 800; color: #1d1d1f; font-family: \'Poppins\', sans-serif;"><i class="fas fa-tags" style="color:#ff9500; margin-right: 8px;"></i> Etiqueta Especial</span>',
        width: '450px', // Hacemos la ventana un poco más compacta y elegante
        html: `
            <style>
                /* Estilos inyectados solo para esta ventana */
                .desc-box {
                    background: rgba(255, 149, 0, 0.08);
                    border: 1px dashed rgba(255, 149, 0, 0.4);
                    border-radius: 16px;
                    padding: 15px;
                    margin-bottom: 20px;
                    text-align: center;
                }
                .desc-input {
                    width: 100%;
                    padding: 12px;
                    background: #f5f5f7;
                    border: 1px solid rgba(0, 0, 0, 0.08);
                    border-radius: 12px;
                    font-size: 14px;
                    color: #1d1d1f;
                    outline: none;
                    transition: all 0.3s ease;
                    font-family: 'Poppins', sans-serif;
                    box-sizing: border-box;
                    -webkit-appearance: none;
                    appearance: none;
                }
                .desc-input:focus {
                    background: #ffffff;
                    border-color: #ff9500;
                    box-shadow: 0 0 0 4px rgba(255, 149, 0, 0.15);
                }
                .desc-label {
                    font-size: 11px; 
                    font-weight: 700; 
                    color: #86868b; 
                    text-transform: uppercase; 
                    letter-spacing: 0.5px; 
                    margin-bottom: 8px; 
                    display: block;
                    text-align: left;
                }
            </style>
            
            <div style="text-align: left; margin-top: 10px; font-family: 'Poppins', sans-serif;">
                
                <div class="desc-box">
                    <p style="font-size: 16px; color: #1d1d1f; margin: 0 0 5px 0; font-weight: 800;">${item.nombre}</p>
                    <p style="font-size: 14px; color: #86868b; margin: 0;">Precio de lista: <span style="text-decoration: line-through; color: #ff3b30; font-weight: 600;">$${precioBase.toFixed(2)}</span></p>
                </div>
                
                <div style="display: flex; gap: 15px;">
                    <div style="flex: 1;">
                        <label class="desc-label"><i class="fas fa-sliders-h"></i> Modalidad</label>
                        <select id="swal-tipo-desc" class="desc-input" style="cursor: pointer; font-weight: 600;">
                            <option value="MONTO">$ Dinero Directo</option>
                            <option value="PORCENTAJE">% Porcentaje</option>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label class="desc-label"><i class="fas fa-hand-holding-usd"></i> A descontar</label>
                        <input type="number" id="swal-valor-desc" class="desc-input" style="text-align: center; font-weight: 800; color: #ff9500; font-size: 16px;" placeholder="0" min="0">
                    </div>
                </div>
            </div>
        `,
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: '<i class="fas fa-check"></i> Aplicar',
        confirmButtonColor: '#ff9500',
        cancelButtonText: 'Cancelar',
        cancelButtonColor: '#8e8e93',
        denyButtonText: '<i class="fas fa-ban"></i> Quitar',
        denyButtonColor: '#ff3b30',
        preConfirm: () => {
            const tipo = document.getElementById('swal-tipo-desc').value;
            const valor = parseFloat(document.getElementById('swal-valor-desc').value) || 0;
            if(valor < 0) return Swal.showValidationMessage('No usar números negativos');
            return { tipo, valor };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            let descCalculado = result.value.tipo === 'PORCENTAJE' 
                ? (precioBase * (result.value.valor / 100)) 
                : result.value.valor;
            
            if (descCalculado > precioBase) {
                Swal.fire('Error', 'El descuento no puede ser mayor al precio del producto.', 'error');
                return;
            }

            carritoGlobal[index].descuento_unitario = descCalculado;
            guardarCarrito();
            Swal.fire({toast: true, position: 'top-end', icon: 'success', title: 'Descuento aplicado', timer: 1500, showConfirmButton: false});
        } else if (result.isDenied) {
            // Elimina el descuento si le dan al botón rojo
            carritoGlobal[index].descuento_unitario = 0;
            guardarCarrito();
        }
    });
};
function renderizarCarrito() {
    const lista = document.getElementById('lista-items-carrito');
    const badge = document.getElementById('badge-carrito');
    const spanTotal = document.getElementById('total-carrito');
    
    lista.innerHTML = '';
    totalCarrito = 0;
    let cantidadTotal = 0;
    let ahorroTotalCliente = 0; // Acumulador de marketing

    if (carritoGlobal.length === 0) {
        lista.innerHTML = '<li class="item-vacio">El carrito está vacío</li>';
        badge.innerText = "0";
        spanTotal.innerText = "0.00";
        cambiarMetodoPago();
        return;
    }

    carritoGlobal.forEach((item, index) => {
        let precioBase = parseFloat(item.tipo === 'reparacion' ? item.a_cobrar : item.precio);
        let descuentoUnit = parseFloat(item.descuento_unitario) || 0;
        let precioFinalUnit = precioBase - descuentoUnit;
        
        let cantidad = parseInt(item.cantidad) || 1;
        let subtotalFila = precioFinalUnit * cantidad;
        
        let detalleTexto = "";
        let icono = "";
        let htmlAhorro = "";

        // Si hay descuento, tachamos el original y mostramos la etiqueta verde
        if (descuentoUnit > 0) {
            ahorroTotalCliente += (descuentoUnit * cantidad);
            detalleTexto = `${cantidad} x <span style="text-decoration: line-through; color: #ff3b30; margin-right: 5px;">$${precioBase.toFixed(2)}</span> $${precioFinalUnit.toFixed(2)}`;
            htmlAhorro = `<div style="font-size: 11px; color: #34c759; font-weight: 700; margin-top: 3px;"><i class="fas fa-arrow-down"></i> Ahorro: $${(descuentoUnit * cantidad).toFixed(2)}</div>`;
        } else {
            detalleTexto = `${cantidad} x $${precioBase.toFixed(2)}`;
        }

        if (item.tipo === 'producto' || item.tipo === 'equipo') {
            if (item.tipo === 'producto') {
                icono = '<i class="fas fa-box" style="color:#007aff;"></i>';
            } else {
                icono = '<i class="fas fa-mobile-alt" style="color:#34c759;"></i>';
                detalleTexto = `Vitrina: ` + detalleTexto; 
            }
        } 
        else if (item.tipo === 'reparacion') {
            detalleTexto = `Folio: #${item.id} | ` + detalleTexto;
            icono = '<i class="fas fa-tools" style="color:#ff9500;"></i>';
        }
        else if (item.tipo === 'abono_apartado') {
            subtotalFila = parseFloat(item.precio); 
            detalleTexto = `Cliente: ${item.cliente_nombre} (Folio #${item.id})`;
            icono = '<i class="fas fa-hand-holding-usd" style="color:#9c27b0;"></i>'; 
        }

        totalCarrito += subtotalFila;
        cantidadTotal += cantidad;

        lista.innerHTML += `
            <li style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.05);">
                <div style="flex-grow: 1; padding-right: 10px;">
                    <div style="font-weight: 600; color: #1d1d1f; font-size: 0.95rem;">${icono} ${item.nombre}</div>
                    <div style="color: #86868b; font-size: 0.85rem;">${detalleTexto}</div>
                    ${htmlAhorro}
                </div>
                <div style="font-weight: 600; color: #1d1d1f; padding-right: 10px;">$${subtotalFila.toFixed(2)}</div>
                
                <div style="display: flex; gap: 8px;">
                    <button onclick="aplicarDescuentoItem(${index})" style="background:rgba(255,149,0,0.1); border:none; color:#ff9500; cursor:pointer; font-size: 1rem; width: 32px; height: 32px; border-radius: 8px; transition: 0.2s;" title="Negociar Precio">
                        <i class="fas fa-tag"></i>
                    </button>
                    <button onclick="eliminarItemCarrito(${index})" style="background:rgba(255,59,48,0.1); border:none; color:#ff3b30; cursor:pointer; font-size: 1rem; width: 32px; height: 32px; border-radius: 8px; transition: 0.2s;" title="Quitar">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </li>
        `;
    });

    // MAGIA NEUROMARKETING: Si hubo ahorros, mostramos una banda verde de celebración en el carrito
    if (ahorroTotalCliente > 0) {
        lista.innerHTML += `
            <li style="padding: 10px; background: rgba(52,199,89,0.1); border-radius: 8px; text-align: center; color: #28a745; font-weight: 800; font-size: 0.9rem; margin-top: 15px; border: 1px dashed rgba(52,199,89,0.3);">
                <i class="fas fa-award"></i> ¡Ahorro Total de esta Venta: $${ahorroTotalCliente.toFixed(2)}!
            </li>
        `;
    }

    badge.innerText = cantidadTotal;
    spanTotal.innerText = totalCarrito.toFixed(2);
    cambiarMetodoPago();
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

function cambiarMetodoPago() {
    const metodoSelect = document.getElementById('metodo-pago');
    const metodo = metodoSelect ? metodoSelect.value : 'Efectivo';
    const inputPagaCon = document.getElementById('paga-con');
    
    if (!inputPagaCon) return;

    if (metodo !== 'Efectivo' && totalCarrito > 0) {
        inputPagaCon.value = totalCarrito.toFixed(2);
        inputPagaCon.disabled = true; 
    } else {
        if(inputPagaCon.disabled) inputPagaCon.value = ''; 
        inputPagaCon.disabled = false;
    }
    calcularCambio();
}

function calcularCambio() {
    const pagaConInput = document.getElementById('paga-con');
    if (!pagaConInput) return;
    
    const pagaCon = parseFloat(pagaConInput.value) || 0;
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
    const metodoSelect = document.getElementById('metodo-pago');
    const metodoPago = metodoSelect ? metodoSelect.value : 'Efectivo';
    
    if (carritoGlobal.length === 0) {
        Swal.fire('Atención', 'El carrito está vacío.', 'warning');
        return;
    }
    
    if (pagaCon < (totalCarrito - 0.01)) { 
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
                metodo_pago: metodoPago
                // La variable carritoGlobal ya lleva incrustado el .descuento_unitario de cada producto
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
                if(metodoSelect) metodoSelect.value = "Efectivo"; 
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