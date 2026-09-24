<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// BLOQUEO DE SEGURIDAD
if (!isset($_SESSION['rol']) || strtolower($_SESSION['rol']) !== 'admin') {
    header("Location: /local3M/dashboard.php");
    exit();
}
?>
<?php include 'templates/header.php'; ?>

<link rel="stylesheet" href="css/gastos.css?v=<?php echo time(); ?>">

<div class="glass-container" style="max-width: 1200px;">
    
    <!-- ENCABEZADO CON BOTONES ALINEADOS A LA DERECHA -->
    <div class="page-title-wrap">
        <div class="title-desc">
            <h1><i class="fas fa-wallet" style="color:#34c759; margin-right:10px;"></i>Control de Gastos</h1>
            <p>Administración detallada de entradas, salidas y comprobantes.</p>
        </div>
        
        <div class="header-actions-wrapper">
            <!-- Exportador de Balance -->
            <div class="export-group-glass">
                <div class="export-input-wrapper" title="Seleccionar Mes">
                    <i class="far fa-calendar-alt"></i>
                    <input type="month" id="mesExportar" class="glass-month-input" value="<?php echo date('Y-m'); ?>">
                </div>
                <button class="glass-btn success export-btn" onclick="exportarMesExcel()">
                    <i class="fas fa-file-excel"></i> Balance
                </button>
            </div>
            
            <!-- Botón Nuevo Registro con Efecto Neón -->
            <button class="btn-neon-snake" onclick="abrirModalNuevo()">
                <span><i class="fas fa-plus-circle"></i> Nuevo Registro</span>
            </button>
        </div>
    </div>

    <!-- TARJETAS DE RESUMEN (KPIs) -->
    <div class="kpi-grid">
        <div class="glass-card kpi-mini-card">
            <div class="kpi-icon-wrap" style="background: rgba(52, 199, 89, 0.15); color: #34c759;"><i class="fas fa-arrow-down"></i></div>
            <div class="kpi-info-wrap">
                <span class="kpi-title">Ingresos (Fecha)</span>
                <span class="kpi-number" id="resumen-ingresos" style="color: #1d1d1f;">$0.00</span>
            </div>
        </div>
        <div class="glass-card kpi-mini-card">
            <div class="kpi-icon-wrap" style="background: rgba(255, 59, 48, 0.15); color: #ff3b30;"><i class="fas fa-arrow-up"></i></div>
            <div class="kpi-info-wrap">
                <span class="kpi-title">Gastos / Salidas</span>
                <span class="kpi-number" id="resumen-gastos" style="color: #1d1d1f;">$0.00</span>
            </div>
        </div>
        <div class="glass-card kpi-mini-card">
            <div class="kpi-icon-wrap" style="background: rgba(0, 122, 255, 0.15); color: #007aff;"><i class="fas fa-balance-scale"></i></div>
            <div class="kpi-info-wrap">
                <span class="kpi-title">Balance Neto</span>
                <span class="kpi-number" id="resumen-balance" style="color: #1d1d1f;">$0.00</span>
            </div>
        </div>
    </div>

    <!-- TABLA DE MOVIMIENTOS Y FILTROS AUTOMÁTICOS -->
    <div class="glass-card" style="padding: 0; overflow: hidden; margin-bottom: 25px; display: flex; flex-direction: column;">
        
        <div class="table-header-glass" style="display: flex; gap: 15px; flex-wrap: wrap; background: rgba(250, 250, 252, 0.5);">
            <!-- FILTROS INTELIGENTES -->
            <div class="date-filter-glass" style="flex: 1; min-width: 200px;">
                <i class="far fa-calendar-alt"></i>
                <input type="date" id="filtroFecha" class="glass-date-input" style="width: 100%;" onchange="cargarMovimientos()">
            </div>
            
            <div class="date-filter-glass" style="flex: 1; min-width: 200px;">
                <i class="fas fa-filter"></i>
                <select id="filtroTipo" class="glass-date-input" style="width: 100%;" onchange="cargarMovimientos()">
                    <option value="">Todos (Ingresos y Gastos)</option>
                    <option value="INGRESO">Solo Ingresos</option>
                    <option value="GASTO">Solo Gastos</option>
                </select>
            </div>
        </div>

        <div class="glass-table-wrapper" style="border: none; border-radius: 0; box-shadow: none;">
           <table class="glass-table" id="tablaGastos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tipo / Origen</th>
                        <th>Descripción</th>
                        <th style="text-align: right;">Monto</th>
                        <th>Categoría</th>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th style="text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="lista-movimientos">
                    <!-- Se llena con JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL NUEVO REGISTRO -->
<div id="modalNuevo" class="glass-modal-overlay">
    <div class="glass-modal-content" style="max-width: 600px;">
        <div class="modal-header-wrap">
            <h2 id="modalTitle"><i class="fas fa-exchange-alt" style="color:#007aff; margin-right:10px;"></i> Registrar</h2>
            <button class="close-modal-btn" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
        </div>
        
        <form id="formGasto" enctype="multipart/form-data">
            <input type="hidden" name="id" id="inputId">
            <input type="hidden" name="action" value="guardar">
            
            <div class="row-2-col">
                <div class="form-group">
                    <label class="glass-label">Tipo de Movimiento</label>
                    <select name="tipo" id="inputTipo" class="glass-input" onchange="actualizarCategorias()">
                        <option value="GASTO">Gasto (Salida de dinero)</option>
                        <option value="INGRESO">Ingreso (Entrada de dinero)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="glass-label">Categoría</label>
                    <select name="categoria" id="inputCategoria" class="glass-input" onchange="verificarMostrarProveedor()"></select>
                </div>
            </div>

            <div id="cajaProveedor" class="form-group" style="display: none;">
                <label class="glass-label"><i class="fas fa-truck-loading" style="color:#007aff;"></i> Proveedor Asociado</label>
                <select name="id_proveedor" id="inputProveedor" class="glass-input" onchange="verificarNuevoProveedor(this)"></select>
            </div>

            <div class="row-2-col">
                <div class="form-group">
                    <label class="glass-label">Fecha y Hora <span class="req-star">*</span></label>
                    <input type="datetime-local" class="glass-input" name="fecha_movimiento" id="inputFechaMovimiento" required>
                </div>
                <div class="form-group">
                    <label class="glass-label">Usuario Responsable <span class="req-star">*</span></label>
                    <input type="text" name="usuario" id="inputUsuario" class="glass-input" required>
                </div>
            </div>

            <div class="form-group">
                <label class="glass-label">Descripción detallada</label>
                <textarea name="descripcion" id="inputDescripcion" class="glass-input" rows="2" placeholder="Ej: Pago de recibo de luz, Compra de material..." required></textarea>
            </div>
            
            <div class="row-2-col">
                <div class="form-group">
                    <label class="glass-label">Monto ($)</label>
                    <input type="number" name="monto" id="inputMonto" class="glass-input" step="0.01" min="0.1" required style="font-size: 18px; font-weight: 800; color: #1d1d1f;">
                </div>
                <div class="form-group">
                    <label class="glass-label">Foto / Comprobante</label>
                    <input type="file" name="foto" id="inputFoto" class="glass-input" accept="image/*" style="padding: 10px;">
                </div>
            </div>

            <div id="previewContainer" style="display:none; text-align:center; background:rgba(0,0,0,0.02); padding:10px; border-radius:12px; margin-bottom: 15px;">
                <p style="font-size: 12px; color: #86868b; margin-bottom: 5px;">Vista previa del comprobante:</p>
                <img id="imgPreview" src="" style="max-height: 150px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
            </div>

            <div class="modal-action-footer">
                <button type="button" class="action-trigger-btn cancel-trigger" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" id="btnGuardar" class="action-trigger-btn commit-trigger"><i class="fas fa-save"></i> Guardar Registro</button>
            </div>
        </form>
    </div>
</div>

<script>
    const USUARIO_SESION = "<?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Sistema'); ?>";
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/gastos.js?v=<?php echo time(); ?>"></script>

<?php include 'templates/footer.php'; ?>