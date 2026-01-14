document.addEventListener('DOMContentLoaded', () => {
    // 1. Primero ajustamos la fecha a México
    inicializarFecha(); 
    
    // 2. Cargamos categorías iniciales
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
            }
        });
    }
});

const catsGastos = ['Alimentos', 'Transporte', 'Servicios', 'Proveedores', 'Nómina', 'Mantenimiento', 'Retiro', 'Otros'];
const catsIngresos = ['Ingreso Extra', 'Inversión', 'Devolución Proveedor', 'Otros'];

// --- NUEVA FUNCIÓN: CORREGIR FECHA ---
function inicializarFecha() {
    const filtroFecha = document.getElementById('filtroFecha');
    if (!filtroFecha) return;

    // Obtenemos la fecha actual forzada a la zona horaria de Ciudad de México
    // 'en-CA' nos da el formato YYYY-MM-DD directo
    const fechaMexico = new Date().toLocaleDateString('en-CA', {
        timeZone: 'America/Mexico_City',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });

    filtroFecha.value = fechaMexico;
    console.log("📅 Fecha ajustada a:", fechaMexico);

    // Una vez puesta la fecha correcta, cargamos los datos
    cargarMovimientos();
}

// Función para llenar el select de categorías según si es Gasto o Ingreso
function actualizarCategorias() {
    const tipo = document.getElementById('inputTipo').value;
    const select = document.getElementById('inputCategoria');
    
    const valorActual = select.value; 
    
    select.innerHTML = '';
    const lista = tipo === 'GASTO' ? catsGastos : catsIngresos;
    
    lista.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c;
        opt.textContent = c;
        select.appendChild(opt);
    });

    if (lista.includes(valorActual)) {
        select.value = valorActual;
    }
}

function cargarMovimientos() {
    const fecha = document.getElementById('filtroFecha').value;
    const tipo = document.getElementById('filtroTipo').value;
    const tbody = document.getElementById('tablaBody');
    
    tbody.innerHTML = '<tr><td colspan="7" class="text-center">Cargando...</td></tr>';

    fetch(`api/gastos.php?action=listar&fecha=${fecha}&tipo=${tipo}`)
        .then(res => res.json())
        .then(data => {
            tbody.innerHTML = '';
            if (data.success && data.data.length > 0) {
                data.data.forEach(m => {
                    // Calcular montos reales
                    const valIngreso = parseFloat(m.ingreso) || 0;
                    const valEgreso = parseFloat(m.egreso) || 0;
                    // Si hay dinero en la columna 'ingreso', es una entrada
                    const esEntrada = valIngreso > 0;
                    
                    const monto = esEntrada ? valIngreso : valEgreso;
                    const signo = esEntrada ? '+' : '-';
                    const colorMonto = esEntrada ? '#28a745' : '#dc3545';
                    
                    // Lógica para el badge (etiqueta)
                    let claseBadge = 'badge-gasto'; 
                    if (esEntrada) {
                        claseBadge = 'badge-ingreso'; 
                    }
                    // Si es REPARACION o VENTA, también le ponemos estilo de ingreso
                    if (m.tipo === 'VENTA') claseBadge = 'badge-primary'; // Azul (si tuvieras css para este)
                    if (m.tipo === 'REPARACION') claseBadge = 'badge-warning'; // Naranja

                    // Botón de evidencia
                    let btnFoto = '<span class="text-muted small">-</span>';
                    if (m.foto) {
                        btnFoto = `<a href="uploads/${m.foto}" target="_blank" class="btn-evidencia">
                                    <i class="fas fa-image"></i> Ver
                                   </a>`;
                    }

                    // Formato de hora
                    let hora = '--:--';
                    try { if(m.fecha) hora = m.fecha.split(' ')[1].substring(0,5); } catch(e){}

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${hora}</td>
                        <td><span class="badge-tipo ${claseBadge}">${m.tipo}</span></td>
                        <td>${m.categoria || '-'}</td>
                        <td>${m.descripcion}</td>
                        <td class="text-center">${btnFoto}</td>
                        <td class="text-right font-weight-bold" style="color: ${colorMonto}">
                            ${signo}${formatoDinero(monto)}
                        </td>
                        <td class="text-center">
                            <button class="btn-icon btn-primary" onclick="editarMovimiento(${m.id})" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon btn-danger" onclick="eliminarMovimiento(${m.id})" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center p-3">No hay movimientos registrados para esta fecha.</td></tr>';
            }
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error al cargar datos.</td></tr>';
        });
}

// ==========================================
// EDICIÓN CON LLAVE MAESTRA
// ==========================================
function editarMovimiento(id) {
    Swal.fire({
        title: 'Modo Edición',
        text: "Ingresa la Llave Maestra para editar este registro:",
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
            const llave = result.value;
            const fd = new FormData();
            fd.append('action', 'obtener');
            fd.append('id', id);
            fd.append('llave_maestra', llave);

            fetch('api/gastos.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        abrirModalEdicion(data.data); 
                    } else {
                        Swal.fire('Acceso Denegado', data.error || 'Contraseña incorrecta', 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Fallo de conexión con el servidor', 'error'));
        }
    });
}

function abrirModalEdicion(movimiento) {
    document.getElementById('modalTitle').textContent = 'Editar Movimiento';
    
    const btnGuardar = document.querySelector('#formGasto button[type="submit"]');
    if(btnGuardar) btnGuardar.textContent = 'Actualizar Cambios';
    
    document.getElementById('inputId').value = movimiento.id; 
    
    const inputTipo = document.getElementById('inputTipo');
    inputTipo.value = movimiento.tipo; 
    
    actualizarCategorias(); 
    
    // Pequeño timeout para asegurar que el select se llenó
    setTimeout(() => {
        document.getElementById('inputCategoria').value = movimiento.categoria;
    }, 50);

    document.getElementById('inputDescripcion').value = movimiento.descripcion;
    document.getElementById('inputMonto').value = movimiento.monto_real;

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

// ==========================================
// ELIMINACIÓN CON LLAVE MAESTRA
// ==========================================
function eliminarMovimiento(id) {
    Swal.fire({
        title: 'Eliminar Registro',
        text: "Ingresa la Llave Maestra para confirmar el borrado:",
        input: 'password',
        inputAttributes: { autocapitalize: 'off' },
        showCancelButton: true,
        confirmButtonText: 'Eliminar Definitivamente',
        confirmButtonColor: '#d33',
        showLoaderOnConfirm: true,
        preConfirm: (llave) => {
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

// ==========================================
// MANEJO DEL FORMULARIO
// ==========================================
const form = document.getElementById('formGasto');
if(form) {
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(form);

        Swal.fire({
            title: 'Guardando...',
            text: 'Procesando cambios',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

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
    return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}