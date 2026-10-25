/* =========================================
 * LÓGICA PARA EDITAR REPARACIÓN (V. FINAL UNIFICADA)
 * Pestañas + Autoguardado Inteligente + Seguridad Patrón + Multi-Fotos + Bitácora
 * ========================================= */

let archivosFormulario = [];
let archivosBitacora = [];
let isDrawingPattern = false;
let currentPattern = [];
let animacionPatronTimeout = [];
let patronActualAnimacion = [];

// --- INICIALIZADORES (TABS, AUTOSAVE INTELIGENTE Y PATRÓN) ---
document.addEventListener('DOMContentLoaded', () => {
    // 1. Código de Barras
    if (typeof CODIGO_BARRAS !== 'undefined' && document.getElementById("barcode-svg") && CODIGO_BARRAS.trim() !== "") {
        setTimeout(() => {
            JsBarcode("#barcode-svg", String(CODIGO_BARRAS), {
                format: "CODE128", width: 1.2, height: 65, displayValue: true, fontSize: 28, fontOptions: "bold", font: "Poppins"
            });
        }, 100);
    }

    // 2. Autoguardado SÓLO en selects estratégicos (El resto usa el botón manual "Forzar Guardado")
    const selectEstadoGlobal = document.getElementById('selectEstado');
    if (selectEstadoGlobal) {
        selectEstadoGlobal.addEventListener('change', autoGuardarSilencioso);
    }
    const selectTipoPass = document.getElementById('tipo_pass');
    if (selectTipoPass) {
        selectTipoPass.addEventListener('change', autoGuardarSilencioso);
    }
    const inputFecha = document.getElementById('fecha_estimada');
    if (inputFecha) {
        inputFecha.addEventListener('change', autoGuardarSilencioso);
    }

    // 3. Inicializar Patrón y Seguridad
    initPatternLock();
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
                    dot.innerText = index + 1;
                }
            });
        }
    }

    // 4. Dropdown de Estados (Liquid Glass)
    const nativeSelect = document.getElementById('selectEstado');
    const dropdownOptions = document.getElementById('glassDropdownOptions');
    const dropdownText = document.getElementById('glassDropdownText');
    if (nativeSelect && dropdownOptions) {
        Array.from(nativeSelect.options).forEach(option => {
            const div = document.createElement('div');
            div.className = 'glass-option';
            if (option.selected) { div.classList.add('selected'); dropdownText.innerHTML = option.text; }
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
            if (customDropdown && !customDropdown.contains(e.target)) customDropdown.classList.remove('active');
        });
    }

    // 5. Checklist nativo
    let estadoAnterior = nativeSelect ? nativeSelect.value : '';
    if (nativeSelect) {
        nativeSelect.addEventListener('change', function() {
            if (this.value === 'Terminado') {
                document.querySelectorAll('.checklist-check').forEach(cb => cb.checked = false);
                document.getElementById('modalChecklist').style.display = 'flex';
            } else { estadoAnterior = this.value; }
        });
    }

    if(typeof REPARACION_ID !== 'undefined') cargarPiezasUsadas();
});

// --- FUNCIONES DE PESTAÑAS (TABS) Y VISIBILIDAD LATERAL ---
window.abrirTab = function(tabId, btnElement) {
    // 1. Cambiar la pestaña activa
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.glass-tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    btnElement.classList.add('active');

    // 2. Mostrar/Ocultar el Código de Barras según la pestaña
    const cardBarcode = document.getElementById('card-barcode');
    if (cardBarcode) {
        if (tabId === 'tab-general') {
            cardBarcode.style.display = 'block'; // Lo muestra en Información General
        } else {
            cardBarcode.style.display = 'none';  // Lo oculta en Taller y Finanzas
        }
    }
};

// --- AUTOGUARDADO INTELIGENTE (SILENCIOSO) ---
window.autoGuardarSilencioso = function() {
    const estadoSeleccionado = document.getElementById('selectEstado').value;
    const ubicacionActual = document.getElementById('ubicacion').value.trim();
    if (estadoSeleccionado === 'Terminado' && ubicacionActual === '') return; 

    let fd = new FormData();
    fd.append('action', 'guardar');
    fd.append('id', REPARACION_ID);
    fd.append('nombre_cliente', document.getElementById('nombre_cliente').value);
    fd.append('telefono', document.getElementById('telefono').value);
    fd.append('tipo_reparacion', document.getElementById('tipo_reparacion').value);
    fd.append('marca_celular', document.getElementById('marca_celular').value);
    fd.append('modelo', document.getElementById('modelo').value);
    fd.append('monto', document.getElementById('monto').value);
    fd.append('adelanto', document.getElementById('adelanto').value);
    fd.append('info_extra', document.getElementsByName('info_extra')[0].value);
    fd.append('estado', estadoSeleccionado);
    fd.append('fecha_estimada', document.getElementById('fecha_estimada').value);     
    fd.append('ubicacion', ubicacionActual); 
    
    const tipoPass = document.getElementById('tipo_pass');
    if (tipoPass) {
        fd.append('tipo_pass', tipoPass.value);
        fd.append('codigo_pass', tipoPass.value === 'Patron' ? document.getElementById('codigo_pass_patron').value : document.getElementById('codigo_pass_txt').value);
    }

    fetch('api/editar_reparacion.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Swal.fire({ toast: true, position: 'bottom-end', icon: 'success', title: 'Guardado auto.', showConfirmButton: false, timer: 1500 });
        }
    }).catch(e => console.log(e));
};

window.guardarCambiosManual = function() {
    autoGuardarSilencioso();
    setTimeout(() => location.reload(), 800);
};

// --- SEGURIDAD: PATRÓN DE DIBUJO ---
window.cambiarTipoPass = function() {
    const tipo = document.getElementById('tipo_pass').value;
    const cajaTxt = document.getElementById('caja_pass_texto');
    const cajaPatron = document.getElementById('caja_pass_patron');
    const lblTxt = document.getElementById('lbl_pass_texto');
    const inputTxt = document.getElementById('codigo_pass_txt');

    cajaTxt.style.display = 'none';
    cajaPatron.style.display = 'none';

    if (tipo === 'PIN') {
        cajaTxt.style.display = 'block'; lblTxt.innerText = 'PIN Numérico'; inputTxt.placeholder = 'Ej: 1234'; inputTxt.type = 'number';
    } else if (tipo === 'Texto') {
        cajaTxt.style.display = 'block'; lblTxt.innerText = 'Contraseña Alfanumérica'; inputTxt.placeholder = 'Ej: Juan123'; inputTxt.type = 'text';
    } else if (tipo === 'Patron') {
        cajaPatron.style.display = 'block';
    }
};

window.limpiarPatron = function() {
    limpiarPatronSinGuardar();
    autoGuardarSilencioso();
};

function limpiarPatronSinGuardar() {
    currentPattern = [];
    document.getElementById('codigo_pass_patron').value = '';
    document.querySelectorAll('.patron-dot').forEach(dot => { dot.classList.remove('active'); dot.innerText = ''; });
}

function initPatternLock() {
    const dots = document.querySelectorAll('.patron-dot');
    const grid = document.getElementById('patronGrid');
    if (!grid) return;

    let patronModificado = false;

    const addDot = (dot) => {
        const val = dot.getAttribute('data-val');
        if (!currentPattern.includes(val)) {
            currentPattern.push(val);
            dot.classList.add('active'); dot.innerText = currentPattern.length; 
            document.getElementById('codigo_pass_patron').value = currentPattern.join('-');
            patronModificado = true; 
        }
    };

    dots.forEach(dot => {
        dot.addEventListener('mousedown', (e) => { 
            e.preventDefault(); 
            if(currentPattern.length > 0) limpiarPatronSinGuardar(); 
            isDrawingPattern = true; 
            patronModificado = false;
            addDot(dot); 
        });
        dot.addEventListener('mouseenter', () => { if(isDrawingPattern) addDot(dot); });
    });
    
    document.addEventListener('mouseup', () => { 
        if (isDrawingPattern) {
            isDrawingPattern = false; 
            if (patronModificado) autoGuardarSilencioso();
        }
    });

    grid.addEventListener('touchstart', (e) => { 
        e.preventDefault(); 
        if(currentPattern.length > 0) limpiarPatronSinGuardar(); 
        isDrawingPattern = true; 
        patronModificado = false;
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
    
    document.addEventListener('touchend', () => { 
        if (isDrawingPattern) {
            isDrawingPattern = false;
            if (patronModificado) autoGuardarSilencioso();
        }
    });
}

window.mostrarAnimacionPatron = function(patronStr) {
    if (!patronStr) return;
    patronActualAnimacion = patronStr.split('-');
    document.getElementById('secuenciaText').innerText = patronStr;
    document.getElementById('modalAnimacionPatron').style.display = 'flex';
    setTimeout(() => { reiniciarAnimacionPatron(); }, 150);
};
window.cerrarAnimacionPatron = function(e) {
    if (e && e.target.id !== 'modalAnimacionPatron') return;
    document.getElementById('modalAnimacionPatron').style.display = 'none';
    detenerAnimacionPatron();
};
window.detenerAnimacionPatron = function() { animacionPatronTimeout.forEach(clearTimeout); animacionPatronTimeout = []; };
window.reiniciarAnimacionPatron = function() {
    detenerAnimacionPatron();
    for(let i=1; i<=9; i++) { let dot = document.getElementById('anim-dot-'+i); if(dot) { dot.classList.remove('active'); dot.innerText = ''; } }
    document.getElementById('svgLineasPatron').innerHTML = '';
    let delay = 400; let previousDotInfo = null;

    patronActualAnimacion.forEach((val, index) => {
        let to = setTimeout(() => {
            let dot = document.getElementById('anim-dot-'+val);
            if (previousDotInfo) dibujarLineaSVG(previousDotInfo, val);
            dot.classList.add('active'); dot.innerText = index + 1;
            previousDotInfo = val;
        }, delay * index);
        animacionPatronTimeout.push(to);
    });
};
function dibujarLineaSVG(fromVal, toVal) {
    let svg = document.getElementById('svgLineasPatron');
    let dotFrom = document.getElementById('anim-dot-'+fromVal);
    let dotTo = document.getElementById('anim-dot-'+toVal);
    if (!dotFrom || !dotTo || !svg) return;
    let svgRect = svg.getBoundingClientRect(); let fromRect = dotFrom.getBoundingClientRect(); let toRect = dotTo.getBoundingClientRect();
    let x1 = (fromRect.left - svgRect.left) + (fromRect.width / 2); let y1 = (fromRect.top - svgRect.top) + (fromRect.height / 2);
    let x2 = (toRect.left - svgRect.left) + (toRect.width / 2); let y2 = (toRect.top - svgRect.top) + (toRect.height / 2);
    let line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    line.setAttribute('x1', x1); line.setAttribute('y1', y1); line.setAttribute('x2', x2); line.setAttribute('y2', y2);
    line.setAttribute('stroke', '#5856d6'); line.setAttribute('stroke-width', '5'); line.setAttribute('stroke-linecap', 'round');
    svg.appendChild(line);
}

// --- FOTOS, BITÁCORA Y EVIDENCIA ---
function comprimirImagen(file, maxWidth = 1280, quality = 0.8) {
    return new Promise((resolve) => {
        const nombreLimpio = (file.name ? file.name.replace(/\.[^/.]+$/, "") : "foto_" + Date.now()) + ".jpg";
        if (!file.type || !file.type.startsWith('image/')) { resolve({ blob: file, name: file.name || nombreLimpio, previewUrl: URL.createObjectURL(file) }); return; }
        const reader = new FileReader(); reader.readAsDataURL(file);
        reader.onload = (e) => {
            const img = new Image(); img.src = e.target.result;
            img.onload = () => {
                let width = img.width; let height = img.height;
                if (width > maxWidth) { height = Math.round((height * maxWidth) / width); width = maxWidth; }
                const canvas = document.createElement('canvas'); canvas.width = width; canvas.height = height;
                const ctx = canvas.getContext('2d'); ctx.drawImage(img, 0, 0, width, height);
                canvas.toBlob((blob) => {
                    if (blob) resolve({ blob: blob, name: nombreLimpio, previewUrl: URL.createObjectURL(blob) });
                    else resolve({ blob: file, name: file.name || nombreLimpio, previewUrl: URL.createObjectURL(file) });
                }, 'image/jpeg', quality);
            };
            img.onerror = () => resolve({ blob: file, name: file.name || nombreLimpio, previewUrl: URL.createObjectURL(file) });
        };
        reader.onerror = () => resolve({ blob: file, name: file.name || nombreLimpio, previewUrl: URL.createObjectURL(file) });
    });
}

window.previsualizarBitacora = async function(inputEl) {
    const container = document.getElementById('bitacora-preview-box');
    if (inputEl && inputEl.files && inputEl.files.length > 0) {
        for (let file of Array.from(inputEl.files)) { archivosBitacora.push(await comprimirImagen(file)); }
        inputEl.value = ''; renderizarPreviews(archivosBitacora, container, 'bitacora');
    }
};

function renderizarPreviews(lista, container, tipo) {
    if (!container) return; container.innerHTML = '';
    if (lista.length === 0) { container.style.display = 'none'; return; }
    container.style.display = 'grid';
    lista.forEach((item, idx) => {
        const div = document.createElement('div'); div.className = 'preview-thumb-item';
        div.innerHTML = `<img src="${item.previewUrl}"><button type="button" onclick="quitarFotoPreview('${tipo}', ${idx})"><i class="fas fa-times"></i></button>`;
        container.appendChild(div);
    });
}

window.quitarFotoPreview = function(tipo, idx) {
    if (tipo === 'form') { archivosFormulario.splice(idx, 1); renderizarPreviews(archivosFormulario, document.getElementById('preview-container'), 'form'); }
    else { archivosBitacora.splice(idx, 1); renderizarPreviews(archivosBitacora, document.getElementById('bitacora-preview-box'), 'bitacora'); }
};

window.guardarBitacoraRapida = function() {
    const nota = document.getElementById('nota_bitacora') ? document.getElementById('nota_bitacora').value.trim() : '';
    if (nota === '' && archivosBitacora.length === 0) { Swal.fire('Atención', 'Escribe una nota o adjunta una foto.', 'warning'); return; }

    let fd = new FormData(); fd.append('action', 'agregar_bitacora'); fd.append('id', REPARACION_ID); fd.append('nota_bitacora', nota);
    archivosBitacora.forEach(item => fd.append('evidencias[]', item.blob, item.name));

    Swal.fire({ title: 'Registrando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('api/editar_reparacion.php', { method: 'POST', body: fd }).then(res => res.json()).then(data => {
        if (data.success) location.reload();
        else Swal.fire('Error', data.message, 'error');
    }).catch(err => {
        console.error("Error en bitácora:", err);
        Swal.fire('Error', 'Fallo de conexión', 'error');
    });
};

let zoomActivo = false;
window.abrirLightbox = function(url, comentario, fecha, usuario, idHistorial) {
    const modal = document.getElementById('modalLightbox'); const img = document.getElementById('lb-img');
    zoomActivo = false; img.classList.remove('zoomed'); document.getElementById('lb-zoom-icon').className = 'fas fa-search-plus';
    img.src = url; document.getElementById('lb-comentario').textContent = comentario || 'Evidencia Fotográfica';
    document.getElementById('lb-meta').innerHTML = `<i class="far fa-calendar-alt"></i> ${fecha} &nbsp;|&nbsp; <i class="far fa-user"></i> ${usuario}`;
    document.getElementById('lb-download').href = url;
    const btnDel = document.getElementById('lb-delete-btn'); if (btnDel) btnDel.onclick = () => eliminarEvidencia(idHistorial);
    modal.style.display = 'flex';
};
window.cerrarLightbox = function(e) { if (e && e.target.id !== 'modalLightbox') return; document.getElementById('modalLightbox').style.display = 'none'; };
window.alternarZoomLightbox = function() {
    const img = document.getElementById('lb-img'); const icon = document.getElementById('lb-zoom-icon'); zoomActivo = !zoomActivo;
    if (zoomActivo) { img.classList.add('zoomed'); icon.className = 'fas fa-search-minus'; } 
    else { img.classList.remove('zoomed'); icon.className = 'fas fa-search-plus'; }
};

window.eliminarEvidencia = function(id) {
    Swal.fire({ title: '¿Eliminar fotografía?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ff3b30', confirmButtonText: 'Sí, eliminar' }).then((res) => {
        if (res.isConfirmed) {
            let fd = new FormData(); fd.append('action', 'eliminar_evidencia'); fd.append('id_historial', id);
            fetch('api/editar_reparacion.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => { if (data.success) location.reload(); });
        }
    });
};

// --- FINANZAS, MAPA Y TICKETS ---
window.calcularDeuda = function() {
    let monto = parseFloat(document.getElementById('monto').value) || 0; let adelanto = parseFloat(document.getElementById('adelanto').value) || 0;
    document.getElementById('deuda').value = Math.max(0, monto - adelanto);
};

window.agregarAbono = function() {
    let montoAbono = parseFloat(document.getElementById('nuevo_abono_monto').value);
    let deudaActual = parseFloat(document.getElementById('deuda').value) || 0;
    if (!montoAbono || montoAbono <= 0) return Swal.fire('Error', 'Ingrese un monto válido.', 'warning');
    if (montoAbono > deudaActual) return Swal.fire('Cuidado', `El abono no puede ser mayor a la deuda ($${deudaActual}).`, 'warning');

    Swal.fire({ title: '¿Cobrar Abono en Caja?', icon: 'info', showCancelButton: true, confirmButtonColor: '#007aff', confirmButtonText: 'Mandar a Caja' }).then((res) => {
        if (res.isConfirmed) {
            if (typeof agregarAlCarritoGlobal === 'function') {
                agregarAlCarritoGlobal({ id: REPARACION_ID, tipo: 'reparacion', accion_reparacion: 'abonar', nombre: 'Abono: ' + document.getElementById('marca_celular').value + ' ' + document.getElementById('modelo').value, costo_total: document.getElementById('monto').value, a_cobrar: montoAbono });
                document.getElementById('nuevo_abono_monto').value = '';
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Abono enviado', showConfirmButton: false, timer: 2000 });
            } else { Swal.fire('Error', 'El carrito global no está conectado.', 'error'); }
        }
    });
};

window.abrirMapaCaja = function() { const m = document.getElementById('modalMapaCaja'); try { new bootstrap.Modal(m).show(); } catch(e) { m.style.display = 'block'; m.classList.add('show'); } dibujarCaja(); };
window.cerrarModalMapa = function() { const m = document.getElementById('modalMapaCaja'); try { bootstrap.Modal.getInstance(m).hide(); } catch(e) { m.style.display = 'none'; m.classList.remove('show'); } autoGuardarSilencioso(); };
window.dibujarCaja = function() {
    fetch('api/obtener_lugares.php').then(res => res.json()).then(data => {
        const grid = document.getElementById('grid-caja'); grid.innerHTML = '';
        ['A', 'B', 'C', 'D'].forEach(fila => {
            for (let col = 1; col <= 12; col++) {
                const coord = `${fila}${col}`; const celda = document.createElement('div'); celda.className = 'lugar-box'; celda.textContent = coord;
                const o = data.data.find(o => o.ubicacion === coord);
                if (o) { if (o.id == REPARACION_ID) celda.classList.add('seleccionado'); else { celda.classList.add('ocupado'); celda.onclick = () => window.open(`editar_reparacion.php?id=${o.id}`, '_blank'); } }
                else celda.onclick = () => seleccionarLugar(coord);
                grid.appendChild(celda);
            }
        });
    });
};
window.seleccionarLugar = function(coord) { document.getElementById('ubicacion').value = coord; cerrarModalMapa(); Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: `Asignado a ${coord}`, showConfirmButton: false, timer: 1500 }); };

window.toggleGlassDropdown = function() { document.getElementById('glassDropdown').classList.toggle('active'); };
window.cancelarChecklist = function() { document.getElementById('modalChecklist').style.display = 'none'; document.getElementById('selectEstado').value = estadoAnterior; };
window.confirmarChecklist = function() { const allChecked = Array.from(document.querySelectorAll('.checklist-check')).every(cb => cb.checked); if (!allChecked) { Swal.fire({ title: '¿Confirmar con pendientes?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, forzar', cancelButtonText: 'Revisar' }).then((r) => { if (r.isConfirmed) finalizarChecklist(); }); } else { finalizarChecklist(); } };
window.finalizarChecklist = function() { document.getElementById('modalChecklist').style.display = 'none'; estadoAnterior = 'Terminado'; autoGuardarSilencioso(); Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Calidad Aprobada', showConfirmButton: false, timer: 1500 }); };

window.entregarReparacion = function() {
    let deuda = parseFloat(document.getElementById('deuda').value) || 0;
    Swal.fire({ title: '¿Mandar a Caja para Entregar?', html: `Saldo a cobrar: <b>$${deuda.toFixed(2)}</b>`, icon: 'info', showCancelButton: true, confirmButtonColor: '#007aff', confirmButtonText: 'Mandar a Caja' }).then((res) => {
        if (res.isConfirmed && typeof agregarAlCarritoGlobal === 'function') {
            agregarAlCarritoGlobal({ id: REPARACION_ID, tipo: 'reparacion', accion_reparacion: 'liquidar', nombre: 'Entrega: ' + document.getElementById('marca_celular').value + ' ' + document.getElementById('modelo').value, costo_total: document.getElementById('monto').value, a_cobrar: deuda });
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Enviado al Carrito', showConfirmButton: false, timer: 2000 });
        }
    });
};

window.imprimirTicket = function() { window.open(TICKET_URL, '_blank'); };
window.imprimirEtiquetaReparacion = function() {
    const c = CODIGO_BARRAS || REPARACION_ID; const e = document.getElementById('marca_celular').value + ' ' + document.getElementById('modelo').value;
    window.open(`imprimir_etiqueta.php?codigo=${encodeURIComponent(c)}&nombre=${encodeURIComponent(e)}&cliente=${encodeURIComponent(document.getElementById('nombre_cliente').value)}&detalles=${encodeURIComponent(document.getElementById('tipo_reparacion').value)}`, '_blank', 'width=450,height=550');
};

let timeoutPiezas;
window.buscarPiezaReparacion = function() {
    clearTimeout(timeoutPiezas); const q = document.getElementById('buscador_piezas').value.trim(); const caja = document.getElementById('resultados_piezas');
    if(q.length < 2) { caja.style.display = 'none'; return; }
    timeoutPiezas = setTimeout(() => {
        fetch(`api/gestion_piezas.php?action=buscar_mercancia&q=${encodeURIComponent(q)}`).then(res => res.json()).then(data => {
            caja.innerHTML = '';
            if(data.success && data.data.length > 0) {
                data.data.forEach(p => {
                    caja.innerHTML += `<div style="padding: 10px; border-bottom: 1px solid #eee; cursor: pointer; display: flex; justify-content: space-between; align-items: center;" onclick="agregarPiezaAReparacion(${p.id})"><div><strong style="font-size: 13px; color: #1d1d1f; display: block;">${p.tipo_repuesto} ${p.marca} ${p.modelo}</strong><span style="font-size: 11px; color: #86868b;">Stock: ${p.cantidad}</span></div><button class="glass-btn primary" style="height: 30px; padding: 0 10px;"><i class="fas fa-plus"></i></button></div>`;
                });
            } else { caja.innerHTML = '<div style="padding: 10px; text-align: center; color: #86868b; font-size: 12px;">Sin resultados.</div>'; }
            caja.style.display = 'block';
        });
    }, 300);
};

window.agregarPiezaAReparacion = function(id_mercancia) {
    Swal.fire({ title: 'Agregando...', didOpen: () => Swal.showLoading() });
    let fd = new FormData(); fd.append('action', 'agregar_pieza'); fd.append('id_reparacion', REPARACION_ID); fd.append('id_mercancia', id_mercancia);
    fetch('api/gestion_piezas.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => { if(d.success) { document.getElementById('buscador_piezas').value = ''; document.getElementById('resultados_piezas').style.display = 'none'; Swal.close(); cargarPiezasUsadas(); } });
};

window.cargarPiezasUsadas = function() {
    fetch(`api/gestion_piezas.php?action=listar_piezas&id_reparacion=${REPARACION_ID}`).then(r => r.json()).then(data => {
        if(data.success) {
            const tbody = document.getElementById('tabla_piezas_usadas'); tbody.innerHTML = '';
            data.piezas.forEach(pz => {
                let cc = (typeof ES_ADMIN !== 'undefined' && ES_ADMIN) ? `<td style="padding: 10px 8px; text-align: right; color: #ff3b30; font-weight: 600;">-$${parseFloat(pz.costo_unitario).toFixed(2)}</td>` : '';
                tbody.innerHTML += `<tr style="border-bottom: 1px solid rgba(0,0,0,0.03);"><td style="padding: 10px 8px; font-size: 13px; font-weight: 500;">${pz.nombre_pieza}</td>${cc}<td style="padding: 10px 8px; text-align: right;"><button type="button" onclick="eliminarPiezaReparacion(${pz.id})" style="background:rgba(255,59,48,0.1); color:#ff3b30; border:none; width:32px; height:32px; border-radius:8px; cursor:pointer;"><i class="fas fa-trash-alt"></i></button></td></tr>`;
            });
            if(data.piezas.length === 0) tbody.innerHTML = `<tr><td colspan="${(typeof ES_ADMIN !== 'undefined' && ES_ADMIN) ? 3 : 2}" style="padding: 15px; text-align: center; color: #86868b; font-size: 12px;">Sin refacciones asignadas.</td></tr>`;
            if (typeof ES_ADMIN !== 'undefined' && ES_ADMIN) {
                if(document.getElementById('lbl_costo_piezas')) document.getElementById('lbl_costo_piezas').innerText = '$' + (parseFloat(data.finanzas.costo_piezas) || 0).toFixed(2);
                if(document.getElementById('lbl_ganancia_neta')) document.getElementById('lbl_ganancia_neta').innerText = '$' + ((parseFloat(data.finanzas.monto) || 0) - (parseFloat(data.finanzas.costo_piezas) || 0)).toFixed(2);
            }
        }
    });
};

window.eliminarPiezaReparacion = function(id_puente) {
    let fd = new FormData(); fd.append('action', 'eliminar_pieza'); fd.append('id_puente', id_puente);
    fetch('api/gestion_piezas.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => { if(d.success) cargarPiezasUsadas(); });
};

const inputMonto = document.getElementById('monto');
if(inputMonto) inputMonto.addEventListener('input', () => cargarPiezasUsadas());