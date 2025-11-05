/* =====================================
LÓGICA PARA REPARACION.PHP (reparacion.js)
=====================================
*/

// Array global para guardar los items del carrito
let carrito = [];

/**
 * Valida que los campos principales no estén vacíos y que el teléfono sea correcto.
 */
function validarCampos(esParaCarrito) {

    // Si SÍ es para el carrito (true), solo validamos el EQUIPO
    if (esParaCarrito) {
        const tipoReparacion = document.getElementById('tipo_reparacion').value.trim();
        const marcaCelular = document.getElementById('marca_celular').value.trim();
        const modelo = document.getElementById('modelo').value.trim();
        const monto = document.getElementById('monto').value.trim();
        const adelanto = document.getElementById('adelanto').value.trim();

        if (!tipoReparacion || !marcaCelular || !modelo || !monto || !adelanto) {
            Swal.fire('Campos de Equipo Vacíos', 'Por favor, complete todos los campos del equipo (reparación, marca, modelo, monto y adelanto).', 'warning');
            return false;
        }
    }
    // Si NO es para el carrito (false, o sea, es para ENVIAR), solo validamos el CLIENTE
    else {
        const nombreCliente = document.getElementById('nombre_cliente').value.trim();
        const telefono = document.getElementById('telefono').value.trim();
        
        if (!nombreCliente || !telefono) {
            Swal.fire('Campos de Cliente Vacíos', 'Por favor, complete el nombre y teléfono del cliente.', 'warning');
            return false;
        }
        if (telefono.length !== 10) {
            Swal.fire('Teléfono Incorrecto', 'El número de teléfono debe tener exactamente 10 dígitos.', 'warning');
            return false;
        }
    }
    
    // Si pasó la validación que le correspondía
    return true;
}

/**
 * Agrega la reparación actual al array del carrito.
 */
function agregarAlCarrito() {
    // Validamos solo los campos del equipo (true)
    if (!validarCampos(true)) return;

    // Recogemos los datos del formulario
    // CAMBIO: Usamos parseInt() en lugar de parseFloat()
    const tipoReparacion = document.getElementById('tipo_reparacion').value;
    const marcaCelular = document.getElementById('marca_celular').value;
    const modelo = document.getElementById('modelo').value;
    const monto = parseInt(document.getElementById('monto').value) || 0;
    const adelanto = parseInt(document.getElementById('adelanto').value) || 0;
    const deuda = Math.max(monto - adelanto, 0);

    // Creamos el objeto de reparación y lo añadimos al carrito
    const reparacion = { tipoReparacion, marcaCelular, modelo, monto, adelanto, deuda };
    carrito.push(reparacion);
    
    // Actualizamos la vista del carrito y limpiamos los campos del equipo
    mostrarCarrito();
    limpiarCamposEquipo();
}

/**
 * Actualiza la lista del carrito en el HTML.
 */
function mostrarCarrito() {
    const listaCarrito = document.getElementById('lista-carrito');
    const btnRegistrar = document.getElementById('btn-registrar');
    const totalDeudaElement = document.getElementById('total-deuda');
    const totalLabelElement = document.getElementById('total-label');
    const contenedorTotal = document.getElementById('contenedor-total');

    listaCarrito.innerHTML = '';
    let totalMonto = 0;
    let totalAdelanto = 0;
    let totalDeuda = 0;

    // CAMBIO: Quitado .toFixed(2) de todos lados
    if (carrito.length === 0) {
        listaCarrito.innerHTML = '<li>El carrito está vacío.</li>';
        btnRegistrar.disabled = true;
        totalLabelElement.textContent = 'Deuda Total:';
        totalDeudaElement.textContent = '$0';
        contenedorTotal.innerHTML = '<strong id="total-label">Deuda Total:</strong> <span id="total-deuda">$0</span>';
    } else {
        carrito.forEach((reparacion, index) => {
            totalMonto += reparacion.monto;
            totalAdelanto += reparacion.adelanto;

            listaCarrito.innerHTML += `
                <li>
                    <button class="delete-btn" onclick="eliminarDelCarrito(${index})">&times;</button>
                    <strong>${reparacion.tipoReparacion}</strong>
                    <div class="carrito-item-detalle">
                        ${reparacion.marcaCelular} ${reparacion.modelo}<br>
                        Monto: $${reparacion.monto} | 
                        Adelanto: $${reparacion.adelanto} | 
                        Deuda: $${reparacion.deuda}
                    </div>
                </li>
            `;
        });

        totalDeuda = totalMonto - totalAdelanto;
        btnRegistrar.disabled = false;

        if (carrito.length === 1) {
            // Si hay un solo item, mostramos su deuda
            contenedorTotal.innerHTML = `<strong id="total-label">Deuda Total:</strong> <span id="total-deuda">$${totalDeuda}</span>`;
        } else {
            // Si hay múltiples items, mostramos el desglose
            contenedorTotal.innerHTML = `
                <div class="totales-multiples">
                    <div>Total Monto: <span>$${totalMonto}</span></div>
                    <div>Total Adelanto: <span>$${totalAdelanto}</span></div>
                    <div class="total-final">Deuda Total: <span>$${totalDeuda}</span></div>
                </div>
            `;
        }
    }
}

/**
 * Elimina un item del carrito por su índice.
 */
function eliminarDelCarrito(index) {
    carrito.splice(index, 1);
    mostrarCarrito();
}

/**
 * Envía el carrito completo al backend (api/registrar_reparaciones.php).
 */
function enviarCarrito() {
    // Validamos que el carrito no esté vacío
    if (carrito.length === 0) {
        Swal.fire('Carrito Vacío', 'Debe agregar al menos una reparación al carrito.', 'warning');
        return;
    }
    // Validamos solo los campos del cliente (false)
    if (!validarCampos(false)) return;

    // Recogemos datos del cliente
    const nombreCliente = document.getElementById('nombre_cliente').value;
    const telefono = document.getElementById('telefono').value;
    const infoExtra = document.getElementById('info_extra').value;

    const btn = document.getElementById('btn-registrar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';

    // Usamos las variables globales definidas en reparacion.php
    const payload = {
        usuario: USUARIO_SESION, 
        nombreCliente,
        telefono,
        infoExtra,
        carrito,
        id_transaccion: ID_TRANSACCION 
    };

    // Usamos la ruta absoluta
    fetch('/local3M/api/registrar_reparaciones.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    })
    .then(response => response.json())
    .then(data => {
        console.log(data);
        if (data.success) {
            Swal.fire({
                title: 'Registro Exitoso',
                text: 'Las reparaciones han sido registradas correctamente.',
                icon: 'success',
                showCancelButton: true,
                confirmButtonText: 'Aceptar (Nueva Orden)',
                cancelButtonText: 'Imprimir Ticket'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Aceptar: Limpia todo para una nueva orden
                    vaciarCarritoYUI();
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    // Imprimir la ÚLTIMA reparación
                    imprimirUltimaYLimpiar();
                }
            });
        } else {
            Swal.fire('Error', 'Hubo un problema al registrar: ' + (data.error || 'Error desconocido'), 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Registrar Orden';
        }
    })
    .catch(error => {
        console.error('Error en fetch:', error);
        Swal.fire('Error de Conexión', 'No se pudo conectar con el servidor. Revisa la consola.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Registrar Orden';
    });
}

/**
 * Abre el ticket y limpia la UI.
 */
function imprimirUltimaYLimpiar() {
    // 1) Abrir ticket
    // Usamos ruta absoluta
    window.open('/local3M/generar_ticket.php?ts=' + Date.now(), '_blank');

    // 2) Limpiar UI
    vaciarCarritoYUI();
}

/**
 * Resetea el carrito y el formulario completo.
 */
function vaciarCarritoYUI() {
    carrito = [];
    mostrarCarrito();
    limpiarTodosCampos();
    
    // Resetea el botón
    const btn = document.getElementById('btn-registrar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-check-circle"></i> Registrar Orden';
}

/**
 * Calcula la deuda en tiempo real.
 */
function calcularDeuda() {
    // CAMBIO: Usamos parseInt()
    var monto = parseInt(document.getElementById('monto').value) || 0;
    var adelanto = parseInt(document.getElementById('adelanto').value) || 0;
    var deuda = monto - adelanto;
    deuda = deuda < 0 ? 0 : deuda; // La deuda no puede ser negativa
    // CAMBIO: Quitado .toFixed(2)
    document.getElementById('deuda').value = deuda;
}

/**
 * Limpia TODOS los campos del formulario (cliente y equipo).
 */
function limpiarTodosCampos() {
    document.getElementById('nombre_cliente').value = '';
    document.getElementById('telefono').value = '';
    document.getElementById('info_extra').value = '';
    limpiarCamposEquipo();
}

/**
 * Limpia solo los campos del equipo (usado después de agregar al carrito).
 */
function limpiarCamposEquipo() {
    document.getElementById('tipo_reparacion').value = '';
    document.getElementById('marca_celular').value = '';
    document.getElementById('modelo').value = '';
    document.getElementById('monto').value = '0';
    document.getElementById('adelanto').value = '0';
    document.getElementById('deuda').value = '0';
}