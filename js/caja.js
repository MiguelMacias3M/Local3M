document.addEventListener('DOMContentLoaded', () => {
    console.log("✅ CAJA.JS - MODIFICADO PARA ESTRUCTURA SQL REAL");
    cargarUsuarios();
    inicializarFecha(); 
});

const filtroFecha = document.getElementById('filtroFecha');
const filtroUsuario = document.getElementById('filtroUsuario');
const modal = document.getElementById('modalMovimiento');
const form = document.getElementById('formMovimiento');

// ==========================================
// 0. INICIALIZACIÓN DE FECHA
// ==========================================
async function inicializarFecha() {
    if (!filtroFecha) return;
    try {
        const res = await fetch(`api/caja.php?action=fecha_servidor&_t=${Date.now()}`);
        const data = await res.json();
        if (data.success) filtroFecha.value = data.fecha;
    } catch (e) { 
        // Fallback matemático (UTC-6) si falla el servidor
        const now = new Date();
        const offsetMexico = 6 * 60 * 60 * 1000; 
        const fechaRestada = new Date(now.getTime() - offsetMexico);
        filtroFecha.value = fechaRestada.toISOString().split('T')[0];
    }
    cargarReporte();
}

// ==========================================
// 1. CARGA DE REPORTES Y DATOS
// ==========================================
async function cargarReporte() {
    const fecha = filtroFecha.value;
    const usuario = filtroUsuario.value;
    if (!fecha) return;

    try {
        const res = await fetch(`api/caja.php?action=reporte_dia&fecha=${fecha}&usuario=${usuario}&_t=${Date.now()}`);
        const json = await res.json();

        if (json.success) {
            // Actualizar KPIs
            document.getElementById('valIngresos').textContent = formatoDinero(json.totales.ingreso);
            document.getElementById('valEgresos').textContent = formatoDinero(json.totales.egreso);
            document.getElementById('valNeto').textContent = formatoDinero(json.totales.neto);
            
            // Actualizar Estado
            actualizarEstadoCaja(json.estado_caja);
            // Llenar Tabla
            llenarTabla(json.movimientos);
        }
    } catch (e) { console.error(e); }
}

function actualizarEstadoCaja(estado) {
    const lblEstado = document.getElementById('lblEstadoCaja');
    const lblMonto = document.getElementById('lblMontoActual');
    const lblDetalle = document.getElementById('lblDetalleCaja');
    const btn = document.getElementById('btnCorteCaja');
    if (!lblEstado) return; 

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
        // Lógica de montos basada en columnas numéricas
        const valIngreso = parseFloat(m.ingreso);
        const valEgreso = parseFloat(m.egreso);
        
        // Si hay valor en ingreso, es dinero que entra (verde)
        const esEntrada = valIngreso > 0;
        const monto = esEntrada ? valIngreso : valEgreso;
        const claseMonto = esEntrada ? 'monto-ingreso' : 'monto-egreso';
        const signo = esEntrada ? '+' : '-';
        
        // Formatear hora
        let hora = m.fecha.split(' ')[1] || '--:--';
        try { 
            // Reemplazo para compatibilidad Safari
            const d = new Date(m.fecha.replace(/-/g, '/'));
            hora = d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}); 
        } catch(e){}

        // --- LÓGICA DE ETIQUETAS BASADA EN TU BASE DE DATOS ---
        // Tu BD usa la columna 'tipo' para definir el origen real
        const tipoDB = (m.tipo || '').toUpperCase();
        
        let etiqueta = tipoDB; // Fallback
        let claseBadge = 'badge-secondary';

        switch(tipoDB) {
            case 'REPARACION':
                etiqueta = 'Reparación';
                claseBadge = 'badge-warning'; // Naranja/Amarillo
                break;
            case 'VENTA':
                etiqueta = 'Venta';
                claseBadge = 'badge-primary'; // Azul
                break;
            case 'INGRESO':
                // Si es un ingreso manual, mostramos la categoría (ej: "Ingreso Extra")
                // Si la categoría es genérica ("General"), mostramos "Ingreso Extra"
                if (m.categoria && m.categoria !== 'General') {
                    etiqueta = m.categoria;
                } else {
                    etiqueta = 'Ingreso Extra';
                }
                claseBadge = 'badge-success'; // Verde
                break;
            case 'GASTO':
            case 'EGRESO':
                etiqueta = 'Gasto';
                if (m.categoria) etiqueta = m.categoria; // Ej: "Alimentos"
                claseBadge = 'badge-danger'; // Rojo
                break;
            case 'RETIRO':
                etiqueta = 'Retiro';
                claseBadge = 'badge-dark'; // Negro/Gris
                break;
            default:
                // Si hay un tipo desconocido, usamos colores genéricos
                claseBadge = esEntrada ? 'badge-info' : 'badge-secondary';
        }

        const badgeHtml = `<span class="badge ${claseBadge}">${etiqueta}</span>`;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${hora}</td>
            <td>${badgeHtml}</td>
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
    document.getElementById('modalTitle').textContent = 'Registrar Gasto';
    document.getElementById('tipoMovimiento').value = 'GASTO';
    const btn = document.querySelector('#formMovimiento button[type="submit"]');
    if(btn) { btn.className = 'form-button btn-danger'; btn.innerHTML = 'Registrar Gasto'; }
    if(modal) modal.style.display = 'flex';
}

function abrirModalIngreso() {
    if(form) form.reset();
    document.getElementById('modalTitle').textContent = 'Registrar Ingreso Extra';
    document.getElementById('tipoMovimiento').value = 'INGRESO';
    // Forzamos categoría para que el switch del JS lo detecte bonito
    const cat = document.getElementById('categoria'); if(cat) cat.value = 'Ingreso Extra';
    const btn = document.querySelector('#formMovimiento button[type="submit"]');
    if(btn) { btn.className = 'form-button btn-success'; btn.innerHTML = 'Registrar Ingreso'; }
    if(modal) modal.style.display = 'flex';
}

function cerrarModal() { if(modal) modal.style.display = 'none'; }

if (form) {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        fd.append('action', 'registrar_movimiento');
        try {
            const res = await fetch('api/caja.php', { method:'POST', body:fd });
            const json = await res.json();
            if(json.success) { 
                Swal.fire({icon:'success', title:'Guardado', timer:1000, showConfirmButton:false});
                cerrarModal(); cargarReporte();
            } else { Swal.fire('Error', json.error, 'error'); }
        } catch(err) { Swal.fire('Error','Fallo conexión','error'); }
    });
}

// ==========================================
// 3. UTILIDADES
// ==========================================
async function cargarUsuarios() {
    try{
        const r = await fetch(`api/caja.php?action=usuarios&_t=${Date.now()}`); 
        const d = await r.json();
        if(d.success && document.getElementById('filtroUsuario')) {
            const sel = document.getElementById('filtroUsuario');
            // Limpiamos excepto la opción "Todos"
            // sel.innerHTML = '<option value="Todos">Todos</option>'; 
            d.data.forEach(u=>{
                const o = document.createElement('option'); o.value=u; o.textContent=u;
                sel.appendChild(o);
            });
        }
    }catch(e){}
}

function formatoDinero(a) { 
    return '$' + parseFloat(a).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'); 
}

function gestionarCaja() { window.location.href = 'cierre_caja.php'; }

const btnExp = document.getElementById('btnExportar');
if(btnExp) btnExp.addEventListener('click', ()=>{ 
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
    downloadLink.download = `Reporte_Caja_${filtroFecha.value}.csv`;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
});