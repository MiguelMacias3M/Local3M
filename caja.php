<?php include 'templates/header.php'; ?>

<!-- ESTILOS EXCLUSIVOS -->
<link rel="stylesheet" href="/local3M/css/caja.css?v=<?php echo time(); ?>">

<div class="glass-container">
    
    <div class="page-title-wrap">
        <div class="title-desc">
            <h1><i class="fas fa-cash-register" style="color: #007aff; margin-right: 10px;"></i>Flujo de Caja</h1>
            <p>Controla ingresos, egresos y realiza tu corte de turno diario.</p>
        </div>
        
        <!-- Búsqueda Automática por Fecha -->
        <div class="date-filter-glass">
            <i class="far fa-calendar-alt"></i>
            <input type="date" id="filtroFecha" class="glass-date-input" title="Selecciona una fecha" onchange="cargarReporte()">
        </div>
    </div>

    <!-- TARJETAS PRINCIPALES (KPIs) -->
    <div class="kpi-grid">
        <!-- Tarjeta de Estado de Caja -->
        <div class="glass-card main-status-card">
            <div>
                <h3 id="lblEstadoCaja" style="color:#007aff; margin:0 0 5px 0;">Cargando...</h3>
                <div class="money-display" id="lblMontoActual">$0.00</div>
                <small id="lblDetalleCaja" style="color:#86868b; font-weight:600;">...</small>
            </div>
            <button id="btnCorteCaja" class="glass-btn primary" onclick="gestionarCaja()">
                <i class="fas fa-sync-alt"></i> Gestionar Turno
            </button>
        </div>

        <!-- Tarjetas de Resumen Financiero -->
        <div class="glass-card kpi-mini-card">
            <div class="kpi-icon-wrap" style="background: rgba(52, 199, 89, 0.15); color: #34c759;"><i class="fas fa-arrow-down"></i></div>
            <div class="kpi-info-wrap">
                <span class="kpi-title">Ingresos del Día</span>
                <span class="kpi-number" id="valIngresos" style="color: #1d1d1f;">$0.00</span>
            </div>
        </div>

        <div class="glass-card kpi-mini-card">
            <div class="kpi-icon-wrap" style="background: rgba(255, 59, 48, 0.15); color: #ff3b30;"><i class="fas fa-arrow-up"></i></div>
            <div class="kpi-info-wrap">
                <span class="kpi-title">Salidas / Gastos</span>
                <span class="kpi-number" id="valEgresos" style="color: #1d1d1f;">$0.00</span>
            </div>
        </div>

        <div class="glass-card kpi-mini-card">
            <div class="kpi-icon-wrap" style="background: rgba(90, 200, 250, 0.15); color: #5ac8fa;"><i class="fas fa-wallet"></i></div>
            <div class="kpi-info-wrap">
                <span class="kpi-title">Balance Neto</span>
                <span class="kpi-number" id="valNeto" style="color: #1d1d1f;">$0.00</span>
            </div>
        </div>
    </div>

    <!-- TABLA DE MOVIMIENTOS -->
    <div class="glass-card" style="padding: 0;">
        <div class="table-header-glass">
            <h2 style="margin: 0; font-size: 18px; font-weight: 700; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-list-ul" style="color: #86868b;"></i> Detalle de Movimientos
            </h2>
            <div class="table-actions-glass">
                <button class="glass-btn danger" onclick="abrirModalGasto()">
                    <i class="fas fa-minus-circle"></i> Retiro/Gasto
                </button>
                <button class="glass-btn success" onclick="abrirModalIngreso()">
                    <i class="fas fa-plus-circle"></i> Ingreso Extra
                </button>
            </div>
        </div>
        
        <div class="glass-table-wrapper" style="border:none; box-shadow:none;">
            <table class="glass-table" id="tablaCaja">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Origen</th>
                        <th>Concepto</th>
                        <th>Categoría</th>
                        <th>Usuario</th>
                        <th class="text-right">Monto</th>
                    </tr>
                </thead>
                <tbody id="tablaBody">
                    <!-- JS Inyecta Filas -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL LIQUID GLASS -->
<div id="modalMovimiento" class="glass-modal-overlay">
    <div class="glass-modal-content" style="max-width: 500px;">
        <div class="modal-header-wrap">
            <h2 id="modalTitle"><i class="fas fa-exchange-alt" style="color: #007aff; margin-right:10px;"></i> Registrar</h2>
            <button class="close-modal-btn" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
        </div>
        
        <form id="formMovimiento">
            <input type="hidden" id="tipoMovimiento" name="tipo">
            
            <div class="form-group">
                <label class="glass-label">Descripción o Motivo <span class="req-star">*</span></label>
                <input type="text" class="glass-input" name="descripcion" id="descripcion" placeholder="Ej: Pago de Luz, Venta chicles..." required autocomplete="off">
            </div>

            <div class="row-2-col">
                <div class="form-group">
                    <label class="glass-label">Monto ($) <span class="req-star">*</span></label>
                    <input type="number" class="form-input glass-input" style="font-weight:700; font-size:18px;" name="monto" id="monto" step="0.01" min="0.1" required>
                </div>
                <div class="form-group">
                    <label class="glass-label">Categoría</label>
                    <select class="glass-input" name="categoria" id="categoria">
                        <option value="General">General</option>
                        <option value="Alimentos">Alimentos</option>
                        <option value="Transporte">Transporte</option>
                        <option value="Servicios">Servicios (Luz/Internet)</option>
                        <option value="Proveedores">Proveedores</option>
                        <option value="Retiro">Retiro de Efectivo</option>
                    </select>
                </div>
            </div>

            <div class="modal-action-footer">
                <button type="button" class="action-trigger-btn cancel-trigger" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="action-trigger-btn commit-trigger" style="width: 100%;">Confirmar Operación</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/local3M/js/caja.js?v=<?php echo time(); ?>"></script>

<?php include 'templates/footer.php'; ?>