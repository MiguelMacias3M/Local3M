/* =========================================
 * LÓGICA DE CONTROL DE CAJA Y GASTOS
 * Versión Liquid Glass + Resumen Financiero
 * ========================================= */

document.addEventListener('DOMContentLoaded', () => {
    inicializarFecha();
    actualizarCategorias(); 
    
    const inputFoto = document.getElementById('inputFoto');
    if(inputFoto) {
        inputFoto.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('previewContainer');
            const img = document.getElementById('imgPreview');
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) { img.src = e.target.result; preview.style.display = 'block'; }
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
    }
});

function inicializarFecha() {
    const filtroFecha = document.getElementById('filtroFecha');
    if (!filtroFecha) return;

    const fechaMexico = new Date().toLocaleDateString('en-CA', {
        timeZone: 'America/Mexico_City',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });

    filtroFecha.value = fechaMexico;
    cargarMovimientos();
}

const catsGastos = ['Alimentos', 'Transporte', 'Servicios', 'Proveedores', 'Nómina', 'Mantenimiento', 'Retiro', 'Otros'];
const catsIngresos = ['Ingreso Extra', 'Inversión', 'Devolución Proveedor', 'Otros'];

function actualizarCategorias(categoriaExtra = null) {
    const inputTipo = document.getElementById('inputTipo');
    const select = document.getElementById('inputCategoria');
    
    if(!inputTipo || !select) return;

    const tipo = inputTipo.value;
    const valorPrevio = select.value;
    
    select.innerHTML = '';
    const lista = tipo === 'GASTO' ? catsGastos : catsIngresos;
    
    lista.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c; opt.textContent = c; select.appendChild(opt);
    });

    if (categoriaExtra && !lista.includes(categoriaExtra)) {
        const opt = document.createElement('option');
        opt.value = categoriaExtra;
        opt.textContent = categoriaExtra + ' (Origen Caja)';
        select.appendChild(opt);
        select.value = categoriaExtra;
    } else if (lista.includes(valorPrevio)) {
        select.value = valorPrevio;
    }
}

function cargarMovimientos() {
    const fecha = document.getElementById('filtroFecha').value;
    const tipo = document.getElementById('filtroTipo').value;
    const tbody = document.getElementById('lista-movimientos');
    
    tbody.innerHTML = '<tr><td colspan="8" class="text-center" style="padding:40px;"><i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Cargando...</td></tr>';

    fetch(`api/gastos.php?action=listar&fecha=${fecha}&tipo=${tipo}&_t=${Date.now()}`)
        .then(res => res.json())
        .then(data => {
            tbody.innerHTML = '';
            
            // Variables para el Dashboard Superior
            let totalIngresos = 0;
            let totalGastos = 0;

            if (data.success && data.data && data.data.length > 0) {
                data.data.forEach(m => {
                    const valIngreso = parseFloat(m.ingreso)||0;
                    const valEgreso = parseFloat(m.egreso)||0;
                    const esEntrada = valIngreso > 0;
                    
                    const tipoUpper = (m.tipo || '').toUpperCase();
                    const esNeutro = m.es_retiro_cierre === true || tipoUpper === 'RETIRO' || tipoUpper === 'CIERRE';

                    const monto = esEntrada ? valIngreso : valEgreso;
                    
                    // Sumamos para los totales (ignoramos cierres neutros si no afectan el balance diario real)
                    if(!esNeutro) {
                        if(esEntrada) totalIngresos += monto;
                        else totalGastos += monto;
                    }

                    let signo = esEntrada ? '+' : '-';
                    let colorMonto = esEntrada ? '#34c759' : '#ff3b30'; 
                    let bgMonto = esEntrada ? 'rgba(52, 199, 89, 0.1)' : 'rgba(255, 59, 48, 0.1)';
                    
                    if (esNeutro) {
                        signo = '•'; 
                        colorMonto = '#86868b'; 
                        bgMonto = 'rgba(134, 134, 139, 0.1)';
                    }

                    let origenBadge = m.origen === 'CAJA' 
                        ? '<span style="font-size:11px; background:rgba(0,122,255,0.1); padding:3px 6px; border-radius:4px; color:#007aff; font-weight:600;"><i class="fas fa-store"></i> MOSTRADOR</span>' 
                        : '<span style="font-size:11px; background:rgba(255,149,0,0.1); padding:3px 6px; border-radius:4px; color:#ff9500; font-weight:600;"><i class="fas fa-laptop-code"></i> ADMIN</span>';

                    let claseBadge = 'status-pending'; 
                    let textoTipo = m.tipo;
                    
                    if (esEntrada) claseBadge = 'status-ready';
                    if (tipoUpper === 'VENTA') claseBadge = 'status-delivered'; 
                    if (tipoUpper === 'REPARACION') { claseBadge = 'status-in-progress'; } 
                    else if (tipoUpper === 'CIERRE') { claseBadge = 'status-pending'; textoTipo = 'Cierre Caja'; }
                    else if (tipoUpper === 'RETIRO') { claseBadge = 'status-pending'; }

                    let fechaLimpia = '--/--/----';
                    let horaLimpia = '--:--';
                    if(m.fecha) {
                        let partes = m.fecha.split(' ');
                        if(partes.length === 2) {
                            let f = partes[0].split('-');
                            fechaLimpia = `${f[2]}/${f[1]}/${f[0]}`;
                            horaLimpia = partes[1].substring(0,5);
                        }
                    }

                    let btnFoto = '';
                    if (m.foto) {
                        btnFoto = `<a href="uploads/${m.foto}" target="_blank" class="btn-icon" style="background:rgba(0,122,255,0.1); color:#007aff;" title="Ver Evidencia"><i class="fas fa-image"></i></a>`;
                    }

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td data-label="ID"><span style="font-family: monospace; font-size: 13px; color: #86868b;">${m.id_transaccion || m.id}</span></td>
                        <td data-label="Tipo/Origen">
                            <div style="margin-bottom: 4px;"><span class="status ${claseBadge}">${textoTipo}</span></div>
                            <div>${origenBadge}</div>
                        </td>
                        <td data-label="Descripción" style="font-weight: 500; color: #1d1d1f;">${m.descripcion}</td>
                        <td data-label="Monto" style="text-align: right;">
                            <span style="background: ${bgMonto}; color: ${colorMonto}; padding: 6px 12px; border-radius: 8px; font-weight: bold; font-size: 14px; display: inline-block;">
                                ${signo} $${formatoDinero(monto)}
                            </span>
                        </td>
                        <td data-label="Categoría"><span style="font-size: 13px; color: #86868b;"><i class="fas fa-tag"></i> ${m.categoria || 'S/C'}</span></td>
                        <td data-label="Fecha">
                            <div style="font-size: 13px; color: #1d1d1f; font-weight: 500;">${fechaLimpia}</div>
                            <div style="font-size: 12px; color: #86868b;">${horaLimpia} hrs</div>
                        </td>
                        <td data-label="Usuario"><span style="font-size: 13px; font-weight: 600;"><i class="fas fa-user-circle"></i> ${m.usuario || 'Sistema'}</span></td>
                        <td data-label="Acciones" style="text-align: center;">
                            <div style="display: flex; gap: 5px; justify-content: center;">
                                ${btnFoto}
                                <button class="btn-icon" style="background:rgba(255,149,0,0.1); color:#ff9500;" onclick="editarMovimiento(${m.id})" title="Editar"><i class="fas fa-edit"></i></button>
                                <button class="btn-icon" style="background:rgba(255,59,48,0.1); color:#ff3b30;" onclick="eliminarMovimiento(${m.id})" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center" style="padding: 40px; color: #86868b;">No hay movimientos registrados.</td></tr>';
            }

            // Actualizar Tarjetas de Resumen
            let balanceFinal = totalIngresos - totalGastos;
            document.getElementById('resumen-ingresos').textContent = '$' + formatoDinero(totalIngresos);
            document.getElementById('resumen-gastos').textContent = '$' + formatoDinero(totalGastos);
            document.getElementById('resumen-balance').textContent = '$' + formatoDinero(balanceFinal);
            
            // Colorear el balance (Verde si es positivo, Rojo si es negativo)
            const balanceEl = document.getElementById('resumen-balance');
            if(balanceFinal >= 0) { balanceEl.style.color = '#007aff'; } 
            else { balanceEl.style.color = '#ff3b30'; }

        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger p-4">Error al cargar datos.</td></tr>';
        });
}

function editarMovimiento(id) {
    Swal.fire({
        title: 'Modo Edición',
        text: 'Ingresa la Llave Maestra:',
        input: 'password',
        inputAttributes: { autocapitalize: 'off', placeholder: '••••••' },
        showCancelButton: true,
        confirmButtonText: 'Acceder',
        confirmButtonColor: '#007aff',
        preConfirm: (llave) => {
            if (!llave) Swal.showValidationMessage('Escribe la contraseña');
            return llave;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action', 'obtener');
            fd.append('id', id);
            fd.append('llave_maestra', result.value);

            fetch('api/gastos.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.success) abrirModalEdicion(data.data);
                    else Swal.fire('Acceso Denegado', data.error || 'Llave incorrecta', 'error');
                })
                .catch(() => Swal.fire('Error', 'Fallo de conexión', 'error'));
        }
    });
}

function abrirModalEdicion(movimiento) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit" style="color:#007aff;"></i> Editar Movimiento';
    document.getElementById('inputId').value = movimiento.id;
    
    const inputTipo = document.getElementById('inputTipo');
    if(movimiento.tipo !== 'GASTO' && movimiento.tipo !== 'INGRESO') {
        inputTipo.value = (parseFloat(movimiento.ingreso) > 0) ? 'INGRESO' : 'GASTO';
    } else {
        inputTipo.value = movimiento.tipo;
    }
    
    actualizarCategorias(movimiento.categoria);
    setTimeout(() => { document.getElementById('inputCategoria').value = movimiento.categoria; }, 50);
    
    document.getElementById('inputDescripcion').value = movimiento.descripcion;
    document.getElementById('inputMonto').value = movimiento.monto_real;

    const inputFecha = document.getElementById('inputFechaMovimiento');
    if (inputFecha && movimiento.fecha) {
        inputFecha.value = movimiento.fecha.replace(' ', 'T').slice(0, 16);
    }

    const inputUsuario = document.getElementById('inputUsuario');
    if(inputUsuario) { inputUsuario.value = movimiento.usuario || ''; }

    const preview = document.getElementById('previewContainer');
    const img = document.getElementById('imgPreview');
    if (movimiento.foto_url) { img.src = movimiento.foto_url; preview.style.display = 'block'; }
    else { preview.style.display = 'none'; img.src = ''; }
    
    document.getElementById('inputFoto').value = ''; 
    document.getElementById('modalNuevo').style.display = 'flex';
}

function eliminarMovimiento(id) {
    Swal.fire({
        title: 'Eliminar Registro',
        text: 'Ingresa la Llave Maestra:',
        input: 'password',
        showCancelButton: true,
        confirmButtonText: 'Eliminar Definitivamente',
        confirmButtonColor: '#ff3b30',
        preConfirm: (llave) => { if (!llave) Swal.showValidationMessage('Requerido'); return llave; }
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action', 'eliminar');
            fd.append('id', id);
            fd.append('llave_maestra', result.value);

            fetch('api/gastos.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if(data.success) { 
                        Swal.fire({toast:true, position:'top-end', icon:'success', title:'Eliminado', showConfirmButton:false, timer:1500});
                        cargarMovimientos(); 
                    }
                    else Swal.fire('Error', data.error, 'error');
                })
                .catch(err => Swal.fire('Error', 'Fallo de conexión', 'error'));
        }
    });
}

const form = document.getElementById('formGasto');
if(form) {
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        
        Swal.fire({title: 'Guardando...', didOpen: () => Swal.showLoading()});

        fetch('api/gastos.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.success) { 
                Swal.fire({toast:true, position:'top-end', icon:'success', title:'Guardado', showConfirmButton:false, timer:1500});
                cerrarModal(); 
                cargarMovimientos(); 
            }
            else Swal.fire('Error', data.error, 'error');
        })
        .catch(err => Swal.fire('Error', 'Fallo de conexión', 'error'));
    });
}

function abrirModalNuevo() {
    form.reset();
    document.getElementById('inputId').value = '';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-exchange-alt" style="color:#007aff;"></i> Registrar Movimiento';
    document.getElementById('previewContainer').style.display = 'none';
    document.getElementById('inputTipo').value = 'GASTO';
    actualizarCategorias();
    
    const inputFecha = document.getElementById('inputFechaMovimiento');
    if (inputFecha) {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        inputFecha.value = now.toISOString().slice(0, 16);
    }

    const inputUsuario = document.getElementById('inputUsuario');
    if(inputUsuario) { inputUsuario.value = (typeof USUARIO_SESION !== 'undefined') ? USUARIO_SESION : ''; }

    document.getElementById('modalNuevo').style.display = 'flex';
}

function cerrarModal() { document.getElementById('modalNuevo').style.display = 'none'; }
function formatoDinero(amount) { return parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'); }

function exportarMesExcel() {
    const mesInput = document.getElementById('mesExportar').value;
    if (!mesInput) { Swal.fire('Atención', 'Selecciona un mes primero', 'warning'); return; }
    const partes = mesInput.split('-');
    window.location.href = `api/gastos.php?action=exportar_mes&mes=${partes[1]}&anio=${partes[0]}`;
}