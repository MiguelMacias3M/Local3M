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
            Swal.fire({icon: 'warning', title: 'Faltan Datos', text: 'Por favor, complete todos los campos obligatorios del equipo.'});
            return false;
        }
    } else {
        const nombreCliente = document.getElementById('nombre_cliente').value.trim();
        const telefono = document.getElementById('telefono').value.trim();
        
        if (!nombreCliente || !telefono) {
            Swal.fire({icon: 'warning', title: 'Faltan Datos', text: 'Por favor, complete el nombre y teléfono del cliente.'});
            return false;
        }
        if (telefono.length !== 10) {
            Swal.fire({icon: 'warning', title: 'Teléfono Inválido', text: 'El número de teléfono debe tener exactamente 10 dígitos numéricos.'});
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
    const fechaEstimada = document.getElementById('fecha_estimada').value;

    const reparacion = { 
        tipoReparacion, 
        marcaCelular, 
        modelo, 
        monto, 
        adelanto, 
        deuda,
        fechaEstimada: fechaEstimada
    };

    carrito.push(reparacion);
    
    mostrarCarrito();
    limpiarCamposEquipo();
}

function mostrarCarrito() {
    const listaCarrito = document.getElementById('lista-carrito');
    const btnRegistrar = document.getElementById('btn-registrar');
    const totalDeudaElement = document.getElementById('total-deuda');

    listaCarrito.innerHTML = '';
    let totalMonto = 0;
    let totalAdelanto = 0;
    let totalDeuda = 0;

    if (carrito.length === 0) {
        listaCarrito.innerHTML = '<li class="empty-cart"><i class="fas fa-box-open" style="font-size: 30px; display: block; margin-bottom: 10px; color: #c7c7cc;"></i>Aún no hay equipos agregados.</li>';
        btnRegistrar.disabled = true;
        totalDeudaElement.textContent = '$0.00';
    } else {
        carrito.forEach((rep, index) => {
            totalMonto += rep.monto;
            totalAdelanto += rep.adelanto;
            
            // Tarjeta Liquid Glass para cada item del carrito
            listaCarrito.innerHTML += `
                <li class="glass-cart-item">
                    <div style="flex: 1;">
                        <h4 style="margin: 0 0 4px 0; color: #1d1d1f; font-size: 15px;"><i class="fas fa-wrench" style="color:#007aff; margin-right:5px;"></i>${rep.tipoReparacion}</h4>
                        <div style="font-size: 13px; color: #86868b; margin-bottom: 8px;">
                            ${rep.marcaCelular} ${rep.modelo}
                        </div>
                        <div style="display: flex; gap: 8px; font-size: 11px; flex-wrap: wrap;">
                            <span class="badge-blue">Costo: $${rep.monto}</span>
                            <span class="badge-green">Abono: $${rep.adelanto}</span>
                            <span class="${rep.deuda > 0 ? 'badge-red' : 'badge-green'}">Resta: $${rep.deuda}</span>
                        </div>
                    </div>
                    <div style="margin-left: 10px;">
                        <button class="btn-icon" style="background: rgba(255,59,48,0.1); color: #ff3b30;" onclick="eliminarDelCarrito(${index})" title="Quitar equipo">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </li>
            `;
        });

        totalDeuda = totalMonto - totalAdelanto;
        btnRegistrar.disabled = false;
        totalDeudaElement.textContent = '$' + totalDeuda.toFixed(2);
    }
}

function eliminarDelCarrito(index) {
    carrito.splice(index, 1);
    mostrarCarrito();
}

function enviarCarrito() {
    if (carrito.length === 0) {
        Swal.fire('Carrito Vacío', 'Debe agregar al menos una reparación a la orden.', 'warning');
        return;
    }
    if (!validarCampos(false)) return;

    const nombreCliente = document.getElementById('nombre_cliente').value;
    const telefono = document.getElementById('telefono').value;
    const infoExtra = document.getElementById('info_extra').value;

    const btn = document.getElementById('btn-registrar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando Orden...';

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
            let hayAdelantos = false;
            
            carrito.forEach((rep, index) => {
                if (rep.adelanto > 0) {
                    hayAdelantos = true;
                    let idReal = data.ids_generados ? data.ids_generados[index] : data.id_transaccion;

                    const itemGlobal = {
                        id: idReal,
                        tipo: 'reparacion',
                        accion_reparacion: 'nuevo_adelanto',
                        nombre: 'Adelanto: ' + rep.tipoReparacion + ' ' + rep.modelo,
                        costo_total: rep.monto,
                        a_cobrar: rep.adelanto
                    };

                    setTimeout(() => {
                        if (typeof agregarAlCarritoGlobal === 'function') {
                            agregarAlCarritoGlobal(itemGlobal);
                        }
                    }, 200 * index); 
                }
            });

            if (hayAdelantos) {
                Swal.fire({
                    title: '¡Orden Registrada!',
                    text: 'Los adelantos se han enviado a la caja para su cobro.',
                    icon: 'success',
                    timer: 2500,
                    showConfirmButton: false
                }).then(() => {
                    vaciarCarritoYUI();
                    setTimeout(() => location.reload(), 500);
                });
            } else {
                Swal.fire({
                    title: 'Registro Exitoso',
                    text: 'Los equipos han sido registrados.',
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: 'Aceptar (Nueva Orden)',
                    cancelButtonText: 'Imprimir Ticket',
                    cancelButtonColor: '#ff9500'
                }).then((result) => {
                    if (result.isConfirmed) {
                        vaciarCarritoYUI();
                        location.reload();
                    } else if (result.dismiss === Swal.DismissReason.cancel) {
                        imprimirUltimaYLimpiar(data.id_transaccion);
                    }
                });
            }

        } else {
            Swal.fire('Error', 'Hubo un problema al registrar: ' + (data.error || 'Error desconocido'), 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirmar y Registrar Orden';
        }
    })
    .catch(error => {
        console.error('Error en fetch:', error);
        Swal.fire('Error de Conexión', 'No se pudo conectar con el servidor.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirmar y Registrar Orden';
    });
}

function imprimirUltimaYLimpiar(idTransaccion) {
    if (idTransaccion) {
        window.open('/local3M/generar_ticket.php?id_transaccion=' + encodeURIComponent(idTransaccion), '_blank');
    } else {
        if (typeof ID_TRANSACCION !== 'undefined') {
             window.open('/local3M/generar_ticket.php?id_transaccion=' + encodeURIComponent(ID_TRANSACCION), '_blank');
        } else {
            Swal.fire('Error', 'No se pudo obtener el folio del ticket.', 'error');
        }
    }
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
    btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirmar y Registrar Orden';
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
    document.getElementById('fecha_estimada').value = '';
}