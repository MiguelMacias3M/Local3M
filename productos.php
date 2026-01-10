<?php
include 'templates/header.php';
?>

<link rel="stylesheet" href="/css/productos.css?v=1.0">

<div class="page-title">
    <h1>Inventario de Productos</h1>
    <p>Gestiona tu stock, precios y códigos de barras.</p>
</div>

<div class="content-box">
    <!-- Barra de Herramientas -->
    <div class="toolbar">
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="buscar" class="form-input" placeholder="Buscar producto...">
        </div>
        <button class="form-button btn-primary" onclick="abrirModal()">
            <i class="fas fa-plus"></i> Nuevo Producto
        </button>
    </div>

    <!-- Tabla -->
    <div class="table-wrap">
        <table class="repair-table products-table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Código</th>
                    <th>Precio</th>
                    <th>Stock</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaProductosBody">
                <!-- Se llena con JS -->
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL PARA AGREGAR/EDITAR -->
<div id="modalProducto" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <button class="modal-close" onclick="cerrarModal()">&times;</button>
        <h2 id="modalTitle">Nuevo Producto</h2>
        
        <form id="formProducto" onsubmit="return false;">
            <input type="hidden" id="id_productos" name="id_productos">
            
            <div class="form-group">
                <label>Nombre del Producto <span class="text-danger">*</span></label>
                <input type="text" class="form-input" name="nombre_producto" id="nombre_producto" required>
            </div>

            <div class="row-2-col">
                <div class="form-group">
                    <label>Código de Barras</label>
                    <div class="input-group">
                        <input type="text" class="form-input" name="codigo_barras" id="codigo_barras" placeholder="Auto si está vacío">
                        <button type="button" class="btn-icon" onclick="generarCodigoAleatorio()" title="Generar"><i class="fas fa-random"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Ubicación</label>
                    <input type="text" class="form-input" name="ubicacion" id="ubicacion" placeholder="Ej: Estante A1">
                </div>
            </div>

            <div class="row-2-col">
                <div class="form-group">
                    <label>Precio ($) <span class="text-danger">*</span></label>
                    <input type="number" class="form-input" name="precio_producto" id="precio_producto" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Stock Inicial <span class="text-danger">*</span></label>
                    <input type="number" class="form-input" name="cantidad_piezas" id="cantidad_piezas" required>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="form-button btn-secondary" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="form-button btn-primary" onclick="guardarProducto()">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- JsBarcode para imprimir códigos en el futuro si quieres -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="/js/productos.js?v=1.0"></script>

<?php include 'templates/footer.php'; ?>