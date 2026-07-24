<?php include 'templates/header.php'; ?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/local3M/css/vitrina.css?v=<?php echo time(); ?>">

<div class="productos-wrapper">
    <div class="page-title">
        <h1>Vitrina y Apartados</h1>
        <p class="glass-label">Control total de tus equipos: celulares, bicicletas eléctricas y más.</p>
    </div>

    <div class="glass-card main-content">
        <div class="toolbar">
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="buscarVitrina" class="glass-input" placeholder="Buscar IMEI, iPhone 13, Juan Pérez...">
            </div>
            
            <div class="action-buttons">
                <button class="glass-btn primary" onclick="abrirModalNuevo()">
                    <i class="fas fa-plus"></i> Nuevo Equipo
                </button>
            </div>
        </div>

        <div class="table-wrap">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Equipo</th>
                        <th>IMEI / Serie</th>
                        <th>Costo / Venta</th>
                        <th>Estado</th>
                        <th>Cliente y Fecha</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaVitrinaBody">
                    <!-- JS inyectará los equipos aquí -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- MODAL 1: NUEVO EQUIPO / EDITAR                 -->
<!-- ============================================== -->
<div id="modalNuevoEquipo" class="modal-overlay" style="display:none;">
    <div class="modal-content glass-card">
        <button class="modal-close" onclick="cerrarModal('modalNuevoEquipo')"><i class="fas fa-times"></i></button>
        <h2 class="section-title" id="tituloModalNuevo">Registrar Equipo</h2>
        
        <form id="formNuevoEquipo" onsubmit="return false;">
            <input type="hidden" id="equipo_id" name="id">
            
            <div class="row-2-col">
                <div class="form-group">
                    <label class="glass-label">Tipo <span class="text-danger">*</span></label>
                    <select class="glass-input" name="tipo" id="equipo_tipo" required>
                        <option value="Celular">Celular</option>
                        <option value="Bicicleta Eléctrica">Bicicleta Eléctrica</option>
                        <option value="Tableta">Tableta</option>
                        <option value="Scooter">Scooter</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="glass-label">IMEI / Serie <span class="text-danger">*</span></label>
                    <input type="text" class="glass-input" name="imei_serie" id="equipo_imei" required>
                </div>
            </div>

            <div class="row-2-col">
                <div class="form-group">
                    <label class="glass-label">Marca</label>
                    <input type="text" class="glass-input" name="marca" id="equipo_marca" placeholder="Ej: Apple">
                </div>
                <div class="form-group">
                    <label class="glass-label">Modelo</label>
                    <input type="text" class="glass-input" name="modelo" id="equipo_modelo" placeholder="Ej: iPhone 13">
                </div>
            </div>

            <div class="row-2-col">
                <div class="form-group">
                    <label class="glass-label">Color</label>
                    <input type="text" class="glass-input" name="color" id="equipo_color" placeholder="Ej: Midnight">
                </div>
                <!-- Vacio para cuadrar la grilla -->
                <div></div>
            </div>

            <div class="row-2-col" style="background: rgba(0, 122, 255, 0.03); padding: 15px; border-radius: 12px; margin-bottom: 15px;">
                <div class="form-group" style="margin:0;">
                    <label class="glass-label">Costo (Compra) $</label>
                    <input type="number" step="0.01" class="glass-input" name="costo" id="equipo_costo" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="glass-label">Precio (Venta) $</label>
                    <input type="number" step="0.01" class="glass-input" name="precio_venta" id="equipo_precio" required>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="glass-btn outline-btn" onclick="cerrarModal('modalNuevoEquipo')">Cancelar</button>
                <button type="submit" class="glass-btn primary" onclick="guardarEquipo()">Guardar Equipo</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================== -->
<!-- MODAL 2: VENDER EQUIPO (CONTADO)               -->
<!-- ============================================== -->
<div id="modalVender" class="modal-overlay" style="display:none;">
    <div class="modal-content glass-card">
        <button class="modal-close" onclick="cerrarModal('modalVender')"><i class="fas fa-times"></i></button>
        <h2 class="section-title">Venta de Contado</h2>
        
        <form id="formVender" onsubmit="return false;">
            <input type="hidden" id="vender_id_equipo">
            
            <div class="resumen-equipo">
                <h3 id="vender_nombre_equipo" style="margin:0; color:#007aff;"></h3>
                <p class="glass-label" id="vender_imei_equipo"></p>
                <h2 style="margin:10px 0; color:#1d1d1f;" id="vender_precio_equipo"></h2>
            </div>

            <div class="row-2-col">
                <div class="form-group">
                    <label class="glass-label">Nombre del Cliente <span class="text-danger">*</span></label>
                    <input type="text" class="glass-input" id="vender_cliente" required placeholder="Para garantías...">
                </div>
                <div class="form-group">
                    <label class="glass-label">Teléfono</label>
                    <input type="text" class="glass-input" id="vender_telefono" placeholder="10 dígitos..." maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                </div>
            </div>

            <div class="form-group">
                <label class="glass-label">Fecha y Hora Exacta</label>
                <input type="text" class="glass-input disabled-input" id="vender_fecha_hora" readonly>
            </div>

            <div class="modal-footer">
                <button type="button" class="glass-btn outline-btn" onclick="cerrarModal('modalVender')">Cancelar</button>
                <button type="button" class="glass-btn success" onclick="procesarAccionVitrina('Vender')"><i class="fas fa-check"></i> Registrar Venta</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================== -->
<!-- MODAL 3: APARTAR EQUIPO                        -->
<!-- ============================================== -->
<div id="modalApartar" class="modal-overlay" style="display:none;">
    <div class="modal-content glass-card">
        <button class="modal-close" onclick="cerrarModal('modalApartar')"><i class="fas fa-times"></i></button>
        <h2 class="section-title">Apartar Equipo</h2>
        
        <form id="formApartar" onsubmit="return false;">
            <input type="hidden" id="apartar_id_equipo">
            <input type="hidden" id="apartar_precio_oculto">
            
            <div class="resumen-equipo" style="border-color: rgba(255, 149, 0, 0.3); background: rgba(255, 149, 0, 0.05);">
                <h3 id="apartar_nombre_equipo" style="margin:0; color:#ff9500;"></h3>
                <p class="glass-label" id="apartar_imei_equipo"></p>
                <h2 style="margin:10px 0; color:#1d1d1f;" id="apartar_precio_equipo"></h2>
            </div>

            <div class="row-2-col">
                <div class="form-group">
                    <label class="glass-label">Nombre del Cliente <span class="text-danger">*</span></label>
                    <input type="text" class="glass-input" id="apartar_cliente" required>
                </div>
                <div class="form-group">
                    <label class="glass-label">Teléfono</label>
                    <input type="text" class="glass-input" id="apartar_telefono" placeholder="10 dígitos..." maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                </div>
            </div>

            <div class="row-2-col">
                <div class="form-group">
                    <label class="glass-label">Anticipo ($) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" class="glass-input" id="apartar_anticipo" oninput="calcularSaldo()" required>
                </div>
                <div class="form-group">
                    <label class="glass-label">Resta ($)</label>
                    <input type="text" class="glass-input disabled-input" id="apartar_saldo" readonly value="0.00">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="glass-btn outline-btn" onclick="cerrarModal('modalApartar')">Cancelar</button>
                <button type="button" class="glass-btn" style="background:#ff9500; color:white;" onclick="procesarAccionVitrina('Apartar')"><i class="fas fa-clock"></i> Registrar Apartado</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================== -->
<!-- MODAL 4: ABONAR A UN APARTADO                  -->
<!-- ============================================== -->
<div id="modalAbonar" class="modal-overlay" style="display:none;">
    <div class="modal-content glass-card">
        <button class="modal-close" onclick="cerrarModal('modalAbonar')"><i class="fas fa-times"></i></button>
        <h2 class="section-title">Registrar Abono</h2>
        
        <form id="formAbonar" onsubmit="return false;">
            <input type="hidden" id="abonar_id_equipo">
            <input type="hidden" id="abonar_saldo_actual_oculto">
            
            <!-- Resumen Verde para destacar la sección de cobro -->
            <div class="resumen-equipo" style="border-color: rgba(52, 199, 89, 0.3); background: rgba(52, 199, 89, 0.05);">
                <h3 id="abonar_nombre_equipo" style="margin:0; color:#28a745;"></h3>
                <p class="glass-label" id="abonar_imei_equipo"></p>
                <h2 style="margin:10px 0; color:#ff3b30;" id="abonar_saldo_texto"></h2>
            </div>

            <div class="row-2-col">
                <div class="form-group">
                    <label class="glass-label">Monto a Abonar ($) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" class="glass-input" id="abonar_monto" oninput="calcularNuevoSaldo()" required placeholder="Ej: 500">
                </div>
                <div class="form-group">
                    <label class="glass-label">Nuevo Saldo Restante ($)</label>
                    <input type="text" class="glass-input disabled-input" id="abonar_nuevo_saldo" readonly>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="glass-btn outline-btn" onclick="cerrarModal('modalAbonar')">Cancelar</button>
                <button type="button" class="glass-btn success" onclick="procesarAbono()">
                    <i class="fas fa-hand-holding-usd"></i> Guardar Abono
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/local3M/js/vitrina.js?v=<?php echo time(); ?>"></script>
<?php include 'templates/footer.php'; ?>