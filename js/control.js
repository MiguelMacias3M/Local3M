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
let currentBarcode = ''; // Variable para guardar el código actual

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

// Elementos Modal Código de Barras
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
    btnMas.disabled = true;
    globalLoader.style.display = 'block';

    try {
        // Usamos ruta absoluta para la API para evitar errores de carpetas
        const url = new URL('/local3M/api/get_reparaciones.php', window.location.origin);
        url.searchParams.set('offset', offset);
        url.searchParams.set('limit', LIMIT);
        if (busqueda) url.searchParams.set('q', busqueda);

        const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
        const json = await res.json();
        
        if (!res.ok) throw new Error(json.error || 'Error de servidor');
        if (!json.success) throw new Error(json.error || 'Error al cargar');

        const chunk = json.data || [];

        if (offset === 0) {
            datos = [];
            tbody.innerHTML = '';
        }

        noResults.style.display = (offset === 0 && chunk.length === 0) ? 'block' : 'none';

        const baseIndex = datos.length;
        datos.push(...chunk);
        renderFilas(chunk, baseIndex);

        finDeLista = !json.hasMore;
        btnMas.style.display = finDeLista ? 'none' : 'block';
        offset += LIMIT;

    } catch (err) {
        console.error(err);
        Swal.fire('Ups', err.message || 'Error inesperado', 'error');
    } finally {
        cargando = false;
        btnMas.disabled = false;
        globalLoader.style.display = 'none';
    }
}

function renderFilas(chunk, baseIndex) {
    chunk.forEach((row, i) => {
        const idx = baseIndex + i;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${esc(row.nombre_cliente)}</td>
            <td>${esc(row.tipo_reparacion)}</td>
            <td>${esc(row.modelo)}</td>
            <td>${esc(row.codigo_barras)}</td>
            <td>${renderEstado(esc(row.estado))}</td>
            <td class="td-actions">
                <button class='form-button btn-info' data-action="ver" data-index="${idx}">
                    <i class="fas fa-eye"></i> Ver
                </button>
                <a href='/local3M/editar_reparacion.php?id=${row.id}' class='form-button btn-warning'>
                    <i class="fas fa-edit"></i> Editar
                </a>
                <button class='form-button btn-danger' data-action="eliminar" data-id="${row.id}">
                    <i class="fas fa-trash"></i> Eliminar
                </button>
                <button class='form-button btn-primary' data-action="imprimir" data-idtx="${esc(row.id_transaccion)}">
                    <i class="fas fa-print"></i> Ticket
                </button>
                <button class='form-button btn-secondary btn-barcode' data-action="barcode" data-id='${row.id}'>
                    <i class="fas fa-barcode"></i> Código
                </button>
            </td>`;
        tbody.appendChild(tr);
    });
}

function esc(val){ return (val ?? '').toString().replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

function renderEstado(estadoRaw){
    const text = (estadoRaw || '').toString().trim();
    if (!text) return `<span class="status status-unknown">--</span>`;
    
    // Normalizar texto para comparar (minusculas, sin acentos)
    const normalized = text.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/\s+/g,' ');

    let cls = 'status-unknown';
    if (normalized.includes('espera')) cls = 'status-wait';
    else if (normalized.includes('revision') || normalized.includes('diagnosticado')) cls = 'status-pending';
    else if (normalized.includes('progreso')) cls = 'status-progress';
    else if (normalized.includes('reparado')) cls = 'status-ready';
    else if (normalized.includes('entregado')) cls = 'status-completed';
    else if (normalized.includes('cancelado') || normalized.includes('no se pudo')) cls = 'status-cancelled';
    
    return `<span class="status ${cls}">${text}</span>`;
}

// ========= Acciones de la tabla (Event Delegation) =========
tbody.addEventListener('click', (e) => {
    const btn = e.target.closest('button, a');
    if (!btn) return;

    const action = btn.getAttribute('data-action');
    
    if (action === 'ver') {
        e.preventDefault();
        const index = parseInt(btn.getAttribute('data-index'), 10);
        const row = isNaN(index) ? null : datos[index];
        if (row) mostrarDetalles(row);
    } else if (action === 'eliminar') {
        e.preventDefault();
        const id = btn.getAttribute('data-id');
        if (id) confirmarEliminar(id);
    } else if (action === 'imprimir') {
        e.preventDefault();
        const idtx = btn.getAttribute('data-idtx');
        if (idtx) imprimirTicket(idtx);
    } else if (action === 'barcode') {
        e.preventDefault();
        const id = btn.getAttribute('data-id');
        if (id) mostrarCodigo(id);
    }
});

// Función de Eliminar con Contraseña (Seguridad)
function confirmarEliminar(id) {
    Swal.fire({
        title: 'Confirmar Eliminación',
        text: "Esta acción no se puede deshacer. Ingresa la contraseña de administrador:",
        input: 'password',
        inputPlaceholder: 'Contraseña',
        inputAttributes: { autocapitalize: 'off', autocorrect: 'off' },
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
        showLoaderOnConfirm: true,
        preConfirm: (password) => {
            // Petición POST a la API
            return fetch('/local3M/api/eliminar_reparacion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, password: password })
            })
            .then(response => {
                if (!response.ok) throw new Error(response.statusText);
                return response.json();
            })
            .then(data => {
                if (!data.success) throw new Error(data.error || 'Error desconocido');
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(`Error: ${error.message}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire('¡Eliminado!', 'La reparación ha sido eliminada correctamente.', 'success')
                .then(() => {
                    // Recargar tabla limpiamente (sin recargar página)
                    offset = 0; datos = []; tbody.innerHTML = ''; cargarPagina();
                });
        }
    });
}

// Resto de utilidades de búsqueda...
inputBuscar.addEventListener('input', () => {
    const txt = inputBuscar.value.trim().toLowerCase();
    if (!txt) {
        tbody.innerHTML = '';
        renderFilas(datos, 0);
        noResults.style.display = (datos.length === 0) ? 'block' : 'none';
        return;
    }
    const filtrados = datos.filter(d => coincideBusqueda(d, txt));
    tbody.innerHTML = '';
    if(filtrados.length > 0) renderFilas(filtrados, 0);
    noResults.style.display = (filtrados.length === 0) ? 'block' : 'none';
});

function coincideBusqueda(row, txt){
    if(!txt) return true;
    const campos = [
        row.nombre_cliente, row.tipo_reparacion, row.modelo, row.codigo_barras
    ].map(v => (v||'').toString().toLowerCase());
    return campos.some(c => c.includes(txt));
}

btnBuscar.addEventListener('click', ejecutarBusquedaServidor);
btnLimpiar.addEventListener('click', () => {
    inputBuscar.value = '';
    busqueda = '';
    offset = 0; 
    finDeLista = false;
    cargarPagina();
});

function ejecutarBusquedaServidor(){
    busqueda = inputBuscar.value.trim();
    offset = 0; 
    finDeLista = false;
    cargarPagina();
}

btnMas.addEventListener('click', cargarPagina);

function formatoFechaMx(iso){
    if(!iso) return '-';
    const s = String(iso).replace(' ','T');
    const d = new Date(s);
    if(isNaN(d)) return iso;
    return d.toLocaleString('es-MX',{year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'});
}

// Mostrar Detalles (Incluye Fecha de Entrega)
function mostrarDetalles(row){
    const detalles = `
        <strong>Atendió:</strong> ${esc(row.usuario)}<br>
        <strong>Cliente:</strong> ${esc(row.nombre_cliente)}<br>
        <strong>Teléfono:</strong> ${esc(row.telefono)}<br>
        <strong>Fecha de ingreso:</strong> ${formatoFechaMx(row.fecha_hora)}<br>
        <strong style="color: #000000ff;">Fecha de entrega:</strong> ${formatoFechaMx(row.fecha_entrega)}<br> 
        
        <hr style="border-color: #eee; margin: 10px 0;">
        <strong>Tipo de Reparación:</strong> ${esc(row.tipo_reparacion)}<br>
        <strong>Marca:</strong> ${esc(row.marca_celular)}<br>
        <strong>Modelo:</strong> ${esc(row.modelo)}<br>
        <strong>Código barras:</strong> ${esc(row.codigo_barras)}<br>
        <hr style="border-color: #eee; margin: 10px 0;">
        <strong>Monto:</strong> $${esc(row.monto)}<br>
        <strong>Adelanto:</strong> $${esc(row.adelanto)}<br>
        <strong>Deuda:</strong> $${esc(row.deuda)}<br>
        <strong>Info Extra:</strong> ${esc(row.info_extra)}<br>
        <strong>Estado:</strong> ${esc(row.estado)}`;
    
    detallesContenido.innerHTML = detalles;
    modalDetalles.style.display = "flex";
}

function cerrarModal(){ modalDetalles.style.display = "none"; }

function imprimirTicket(idTransaccion){
    window.location.href = '/local3M/generar_ticket_id.php?id_transaccion=' + encodeURIComponent(idTransaccion);
}

// ===== Modal Código de Barras =====
function openBarcodeModal(){ barcodeModal.style.display = 'flex'; }
function closeBarcodeModal(){ barcodeModal.style.display = 'none'; }

async function mostrarCodigo(id) {
    barcodeSpinner.style.display = 'block';
    barcodeWrap.style.display = 'none';
    barcodeError.style.display = 'none';
    
    // Deshabilitar botones mientras carga
    if(btnPrintBarcode) btnPrintBarcode.disabled = true;
    if(btnCopyBarcode) btnCopyBarcode.disabled = true;

    document.getElementById('barcode-svg').innerHTML = '';
    document.getElementById('barcode-text').textContent = '';

    openBarcodeModal();

    try {
        // Petición a la API para obtener o generar el código
        const res = await fetch(`/local3M/api/get_codigo_reparacion.php?id=${encodeURIComponent(id)}`, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.error || 'No se pudo obtener el código.');

        const code = data.codigo_barras;
        if (!code) throw new Error('Esta reparación no tiene código de barras.');

        currentBarcode = code; // Guardar para usar en los botones

        JsBarcode("#barcode-svg", code, { 
            format:"code128", 
            width: 2, 
            height: 80, 
            displayValue: false, 
            margin: 10 
        });

        document.getElementById('barcode-text').textContent = code;
        barcodeSpinner.style.display = 'none';
        barcodeWrap.style.display = 'block';
        
        // Habilitar botones
        if(btnPrintBarcode) btnPrintBarcode.disabled = false;
        if(btnCopyBarcode) btnCopyBarcode.disabled = false;

    } catch (err) {
        barcodeSpinner.style.display = 'none';
        barcodeError.textContent = err.message || 'Error inesperado.';
        barcodeError.style.display = 'block';
    }
}

// Listeners para los botones del modal de código de barras
if(btnCopyBarcode){
    btnCopyBarcode.addEventListener('click', () => {
        if(currentBarcode){
            navigator.clipboard.writeText(currentBarcode);
            const originalText = btnCopyBarcode.innerHTML;
            btnCopyBarcode.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
            setTimeout(() => { btnCopyBarcode.innerHTML = originalText; }, 1500);
        }
    });
}

if(btnPrintBarcode){
    btnPrintBarcode.addEventListener('click', () => {
        const svg = document.getElementById('barcode-svg');
        if(svg && currentBarcode){
            const w = window.open('', '_blank', 'width=400,height=300');
            w.document.write(`<html><body style="text-align:center; margin-top:50px;">${svg.outerHTML}<br><strong style="font-family:monospace; letter-spacing:3px; font-size:20px;">${currentBarcode}</strong><script>window.print();</script></body></html>`);
            w.document.close();
        }
    });
}

// Primera carga
cargarPagina();