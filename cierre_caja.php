<?php include 'templates/header.php'; ?>

<!-- Estilos -->
<link rel="stylesheet" href="/css/cierre_caja.css?v=1.0">

<div class="page-title">
    <h1>Gestión de Turno</h1>
    <p>Apertura y Cierre de Caja diario.</p>
</div>

<div class="cierre-container">
    <!-- Encabezado con Botón de Regreso -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div id="loader" class="loader-container" style="margin:0; padding:0;">
            <div class="spinner" style="width:20px; height:20px; border-width:2px; display:inline-block; vertical-align:middle;"></div>
            <span style="font-size:0.9rem; margin-left:10px;">Cargando estado...</span>
        </div>
        
        <!-- AQUÍ ESTÁ EL BOTÓN QUE PEDISTE -->
        <a href="caja.php" class="form-button btn-secondary" style="text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Volver a Caja
        </a>
    </div>

    <!-- VISTA 1: CAJA CERRADA (Formulario de Apertura) -->
    <div id="viewCerrada" class="caja-card closed-card" style="display:none;">
        <div class="card-icon"><i class="fas fa-store-alt-slash"></i></div>
        <h2>La Caja está Cerrada</h2>
        <p>Inicia un nuevo turno para comenzar a operar.</p>
        
        <form id="formAbrir" onsubmit="return false;">
            <div class="form-group">
                <label>Fondo Inicial (Dinero en cajón)</label>
                <div class="input-group">
                    <span class="currency-symbol">$</span>
                    <input type="number" id="saldo_inicial" class="form-input big-input" step="0.01" required>
                </div>
                <small class="hint">Fondo sugerido (del cierre anterior): <span id="fondoSugerido" class="fw-bold">$0.00</span></small>
            </div>
            <button class="form-button btn-primary btn-lg" onclick="abrirCaja()">
                <i class="fas fa-door-open"></i> ABRIR CAJA
            </button>
        </form>
    </div>

    <!-- VISTA 2: CAJA ABIERTA (Resumen y Cierre) -->
    <div id="viewAbierta" class="caja-grid" style="display:none;">
        
        <!-- Columna Izquierda: Información -->
        <div class="info-col">
            <div class="info-card">
                <div class="info-header">
                    <span class="badge-open">TURNO ABIERTO</span>
                    <small id="fechaApertura">--/--/-- --:--</small>
                </div>
                
                <div class="system-calc">
                    <div class="calc-row">
                        <span>Saldo Inicial</span>
                        <span id="sysInicial">$0.00</span>
                    </div>
                    <div class="calc-row text-success">
                        <span>+ Ingresos</span>
                        <span id="sysIngresos">$0.00</span>
                    </div>
                    <div class="calc-row text-danger">
                        <span>- Egresos</span>
                        <span id="sysEgresos">$0.00</span>
                    </div>
                    <hr>
                    <div class="calc-row total-row">
                        <span>Total Teórico</span>
                        <span id="sysTeorico">$0.00</span>
                    </div>
                    <small class="hint-text">* Dinero que el sistema espera encontrar.</small>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Formulario de Cierre -->
        <div class="form-col">
            <div class="cierre-form-card">
                <h3><i class="fas fa-calculator"></i> Realizar Corte</h3>
                
                <form id="formCerrar" onsubmit="return false;">
                    <input type="hidden" id="id_cierre">

                    <div class="form-group">
                        <label>1. ¿Cuánto dinero contaste? (Real)</label>
                        <input type="number" id="saldo_real" class="form-input" step="0.01" oninput="calcularCierre()" required>
                    </div>

                    <div class="form-group">
                        <label>2. ¿Cuánto dejas para mañana? (Fondo)</label>
                        <input type="number" id="fondo_sig" class="form-input" step="0.01" oninput="calcularCierre()" required>
                    </div>

                    <!-- Cálculos automáticos visuales -->
                    <div class="results-preview">
                        <div class="res-row">
                            <span>Diferencia:</span>
                            <span id="resDiferencia" class="fw-bold">$0.00</span>
                        </div>
                        <div class="res-row">
                            <span>A Retirar (Ganancia):</span>
                            <span id="resRetiro" class="fw-bold text-primary">$0.00</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Notas del Cierre</label>
                        <textarea id="notas" class="form-textarea" rows="2" placeholder="Observaciones..."></textarea>
                    </div>

                    <button class="form-button btn-danger btn-lg" onclick="cerrarCaja()">
                        <i class="fas fa-lock"></i> CERRAR TURNO
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/js/cierre_caja.js?v=1.0"></script>

<?php include 'templates/footer.php'; ?>