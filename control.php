<?php
include 'templates/header.php';

$toastMessage = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'eliminado') {
    $toastMessage = "Swal.fire({ icon:'success', title:'Eliminado', text:'La reparación fue eliminada correctamente', timer:2000, showConfirmButton:false });";
}
?>

<link rel="stylesheet" href="/local3M/css/control.css?v=<?php echo time(); ?>">

<div class="container glass-container">
    <div class="glass-header">
        <div>
            <h1><i class="fas fa-tasks" style="color: #5856d6;"></i> Control de Reparaciones</h1>
            <p>Consulta, filtra y administra todas tus órdenes de trabajo.</p>
        </div>
    </div>

    <div class="glass-card glass-search-box">
        <div class="glass-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="buscar" class="glass-input" placeholder="Buscar por cliente, modelo o código...">
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1;">
            <button id="btnBuscar" class="glass-btn primary" style="flex: 1;"><i class="fas fa-search"></i> Buscar</button>
            <button id="btnLimpiar" class="glass-btn" style="flex: 1;"><i class="fas fa-eraser"></i> Limpiar</button>
        </div>
    </div>

    <div class="glass-card">
        <div class="glass-table-wrapper">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Equipo</th>
                        <th>Problema</th>
                        <th>Estado</th>
                        <th style="text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaReparacionesBody">
                    </tbody>
            </table>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <button id="btnMas" class="glass-btn" onclick="cargarPagina()">Cargar más resultados</button>
            <div id="globalLoader" class="spinner" style="display:none; border-left-color: #007aff;"></div>
        </div>
        <p id="noResults" style="display:none; text-align:center; color:#86868b; margin-top:15px;">No se encontraron resultados.</p>
    </div>
</div>

<div id="modalDetalles" class="glass-modal-overlay" style="display: none;">
    <div class="glass-modal-content">
        <div class="detail-modal-header">
            <h2><i class="fas fa-info-circle" style="color:#007aff;"></i> Orden de Trabajo</h2>
            <span class="status status-progress" style="margin: 0; font-size: 12px;">Consulta General</span>
        </div>
        <div id="detallesContenido"></div>
    </div>
</div>

<div id="barcodeModal" class="glass-modal-overlay" style="display: none;">
    <div class="glass-modal-content" style="max-width: 400px; text-align: center; margin: auto;">
        <h3 style="margin-top:0; color: #1d1d1f;">Código de Barras</h3>
        
        <div style="margin: 20px 0; background: white; padding: 20px; border-radius: 14px; box-shadow: inset 0 2px 10px rgba(0,0,0,0.05);">
            <div id="barcode-spinner" class="spinner" style="display:none; border-left-color: #007aff;"></div>
            <div id="barcode-wrap" style="display:none;">
                <svg id="barcode-svg" style="max-width: 100%;"></svg>
                <div id="barcode-text" style="font-weight:bold; margin-top:10px; letter-spacing: 2px;"></div>
            </div>
            <div id="barcode-error" style="display:none; color: #ff3b30; font-weight: 600;"></div>
        </div>

        <div style="display: flex; gap: 10px; flex-direction: column;">
            <button id="btnPrintBarcode" class="glass-btn success" disabled><i class="fas fa-print"></i> Imprimir Etiqueta</button>
            <button id="btnCopyBarcode" class="glass-btn info" disabled><i class="fas fa-copy"></i> Copiar</button>
            <button class="glass-btn" onclick="document.getElementById('barcodeModal').style.display='none'">Cerrar</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php echo $toastMessage; ?>
</script>
<script src="/local3M/js/control.js?v=<?php echo time(); ?>"></script>
<?php include 'templates/footer.php'; ?>