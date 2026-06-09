/* =====================================
LÓGICA PARA CONTROL.PHP (control.js)
=====================================
*/

const LIMIT = 20;
let offset = 0;
let cargando = false;
let finDeLista = false;
let datos = [];
let busqueda = '';
let currentBarcode = ''; 
let currentModelo = '';
let currentCliente = '';
let currentFalla = '';

// Elementos del DOM
const tbody = document.getElementById('tablaReparacionesBody');
const btnMas = document.getElementById('btnMas');
const globalLoader = document.getElementById('globalLoader');
const noResults = document.getElementById('noResults');
const inputBuscar = document.getElementById('buscar');
const btnBuscar = document.getElementById('btnBuscar');
const btnLimpiar = document.getElementById('btnLimpiar');
const modalDetalles = document.getElementById('modalDetalles');
const detallesContenido = document.getElementById('detallesContenido');

// Elementos Modal Código de Barras Independiente
const barcodeModal = document.getElementById('barcodeModal');
const barcodeSpinner = document.getElementById('barcode-spinner');
const barcodeWrap = document.getElementById('barcode-wrap');
const barcodeError = document.getElementById('barcode-error');
const btnPrintBarcode = document.getElementById('btnPrintBarcode');
const btnCopyBarcode = document.getElementById('btnCopyBarcode');

// ========= Carga paginada =========
async function cargarPagina() {
    if (cargando || finDeLista) return;
    cargando = true;
    if (btnMas) btnMas.disabled = true;
    if (globalLoader) globalLoader.style.display = 'inline-block';
    if (noResults) noResults.style.display = 'none';

    try {
        const url = `/local3M/api/get_reparaciones.php?limit=${LIMIT}&offset=${offset}&q=${encodeURIComponent(busqueda)}`;
        const res = await fetch(url);
        const data = await res.json();

        if (data.success) {
            const lista = data.data || [];
            if (lista.length < LIMIT) {
                finDeLista = true;
                if (btnMas) btnMas.style.display = 'none';
            } else {
                if (btnMas) btnMas.style.display = 'inline-block';
            }

            datos = datos.concat(lista);
            renderizarFilas(lista);
            offset += lista.length;

            if (datos.length === 0) {
                if (noResults) noResults.style.display = 'block';
                if (btnMas) btnMas.style.display = 'none';
            }
        } else {
            console.error("Error del servidor:", data.error);
        }
    } catch (e) {
        console.error("Error al cargar reparaciones:", e);
    } finally {
        cargando = false;
        if (btnMas) btnMas.disabled = false;
        if (globalLoader) globalLoader.style.display = 'none';
    }
}

// ========= Renderizar Filas de la Tabla =========
function renderizarFilas(lista) {
    lista.forEach(rep => {
        const tr = document.createElement('tr');
        
        let estadoClass = 'status-unknown';
        const est = (rep.estado || '').toLowerCase();
        if (est.includes('espera')) estadoClass = 'status-wait';
        else if (est.includes('revision') || est.includes('diagnosticado')) estadoClass = 'status-pending';
        else if (est.includes('progreso')) estadoClass = 'status-progress';
        else if (est.includes('reparado')) estadoClass = 'status-ready';

        // SINCRO BD: monto, adelanto, deuda
        const costoTotal = parseFloat(rep.monto) || 0;
        const totalAbonado = parseFloat(rep.adelanto) || 0; 
        const saldoPendiente = rep.deuda !== undefined && rep.deuda !== null ? parseFloat(rep.deuda) : (costoTotal - totalAbonado);

        const codigoVisual = rep.codigo_barras || rep.codigo || rep.folio || rep.id;

        tr.innerHTML = `
            <td data-label="Folio"><strong>${escapeHTML(codigoVisual)}</strong></td>
            <td data-label="Cliente">
                <div style="font-weight:600;">${escapeHTML(rep.nombre_cliente)}</div>
                <div style="font-size:12px; color:#86868b;">${escapeHTML(rep.telefono)}</div>
            </td>
            <td data-label="Equipo">
                <div style="font-weight:600; color:#007aff;">${escapeHTML(rep.modelo)}</div>
            </td>
            <td data-label="Problema">${escapeHTML(rep.tipo_reparacion)}</td>
            <td data-label="Estado"><span class="status ${estadoClass}">${escapeHTML(rep.estado)}</span></td>
            <td data-label="Acciones" style="text-align: center;">
                <div style="display: inline-flex; gap: 6px; flex-wrap: wrap; justify-content: center;">
                    <button class="glass-btn" style="height:36px; padding:0 12px; font-size:13px;" onclick="verDetalles(${rep.id})" title="Ver Detalles y Código">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="glass-btn info" style="height:36px; padding:0 12px; font-size:13px;" onclick="mostrarCodigo(${rep.id})" title="Imprimir/Copiar Código de Barras">
                        <i class="fas fa-barcode"></i>
                    </button>
                    <a href="/local3M/editar_reparacion.php?id=${rep.id}" class="glass-btn primary" style="height:36px; padding:0 12px; font-size:13px; text-decoration:none;" title="Editar / Actualizar">
                        <i class="fas fa-edit"></i>
                    </a>
                    ${saldoPendiente > 0 && est.includes('reparado') ? `
                        <button class="glass-btn success" style="height:36px; padding:0 12px; font-size:13px;" onclick="enviarReparacionAlCarritoGlobal(${rep.id}, '${escapeJS(rep.modelo)}', ${costoTotal}, ${saldoPendiente})" title="Cobrar Entrega">
                            <i class="fas fa-cash-register"></i>
                        </button>
                    ` : ''}
                    <button class="glass-btn" style="height:36px; padding:0 12px; font-size:13px; background: rgba(255,59,48,0.1); color:#ff3b30; border-color:rgba(255,59,48,0.2);" onclick="eliminarReparacion(${rep.id})" title="Eliminar">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// ========= Filtrado y Búsqueda =========
function ejecutarBusqueda() {
    busqueda = inputBuscar.value.trim();
    tbody.innerHTML = '';
    offset = 0;
    datos = [];
    finDeLista = false;
    cargarPagina();
}

if (btnBuscar) btnBuscar.addEventListener('click', ejecutarBusqueda);
if (inputBuscar) {
    inputBuscar.addEventListener('keyup', (e) => {
        if (e.key === 'Enter') ejecutarBusqueda();
    });
}

if (btnLimpiar) {
    btnLimpiar.addEventListener('click', () => {
        inputBuscar.value = '';
        busqueda = '';
        tbody.innerHTML = '';
        offset = 0;
        datos = [];
        finDeLista = false;
        cargarPagina();
    });
}

// ========= Ventana Amigable de Detalles de Orden (Liquid Glass) =========
function verDetalles(id) {
    const rep = datos.find(x => x.id == id);
    if (!rep) return;

    const fechaReg = rep.fecha_hora || rep.fecha_ingreso || rep.fecha || 'N/A';
    
    // SINCRO BD: monto, adelanto, deuda
    const costoTotal = parseFloat(rep.monto) || 0;
    const anticipo = parseFloat(rep.adelanto) || 0;
    const restante = rep.deuda !== undefined && rep.deuda !== null ? parseFloat(rep.deuda) : (costoTotal - anticipo);
    
    const infoExtraContenido = rep.info_extra || rep.observaciones || 'Sin anotaciones adicionales en la recepción.';
    
    const codigoCompleto = rep.codigo_barras || rep.codigo || rep.folio || rep.id;

    detallesContenido.innerHTML = `
        <div class="detail-section-card">
            <div class="detail-section-title"><i class="fas fa-user"></i> Datos del Cliente</div>
            <div class="detail-grid">
                <div>
                    <div class="detail-item-label">Nombre del Cliente</div>
                    <div class="detail-item-value">${escapeHTML(rep.nombre_cliente)}</div>
                </div>
                <div>
                    <div class="detail-item-label">Teléfono de Contacto</div>
                    <div class="detail-item-value">${escapeHTML(rep.telefono)}</div>
                </div>
            </div>
        </div>

        <div class="detail-section-card">
            <div class="detail-section-title"><i class="fas fa-mobile-alt"></i> Detalles de la Reparación</div>
            <div style="margin-bottom: 12px;">
                <div class="detail-item-label">Modelo del Equipo</div>
                <div class="detail-item-value" style="color: #007aff; font-size: 16px;">${escapeHTML(rep.modelo)}</div>
            </div>
            <div style="margin-bottom: 12px;">
                <div class="detail-item-label">Falla / Servicio Requerido</div>
                <div class="detail-item-value">${escapeHTML(rep.tipo_reparacion)}</div>
            </div>
            <div class="detail-grid">
                <div>
                    <div class="detail-item-label">Fecha de Registro</div>
                    <div class="detail-item-value">${escapeHTML(fechaReg)}</div>
                </div>
                <div>
                    <div class="detail-item-label">Promesa de Entrega</div>
                    <div class="detail-item-value">${escapeHTML(rep.fecha_estimada || 'N/A')}</div>
                </div>
            </div>
        </div>

        <div class="detail-section-card" style="background: rgba(0, 122, 255, 0.05);">
            <div class="detail-section-title" style="color: #007aff;"><i class="fas fa-dollar-sign"></i> Balance de la Orden</div>
            <div class="detail-grid">
                <div>
                    <div class="detail-item-label">Costo Total</div>
                    <div class="detail-item-value">$${costoTotal.toFixed(2)}</div>
                </div>
                <div>
                    <div class="detail-item-label">Anticipo Dejado</div>
                    <div class="detail-item-value" style="color: #34c759;">$${anticipo.toFixed(2)}</div>
                </div>
                <div>
                    <div class="detail-item-label">Saldo Restante</div>
                    <div class="detail-item-value" style="color: ${restante > 0 ? '#ff9500' : '#34c759'}; font-size: 16px;">
                        $${restante.toFixed(2)}
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 12px; margin-bottom: 20px; padding: 0 5px;">
            <div class="detail-item-label">Información Extra de Recepción</div>
            <p style="background: rgba(0,0,0,0.03); padding: 12px; border-radius: 12px; font-style: italic; margin: 5px 0 0 0; font-size: 13.5px; color:#555; white-space: pre-line;">
                ${escapeHTML(infoExtraContenido)}
            </p>
        </div>

        <div class="detail-section-card" style="text-align: center; background: white; border: 1px solid rgba(0,0,0,0.04); margin-bottom: 20px;">
            <div class="detail-section-title" style="justify-content: center; margin-bottom: 5px;"><i class="fas fa-barcode"></i> Etiqueta de Taller</div>
            <div style="display: flex; justify-content: center; padding: 10px 0; overflow: visible;">
                <svg id="modal-detail-barcode-svg" style="max-width: 100%; height: auto;"></svg>
            </div>
        </div>

        <div style="margin-top: 15px; padding: 0 5px;">
            <button class="glass-btn primary" style="width: 100%; height: 46px; justify-content: center; font-weight: 700;" onclick="document.getElementById('modalDetalles').style.display='none'">
                <i class="fas fa-times-circle"></i> Cerrar Detalles
            </button>
        </div>
    `;

    modalDetalles.style.display = 'flex';

    setTimeout(() => {
        try {
            const svgElement = document.getElementById("modal-detail-barcode-svg");
            svgElement.style.maxWidth = "100%";
            svgElement.style.height = "auto";

            JsBarcode("#modal-detail-barcode-svg", String(codigoCompleto), {
                format: "CODE128",
                width: 1.6,
                height: 55,
                displayValue: true, 
                text: String(codigoCompleto), 
                fontSize: 15,
                fontOptions: "bold",
                textPosition: "bottom",
                font: "Poppins",
                margin: 5
            });
        } catch (err) {
            console.error("Error al generar código de barras interno:", err);
        }
    }, 120); 
}

// ========= Modal de Código de Barras Independiente =========
function mostrarCodigo(id) {
    const rep = datos.find(x => x.id == id);
    if (!rep) return;

    const codigoCompleto = rep.codigo_barras || rep.codigo || rep.folio || rep.id;
    
    currentBarcode = String(codigoCompleto);
    currentModelo = rep.modelo;
    currentCliente = rep.nombre_cliente;
    currentFalla = rep.tipo_reparacion;

    barcodeModal.style.display = 'flex';
    barcodeSpinner.style.display = 'inline-block';
    barcodeWrap.style.display = 'none';
    barcodeError.style.display = 'none';

    btnPrintBarcode.disabled = true;
    btnCopyBarcode.disabled = true;

    setTimeout(() => {
        try {
            const svgElement = document.getElementById("barcode-svg");
            svgElement.style.maxWidth = "100%";
            svgElement.style.height = "auto";

            JsBarcode("#barcode-svg", currentBarcode, {
                format: "CODE128",
                width: 1.6,
                height: 55,
                displayValue: true, 
                text: currentBarcode,
                fontSize: 15,
                fontOptions: "bold",
                textPosition: "bottom",
                font: "Poppins",
                margin: 5
            });

            document.getElementById('barcode-text').innerText = ""; 
            barcodeSpinner.style.display = 'none';
            barcodeWrap.style.display = 'block';
            btnPrintBarcode.disabled = false;
            btnCopyBarcode.disabled = false;
        } catch (err) {
            console.error(err);
            barcodeSpinner.style.display = 'none';
            barcodeError.innerText = "Error al generar código alfanumérico";
            barcodeError.style.display = 'block';
        }
    }, 150);
}

// ========= Acciones del Código de Barras =========
if (btnPrintBarcode) {
    btnPrintBarcode.addEventListener('click', () => {
        if (currentBarcode) {
            const url = `/local3M/imprimir_etiqueta.php?codigo=${encodeURIComponent(currentBarcode)}` +
                        `&nombre=${encodeURIComponent(currentModelo)}` +
                        `&cliente=${encodeURIComponent(currentCliente)}` +
                        `&detalles=${encodeURIComponent(currentFalla)}`;
            window.open(url, '_blank', 'width=400,height=500');
        }
    });
}

if (btnCopyBarcode) {
    btnCopyBarcode.addEventListener('click', () => {
        if (currentBarcode) {
            navigator.clipboard.writeText(currentBarcode).then(() => {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Copiado al portapapeles', timer: 1500, showConfirmButton: false });
            });
        }
    });
}

// ========= Función para mandar Reparaciones Listas al Carrito Global =========
function enviarReparacionAlCarritoGlobal(idReparacion, modelo, costoTotal, saldoPendiente) {
    if (saldoPendiente <= 0) {
        Swal.fire('Aviso', 'Esta reparación ya está liquidada.', 'info');
        return;
    }

    const itemGlobal = {
        id: idReparacion,
        tipo: 'reparacion',
        nombre: 'Entrega: ' + modelo,
        costo_total: parseFloat(costoTotal),
        a_cobrar: parseFloat(saldoPendiente)
    };

    if (typeof agregarAlCarritoGlobal === 'function') {
        agregarAlCarritoGlobal(itemGlobal);
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Saldo enviado al carrito', timer: 1500, showConfirmButton: false });
    } else {
        Swal.fire('Error', 'El módulo del carrito de cobro global no está disponible.', 'error');
    }
}

// ========= Eliminar Orden de Taller =========
function eliminarReparacion(id) {
    Swal.fire({
        title: '¿Estás completamente seguro?',
        text: "Esta acción eliminará permanentemente el registro del taller y no podrá recuperarse.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff3b30',
        cancelButtonColor: '#8e8e93',
        confirmButtonText: 'Sí, borrar registro',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `/local3M/api/eliminar_reparacion.php?id=${id}`;
        }
    });
}

// ========= Helpers de sanitización anti-XSS =========
function escapeHTML(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/[&<>"']/g, function (m) {
        switch (m) {
            case '&': return '&amp;';
            case '<': return '&lt;';
            case '>': return '&gt;';
            case '"': return '&quot;';
            case "'": return '&#039;';
            default: return m;
        }
    });
}

function escapeJS(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '\\r');
}

// ========= Cerrar Modales al Hacer Clic Fuera =========
document.querySelectorAll('.glass-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});

// Carga inicial al abrir el panel
document.addEventListener('DOMContentLoaded', () => {
    cargarPagina();
});