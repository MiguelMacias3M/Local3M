/*
 * Lógica para Editar Reparación
 * VERSIÓN: Con soporte para subir FOTOS (FormData)
 */

document.addEventListener('DOMContentLoaded', function() {
    // Generar código de barras
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

// --- Función para previsualizar la foto antes de subir ---
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

// --- Calcular Deuda ---
function calcularDeuda() {
    let monto = parseFloat(document.getElementById('monto').value) || 0;
    let adelanto = parseFloat(document.getElementById('adelanto').value) || 0;
    let deuda = monto - adelanto;
    if (deuda < 0) deuda = 0;
    document.getElementById('deuda').value = deuda;
}

// --- Agregar Abono Visual ---
function agregarAbono() {
    let montoAbono = parseFloat(document.getElementById('nuevo_abono_monto').value);
    if (!montoAbono || montoAbono <= 0) {
        Swal.fire('Error', 'Ingrese un monto válido', 'warning');
        return;
    }

    let adelantoInput = document.getElementById('adelanto');
    let nuevoAdelanto = parseFloat(adelantoInput.value) + montoAbono;
    
    // Validar que no pague más de la cuenta
    let montoTotal = parseFloat(document.getElementById('monto').value);
    if (nuevoAdelanto > montoTotal) {
        Swal.fire('Cuidado', 'El abono supera la deuda total', 'warning');
        return;
    }

    adelantoInput.value = nuevoAdelanto;
    document.getElementById('nuevo_abono_monto').value = ''; // Limpiar
    calcularDeuda();
    
    Swal.fire({
        title: 'Abono agregado temporalmente',
        text: 'Para confirmar y registrar en caja, presiona "Guardar Cambios".',
        icon: 'info',
        timer: 3000
    });
}

// --- GUARDAR CAMBIOS (CON FOTO) ---
function guardarCambios() {
    // 1. Usamos FormData en lugar de JSON simple
    let formData = new FormData();
    
    formData.append('action', 'guardar');
    formData.append('id', REPARACION_ID);
    
    // Agregar campos de texto
    formData.append('nombre_cliente', document.getElementById('nombre_cliente').value);
    formData.append('telefono', document.getElementById('telefono').value);
    formData.append('tipo_reparacion', document.getElementById('tipo_reparacion').value);
    formData.append('marca_celular', document.getElementById('marca_celular').value);
    formData.append('modelo', document.getElementById('modelo').value);
    formData.append('monto', document.getElementById('monto').value);
    formData.append('adelanto', document.getElementById('adelanto').value);
    formData.append('info_extra', document.getElementsByName('info_extra')[0].value);
    formData.append('estado', document.getElementById('selectEstado').value);

    // 2. Agregar el archivo (si existe)
    const fileInput = document.getElementById('evidencia_input');
    if(fileInput.files.length > 0) {
        formData.append('evidencia', fileInput.files[0]);
    }

    Swal.fire({
        title: 'Guardando...',
        text: 'Subiendo datos y evidencia, por favor espere.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading() }
    });

    // 3. Enviar sin headers JSON (fetch detecta multipart automáticamente)
    fetch('api/editar_reparacion.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Guardado', 'Cambios registrados correctamente', 'success')
            .then(() => {
                location.reload(); // Recargar para ver la foto en historial
            });
        } else {
            Swal.fire('Error', data.message || 'Error al guardar', 'error');
        }
    })
    .catch(error => {
        console.error(error);
        Swal.fire('Error', 'Fallo en la conexión', 'error');
    });
}

// --- Entregar Reparación ---
function entregarReparacion() {
    // Para entregar, no necesitamos foto obligatoria, usamos JSON simple
    // o reutilizamos FormData. Usaremos JSON simple aquí por simplicidad
    // a menos que quieras subir foto de entrega también.
    
    Swal.fire({
        title: '¿Entregar equipo?',
        text: "Se marcará como entregado y se liquidará la deuda.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        confirmButtonText: 'Sí, entregar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api/editar_reparacion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'entregar',
                    id: REPARACION_ID
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: '¡Entregado!',
                        text: 'Equipo entregado con éxito.',
                        icon: 'success'
                    }).then(() => {
                        // Abrir ticket y volver
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

// --- Imprimir Ticket ---
function imprimirTicket() {
    window.open(TICKET_URL, '_blank');
}