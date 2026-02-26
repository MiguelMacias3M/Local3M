<?php include 'templates/header.php'; ?>

<!-- Enlace a los estilos nuevos -->
<link rel="stylesheet" href="css/gastos.css?v=<?php echo time(); ?>">

<div class="gastos-container">
    
    <!-- Encabezado -->
    <div class="header-gastos">
        <div>
            <h1>Ingresos y Gastos</h1>
            <p class="text-muted">Administración detallada con evidencia.</p>
        </div>
        <button class="form-button btn-primary" onclick="abrirModalNuevo()">
            <i class="fas fa-plus-circle"></i> Nuevo Movimiento
        </button>
    </div>

    <!-- Barra de Filtros -->
    <div class="filter-bar">
        <div class="form-group">
            <label for="filtroFecha">Fecha</label>
            <input type="date" id="filtroFecha" class="form-input" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="form-group">
            <label for="filtroTipo">Tipo de Movimiento</label>
            <select id="filtroTipo" class="form-input">
                <option value="">Todos (Ingresos y Gastos)</option>
                <option value="INGRESO">Solo Ingresos</option>
                <option value="GASTO">Solo Gastos</option>
            </select>
        </div>
        <div class="form-group" style="flex: 0 0 auto;">
            <label>&nbsp;</label>
            <button class="form-button btn-secondary" onclick="cargarMovimientos()">
                <i class="fas fa-search"></i> Filtrar
            </button>
        </div>
    </div>

    <!-- Tabla de Resultados -->
    <div class="table-responsive">
        <table class="table-gastos">
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Tipo</th>
                    <th>Categoría</th>
                    <th>Descripción</th>
                    <th class="text-center">Evidencia</th>
                    <th class="text-right">Monto</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaBody">
                <!-- Se llena con JS -->
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL NUEVO/EDITAR MOVIMIENTO -->
<div id="modalNuevo" class="custom-overlay" style="display:none;">
    <div class="custom-modal">
        <button class="custom-close" onclick="cerrarModal()">&times;</button>
        <h2 id="modalTitle">Registrar Movimiento</h2>
        
        
        <form id="formGasto" enctype="multipart/form-data">
            <!-- ID OCULTO PARA EDICIÓN -->
            <input type="hidden" name="id" id="inputId">
            <input type="hidden" name="action" value="guardar">
            
            <div class="form-group">
                <label>Tipo de Movimiento</label>
                <select name="tipo" id="inputTipo" class="form-input" onchange="actualizarCategorias()">
                    <option value="GASTO">Gasto (Salida de dinero)</option>
                    <option value="INGRESO">Ingreso (Entrada de dinero)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Categoría</label>
                <select name="categoria" id="inputCategoria" class="form-input">
                    <!-- Se llena dinámicamente con JS -->
                </select>
            </div>
            <div class="form-group">
                <label>Fecha y Hora (Opcional - Puedes cambiarla) <span class="text-danger">*</span></label>
                <input type="datetime-local" class="form-input" name="fecha_movimiento" id="inputFechaMovimiento" required>
            </div>
            <div class="form-group">
                <label>Descripción detallada</label>
                <textarea name="descripcion" id="inputDescripcion" class="form-input" rows="2" placeholder="Ej: Pago de recibo CFE Enero, Compra de material..." required></textarea>
            </div>

            <div class="row-2-col">
                <div class="form-group">
                    <label>Monto ($)</label>
                    <input type="number" name="monto" id="inputMonto" class="form-input" step="0.01" min="0.1" required>
                </div>
                <div class="form-group">
                    <label>Foto / Comprobante (Opcional)</label>
                    <input type="file" name="foto" id="inputFoto" class="form-input" accept="image/*">
                </div>
            </div>

            <!-- Previsualización de imagen -->
            <div id="previewContainer">
                <p class="small text-muted">Vista previa de la evidencia:</p>
                <img id="imgPreview" src="">
            </div>

            <div class="modal-footer">
                <button type="button" class="form-button btn-secondary" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" id="btnGuardar" class="form-button btn-primary">Guardar Registro</button>
            </div>
        </form>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/gastos.js?v=<?php echo time(); ?>"></script>

<?php include 'templates/footer.php'; ?>