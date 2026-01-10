<?php include 'templates/header.php'; ?>

<!-- Estilos específicos -->
<link rel="stylesheet" href="/local3M/css/mercancia.css?v=1.0">

<div class="page-title">
    <h1>Inventario de Mercancía</h1>
    <p>Gestiona refacciones, ubicaciones y costos de toda tu mercancía.</p>
</div>

<div class="content-box">
    <!-- Toolbar -->
    <div class="toolbar">
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="buscar" class="form-input" placeholder="Buscar por marca, modelo o código...">
        </div>
        <button class="form-button btn-primary" onclick="abrirModal()">
            <i class="fas fa-plus"></i> Nueva Mercancía
        </button>
    </div>

    <!-- Tabla -->
    <div class="table-wrap">
        <table class="repair-table merch-table">
            <thead>
                <tr>
                    <th>Marca/Modelo</th>
                    <th>Compatibilidad</th>
                    <th>Ubicación</th>
                    <th>Costo</th>
                    <th class="text-center">Stock</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaMercanciaBody">
                <!-- Se llena con JS -->
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL PARA AGREGAR/EDITAR -->
<div id="modalMercancia" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <button class="modal-close" onclick="cerrarModal()">&times;</button>
        <h2 id="modalTitle">Nueva Mercancía</h2>
        
        <form id="formMercancia" onsubmit="return false;">
            <input type="hidden" id="id_mercancia" name="id">
            
            <div class="row-2-col">
                <div class="form-group">
                    <label>Marca <span class="text-danger">*</span></label>
                    <input type="text" class="form-input" name="marca" id="marca" required>
                </div>
                <div class="form-group">
                    <label>Modelo <span class="text-danger">*</span></label>
                    <input type="text" class="form-input" name="modelo" id="modelo" required>
                </div>
            </div>

            <div class="form-group">
                <label>Compatibilidad</label>
                <input type="text" class="form-input" name="compatibilidad" id="compatibilidad" placeholder="Ej: A10, A20, M10">
            </div>

            <div class="row-2-col">
                <div class="form-group">
                    <label>Costo ($) <span class="text-danger">*</span></label>
                    <input type="number" class="form-input" name="costo" id="costo" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Stock Inicial</label>
                    <input type="number" class="form-input" name="cantidad" id="cantidad" value="1" min="0" required>
                </div>
            </div>

            <div class="row-2-col">
                <div class="form-group">
                    <label>Ubicación</label>
                    <input type="text" class="form-input" name="ubicacion" id="ubicacion" placeholder="Ej: Caja 4">
                </div>
                <div class="form-group">
                    <label>Código de Barras</label>
                    <div class="input-group">
                        <input type="text" class="form-input" name="codigo_barras" id="codigo_barras" placeholder="Auto">
                        <button type="button" class="btn-icon" onclick="generarCodigo()" title="Generar"><i class="fas fa-random"></i></button>
                    </div>
                </div>
            </div>
            
            <!-- Preview del código de barras -->
            <div id="barcode-preview" style="text-align:center; margin-top:10px; display:none;">
                <svg id="barcode-svg"></svg>
            </div>

            <div class="modal-footer">
                <button type="button" class="form-button btn-secondary" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="form-button btn-primary" onclick="guardarMercancia()">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="/local3M/js/mercancia.js?v=1.0"></script>

<?php include 'templates/footer.php'; ?>