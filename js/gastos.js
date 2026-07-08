/* =========================================
 * LÓGICA DE CONTROL DE CAJA Y GASTOS (COMPLETO)
 * Versión Final: Liquid Glass + Proveedores
 * ========================================= */

// 1. Inicialización
document.addEventListener('DOMContentLoaded', () => {
    inicializarFecha();
    actualizarCategorias(); 
    
    // Escuchar la imagen
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

// 2. Utilidades Generales
window.formatoDinero = function(amount) { 
    return parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'); 
};

window.cerrarModal = function() { 
    const modal = document.getElementById('modalNuevo');
    if (modal) modal.style.display = 'none'; 
};

// 3. Control de Modales (Abrir, Editar)
window.abrirModalNuevo = function() {
    const form = document.getElementById('formGasto');
    if(form) form.reset();
    document.getElementById('inputId').value = '';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-exchange-alt" style="color:#007aff;"></i> Registrar Movimiento';
    document.getElementById('previewContainer').style.display = 'none';
    
    // Forzar tipo a GASTO
    document.getElementById('inputTipo').value = 'GASTO';
    actualizarCategorias();
    
    // Seleccionar 'Alimentos' por defecto para que la caja del proveedor NO estorbe
    const selectCat = document.getElementById('inputCategoria');
    if(selectCat) selectCat.value = 'Alimentos';
    verificarMostrarProveedor();
    
    const inputFecha = document.getElementById('inputFechaMovimiento');
    if (inputFecha) {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        inputFecha.value = now.toISOString().slice(0, 16);
    }

    const inputUsuario = document.getElementById('inputUsuario');
    if(inputUsuario) { inputUsuario.value = (typeof USUARIO_SESION !== 'undefined') ? USUARIO_SESION : ''; }

    document.getElementById('modalNuevo').style.display = 'flex';
};

window.abrirModalEdicion = function(movimiento) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit" style="color:#007aff;"></i> Editar Movimiento';
    document.getElementById('inputId').value = movimiento.id;
    
    const inputTipo = document.getElementById('inputTipo');
    if(movimiento.tipo !== 'GASTO' && movimiento.tipo !== 'INGRESO') {
        inputTipo.value = (parseFloat(movimiento.ingreso) > 0) ? 'INGRESO' : 'GASTO';
    } else {
        inputTipo.value = movimiento.tipo;
    }
    
    actualizarCategorias(movimiento.categoria);
    
    setTimeout(() => { 
        document.getElementById('inputCategoria').value = movimiento.categoria; 
        verificarMostrarProveedor();
        
        // Si tiene proveedor asignado, lo seleccionamos
        if (movimiento.id_proveedor) {
            const provSelect = document.getElementById('inputProveedor');
            if(provSelect) provSelect.value = movimiento.id_proveedor;
        }
    }, 150);
    
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
};

// 4. Lógica de Categorías y Proveedores
const catsGastos = ['Alimentos', 'Transporte', 'Servicios', 'Proveedores', 'Nómina', 'Mantenimiento', 'Retiro', 'Otros'];
const catsIngresos = ['Ingreso Extra', 'Inversión', 'Devolución Proveedor', 'Otros'];

window.actualizarCategorias = function(categoriaExtra = null) {
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
        opt.value = categoriaExtra; opt.textContent = categoriaExtra + ' (Origen Caja)';
        select.appendChild(opt); select.value = categoriaExtra;
    } else if (lista.includes(valorPrevio)) {
        select.value = valorPrevio;
    }

    verificarMostrarProveedor();
};

window.verificarMostrarProveedor = function() {
    const categoriaSeleccionada = document.getElementById('inputCategoria').value;
    const cajaProv = document.getElementById('cajaProveedor');

    if (cajaProv) {
        if (categoriaSeleccionada === 'Proveedores') {
            cajaProv.style.display = 'block';
            cargarListaProveedores(); 
        } else {
            cajaProv.style.display = 'none';
            const inputProv = document.getElementById('inputProveedor');
            if (inputProv) inputProv.value = ''; 
        }
    }
};

window.cargarListaProveedores = async function() {
    try {
        const res = await fetch('api/proveedores.php?action=listar');
        const data = await res.json();
        const select = document.getElementById('inputProveedor');
        const valorActual = select.value;
        
        select.innerHTML = '<option value="">Ninguno / No aplica</option>';
        
        if (data.success && data.data) {
            data.data.forEach(prov => {
                select.innerHTML += `<option value="${prov.id}">${prov.empresa}</option>`;
            });
        }
        
        select.innerHTML += `<option value="NUEVO" style="color: #007aff; font-weight: bold;">+ Dar de alta nuevo proveedor...</option>`;
        
        if (valorActual && valorActual !== 'NUEVO') {
            select.value = valorActual;
        }
    } catch (e) {
        console.error("Error al cargar proveedores", e);
    }
};

window.verificarNuevoProveedor = function(selectElement) {
    if (selectElement.value === 'NUEVO') {
        nuevoProveedorRapido();
    }
};

window.nuevoProveedorRapido = function() {
    Swal.fire({
        title: 'Alta de Proveedor',
        html: `
            <div style="text-align: left; margin-top: 15px;">
                <label style="font-size: 12px; font-weight: 600; color: #86868b; text-transform: uppercase;">Empresa / Marca <span style="color:#ff3b30">*</span></label>
                <input id="swal-prov-empresa" class="glass-input" style="margin-bottom: 15px; width: 100%; box-sizing: border-box;" placeholder="Ej: FixMobile, Steren...">
                
                <label style="font-size: 12px; font-weight: 600; color: #86868b; text-transform: uppercase;">Nombre del Contacto</label>
                <input id="swal-prov-contacto" class="glass-input" style="margin-bottom: 15px; width: 100%; box-sizing: border-box;" placeholder="Opcional">
                
                <label style="font-size: 12px; font-weight: 600; color: #86868b; text-transform: uppercase;">Teléfono / WhatsApp</label>
                <input id="swal-prov-tel" type="tel" class="glass-input" style="width: 100%; box-sizing: border-box;" placeholder="10 dígitos" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);">
            </div>
        `,
        customClass: {
            popup: 'glass-swal-popup',
            confirmButton: 'glass-btn success',
            cancelButton: 'glass-btn secondary',
            actions: 'swal-glass-actions'
        },
        buttonsStyling: false, 
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save"></i> Guardar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const empresa = document.getElementById('swal-prov-empresa').value;
            if (!empresa) { Swal.showValidationMessage('La empresa es obligatoria'); }
            return { 
                empresa: empresa, 
                contacto: document.getElementById('swal-prov-contacto').value,
                telefono: document.getElementById('swal-prov-tel').value
            }
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action', 'guardar');
            fd.append('empresa', result.value.empresa);
            fd.append('contacto', result.value.contacto);
            fd.append('telefono', result.value.telefono);
            
            try {
                const res = await fetch('api/proveedores.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                if (data.success) {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Proveedor agregado', timer: 1500, showConfirmButton: false });
                    await cargarListaProveedores(); 
                    document.getElementById('inputProveedor').value = data.id; 
                } else {
                    Swal.fire('Error', data.error, 'error');
                    document.getElementById('inputProveedor').value = ''; 
                }
            } catch (err) {
                Swal.fire('Error', 'Fallo de conexión', 'error');
                document.getElementById('inputProveedor').value = ''; 
            }
        } else {
            document.getElementById('inputProveedor').value = '';
        }
    });
};

// 5. Envío del Formulario de Movimiento
const formGasto = document.getElementById('formGasto');
if(formGasto) {
    formGasto.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(formGasto);
        
        Swal.fire({title: 'Guardando...', didOpen: () => Swal.showLoading()});

        try {
            const res = await fetch('api/gastos.php', { method: 'POST', body: formData });
            const text = await res.text();
            
            let data;
            try {
                data = JSON.parse(text);
            } catch(e) {
                console.error("Respuesta cruda de PHP:", text);
                throw new Error("El servidor devolvió un error interno.");
            }

            if(data.success) { 
                Swal.fire({toast:true, position:'top-end', icon:'success', title:'Guardado', showConfirmButton:false, timer:1500});
                cerrarModal(); 
                cargarMovimientos(); 
            } else {
                Swal.fire('Error', data.error || 'No se pudo guardar', 'error');
            }
        } catch (err) {
            console.error("Error al guardar:", err);
            Swal.fire('Error', 'Revisa la consola para más detalles', 'error');
        }
    });
}

// 6. Carga de Datos y Edición
function inicializarFecha() {
    const filtroFecha = document.getElementById('filtroFecha');
    if (!filtroFecha) return;
    const fechaMexico = new Date().toLocaleDateString('en-CA', { timeZone: 'America/Mexico_City', year: 'numeric', month: '2-digit', day: '2-digit' });
    filtroFecha.value = fechaMexico;
    cargarMovimientos();
}

window.cargarMovimientos = function() {
    const fecha = document.getElementById('filtroFecha').value;
    const tipo = document.getElementById('filtroTipo').value;
    const tbody = document.getElementById('lista-movimientos');
    
    tbody.innerHTML = '<tr><td colspan="8" class="text-center" style="padding:40px;"><i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Cargando...</td></tr>';

    fetch(`api/gastos.php?action=listar&fecha=${fecha}&tipo=${tipo}&_t=${Date.now()}`)
        .then(res => res.json())
        .then(data => {
            tbody.innerHTML = '';
            
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
                    
                    if(!esNeutro) {
                        if(esEntrada) totalIngresos += monto;
                        else totalGastos += monto;
                    }

                    let signo = esEntrada ? '+' : '-';
                    let colorMonto = esEntrada ? '#34c759' : '#ff3b30'; 
                    let bgMonto = esEntrada ? 'rgba(52, 199, 89, 0.1)' : 'rgba(255, 59, 48, 0.1)';
                    
                    if (esNeutro) {
                        signo = '•'; colorMonto = '#86868b'; bgMonto = 'rgba(134, 134, 139, 0.1)';
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

            let balanceFinal = totalIngresos - totalGastos;
            document.getElementById('resumen-ingresos').textContent = '$' + formatoDinero(totalIngresos);
            document.getElementById('resumen-gastos').textContent = '$' + formatoDinero(totalGastos);
            document.getElementById('resumen-balance').textContent = '$' + formatoDinero(balanceFinal);
            
            const balanceEl = document.getElementById('resumen-balance');
            if(balanceFinal >= 0) { balanceEl.style.color = '#007aff'; } 
            else { balanceEl.style.color = '#ff3b30'; }

        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger p-4">Error al cargar datos.</td></tr>';
        });
};

// ==========================================
// MODO EDICIÓN CON LLAVE MAESTRA (SIN AVISOS EN CONSOLA)
// ==========================================
window.editarMovimiento = function(id) {
    Swal.fire({
        title: 'Modo Edición',
        // Metemos el input dentro de un form fantasma para que Chrome sea feliz
        html: `
            <p style="font-size: 14px; color: #86868b; margin-bottom: 15px;">Ingresa la Llave Maestra:</p>
            <form onsubmit="event.preventDefault();">
                <input type="password" id="swal-llave-editar" class="glass-input" style="width: 80%; text-align: center; letter-spacing: 5px; font-size: 20px;" placeholder="••••••" autocomplete="new-password">
            </form>
        `,
        customClass: { popup: 'glass-swal-popup' }, // Le damos el estilo cristal que ya creamos
        showCancelButton: true,
        confirmButtonText: 'Acceder',
        confirmButtonColor: '#007aff',
        cancelButtonText: 'Cancelar',
        focusConfirm: false,
        preConfirm: () => {
            const llave = document.getElementById('swal-llave-editar').value;
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
};

// ==========================================
// ELIMINAR REGISTRO CON LLAVE MAESTRA
// ==========================================
window.eliminarMovimiento = function(id) {
    Swal.fire({
        title: 'Eliminar Registro',
        // Formulario fantasma para evitar el warning de DOM Password
        html: `
            <p style="font-size: 14px; color: #86868b; margin-bottom: 15px;">Ingresa la Llave Maestra:</p>
            <form onsubmit="event.preventDefault();">
                <input type="password" id="swal-llave-eliminar" class="glass-input" style="width: 80%; text-align: center; letter-spacing: 5px; font-size: 20px;" placeholder="••••••" autocomplete="new-password">
            </form>
        `,
        customClass: { popup: 'glass-swal-popup' }, 
        showCancelButton: true,
        confirmButtonText: 'Eliminar Definitivamente',
        confirmButtonColor: '#ff3b30',
        cancelButtonText: 'Cancelar',
        focusConfirm: false,
        preConfirm: () => {
            const llave = document.getElementById('swal-llave-eliminar').value;
            if (!llave) Swal.showValidationMessage('Requerido');
            return llave;
        }
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
};

window.exportarMesExcel = function() {
    const mesInput = document.getElementById('mesExportar').value;
    if (!mesInput) { Swal.fire('Atención', 'Selecciona un mes primero', 'warning'); return; }
    const partes = mesInput.split('-');
    window.location.href = `api/gastos.php?action=exportar_mes&mes=${partes[1]}&anio=${partes[0]}`;
};