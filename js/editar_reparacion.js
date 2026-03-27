/*
 * Lógica para Editar Reparación
 * Versión Final: Ubicación (Mapa Caja) + Fotos + Pagos
 */

document.addEventListener('DOMContentLoaded', function() {
    if (typeof CODIGO_BARRAS !== 'undefined' && document.getElementById("barcode-svg")) {
        JsBarcode("#barcode-svg", CODIGO_BARRAS, {
            format: "CODE128",
            lineColor: "#000",
            width: 2,
            height: 50,
            displayValue: false
        });
    }
});

// --- FUNCIONES MAPA CAJA ---

function abrirMapaCaja() {
    // Usamos bootstrap para abrir el modal si está disponible, o CSS manual
    const modalEl = document.getElementById('modalMapaCaja');
    
    // Intentar abrir con Bootstrap
    try {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    } catch(e) {
        // Fallback manual si no hay bootstrap JS cargado
        modalEl.style.display = 'block';
        modalEl.classList.add('show');
    }

    dibujarCaja();
}

function cerrarModalMapa() {
    const modalEl = document.getElementById('modalMapaCaja');
    
    try {
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
    } catch(e) {
        modalEl.style.display = 'none';
        modalEl.classList.remove('show');
    }
}

function dibujarCaja() {
    const grid = document.getElementById('grid-caja');
    grid.innerHTML = '<div class="col-12 text-center"><i class="fas fa-spinner fa-spin"></i> Cargando caja...</div>';

    fetch('api/obtener_lugares.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                grid.innerHTML = 'Error al cargar datos.';
                return;
            }

            grid.innerHTML = ''; // Limpiar
            const ocupados = data.data; // Array {id, ubicacion}
            
            const filas = ['A', 'B', 'C', 'D'];
            const columnas = 12;

            filas.forEach(fila => {
                for (let col = 1; col <= columnas; col++) {
                    const coord = `${fila}${col}`; 
                    
                    // Crear celda
                    const celda = document.createElement('div');
                    celda.className = 'lugar-box';
                    celda.textContent = coord;

                    // Buscar si está ocupado
                    const ocupacion = ocupados.find(o => o.ubicacion === coord);

                    if (ocupacion) {
                        // CASO 1: Es el equipo que estoy editando AHORITA
                        if (typeof REPARACION_ID !== 'undefined' && ocupacion.id == REPARACION_ID) {
                             celda.classList.add('seleccionado');
                             // Si le doy click al mío, no hace nada o confirma
                        } else {
                            // CASO 2: Es OTRO equipo (El que se les olvidó entregar)
                            celda.classList.add('ocupado');
                            celda.title = `Ocupado por Orden #${ocupacion.id}`;
                            
                            // --- AQUÍ ESTÁ LA MAGIA ---
                            // Al hacer click, preguntamos si quieren ir a ver esa reparación
                            celda.onclick = () => {
                                Swal.fire({
                                    title: `Lugar ${coord} Ocupado`,
                                    text: `Este lugar lo tiene la orden #${ocupacion.id}. ¿Quieres ver esa reparación?`,
                                    icon: 'question',
                                    showCancelButton: true,
                                    confirmButtonText: 'Sí, ir a verla',
                                    cancelButtonText: 'Cancelar'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        // Abrimos en una pestaña nueva para no perder lo que hacías
                                        window.open(`editar_reparacion.php?id=${ocupacion.id}`, '_blank');
                                    }
                                });
                            };
                        }
                    } else {
                        // CASO 3: Lugar Libre
                        celda.onclick = () => seleccionarLugar(coord);
                    }

                    grid.appendChild(celda);
                }
            });
        });
}

function seleccionarLugar(coordenada) {
    document.getElementById('ubicacion').value = coordenada;
    cerrarModalMapa();
    
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000
    });
    Toast.fire({
        icon: 'success',
        title: `Asignado a ${coordenada}`
    });
}

// --- FUNCIONES ORIGINALES ---

function previsualizarFoto() {
    const input = document.getElementById('evidencia_input');
    const preview = document.getElementById('img-preview');
    const container = document.getElementById('preview-container');

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            container.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    } else {
        container.style.display = 'none';
    }
}

function calcularDeuda() {
    let monto = parseFloat(document.getElementById('monto').value) || 0;
    let adelanto = parseFloat(document.getElementById('adelanto').value) || 0;
    let deuda = monto - adelanto;
    if (deuda < 0) deuda = 0;
    document.getElementById('deuda').value = deuda;
}

function agregarAbono() {
    let montoAbono = parseFloat(document.getElementById('nuevo_abono_monto').value);
    
    // 1. Validaciones
    if (!montoAbono || montoAbono <= 0) {
        Swal.fire('Error', 'Ingrese un monto válido para el abono.', 'warning');
        return;
    }

    let montoTotal = parseFloat(document.getElementById('monto').value) || 0;
    let deudaActual = parseFloat(document.getElementById('deuda').value) || 0;

    if (montoAbono > deudaActual) {
        Swal.fire('Cuidado', `El abono ($${montoAbono}) no puede ser mayor a la deuda actual ($${deudaActual}).`, 'warning');
        return;
    }

    const modelo = document.getElementById('modelo').value;
    const marca = document.getElementById('marca_celular').value;

    // 2. Confirmación y envío al carrito
    Swal.fire({
        title: '¿Cobrar Abono en Caja?',
        html: `Se enviará un abono por <b>$${montoAbono.toFixed(2)}</b> para el equipo <b>${marca} ${modelo}</b> al carrito global.`,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#007aff',
        confirmButtonText: '<i class="fas fa-cart-plus"></i> Mandar a Caja',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            
            // Empaquetamos los datos con la etiqueta secreta "abonar"
            const itemGlobal = {
                id: REPARACION_ID,
                tipo: 'reparacion',
                accion_reparacion: 'abonar', // LA ETIQUETA SECRETA
                nombre: 'Abono: ' + marca + ' ' + modelo,
                costo_total: montoTotal,
                a_cobrar: montoAbono
            };

            if (typeof agregarAlCarritoGlobal === 'function') {
                agregarAlCarritoGlobal(itemGlobal);
                
                // Limpiamos la cajita de texto del abono para que no estorbe
                document.getElementById('nuevo_abono_monto').value = '';

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Abono enviado al carrito',
                    showConfirmButton: false,
                    timer: 2000
                });
            } else {
                Swal.fire('Error', 'El carrito global no está conectado.', 'error');
            }
        }
    });
}

function guardarCambios() {
    let formData = new FormData();
    formData.append('action', 'guardar');
    formData.append('id', REPARACION_ID);
    
    formData.append('nombre_cliente', document.getElementById('nombre_cliente').value);
    formData.append('telefono', document.getElementById('telefono').value);
    formData.append('tipo_reparacion', document.getElementById('tipo_reparacion').value);
    formData.append('marca_celular', document.getElementById('marca_celular').value);
    formData.append('modelo', document.getElementById('modelo').value);
    formData.append('monto', document.getElementById('monto').value);
    formData.append('adelanto', document.getElementById('adelanto').value);
    formData.append('info_extra', document.getElementsByName('info_extra')[0].value);
    formData.append('estado', document.getElementById('selectEstado').value);
    formData.append('fecha_estimada', document.getElementById('fecha_estimada').value);     
    formData.append('ubicacion', document.getElementById('ubicacion').value); 

    const fileInput = document.getElementById('evidencia_input');
    if(fileInput.files.length > 0) {
        formData.append('evidencia', fileInput.files[0]);
    }

    Swal.fire({
        title: 'Guardando...',
        didOpen: () => { Swal.showLoading() }
    });

    fetch('api/editar_reparacion.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Guardado', 'Cambios registrados correctamente', 'success')
            .then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', data.message || 'Error al guardar', 'error');
        }
    })
    .catch(error => {
        Swal.fire('Error', 'Fallo de conexión', 'error');
    });
}

function entregarReparacion() {
    const deudaInput = document.getElementById('deuda');
    const saldoPendiente = parseFloat(deudaInput.value) || 0;
    const costoTotal = parseFloat(document.getElementById('monto').value) || 0;
    const modelo = document.getElementById('modelo').value;
    const marca = document.getElementById('marca_celular').value;

    Swal.fire({
        title: '¿Mandar a Caja para Entregar?',
        html: `El equipo <b>${marca} ${modelo}</b> se enviará al carrito global.<br><br>Saldo a cobrar: <b>$${saldoPendiente.toFixed(2)}</b>`,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#007aff',
        confirmButtonText: '<i class="fas fa-cart-plus"></i> Mandar a Caja',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Empaquetamos los datos con la etiqueta secreta "liquidar"
            const itemGlobal = {
                id: REPARACION_ID,
                tipo: 'reparacion',
                accion_reparacion: 'liquidar', // LA ETIQUETA SECRETA
                nombre: 'Entrega: ' + marca + ' ' + modelo,
                costo_total: costoTotal,
                a_cobrar: saldoPendiente
            };

            // Mandamos el paquete al carrito global
            if (typeof agregarAlCarritoGlobal === 'function') {
                agregarAlCarritoGlobal(itemGlobal);
                
                // Opcional: Redirigir a ventas o cerrar la ventana de edición
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Enviado al Carrito Flotante',
                    showConfirmButton: false,
                    timer: 2000
                });
            } else {
                Swal.fire('Error', 'El carrito global no está conectado. Verifica tu header.', 'error');
            }
        }
    });
}

function imprimirTicket() {
    window.open(TICKET_URL, '_blank');
}