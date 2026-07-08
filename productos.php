<?php
include 'templates/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/local3M/css/productos.css?v=<?php echo time(); ?>">

<div class="productos-wrapper">
    <div class="page-title">
        <h1>Inventario de Productos</h1>
        <p class="glass-label">Gestiona tu stock, precios y códigos de barras de forma rápida.</p>
    </div>

    <div class="glass-card main-content">
        <div class="toolbar">
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="buscar" class="glass-input" placeholder="Buscar por nombre o código...">
            </div>
            
            <div class="action-buttons">
                <button class="glass-btn outline-btn" onclick="imprimirInventario()">
                    <i class="fas fa-file-pdf text-danger"></i> Imprimir Stock
                </button>

                <button class="glass-btn primary" onclick="abrirModal()">
                    <i class="fas fa-plus"></i> Nuevo Producto
                </button>
            </div>
        </div>

        <div class="table-wrap">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Código</th>
                        <th>Ubicación</th> 
                        <th>Precio</th>
                        <th>Stock</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaProductosBody">
                    </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modalProducto" class="modal-overlay" style="display:none;">
    <div class="modal-content glass-card">
        <button class="modal-close" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
        <h2 id="modalTitle" class="section-title">Nuevo Producto</h2>
        
        <form id="formProducto" onsubmit="return false;">
            <input type="hidden" id="id_productos" name="id_productos">
            
            <div class="form-group">
                <label class="glass-label">Nombre del Producto <span class="text-danger">*</span></label>
                <input type="text" class="glass-input" name="nombre_producto" id="nombre_producto" required>
            </div>

            <div class="row-2-col">
                <div class="form-group">
                    <label class="glass-label">Código de Barras</label>
                    <div class="input-group">
                        <input type="text" class="glass-input" name="codigo_barras" id="codigo_barras" placeholder="Auto si está vacío">
                        <button type="button" class="btn-icon outline-btn" onclick="generarCodigoAleatorio()" title="Generar código">
                            <i class="fas fa-random" style="color: #007aff;"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="glass-label">Ubicación</label>
                    <input type="text" class="glass-input" name="ubicacion" id="ubicacion" placeholder="Ej: Estante A1">
                </div>
            </div>

            <div class="row-2-col">
                <div class="form-group">
                    <label class="glass-label">Precio ($) <span class="text-danger">*</span></label>
                    <input type="number" class="glass-input" name="precio_producto" id="precio_producto" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="glass-label">Stock Inicial <span class="text-danger">*</span></label>
                    <input type="number" class="glass-input" name="cantidad_piezas" id="cantidad_piezas" required>
                </div>
            </div>

            <div id="barcodePreviewContainer" class="barcode-container">
                <p>ESCANEA ESTE CÓDIGO</p>
                <svg id="barcodePreview"></svg>
            </div>

            <div class="modal-footer">
                <button type="button" class="glass-btn outline-btn" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="glass-btn success" onclick="guardarProducto()">
                    <i class="fas fa-check"></i> Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="/local3M/js/productos.js?v=<?php echo time(); ?>"></script>

<?php include 'templates/footer.php'; ?>