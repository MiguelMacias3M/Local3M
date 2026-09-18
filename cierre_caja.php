<?php include 'templates/header.php'; ?>

<link rel="stylesheet" href="/local3M/css/cierre_caja.css?v=<?php echo time(); ?>">

<div class="glass-container">
    <div class="page-title-wrap">
        <div class="title-desc">
            <h1><i class="fas fa-lock" style="color: #ff3b30; margin-right: 10px;"></i>Corte de Turno</h1>
            <p>Arqueo de efectivo físico y cierre de caja.</p>
        </div>
        <button class="glass-btn secondary" onclick="window.location.href='caja.php'">
            <i class="fas fa-arrow-left"></i> Volver a Caja
        </button>
    </div>

    <!-- VISTA 1: ABRIR CAJA (Se muestra si está CERRADA) -->
    <div id="vista_abrir" style="display: none;">
        <div class="glass-card center-card">
            <div class="icon-circle success-bg"><i class="fas fa-store"></i></div>
            <h2>La caja está cerrada</h2>
            <p style="color:#86868b; margin-bottom: 25px;">Este es el fondo que hay en la caja. Antes de abrir tu turno, corrobora que sea correcto</p>
            
            <div class="form-group" style="text-align: left;">
                <label class="glass-label">Fondo Inicial ($)</label>
                <input type="number" id="saldo_inicial" class="glass-input text-center" style="font-size: 24px; font-weight: 800; color: #34c759;" step="0.5" value="0" disabled>
            </div>
            
            <button class="glass-btn success" style="width: 100%; height: 50px; font-size: 16px;" onclick="abrirCaja()">
                <i class="fas fa-door-open"></i> ABRIR CAJA
            </button>
        </div>
    </div>

    <!-- VISTA 2: CERRAR CAJA (Se muestra si está ABIERTA) -->
    <div id="vista_cierre" class="cierre-grid" style="display: none;">
        
        <!-- Columna Izquierda: Resumen del Sistema (Solo Efectivo) -->
        <div class="glass-card">
            <h3><i class="fas fa-desktop" style="color:#007aff;"></i> Sistema (Solo Efectivo)</h3>
            <div class="resumen-lista">
                <div class="resumen-item">
                    <span>Fondo Inicial:</span>
                    <strong id="lbl_inicial">$0.00</strong>
                </div>
                <div class="resumen-item">
                    <span>(+) Entradas Físicas:</span>
                    <strong id="lbl_ingresos" style="color:#34c759;">+$0.00</strong>
                </div>
                <div class="resumen-item">
                    <span>(-) Salidas / Retiros:</span>
                    <strong id="lbl_egresos" style="color:#ff3b30;">-$0.00</strong>
                </div>
                <hr style="border-top: 1px dashed rgba(0,0,0,0.1); margin: 15px 0;">
                <div class="resumen-item total-teorico">
                    <span>Efectivo Teórico (Debería haber):</span>
                    <strong id="lbl_teorico" style="color:#007aff;">$0.00</strong>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Arqueo Físico -->
        <div class="glass-card">
            <h3><i class="fas fa-money-bill-wave" style="color:#34c759;"></i> Arqueo Físico</h3>
            
            <div class="form-group">
                <label class="glass-label">Efectivo Real en Cajón <span class="req-star">*</span></label>
                <div style="display: flex; gap: 10px;">
                    <input type="number" id="saldo_real" class="glass-input" style="font-size: 20px; font-weight: 800; color: #1d1d1f;" placeholder="0.00" oninput="calcularCierre()" step="0.5">
                    <button class="glass-btn info" onclick="abrirModalContador()" title="Contar billetes y monedas" style="width: auto; padding: 0 15px;">
                        <i class="fas fa-calculator"></i> Contar
                    </button>
                </div>
            </div>

            <div class="diferencia-box" id="cajaDiferencia">
                <span>Diferencia:</span>
                <strong id="lbl_diferencia">$0.00</strong>
            </div>

            <hr style="border-top: 1px dashed rgba(0,0,0,0.1); margin: 20px 0;">

            <div class="row-2-col">
                <div class="form-group">
                    <label class="glass-label">Fondo para mañana <span class="req-star">*</span></label>
                    <input type="number" id="fondo_sig" class="glass-input" placeholder="0.00" oninput="calcularCierre()" step="0.5">
                </div>
                <div class="form-group">
                    <label class="glass-label">Retiro de Ganancia</label>
                    <input type="number" id="lbl_retiro" class="glass-input" placeholder="0.00" readonly style="background: rgba(0,122,255,0.05); color:#007aff; font-weight:700;">
                </div>
            </div>

            <div class="form-group">
                <label class="glass-label">Notas o Justificaciones</label>
                <input type="text" id="notas" class="glass-input" placeholder="Justifica faltantes o sobrantes aquí...">
            </div>

            <button class="glass-btn danger" style="width: 100%; height: 55px; font-size: 16px; margin-top: 10px;" onclick="cerrarCaja()">
                <i class="fas fa-lock"></i> CERRAR TURNO
            </button>
        </div>
    </div>
</div>

<!-- MODAL: CONTADOR DE BILLETES Y MONEDAS -->
<div id="modalContador" class="glass-modal-overlay">
    <div class="glass-modal-content" style="max-width: 600px;">
        <div class="modal-header-wrap">
            <h2 style="margin:0; font-size:22px;"><i class="fas fa-coins" style="color: #ff9500; margin-right:10px;"></i> Calculadora de Efectivo</h2>
            <button class="close-modal-btn" onclick="cerrarModalContador()"><i class="fas fa-times"></i></button>
        </div>

        <div class="contador-grid">
            <!-- Billetes -->
            <div class="denominacion-col">
                <h4 style="text-align:center; color:#86868b; margin-top:0; letter-spacing:1px;">BILLETES</h4>
                <div class="denominacion-item"><label>$1,000</label><input type="number" class="glass-input den-input" data-val="1000" min="0" placeholder="0"></div>
                <div class="denominacion-item"><label>$500</label><input type="number" class="glass-input den-input" data-val="500" min="0" placeholder="0"></div>
                <div class="denominacion-item"><label>$200</label><input type="number" class="glass-input den-input" data-val="200" min="0" placeholder="0"></div>
                <div class="denominacion-item"><label>$100</label><input type="number" class="glass-input den-input" data-val="100" min="0" placeholder="0"></div>
                <div class="denominacion-item"><label>$50</label><input type="number" class="glass-input den-input" data-val="50" min="0" placeholder="0"></div>
                <div class="denominacion-item"><label>$20</label><input type="number" class="glass-input den-input" data-val="20" min="0" placeholder="0"></div>
            </div>
            
            <!-- Monedas -->
            <div class="denominacion-col">
                <h4 style="text-align:center; color:#86868b; margin-top:0; letter-spacing:1px;">MONEDAS</h4>
                <div class="denominacion-item"><label>$20</label><input type="number" class="glass-input den-input" data-val="20" min="0" placeholder="0"></div>
                <div class="denominacion-item"><label>$10</label><input type="number" class="glass-input den-input" data-val="10" min="0" placeholder="0"></div>
                <div class="denominacion-item"><label>$5</label><input type="number" class="glass-input den-input" data-val="5" min="0" placeholder="0"></div>
                <div class="denominacion-item"><label>$2</label><input type="number" class="glass-input den-input" data-val="2" min="0" placeholder="0"></div>
                <div class="denominacion-item"><label>$1</label><input type="number" class="glass-input den-input" data-val="1" min="0" placeholder="0"></div>
                <div class="denominacion-item"><label>50¢</label><input type="number" class="glass-input den-input" data-val="0.5" min="0" placeholder="0"></div>
            </div>
        </div>

        <div class="contador-total-wrap">
            <span style="color:#86868b; font-size:14px; font-weight:600; text-transform:uppercase;">Total en Cajón</span>
            <strong id="lblTotalContador" style="color:#34c759; font-size:36px;">$0.00</strong>
        </div>

        <div class="modal-action-footer" style="margin-top:20px;">
            <button class="action-trigger-btn cancel-trigger" onclick="limpiarContador()"><i class="fas fa-broom"></i> Limpiar</button>
            <button class="action-trigger-btn commit-trigger" onclick="aplicarConteo()"><i class="fas fa-check"></i> Transferir a la caja</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/local3M/js/cierre_caja.js?v=<?php echo time(); ?>"></script>

<?php include 'templates/footer.php'; ?>