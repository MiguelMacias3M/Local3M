/* =========================================
 * LÓGICA PARA EDITAR REPARACIÓN (JS PURO)
 * Versión Final: Ubicación (Mapa Caja) + Fotos + Pagos + Checklist + Nuevos Estados
 * ========================================= */

// --- DIBUJO DE CÓDIGO DE BARRAS INICIAL ---
document.addEventListener('DOMContentLoaded', function() {
    if (typeof CODIGO_BARRAS !== 'undefined' && document.getElementById("barcode-svg")) {
        try {
            JsBarcode("#barcode-svg", CODIGO_BARRAS, {
                format: "CODE128",
                lineColor: "#000",
                width: 2,
                height: 50,
                displayValue: false
            });
        } catch(e) {
            console.error("Error al dibujar barcode inicial:", e);
        }
    }
});

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

// --- FUNCIONES ORIGINALES (FOTO, DEUDA, ABONO, GUARDAR) ---
function previsualizarFoto() {
    const input = document.getElementById('evidencia_input');
    const preview = document.getElementById('img-preview');
    const container = document.getElementById('preview-container');

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            container.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    } else {
        container.style.display = 'none';
    }
}

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

    // 🔒 CANDADO DE SEGURIDAD: Si está Terminado, requiere ubicación física
    if (estadoSeleccionado === 'Terminado' && ubicacionActual === '') {
        Swal.fire({
            title: '¡Falta la Ubicación!',
            text: 'Para marcar el equipo como Terminado, debes indicar en qué repisa o caja lo dejaste para no perderlo.',
            icon: 'warning',
            confirmButtonText: '<i class="fas fa-map-marker-alt"></i> Asignar Lugar',
            confirmButtonColor: '#007aff'
        }).then(() => {
            abrirMapaCaja(); // Despliega el mapa automáticamente
        });
        return; // Detiene el guardado hasta que cumpla la regla
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

    const fileInput = document.getElementById('evidencia_input');
    if(fileInput.files.length > 0) {
        formData.append('evidencia', fileInput.files[0]);
    }

    Swal.fire({
        title: 'Guardando...',
        didOpen: () => { Swal.showLoading() }
    });

    fetch('api/editar_reparacion.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Guardado', 'Cambios registrados correctamente', 'success')
            .then(() => { location.reload(); });
        } else {
            Swal.fire('Error', data.message || 'Error al guardar', 'error');
        }
    })
    .catch(error => {
        Swal.fire('Error', 'Fallo de conexión', 'error');
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
    } else {
        console.error("No se encontró el HTML del modal modalChecklist");
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
    // Tomamos el código existente o usamos el ID como respaldo
    const codigo = CODIGO_BARRAS || REPARACION_ID; 
    
    // Leemos los datos actuales de los inputs por si los acabas de modificar
    const cliente = document.getElementById('nombre_cliente').value || 'Sin nombre';
    const marca = document.getElementById('marca_celular').value || '';
    const modelo = document.getElementById('modelo').value || '';
    const equipo = (marca + ' ' + modelo).trim() || 'Equipo';
    const falla = document.getElementById('tipo_reparacion').value || 'Revisión';

    if (!codigo) {
        Swal.fire('Error', 'No hay un código válido para imprimir.', 'error');
        return;
    }

    // Armamos la URL exacta como la usa control.js
    const url = `/local3M/imprimir_etiqueta.php?codigo=${encodeURIComponent(codigo)}` +
                `&nombre=${encodeURIComponent(equipo)}` +
                `&cliente=${encodeURIComponent(cliente)}` +
                `&detalles=${encodeURIComponent(falla)}`;
                
    // Abrimos la ventanita de impresión
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
    
    // 1. Clonar las opciones del viejo select al nuevo diseño de cristal
    Array.from(nativeSelect.options).forEach(option => {
        const div = document.createElement('div');
        div.className = 'glass-option';
        
        // Si es el estado actual guardado, lo pintamos activo
        if (option.selected) {
            div.classList.add('selected');
            dropdownText.innerHTML = option.text;
        }
        
        div.innerHTML = option.text;
        
        // 2. ¿Qué pasa al hacer clic en una opción de cristal?
        div.onclick = function() {
            // A) Actualizar colores visuales
            document.querySelectorAll('.glass-option').forEach(el => el.classList.remove('selected'));
            this.classList.add('selected');
            dropdownText.innerHTML = this.innerHTML;
            
            // B) Sincronizar secretamente con el Select Oculto para que Guardar() siga funcionando
            nativeSelect.value = option.value;
            
            // C) Disparar el evento para que salte el Checklist si se elige "Terminado"
            nativeSelect.dispatchEvent(new Event('change'));
            
            // D) Cerrar el menú
            document.getElementById('glassDropdown').classList.remove('active');
        };
        
        dropdownOptions.appendChild(div);
    });

    // 3. Cerrar el menú si hacemos clic afuera en la pantalla
    document.addEventListener('click', function(e) {
        const customDropdown = document.getElementById('glassDropdown');
        if (customDropdown && !customDropdown.contains(e.target)) {
            customDropdown.classList.remove('active');
        }
    });
});

// Función para el botón del Dropdown
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
                    // Extraemos el código en texto plano
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
                // Si es admin, mostramos el dinero
                if (typeof ES_ADMIN !== 'undefined' && ES_ADMIN) {
                    celdaCosto = `<td style="padding: 10px 8px; text-align: right; color: #ff3b30; font-weight: 600;">-$${parseFloat(pz.costo_unitario).toFixed(2)}</td>`;
                }
                
                // Dibujamos la fila con el botón de eliminar
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
                // Ajustar columnas dinámicamente si no hay nada
                const columnas = (typeof ES_ADMIN !== 'undefined' && ES_ADMIN) ? 3 : 2;
                tbody.innerHTML = `<tr><td colspan="${columnas}" style="padding: 15px; text-align: center; color: #86868b; font-size: 12px;">Sin refacciones extraídas del sistema aún.</td></tr>`;
            }

            // Cálculos financieros (Solo si es admin y existen las etiquetas)
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

// --- NUEVA FUNCIÓN PARA ELIMINAR Y DEVOLVER AL STOCK ---
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
                    cargarPiezasUsadas(); // Recargamos la tablita
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }
    });
};
// Escuchar cambios en el input del monto para recalcular ganancia en vivo
document.getElementById('monto').addEventListener('input', () => {
    cargarPiezasUsadas(); 
});