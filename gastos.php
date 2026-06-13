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

<div class="container glass-container" style="max-width: 1200px;">
    
    <div class="page-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px;">
        <div>
            <h1 style="margin: 0; font-weight: 800; font-size: 26px;"><i class="fas fa-wallet" style="color:#34c759;"></i> Control de Caja y Gastos</h1>
            <p style="color: #86868b; margin: 5px 0 0 0; font-size: 14px;">Administración detallada de entradas, salidas y comprobantes.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 5px; background: rgba(255,255,255,0.7); padding: 5px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.05);">
                <input type="month" id="mesExportar" class="glass-input" style="padding: 6px 12px; margin: 0; min-height: auto; width: 140px; font-size: 13px;" value="<?php echo date('Y-m'); ?>">
                <button class="glass-btn success" style="padding: 6px 12px; margin: 0; font-size: 13px;" onclick="exportarMesExcel()" title="Descargar Excel del mes">
                    <i class="fas fa-file-excel"></i> Descargar balance mensual
                </button>
            </div>
            <button class="glass-btn primary" onclick="abrirModalNuevo()">
                <i class="fas fa-plus-circle"></i> Nuevo Registro
            </button>
        </div>
    </div>

    <div class="row-3-col" style="margin-bottom: 25px;">
        <div class="glass-card text-center" style="padding: 20px;">
            <h4 style="margin: 0; color: #86868b; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">Ingresos (Fecha)</h4>
            <h2 id="resumen-ingresos" style="margin: 10px 0 0 0; font-size: 28px; color: #34c759; font-weight: 800;">$0.00</h2>
        </div>
        <div class="glass-card text-center" style="padding: 20px;">
            <h4 style="margin: 0; color: #86868b; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">Gastos / Salidas</h4>
            <h2 id="resumen-gastos" style="margin: 10px 0 0 0; font-size: 28px; color: #ff3b30; font-weight: 800;">$0.00</h2>
        </div>
        <div class="glass-card text-center" style="padding: 20px;">
            <h4 style="margin: 0; color: #86868b; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">Balance Neto</h4>
            <h2 id="resumen-balance" style="margin: 10px 0 0 0; font-size: 28px; color: #007aff; font-weight: 800;">$0.00</h2>
        </div>
    </div>

    <div class="glass-card" style="padding: 15px 25px; margin-bottom: 25px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 200px;">
            <label style="font-size: 13px; font-weight: 600; color: #86868b; margin-bottom: 5px; display: block;">Filtrar por Fecha</label>
            <input type="date" id="filtroFecha" class="glass-input" value="<?php echo date('Y-m-d'); ?>" style="margin:0;">
        </div>
        <div style="flex: 1; min-width: 200px;">
            <label style="font-size: 13px; font-weight: 600; color: #86868b; margin-bottom: 5px; display: block;">Tipo de Movimiento</label>
            <select id="filtroTipo" class="glass-input" style="margin:0;">
                <option value="">Todos (Ingresos y Gastos)</option>
                <option value="INGRESO">Solo Ingresos</option>
                <option value="GASTO">Solo Gastos</option>
            </select>
        </div>
        <div style="flex: 0 0 auto;">
            <button class="glass-btn info" style="margin:0; height: 48px;" onclick="cargarMovimientos()">
                <i class="fas fa-search"></i> Buscar
            </button>
        </div>
    </div>

    <div class="glass-table-wrapper">
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
                </tbody>
        </table>
    </div>
</div>

<div id="modalNuevo" class="glass-modal-overlay" style="display:none;">
    <div class="glass-modal-content" style="max-width: 600px; padding: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 15px; margin-bottom: 20px;">
            <h2 id="modalTitle" style="margin: 0; font-size: 20px; font-weight: 800; color: #1d1d1f;"><i class="fas fa-exchange-alt" style="color:#007aff;"></i> Registrar Movimiento</h2>
            <button class="btn-icon" style="background: rgba(255,59,48,0.1); color: #ff3b30;" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
        </div>
        
        <form id="formGasto" enctype="multipart/form-data">
            <input type="hidden" name="id" id="inputId">
            <input type="hidden" name="action" value="guardar">
            
            <div class="row-2-col" style="margin-bottom: 15px;">
                <div>
                    <label style="font-size: 13px; font-weight: 600; color: #86868b; display: block; margin-bottom: 5px;">Tipo de Movimiento</label>
                    <select name="tipo" id="inputTipo" class="glass-input" onchange="actualizarCategorias()" style="margin:0;">
                        <option value="GASTO">Gasto (Salida de dinero)</option>
                        <option value="INGRESO">Ingreso (Entrada de dinero)</option>
                    </select>
                </div>
                <div>
                    <label style="font-size: 13px; font-weight: 600; color: #86868b; display: block; margin-bottom: 5px;">Categoría</label>
                    <select name="categoria" id="inputCategoria" class="glass-input" style="margin:0;"></select>
                </div>
            </div>

            <div class="row-2-col" style="margin-bottom: 15px;">
                <div>
                    <label style="font-size: 13px; font-weight: 600; color: #86868b; display: block; margin-bottom: 5px;">Fecha y Hora <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="glass-input" name="fecha_movimiento" id="inputFechaMovimiento" required style="margin:0;">
                </div>
                <div>
                    <label style="font-size: 13px; font-weight: 600; color: #86868b; display: block; margin-bottom: 5px;">Usuario Responsable <span class="text-danger">*</span></label>
                    <input type="text" name="usuario" id="inputUsuario" class="glass-input" required style="margin:0;">
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="font-size: 13px; font-weight: 600; color: #86868b; display: block; margin-bottom: 5px;">Descripción detallada</label>
                <textarea name="descripcion" id="inputDescripcion" class="glass-input" rows="2" placeholder="Ej: Pago de recibo de luz, Compra de material..." required style="margin:0;"></textarea>
            </div>
            
            <div class="row-2-col" style="margin-bottom: 15px;">
                <div>
                    <label style="font-size: 13px; font-weight: 600; color: #86868b; display: block; margin-bottom: 5px;">Monto ($)</label>
                    <input type="number" name="monto" id="inputMonto" class="glass-input" step="0.01" min="0.1" required style="margin:0; font-size: 18px; font-weight: bold; color: #1d1d1f;">
                </div>
                <div>
                    <label style="font-size: 13px; font-weight: 600; color: #86868b; display: block; margin-bottom: 5px;">Foto / Comprobante</label>
                    <input type="file" name="foto" id="inputFoto" class="glass-input" accept="image/*" style="margin:0; padding: 10px;">
                </div>
            </div>

            <div id="previewContainer" style="display:none; text-align:center; background:rgba(0,0,0,0.02); padding:10px; border-radius:12px; margin-bottom: 15px;">
                <p style="font-size: 12px; color: #86868b; margin-bottom: 5px;">Vista previa del comprobante:</p>
                <img id="imgPreview" src="" style="max-height: 150px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px;">
                <button type="button" class="glass-btn secondary" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" id="btnGuardar" class="glass-btn primary"><i class="fas fa-save"></i> Guardar Registro</button>
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