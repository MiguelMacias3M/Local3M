// ==========================================================================
// CONTROLADOR LOGICO REACTIVO DE MERCANCÍA - LIQUID GLASS
// ==========================================================================

document.addEventListener('DOMContentLoaded', () => {
    cargarMercancia();
    configurarBusquedaEfectiva();
});

// Variables maestras de interacción DOM
const modalOverlay = document.getElementById('modalMercancia');
const formularioMercancia = document.getElementById('formFormercancia') || document.getElementById('formMercancia');
const tablaCuerpo = document.getElementById('tablaMercanciaBody');
const inputBusquedaEfectiva = document.getElementById('buscar');

// Configuración de búsqueda en tiempo real (evita saltos bruscos)
function configurarBusquedaEfectiva() {
    if (inputBusquedaEfectiva) {
        inputBusquedaEfectiva.addEventListener('input', () => {
            cargarMercancia(inputBusquedaEfectiva.value);
        });
    }
}

// 1. CARGAR DATOS GENERALES E INYECTAR CLASES ASOCIADAS
async function cargarMercancia(criterioBusqueda = '') {
    try {
        const respuestaServidor = await fetch(`/local3M/api/mercancia.php?action=listar&q=${encodeURIComponent(criterioBusqueda)}`);
        const conversionJson = await respuestaServidor.json();
        
        tablaCuerpo.innerHTML = '';
        
        if (conversionJson.success) {
            if (conversionJson.data.length === 0) {
                tablaCuerpo.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center empty-state-message" style="padding: 50px; color: #86868b; font-size: 15px;">
                            <i class="fas fa-box-open" style="display:block; font-size: 30px; margin-bottom:10px; color:#ccc;"></i>
                            No se encontraron refacciones bajo los términos especificados.
                        </td>
                    </tr>`;
                return;
            }
            
            // Diccionario semántico de mapeo visual (Iconos y paletas Liquid Glass)
            const mapeoVisualRefacciones = {
                'pantalla':        { icono: 'fa-mobile-alt', color: '#007aff', fondo: 'rgba(0, 122, 255, 0.12)' },
                'batería':         { icono: 'fa-battery-full', color: '#34c759', fondo: 'rgba(52, 199, 89, 0.12)' },
                'bateria':         { icono: 'fa-battery-full', color: '#34c759', fondo: 'rgba(52, 199, 89, 0.12)' },
                'centro de carga': { icono: 'fa-bolt', color: '#ff9500', fondo: 'rgba(255, 149, 0, 0.12)' },
                'flexor':          { icono: 'fa-project-diagram', color: '#5856d6', fondo: 'rgba(88, 86, 214, 0.12)' },
                'tablilla':        { icono: 'fa-microchip', color: '#5ac8fa', fondo: 'rgba(90, 200, 250, 0.12)' },
                'cámara':          { icono: 'fa-camera', color: '#ff2d55', fondo: 'rgba(255, 45, 85, 0.12)' },
                'camara':          { icono: 'fa-camera', color: '#ff2d55', fondo: 'rgba(255, 45, 85, 0.12)' },
                'tapa trasera':    { icono: 'fa-layer-group', color: '#af52de', fondo: 'rgba(175, 82, 222, 0.12)' }
            };

            conversionJson.data.forEach(itemStock => {
                const filaFisica = document.createElement('tr');
                
                // Normalización de texto para búsqueda en diccionario
                const tipoNormalizado = (itemStock.tipo_repuesto || 'Otro').toLowerCase().trim();
                const estiloVisual = mapeoVisualRefacciones[tipoNormalizado] || { icono: 'fa-tools', color: '#8e8e93', fondo: 'rgba(142, 142, 147, 0.12)' };

                filaFisica.innerHTML = `
                    <td data-label="Refacción">
                        <div class="identity-cell-wrap" style="display: flex; align-items: center; gap: 12px;">
                            <div class="semantic-icon-box" style="width: 38px; height: 38px; border-radius: 10px; background: ${estiloVisual.fondo}; color: ${estiloVisual.color}; display: flex; align-items: center; justify-content: center; font-size: 16px; border: 1px solid ${estiloVisual.color}25;">
                                <i class="fas ${estiloVisual.icono}"></i>
                            </div>
                            <div>
                                <span class="main-repuesto-name" style="font-weight: 700; color: #1d1d1f; display: block; font-size: 14px;">${itemStock.tipo_repuesto}</span>
                                <code class="barcode-subtext" style="font-size: 11px; color: #86868b; font-family: monospace; letter-spacing: 0.5px;">${itemStock.codigo_barras || '--'}</code>
                            </div>
                        </div>
                    </td>
                    <td data-label="Equipo">
                        <div class="device-cell">
                            <strong style="color: #1d1d1f; font-size: 14px; display: block;">${itemStock.marca}</strong>
                            <span style="color: #007aff; font-size: 13px; font-weight: 500;">${itemStock.modelo}</span>
                        </div>
                    </td>
                    <td data-label="Compatibilidad">
                        <span class="compatibility-text" style="font-size: 13px; color: #48484a;">${itemStock.compatibilidad || 'Exclusivo'}</span>
                    </td>
                    <td data-label="Ubicación">
                        <span class="location-badge" style="font-size: 12px; color: #555; background: rgba(0,0,0,0.04); padding: 4px 8px; border-radius: 6px; font-weight: 500;">
                            <i class="fas fa-box" style="margin-right: 4px; color: #86868b;"></i> ${itemStock.ubicacion || 'Sin área'}
                        </span>
                    </td>
                    <td data-label="Stock" class="text-center">
                        <div class="reactive-stock-counter" style="display: inline-flex; align-items: center; gap: 6px; background: rgba(0,0,0,0.03); padding: 4px; border-radius: 10px;">
                            <button type="button" class="counter-step-btn" style="width: 28px; height: 28px; border-radius: 7px; border: none; background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: all 0.2s;" onclick="cambiarStock(${itemStock.id}, 'restar')" ${itemStock.cantidad <= 0 ? 'disabled' : ''}>
                                <i class="fas fa-minus" style="font-size: 10px; color: #ff3b30;"></i>
                            </button>
                            <span class="current-stock-value" style="font-weight: 700; font-size: 14px; min-width: 26px; text-align: center; color: ${itemStock.cantidad <= 2 ? '#ff3b30' : '#1d1d1f'};">${itemStock.cantidad}</span>
                            <button type="button" class="counter-step-btn" style="width: 28px; height: 28px; border-radius: 7px; border: none; background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: all 0.2s;" onclick="cambiarStock(${itemStock.id}, 'sumar')">
                                <i class="fas fa-plus" style="font-size: 10px; color: #34c759;"></i>
                            </button>
                        </div>
                    </td>
                    <td data-label="Costo">
                        <span class="cost-currency-display" style="font-weight: 700; color: #1d1d1f; font-size: 14px;">$${parseFloat(itemStock.costo).toFixed(2)}</span>
                    </td>
                    <td data-label="Acciones" class="text-right">
                        <div class="row-actions-container" style="display: flex; gap: 6px; justify-content: flex-end;">
                            <button type="button" class="control-action-btn print-action" style="width: 34px; height: 34px; border-radius: 8px; border: none; background: rgba(0,122,255,0.1); color: #007aff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onclick="imprimirEtiquetaRefaccion('${itemStock.codigo_barras}', '${itemStock.tipo_repuesto} ${itemStock.marca} ${itemStock.modelo}')" title="Imprimir Etiqueta Xprinter"><i class="fas fa-print"></i></button>
                            <button type="button" class="control-action-btn edit-action" style="width: 34px; height: 34px; border-radius: 8px; border: none; background: rgba(255,149,0,0.1); color: #ff9500; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onclick="editarMercancia(${itemStock.id})" title="Editar Ficha Técnica"><i class="fas fa-edit"></i></button>
                            <button type="button" class="control-action-btn delete-action" style="width: 34px; height: 34px; border-radius: 8px; border: none; background: rgba(255,59,48,0.1); color: #ff3b30; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onclick="eliminarMercancia(${itemStock.id})" title="Eliminar Registro"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </td>
                `;
                tablaCuerpo.appendChild(filaFisica);
            });
        }
    } catch (errorExterna) {
        console.error("Fallo crítico de renderizado: ", errorExterna);
    }
}

// 2. MODIFICACIÓN REACTIVA DEL CONTADOR DE STOCK
async function cambiarStock(idRegistro, operacionAccion) {
    try {
        const payloadData = new FormData();
        payloadData.append('action', 'stock');
        payloadData.append('id', idRegistro);
        payloadData.append('tipo', operacionAccion);

        const consultaServidor = await fetch('/local3M/api/mercancia.php', { method: 'POST', body: payloadData });
        const resultadoOperación = await consultaServidor.json();
        
        if (resultadoOperación.success) {
            // Recargar respetando el filtro actual de búsqueda
            cargarMercancia(inputBusquedaEfectiva ? inputBusquedaEfectiva.value : '');
        }
    } catch (e) {
        console.error("Imposible actualizar inventario express: ", e);
    }
}

// 3. APERTURA Y CONTROL DE MODALES
function abrirModalNuevo() {
    formularioMercancia.reset();
    document.getElementById('inputId').value = '';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-box-open" style="color: #007aff; margin-right: 10px;"></i>Nueva Refacción al Inventario';
    document.getElementById('barcode-preview').style.display = 'none';
    modalOverlay.style.display = 'flex';
}

function cerrarModal() {
    modalOverlay.style.display = 'none';
}

// 4. GENERACIÓN DE CÓDIGOS DE BARRAS DE ALTA DENSIDAD (JsBarcode)
function generarCodigo() {
    const fechaInstante = new Date();
    const formatoFechaStr = fechaInstante.getFullYear().toString().substr(-2) + 
                            (fechaInstante.getMonth() + 1).toString().padStart(2, '0') + 
                            fechaInstante.getDate().toString().padStart(2, '0');
    const digitoAleatorio = Math.floor(100 + Math.random() * 900);
    const codigoUnicoConstruido = 'MER' + formatoFechaStr + digitoAleatorio;
    
    const inputDestino = document.getElementById('inputCodigoBarras');
    inputDestino.value = codigoUnicoConstruido;
    
    // Inyectar el vector gráfico SVG en pantalla
    JsBarcode("#barcode-svg", codigoUnicoConstruido, {
        format: "CODE128",
        width: 1.8,
        height: 45,
        displayValue: true,
        fontSize: 12,
        margin: 5
    });
    
    document.getElementById('barcode-preview').style.display = 'block';
}

// 5. EDICIÓN TÉCNICA AVANZADA
async function editarMercancia(idBusqueda) {
    try {
        const peticionFicha = await fetch(`/local3M/api/mercancia.php?action=obtener&id=${idBusqueda}`);
        const conversionFicha = await peticionFicha.json();
        
        if (conversionFicha.success) {
            const dataObjeto = conversionFicha.data;
            
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit" style="color: #ff9500; margin-right: 10px;"></i>Modificar Ficha de Refacción';
            document.getElementById('inputId').value = dataObjeto.id;
            document.getElementById('inputTipoRepuesto').value = dataObjeto.tipo_repuesto || 'Pantalla';
            document.getElementById('inputMarca').value = dataObjeto.marca;
            document.getElementById('inputModelo').value = dataObjeto.modelo;
            document.getElementById('inputCantidad').value = dataObjeto.cantidad;
            document.getElementById('inputCompatibilidad').value = dataObjeto.compatibilidad || '';
            document.getElementById('inputCosto').value = dataObjeto.costo;
            document.getElementById('inputUbicacion').value = dataObjeto.ubicacion || '';
            document.getElementById('inputCodigoBarras').value = dataObjeto.codigo_barras || '';
            
            if (dataObjeto.codigo_barras) {
                JsBarcode("#barcode-svg", dataObjeto.codigo_barras, {
                    format: "CODE128",
                    width: 1.8,
                    height: 45,
                    displayValue: true,
                    fontSize: 12
                });
                document.getElementById('barcode-preview').style.display = 'block';
            } else {
                document.getElementById('barcode-preview').style.display = 'none';
            }
            
            modalOverlay.style.display = 'flex';
        }
    } catch (e) {
        console.error("Fallo en recuperación de ficha técnica: ", e);
    }
}

// 6. GUARDAR (INSERCIÓN O ACTUALIZACIÓN CON SWEETALERT ANIMADO)
async function guardarMercancia() {
    try {
        Swal.fire({
            title: 'Procesando Inventario...',
            text: 'Validando empaquetado y subiendo datos',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const empaquetadoDatos = new FormData(formularioMercancia);
        empaquetadoDatos.append('action', 'guardar');

        const envioServidor = await fetch('/local3M/api/mercancia.php', { method: 'POST', body: empaquetadoDatos });
        const respuestaServidorAccion = await envioServidor.json();
        
        if (respuestaServidorAccion.success) {
            Swal.fire({
                icon: 'success',
                title: 'Inventario Actualizado',
                text: 'La refacción se guardó con éxito en el sistema.',
                showConfirmButton: false,
                timer: 1500
            });
            cerrarModal();
            cargarMercancia(inputBusquedaEfectiva ? inputBusquedaEfectiva.value : '');
        } else {
            Swal.fire('Error Operativo', respuestaServidorAccion.error, 'error');
        }
    } catch (errForm) {
        Swal.fire('Error Crítico', 'Fallo de enlace con el archivo API', 'error');
    }
}

// 7. DESTRUCCIÓN / ELIMINACIÓN SEGURA CON RETROALIMENTACIÓN
function eliminarMercancia(idEliminar) {
    Swal.fire({
        title: '¿Dar de baja refacción?',
        text: 'Esta acción removerá el producto permanentemente de los listados activos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff3b30',
        cancelButtonColor: '#8e8e93',
        confirmButtonText: 'Sí, borrar del stock',
        cancelButtonText: 'Conservar'
    }).then(async (decisionUsuario) => {
        if (decisionUsuario.isConfirmed) {
            try {
                const empaquetadoBaja = new FormData();
                empaquetadoBaja.append('action', 'eliminar');
                empaquetadoBaja.append('id', idEliminar);
                
                const ejecucionBaja = await fetch('/local3M/api/mercancia.php', { method: 'POST', body: empaquetadoBaja });
                const respuestaBaja = await ejecucionBaja.json();
                
                if (respuestaBaja.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Eliminado',
                        showConfirmButton: false,
                        timer: 1200
                    });
                    cargarMercancia(inputBusquedaEfectiva ? inputBusquedaEfectiva.value : '');
                }
            } catch (e) {
                Swal.fire('Error', 'No se pudo realizar la baja de stock', 'error');
            }
        }
    });
}

// 8. COMUNICACIÓN DIRECTA CON HARDWARE IMPRESOR XPRINTER (VÍA IFRAME/VENTANA MODAL)
function imprimirEtiquetaRefaccion(codigoBarrasArticulo, identificadorTextoCompleto) {
    if (!codigoBarrasArticulo || codigoBarrasArticulo.trim() === '' || codigoBarrasArticulo === 'null') {
        Swal.fire('Código Requerido', 'Para despachar una etiqueta necesitas generar un código de barras primero.', 'info');
        return;
    }
    
    // Abrir pasarela de render térmico nativo
    const rutaImpresionDirecta = `/local3M/imprimir_etiqueta.php?codigo=${encodeURIComponent(codigoBarrasArticulo)}&nombre=${encodeURIComponent(identificadorTextoCompleto)}&cliente=Stock&detalles=Refaccion`;
    window.open(rutaImpresionDirecta, '_blank', 'width=450,height=550,scrollbars=no,resizable=no');
}

// ==========================================================================
// CIERRE DE MODAL AL HACER CLIC AFUERA (Fondo oscuro)
// ==========================================================================
window.addEventListener('click', (evento) => {
    // Si el clic fue exactamente en la capa oscura (modalOverlay) y no adentro
    if (evento.target === modalOverlay) {
        cerrarModal();
    }
});