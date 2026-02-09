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
                        // Si es MI equipo actual
                        if (typeof REPARACION_ID !== 'undefined' && ocupacion.id == REPARACION_ID) {
                             celda.classList.add('seleccionado');
                        } else {
                            // Si es otro equipo
                            celda.classList.add('ocupado');
                            celda.title = `Ocupado (Orden #${ocupacion.id})`;
                        }
                    } else {
                        // Libre
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
    if (!montoAbono || montoAbono <= 0) {
        Swal.fire('Error', 'Ingrese un monto válido', 'warning');
        return;
    }

    let adelantoInput = document.getElementById('adelanto');
    let nuevoAdelanto = parseFloat(adelantoInput.value) + montoAbono;
    
    let montoTotal = parseFloat(document.getElementById('monto').value);
    if (nuevoAdelanto > montoTotal) {
        Swal.fire('Cuidado', 'El abono supera la deuda total', 'warning');
        return;
    }

    adelantoInput.value = nuevoAdelanto;
    document.getElementById('nuevo_abono_monto').value = '';
    calcularDeuda();
    
    Swal.fire({
        title: 'Abono agregado temporalmente',
        text: 'Presiona "Guardar" para confirmar el abono y registrarlo en caja.',
        icon: 'info',
        timer: 3000
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
    Swal.fire({
        title: '¿Entregar equipo?',
        text: "Se marcará como entregado y se liquidará la deuda.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        confirmButtonText: 'Sí, entregar'
    }).then((result) => {
        if (result.isConfirmed) {
            let formData = new FormData();
            formData.append('action', 'entregar');
            formData.append('id', REPARACION_ID);

            fetch('api/editar_reparacion.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('¡Entregado!', 'Equipo entregado.', 'success').then(() => {
                        window.open(data.ticketUrl, '_blank');
                        window.location.href = 'control.php';
                    });
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }
    });
}

function imprimirTicket() {
    window.open(TICKET_URL, '_blank');
}