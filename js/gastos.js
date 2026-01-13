document.addEventListener('DOMContentLoaded', () => {
    cargarMovimientos();
    actualizarCategorias();
    
    // Listener para vista previa de imagen
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

const catsGastos = ['Alimentos', 'Transporte', 'Servicios', 'Proveedores', 'Nómina', 'Mantenimiento', 'Retiro', 'Otros'];
const catsIngresos = ['Ingreso Extra', 'Inversión', 'Devolución Proveedor', 'Otros'];

function actualizarCategorias() {
    const tipo = document.getElementById('inputTipo').value;
    const select = document.getElementById('inputCategoria');
    select.innerHTML = '';
    
    const lista = tipo === 'GASTO' ? catsGastos : catsIngresos;
    
    lista.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c;
        opt.textContent = c;
        select.appendChild(opt);
    });
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
                    const valIngreso = parseFloat(m.ingreso) || 0;
                    const valEgreso = parseFloat(m.egreso) || 0;
                    const esEntrada = valIngreso > 0;
                    
                    const monto = esEntrada ? valIngreso : valEgreso;
                    const signo = esEntrada ? '+' : '-';
                    const colorMonto = esEntrada ? '#28a745' : '#dc3545'; 
                    
                    let claseBadge = 'badge-gasto'; 
                    if (esEntrada) {
                        claseBadge = 'badge-ingreso'; 
                    }

                    let btnFoto = '<span class="text-muted small">-</span>';
                    if (m.foto) {
                        btnFoto = `<a href="uploads/${m.foto}" target="_blank" class="btn-evidencia">
                                    <i class="fas fa-image"></i> Ver
                                   </a>`;
                    }

                    let hora = '--:--';
                    try {
                        if(m.fecha) hora = m.fecha.split(' ')[1].substring(0,5);
                    } catch(e) {}

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
                            <button class="btn-icon btn-danger" onclick="eliminarMovimiento(${m.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center p-3">No hay movimientos registrados.</td></tr>';
            }
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error al cargar datos.</td></tr>';
        });
}

// Envío del formulario
const form = document.getElementById('formGasto');
if(form) {
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(form);

        Swal.fire({
            title: 'Guardando...',
            text: 'Subiendo información y evidencia',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch('api/gastos.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                Swal.fire('Éxito', 'Movimiento registrado correctamente', 'success');
                cerrarModal();
                cargarMovimientos();
            } else {
                Swal.fire('Error', data.error || 'No se pudo guardar', 'error');
            }
        })
        .catch(err => Swal.fire('Error', 'Fallo de conexión', 'error'));
    });
}

function eliminarMovimiento(id) {
    // CAMBIO: Pedir contraseña (Llave Maestra)
    Swal.fire({
        title: 'Requiere Autorización',
        text: "Ingresa la Llave Maestra para eliminar este registro:",
        input: 'password',
        inputAttributes: {
            autocapitalize: 'off',
            placeholder: 'Llave Maestra'
        },
        showCancelButton: true,
        confirmButtonText: 'Eliminar',
        confirmButtonColor: '#d33',
        cancelButtonText: 'Cancelar',
        showLoaderOnConfirm: true,
        preConfirm: (llave) => {
            if (!llave) {
                Swal.showValidationMessage('Por favor ingresa la llave maestra');
            }
            return llave;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action', 'eliminar');
            fd.append('id', id);
            fd.append('llave_maestra', result.value); // Enviamos la llave al servidor

            fetch('api/gastos.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        Swal.fire('Eliminado', 'El registro ha sido borrado.', 'success');
                        cargarMovimientos();
                    } else {
                        Swal.fire('Acceso Denegado', data.error || 'Llave incorrecta', 'error');
                    }
                })
                .catch(err => Swal.fire('Error', 'Fallo de conexión', 'error'));
        }
    });
}

// Modales y Utilidades
function abrirModalNuevo() {
    if(form) form.reset();
    const prev = document.getElementById('previewContainer');
    if(prev) prev.style.display = 'none';
    actualizarCategorias(); 
    document.getElementById('modalNuevo').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalNuevo').style.display = 'none';
}

function formatoDinero(amount) {
    return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}