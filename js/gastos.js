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

    fetch(`api/gastos.php?action=listar&fecha=${fecha}&tipo=${tipo}&_t=${Date.now()}`)
        .then(res => res.json())
        .then(data => {
            tbody.innerHTML = '';
            if (data.success && data.data.length > 0) {
                data.data.forEach(m => {
                    // Determinar colores y signos
                    const esIngreso = m.tipo === 'INGRESO';
                    const claseBadge = esIngreso ? 'badge-ingreso' : 'badge-gasto';
                    const signo = esIngreso ? '+' : '-';
                    const monto = esIngreso ? m.ingreso : m.egreso;
                    
                    // Botón de evidencia
                    let btnFoto = '<span class="text-muted small">-</span>';
                    if (m.foto) {
                        btnFoto = `<a href="uploads/${m.foto}" target="_blank" class="btn-evidencia">
                                    <i class="fas fa-image"></i> Ver
                                   </a>`;
                    }

                    // Formatear hora
                    let hora = m.fecha.split(' ')[1].substring(0,5);

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${hora}</td>
                        <td><span class="badge-tipo ${claseBadge}">${m.tipo}</span></td>
                        <td>${m.categoria}</td>
                        <td>${m.descripcion}</td>
                        <td class="text-center">${btnFoto}</td>
                        <td class="text-right font-weight-bold" style="color: ${esIngreso?'green':'red'}">
                            ${signo}$${parseFloat(monto).toFixed(2)}
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
        .catch(err => console.error(err));
}

// Envío del formulario con FormData (para soportar archivos)
const form = document.getElementById('formGasto');
if(form) {
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        
        const formData = new FormData(form);
        // 'action' ya viene en un input hidden, o se puede agregar:
        // formData.append('action', 'guardar'); 

        Swal.fire({
            title: 'Guardando...',
            text: 'Subiendo información y evidencia',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch('api/gastos.php', {
            method: 'POST',
            body: formData
        })
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
    Swal.fire({
        title: '¿Eliminar?',
        text: "Se borrará el registro y la foto asociada.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, borrar',
        confirmButtonColor: '#d33'
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action', 'eliminar');
            fd.append('id', id);

            fetch('api/gastos.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        Swal.fire('Eliminado', '', 'success');
                        cargarMovimientos();
                    } else {
                        Swal.fire('Error', 'No se pudo eliminar', 'error');
                    }
                });
        }
    });
}

// Modales
function abrirModalNuevo() {
    form.reset();
    document.getElementById('previewContainer').style.display = 'none';
    actualizarCategorias(); // Cargar categorías por defecto (Gasto)
    document.getElementById('modalNuevo').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalNuevo').style.display = 'none';
}