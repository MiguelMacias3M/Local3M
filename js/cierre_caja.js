document.addEventListener('DOMContentLoaded', () => {
    verificarEstado();
});

let saldoTeoricoGlobal = 0;

// 1. Verificar Estado
async function verificarEstado() {
    try {
        const res = await fetch('/api/cierre_caja.php?action=estado');
        const json = await res.json();
        
        document.getElementById('loader').style.display = 'none';

        if (json.success) {
            if (json.estado === 'ABIERTA') {
                mostrarVistaAbierta(json.datos);
            } else {
                mostrarVistaCerrada(json.fondo_sugerido);
            }
        }
    } catch (e) {
        Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
    }
}

function mostrarVistaCerrada(fondo) {
    document.getElementById('viewCerrada').style.display = 'block';
    document.getElementById('viewAbierta').style.display = 'none';
    
    document.getElementById('saldo_inicial').value = parseFloat(fondo).toFixed(2);
    document.getElementById('fondoSugerido').textContent = formatoDinero(fondo);
}

function mostrarVistaAbierta(datos) {
    document.getElementById('viewCerrada').style.display = 'none';
    document.getElementById('viewAbierta').style.display = 'grid';

    // Llenar datos sistema
    document.getElementById('fechaApertura').textContent = datos.fecha_apertura;
    document.getElementById('sysInicial').textContent = formatoDinero(datos.saldo_inicial);
    document.getElementById('sysIngresos').textContent = '+ ' + formatoDinero(datos.ingresos);
    document.getElementById('sysEgresos').textContent = '- ' + formatoDinero(datos.egresos);
    document.getElementById('sysTeorico').textContent = formatoDinero(datos.saldo_teorico);
    
    // Guardar para cálculos
    saldoTeoricoGlobal = parseFloat(datos.saldo_teorico);
    document.getElementById('id_cierre').value = datos.id;

    calcularCierre(); // Inicializar cálculos
}

// 2. Abrir Caja
async function abrirCaja() {
    const inicial = document.getElementById('saldo_inicial').value;
    
    if (inicial === '' || parseFloat(inicial) < 0) {
        Swal.fire('Error', 'Ingresa un fondo inicial válido', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'abrir');
    formData.append('saldo_inicial', inicial);

    try {
        const res = await fetch('/api/cierre_caja.php', { method: 'POST', body: formData });
        const json = await res.json();

        if (json.success) {
            Swal.fire({icon:'success', title:'Turno Iniciado', timer:1500, showConfirmButton:false});
            verificarEstado();
        } else {
            Swal.fire('Error', json.error, 'error');
        }
    } catch (e) { Swal.fire('Error', 'Fallo de red', 'error'); }
}

// 3. Cálculos en tiempo real
function calcularCierre() {
    const real = parseFloat(document.getElementById('saldo_real').value) || 0;
    const fondo = parseFloat(document.getElementById('fondo_sig').value) || 0;

    const diferencia = real - saldoTeoricoGlobal;
    const retiro = real - fondo;

    const elDif = document.getElementById('resDiferencia');
    elDif.textContent = formatoDinero(diferencia);
    elDif.className = diferencia >= -0.01 ? 'fw-bold text-success' : 'fw-bold text-danger';

    document.getElementById('resRetiro').textContent = formatoDinero(retiro);
}

// 4. Cerrar Caja
async function cerrarCaja() {
    const real = document.getElementById('saldo_real').value;
    const fondo = document.getElementById('fondo_sig').value;

    if (real === '' || fondo === '') {
        Swal.fire('Campos vacíos', 'Debes contar el dinero y definir el fondo.', 'warning');
        return;
    }

    const confirm = await Swal.fire({
        title: '¿Cerrar Turno?',
        text: 'Se registrará el corte y el retiro de ganancias.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cerrar',
        confirmButtonColor: '#dc3545'
    });

    if (!confirm.isConfirmed) return;

    const formData = new FormData();
    formData.append('action', 'cerrar');
    formData.append('id_cierre', document.getElementById('id_cierre').value);
    formData.append('saldo_real', real);
    formData.append('fondo_sig', fondo);
    formData.append('notas', document.getElementById('notas').value);

    try {
        const res = await fetch('/api/cierre_caja.php', { method: 'POST', body: formData });
        const json = await res.json();

        if (json.success) {
            Swal.fire({icon:'success', title:'Caja Cerrada', text:'El turno ha finalizado.', timer:2000});
            verificarEstado(); // Volverá a mostrar la vista de apertura
        } else {
            Swal.fire('Error', json.error, 'error');
        }
    } catch (e) { Swal.fire('Error', 'Fallo de red', 'error'); }
}

function formatoDinero(amount) {
    return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}