document.addEventListener('DOMContentLoaded', () => {
    // 1. PRIMERO CORREGIMOS LA FECHA
    inicializarFecha();
    
    // 2. Cargamos categorías
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

// --- FUNCIÓN: CORREGIR FECHA A MÉXICO ---
function inicializarFecha() {
    const filtroFecha = document.getElementById('filtroFecha');
    if (!filtroFecha) return;

    // Forzar fecha actual de CDMX
    const fechaMexico = new Date().toLocaleDateString('en-CA', {
        timeZone: 'America/Mexico_City',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });

    filtroFecha.value = fechaMexico;
    // console.log("📅 Fecha ajustada a:", fechaMexico);

    // Cargar movimientos una vez puesta la fecha correcta
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
    
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x"></i><br><br><span style="font-weight: 500;">Cargando movimientos...</span></td></tr>';

    fetch(`api/gastos.php?action=listar&fecha=${fecha}&tipo=${tipo}&_t=${Date.now()}`)
        .then(res => res.json())
        .then(data => {
            tbody.innerHTML = '';
            if (data.success && data.data && data.data.length > 0) {
                data.data.forEach(m => {
                    const valIngreso = parseFloat(m.ingreso)||0;
                    const valEgreso = parseFloat(m.egreso)||0;
                    const esEntrada = valIngreso > 0;
                    
                    const tipoUpper = (m.tipo || '').toUpperCase();
                    const esNeutro = m.es_retiro_cierre === true || tipoUpper === 'RETIRO' || tipoUpper === 'CIERRE';

                    const monto = esEntrada ? valIngreso : valEgreso;
                    
                    // Colores de los montos
                    let signo = esEntrada ? '+' : '-';
                    let colorMonto = esEntrada ? '#28a745' : '#dc3545'; 
                    let bgMonto = esEntrada ? 'rgba(40, 167, 69, 0.1)' : 'rgba(220, 53, 69, 0.1)';
                    
                    if (esNeutro) {
                        signo = '•'; 
                        colorMonto = '#6c757d'; 
                        bgMonto = 'rgba(108, 117, 125, 0.1)';
                    }

                    // Badge de Origen
                    let origenBadge = m.origen === 'CAJA' 
                        ? '<span style="font-size:0.65em; background:#f8f9fa; padding:3px 6px; border-radius:4px; color:#6c757d; border:1px solid #dee2e6; letter-spacing: 0.5px; text-transform: uppercase;"><i class="fas fa-store"></i> Mostrador</span>' 
                        : '<span style="font-size:0.65em; background:#e3f2fd; padding:3px 6px; border-radius:4px; color:#0d47a1; border:1px solid #bbdefb; letter-spacing: 0.5px; text-transform: uppercase;"><i class="fas fa-laptop-code"></i> Admin</span>';

                    // ==========================================
                    // ETIQUETAS DE TIPO (MORADO SUTIL)
                    // ==========================================
                    let claseBadge = 'badge-gasto'; 
                    let textoTipo = m.tipo;
                    let estiloBadge = 'padding: 5px 10px; border-radius: 6px; font-size: 0.8em; letter-spacing: 0.5px;';

                    if (esEntrada) claseBadge = 'badge-ingreso';
                    if (tipoUpper === 'VENTA') claseBadge = 'badge-primary'; 
                    
                    // Aquí asignamos el MORADO SUTIL (Fondo transparente 12%, borde suave y letra fuerte)
                    if (tipoUpper === 'REPARACION') { 
                        claseBadge = ''; 
                        estiloBadge += ' background-color: rgba(111, 66, 193, 0.12); color: #6f42c1; font-weight: 600; border: 1px solid rgba(111, 66, 193, 0.2);'; 
                    } 
                    else if (tipoUpper === 'CIERRE') { claseBadge = 'badge-dark'; textoTipo = 'Cierre Caja'; }
                    else if (tipoUpper === 'RETIRO') { claseBadge = 'badge-secondary'; }
                    // ==========================================

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
                        btnFoto = `<a href="uploads/${m.foto}" target="_blank" class="btn-icon" style="background-color:#17a2b8; color:white; width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; text-decoration: none; transition: 0.2s;" title="Ver Evidencia"><i class="fas fa-image"></i></a>`;
                    }

                    const tr = document.createElement('tr');
                    tr.style.transition = "background-color 0.2s";
                    tr.onmouseover = function() { this.style.backgroundColor = '#f8f9fa'; }
                    tr.onmouseout = function() { this.style.backgroundColor = 'transparent'; }

                    tr.innerHTML = `
                        <td style="vertical-align: middle;">
                            <span style="background: #f1f3f5; padding: 4px 8px; border-radius: 6px; font-family: monospace; font-size: 0.85em; color: #495057; border: 1px solid #e9ecef;">${m.id_transaccion || m.id}</span>
                        </td>
                        <td class="text-center" style="vertical-align: middle;">
                            <div style="margin-bottom: 6px;"><span class="badge-tipo ${claseBadge}" style="${estiloBadge}">${textoTipo}</span></div>
                            <div>${origenBadge}</div>
                        </td>
                        <td style="vertical-align: middle; color: #343a40; font-weight: 500; font-size: 0.95em;">
                            ${m.descripcion}
                        </td>
                        <td class="text-right" style="vertical-align: middle;">
                            <span style="background: ${bgMonto}; color: ${colorMonto}; padding: 6px 12px; border-radius: 8px; font-weight: bold; font-size: 0.95em; display: inline-block; min-width: 90px; text-align: center; letter-spacing: 0.5px;">
                                ${signo} $${formatoDinero(monto)}
                            </span>
                        </td>
                        <td style="vertical-align: middle;">
                            <span style="background: #f8f9fa; color: #6c757d; padding: 5px 10px; border-radius: 20px; font-size: 0.85em; border: 1px solid #dee2e6; white-space: nowrap;">
                                <i class="fas fa-tag" style="margin-right: 4px; opacity: 0.6;"></i> ${m.categoria || 'Sin Categoría'}
                            </span>
                        </td>
                        <td style="vertical-align: middle; white-space: nowrap;">
                            <div style="font-size: 0.85em; color: #495057; font-weight: 600;"><i class="far fa-calendar-alt" style="margin-right:5px; color:#adb5bd;"></i>${fechaLimpia}</div>
                            <div style="font-size: 0.8em; color: #868e96; margin-top: 3px;"><i class="far fa-clock" style="margin-right:5px; color:#adb5bd;"></i>${horaLimpia} hrs</div>
                        </td>
                        <td style="vertical-align: middle;">
                            <span style="background: #e9ecef; color: #495057; padding: 5px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 600; display: inline-flex; align-items: center; white-space: nowrap;">
                                <i class="fas fa-user-circle" style="font-size: 1.2em; margin-right: 6px; color: #adb5bd;"></i> ${m.usuario || 'Sistema'}
                            </span>
                        </td>
                        <td class="text-center" style="vertical-align: middle;">
                            <div style="display: flex; gap: 6px; justify-content: center;">
                                ${btnFoto}
                                <button class="btn-icon btn-primary" onclick="editarMovimiento(${m.id})" title="Editar" style="width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: none; transition: 0.2s;"><i class="fas fa-edit"></i></button>
                                <button class="btn-icon btn-danger" onclick="eliminarMovimiento(${m.id})" title="Eliminar" style="width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: none; transition: 0.2s;"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center p-5 text-muted" style="font-size: 1.1em;"><i class="fas fa-folder-open fa-3x mb-3" style="color: #dee2e6; display:block;"></i> No hay movimientos registrados en esta fecha.</td></tr>';
            }
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger p-4"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>Error al cargar los datos. Revisa tu conexión.</td></tr>';
        });
}

// --- EDICIÓN ---
function editarMovimiento(id) {
    Swal.fire({
        title: 'Modo Edición',
        text: 'Ingresa la Llave Maestra:',
        input: 'password',
        inputAttributes: { autocapitalize: 'off', placeholder: '••••••' },
        showCancelButton: true,
        confirmButtonText: 'Acceder',
        confirmButtonColor: '#007bff',
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
    document.getElementById('modalTitle').textContent = 'Editar Movimiento';
    const btnGuardar = document.querySelector('#formGasto button[type="submit"]');
    if(btnGuardar) btnGuardar.textContent = 'Actualizar Cambios';
    
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

    // ==========================================
    // CAMPO DE USUARIO (MODO EDICIÓN)
    // Carga el usuario original de la BD para que lo actualices manualmente
    // ==========================================
    const inputUsuario = document.getElementById('inputUsuario');
    if(inputUsuario) {
        inputUsuario.value = movimiento.usuario || '';
    }

    const preview = document.getElementById('previewContainer');
    const img = document.getElementById('imgPreview');
    if (movimiento.foto_url) { img.src = movimiento.foto_url; preview.style.display = 'block'; }
    else { preview.style.display = 'none'; img.src = ''; }
    
    document.getElementById('inputFoto').value = ''; 
    document.getElementById('modalNuevo').style.display = 'flex';
}

// --- ELIMINACIÓN ---
function eliminarMovimiento(id) {
    Swal.fire({
        title: 'Eliminar Registro',
        text: 'Ingresa la Llave Maestra:',
        input: 'password',
        showCancelButton: true,
        confirmButtonText: 'Eliminar Definitivamente',
        confirmButtonColor: '#d33',
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
                    if(data.success) { Swal.fire('Eliminado', '', 'success'); cargarMovimientos(); }
                    else Swal.fire('Error', data.error, 'error');
                })
                .catch(err => Swal.fire('Error', 'Fallo de conexión', 'error'));
        }
    });
}

// --- FORMULARIO ---
const form = document.getElementById('formGasto');
if(form) {
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        
        Swal.fire({title: 'Guardando...', didOpen: () => Swal.showLoading()});

        fetch('api/gastos.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.success) { Swal.fire('Éxito', '', 'success'); cerrarModal(); cargarMovimientos(); }
            else Swal.fire('Error', data.error, 'error');
        })
        .catch(err => Swal.fire('Error', 'Fallo de conexión', 'error'));
    });
}

function abrirModalNuevo() {
    form.reset();
    document.getElementById('inputId').value = '';
    document.getElementById('modalTitle').textContent = 'Registrar Movimiento';
    const btnGuardar = document.querySelector('#formGasto button[type="submit"]');
    if(btnGuardar) btnGuardar.textContent = 'Guardar';
    document.getElementById('previewContainer').style.display = 'none';
    
    document.getElementById('inputTipo').value = 'GASTO';
    actualizarCategorias();
    
    const inputFecha = document.getElementById('inputFechaMovimiento');
    if (inputFecha) {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        inputFecha.value = now.toISOString().slice(0, 16);
    }

    // ==========================================
    // CAMPO DE USUARIO (MODO NUEVO)
    // Sugiere el nombre de sesión, pero es libre de borrarse
    // ==========================================
    const inputUsuario = document.getElementById('inputUsuario');
    if(inputUsuario) {
        inputUsuario.value = (typeof USUARIO_SESION !== 'undefined') ? USUARIO_SESION : '';
    }

    document.getElementById('modalNuevo').style.display = 'flex';
}

function cerrarModal() { document.getElementById('modalNuevo').style.display = 'none'; }
function formatoDinero(amount) { return parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'); }
// ==========================================
// EXPORTAR EXCEL MENSUAL
// ==========================================
function exportarMesExcel() {
    const mesInput = document.getElementById('mesExportar').value;
    if (!mesInput) {
        Swal.fire('Atención', 'Selecciona un mes primero', 'warning');
        return;
    }

    // El input devuelve formato "YYYY-MM" (Ej. 2026-02)
    const partes = mesInput.split('-');
    const anio = partes[0];
    const mes = partes[1];

    // Redirigir al archivo PHP que forzará la descarga del CSV
    window.location.href = `api/gastos.php?action=exportar_mes&mes=${mes}&anio=${anio}`;
}