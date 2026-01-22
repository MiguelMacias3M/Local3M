<?php
// 1. Header y Conexión
include 'templates/header.php';
include 'config/conexion.php'; // Necesario para cargar los datos iniciales

// 2. Validar ID
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    echo "<div class='container'><div class='alert alert-danger'>ID de reparación no válido.</div></div>";
    include 'templates/footer.php';
    exit();
}
$id = (int)$_GET['id'];

// 3. Cargar datos de la reparación
$stmt = $conn->prepare("SELECT * FROM reparaciones WHERE id = :id");
$stmt->execute([':id' => $id]);
$reparacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reparacion) {
    echo "<div class='container'><div class='alert alert-warning'>Reparación no encontrada.</div></div>";
    include 'templates/footer.php';
    exit();
}

// URL Ticket (para JS)
$idTrans = $reparacion['id_transaccion'];
$ticketUrl = "generar_ticket_id.php?id_transaccion=" . urlencode($idTrans);
?>

<link rel="stylesheet" href="/local3M/css/editar_reparacion.css?v=1.0">

<div class="page-title">
    <h1>Editar Reparación #<?= $id ?></h1>
    <p>Modifica los detalles, registra abonos o entrega el equipo.</p>
</div>

<div class="content-box">
    <div class="edit-grid">
        <div class="edit-form-col">
            <form id="formEditar" onsubmit="return false;">
                <input type="hidden" name="id" value="<?= $id ?>">
                
                <div class="form-section">
                    <h3><i class="fas fa-user"></i> Cliente</h3>
                    <div class="form-group">
                        <label>Nombre del Cliente <span class="text-danger">*</span></label>
                        <input type="text" class="form-input" id="nombre_cliente" name="nombre_cliente" 
                               value="<?= htmlspecialchars($reparacion['nombre_cliente']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Teléfono (10 dígitos) <span class="text-danger">*</span></label>
                        <input type="tel" class="form-input" id="telefono" name="telefono" 
                               value="<?= htmlspecialchars($reparacion['telefono']) ?>" 
                               maxlength="10" minlength="10" pattern="[0-9]{10}" 
                               title="Debe contener exactamente 10 dígitos numéricos"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-mobile-alt"></i> Equipo</h3>
                    <div class="form-group">
                        <label>Tipo de Reparación <span class="text-danger">*</span></label>
                        <input type="text" class="form-input" id="tipo_reparacion" name="tipo_reparacion" 
                               value="<?= htmlspecialchars($reparacion['tipo_reparacion']) ?>" required>
                    </div>
                    <div class="row-2-col">
                        <div class="form-group">
                            <label>Marca <span class="text-danger">*</span></label>
                            <input type="text" class="form-input" id="marca_celular" name="marca_celular" 
                                   value="<?= htmlspecialchars($reparacion['marca_celular']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Modelo <span class="text-danger">*</span></label>
                            <input type="text" class="form-input" id="modelo" name="modelo" 
                                   value="<?= htmlspecialchars($reparacion['modelo']) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-dollar-sign"></i> Pagos</h3>
                    <div class="row-3-col">
                        <div class="form-group">
                            <label>Monto Total <span class="text-danger">*</span></label>
                            <input type="number" class="form-input" id="monto" name="monto" 
                                   value="<?= (int)$reparacion['monto'] ?>" min="0" oninput="calcularDeuda()" required>
                        </div>
                        <div class="form-group">
                            <label>Adelanto (Total)</label>
                            <input type="number" class="form-input" id="adelanto" name="adelanto" 
                                   value="<?= (int)$reparacion['adelanto'] ?>" min="0" oninput="calcularDeuda()" readonly>
                        </div>
                        <div class="form-group">
                            <label>Deuda</label>
                            <input type="number" class="form-input" id="deuda" name="deuda" 
                                   value="<?= (int)$reparacion['deuda'] ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="abono-box">
                        <input type="number" class="form-input" id="nuevo_abono_monto" placeholder="Monto a abonar..." min="1">
                        <button type="button" class="form-button btn-info" onclick="agregarAbono()">
                            <i class="fas fa-plus"></i> Agregar Abono
                        </button>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Estado e Info</h3>
                    <div class="form-group">
                        <label>Información Extra</label>
                        <input type="text" class="form-input" name="info_extra" value="<?= htmlspecialchars($reparacion['info_extra']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Estado Actual</label>
                        <select class="form-input" name="estado" id="selectEstado">
                            <?php
                            $estados = ['En espera', 'En revision', 'Diagnosticado', 'En preparacion', 'En progreso', 'Reparado', 'Entregado', 'Cancelado', 'No se pudo reparar'];
                            foreach ($estados as $est) {
                                $selected = ($reparacion['estado'] == $est) ? 'selected' : '';
                                echo "<option value='$est' $selected>$est</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="action-buttons-container">
                    <button type="button" class="form-button btn-primary" onclick="guardarCambios()">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                    <button type="button" class="form-button btn-success" onclick="entregarReparacion()">
                        <i class="fas fa-check-double"></i> Entregar Equipo
                    </button>
                    <button type="button" class="form-button btn-warning" onclick="imprimirTicket()">
                        <i class="fas fa-print"></i> Ticket
                    </button>
                    <a href="control.php" class="form-button btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </form>
        </div>

        <div class="edit-sidebar">
            <div class="barcode-card">
                <h4>Código de Barras</h4>
                <div class="barcode-display">
                    <svg id="barcode-svg"></svg>
                    <div class="barcode-text"><?= $reparacion['codigo_barras'] ?></div>
                </div>
                <div class="barcode-actions">
                    <button class="form-button btn-secondary btn-sm" id="btnCopiar">Copiar</button>
                    <button class="form-button btn-secondary btn-sm" id="btnImprimirCodigo">Imprimir</button>
                </div>
            </div>
        </div>
    </div> <div class="history-section" style="margin-top: 3rem; border-top: 1px solid #eee; padding-top: 1.5rem;">
        <h3 style="margin-bottom: 1rem; color: var(--primary-color); border-bottom: 2px solid #eee; padding-bottom: 0.5rem;">
            <i class="fas fa-history"></i> Historial de Movimientos
        </h3>
        
        <div style="overflow-x: auto;">
            <table class="repair-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f8f9fa;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Fecha y Hora</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Estado</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Comentario</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Responsable</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Consultar historial
                    $sql_h = "SELECT * FROM historial_reparaciones WHERE id_reparacion = :id ORDER BY fecha_cambio DESC";
                    $stmt_h = $conn->prepare($sql_h);
                    $stmt_h->execute([':id' => $id]);
                    $movimientos = $stmt_h->fetchAll(PDO::FETCH_ASSOC);

                    if (count($movimientos) > 0) {
                        foreach ($movimientos as $mov) {
                            // Formatear fecha (Ej: 21/01/2026 04:30 PM)
                            $fecha = date("d/m/Y h:i A", strtotime($mov['fecha_cambio']));
                            echo "<tr style='border-bottom: 1px solid #eee;'>";
                            echo "<td style='padding: 12px;'><strong>{$fecha}</strong></td>";
                            echo "<td style='padding: 12px;'><span class='status' style='background-color: #e2e6ea; color: #333; padding: 4px 10px; border-radius: 4px;'>" . htmlspecialchars($mov['estado_nuevo']) . "</span></td>";
                            echo "<td style='padding: 12px; color: #555;'>" . htmlspecialchars($mov['comentario']) . "</td>";
                            echo "<td style='padding: 12px;'><i class='fas fa-user-circle' style='color: #888;'></i> " . htmlspecialchars($mov['usuario_responsable']) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' style='padding: 20px; text-align:center; color:#999; font-style: italic;'>No hay movimientos registrados para esta reparación.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    </div> <script>
    const REPARACION_ID = <?= $id ?>;
    const TICKET_URL = "<?= $ticketUrl ?>";
    const CODIGO_BARRAS = "<?= $reparacion['codigo_barras'] ?>";
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="/local3M/js/editar_reparacion.js"></script>

<?php include 'templates/footer.php'; ?>