/* =========================================
 * LÓGICA PARA EDITAR REPARACIÓN (JS PURO)
 * Versión Final: Ubicación (Mapa Caja) + Fotos + Pagos + Checklist
 * ========================================= */

// --- DIBUJO DE CÓDIGO DE BARRAS INICIAL ---
document.addEventListener('DOMContentLoaded', function() {
    if (typeof CODIGO_BARRAS !== 'undefined' && document.getElementById("barcode-svg")) {
        try {
            JsBarcode("#barcode-svg", CODIGO_BARRAS, {
                format: "CODE128",
                lineColor: "#000",
                width: 2,
                height: 50,
                displayValue: false
            });
        } catch(e) {
            console.error("Error al dibujar barcode inicial:", e);
        }
    }
});

// --- FUNCIONES MAPA CAJA ---
function abrirMapaCaja() {
    const modalEl = document.getElementById('modalMapaCaja');
    try {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    } catch(e) {
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

            grid.innerHTML = ''; 
            const ocupados = data.data; 
            
            const filas = ['A', 'B', 'C', 'D'];
            const columnas = 12;

            filas.forEach(fila => {
                for (let col = 1; col <= columnas; col++) {
                    const coord = `${fila}${col}`; 
                    
                    const celda = document.createElement('div');
                    celda.className = 'lugar-box';
                    celda.textContent = coord;

                    const ocupacion = ocupados.find(o => o.ubicacion === coord);

                    if (ocupacion) {
                        if (typeof REPARACION_ID !== 'undefined' && ocupacion.id == REPARACION_ID) {
                             celda.classList.add('seleccionado');
                        } else {
                            celda.classList.add('ocupado');
                            celda.title = `Ocupado por Orden #${ocupacion.id}`;
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
                                        window.open(`editar_reparacion.php?id=${ocupacion.id}`, '_blank');
                                    }
                                });
                            };
                        }
                    } else {
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
    Swal.fire({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        icon: 'success',
        title: `Asignado a ${coordenada}`,
        timer: 2000
    });
}

// --- FUNCIONES ORIGINALES (FOTO, DEUDA, ABONO, GUARDAR) ---
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
            const itemGlobal = {
                id: REPARACION_ID,
                tipo: 'reparacion',
                accion_reparacion: 'abonar',
                nombre: 'Abono: ' + marca + ' ' + modelo,
                costo_total: montoTotal,
                a_cobrar: montoAbono
            };

            if (typeof agregarAlCarritoGlobal === 'function') {
                agregarAlCarritoGlobal(itemGlobal);
                document.getElementById('nuevo_abono_monto').value = '';
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: 'Abono enviado al carrito', showConfirmButton: false, timer: 2000
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
            .then(() => { location.reload(); });
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
            const itemGlobal = {
                id: REPARACION_ID,
                tipo: 'reparacion',
                accion_reparacion: 'liquidar',
                nombre: 'Entrega: ' + marca + ' ' + modelo,
                costo_total: costoTotal,
                a_cobrar: saldoPendiente
            };

            if (typeof agregarAlCarritoGlobal === 'function') {
                agregarAlCarritoGlobal(itemGlobal);
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: 'Enviado al Carrito Flotante', showConfirmButton: false, timer: 2000
                });
            } else {
                Swal.fire('Error', 'El carrito global no está conectado.', 'error');
            }
        }
    });
}

function imprimirTicket() {
    window.open(TICKET_URL, '_blank');
}

// ==========================================
// MAGIA DEL CHECKLIST DE REPARACIÓN 
// ==========================================

let estadoAnterior = '';

document.addEventListener('DOMContentLoaded', () => {
    const selectEstadoGlobal = document.getElementById('selectEstado');
    
    if (selectEstadoGlobal) {
        estadoAnterior = selectEstadoGlobal.value;
        
        selectEstadoGlobal.addEventListener('change', function() {
            if (this.value === 'Reparado') {
                window.abrirChecklist();
            } else {
                estadoAnterior = this.value;
            }
        });
    }
});

window.abrirChecklist = function() {
    document.querySelectorAll('.checklist-check').forEach(cb => cb.checked = false);
    
    const modalChecklist = document.getElementById('modalChecklist');
    if(modalChecklist) {
        modalChecklist.style.display = 'flex';
    } else {
        console.error("No se encontró el HTML del modal modalChecklist");
    }
};

window.cancelarChecklist = function() {
    document.getElementById('modalChecklist').style.display = 'none';
    const selectEstadoGlobal = document.getElementById('selectEstado');
    if (selectEstadoGlobal) {
        selectEstadoGlobal.value = estadoAnterior;
    }
};

window.confirmarChecklist = function() {
    const checks = document.querySelectorAll('.checklist-check');
    const allChecked = Array.from(checks).every(cb => cb.checked);
    
    if (!allChecked) {
        Swal.fire({
            title: '¿Confirmar con pendientes?',
            text: 'Aún no has marcado todas las pruebas. ¿Estás 100% seguro de que el equipo está listo para entregarse?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#34c759',
            cancelButtonColor: '#8e8e93',
            confirmButtonText: 'Sí, forzar Reparado',
            cancelButtonText: 'Revisar de nuevo'
        }).then((result) => {
            if (result.isConfirmed) {
                window.finalizarChecklist();
            }
        });
    } else {
        window.finalizarChecklist();
    }
};

window.finalizarChecklist = function() {
    document.getElementById('modalChecklist').style.display = 'none';
    estadoAnterior = 'Reparado'; 
    
    Swal.fire({
        toast: true, position: 'top-end', icon: 'success',
        title: 'Calidad Aprobada', showConfirmButton: false, timer: 2000
    });

    let infoExtraInput = document.getElementsByName('info_extra')[0];
    if (infoExtraInput && !infoExtraInput.value.includes('[Checklist OK]')) {
        infoExtraInput.value = infoExtraInput.value ? infoExtraInput.value + ' | [Checklist OK]' : '[Checklist OK]';
    }
};