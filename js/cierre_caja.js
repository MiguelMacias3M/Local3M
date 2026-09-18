document.addEventListener('DOMContentLoaded', () => {
    verificarEstado();

    // Eventos para el contador de billetes/monedas
    document.querySelectorAll('.den-input').forEach(input => {
        input.addEventListener('input', calcularSumaDinero);
        // Autoseleccionar todo el texto al dar clic para escribir rápido
        input.addEventListener('focus', function() { this.select(); });
    });
});

let saldoTeoricoGlobal = 0;
let idCierreActivo = '';
let totalArqueo = 0;

// 1. Verificar Estado
async function verificarEstado() {
    try {
        const res = await fetch('/local3M/api/cierre_caja.php?action=estado');
        const json = await res.json();
        
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
    document.getElementById('vista_cierre').style.display = 'none';
    document.getElementById('vista_abrir').style.display = 'block';
    
    document.getElementById('saldo_inicial').value = parseFloat(fondo).toFixed(2);
}

function mostrarVistaAbierta(datos) {
    document.getElementById('vista_abrir').style.display = 'none';
    document.getElementById('vista_cierre').style.display = 'grid';

    // Llenar datos sistema (Respetando el filtrado de tu PHP)
    document.getElementById('lbl_inicial').textContent = formatoDinero(datos.saldo_inicial);
    document.getElementById('lbl_ingresos').textContent = '+' + formatoDinero(datos.ingresos);
    document.getElementById('lbl_egresos').textContent = '-' + formatoDinero(datos.egresos);
    document.getElementById('lbl_teorico').textContent = formatoDinero(datos.saldo_teorico);
    
    saldoTeoricoGlobal = parseFloat(datos.saldo_teorico);
    idCierreActivo = datos.id;

    // Limpiar campos
    document.getElementById('saldo_real').value = '';
    document.getElementById('fondo_sig').value = '';
    document.getElementById('notas').value = '';
    calcularCierre();
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
        const res = await fetch('/local3M/api/cierre_caja.php', { method: 'POST', body: formData });
        const json = await res.json();

        if (json.success) {
            Swal.fire({icon:'success', title:'Turno Iniciado', timer:1500, showConfirmButton:false});
            verificarEstado();
        } else {
            Swal.fire('Error', json.error, 'error');
        }
    } catch (e) { Swal.fire('Error', 'Fallo de red', 'error'); }
}

// ==========================================
// 3. MÓDULO CONTADOR DE BILLETES Y MONEDAS
// ==========================================
window.abrirModalContador = function() { document.getElementById('modalContador').style.display = 'flex'; }
window.cerrarModalContador = function() { document.getElementById('modalContador').style.display = 'none'; }

function calcularSumaDinero() {
    totalArqueo = 0;
    document.querySelectorAll('.den-input').forEach(input => {
        const valorFacial = parseFloat(input.getAttribute('data-val'));
        const cantidadBilletes = parseInt(input.value) || 0;
        totalArqueo += (valorFacial * cantidadBilletes);
    });
    document.getElementById('lblTotalContador').innerText = formatoDinero(totalArqueo);
}

window.limpiarContador = function() {
    document.querySelectorAll('.den-input').forEach(i => i.value = '');
    calcularSumaDinero();
};

window.aplicarConteo = function() {
    if(totalArqueo >= 0) {
        document.getElementById('saldo_real').value = totalArqueo.toFixed(2);
        calcularCierre(); // Actualizamos colores y retiro en vivo
        cerrarModalContador();
    }
};

// ==========================================
// 4. CÁLCULO DE DIFERENCIAS (SOBRANTES/FALTANTES)
// ==========================================
window.calcularCierre = function() {
    const real = parseFloat(document.getElementById('saldo_real').value) || 0;
    const fondo = parseFloat(document.getElementById('fondo_sig').value) || 0;

    const diferencia = real - saldoTeoricoGlobal;
    let retiro = real - fondo;
    if(retiro < 0) retiro = 0;

    const elDif = document.getElementById('lbl_diferencia');
    const cajaDif = document.getElementById('cajaDiferencia');

    elDif.innerText = (diferencia >= 0 ? '+' : '') + formatoDinero(diferencia);
    
    // Pintamos de colores si hay faltante o sobrante
    if (diferencia > 0) {
        elDif.style.color = '#34c759'; 
        cajaDif.style.background = 'rgba(52, 199, 89, 0.1)';
        cajaDif.style.border = '1px solid rgba(52, 199, 89, 0.2)';
    } else if (diferencia < 0) {
        elDif.style.color = '#ff3b30'; 
        cajaDif.style.background = 'rgba(255, 59, 48, 0.1)';
        cajaDif.style.border = '1px solid rgba(255, 59, 48, 0.2)';
    } else {
        elDif.style.color = '#1d1d1f'; 
        cajaDif.style.background = 'rgba(0, 0, 0, 0.03)';
        cajaDif.style.border = '1px dashed rgba(0, 0, 0, 0.1)';
    }

    document.getElementById('lbl_retiro').value = retiro.toFixed(2);
};

// ==========================================
// 5. CERRAR CAJA EN PHP
// ==========================================
async function cerrarCaja() {
    const real = document.getElementById('saldo_real').value;
    const fondo = document.getElementById('fondo_sig').value;
    const retiro = parseFloat(document.getElementById('lbl_retiro').value) || 0;
    const notas = document.getElementById('notas').value.trim();

    if (real === '' || fondo === '') {
        Swal.fire('Campos vacíos', 'Debes ingresar cuánto dinero contaste y cuánto dejas de fondo.', 'warning');
        return;
    }

    const diferencia = parseFloat(real) - saldoTeoricoGlobal;

    // Tu regla de negocio: Si hay diferencia, obligar a poner nota
    if (diferencia !== 0 && notas === '') {
        Swal.fire('Diferencia Detectada', 'Tienes un faltante/sobrante. Es obligatorio escribir una Nota explicando la razón.', 'error');
        document.getElementById('notas').focus();
        return;
    }

    const confirm = await Swal.fire({
        title: '¿Cerrar Turno?',
        html: `Retirarás <b>$${retiro.toFixed(2)}</b> de ganancia física.<br>El turno de mañana abrirá con <b>$${parseFloat(fondo).toFixed(2)}</b>.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-lock"></i> Sí, Cerrar',
        confirmButtonColor: '#ff3b30',
        cancelButtonColor: '#8e8e93',
        cancelButtonText: 'Cancelar'
    });

    if (!confirm.isConfirmed) return;

    Swal.fire({title: 'Procesando...', didOpen: () => Swal.showLoading()});

    const formData = new FormData();
    formData.append('action', 'cerrar');
    formData.append('id_cierre', idCierreActivo);
    formData.append('saldo_real', real);
    formData.append('fondo_sig', fondo);
    formData.append('notas', notas);

    try {
        const res = await fetch('/local3M/api/cierre_caja.php', { method: 'POST', body: formData });
        const json = await res.json();

        if (json.success) {
            Swal.fire({icon:'success', title:'Turno Cerrado', text:'Mandando comprobante...', timer:2000, showConfirmButton:false})
            .then(() => {
                window.location.href = 'caja.php';
            });
        } else {
            Swal.fire('Error', json.error, 'error');
        }
    } catch (e) { Swal.fire('Error', 'Fallo de red', 'error'); }
}

function formatoDinero(amount) {
    return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}