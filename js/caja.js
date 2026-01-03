document.addEventListener('DOMContentLoaded', () => {
    cargarUsuarios();
    inicializarFecha(); // Primero ajustamos la fecha, luego cargamos el reporte
});

// Referencias a elementos del DOM
const filtroFecha = document.getElementById('filtroFecha');
const filtroUsuario = document.getElementById('filtroUsuario');
const modal = document.getElementById('modalMovimiento');
const form = document.getElementById('formMovimiento');

// ==========================================
// 0. INICIALIZACIÓN DE FECHA (SOLUCIÓN ZONA HORARIA)
// ==========================================

async function inicializarFecha() {
    // Si no existe el input, no hacemos nada
    if (!filtroFecha) return;

    try {
        // Opción A: Preguntar al servidor qué día es (Lo más seguro)
        // Usamos ruta relativa 'api/caja.php' para que funcione en cualquier carpeta
        const res = await fetch('api/caja.php?action=fecha_servidor');
        const data = await res.json();

        if (data.success && data.fecha) {
            filtroFecha.value = data.fecha;
            console.log("📅 Fecha sincronizada con servidor (MX):", data.fecha);
        } else {
            throw new Error("No se obtuvo fecha del servidor");
        }
    } catch (e) {
        // Opción B (Fallback): Calcular en el navegador forzando zona horaria de México
        console.warn("⚠️ Usando fecha local forzada a MX (Fallback)");
        const hoyMexico = new Date().toLocaleDateString('en-CA', {
            timeZone: 'America/Mexico_City',
            year: 'numeric', month: '2-digit', day: '2-digit'
        });
        filtroFecha.value = hoyMexico;
    }

    // UNA VEZ PUESTA LA FECHA CORRECTA, CARGAMOS EL REPORTE
    cargarReporte();
}

// ==========================================
// 1. CARGA DE REPORTES Y DATOS
// ==========================================

async function cargarReporte() {
    const fecha = filtroFecha.value;
    const usuario = filtroUsuario.value;

    // Si por alguna razón la fecha está vacía, no cargamos para evitar errores
    if (!fecha) return;

    try {
        // Petición a la API para obtener totales y movimientos
        const res = await fetch(`api/caja.php?action=reporte_dia&fecha=${fecha}&usuario=${usuario}`);
        const json = await res.json();

        if (json.success) {
            // Actualizar KPIs
            document.getElementById('valIngresos').textContent = formatoDinero(json.totales.ingreso);
            document.getElementById('valEgresos').textContent = formatoDinero(json.totales.egreso);
            document.getElementById('valNeto').textContent = formatoDinero(json.totales.neto);

            // Actualizar Estado Caja
            actualizarEstadoCaja(json.estado_caja);

            // Llenar Tabla
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

    if (!lblEstado || !lblMonto) return; // Evitar errores si no existen los elementos

    if (estado.estado === 'ABIERTA') {
        lblEstado.textContent = 'CAJA ABIERTA';
        lblEstado.style.color = '#28a745';
        lblMonto.textContent = formatoDinero(estado.monto_actual);
        lblDetalle.textContent = `Por: ${estado.usuario}`;
        
        if(btn) {
            btn.innerHTML = '<i class="fas fa-lock"></i> Realizar Corte';
            btn.onclick = () => window.location.href = 'cierre_caja.php';
        }
    } else {
        lblEstado.textContent = 'CAJA CERRADA';
        lblEstado.style.color = '#dc3545';
        lblMonto.textContent = '$0.00';
        lblDetalle.textContent = 'Sin turno activo';
        
        if(btn) {
            btn.innerHTML = '<i class="fas fa-key"></i> Abrir Turno';
            btn.onclick = () => window.location.href = 'cierre_caja.php';
        }
    }
}

function llenarTabla(movs) {
    const tbody = document.getElementById('tablaBody');
    if (!tbody) return;
    
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
        
        // Formatear hora de forma segura
        let hora = '';
        try {
            // Reemplazar guiones por barras para compatibilidad con Safari/iOS si fuera necesario
            const fechaSafe = m.fecha.replace(/-/g, '/'); 
            const fechaObj = new Date(fechaSafe);
            hora = fechaObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        } catch (e) {
            hora = m.fecha.split(' ')[1] || '--:--'; // Fallback simple
        }

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
// 2. GESTIÓN DE MODALES
// ==========================================

function abrirModalGasto() {
    if(form) form.reset();
    document.getElementById('modalTitle').textContent = 'Registrar Gasto (Salida)';
    document.getElementById('tipoMovimiento').value = 'GASTO';
    
    const btnGuardar = document.querySelector('#formMovimiento button[type="submit"]');
    if(btnGuardar) {
        btnGuardar.className = 'form-button btn-danger';
        btnGuardar.innerHTML = '<i class="fas fa-save"></i> Registrar Gasto';
    }
    
    if(modal) modal.style.display = 'flex';
}

function abrirModalIngreso() {
    if(form) form.reset();
    document.getElementById('modalTitle').textContent = 'Registrar Ingreso Extra';
    document.getElementById('tipoMovimiento').value = 'INGRESO';
    
    const btnGuardar = document.querySelector('#formMovimiento button[type="submit"]');
    if(btnGuardar) {
        btnGuardar.className = 'form-button btn-success';
        btnGuardar.innerHTML = '<i class="fas fa-save"></i> Registrar Ingreso';
    }

    if(modal) modal.style.display = 'flex';
}

function cerrarModal() {
    if(modal) modal.style.display = 'none';
}

// Asignar el evento submit al formulario para prevenir recarga
if (form) {
    form.addEventListener('submit', (e) => {
        e.preventDefault(); // Evita que la página se recargue
        guardarMovimiento();
    });
}

async function guardarMovimiento() {
    const descripcion = document.getElementById('descripcion').value.trim();
    const monto = document.getElementById('monto').value;

    if (!descripcion || !monto || parseFloat(monto) <= 0) {
        Swal.fire('Datos incompletos', 'Por favor ingresa una descripción y un monto válido.', 'warning');
        return;
    }

    const formData = new FormData(form);
    formData.append('action', 'registrar_movimiento');

    try {
        const res = await fetch('api/caja.php', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        if (json.success) {
            Swal.fire({ icon: 'success', title: 'Registrado', timer: 1000, showConfirmButton: false });
            cerrarModal();
            cargarReporte(); 
        } else {
            Swal.fire('Error', json.error || 'Error desconocido', 'error');
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'Fallo de conexión', 'error');
    }
}

// ==========================================
// 3. UTILIDADES
// ==========================================

async function cargarUsuarios() {
    try {
        const res = await fetch('api/caja.php?action=usuarios');
        const json = await res.json();
        if (json.success) {
            const select = document.getElementById('filtroUsuario');
            if(select) {
                // Limpiar opciones previas excepto la primera si es "Todos"
                // select.innerHTML = '<option value="Todos">Todos</option>'; 
                json.data.forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u;
                    opt.textContent = u;
                    select.appendChild(opt);
                });
            }
        }
    } catch (e) {}
}

function formatoDinero(amount) {
    return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Exportar CSV Simple
const btnExportar = document.getElementById('btnExportar');
if(btnExportar) {
    btnExportar.addEventListener('click', () => {
        let csv = [];
        // Asegúrate de que tu tabla tenga el ID correcto, si es 'tablaCaja' o la tabla que contiene 'tablaBody'
        // Si la tabla tiene id="tablaCaja", esto funciona:
        const rows = document.querySelectorAll("table tr"); 
        
        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll("td, th");
            // Ocultar columnas que no queramos si es necesario
            for (let j = 0; j < cols.length; j++) 
                row.push('"' + cols[j].innerText + '"');
            csv.push(row.join(","));        
        }

        const csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
        const downloadLink = document.createElement("a");
        downloadLink.download = `Reporte_Caja_${filtroFecha.value}.csv`;
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = "none";
        document.body.appendChild(downloadLink);
        downloadLink.click();
    });
}

function gestionarCaja() {
    window.location.href = 'cierre_caja.php';
}