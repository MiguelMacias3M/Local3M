document.addEventListener('DOMContentLoaded', () => {
    inicializarFecha(); 
});

const filtroFecha = document.getElementById('filtroFecha');
const modal = document.getElementById('modalMovimiento');
const form = document.getElementById('formMovimiento');

function inicializarFecha() {
    if (!filtroFecha) return;
    const fechaMexico = new Date().toLocaleDateString('en-CA', {
        timeZone: 'America/Mexico_City',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });
    filtroFecha.value = fechaMexico;
    cargarReporte();
}

async function cargarReporte() {
    const fecha = filtroFecha.value;
    if (!fecha) return;

    // YA NO SE MANDA EL USUARIO, PORQUE YA NO EXISTE EL FILTRO
    try {
        const res = await fetch(`api/caja.php?action=reporte_dia&fecha=${fecha}&usuario=Todos&_t=${Date.now()}`);
        const json = await res.json();

        if (json.success) {
            document.getElementById('valIngresos').textContent = formatoDinero(json.totales.ingreso);
            document.getElementById('valEgresos').textContent = formatoDinero(json.totales.egreso);
            document.getElementById('valNeto').textContent = formatoDinero(json.totales.neto);

            actualizarEstadoCaja(json.estado_caja);
            llenarTabla(json.movimientos);
        }
    } catch (e) { console.error("Error cargando reporte:", e); }
}

function actualizarEstadoCaja(estado) {
    const lblEstado = document.getElementById('lblEstadoCaja');
    const lblMonto = document.getElementById('lblMontoActual');
    const lblDetalle = document.getElementById('lblDetalleCaja');
    const btn = document.getElementById('btnCorteCaja');

    if (!lblEstado || !lblMonto) return; 

    if (estado.estado === 'ABIERTA') {
        lblEstado.textContent = 'TURNO ACTIVO';
        lblEstado.style.color = '#34c759';
        lblMonto.textContent = formatoDinero(estado.monto_actual);
        lblDetalle.textContent = `Operado por: ${estado.usuario}`;
        
        if(btn) {
            btn.innerHTML = '<i class="fas fa-lock"></i> Hacer Corte';
            btn.className = 'glass-btn primary';
            btn.onclick = () => window.location.href = 'cierre_caja.php';
        }
    } else {
        lblEstado.textContent = 'CAJA CERRADA';
        lblEstado.style.color = '#ff3b30';
        lblMonto.textContent = '$0.00';
        lblDetalle.textContent = 'Sin operaciones activas';
        
        if(btn) {
            btn.innerHTML = '<i class="fas fa-key"></i> Abrir Turno';
            btn.className = 'glass-btn success';
            btn.onclick = () => window.location.href = 'cierre_caja.php';
        }
    }
}

function llenarTabla(movs) {
    const tbody = document.getElementById('tablaBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';

    if (movs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:#86868b;">No hay movimientos para esta fecha.</td></tr>';
        return;
    }

    movs.forEach(m => {
        const valIngreso = parseFloat(m.ingreso);
        const valEgreso = parseFloat(m.egreso);
        
        const esEntrada = valIngreso > 0;
        const monto = esEntrada ? valIngreso : valEgreso;
        
        // Estilos de la celda de dinero
        const colorDinero = esEntrada ? '#34c759' : '#ff3b30';
        const bgDinero = esEntrada ? 'rgba(52, 199, 89, 0.1)' : 'rgba(255, 59, 48, 0.1)';
        const signo = esEntrada ? '+' : '-';
        
        let hora = m.fecha.split(' ')[1] || '--:--';
        try {
            const fechaSafe = m.fecha.replace(/-/g, '/'); 
            const fechaObj = new Date(fechaSafe);
            hora = fechaObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        } catch (e) {}

        const tipoDB = (m.tipo || '').toUpperCase();
        let etiqueta = tipoDB;
        let colorTag = '#86868b';
        let bgTag = 'rgba(0,0,0,0.05)';

        switch(tipoDB) {
            case 'REPARACION': etiqueta = 'Reparación'; colorTag = '#af52de'; bgTag = 'rgba(175,82,222,0.15)'; break;
            case 'VENTA': etiqueta = 'Venta Mostrador'; colorTag = '#007aff'; bgTag = 'rgba(0,122,255,0.15)'; break;
            case 'INGRESO': etiqueta = m.categoria !== 'General' ? m.categoria : 'Ingreso Extra'; colorTag = '#34c759'; bgTag = 'rgba(52,199,89,0.15)'; break;
            case 'GASTO': case 'EGRESO': etiqueta = m.categoria !== 'General' ? m.categoria : 'Gasto'; colorTag = '#ff3b30'; bgTag = 'rgba(255,59,48,0.15)'; break;
            case 'RETIRO': etiqueta = 'Retiro'; colorTag = '#ff9500'; bgTag = 'rgba(255,149,0,0.15)'; break;
            case 'CIERRE': etiqueta = 'Cierre de Caja'; colorTag = '#1d1d1f'; bgTag = 'rgba(0,0,0,0.1)'; break;
        }

        const badgeHtml = `<span style="background:${bgTag}; color:${colorTag}; font-weight:700; font-size:11px; padding:4px 8px; border-radius:6px; letter-spacing:0.5px; text-transform:uppercase;">${etiqueta}</span>`;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td data-label="Hora" style="font-weight:600; color:#86868b;">${hora}</td>
            <td data-label="Origen">${badgeHtml}</td>
            <td data-label="Concepto" style="font-weight:500; color:#1d1d1f;">${m.descripcion}</td>
            <td data-label="Categoría" style="font-size:13px; color:#86868b;">${m.categoria || '-'}</td>
            <td data-label="Usuario" style="font-size:13px; font-weight:600;"><i class="fas fa-user-circle" style="color:#007aff; margin-right:4px;"></i>${m.usuario}</td>
            <td data-label="Monto" style="text-align: right;">
                <span style="background:${bgDinero}; color:${colorDinero}; font-weight:800; padding:6px 12px; border-radius:8px; display:inline-block; font-size:14px;">
                    ${signo}$${parseFloat(monto).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')}
                </span>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function abrirModalGasto() {
    if(form) form.reset();
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-arrow-up" style="color: #ff3b30; margin-right:10px;"></i> Registrar Salida';
    document.getElementById('tipoMovimiento').value = 'GASTO';
    document.querySelector('.commit-trigger').style.background = '#ff3b30';
    if(modal) modal.style.display = 'flex';
}

function abrirModalIngreso() {
    if(form) form.reset();
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-arrow-down" style="color: #34c759; margin-right:10px;"></i> Registrar Entrada';
    document.getElementById('tipoMovimiento').value = 'INGRESO';
    document.querySelector('.commit-trigger').style.background = '#34c759';
    if(modal) modal.style.display = 'flex';
}

function cerrarModal() {
    if(modal) modal.style.display = 'none';
}

if (form) {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const descripcion = document.getElementById('descripcion').value.trim();
        const monto = document.getElementById('monto').value;

        if (!descripcion || !monto || parseFloat(monto) <= 0) {
            Swal.fire('Error', 'Ingrese un motivo y monto válido', 'warning'); return;
        }

        const formData = new FormData(form);
        formData.append('action', 'registrar_movimiento');

        Swal.fire({title: 'Procesando...', didOpen: () => Swal.showLoading()});

        try {
            const res = await fetch('api/caja.php', { method: 'POST', body: formData });
            const json = await res.json();
            if (json.success) {
                Swal.fire({ icon: 'success', title: 'Registrado', timer: 1200, showConfirmButton: false });
                cerrarModal();
                cargarReporte(); 
            } else { Swal.fire('Error', json.error, 'error'); }
        } catch (e) { Swal.fire('Error', 'Fallo de red', 'error'); }
    });
}

function formatoDinero(amount) {
    return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function gestionarCaja() {
    window.location.href = 'cierre_caja.php';
}

// Cierra modal al tocar fuera
window.onclick = function(event) {
    if (event.target == modal) { cerrarModal(); }
}