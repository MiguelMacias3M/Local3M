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
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

<div class="container glass-container">
    <div class="page-title" style="margin-bottom: 25px;">
        <h1 style="margin: 0; font-weight: 800; font-size: 26px;"><i class="fas fa-tools" style="color:#ff2d55;"></i> Editar Reparación #<?= $id ?></h1>
    </div>

    <div class="edit-grid">
        <div class="edit-form-col">
            <form id="formEditar" onsubmit="return false;">
                <input type="hidden" name="id" id="id_reparacion_hidden" value="<?= $id ?>">
                
                <div class="glass-card">
                    <h3><i class="fas fa-user"></i> Datos del Cliente</h3>
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" class="glass-input" id="nombre_cliente" value="<?= htmlspecialchars($reparacion['nombre_cliente']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="tel" class="glass-input" id="telefono" value="<?= htmlspecialchars($reparacion['telefono']) ?>" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                </div>

                <div class="glass-card">
                    <h3><i class="fas fa-mobile-alt"></i> Equipo</h3>
                    <div class="form-group">
                        <label>Reparación Solicitada</label>
                        <input type="text" class="glass-input" id="tipo_reparacion" value="<?= htmlspecialchars($reparacion['tipo_reparacion']) ?>">
                    </div>
                    <div class="row-2-col">
                        <div class="form-group">
                            <label>Marca</label>
                            <input type="text" class="glass-input" id="marca_celular" value="<?= htmlspecialchars($reparacion['marca_celular']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Modelo</label>
                            <input type="text" class="glass-input" id="modelo" value="<?= htmlspecialchars($reparacion['modelo']) ?>">
                        </div>
                    </div>
                </div>

                <div class="glass-card">
                    <h3><i class="fas fa-dollar-sign"></i> Balance Financiero</h3>
                    <div class="row-3-col">
                        <div class="form-group">
                            <label>Costo Total</label>
                            <input type="number" class="glass-input" id="monto" value="<?= (int)$reparacion['monto'] ?>" oninput="calcularDeuda()">
                        </div>
                        <div class="form-group">
                            <label>Adelanto</label>
                            <input type="number" class="glass-input" id="adelanto" value="<?= (int)$reparacion['adelanto'] ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Deuda</label>
                            <input type="number" class="glass-input" id="deuda" value="<?= (int)$reparacion['deuda'] ?>" readonly style="color: #ff3b30; font-weight:bold;">
                        </div>
                    </div>
                    <div class="abono-box">
                        <div style="flex:1;">
                            <label style="color:#007aff;">Registrar Abono</label>
                            <input type="number" class="glass-input" id="nuevo_abono_monto" placeholder="Monto abono...">
                        </div>
                        <button type="button" class="glass-btn info" onclick="agregarAbono()"><i class="fas fa-plus"></i> Agregar</button>
                    </div>
                </div>

                <div class="glass-card">
                    <h3><i class="fas fa-info-circle"></i> Estado y Ubicación</h3>
                    
                    <div class="form-group">
                        <label>Información Extra / Notas</label>
                        <input type="text" class="glass-input" name="info_extra" value="<?= htmlspecialchars($reparacion['info_extra']) ?>">
                    </div>

                    <div class="form-group">
                        <label>Estado Actual</label>
                        <select class="glass-input" name="estado" id="selectEstado">
                            <?php
                            $estados = ['En espera', 'En revision', 'Diagnosticado', 'En preparacion', 'En progreso', 'Reparado', 'Entregado', 'Cancelado', 'No se pudo reparar'];
                            foreach ($estados as $est) {
                                $sel = ($reparacion['estado'] == $est) ? 'selected' : '';
                                echo "<option value='$est' $sel>$est</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="row-2-col">
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt" style="color:#ff2d55;"></i> Ubicación Física</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" class="glass-input" id="ubicacion" 
                                       value="<?= htmlspecialchars($reparacion['ubicacion'] ?? '') ?>" 
                                       placeholder="Ej: A1" readonly onclick="abrirMapaCaja()">
                                <button type="button" class="glass-btn primary" onclick="abrirMapaCaja()" style="padding: 0 15px;">
                                    <i class="fas fa-th"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="far fa-clock" style="color:#ff9500;"></i> Entrega Estimada</label>
                            <?php 
                                $fechaVal = '';
                                if (!empty($reparacion['fecha_estimada'])) {
                                    $fechaVal = date('Y-m-d\TH:i', strtotime($reparacion['fecha_estimada']));
                                }
                            ?>
                            <input type="datetime-local" class="glass-input" id="fecha_estimada" value="<?= $fechaVal ?>">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 20px; border-top: 1px dashed rgba(0,0,0,0.1); padding-top: 15px;">
                        <label style="color: #007aff; cursor:pointer;">
                            <i class="fas fa-camera"></i> <strong>Adjuntar Evidencia Fotográfica</strong>
                        </label>
                        <input type="file" id="evidencia_input" accept="image/*" class="glass-input" onchange="previsualizarFoto()" style="background:transparent; border:none; padding:0;">
                        <div id="preview-container" style="display:none; margin-top:10px; text-align:center; background:white; padding:10px; border-radius:12px; border:1px solid #eee;">
                            <img id="img-preview" src="" style="max-height: 150px; border-radius: 8px;">
                        </div>
                    </div>
                </div>

                <div class="glass-action-bar">
                    <button type="button" class="glass-btn primary" onclick="guardarCambios()"><i class="fas fa-save"></i> Guardar</button>
                    <button type="button" class="glass-btn success" onclick="entregarReparacion()"><i class="fas fa-check-double"></i> Entregar</button>
                    <button type="button" class="glass-btn warning" onclick="imprimirTicket()"><i class="fas fa-print"></i> Ticket</button>
                    <a href="control.php" class="glass-btn secondary" style="text-decoration:none;"><i class="fas fa-arrow-left"></i> Volver</a>
                </div>
            </form>
        </div>

        <div class="edit-sidebar">
            <div class="barcode-card">
                <h4 style="margin-top:0; color:#1d1d1f; font-weight:800;"><i class="fas fa-barcode"></i> Código de Barras</h4>
                <div class="barcode-display">
                    <svg id="barcode-svg"></svg>
                </div>
            </div>
        </div>
    </div>

    <div class="glass-card" style="margin-top: 3rem;">
        <h3><i class="fas fa-history"></i> Historial de Movimientos</h3>
        <div class="glass-table-wrapper">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Comentario</th>
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
                            // Inyección de Data-Label para Móviles
                            echo "<td data-label='Fecha'>$fecha</td>";
                            echo "<td data-label='Estado'><span class='status status-pending' style='font-size:12px;'>{$m['estado_nuevo']}</span></td>";
                            echo "<td data-label='Comentario'>{$m['comentario']}";
                            if (!empty($m['url_evidencia'])) {
                                echo "<br><br><a href='{$m['url_evidencia']}' target='_blank' style='color:#007aff; font-weight:600; text-decoration:none; background:rgba(0,122,255,0.1); padding:4px 8px; border-radius:6px;'>
                                        <i class='fas fa-image'></i> Ver Foto
                                      </a>";
                            }
                            echo "</td>";
                            echo "<td data-label='Usuario'><i class='far fa-user-circle'></i> {$m['usuario_responsable']}</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' align='center' style='padding:30px; color:#86868b;'>Aún no hay movimientos registrados en el historial.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMapaCaja" tabindex="-1" aria-hidden="true" style="display:none; background: rgba(0,0,0,0.5); backdrop-filter:blur(5px); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999;">
    <div class="modal-dialog modal-lg" style="margin: 5% auto; max-width: 800px;">
        <div class="modal-content" style="background: rgba(255,255,255,0.9); backdrop-filter:blur(20px); border-radius: 20px; box-shadow: 0 25px 50px rgba(0,0,0,0.2); border:1px solid white;">
            <div class="modal-header" style="padding: 20px; border-bottom: 1px solid rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center;">
                <h5 class="modal-title" style="margin: 0; font-weight:800; font-size:20px; color:#1d1d1f;"><i class="fas fa-box-open" style="color:#ff9500;"></i> Selecciona un Lugar (48 Espacios)</h5>
                <button type="button" onclick="cerrarModalMapa()" style="background: rgba(255,59,48,0.1); border: none; color: #ff3b30; width:35px; height:35px; border-radius:50%; font-size: 1.2rem; cursor: pointer; display:flex; align-items:center; justify-content:center;"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body text-center" style="padding: 20px;">
                
                <div style="display:flex; justify-content:center; gap:10px; margin-bottom: 20px; flex-wrap:wrap;">
                    <span style="background: rgba(52, 199, 89, 0.2); color: #155724; border:1px solid #34c759; padding: 6px 12px; border-radius: 8px; font-size:13px; font-weight:600;">Disponible</span>
                    <span style="background: rgba(255, 59, 48, 0.2); color: #ff3b30; border:1px solid #ff3b30; padding: 6px 12px; border-radius: 8px; font-size:13px; font-weight:600;">Ocupado</span>
                    <span style="background: #007aff; color: white; padding: 6px 12px; border-radius: 8px; font-size:13px; font-weight:600; border:1px solid #0056b3;">Seleccionado</span>
                </div>

                <div id="grid-caja" class="grid-container"></div>

            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Obtenemos el código de la base de datos de forma dinámica
        let codigoGuardado = "<?= htmlspecialchars($reparacion['codigo_barras'] ?? $reparacion['codigo'] ?? $reparacion['folio'] ?? $reparacion['id']) ?>";
        let contenedorDibujo = document.getElementById('barcode-svg');

        if(contenedorDibujo && codigoGuardado.trim() !== "") {
            // Forzamos un micro-retraso para que la caja blanca se expanda antes de dibujar
            setTimeout(() => {
                try {
                    JsBarcode("#barcode-svg", String(codigoGuardado), {
                        format: "CODE128",
                        width: 1.2,            // Barras finas para que no se desborde
                        height: 65,            // Altura equilibrada
                        displayValue: true,    // 🚨 Activa los números
                        text: String(codigoGuardado), // 🚨 Fuerza la inyección de letras y números
                        fontSize: 28,          // Tamaño legible
                        fontOptions: "bold",
                        textPosition: "bottom",
                        font: "Poppins",
                        margin: 3
                    });
                } catch(e) {
                    console.error("Error pintando código en vista de edición: ", e);
                }
            }, 100);
        }
    });
</script>
<script>
    const REPARACION_ID = <?= $id ?>;
    const TICKET_URL = "<?= $ticketUrl ?>";
    const CODIGO_BARRAS = "<?= $reparacion['codigo_barras'] ?>";
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> 
<script src="/local3M/js/editar_reparacion.js?v=<?php echo time(); ?>"></script>
<?php include 'templates/footer.php'; ?>