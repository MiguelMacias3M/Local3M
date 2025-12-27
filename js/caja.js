document.addEventListener('DOMContentLoaded', () => {
    cargarUsuarios();
    cargarReporte();
});

// Referencias a elementos del DOM
const filtroFecha = document.getElementById('filtroFecha');
const filtroUsuario = document.getElementById('filtroUsuario');
const modal = document.getElementById('modalMovimiento');
const form = document.getElementById('formMovimiento');

// ==========================================
// 1. CARGA DE REPORTES Y DATOS
// ==========================================

async function cargarReporte() {
    const fecha = filtroFecha.value;
    const usuario = filtroUsuario.value;

    try {
        // Petición a la API para obtener totales y movimientos
        const res = await fetch(`/local3M/api/caja.php?action=reporte_dia&fecha=${fecha}&usuario=${usuario}`);
        const json = await res.json();

        if (json.success) {
            // Actualizar KPIs (Indicadores)
            document.getElementById('valIngresos').textContent = formatoDinero(json.totales.ingreso);
            document.getElementById('valEgresos').textContent = formatoDinero(json.totales.egreso);
            document.getElementById('valNeto').textContent = formatoDinero(json.totales.neto);

            // Actualizar Estado Caja (Tarjeta superior)
            actualizarEstadoCaja(json.estado_caja);

            // Llenar Tabla de movimientos
            llenarTabla(json.movimientos);
        }
    } catch (e) {
        console.error("Error cargando reporte:", e);
    }
}

function actualizarEstadoCaja(estado) {
    const lblEstado = document.getElementById('lblEstadoCaja');
    const lblMonto = document.getElementById('lblMontoActual');
    const lblDetalle = document.getElementById('lblDetalleCaja');
    const btn = document.getElementById('btnCorteCaja');

    if (estado.estado === 'ABIERTA') {
        lblEstado.textContent = 'CAJA ABIERTA';
        lblEstado.style.color = '#28a745'; // Verde
        lblMonto.textContent = formatoDinero(estado.monto_actual);
        lblDetalle.textContent = `Por: ${estado.usuario}`;
        
        btn.innerHTML = '<i class="fas fa-lock"></i> Realizar Corte';
        btn.onclick = () => window.location.href = 'cierre_caja.php';
    } else {
        lblEstado.textContent = 'CAJA CERRADA';
        lblEstado.style.color = '#dc3545'; // Rojo
        lblMonto.textContent = '$0.00';
        lblDetalle.textContent = 'Sin turno activo';
        
        btn.innerHTML = '<i class="fas fa-key"></i> Abrir Turno';
        btn.onclick = () => window.location.href = 'cierre_caja.php';
    }
}

function llenarTabla(movs) {
    const tbody = document.getElementById('tablaBody');
    tbody.innerHTML = '';

    if (movs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding:20px;">No hay movimientos registrados hoy.</td></tr>';
        return;
    }

    movs.forEach(m => {
        const esIngreso = parseFloat(m.ingreso) > 0;
        const monto = esIngreso ? m.ingreso : m.egreso;
        const claseMonto = esIngreso ? 'monto-ingreso' : 'monto-egreso';
        const signo = esIngreso ? '+' : '-';
        
        // Formatear fecha y hora
        const fechaObj = new Date(m.fecha);
        const hora = fechaObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${hora}</td>
            <td><span class="badge badge-secondary">${m.tipo}</span></td>
            <td>${m.descripcion}</td>
            <td>${m.categoria || '-'}</td>
            <td>${m.usuario}</td>
            <td class="text-right ${claseMonto}">${signo}${formatoDinero(monto)}</td>
        `;
        tbody.appendChild(tr);
    });
}

// ==========================================
// 2. LÓGICA DE BOTONES (GASTO / INGRESO)
// ==========================================

// Función para ABRIR MODAL DE GASTO
function abrirModalGasto() {
    form.reset();
    document.getElementById('modalTitle').textContent = 'Registrar Gasto (Salida)';
    // Establecemos el tipo oculto como GASTO
    document.getElementById('tipoMovimiento').value = 'GASTO';
    
    // Personalizamos el botón de guardar (Rojo para gastos)
    const btnGuardar = document.querySelector('#formMovimiento button[type="submit"]');
    if(btnGuardar) {
        btnGuardar.className = 'form-button btn-danger';
        btnGuardar.innerHTML = '<i class="fas fa-save"></i> Registrar Gasto';
    }
    
    modal.style.display = 'flex';
}

// Función para ABRIR MODAL DE INGRESO
function abrirModalIngreso() {
    form.reset();
    document.getElementById('modalTitle').textContent = 'Registrar Ingreso Extra';
    // Establecemos el tipo oculto como INGRESO
    document.getElementById('tipoMovimiento').value = 'INGRESO';
    
    // Personalizamos el botón de guardar (Verde para ingresos)
    const btnGuardar = document.querySelector('#formMovimiento button[type="submit"]');
    if(btnGuardar) {
        btnGuardar.className = 'form-button btn-success';
        btnGuardar.innerHTML = '<i class="fas fa-save"></i> Registrar Ingreso';
    }

    modal.style.display = 'flex';
}

function cerrarModal() {
    modal.style.display = 'none';
}

// Función para GUARDAR EL MOVIMIENTO (Llamada al API)
async function guardarMovimiento() {
    const descripcion = document.getElementById('descripcion').value.trim();
    const monto = document.getElementById('monto').value;

    // Validación simple
    if (!descripcion || !monto || parseFloat(monto) <= 0) {
        Swal.fire('Datos incompletos', 'Por favor ingresa una descripción y un monto válido.', 'warning');
        return;
    }

    const formData = new FormData(form);
    formData.append('action', 'registrar_movimiento');

    try {
        const res = await fetch('/local3M/api/caja.php', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        if (json.success) {
            Swal.fire({
                icon: 'success',
                title: 'Movimiento Registrado',
                showConfirmButton: false,
                timer: 1500
            });
            cerrarModal();
            cargarReporte(); // Recargar la tabla y los totales para ver el cambio
        } else {
            Swal.fire('Error', json.error || 'No se pudo guardar el movimiento', 'error');
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'Fallo de conexión con el servidor', 'error');
    }
}

// ==========================================
// 3. UTILIDADES
// ==========================================

async function cargarUsuarios() {
    try {
        const res = await fetch('/local3M/api/caja.php?action=usuarios');
        const json = await res.json();
        if (json.success) {
            const select = document.getElementById('filtroUsuario');
            json.data.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u;
                opt.textContent = u;
                select.appendChild(opt);
            });
        }
    } catch (e) {}
}

function formatoDinero(amount) {
    return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Exportar Tabla a CSV
const btnExportar = document.getElementById('btnExportar');
if(btnExportar) {
    btnExportar.addEventListener('click', () => {
        let csv = [];
        const rows = document.querySelectorAll("#tablaCaja tr");
        
        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll("td, th");
            for (let j = 0; j < cols.length; j++) 
                row.push('"' + cols[j].innerText + '"');
            csv.push(row.join(","));        
        }

        const csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
        const downloadLink = document.createElement("a");
        downloadLink.download = `Reporte_Caja_${new Date().toLocaleDateString()}.csv`;
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = "none";
        document.body.appendChild(downloadLink);
        downloadLink.click();
    });
}

// Redirigir a gestión de caja
function gestionarCaja() {
    window.location.href = 'cierre_caja.php';
}