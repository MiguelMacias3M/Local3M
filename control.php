<?php
// 1. Incluimos el header (seguridad, menú, etc.)
include 'templates/header.php';

// Verificamos si hay un mensaje (ej. de eliminación)
$toastMessage = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'eliminado') {
        $toastMessage = "Swal.fire({ icon:'success', title:'Eliminado', text:'La reparación fue eliminada correctamente', timer:2000, showConfirmButton:false });";
    }
}
?>

<!-- 
    Estilos con versión forzada 
-->
<link rel="stylesheet" href="/css/control.css?v=9999">

<!-- Título de la Página -->
<div class="page-title">
    <h1>Control de Reparaciones</h1>
    <p>Consulta, filtra y administra todas tus órdenes de trabajo.</p>
</div>

<!-- Controles de Búsqueda -->
<div class="content-box search-box">
    <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="buscar" class="form-input" placeholder="Buscar por cliente, modelo, reparación o código de barras…">
    </div>
    <div class="search-buttons">
        <button id="btnBuscar" class="form-button btn-primary"><i class="fas fa-search"></i> Buscar en Servidor</button>
        <button id="btnLimpiar" class="form-button btn-secondary"><i class="fas fa-times"></i> Limpiar</button>
    </div>
</div>

<!-- Contenedor de la Tabla -->
<div class="content-box">
    <div class="table-wrap">
        <table class="repair-table control-table" id="tablaReparaciones">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Tipo Reparación</th>
                    <th>Modelo</th>
                    <th>Código</th>
                    <th>Estado</th>
                    <th class="td-actions">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaReparacionesBody">
                <!-- El contenido se cargará con JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- Feedback para el usuario -->
    <div id="noResults" class="table-feedback" style="display:none;">
        <i class="fas fa-info-circle"></i> No se encontraron reparaciones que coincidan.
    </div>

    <div class="more-wrap">
        <button id="btnMas" class="form-button btn-secondary">Mostrar más</button>
        <div id="globalLoader" class="spinner" style="display:none;" aria-hidden="true"></div>
    </div>
</div>


<!-- MODALES -->
<div id="modalDetalles" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <button class="modal-close" onclick="cerrarModal()">&times;</button>
        <h2>Detalles de la Reparación</h2>
        <div id="detallesContenido" class="modal-body"></div>
    </div>
</div>

<div id="barcodeModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <button class="modal-close" onclick="closeBarcodeModal()">&times;</button>
        <h2>Código de Barras</h2>
        <div id="barcode-body" class="modal-body">
            <div id="barcode-spinner" class="spinner" style="display:none;"></div>
            <div id="barcode-wrap" style="display:none; text-align:center;">
                <svg id="barcode-svg"></svg>
                <div id="barcode-text"></div>
            </div>
            <div id="barcode-error" class="form-error" style="display:none;"></div>
        </div>
        <div class="modal-footer">
            <button class="form-button btn-secondary" onclick="closeBarcodeModal()">Cerrar</button>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- 
    LA SOLUCIÓN ESTÁ AQUÍ:
    Cambiamos la versión a ?v=9999 para obligar al navegador a olvidar el archivo viejo.
-->
<script src="/js/control.js?v=9999"></script>

<!-- Script para mostrar mensajes (alertas PHP) -->
<script>
    <?php echo $toastMessage; ?>
</script>

<?php
// 3. Incluimos el footer
include 'templates/footer.php';
?>