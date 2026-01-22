<?php
include 'templates/header.php';
include 'config/conexion.php';

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    echo "<div class='container'><div class='alert alert-danger'>ID inválido.</div></div>";
    include 'templates/footer.php'; exit();
}
$id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM reparaciones WHERE id = :id");
$stmt->execute([':id' => $id]);
$reparacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reparacion) {
    echo "<div class='container'><div class='alert alert-warning'>No encontrado.</div></div>";
    include 'templates/footer.php'; exit();
}

$ticketUrl = "generar_ticket_id.php?id_transaccion=" . urlencode($reparacion['id_transaccion']);
?>

<link rel="stylesheet" href="/local3M/css/editar_reparacion.css?v=<?php echo time(); ?>">

<div class="page-title">
    <h1>Editar Reparación #<?= $id ?></h1>
</div>

<div class="content-box">
    <div class="edit-grid">
        <div class="edit-form-col">
            <form id="formEditar" onsubmit="return false;">
                <input type="hidden" name="id" value="<?= $id ?>">
                
                <div class="form-section">
                    <h3><i class="fas fa-user"></i> Cliente</h3>
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" class="form-input" id="nombre_cliente" value="<?= htmlspecialchars($reparacion['nombre_cliente']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="tel" class="form-input" id="telefono" value="<?= htmlspecialchars($reparacion['telefono']) ?>" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-mobile-alt"></i> Equipo</h3>
                    <div class="form-group">
                        <label>Reparación</label>
                        <input type="text" class="form-input" id="tipo_reparacion" value="<?= htmlspecialchars($reparacion['tipo_reparacion']) ?>">
                    </div>
                    <div class="row-2-col">
                        <div class="form-group">
                            <label>Marca</label>
                            <input type="text" class="form-input" id="marca_celular" value="<?= htmlspecialchars($reparacion['marca_celular']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Modelo</label>
                            <input type="text" class="form-input" id="modelo" value="<?= htmlspecialchars($reparacion['modelo']) ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-dollar-sign"></i> Pagos</h3>
                    <div class="row-3-col">
                        <div class="form-group">
                            <label>Monto</label>
                            <input type="number" class="form-input" id="monto" value="<?= (int)$reparacion['monto'] ?>" oninput="calcularDeuda()">
                        </div>
                        <div class="form-group">
                            <label>Adelanto</label>
                            <input type="number" class="form-input" id="adelanto" value="<?= (int)$reparacion['adelanto'] ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Deuda</label>
                            <input type="number" class="form-input" id="deuda" value="<?= (int)$reparacion['deuda'] ?>" readonly>
                        </div>
                    </div>
                    <div class="abono-box">
                        <input type="number" class="form-input" id="nuevo_abono_monto" placeholder="Monto abono...">
                        <button type="button" class="form-button btn-info" onclick="agregarAbono()">Agregar</button>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Estado y Evidencia</h3>
                    <div class="form-group">
                        <label>Info Extra</label>
                        <input type="text" class="form-input" name="info_extra" value="<?= htmlspecialchars($reparacion['info_extra']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select class="form-input" name="estado" id="selectEstado">
                            <?php
                            $estados = ['En espera', 'En revision', 'Diagnosticado', 'En preparacion', 'En progreso', 'Reparado', 'Entregado', 'Cancelado', 'No se pudo reparar'];
                            foreach ($estados as $est) {
                                $sel = ($reparacion['estado'] == $est) ? 'selected' : '';
                                echo "<option value='$est' $sel>$est</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-top: 15px; border-top: 1px dashed #ccc; padding-top: 10px;">
                        <label style="color: var(--primary-color); cursor:pointer;">
                            <i class="fas fa-camera"></i> <strong>Adjuntar Evidencia (Foto)</strong>
                        </label>
                        <input type="file" id="evidencia_input" accept="image/*" class="form-input" onchange="previsualizarFoto()">
                        
                        <div id="preview-container" style="display:none; margin-top:10px; text-align:center;">
                            <img id="img-preview" src="" style="max-height: 150px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                            <p style="font-size:0.8rem; color:green; margin:5px 0;">Foto lista para guardar</p>
                        </div>
                    </div>
                    </div>

                <div class="action-buttons-container">
                    <button type="button" class="form-button btn-primary" onclick="guardarCambios()"><i class="fas fa-save"></i> Guardar</button>
                    <button type="button" class="form-button btn-success" onclick="entregarReparacion()"><i class="fas fa-check-double"></i> Entregar</button>
                    <button type="button" class="form-button btn-warning" onclick="imprimirTicket()"><i class="fas fa-print"></i> Ticket</button>
                    <a href="control.php" class="form-button btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
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
            </div>
        </div>
    </div>

    <div class="history-section" style="margin-top: 3rem; border-top: 1px solid #eee; padding-top: 1.5rem;">
        <h3><i class="fas fa-history"></i> Historial de Movimientos</h3>
        <table class="repair-table" style="width:100%">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Comentario / Evidencia</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql_h = "SELECT * FROM historial_reparaciones WHERE id_reparacion = :id ORDER BY fecha_cambio DESC";
                $stmt_h = $conn->prepare($sql_h);
                $stmt_h->execute([':id' => $id]);
                $movs = $stmt_h->fetchAll(PDO::FETCH_ASSOC);

                if ($movs) {
                    foreach ($movs as $m) {
                        $fecha = date("d/m/Y h:i A", strtotime($m['fecha_cambio']));
                        echo "<tr>";
                        echo "<td>$fecha</td>";
                        echo "<td><span class='status'>{$m['estado_nuevo']}</span></td>";
                        echo "<td>{$m['comentario']}";
                        // MOSTRAR ENLACE SI HAY FOTO
                        if (!empty($m['url_evidencia'])) {
                            echo "<br><a href='{$m['url_evidencia']}' target='_blank' style='color:#007bff; font-weight:bold; text-decoration:none;'>
                                    <i class='fas fa-image'></i> Ver Evidencia
                                  </a>";
                        }
                        echo "</td>";
                        echo "<td>{$m['usuario_responsable']}</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='4' align='center'>Sin movimientos.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    const REPARACION_ID = <?= $id ?>;
    const TICKET_URL = "<?= $ticketUrl ?>";
    const CODIGO_BARRAS = "<?= $reparacion['codigo_barras'] ?>";
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="/local3M/js/editar_reparacion.js?v=<?php echo time(); ?>"></script>
<?php include 'templates/footer.php'; ?>