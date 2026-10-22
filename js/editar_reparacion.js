/* =========================================
 * LÓGICA PARA EDITAR REPARACIÓN (JS PURO)
 * Versión Final: Bitácora Única + Fotos Comprimidas Sin Duplicados + Lightbox + Pagos + Checklist
 * ========================================= */

let archivosBitacora = [];

// --- COMPRESOR DE IMÁGENES EN CLIENTE (MÁX 1280px, JPG 80%) ---
function comprimirImagen(file, maxWidth = 1280, quality = 0.8) {
    return new Promise((resolve) => {
        const nombreLimpio = (file.name ? file.name.replace(/\.[^/.]+$/, "") : "foto_" + Date.now()) + ".jpg";
        
        if (!file.type || !file.type.startsWith('image/')) {
            resolve({ blob: file, name: file.name || nombreLimpio, previewUrl: URL.createObjectURL(file) });
            return;
        }
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = (event) => {
            const img = new Image();
            img.src = event.target.result;
            img.onload = () => {
                let width = img.width;
                let height = img.height;
                if (width > maxWidth) {
                    height = Math.round((height * maxWidth) / width);
                    width = maxWidth;
                }
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                canvas.toBlob((blob) => {
                    if (blob) {
                        resolve({
                            blob: blob,
                            name: nombreLimpio,
                            previewUrl: URL.createObjectURL(blob)
                        });
                    } else {
                        resolve({ blob: file, name: file.name || nombreLimpio, previewUrl: URL.createObjectURL(file) });
                    }
                }, 'image/jpeg', quality);
            };
            img.onerror = () => resolve({ blob: file, name: file.name || nombreLimpio, previewUrl: URL.createObjectURL(file) });
        };
        reader.onerror = () => resolve({ blob: file, name: file.name || nombreLimpio, previewUrl: URL.createObjectURL(file) });
    });
}

// --- FUNCIONES MAPA CAJA ---
function abrirMapaCaja() {
    const modalEl = document.getElementById('modalMapaCaja');
    try {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    } catch(e) {
        modalEl.style.display = 'block';
        modalEl.classList.add('show');
    }
    dibujarCaja();
}

function cerrarModalMapa() {
    const modalEl = document.getElementById('modalMapaCaja');
    try {
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
    } catch(e) {
        modalEl.style.display = 'none';
        modalEl.classList.remove('show');
    }
}

function dibujarCaja() {
    const grid = document.getElementById('grid-caja');
    grid.innerHTML = '<div class="col-12 text-center"><i class="fas fa-spinner fa-spin"></i> Cargando caja...</div>';

    fetch('api/obtener_lugares.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                grid.innerHTML = 'Error al cargar datos.';
                return;
            }

            grid.innerHTML = ''; 
            const ocupados = data.data; 
            
            const filas = ['A', 'B', 'C', 'D'];
            const columnas = 12;

            filas.forEach(fila => {
                for (let col = 1; col <= columnas; col++) {
                    const coord = `${fila}${col}`; 
                    
                    const celda = document.createElement('div');
                    celda.className = 'lugar-box';
                    celda.textContent = coord;

                    const ocupacion = ocupados.find(o => o.ubicacion === coord);

                    if (ocupacion) {
                        if (typeof REPARACION_ID !== 'undefined' && ocupacion.id == REPARACION_ID) {
                             celda.classList.add('seleccionado');
                        } else {
                            celda.classList.add('ocupado');
                            celda.title = `Ocupado por Orden #${ocupacion.id}`;
                            celda.onclick = () => {
                                Swal.fire({
                                    title: `Lugar ${coord} Ocupado`,
                                    text: `Este lugar lo tiene la orden #${ocupacion.id}. ¿Quieres ver esa reparación?`,
                                    icon: 'question',
                                    showCancelButton: true,
                                    confirmButtonText: 'Sí, ir a verla',
                                    cancelButtonText: 'Cancelar'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        window.open(`editar_reparacion.php?id=${ocupacion.id}`, '_blank');
                                    }
                                });
                            };
                        }
                    } else {
                        celda.onclick = () => seleccionarLugar(coord);
                    }
                    grid.appendChild(celda);
                }
            });
        });
}

function seleccionarLugar(coordenada) {
    document.getElementById('ubicacion').value = coordenada;
    cerrarModalMapa();
    Swal.fire({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        icon: 'success',
        title: `Asignado a ${coordenada}`,
        timer: 2000
    });
}

// --- PREVISUALIZAR Y COMPRIMIR FOTOS DE BITÁCORA ---
async function previsualizarBitacora(inputEl) {
    const container = document.getElementById('bitacora-preview-box');
    if (inputEl && inputEl.files && inputEl.files.length > 0) {
        for (let file of Array.from(inputEl.files)) {
            const itemComprimido = await comprimirImagen(file);
            archivosBitacora.push(itemComprimido);
        }
        inputEl.value = '';
        renderizarPreviewsBitacora(container);
    }
}

function renderizarPreviewsBitacora(container) {
    if (!container) return;
    container.innerHTML = '';
    if (archivosBitacora.length === 0) {
        container.style.display = 'none';
        return;
    }
    container.style.display = 'grid';
    archivosBitacora.forEach((item, idx) => {
        const div = document.createElement('div');
        div.className = 'preview-thumb-item';
        div.innerHTML = `
            <img src="${item.previewUrl}">
            <button type="button" onclick="quitarFotoPreview(${idx})"><i class="fas fa-times"></i></button>
        `;
        container.appendChild(div);
    });
}

window.quitarFotoPreview = function(idx) {
    archivosBitacora.splice(idx, 1);
    renderizarPreviewsBitacora(document.getElementById('bitacora-preview-box'));
};

// --- GUARDAR BITÁCORA RÁPIDA (SIN DUPLICADOS) ---
window.guardarBitacoraRapida = function() {
    const notaEl = document.getElementById('nota_bitacora');
    const nota = notaEl ? notaEl.value.trim() : '';

    if (nota === '' && archivosBitacora.length === 0) {
        Swal.fire('Atención', 'Escribe una nota técnica o adjunta una foto para la bitácora.', 'warning');
        return;
    }

    let fd = new FormData();
    fd.append('action', 'agregar_bitacora');
    fd.append('id', REPARACION_ID);
    fd.append('nota_bitacora', nota);

    // Enviamos únicamente 'evidencias[]' para que se guarde 1 sola vez por foto
    archivosBitacora.forEach((item) => {
        fd.append('evidencias[]', item.blob, item.name);
    });

    Swal.fire({
        title: 'Registrando en bitácora...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch('api/editar_reparacion.php', {
        method: 'POST',
        body: fd
    })
    .then(async res => {
        const texto = await res.text();
        try {
            return JSON.parse(texto);
        } catch (e) {
            throw new Error(texto || 'Respuesta inválida del servidor');
        }
    })
    .then(data => {
        if (data.success) {
            Swal.fire({
                toast: true, position: 'top-end', icon: 'success',
                title: 'Registrado en bitácora', showConfirmButton: false, timer: 1000
            }).then(() => location.reload());
        } else {
            Swal.fire('Error', data.message || 'No se pudo guardar la bitácora', 'error');
        }
    })
    .catch(err => {
        console.error("Error en bitácora:", err);
        Swal.fire('Error', 'No se pudo conectar con la API: ' + err.message.substring(0, 100), 'error');
    });
};

// --- VISOR LIGHTBOX LIQUID GLASS ---
let zoomActivo = false;
window.abrirLightbox = function(url, comentario, fecha, usuario, idHistorial) {
    const modal = document.getElementById('modalLightbox');
    const img = document.getElementById('lb-img');
    const lblCom = document.getElementById('lb-comentario');
    const lblMeta = document.getElementById('lb-meta');
    const btnDown = document.getElementById('lb-download');
    const btnDel = document.getElementById('lb-delete-btn');

    zoomActivo = false;
    img.classList.remove('zoomed');
    document.getElementById('lb-zoom-icon').className = 'fas fa-search-plus';

    img.src = url;
    lblCom.textContent = comentario || 'Evidencia Fotográfica';
    lblMeta.innerHTML = `<i class="far fa-calendar-alt"></i> ${fecha} &nbsp;|&nbsp; <i class="far fa-user"></i> ${usuario}`;
    btnDown.href = url;

    if (btnDel) {
        btnDel.onclick = () => eliminarEvidencia(idHistorial);
    }

    modal.style.display = 'flex';
};

window.cerrarLightbox = function(e) {
    if (e && e.target.id !== 'modalLightbox') return;
    document.getElementById('modalLightbox').style.display = 'none';
    document.getElementById('lb-img').src = '';
};

window.alternarZoomLightbox = function() {
    const img = document.getElementById('lb-img');
    const icon = document.getElementById('lb-zoom-icon');
    zoomActivo = !zoomActivo;
    if (zoomActivo) {
        img.classList.add('zoomed');
        icon.className = 'fas fa-search-minus';
    } else {
        img.classList.remove('zoomed');
        icon.className = 'fas fa-search-plus';
    }
};

window.eliminarEvidencia = function(idHistorial) {
    Swal.fire({
        title: '¿Eliminar esta fotografía?',
        text: 'Se borrará el archivo del servidor de forma permanente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff3b30',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((res) => {
        if (res.isConfirmed) {
            let fd = new FormData();
            fd.append('action', 'eliminar_evidencia');
            fd.append('id_historial', idHistorial);

            fetch('api/editar_reparacion.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    Swal.fire('Error', data.message || 'No se pudo eliminar', 'error');
                }
            });
        }
    });
};

// --- FUNCIONES FINANCIERAS Y GUARDADO PRINCIPAL ---
function calcularDeuda() {
    let monto = parseFloat(document.getElementById('monto').value) || 0;
    let adelanto = parseFloat(document.getElementById('adelanto').value) || 0;
    let deuda = monto - adelanto;
    if (deuda < 0) deuda = 0;
    document.getElementById('deuda').value = deuda;
}

function agregarAbono() {
    let montoAbono = parseFloat(document.getElementById('nuevo_abono_monto').value);
    
    if (!montoAbono || montoAbono <= 0) {
        Swal.fire('Error', 'Ingrese un monto válido para el abono.', 'warning');
        return;
    }

    let montoTotal = parseFloat(document.getElementById('monto').value) || 0;
    let deudaActual = parseFloat(document.getElementById('deuda').value) || 0;

    if (montoAbono > deudaActual) {
        Swal.fire('Cuidado', `El abono ($${montoAbono}) no puede ser mayor a la deuda actual ($${deudaActual}).`, 'warning');
        return;
    }

    const modelo = document.getElementById('modelo').value;
    const marca = document.getElementById('marca_celular').value;

    Swal.fire({
        title: '¿Cobrar Abono en Caja?',
        html: `Se enviará un abono por <b>$${montoAbono.toFixed(2)}</b> para el equipo <b>${marca} ${modelo}</b> al carrito global.`,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#007aff',
        confirmButtonText: '<i class="fas fa-cart-plus"></i> Mandar a Caja',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const itemGlobal = {
                id: REPARACION_ID,
                tipo: 'reparacion',
                accion_reparacion: 'abonar',
                nombre: 'Abono: ' + marca + ' ' + modelo,
                costo_total: montoTotal,
                a_cobrar: montoAbono
            };

            if (typeof agregarAlCarritoGlobal === 'function') {
                agregarAlCarritoGlobal(itemGlobal);
                document.getElementById('nuevo_abono_monto').value = '';
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: 'Abono enviado al carrito', showConfirmButton: false, timer: 2000
                });
            } else {
                Swal.fire('Error', 'El carrito global no está conectado.', 'error');
            }
        }
    });
}

function guardarCambios() {
    const estadoSeleccionado = document.getElementById('selectEstado').value;
    const ubicacionActual = document.getElementById('ubicacion').value.trim();

    if (estadoSeleccionado === 'Terminado' && ubicacionActual === '') {
        Swal.fire({
            title: '¡Falta la Ubicación!',
            text: 'Para marcar el equipo como Terminado, debes indicar en qué repisa o caja lo dejaste para no perderlo.',
            icon: 'warning',
            confirmButtonText: '<i class="fas fa-map-marker-alt"></i> Asignar Lugar',
            confirmButtonColor: '#007aff'
        }).then(() => {
            abrirMapaCaja();
        });
        return;
    }

    let formData = new FormData();
    formData.append('action', 'guardar');
    formData.append('id', REPARACION_ID);
    
    formData.append('nombre_cliente', document.getElementById('nombre_cliente').value);
    formData.append('telefono', document.getElementById('telefono').value);
    formData.append('tipo_reparacion', document.getElementById('tipo_reparacion').value);
    formData.append('marca_celular', document.getElementById('marca_celular').value);
    formData.append('modelo', document.getElementById('modelo').value);
    formData.append('monto', document.getElementById('monto').value);
    formData.append('adelanto', document.getElementById('adelanto').value);
    formData.append('info_extra', document.getElementsByName('info_extra')[0].value);
    formData.append('estado', document.getElementById('selectEstado').value);
    formData.append('fecha_estimada', document.getElementById('fecha_estimada').value);     
    formData.append('ubicacion', document.getElementById('ubicacion').value); 
// --- NUEVO: GUARDAR SEGURIDAD ---
    formData.append('tipo_pass', document.getElementById('tipo_pass').value);
    if (document.getElementById('tipo_pass').value === 'Patron') {
        formData.append('codigo_pass', document.getElementById('codigo_pass_patron').value);
    } else {
        formData.append('codigo_pass', document.getElementById('codigo_pass_txt').value);
    }
    Swal.fire({
        title: 'Guardando...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading() }
    });

    fetch('api/editar_reparacion.php', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        const texto = await response.text();
        try {
            return JSON.parse(texto);
        } catch (e) {
            throw new Error(texto || 'Respuesta inválida del servidor');
        }
    })
    .then(data => {
        if (data.success) {
            Swal.fire('Guardado', 'Cambios registrados correctamente', 'success')
            .then(() => { location.reload(); });
        } else {
            Swal.fire('Error', data.message || 'Error al guardar', 'error');
        }
    })
    .catch(error => {
        console.error("Error al guardar:", error);
        Swal.fire('Error', 'Detalle: ' + error.message.substring(0, 120), 'error');
    });
}

function entregarReparacion() {
    const deudaInput = document.getElementById('deuda');
    const saldoPendiente = parseFloat(deudaInput.value) || 0;
    const costoTotal = parseFloat(document.getElementById('monto').value) || 0;
    const modelo = document.getElementById('modelo').value;
    const marca = document.getElementById('marca_celular').value;

    Swal.fire({
        title: '¿Mandar a Caja para Entregar?',
        html: `El equipo <b>${marca} ${modelo}</b> se enviará al carrito global.<br><br>Saldo a cobrar: <b>$${saldoPendiente.toFixed(2)}</b>`,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#007aff',
        confirmButtonText: '<i class="fas fa-cart-plus"></i> Mandar a Caja',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const itemGlobal = {
                id: REPARACION_ID,
                tipo: 'reparacion',
                accion_reparacion: 'liquidar',
                nombre: 'Entrega: ' + marca + ' ' + modelo,
                costo_total: costoTotal,
                a_cobrar: saldoPendiente
            };

            if (typeof agregarAlCarritoGlobal === 'function') {
                agregarAlCarritoGlobal(itemGlobal);
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: 'Enviado al Carrito Flotante', showConfirmButton: false, timer: 2000
                });
            } else {
                Swal.fire('Error', 'El carrito global no está conectado.', 'error');
            }
        }
    });
}

function imprimirTicket() {
    window.open(TICKET_URL, '_blank');
}

// ==========================================
// MAGIA DEL CHECKLIST DE REPARACIÓN 
// ==========================================

let estadoAnterior = '';

document.addEventListener('DOMContentLoaded', () => {
    const selectEstadoGlobal = document.getElementById('selectEstado');
    
    if (selectEstadoGlobal) {
        estadoAnterior = selectEstadoGlobal.value;
        
        selectEstadoGlobal.addEventListener('change', function() {
            if (this.value === 'Terminado') {
                window.abrirChecklist();
            } else {
                estadoAnterior = this.value;
            }
        });
    }
});

window.abrirChecklist = function() {
    document.querySelectorAll('.checklist-check').forEach(cb => cb.checked = false);
    
    const modalChecklist = document.getElementById('modalChecklist');
    if(modalChecklist) {
        modalChecklist.style.display = 'flex';
    }
};

window.cancelarChecklist = function() {
    document.getElementById('modalChecklist').style.display = 'none';
    const selectEstadoGlobal = document.getElementById('selectEstado');
    if (selectEstadoGlobal) {
        selectEstadoGlobal.value = estadoAnterior;
    }
};

window.confirmarChecklist = function() {
    const checks = document.querySelectorAll('.checklist-check');
    const allChecked = Array.from(checks).every(cb => cb.checked);
    
    if (!allChecked) {
        Swal.fire({
            title: '¿Confirmar con pendientes?',
            text: 'Aún no has marcado todas las pruebas. ¿Estás 100% seguro de que el equipo está listo para entregarse?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#34c759',
            cancelButtonColor: '#8e8e93',
            confirmButtonText: 'Sí, forzar Terminado',
            cancelButtonText: 'Revisar de nuevo'
        }).then((result) => {
            if (result.isConfirmed) {
                window.finalizarChecklist();
            }
        });
    } else {
        window.finalizarChecklist();
    }
};

window.finalizarChecklist = function() {
    document.getElementById('modalChecklist').style.display = 'none';
    estadoAnterior = 'Terminado'; 
    
    Swal.fire({
        toast: true, position: 'top-end', icon: 'success',
        title: 'Calidad Aprobada', showConfirmButton: false, timer: 2000
    });

    let infoExtraInput = document.getElementsByName('info_extra')[0];
    if (infoExtraInput && !infoExtraInput.value.includes('[Checklist OK]')) {
        infoExtraInput.value = infoExtraInput.value ? infoExtraInput.value + ' | [Checklist OK]' : '[Checklist OK]';
    }
};

// ==========================================
// FUNCIÓN PARA IMPRIMIR ETIQUETA DIRECTA
// ==========================================
window.imprimirEtiquetaReparacion = function() {
    const codigo = CODIGO_BARRAS || REPARACION_ID; 
    const cliente = document.getElementById('nombre_cliente').value || 'Sin nombre';
    const marca = document.getElementById('marca_celular').value || '';
    const modelo = document.getElementById('modelo').value || '';
    const equipo = (marca + ' ' + modelo).trim() || 'Equipo';
    const falla = document.getElementById('tipo_reparacion').value || 'Revisión';

    if (!codigo) {
        Swal.fire('Error', 'No hay un código válido para imprimir.', 'error');
        return;
    }

    const url = `imprimir_etiqueta.php?codigo=${encodeURIComponent(codigo)}` +
                `&nombre=${encodeURIComponent(equipo)}` +
                `&cliente=${encodeURIComponent(cliente)}` +
                `&detalles=${encodeURIComponent(falla)}`;
                
    window.open(url, '_blank', 'width=450,height=550,scrollbars=no,resizable=no');
};

// ==========================================
// MOTOR CUSTOM DROPDOWN LIQUID GLASS
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
    const nativeSelect = document.getElementById('selectEstado');
    if (!nativeSelect) return;

    const dropdownOptions = document.getElementById('glassDropdownOptions');
    const dropdownText = document.getElementById('glassDropdownText');
    
    Array.from(nativeSelect.options).forEach(option => {
        const div = document.createElement('div');
        div.className = 'glass-option';
        
        if (option.selected) {
            div.classList.add('selected');
            dropdownText.innerHTML = option.text;
        }
        
        div.innerHTML = option.text;
        
        div.onclick = function() {
            document.querySelectorAll('.glass-option').forEach(el => el.classList.remove('selected'));
            this.classList.add('selected');
            dropdownText.innerHTML = this.innerHTML;
            nativeSelect.value = option.value;
            nativeSelect.dispatchEvent(new Event('change'));
            document.getElementById('glassDropdown').classList.remove('active');
        };
        
        dropdownOptions.appendChild(div);
    });

    document.addEventListener('click', function(e) {
        const customDropdown = document.getElementById('glassDropdown');
        if (customDropdown && !customDropdown.contains(e.target)) {
            customDropdown.classList.remove('active');
        }
    });
});

window.toggleGlassDropdown = function() {
    document.getElementById('glassDropdown').classList.toggle('active');
};

// ==========================================
// MÓDULO DE REFACCIONES Y GANANCIA NETA
// ==========================================

document.addEventListener('DOMContentLoaded', () => {
    if(typeof REPARACION_ID !== 'undefined') cargarPiezasUsadas();
});

let timeoutPiezas;
function buscarPiezaReparacion() {
    clearTimeout(timeoutPiezas);
    const q = document.getElementById('buscador_piezas').value.trim();
    const caja = document.getElementById('resultados_piezas');
    
    if(q.length < 2) { caja.style.display = 'none'; return; }

    timeoutPiezas = setTimeout(() => {
        fetch(`api/gestion_piezas.php?action=buscar_mercancia&q=${encodeURIComponent(q)}`)
        .then(res => res.json())
        .then(data => {
            caja.innerHTML = '';
            if(data.success && data.data.length > 0) {
                data.data.forEach(p => {
                    const codigoB = p.codigo_barras ? p.codigo_barras : 'S/C';
                    
                    caja.innerHTML += `
                        <div style="padding: 10px; border-bottom: 1px solid #eee; cursor: pointer; display: flex; justify-content: space-between; align-items: center;" onclick="agregarPiezaAReparacion(${p.id})">
                            <div>
                                <strong style="font-size: 13px; color: #1d1d1f; display: block;">
                                    ${p.tipo_repuesto} ${p.marca} ${p.modelo} 
                                    <span style="color:#007aff; font-family:monospace; font-size:12px; margin-left:5px; background: rgba(0,122,255,0.08); padding: 2px 6px; border-radius: 6px;">[${codigoB}]</span>
                                </strong>
                                <span style="font-size: 11px; color: #86868b;">Stock disponible: ${p.cantidad}</span>
                            </div>
                            <button class="glass-btn primary" style="height: 30px; padding: 0 10px; font-size: 12px; min-width: auto; width: auto;"><i class="fas fa-plus"></i></button>
                        </div>`;
                });
                caja.style.display = 'block';
            } else {
                caja.innerHTML = '<div style="padding: 10px; text-align: center; color: #86868b; font-size: 12px;">No hay piezas en stock o no existe.</div>';
                caja.style.display = 'block';
            }
        });
    }, 300);
}

function agregarPiezaAReparacion(id_mercancia) {
    Swal.fire({ title: 'Agregando pieza...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});
    
    let fd = new FormData();
    fd.append('action', 'agregar_pieza');
    fd.append('id_reparacion', REPARACION_ID);
    fd.append('id_mercancia', id_mercancia);

    fetch('api/gestion_piezas.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            document.getElementById('buscador_piezas').value = '';
            document.getElementById('resultados_piezas').style.display = 'none';
            Swal.close();
            cargarPiezasUsadas();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    });
}

window.cargarPiezasUsadas = function() {
    fetch(`api/gestion_piezas.php?action=listar_piezas&id_reparacion=${REPARACION_ID}`)
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            const tbody = document.getElementById('tabla_piezas_usadas');
            tbody.innerHTML = '';
            
            data.piezas.forEach(pz => {
                let celdaCosto = '';
                if (typeof ES_ADMIN !== 'undefined' && ES_ADMIN) {
                    celdaCosto = `<td style="padding: 10px 8px; text-align: right; color: #ff3b30; font-weight: 600;">-$${parseFloat(pz.costo_unitario).toFixed(2)}</td>`;
                }
                
                tbody.innerHTML += `
                    <tr style="border-bottom: 1px solid rgba(0,0,0,0.03);">
                        <td style="padding: 10px 8px; font-size: 13px; font-weight: 500;">${pz.nombre_pieza}</td>
                        ${celdaCosto}
                        <td style="padding: 10px 8px; text-align: right;">
                            <button type="button" onclick="eliminarPiezaReparacion(${pz.id})" style="background:rgba(255,59,48,0.1); color:#ff3b30; border:none; width:32px; height:32px; border-radius:8px; cursor:pointer; transition: 0.2s;">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>`;
            });

            if(data.piezas.length === 0) {
                const columnas = (typeof ES_ADMIN !== 'undefined' && ES_ADMIN) ? 3 : 2;
                tbody.innerHTML = `<tr><td colspan="${columnas}" style="padding: 15px; text-align: center; color: #86868b; font-size: 12px;">Sin refacciones extraídas del sistema aún.</td></tr>`;
            }

            if (typeof ES_ADMIN !== 'undefined' && ES_ADMIN) {
                const cobroCliente = parseFloat(data.finanzas.monto) || 0;
                const costoPiezas = parseFloat(data.finanzas.costo_piezas) || 0;
                const gananciaNeta = cobroCliente - costoPiezas;

                const lblCosto = document.getElementById('lbl_costo_piezas');
                const lblGanancia = document.getElementById('lbl_ganancia_neta');
                
                if(lblCosto) lblCosto.innerText = '$' + costoPiezas.toFixed(2);
                if(lblGanancia) lblGanancia.innerText = '$' + gananciaNeta.toFixed(2);
            }
        }
    });
};

window.eliminarPiezaReparacion = function(id_puente) {
    Swal.fire({
        title: '¿Remover pieza?',
        text: "Se devolverá 1 unidad al inventario de mercancía automáticamente.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff3b30',
        cancelButtonColor: '#8e8e93',
        confirmButtonText: '<i class="fas fa-undo"></i> Sí, remover',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Devolviendo stock...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});
            
            let fd = new FormData();
            fd.append('action', 'eliminar_pieza');
            fd.append('id_puente', id_puente);

            fetch('api/gestion_piezas.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    Swal.close();
                    cargarPiezasUsadas();
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }
    });
};

document.getElementById('monto').addEventListener('input', () => {
    cargarPiezasUsadas(); 
});

// ==========================================
// MOTOR DE SEGURIDAD (PIN, TEXTO, PATRÓN)
// ==========================================

let isDrawingPattern = false;
let currentPattern = [];

document.addEventListener('DOMContentLoaded', () => {
    // 1. Inicializar el motor visual del Patrón
    initPatternLock();

    // 2. Cargar los datos guardados en la BD
    const selectTipo = document.getElementById('tipo_pass');
    if (selectTipo && typeof TIPO_PASS_DB !== 'undefined') {
        selectTipo.value = TIPO_PASS_DB;
        cambiarTipoPass();

        if (TIPO_PASS_DB === 'PIN' || TIPO_PASS_DB === 'Texto') {
            document.getElementById('codigo_pass_txt').value = CODIGO_PASS_DB;
        } else if (TIPO_PASS_DB === 'Patron' && CODIGO_PASS_DB) {
            document.getElementById('codigo_pass_patron').value = CODIGO_PASS_DB;
            const parts = CODIGO_PASS_DB.split('-');
            parts.forEach((val, index) => {
                currentPattern.push(val);
                const dot = document.querySelector(`.patron-dot[data-val="${val}"]`);
                if (dot) {
                    dot.classList.add('active');
                    dot.innerText = index + 1; // Pone el número de orden
                }
            });
        }
    }
});

window.cambiarTipoPass = function() {
    const tipo = document.getElementById('tipo_pass').value;
    const cajaTxt = document.getElementById('caja_pass_texto');
    const cajaPatron = document.getElementById('caja_pass_patron');
    const lblTxt = document.getElementById('lbl_pass_texto');
    const inputTxt = document.getElementById('codigo_pass_txt');

    cajaTxt.style.display = 'none';
    cajaPatron.style.display = 'none';

    if (tipo === 'PIN') {
        cajaTxt.style.display = 'block';
        lblTxt.innerText = 'PIN Numérico';
        inputTxt.placeholder = 'Ej: 1234';
        inputTxt.type = 'number';
    } else if (tipo === 'Texto') {
        cajaTxt.style.display = 'block';
        lblTxt.innerText = 'Contraseña Alfanumérica';
        inputTxt.placeholder = 'Ej: Juan123';
        inputTxt.type = 'text';
    } else if (tipo === 'Patron') {
        cajaPatron.style.display = 'block';
    }
};

window.limpiarPatron = function() {
    currentPattern = [];
    document.getElementById('codigo_pass_patron').value = '';
    document.querySelectorAll('.patron-dot').forEach(dot => {
        dot.classList.remove('active');
        dot.innerText = '';
    });
};

function initPatternLock() {
    const dots = document.querySelectorAll('.patron-dot');
    const grid = document.getElementById('patronGrid');
    if (!grid) return;

    const addDot = (dot) => {
        const val = dot.getAttribute('data-val');
        if (!currentPattern.includes(val)) {
            currentPattern.push(val);
            dot.classList.add('active');
            dot.innerText = currentPattern.length; 
            document.getElementById('codigo_pass_patron').value = currentPattern.join('-');
        }
    };

    // Eventos para Computadora (Mouse)
    dots.forEach(dot => {
        dot.addEventListener('mousedown', (e) => {
            e.preventDefault();
            if(currentPattern.length > 0) limpiarPatron();
            isDrawingPattern = true;
            addDot(dot);
        });
        dot.addEventListener('mouseenter', (e) => {
            if(isDrawingPattern) addDot(dot);
        });
    });

    document.addEventListener('mouseup', () => { isDrawingPattern = false; });

    // Eventos para Celular (Pantalla Táctil)
    grid.addEventListener('touchstart', (e) => {
        e.preventDefault(); // Evita que se mueva la pantalla al dibujar
        if(currentPattern.length > 0) limpiarPatron();
        isDrawingPattern = true;
        const touch = e.touches[0];
        const dot = document.elementFromPoint(touch.clientX, touch.clientY);
        if (dot && dot.classList.contains('patron-dot')) addDot(dot);
    }, {passive: false});

    grid.addEventListener('touchmove', (e) => {
        e.preventDefault();
        if (!isDrawingPattern) return;
        const touch = e.touches[0];
        const dot = document.elementFromPoint(touch.clientX, touch.clientY);
        if (dot && dot.classList.contains('patron-dot')) addDot(dot);
    }, {passive: false});

    document.addEventListener('touchend', () => { isDrawingPattern = false; });
}

// ==========================================
// REPRODUCTOR ANIMADO DEL PATRÓN DE DESBLOQUEO
// ==========================================
let animacionPatronTimeout = [];
let patronActualAnimacion = [];

window.mostrarAnimacionPatron = function(patronStr) {
    if (!patronStr) return;
    patronActualAnimacion = patronStr.split('-');
    document.getElementById('secuenciaText').innerText = patronStr;
    document.getElementById('modalAnimacionPatron').style.display = 'flex';
    
    // Pequeño retraso para permitir que el modal se renderice y el SVG calcule sus medidas
    setTimeout(() => {
        reiniciarAnimacionPatron();
    }, 150);
};

window.cerrarAnimacionPatron = function(e) {
    if (e && e.target.id !== 'modalAnimacionPatron') return;
    document.getElementById('modalAnimacionPatron').style.display = 'none';
    detenerAnimacionPatron();
};

window.detenerAnimacionPatron = function() {
    animacionPatronTimeout.forEach(clearTimeout);
    animacionPatronTimeout = [];
};

window.reiniciarAnimacionPatron = function() {
    detenerAnimacionPatron();
    
    // Limpiar todos los puntos
    for(let i=1; i<=9; i++) {
        let dot = document.getElementById('anim-dot-'+i);
        if(dot) {
            dot.classList.remove('active');
            dot.innerText = '';
        }
    }
    // Limpiar las líneas dibujadas
    document.getElementById('svgLineasPatron').innerHTML = '';

    // Intervalo de la animación (400 milisegundos entre cada punto)
    let delay = 400; 
    let previousDotInfo = null;

    patronActualAnimacion.forEach((val, index) => {
        let to = setTimeout(() => {
            let dot = document.getElementById('anim-dot-'+val);
            
            // Si hay un punto anterior, dibujamos la línea hacia el punto actual
            if (previousDotInfo) {
                dibujarLineaSVG(previousDotInfo, val);
            }
            
            // Iluminar el punto actual y ponerle su número de orden
            dot.classList.add('active');
            dot.innerText = index + 1;

            previousDotInfo = val;

        }, delay * index); // Multiplicamos por index para que se ejecuten en cascada
        
        animacionPatronTimeout.push(to);
    });
};

// Dibuja una línea perfecta calculando el centro geométrico de cada punto
function dibujarLineaSVG(fromVal, toVal) {
    let svg = document.getElementById('svgLineasPatron');
    let dotFrom = document.getElementById('anim-dot-'+fromVal);
    let dotTo = document.getElementById('anim-dot-'+toVal);
    
    if (!dotFrom || !dotTo || !svg) return;

    let svgRect = svg.getBoundingClientRect();
    let fromRect = dotFrom.getBoundingClientRect();
    let toRect = dotTo.getBoundingClientRect();

    // Calcular el centro de cada círculo relativo al lienzo SVG
    let x1 = (fromRect.left - svgRect.left) + (fromRect.width / 2);
    let y1 = (fromRect.top - svgRect.top) + (fromRect.height / 2);
    let x2 = (toRect.left - svgRect.left) + (toRect.width / 2);
    let y2 = (toRect.top - svgRect.top) + (toRect.height / 2);

    let line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    line.setAttribute('x1', x1);
    line.setAttribute('y1', y1);
    line.setAttribute('x2', x2);
    line.setAttribute('y2', y2);
    line.setAttribute('stroke', '#5856d6'); // Color morado
    line.setAttribute('stroke-width', '5');
    line.setAttribute('stroke-linecap', 'round');
    
    svg.appendChild(line);
}