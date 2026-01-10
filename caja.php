<?php include 'templates/header.php'; ?>

<!-- 
    IMPORTANTE: ?v=FINAL_FIX_DUPLICADOS para forzar la carga de los nuevos estilos 
-->
<link rel="stylesheet" href="/css/caja.css?v=FINAL_FIX_DUPLICADOS">

<div class="page-title">
    <h1>Flujo de Caja 3M</h1>
    <p>Consulta ingresos, gastos y el estado actual de la caja.</p>
</div>

<!-- PANEL SUPERIOR: Estado y Filtros -->
<div class="caja-header-grid">
    
    <!-- Tarjeta Estado -->
    <div class="content-box status-card" id="statusCard">
        <div class="status-icon"><i class="fas fa-cash-register"></i></div>
        <div class="status-info">
            <h3 id="lblEstadoCaja">Cargando...</h3>
            <div class="money-display" id="lblMontoActual">$0.00</div>
            <small id="lblDetalleCaja">...</small>
        </div>
        <div class="status-actions">
            <button id="btnCorteCaja" class="form-button btn-primary" onclick="gestionarCaja()">
                <i class="fas fa-sync-alt"></i> Gestionar Turno
            </button>
        </div>
    </div>

    <!-- Tarjeta Filtros -->
    <div class="content-box filters-card">
        <h3><i class="fas fa-filter"></i> Filtros de Reporte</h3>
        <div class="filters-row">
            <div class="form-group">
                <label>Fecha</label>
                <input type="date" id="filtroFecha" class="form-input" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Usuario</label>
                <select id="filtroUsuario" class="form-input">
                    <option value="Todos">Todos</option>
                    <!-- Se llena con JS -->
                </select>
            </div>
            <button class="form-button btn-secondary btn-icon" onclick="cargarReporte()" title="Actualizar">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>
</div>

<!-- KPI GRID (Indicadores) -->
<div class="kpi-grid">
    <div class="kpi-card kpi-success">
        <div class="kpi-icon"><i class="fas fa-arrow-down"></i></div>
        <div class="kpi-data">
            <span class="kpi-label">Ingresos del Día</span>
            <span class="kpi-value" id="valIngresos">$0.00</span>
        </div>
    </div>
    <div class="kpi-card kpi-danger">
        <div class="kpi-icon"><i class="fas fa-arrow-up"></i></div>
        <div class="kpi-data">
            <span class="kpi-label">Gastos / Salidas</span>
            <span class="kpi-value" id="valEgresos">$0.00</span>
        </div>
    </div>
    <div class="kpi-card kpi-info">
        <div class="kpi-icon"><i class="fas fa-wallet"></i></div>
        <div class="kpi-data">
            <span class="kpi-label">Balance Neto</span>
            <span class="kpi-value" id="valNeto">$0.00</span>
        </div>
    </div>
</div>

<!-- TABLA DE MOVIMIENTOS -->
<div class="content-box">
    <div class="table-header">
        <h2>Detalle de Movimientos</h2>
        <div class="table-actions">
            <button class="form-button btn-danger btn-sm" onclick="abrirModalGasto()">
                <i class="fas fa-minus-circle"></i> Registrar Gasto
            </button>
            <button class="form-button btn-success btn-sm" onclick="abrirModalIngreso()">
                <i class="fas fa-plus-circle"></i> Ingreso Extra
            </button>
            <button class="form-button btn-secondary btn-sm" id="btnExportar">
                <i class="fas fa-file-csv"></i> CSV
            </button>
        </div>
    </div>
    
    <div class="table-wrap">
        <table class="repair-table caja-table" id="tablaCaja">
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th>Categoría</th>
                    <th>Usuario</th>
                    <th class="text-right">Monto</th>
                </tr>
            </thead>
            <tbody id="tablaBody">
                <!-- Se llena con JS -->
            </tbody>
        </table>
    </div>
</div>

<!-- 
    MODAL CORREGIDO:
    1. Quitamos 'onsubmit' del form (lo maneja JS).
    2. Quitamos 'onclick' del botón Submit (lo maneja el evento submit).
-->
<div id="modalMovimiento" class="custom-overlay" style="display:none;">
    <div class="custom-modal">
        <button class="custom-close" onclick="cerrarModal()">&times;</button>
        <h2 id="modalTitle">Registrar Gasto</h2>
        
        <form id="formMovimiento">
            <input type="hidden" id="tipoMovimiento" name="tipo">
            
            <div class="form-group">
                <label>Descripción <span class="text-danger">*</span></label>
                <input type="text" class="form-input" name="descripcion" id="descripcion" placeholder="Ej: Comida, Transporte" required>
            </div>

            <div class="row-2-col">
                <div class="form-group">
                    <label>Monto ($) <span class="text-danger">*</span></label>
                    <input type="number" class="form-input" name="monto" id="monto" step="0.01" min="0.1" required>
                </div>
                <div class="form-group">
                    <label>Categoría</label>
                    <select class="form-input" name="categoria" id="categoria">
                        <option value="General">General</option>
                        <option value="Alimentos">Alimentos</option>
                        <option value="Transporte">Transporte</option>
                        <option value="Servicios">Servicios (Luz/Internet)</option>
                        <option value="Proveedores">Proveedores</option>
                        <option value="Retiro">Retiro de Efectivo</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="form-button btn-secondary" onclick="cerrarModal()">Cancelar</button>
                <!-- CORRECCIÓN: Se quitó onclick="guardarMovimiento()" para evitar doble envío -->
                <button type="submit" class="form-button btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/js/caja.js?v=<?php echo time(); ?>"></script>

<?php include 'templates/footer.php'; ?>