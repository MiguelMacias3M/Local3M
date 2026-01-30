/* =====================================
LÓGICA PARA REPARACION.PHP (reparacion.js)
=====================================
*/

let carrito = [];

function validarCampos(esParaCarrito) {
    if (esParaCarrito) {
        const tipoReparacion = document.getElementById('tipo_reparacion').value.trim();
        const marcaCelular = document.getElementById('marca_celular').value.trim();
        const modelo = document.getElementById('modelo').value.trim();
        const monto = document.getElementById('monto').value.trim();
        const adelanto = document.getElementById('adelanto').value.trim();

        if (!tipoReparacion || !marcaCelular || !modelo || !monto || !adelanto) {
            Swal.fire('Campos de Equipo Vacíos', 'Por favor, complete todos los campos del equipo.', 'warning');
            return false;
        }
    } else {
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
    return true;
}

function agregarAlCarrito() {
    if (!validarCampos(true)) return;

    const tipoReparacion = document.getElementById('tipo_reparacion').value;
    const marcaCelular = document.getElementById('marca_celular').value;
    const modelo = document.getElementById('modelo').value;
    const monto = parseInt(document.getElementById('monto').value) || 0;
    const adelanto = parseInt(document.getElementById('adelanto').value) || 0;
    const deuda = Math.max(monto - adelanto, 0);
        // Dentro de tu función de agregar al carrito:
    const fechaEstimada = document.getElementById('fecha_estimada').value;

    // Al crear el objeto del producto/reparación:
    const item = {
        // ... tus otros campos (marca, modelo, etc.) ...
        fechaEstimada: fechaEstimada, // <--- NUEVO
        // ...
    };

// Y muy importante: Limpiar el campo después de agregar
document.getElementById('fecha_estimada').value = '';
    const reparacion = { tipoReparacion, marcaCelular, modelo, monto, adelanto, deuda };
    carrito.push(reparacion);
    
    mostrarCarrito();
    limpiarCamposEquipo();
}

function mostrarCarrito() {
    const listaCarrito = document.getElementById('lista-carrito');
    const btnRegistrar = document.getElementById('btn-registrar');
    const totalDeudaElement = document.getElementById('total-deuda');
    const contenedorTotal = document.getElementById('contenedor-total');

    listaCarrito.innerHTML = '';
    let totalMonto = 0;
    let totalAdelanto = 0;
    let totalDeuda = 0;

    if (carrito.length === 0) {
        listaCarrito.innerHTML = '<li>El carrito está vacío.</li>';
        btnRegistrar.disabled = true;
        totalDeudaElement.textContent = '$0';
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
        
        contenedorTotal.innerHTML = `<strong id="total-label">Deuda Total:</strong> <span id="total-deuda">$${totalDeuda}</span>`;
    }
}

function eliminarDelCarrito(index) {
    carrito.splice(index, 1);
    mostrarCarrito();
}

function enviarCarrito() {
    if (carrito.length === 0) {
        Swal.fire('Carrito Vacío', 'Debe agregar al menos una reparación.', 'warning');
        return;
    }
    if (!validarCampos(false)) return;

    const nombreCliente = document.getElementById('nombre_cliente').value;
    const telefono = document.getElementById('telefono').value;
    const infoExtra = document.getElementById('info_extra').value;

    const btn = document.getElementById('btn-registrar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';

    // Usamos la variable global ID_TRANSACCION definida en el PHP
    const payload = {
        usuario: USUARIO_SESION, 
        nombreCliente,
        telefono,
        infoExtra,
        carrito,
        id_transaccion: ID_TRANSACCION 
    };

    fetch('/local3M/api/registrar_reparaciones.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // IMPORTANTE: Aquí recibimos el ID correcto del servidor
            const nuevoIdTransaccion = data.id_transaccion;

            Swal.fire({
                title: 'Registro Exitoso',
                text: 'Las reparaciones han sido registradas correctamente.',
                icon: 'success',
                showCancelButton: true,
                confirmButtonText: 'Aceptar (Nueva Orden)',
                cancelButtonText: 'Imprimir Ticket',
                cancelButtonColor: '#ffc107'
            }).then((result) => {
                if (result.isConfirmed) {
                    vaciarCarritoYUI();
                    location.reload();
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    // Enviamos el ID nuevo a la función de imprimir
                    imprimirUltimaYLimpiar(nuevoIdTransaccion);
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
        Swal.fire('Error de Conexión', 'No se pudo conectar con el servidor.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Registrar Orden';
    });
}

// Función corregida para abrir el ticket
function imprimirUltimaYLimpiar(idTransaccion) {
    if (idTransaccion) {
        // Abrimos el ticket con el ID específico de la transacción
        // Fíjate que aquí NO usamos ?ts=, usamos ?id_transaccion=
        window.open('/local3M/generar_ticket.php?id_transaccion=' + encodeURIComponent(idTransaccion), '_blank');
    } else {
        // Respaldo por si acaso
        if (typeof ID_TRANSACCION !== 'undefined') {
             window.open('/local3M/generar_ticket.php?id_transaccion=' + encodeURIComponent(ID_TRANSACCION), '_blank');
        } else {
            Swal.fire('Error', 'No se pudo obtener el folio del ticket.', 'error');
        }
    }

    // Limpiamos y recargamos la página después de un momento
    setTimeout(() => {
        vaciarCarritoYUI();
        location.reload();
    }, 1000);
}

function vaciarCarritoYUI() {
    carrito = [];
    mostrarCarrito();
    limpiarTodosCampos();
    const btn = document.getElementById('btn-registrar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-check-circle"></i> Registrar Orden';
}

function calcularDeuda() {
    var monto = parseInt(document.getElementById('monto').value) || 0;
    var adelanto = parseInt(document.getElementById('adelanto').value) || 0;
    var deuda = monto - adelanto;
    deuda = deuda < 0 ? 0 : deuda;
    document.getElementById('deuda').value = deuda;
}

function limpiarTodosCampos() {
    document.getElementById('nombre_cliente').value = '';
    document.getElementById('telefono').value = '';
    document.getElementById('info_extra').value = '';
    limpiarCamposEquipo();
}

function limpiarCamposEquipo() {
    document.getElementById('tipo_reparacion').value = '';
    document.getElementById('marca_celular').value = '';
    document.getElementById('modelo').value = '';
    document.getElementById('monto').value = '0';
    document.getElementById('adelanto').value = '0';
    document.getElementById('deuda').value = '0';
}