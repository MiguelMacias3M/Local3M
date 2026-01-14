document.addEventListener('DOMContentLoaded', () => {
    inicializarFecha();
    actualizarCategorias(); 
    
    // Listener para vista previa de imagen en el input file
    const inputFoto = document.getElementById('inputFoto');
    if(inputFoto) {
        inputFoto.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('previewContainer');
            const img = document.getElementById('imgPreview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
    }
});

// --- 1. INICIALIZACIÓN DE FECHA (MÉXICO) ---
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
    cargarMovimientos();
}

// Listas de categorías administrativas
const catsGastos = ['Alimentos', 'Transporte', 'Servicios', 'Proveedores', 'Nómina', 'Mantenimiento', 'Retiro', 'Otros'];
const catsIngresos = ['Ingreso Extra', 'Inversión', 'Devolución Proveedor', 'Otros'];

function actualizarCategorias(categoriaExtra = null) {
    const tipo = document.getElementById('inputTipo').value;
    const select = document.getElementById('inputCategoria');
    const valorPrevio = select.value;
    
    select.innerHTML = '';
    const lista = tipo === 'GASTO' ? catsGastos : catsIngresos;
    
    lista.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c; opt.textContent = c; select.appendChild(opt);
    });

    // Si la categoría actual (al editar) no está en la lista estándar, la agregamos
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

// --- 2. CARGAR MOVIMIENTOS (VISUALIZACIÓN INTELIGENTE) ---
function cargarMovimientos() {
    const fecha = document.getElementById('filtroFecha').value;
    const tipo = document.getElementById('filtroTipo').value;
    const tbody = document.getElementById('tablaBody');
    
    tbody.innerHTML = '<tr><td colspan="8" class="text-center">Cargando...</td></tr>';

    fetch(`api/gastos.php?action=listar&fecha=${fecha}&tipo=${tipo}&_t=${Date.now()}`)
        .then(res => res.json())
        .then(data => {
            tbody.innerHTML = '';
            if (data.success && data.data && data.data.length > 0) {
                data.data.forEach(m => {
                    const valIngreso = parseFloat(m.ingreso)||0;
                    const valEgreso = parseFloat(m.egreso)||0;
                    const esEntrada = valIngreso > 0;
                    
                    const monto = esEntrada ? valIngreso : valEgreso;
                    
                    // --- LÓGICA DE COLORES Y SIGNOS ---
                    // Detectar movimientos neutros (Cierres y Retiros)
                    const tipoUpper = (m.tipo || '').toUpperCase();
                    const esNeutro = m.es_retiro_cierre === true || tipoUpper === 'RETIRO' || tipoUpper === 'CIERRE';

                    let signo = esEntrada ? '+' : '-';
                    let colorMonto = esEntrada ? '#28a745' : '#dc3545'; // Verde o Rojo
                    
                    if (esNeutro) {
                        signo = '•'; // Signo neutro
                        colorMonto = '#6c757d'; // Gris (No cuenta como gasto/ingreso operativo)
                    }

                    // Badge de Origen (Admin vs Caja)
                    let origenBadge = '';
                    if (m.origen === 'CAJA') {
                        origenBadge = '<span style="font-size:0.75em; background:#f8f9fa; padding:2px 6px; border-radius:4px; color:#6c757d; border:1px solid #dee2e6;">Mostrador</span>';
                    } else {
                        origenBadge = '<span style="font-size:0.75em; background:#e3f2fd; padding:2px 6px; border-radius:4px; color:#0d47a1; border:1px solid #bbdefb;">Admin</span>';
                    }

                    // Badge de Tipo (Etiquetas de colores)
                    let claseBadge = 'badge-gasto'; 
                    let textoTipo = m.tipo;

                    if (esEntrada) claseBadge = 'badge-ingreso';
                    if (tipoUpper === 'VENTA') claseBadge = 'badge-primary'; 
                    if (tipoUpper === 'REPARACION') claseBadge = 'badge-warning'; 
                    
                    if (tipoUpper === 'CIERRE') {
                        claseBadge = 'badge-dark';
                        textoTipo = 'Cierre Caja';
                    }
                    if (tipoUpper === 'RETIRO') {
                        claseBadge = 'badge-secondary'; // Gris claro
                    }

                    // Botón Foto
                    let btnFoto = '<span class="text-muted small">-</span>';
                    if (m.foto) {
                        btnFoto = `<a href="uploads/${m.foto}" target="_blank" class="btn-evidencia">
                                    <i class="fas fa-image"></i> Ver
                                   </a>`;
                    }

                    // Hora
                    let hora = '--:--';
                    try { if(m.fecha) hora = m.fecha.split(' ')[1].substring(0,5); } catch(e){}

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${hora}</td>
                        <td class="text-center">${origenBadge}</td>
                        <td><span class="badge-tipo ${claseBadge}">${textoTipo}</span></td>
                        <td>${m.categoria || '-'}</td>
                        <td>${m.descripcion}</td>
                        <td class="text-center">${btnFoto}</td>
                        <td class="text-right font-weight-bold" style="color: ${colorMonto}">
                            ${signo} $${formatoDinero(monto)}
                        </td>
                        <td class="text-center">
                            <button class="btn-icon btn-primary" onclick="editarMovimiento(${m.id})" title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="btn-icon btn-danger" onclick="eliminarMovimiento(${m.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center p-4 text-muted">No hay movimientos registrados en esta fecha.</td></tr>';
            }
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger p-3">Error al cargar datos.</td></tr>';
        });
}

// --- 3. EDICIÓN CON LLAVE MAESTRA ---
function editarMovimiento(id) {
    Swal.fire({
        title: 'Modo Edición',
        text: 'Ingresa la Llave Maestra para editar:',
        input: 'password',
        inputAttributes: { autocapitalize: 'off', placeholder: '••••••' },
        showCancelButton: true,
        confirmButtonText: 'Acceder',
        confirmButtonColor: '#007bff',
        showLoaderOnConfirm: true,
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
                    if (data.success) {
                        abrirModalEdicion(data.data);
                    } else {
                        Swal.fire('Acceso Denegado', data.error || 'Llave incorrecta', 'error');
                    }
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
    
    // Asignar tipo (INGRESO/GASTO/VENTA...)
    const inputTipo = document.getElementById('inputTipo');
    // Si el tipo es especial (Venta/Reparación), lo mostramos como INGRESO para que el select funcione visualmente
    if(movimiento.tipo !== 'GASTO' && movimiento.tipo !== 'INGRESO') {
        inputTipo.value = (parseFloat(movimiento.ingreso) > 0) ? 'INGRESO' : 'GASTO';
    } else {
        inputTipo.value = movimiento.tipo;
    }
    
    // Cargar categorías
    actualizarCategorias(movimiento.categoria);
    
    // Timeout para asegurar que el select se llenó antes de asignar valor
    setTimeout(() => { document.getElementById('inputCategoria').value = movimiento.categoria; }, 50);

    document.getElementById('inputDescripcion').value = movimiento.descripcion;
    document.getElementById('inputMonto').value = movimiento.monto_real;

    // Foto
    const preview = document.getElementById('previewContainer');
    const img = document.getElementById('imgPreview');
    if (movimiento.foto_url) { 
        img.src = movimiento.foto_url; 
        preview.style.display = 'block'; 
    } else { 
        preview.style.display = 'none'; 
        img.src = ''; 
    }
    
    document.getElementById('inputFoto').value = ''; 
    document.getElementById('modalNuevo').style.display = 'flex';
}

// --- 4. ELIMINACIÓN CON LLAVE MAESTRA ---
function eliminarMovimiento(id) {
    Swal.fire({
        title: 'Eliminar Registro',
        text: 'Ingresa la Llave Maestra:',
        input: 'password',
        showCancelButton: true,
        confirmButtonText: 'Eliminar',
        confirmButtonColor: '#d33',
        showLoaderOnConfirm: true,
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
                        Swal.fire('Eliminado', 'El registro ha sido borrado.', 'success'); 
                        cargarMovimientos(); 
                    } else {
                        Swal.fire('Error', data.error || 'Llave incorrecta', 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Fallo de conexión', 'error'));
        }
    });
}

// --- 5. MANEJO DEL FORMULARIO ---
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
                Swal.fire('Éxito', 'Operación completada', 'success'); 
                cerrarModal(); 
                cargarMovimientos(); 
            } else {
                Swal.fire('Error', data.error || 'Error al guardar', 'error');
            }
        })
        .catch(err => Swal.fire('Error', 'Fallo de conexión', 'error'));
    });
}

function abrirModalNuevo() {
    form.reset();
    document.getElementById('inputId').value = '';
    document.getElementById('modalTitle').textContent = 'Registrar Movimiento';
    const btnGuardar = document.querySelector('#formGasto button[type="submit"]');
    if(btnGuardar) btnGuardar.textContent = 'Guardar Registro';
    
    document.getElementById('previewContainer').style.display = 'none';
    document.getElementById('inputTipo').value = 'GASTO';
    actualizarCategorias();
    
    document.getElementById('modalNuevo').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalNuevo').style.display = 'none';
}

function formatoDinero(amount) {
    return parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}