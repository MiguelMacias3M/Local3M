// Inicializar código de barras al cargar
document.addEventListener('DOMContentLoaded', () => {
    if (typeof JsBarcode !== 'undefined' && CODIGO_BARRAS) {
        JsBarcode("#barcode-svg", CODIGO_BARRAS, {
            format: "code128",
            width: 2,
            height: 60,
            displayValue: false
        });
    }
});

// Calcular deuda en tiempo real
function calcularDeuda() {
    const monto = parseInt(document.getElementById('monto').value) || 0;
    const adelanto = parseInt(document.getElementById('adelanto').value) || 0;
    const deuda = Math.max(0, monto - adelanto);
    document.getElementById('deuda').value = deuda;
}

// Agregar Abono (Lógica local antes de guardar)
function agregarAbono() {
    const inputAbono = document.getElementById('nuevo_abono_monto');
    const inputAdelanto = document.getElementById('adelanto');
    const inputMonto = document.getElementById('monto');

    const abono = parseInt(inputAbono.value) || 0;
    const adelantoActual = parseInt(inputAdelanto.value) || 0;
    const montoTotal = parseInt(inputMonto.value) || 0;

    if (abono <= 0) {
        Swal.fire('Error', 'Ingresa un monto válido mayor a 0', 'warning');
        return;
    }

    if ((adelantoActual + abono) > montoTotal) {
        Swal.fire('Error', 'El abono supera la deuda pendiente.', 'error');
        return;
    }

    // Actualizar campos visualmente
    inputAdelanto.value = adelantoActual + abono;
    inputAbono.value = ''; // Limpiar campo
    calcularDeuda();

    Swal.fire({
        icon: 'success',
        title: 'Abono Agregado',
        text: `Se sumaron $${abono} al adelanto. Presiona "Guardar Cambios" para registrarlo en caja.`,
        timer: 3000
    });
}

// Guardar Cambios
async function guardarCambios() {
    const result = await Swal.fire({
        title: '¿Guardar cambios?',
        text: 'Se actualizará la información y se registrarán los abonos en caja.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, guardar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0056b3'
    });

    if (!result.isConfirmed) return;

    enviarDatos('guardar');
}

// Entregar Reparación
async function entregarReparacion() {
    const deuda = parseInt(document.getElementById('deuda').value) || 0;
    
    let mensaje = 'Se marcará como entregado.';
    if (deuda > 0) {
        mensaje += ` Se registrará el cobro final de $${deuda} en caja.`;
    }

    const result = await Swal.fire({
        title: '¿Entregar Equipo?',
        text: mensaje,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, entregar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754'
    });

    if (!result.isConfirmed) return;

    enviarDatos('entregar');
}

// Función genérica para enviar a la API
async function enviarDatos(accion) {
    const form = document.getElementById('formEditar');
    const formData = new FormData(form);
    
    // Convertir FormData a objeto plano
    const data = Object.fromEntries(formData.entries());
    data.action = accion;

    try {
        const res = await fetch('/local3M/api/editar_reparacion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const json = await res.json();

        if (json.success) {
            if (accion === 'entregar') {
                Swal.fire({
                    title: '¡Entregado!',
                    text: 'El equipo ha sido entregado correctamente.',
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: 'Imprimir Ticket',
                    cancelButtonText: 'Cerrar'
                }).then((r) => {
                    if (r.isConfirmed) window.open(json.ticketUrl, '_blank');
                    location.reload();
                });
            } else {
                Swal.fire('Guardado', 'Los cambios se han guardado correctamente.', 'success')
                    .then(() => location.reload());
            }
        } else {
            Swal.fire('Error', json.message || 'Ocurrió un error desconocido.', 'error');
        }
    } catch (error) {
        console.error(error);
        Swal.fire('Error', 'Error de conexión con el servidor.', 'error');
    }
}

// Imprimir Ticket
function imprimirTicket() {
    if (TICKET_URL) {
        window.open(TICKET_URL, '_blank');
    } else {
        Swal.fire('Aviso', 'No se pudo generar la URL del ticket.', 'info');
    }
}

// Botones de Código de Barras
document.getElementById('btnCopiar')?.addEventListener('click', () => {
    navigator.clipboard.writeText(CODIGO_BARRAS);
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Copiado', timer: 1500, showConfirmButton: false });
});

document.getElementById('btnImprimirCodigo')?.addEventListener('click', () => {
    const w = window.open('', '_blank', 'width=400,height=300');
    w.document.write(`<html><body style="text-align:center; margin-top:50px;">${document.getElementById('barcode-svg').outerHTML}<br><strong>${CODIGO_BARRAS}</strong><script>window.print();</script></body></html>`);
    w.document.close();
});