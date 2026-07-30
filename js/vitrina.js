let timeoutBusqueda;

document.addEventListener('DOMContentLoaded', () => {
    cargarVitrina();
    
    const inputBuscar = document.getElementById('buscarVitrina');
    if(inputBuscar) {
        inputBuscar.addEventListener('input', () => {
            clearTimeout(timeoutBusqueda);
            timeoutBusqueda = setTimeout(() => {
                cargarVitrina(inputBuscar.value.trim());
            }, 300);
        });
        inputBuscar.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(timeoutBusqueda);
                cargarVitrina(inputBuscar.value.trim());
            }
        });
    }
});

function obtenerFechaHoraActual() {
    const ahora = new Date();
    return ahora.toLocaleString('es-MX', { 
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
}

function calcularSaldo() {
    const total = parseFloat(document.getElementById('apartar_precio_oculto').value) || 0;
    const anticipo = parseFloat(document.getElementById('apartar_anticipo').value) || 0;
    let saldo = total - anticipo;
    if (saldo < 0) saldo = 0;
    document.getElementById('apartar_saldo').value = saldo.toFixed(2);
}

async function cargarVitrina(query = '') {
    try {
        const res = await fetch(`/local3M/api/vitrina.php?action=listar&q=${encodeURIComponent(query)}`);
        const json = await res.json();
        const tbody = document.getElementById('tablaVitrinaBody');
        tbody.innerHTML = '';
        
        if (json.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:#86868b;">No se encontraron equipos en vitrina.</td></tr>';
            return;
        }

        json.data.forEach(e => {
            const tr = document.createElement('tr');
            let badgeEstado = '', clienteHtml = '<span style="color:#c7c7cc;">--</span>', botonesHtml = '';
            
            // Construir Nombre Completo
            const nombreEquipo = `${e.marca || ''} ${e.modelo || ''} ${e.color || ''}`.trim();

            if (e.estado === 'Disponible') {
                badgeEstado = `<span class="badge-status status-disponible"><i class="fas fa-check-circle"></i> Disponible</span>`;
                botonesHtml = `
                    <button class="btn-icon print" onclick="event.stopPropagation(); abrirModalVender(${e.id}, '${nombreEquipo}', '${e.imei_serie}', ${e.precio_venta})" title="Vender">
                        <i class="fas fa-dollar-sign"></i>
                    </button>
                    <button class="btn-icon edit" onclick="event.stopPropagation(); abrirModalApartar(${e.id}, '${nombreEquipo}', '${e.imei_serie}', ${e.precio_venta})" title="Apartar">
                        <i class="fas fa-calendar-alt"></i>
                    </button>
                    <button class="btn-icon" style="background:rgba(0,0,0,0.05);" onclick="event.stopPropagation(); editarEquipo(${e.id})" title="Editar">
                        <i class="fas fa-pen"></i>
                    </button>
                `;
           } else if (e.estado === 'Apartado') {
                badgeEstado = `<span class="badge-status status-apartado"><i class="fas fa-clock"></i> Apartado</span>`;
                clienteHtml = `
                    <div class="cliente-info">
                        <span class="cliente-nombre"><i class="fas fa-user"></i> ${e.cliente_nombre || 'Sin nombre'}</span>
                        <span class="cliente-fecha">${e.fecha_operacion}</span>
                        <span class="text-danger" style="font-size:12px; font-weight:700;">Resta: $${e.saldo_restante}</span>
                    </div>`;
                
               // --- BOTONES PARA EQUIPOS APARTADOS ---
                botonesHtml = `
                    <button class="btn-icon print" onclick="event.stopPropagation(); abrirModalAbono(${e.id}, '${nombreEquipo}', '${e.imei_serie}', ${e.saldo_restante}, '${e.cliente_nombre}')" title="Abonar / Liquidar">
                        <i class="fas fa-hand-holding-usd"></i>
                    </button>
                    <!-- NUEVO BOTON DE CANCELAR APARTADO -->
                    <button class="btn-icon delete" onclick="event.stopPropagation(); cancelarApartado(${e.id}, '${nombreEquipo}', ${e.anticipo})" title="Cancelar Apartado y Devolver Dinero">
                        <i class="fas fa-ban"></i>
                    </button>`;
            }
             else if (e.estado === 'Vendido') {
                badgeEstado = `<span class="badge-status status-vendido"><i class="fas fa-lock"></i> Vendido</span>`;
                clienteHtml = `
                    <div class="cliente-info">
                        <span class="cliente-nombre"><i class="fas fa-user"></i> ${e.cliente_nombre || 'Sin nombre'}</span>
                        <span class="cliente-fecha">${e.fecha_operacion}</span>
                    </div>`;
                botonesHtml = `
                    <button class="btn-icon outline-btn" onclick="event.stopPropagation(); Swal.fire('Garantía Válida', 'Equipo vendido a ${e.cliente_nombre}', 'success')" title="Ver Garantía">
                        <i class="fas fa-shield-alt"></i>
                    </button>`;
            }

            tr.innerHTML = `
                <td>
                    <span style="font-size:12px; color:#86868b; display:block;">${e.tipo}</span>
                    <strong style="color: #1d1d1f; font-size: 15px;">${nombreEquipo}</strong>
                </td>
                <td><span class="badge-code">${e.imei_serie || '--'}</span></td>
                <td>
                    <span class="badge-precio" style="font-size: 16px; color: #007aff;">$${parseFloat(e.precio_venta).toFixed(2)}</span>
                </td>
                <td>${badgeEstado}</td>
                <td>${clienteHtml}</td>
                <td class="text-right" style="white-space: nowrap;">${botonesHtml}</td>
            `;
            tbody.appendChild(tr);
        });
    } catch (e) { console.error(e); }
}

function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }

// FUNCIONES DE MODALES Y ACCIONES
function abrirModalNuevo() {
    document.getElementById('btnEliminarEquipo').style.display = 'none'; // OCULTAMOS EL BOTÓN ROJO
    document.getElementById('formNuevoEquipo').reset();
    document.getElementById('equipo_id').value = '';
    document.getElementById('tituloModalNuevo').textContent = 'Registrar Equipo';
    document.getElementById('modalNuevoEquipo').style.display = 'flex';
}

function abrirModalVender(id, nombre, imei, precio) {
    document.getElementById('formVender').reset();
    document.getElementById('vender_id_equipo').value = id;
    document.getElementById('vender_nombre_equipo').textContent = nombre;
    document.getElementById('vender_imei_equipo').textContent = 'IMEI/Serie: ' + imei;
    document.getElementById('vender_precio_equipo').textContent = 'Total: $' + parseFloat(precio).toFixed(2);
    document.getElementById('vender_fecha_hora').value = obtenerFechaHoraActual();
    document.getElementById('modalVender').style.display = 'flex';
}

function abrirModalApartar(id, nombre, imei, precio) {
    document.getElementById('formApartar').reset();
    document.getElementById('apartar_id_equipo').value = id;
    document.getElementById('apartar_precio_oculto').value = precio;
    document.getElementById('apartar_nombre_equipo').textContent = nombre;
    document.getElementById('apartar_imei_equipo').textContent = 'IMEI/Serie: ' + imei;
    document.getElementById('apartar_precio_equipo').textContent = 'Total: $' + parseFloat(precio).toFixed(2);
    document.getElementById('modalApartar').style.display = 'flex';
}

async function guardarEquipo() {
    const formData = new FormData(document.getElementById('formNuevoEquipo'));
    formData.append('action', 'guardar_equipo');
    try {
        const res = await fetch('/local3M/api/vitrina.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            Swal.fire({ icon: 'success', title: 'Equipo Guardado', showConfirmButton: false, timer: 1200 });
            cerrarModal('modalNuevoEquipo');
            cargarVitrina();
        } else Swal.fire('Error', json.error, 'error');
    } catch (e) { Swal.fire('Error', 'Problema de conexión', 'error'); }
}

// --- CENTRALIZAMOS TODAS LAS VENTAS, ABONOS Y APARTADOS AL CARRITO ---
function procesarAccionVitrina(accion) {
    if (accion === 'Vender') {
        const id = document.getElementById('vender_id_equipo').value;
        const cliente = document.getElementById('vender_cliente').value.trim();
        const telefono = document.getElementById('vender_telefono').value.trim();
        const nombreEquipo = document.getElementById('vender_nombre_equipo').textContent;
        // Limpiamos el texto "Total: $1500.00" para quedarnos solo con el número
        const precio = parseFloat(document.getElementById('vender_precio_equipo').textContent.replace(/[^0-9.]/g, ''));

        if (!cliente) return Swal.fire('Atención', 'El nombre del cliente es obligatorio', 'warning');

        if (typeof agregarAlCarritoGlobal === 'function') {
            agregarAlCarritoGlobal({
                id: id,
                nombre: nombreEquipo,
                precio: precio,
                cantidad: 1,
                tipo: 'equipo', // Tu carrito ya soporta este tipo para venta directa
                cliente_nombre: cliente,
                telefono: telefono
            });
            cerrarModal('modalVender');
        } else {
            Swal.fire('Error', 'No se detectó el carrito global.', 'error');
        }
    } 
    else if (accion === 'Apartar') {
        const id = document.getElementById('apartar_id_equipo').value;
        const cliente = document.getElementById('apartar_cliente').value.trim();
        const telefono = document.getElementById('apartar_telefono').value.trim();
        const anticipo = parseFloat(document.getElementById('apartar_anticipo').value) || 0;
        const saldo = parseFloat(document.getElementById('apartar_saldo').value) || 0;
        const nombreEquipo = document.getElementById('apartar_nombre_equipo').textContent;

        if (!cliente || anticipo <= 0) return Swal.fire('Atención', 'Cliente y anticipo válido son obligatorios', 'warning');

        if (typeof agregarAlCarritoGlobal === 'function') {
            agregarAlCarritoGlobal({
                id: id,
                nombre: 'Enganche: ' + nombreEquipo,
                precio: anticipo,
                cantidad: 1,
                tipo: 'abono_apartado', // Usamos este para que tome el ícono morado de tu carrito
                cliente_nombre: cliente,
                // Le pasamos estas variables ocultas para que el backend sepa que es un apartado NUEVO
                es_nuevo_apartado: true,
                telefono: telefono,
                saldo_restante: saldo
            });
            cerrarModal('modalApartar');
        }
    }
}

// Actualizamos el Abono para que también solo envíe datos, sin tocar la BD antes de tiempo
function procesarAbono() {
    const id = document.getElementById('abonar_id_equipo').value;
    const abono = parseFloat(document.getElementById('abonar_monto').value) || 0;
    const nombreEquipo = document.getElementById('abonar_nombre_equipo').textContent;
    const nombreCliente = document.getElementById('formAbonar').getAttribute('data-cliente') || 'Cliente';

    if (abono <= 0) return Swal.fire('Atención', 'Ingresa un monto válido a abonar', 'warning');

    if (typeof agregarAlCarritoGlobal === 'function') {
        agregarAlCarritoGlobal({
            id: id,
            nombre: 'Abono: ' + nombreEquipo,
            precio: abono,
            cantidad: 1,
            tipo: 'abono_apartado',
            cliente_nombre: nombreCliente,
            es_nuevo_apartado: false // Backend sabrá que es un abono a deuda existente
        });
        cerrarModal('modalAbonar');
    }
}
function abrirModalAbono(id, nombre, imei, saldo_actual, cliente) {
    document.getElementById('formAbonar').reset();
    document.getElementById('abonar_id_equipo').value = id;
    document.getElementById('abonar_saldo_actual_oculto').value = saldo_actual;
    
    // Guardamos el cliente temporalmente en un atributo del form para usarlo al cobrar
    document.getElementById('formAbonar').setAttribute('data-cliente', cliente);

    document.getElementById('abonar_nombre_equipo').textContent = nombre;
    document.getElementById('abonar_imei_equipo').textContent = 'IMEI/Serie: ' + imei;
    document.getElementById('abonar_saldo_texto').textContent = 'Deuda Actual: $' + parseFloat(saldo_actual).toFixed(2);
    document.getElementById('abonar_nuevo_saldo').value = parseFloat(saldo_actual).toFixed(2);
    
    document.getElementById('modalAbonar').style.display = 'flex';
    setTimeout(() => document.getElementById('abonar_monto').focus(), 100);
}

function calcularNuevoSaldo() {
    const saldoActual = parseFloat(document.getElementById('abonar_saldo_actual_oculto').value) || 0;
    const abono = parseFloat(document.getElementById('abonar_monto').value) || 0;
    let nuevoSaldo = saldoActual - abono;
    
    // Si el cliente da más dinero del que debe, el saldo no puede ser negativo
    if (nuevoSaldo < 0) nuevoSaldo = 0; 
    
    document.getElementById('abonar_nuevo_saldo').value = nuevoSaldo.toFixed(2);
}

async function procesarAbono() {
    const id = document.getElementById('abonar_id_equipo').value;
    const abono = document.getElementById('abonar_monto').value;
    const nombreEquipo = document.getElementById('abonar_nombre_equipo').textContent;
    const nombreCliente = document.getElementById('formAbonar').getAttribute('data-cliente') || 'Mostrador';

    if (!abono || parseFloat(abono) <= 0) {
        return Swal.fire('Atención', 'Ingresa un monto válido a abonar', 'warning');
    }

    let formData = new FormData();
    formData.append('action', 'abonar');
    formData.append('id', id);
    formData.append('abono', abono);

    try {
        const res = await fetch('/local3M/api/vitrina.php', { method: 'POST', body: formData });
        const json = await res.json();
        
        if (json.success) {
            
            // --- CONEXIÓN PERFECTA CON TU CARRITO GLOBAL ---
            if (typeof agregarAlCarritoGlobal === 'function') {
                const itemAbono = {
                    id: id,
                    nombre: 'Abono: ' + nombreEquipo,
                    precio: parseFloat(abono),
                    cantidad: 1,
                    tipo: 'abono_apartado',
                    cliente_nombre: nombreCliente
                };
                
                agregarAlCarritoGlobal(itemAbono);
            } else {
                console.warn("No se detectó la función agregarAlCarritoGlobal.");
            }

            const nuevoSaldo = document.getElementById('abonar_nuevo_saldo').value;
            let mensaje = parseFloat(nuevoSaldo) <= 0 
                ? 'Equipo liquidado. Enviado al carrito para cobro final.' 
                : 'Abono registrado. Enviado al carrito para cobro.';
                
            Swal.fire({ icon: 'success', title: 'Listo', text: mensaje, showConfirmButton: false, timer: 1500 });
            cerrarModal('modalAbonar');
            cargarVitrina(document.getElementById('buscarVitrina').value);
        } else {
            Swal.fire('Error', json.error, 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Problema de conexión con el servidor', 'error');
    }
    
}

// --- FUNCIÓN PARA EDITAR CON CONTRASEÑA MAESTRA ---
async function editarEquipo(id) {
    const { value: password } = await Swal.fire({
        title: 'Seguridad',
        text: 'Ingresa la contraseña maestra para modificar este equipo',
        input: 'password',
        inputPlaceholder: 'Contraseña maestra...',
        inputAttributes: {
            autocapitalize: 'off',
            autocorrect: 'off'
        },
        showCancelButton: true,
        confirmButtonColor: '#ff9500',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Autorizar'
    });

    if (!password) return; // Si canceló o cerró el cuadro

    let formData = new FormData();
    formData.append('action', 'obtener_editar');
    formData.append('id', id);
    formData.append('password', password);

    try {
        const res = await fetch('/local3M/api/vitrina.php', { method: 'POST', body: formData });
        const json = await res.json();
        
        if (json.success) {
            const e = json.data;
            
            // Llenar el formulario con los datos protegidos
            document.getElementById('equipo_id').value = e.id;
            document.getElementById('equipo_tipo').value = e.tipo;
            document.getElementById('equipo_imei').value = e.imei_serie;
            document.getElementById('equipo_marca').value = e.marca;
            document.getElementById('equipo_modelo').value = e.modelo;
            document.getElementById('equipo_color').value = e.color;
            document.getElementById('equipo_costo').value = parseFloat(e.costo).toFixed(2);
            document.getElementById('equipo_precio').value = parseFloat(e.precio_venta).toFixed(2);
            
            // Cambiar título y mostrar modal
            document.getElementById('tituloModalNuevo').textContent = 'Editar Equipo';
            document.getElementById('btnEliminarEquipo').style.display = 'inline-flex'; // MOSTRAMOS EL BOTÓN ROJO
            document.getElementById('modalNuevoEquipo').style.display = 'flex';
        } else {
            Swal.fire('Acceso Denegado', json.error, 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'Problema de conexión con el servidor', 'error');
    }
}

function eliminarEquipo() {
    const id = document.getElementById('equipo_id').value;
    
    if (!id) return; // Por si acaso

    Swal.fire({
        title: '¿Estás completamente seguro?',
        text: "Esta acción borrará el equipo de la vitrina para siempre y no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff3b30',
        cancelButtonColor: '#86868b',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            let formData = new FormData();
            formData.append('action', 'eliminar_equipo');
            formData.append('id', id);

            try {
                const res = await fetch('/local3M/api/vitrina.php', { method: 'POST', body: formData });
                const json = await res.json();
                
                if (json.success) {
                    Swal.fire({ icon: 'success', title: 'Eliminado', text: 'El equipo fue borrado de tu vitrina.', timer: 1500, showConfirmButton: false });
                    cerrarModal('modalNuevoEquipo');
                    cargarVitrina(document.getElementById('buscarVitrina').value);
                } else {
                    Swal.fire('Error', json.error, 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
            }
        }
    });
}

// --- FUNCIÓN PARA CANCELAR UN APARTADO Y REEMBOLSAR ---
function cancelarApartado(id, nombreEquipo, anticipo) {
    Swal.fire({
        title: '¿Cancelar apartado?',
        text: `El equipo volverá a estar "Disponible". Se registrará una salida de tu caja actual (Devolución) por $${parseFloat(anticipo).toFixed(2)} para regresárselos al cliente.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff3b30',
        cancelButtonColor: '#86868b',
        confirmButtonText: 'Sí, cancelar y devolver',
        cancelButtonText: 'No'
    }).then(async (result) => {
        if (result.isConfirmed) {
            let formData = new FormData();
            formData.append('action', 'cancelar_apartado');
            formData.append('id', id);

            try {
                const res = await fetch('/local3M/api/vitrina.php', { method: 'POST', body: formData });
                const json = await res.json();
                
                if (json.success) {
                    Swal.fire('Cancelado', 'El equipo vuelve a estar disponible y el anticipo se descontó de tu caja de hoy.', 'success');
                    cargarVitrina(document.getElementById('buscarVitrina').value);
                } else {
                    Swal.fire('Error', json.error, 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'Problema de conexión con el servidor', 'error');
            }
        }
    });
}