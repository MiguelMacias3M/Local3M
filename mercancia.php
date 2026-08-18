<?php include 'templates/header.php'; ?>

<link rel="stylesheet" href="/local3M/css/mercancia.css?v=<?php echo time(); ?>">

<div class="container glass-container">
    <div class="page-title-wrap">
        <div class="title-desc">
            <h1><i class="fas fa-boxes" style="color: #007aff; margin-right: 10px;"></i>Inventario de Mercancía</h1>
            <p>Gestiona refacciones, componentes de reparación, ubicaciones físicos y costos reales de stock.</p>
        </div>
        <button class="glass-btn primary-btn" onclick="abrirModalNuevo()">
            <i class="fas fa-plus"></i> Nueva Mercancía
        </button>
    </div>

    <div class="glass-card toolbar-card">
        <div class="search-box-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="buscar" class="glass-input search-input" placeholder="Buscar por marca, modelo, tipo de repuesto o código de barras...">
        </div>
    </div>

    <div class="glass-table-wrapper">
        <table class="glass-table" id="tablaMercancia">
           <thead>
                <tr>
                    <th>Refacción / Código</th>
                    <th>Equipo Destino</th>
                    <th>Compatibilidad Ext.</th>
                    <th>Ubicación Almacén</th>
                    <th class="text-center">Stock Real</th>
                    <th>Precio Público</th> <!-- NUEVA COLUMNA -->
                    <th class="text-right">Acciones de Gestión</th>
                </tr>
            </thead>
            <tbody id="tablaMercanciaBody">
                </tbody>
        </table>
    </div>
</div>

<div id="modalMercancia" class="glass-modal-overlay">
    <div class="glass-modal-content">
        <div class="modal-header-wrap">
            <h2 id="modalTitle"><i class="fas fa-box-open" style="color: #007aff; margin-right: 10px;"></i>Detalle de Refacción</h2>
            <button class="close-modal-btn" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
        </div>
        
        <form id="formMercancia" onsubmit="event.preventDefault(); guardarMercancia();">
            <input type="hidden" name="id" id="inputId">
            
            <div class="row-2-col">
<div class="form-group">
                <label class="glass-label"><i class="fas fa-microchip"></i> Tipo de Pieza <span class="req-star">*</span></label>
                <select id="inputTipoRepuesto" name="tipo_repuesto_select" class="glass-input select-glass" onchange="verificarTipoOtro()" required>
                   <option value="">Selecciona una opción...</option>
                        <option value="Pantalla">Pantalla / Display LCD</option>
                        <option value="Batería">Batería de Litio</option>
                        <option value="Centro de Carga">Centro de Carga / Flex Pin</option>
                        <option value="Flexor">Flexor Interconector</option>
                        <option value="Tablilla">Tablilla Lógica / Sub-board</option>
                        <option value="Bocina / Auricular">Bocina / Auricular Altavoz</option>
                        <option value="Cámara">Módulo de Cámara</option>
                        <option value="Tapa Trasera">Tapa Trasera / Cristal</option>
                        <option value="Cristal de Cámara">Cristal de Cámara Lens</option>
                        <option value="Componente IC">Componente IC / Integrado</option>
                    <option value="Otro">➕ Otro (Especificar manualmente...)</option>
                </select>
                
                <input type="text" id="inputTipoRepuestoOtro" name="tipo_repuesto_otro" class="glass-input" placeholder="Ej: Lector de huella iPhone 13" style="display: none; margin-top: 12px; border: 1px solid #007aff; background: rgba(0, 122, 255, 0.05); transition: all 0.3s ease;">
            </div>
            
                <div class="form-group">
                    <label class="glass-label">Marca del Dispositivo <span class="req-star">*</span></label>
                    <input type="text" name="marca" id="inputMarca" class="glass-input" placeholder="Ej: Samsung, iPhone, Xiaomi" required>
                </div>
            </div>

            <div class="form-group">
                <label class="glass-label">Modelo Exacto de Aplicación <span class="req-star">*</span></label>
                <input type="text" name="modelo" id="inputModelo" class="glass-input" placeholder="Ej: Galaxy A54 5G, iPhone 13 Pro" required>
            </div>

            <div class="form-group">
                <label class="glass-label">Modelos Compatibles Alternos (Opcional)</label>
                <input type="text" name="compatibilidad" id="inputCompatibilidad" class="glass-input" placeholder="Ej: Compatible con SM-A546B, SM-A546E">
            </div>

           <div class="row-3-col">
                <div class="form-group">
                    <label class="glass-label">Stock Inicial <span class="req-star">*</span></label>
                    <input type="number" name="cantidad" id="inputCantidad" class="glass-input text-center font-bold" value="1" min="0" required>
                </div>
                
                <div class="form-group">
                    <label class="glass-label">Precio Público ($) <span class="req-star">*</span></label>
                    <input type="number" name="precio_publico" id="inputPrecioPublico" class="glass-input font-bold" placeholder="0" min="0" required>
                </div>
                
                <div class="form-group">
                    <label class="glass-label">Ubicación Física</label>
                    <input type="text" name="ubicacion" id="inputUbicacion" class="glass-input" placeholder="Ej: Caja 4A, Estante B">
                </div>
            </div>

            <!-- MAGIA PHP: Validación de Rol Inhackable (Fuera de las columnas) -->
            <?php if (isset($esAdmin) && $esAdmin): ?>
                <div class="form-group" id="grupoCostoSecreto" style="background: rgba(255,59,48,0.05); padding: 10px; border-radius: 8px; border: 1px dashed #ff3b30; margin-bottom: 15px;">
                    <label class="glass-label" style="color: #ff3b30;"><i class="fas fa-eye-slash"></i> Costo Real Mayoreo ($)</label>
                    <input type="number" name="costo" id="inputCosto" class="glass-input font-bold" value="0" min="0">
                </div>
            <?php else: ?>
                <input type="hidden" name="costo" id="inputCosto" value="0">
            <?php endif; ?>

            <div class="form-group barcode-section-wrap">
                <label class="glass-label">Código de Barras Interno</label>
                <div class="barcode-input-group">
                    <input type="text" name="codigo_barras" id="inputCodigoBarras" class="glass-input unique-barcode-input" placeholder="Dejar vacío para autogenerar prefijo MER">
                    <button type="button" class="action-trigger-btn secondary-trigger" onclick="generarCodigo()" title="Generar Código de Barras Único">
                        <i class="fas fa-random"></i> Generar
                    </button>
                </div>
                <div id="barcode-preview" class="barcode-container-render" style="display: none; text-align: center;">
                    <svg id="barcode-svg"></svg>
                </div>
            </div>

            <div class="modal-action-footer">
                <button type="button" class="action-trigger-btn cancel-trigger" onclick="cerrarModal()">Cancelar Operación</button>
                <button type="submit" id="btnSubmitForm" class="action-trigger-btn commit-trigger">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="/local3M/js/mercancia.js?v=<?php echo time(); ?>"></script>

<?php include 'templates/footer.php'; ?>