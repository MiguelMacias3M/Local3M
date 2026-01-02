document.addEventListener('DOMContentLoaded', () => {
    cargarUsuarios();
    cargarReporte(); // Carga con fecha de hoy por defecto
});

// Referencias al DOM
const filtroFechaInicio = document.getElementById('fechaInicio');
const filtroFechaFin = document.getElementById('fechaFin');
const filtroUsuario = document.getElementById('filtroUsuario');
const modal = document.getElementById('modalMovimiento');
const form = document.getElementById('formMovimiento');

// Variables globales para exportar lo que se ve en pantalla
let datosMovimientos = [];
let datosTotales = {};

// ==========================================
// 1. MANEJO DE FECHAS (Periodos)
// ==========================================
function cambiarPeriodo() {
    const periodo = document.getElementById('filtroPeriodo').value;
    const hoy = new Date();
    let inicio = new Date();
    let fin = new Date();

    // Deshabilitar inputs si no es personalizado
    if (periodo !== 'personalizado') {
        filtroFechaInicio.disabled = false; // Dejamos editables por si acaso, o true si prefieres bloquear
        filtroFechaFin.disabled = false;
    }

    if (periodo === 'dia') {
        // Hoy: inicio y fin son hoy
    } else if (periodo === 'ayer') {
        inicio.setDate(hoy.getDate() - 1);
        fin.setDate(hoy.getDate() - 1);
    } else if (periodo === 'semana') {
        // Lunes de esta semana
        const diaSemana = hoy.getDay() || 7; // 1 (Lunes) a 7 (Domingo)
        // Restamos días para llegar al lunes
        inicio.setDate(hoy.getDate() - (diaSemana - 1));
        // Fin es hoy (o el domingo si prefieres semana completa)
    } else if (periodo === 'mes') {
        inicio.setDate(1); // Día 1 del mes actual
        // Fin es hoy
    } else {
        // Personalizado: No cambiamos las fechas, el usuario elige
        return; 
    }

    // Formatear a YYYY-MM-DD para los inputs HTML
    filtroFechaInicio.value = inicio.toISOString().split('T')[0];
    filtroFechaFin.value = fin.toISOString().split('T')[0];
    
    // Recargar automáticamente al cambiar el combo
    if (periodo !== 'personalizado') {
        cargarReporte();
    }
}

// ==========================================
// 2. CARGA DE REPORTES (API)
// ==========================================

async function cargarReporte() {
    const inicio = filtroFechaInicio.value;
    const fin = filtroFechaFin.value;
    const usuario = filtroUsuario.value;

    try {
        const res = await fetch(`/local3M/api/caja.php?action=reporte_rango&inicio=${inicio}&fin=${fin}&usuario=${usuario}`);
        const json = await res.json();

        if (json.success) {
            datosTotales = json.totales;
            datosMovimientos = json.movimientos;

            // Actualizar KPIs
            document.getElementById('valIngresos').textContent = formatoDinero(datosTotales.ingreso);
            document.getElementById('valEgresos').textContent = formatoDinero(datosTotales.egreso);
            document.getElementById('valNeto').textContent = formatoDinero(datosTotales.neto);

            // Actualizar Estado Caja (Tarjeta superior)
            actualizarEstadoCaja(json.estado_caja);

            // Llenar Tabla
            llenarTabla(datosMovimientos);
        } else {
            console.error("Error API:", json.error);
        }
    } catch (e) { console.error("Error de conexión:", e); }
}

function llenarTabla(movs) {
    const tbody = document.getElementById('tablaBody');
    tbody.innerHTML = '';

    if (movs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding:20px; color:#666;">No hay movimientos en este periodo.</td></tr>';
        return;
    }

    movs.forEach(m => {
        const esIngreso = parseFloat(m.ingreso) > 0;
        const monto = esIngreso ? m.ingreso : m.egreso;
        const claseMonto = esIngreso ? 'monto-ingreso' : 'monto-egreso';
        const signo = esIngreso ? '+' : '-';
        
        // Formato de fecha legible
        const fechaObj = new Date(m.fecha);
        // Ajuste zona horaria si es necesario o confiar en el string del servidor
        // Usamos toLocaleString para mostrar fecha y hora
        const fechaHora = fechaObj.toLocaleString('es-MX', {
            year: '2-digit', month:'2-digit', day:'2-digit', 
            hour:'2-digit', minute:'2-digit'
        });

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${fechaHora}</td>
            <td><span class="badge badge-secondary">${m.tipo}</span></td>
            <td>${m.descripcion}</td>
            <td>${m.categoria || '-'}</td>
            <td>${m.usuario}</td>
            <td class="text-right ${claseMonto}">${signo}${formatoDinero(monto)}</td>
        `;
        tbody.appendChild(tr);
    });
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
        btn.className = 'form-button btn-primary'; // Asegurar estilo
        btn.onclick = () => window.location.href = 'cierre_caja.php';
    } else {
        lblEstado.textContent = 'CAJA CERRADA';
        lblEstado.style.color = '#dc3545'; // Rojo
        lblMonto.textContent = '$0.00';
        lblDetalle.textContent = 'Sin turno activo';
        
        btn.innerHTML = '<i class="fas fa-key"></i> Abrir Turno';
        btn.className = 'form-button btn-success'; // Cambiar a verde para abrir
        btn.onclick = () => window.location.href = 'cierre_caja.php';
    }
}

// ==========================================
// 3. EXPORTAR A EXCEL (SheetJS)
// ==========================================
function exportarExcel() {
    if (!datosMovimientos || datosMovimientos.length === 0) {
        Swal.fire('Sin datos', 'No hay movimientos para exportar.', 'info');
        return;
    }

    // 1. Preparar datos (Mapeo limpio)
    const datosExcel = datosMovimientos.map(m => {
        const esIngreso = parseFloat(m.ingreso) > 0;
        return {
            "Fecha": m.fecha,
            "Tipo": m.tipo,
            "Descripción": m.descripcion,
            "Categoría": m.categoria || '-',
            "Usuario": m.usuario,
            "Ingreso": esIngreso ? parseFloat(m.ingreso) : 0,
            "Egreso": esIngreso ? 0 : parseFloat(m.egreso)
        };
    });

    // 2. Agregar fila de totales al final
    datosExcel.push({
        "Fecha": "TOTALES",
        "Tipo": "", "Descripción": "", "Categoría": "", "Usuario": "",
        "Ingreso": parseFloat(datosTotales.ingreso),
        "Egreso": parseFloat(datosTotales.egreso)
    });
    // Agregar fila de balance
    datosExcel.push({
        "Fecha": "BALANCE NETO",
        "Tipo": "", "Descripción": "", "Categoría": "", "Usuario": "",
        "Ingreso": "",
        "Egreso": parseFloat(datosTotales.neto)
    });

    // 3. Crear Libro y Hoja
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.json_to_sheet(datosExcel);

    // Ajustar anchos de columna (Opcional pero recomendado)
    const wscols = [
        {wch: 20}, // Fecha
        {wch: 10}, // Tipo
        {wch: 40}, // Descripción
        {wch: 15}, // Categoría
        {wch: 15}, // Usuario
        {wch: 12}, // Ingreso
        {wch: 12}  // Egreso
    ];
    ws['!cols'] = wscols;

    XLSX.utils.book_append_sheet(wb, ws, "Reporte Caja");

    // 4. Descargar archivo
    const fechaNombre = filtroFechaInicio.value === filtroFechaFin.value 
                        ? filtroFechaInicio.value 
                        : `${filtroFechaInicio.value}_al_${filtroFechaFin.value}`;
    
    XLSX.writeFile(wb, `Reporte_Caja_3M_${fechaNombre}.xlsx`);
}

// ==========================================
// 4. GESTIÓN DE MODALES
// ==========================================
function abrirModalGasto() {
    form.reset();
    document.getElementById('modalTitle').textContent = 'Registrar Gasto';
    document.getElementById('tipoMovimiento').value = 'GASTO';
    
    // Botón rojo
    const btnSubmit = document.querySelector('#formMovimiento button[type="submit"]');
    btnSubmit.className = 'form-button btn-danger';
    btnSubmit.textContent = 'Registrar Gasto';
    
    modal.style.display = 'flex';
}

function abrirModalIngreso() {
    form.reset();
    document.getElementById('modalTitle').textContent = 'Registrar Ingreso';
    document.getElementById('tipoMovimiento').value = 'INGRESO';
    
    // Botón verde
    const btnSubmit = document.querySelector('#formMovimiento button[type="submit"]');
    btnSubmit.className = 'form-button btn-success';
    btnSubmit.textContent = 'Registrar Ingreso';
    
    modal.style.display = 'flex';
}

function cerrarModal() {
    modal.style.display = 'none';
}

async function guardarMovimiento() {
    const descripcion = document.getElementById('descripcion').value.trim();
    const monto = document.getElementById('monto').value;

    if (!descripcion || !monto || parseFloat(monto) <= 0) {
        Swal.fire('Datos incompletos', 'Revisa descripción y monto.', 'warning');
        return;
    }

    const formData = new FormData(form);
    formData.append('action', 'registrar_movimiento');

    try {
        const res = await fetch('/local3M/api/caja.php', { method: 'POST', body: formData });
        const json = await res.json();
        
        if (json.success) {
            Swal.fire({ icon: 'success', title: 'Registrado', timer: 1000, showConfirmButton: false });
            cerrarModal();
            cargarReporte(); // Recargar datos
        } else {
            Swal.fire('Error', json.error || 'Error desconocido', 'error');
        }
    } catch (e) { Swal.fire('Error', 'Fallo de conexión', 'error'); }
}

// ==========================================
// 5. UTILIDADES
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

function gestionarCaja() {
    window.location.href = 'cierre_caja.php';
}