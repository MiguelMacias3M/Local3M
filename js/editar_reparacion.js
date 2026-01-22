/*
 * Lógica para Editar Reparación
 * Versión Final: Ubicación + Fotos
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
    
    // Campos de Texto
    formData.append('nombre_cliente', document.getElementById('nombre_cliente').value);
    formData.append('telefono', document.getElementById('telefono').value);
    formData.append('tipo_reparacion', document.getElementById('tipo_reparacion').value);
    formData.append('marca_celular', document.getElementById('marca_celular').value);
    formData.append('modelo', document.getElementById('modelo').value);
    formData.append('monto', document.getElementById('monto').value);
    formData.append('adelanto', document.getElementById('adelanto').value);
    formData.append('info_extra', document.getElementsByName('info_extra')[0].value);
    formData.append('estado', document.getElementById('selectEstado').value);
    
    // --- CAMPO NUEVO: UBICACIÓN ---
    let ubicacion = document.getElementById('ubicacion').value;
    formData.append('ubicacion', ubicacion); 

    // Archivo
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