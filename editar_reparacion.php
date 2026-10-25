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

$sql_h = "SELECT * FROM historial_reparaciones WHERE id_reparacion = :id ORDER BY fecha_cambio DESC";
$stmt_h = $conn->prepare($sql_h);
$stmt_h->execute([':id' => $id]);
$movs = $stmt_h->fetchAll(PDO::FETCH_ASSOC);

$fotos_evidencia = array_filter($movs, function($m) { return !empty($m['url_evidencia']); });
$ticketUrl = "generar_ticket_id.php?id_transaccion=" . urlencode($reparacion['id_transaccion']);
?>

<link rel="stylesheet" href="css/editar_reparacion.css?v=<?php echo time(); ?>">
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

<div class="container glass-container">
    <div class="page-title" style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h1 style="margin: 0; font-weight: 800; font-size: 26px;">
            <i class="fas fa-tools" style="color:#ff2d55;"></i> Editar Reparación #<?= $id ?>
        </h1>
        
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if (!empty($reparacion['tipo_pass']) && $reparacion['tipo_pass'] !== 'Ninguno'): ?>
                <?php if ($reparacion['tipo_pass'] === 'Patron'): ?>
                    <button type="button" onclick="mostrarAnimacionPatron('<?= htmlspecialchars($reparacion['codigo_pass']) ?>')" style="background: rgba(88, 86, 214, 0.1); color: #5856d6; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 13px; border: 1px solid rgba(88, 86, 214, 0.3); cursor: pointer; transition: 0.2s;">
                        <i class="fas fa-play-circle"></i> Ver Patrón Animado
                    </button>
                <?php else: ?>
                    <span style="background: rgba(255, 149, 0, 0.1); color: #ff9500; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 13px; border: 1px solid rgba(255, 149, 0, 0.3);">
                        <i class="fas fa-lock"></i> <?= htmlspecialchars($reparacion['tipo_pass']) ?>: <?= htmlspecialchars($reparacion['codigo_pass']) ?>
                    </span>
                <?php endif; ?>
            <?php endif; ?>

            <span style="background: rgba(0,122,255,0.1); color: #007aff; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 13px;">
                <i class="fas fa-camera"></i> <?= count($fotos_evidencia) ?> Evidencia(s)
            </span>
        </div>
    </div>

    <!-- PESTAÑAS (TABS) -->
    <div class="glass-tabs-container">
        <button type="button" class="glass-tab-btn active" onclick="abrirTab('tab-general', this)"><i class="fas fa-info-circle"></i> 1. Información General</button>
        <button type="button" class="glass-tab-btn" onclick="abrirTab('tab-taller', this)"><i class="fas fa-wrench"></i> 2. Taller y Diagnóstico</button>
        <button type="button" class="glass-tab-btn" onclick="abrirTab('tab-finanzas', this)"><i class="fas fa-dollar-sign"></i> 3. Finanzas e Insumos</button>
    </div>

    <div class="edit-grid">
        <div class="edit-form-col">
            <form id="formEditar" onsubmit="return false;">
                
                <!-- PESTAÑA 1: INFORMACIÓN GENERAL -->
                <div id="tab-general" class="tab-content active">
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
                        <h3><i class="fas fa-lock" style="color:#5856d6;"></i> Seguridad del Equipo</h3>
                        <div class="row-2-col" style="align-items: start;">
                            <div class="form-group">
                                <label>Método de Desbloqueo</label>
                                <select class="glass-input" id="tipo_pass" onchange="cambiarTipoPass()" style="font-weight: 700; color: #5856d6;">
                                    <option value="Ninguno">Ninguno / Sin Contraseña</option>
                                    <option value="PIN">PIN Numérico</option>
                                    <option value="Texto">Contraseña (Texto/Alfanumérica)</option>
                                    <option value="Patron">Patrón de Dibujo</option>
                                </select>
                            </div>
                            
                            <div id="caja_pass_texto" class="form-group" style="display:none;">
                                <label id="lbl_pass_texto">Código / Contraseña</label>
                                <input type="text" class="glass-input" id="codigo_pass_txt" placeholder="Ingresa la contraseña..." autocomplete="off">
                            </div>
                        </div>

                        <div id="caja_pass_patron" style="display:none; text-align: center;">
                            <label style="color: #86868b; font-weight: 600; font-size: 13px;">Dibuja el patrón deslizando el dedo o ratón</label>
                            <div class="patron-grid" id="patronGrid">
                                <div class="patron-dot" data-val="1"></div><div class="patron-dot" data-val="2"></div><div class="patron-dot" data-val="3"></div>
                                <div class="patron-dot" data-val="4"></div><div class="patron-dot" data-val="5"></div><div class="patron-dot" data-val="6"></div>
                                <div class="patron-dot" data-val="7"></div><div class="patron-dot" data-val="8"></div><div class="patron-dot" data-val="9"></div>
                            </div>
                            <button type="button" class="glass-btn secondary" style="height: 35px; padding: 0 15px; font-size: 12px; margin-top: 5px;" onclick="limpiarPatron()">
                                <i class="fas fa-eraser"></i> Borrar Dibujo
                            </button>
                            <input type="hidden" id="codigo_pass_patron">
                        </div>
                    </div>
                </div>

                <!-- PESTAÑA 2: TALLER Y DIAGNÓSTICO -->
                <div id="tab-taller" class="tab-content">
                    <div class="glass-card" style="position: relative; z-index: 99;">
                        <h3><i class="fas fa-info-circle"></i> Control del Taller</h3>
                        
                        <div class="form-group">
                            <label>Notas de Recepción / Falla reportada</label>
                            <input type="text" class="glass-input" name="info_extra" value="<?= htmlspecialchars($reparacion['info_extra']) ?>">
                        </div>

                        <div class="form-group">
                            <label>Estado Actual</label>
                            <select class="glass-input" name="estado" id="selectEstado" style="display: none;">
                                <?php
                                $estadosNuevos = [
                                    'Pendiente' => '⏳ Pendiente (Recepción)',
                                    'Evaluacion Tecnica' => '🪛 Evaluación Técnica / Apertura',
                                    'En espera de confimacion' => '🙋 En espera de confimación',
                                    'Espera de Refaccion' => '📦 Esperando Refacción',
                                    'En Reparacion' => '🛠️ Ejecutando Reparación',
                                    'Terminado' => '✅ Terminado (Listo en Repisa)',
                                    'Entregado' => '🤝 Entregado al Cliente',
                                    'Cancelado' => '🛑 Orden Cancelada',
                                    'Irreparable' => '⚠️ Irreparable / Devuelto'
                                ];
                                $estadoActualBD = $reparacion['estado'];
                                if (!array_key_exists($estadoActualBD, $estadosNuevos) && !empty($estadoActualBD)) {
                                    $estadosNuevos[$estadoActualBD] = '🔄 ' . $estadoActualBD . ' (Estado Anterior)';
                                }
                                foreach ($estadosNuevos as $valor => $etiqueta) {
                                    $sel = ($estadoActualBD === $valor) ? 'selected' : '';
                                    echo "<option value=\"$valor\" $sel>$etiqueta</option>";
                                }
                                ?>
                            </select>
                            <div class="custom-glass-dropdown" id="glassDropdown">
                                <div class="glass-dropdown-selected" onclick="toggleGlassDropdown()">
                                    <span id="glassDropdownText">Cargando...</span>
                                    <i class="fas fa-chevron-down arrow-icon"></i>
                                </div>
                                <div class="glass-dropdown-options" id="glassDropdownOptions"></div>
                            </div>
                        </div>

                        <div class="row-2-col">
                            <div class="form-group" style="margin-bottom: 0;">
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
                            <div class="form-group" style="margin-bottom: 0;">
                                <label><i class="far fa-clock" style="color:#ff9500;"></i> Entrega Estimada</label>
                                <input type="datetime-local" class="glass-input" id="fecha_estimada" value="<?= !empty($reparacion['fecha_estimada']) ? date('Y-m-d\TH:i', strtotime($reparacion['fecha_estimada'])) : '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="glass-card">
                        <h3><i class="fas fa-history"></i> Historial de Movimientos</h3>
                        <div class="glass-table-wrapper">
                            <table class="glass-table">
                                <thead>
                                    <tr><th>Fecha</th><th>Estado</th><th>Comentario / Evidencia</th><th>Usuario</th></tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($movs) {
                                        foreach ($movs as $m) {
                                            $fecha = date("d/m/y h:i A", strtotime($m['fecha_cambio']));
                                            $com_clean = htmlspecialchars(ltrim($m['comentario'] ?? '', '? '), ENT_QUOTES);
                                            $usu_clean = htmlspecialchars($m['usuario_responsable'] ?? '', ENT_QUOTES);
                                            echo "<tr><td data-label='Fecha' style='white-space:nowrap;'>$fecha</td>";
                                            echo "<td data-label='Estado'><span style='font-size:12px; font-weight:bold;'>{$m['estado_nuevo']}</span></td>";
                                            echo "<td data-label='Comentario'>$com_clean";
                                            if (!empty($m['url_evidencia'])) {
                                                $url_ev = htmlspecialchars($m['url_evidencia'], ENT_QUOTES);
                                                echo "<div style='margin-top:8px;'><button type='button' class='btn-ver-evidencia-tabla' onclick=\"abrirLightbox('$url_ev', '$com_clean', '$fecha', '$usu_clean', {$m['id']})\"><img src='$url_ev' style='width:28px; height:28px; object-fit:cover; border-radius:5px;'><span><i class='fas fa-search-plus'></i> Ver Evidencia</span></button></div>";
                                            }
                                            echo "</td><td data-label='Usuario'><i class='far fa-user-circle'></i> {$m['usuario_responsable']}</td></tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='4' align='center' style='padding:30px; color:#86868b;'>Aún no hay movimientos.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- PESTAÑA 3: FINANZAS E INSUMOS -->
                <div id="tab-finanzas" class="tab-content">
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
                                <input type="number" class="glass-input no-autosave" id="nuevo_abono_monto" placeholder="Monto abono...">                            </div>
                            <button type="button" class="glass-btn info" onclick="agregarAbono()"><i class="fas fa-plus"></i> Agregar</button>
                        </div>
                    </div>

                    <div class="glass-card" style="background: rgba(52, 199, 89, 0.04); border: 1px solid rgba(52, 199, 89, 0.2);">
                        <h3><i class="fas fa-microchip" style="color:#34c759;"></i> Refacciones Utilizadas</h3>
                        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                            <input type="text" id="buscador_piezas" class="glass-input" placeholder="Buscar refacción en inventario..." onkeyup="buscarPiezaReparacion()" autocomplete="off">
                        </div>
                        <div id="resultados_piezas" style="background: white; border-radius: 10px; max-height: 150px; overflow-y: auto; margin-bottom: 15px; display: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1);"></div>

                        <div style="background: white; border-radius: 12px; padding: 10px;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                <thead>
                                    <tr style="border-bottom: 1px solid #eee; color: #86868b; text-align: left;">
                                        <th style="padding: 8px;">Pieza Extraída</th>
                                        <?php if(isset($esAdmin) && $esAdmin): ?>
                                        <th style="padding: 8px; text-align: right;">Costo (Mayoreo)</th>
                                        <?php endif; ?>
                                        <th style="padding: 8px; text-align: right; width: 50px;"></th> 
                                    </tr>
                                </thead>
                                <tbody id="tabla_piezas_usadas"></tbody>
                            </table>
                        </div>
                        <?php if(isset($esAdmin) && $esAdmin): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center;">
                            <div style="color: #86868b; font-size: 13px;">Costo total piezas: <b id="lbl_costo_piezas" style="color: #ff3b30;">$0.00</b></div>
                            <div style="font-size: 16px; font-weight: 800; color: #1d1d1f;">Ganancia Neta: <span id="lbl_ganancia_neta" style="color: #34c759;">$0.00</span></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- COLUMNA LATERAL: CÓDIGO DE BARRAS + BITÁCORA -->
        <div class="edit-sidebar">
            <div class="barcode-card" id="card-barcode" style="margin-bottom: 25px;">º
                <h4 style="margin-top:0; color:#1d1d1f; font-weight:800;"><i class="fas fa-barcode"></i> Código de Barras</h4>
                <div class="barcode-display"><svg id="barcode-svg"></svg></div>
                <button type="button" class="glass-btn info" style="width: 100%; margin-top: 15px;" onclick="imprimirEtiquetaReparacion()">
                    <i class="fas fa-tags"></i> Imprimir Etiqueta
                </button>
            </div>

            <div class="glass-card" style="padding: 20px;">
                <h3 style="font-size: 1.05rem; margin-bottom: 15px;">
                    <i class="fas fa-camera-retro" style="color:#ff2d55;"></i> Bitácora y Evidencias
                </h3>
                <div class="bitacora-fast-box">
                    <textarea id="nota_bitacora" class="glass-input" rows="2" placeholder="Escribe un diagnóstico, contraseña o nota para el otro técnico..." style="padding: 10px 12px; font-size: 13px; resize: vertical; margin-bottom: 10px; background: white;"></textarea>
                    
                    <div style="display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap;">
                        <label for="bitacora_fotos_input" class="bitacora-upload-btn" title="Subir de galería">
                            <i class="fas fa-folder-open"></i> Galería
                        </label>
                        <label for="bitacora_camara_input" class="bitacora-upload-btn camara" title="Abrir cámara">
                            <i class="fas fa-camera"></i> Cámara
                        </label>
                        <button type="button" class="glass-btn primary" onclick="guardarBitacoraRapida()" style="flex: 1; height: 38px; font-size: 13px; border-radius: 10px; padding: 0 12px;">
                            <i class="fas fa-paper-plane"></i> Subir
                        </button>
                    </div>

                    <input type="file" id="bitacora_fotos_input" accept="image/*" multiple onchange="previsualizarBitacora(this)" style="display:none;">
                    <input type="file" id="bitacora_camara_input" accept="image/*" capture="environment" onchange="previsualizarBitacora(this)" style="display:none;">
                    <div id="bitacora-preview-box" class="preview-grid-container" style="display:none; margin-top: 8px;"></div>
                </div>

                <div style="margin-top: 18px;">
                    <div style="font-size: 12px; font-weight: 700; color: #86868b; text-transform: uppercase; margin-bottom: 10px; display: flex; justify-content: space-between;">
                        <span>Álbum Fotográfico</span><span><?= count($fotos_evidencia) ?> foto(s)</span>
                    </div>

                    <?php if (count($fotos_evidencia) > 0): ?>
                        <div class="evidence-gallery-grid">
                            <?php foreach ($fotos_evidencia as $f): 
                                $fechaF = date("d/m/y h:i A", strtotime($f['fecha_cambio']));
                                $comF = htmlspecialchars(ltrim($f['comentario'] ?? '', '? '), ENT_QUOTES);
                                $usuF = htmlspecialchars($f['usuario_responsable'] ?? 'Técnico', ENT_QUOTES);
                                $urlF = htmlspecialchars($f['url_evidencia'], ENT_QUOTES);
                            ?>
                                <div class="evidence-thumb-card" onclick="abrirLightbox('<?= $urlF ?>', '<?= $comF ?>', '<?= $fechaF ?>', '<?= $usuF ?>', <?= (int)$f['id'] ?>)">
                                    <img src="<?= $urlF ?>" alt="Evidencia" loading="lazy">
                                    <div class="evidence-thumb-overlay">
                                        <span><i class="fas fa-user"></i> <?= $usuF ?></span>
                                        <small><?= date("d/m", strtotime($f['fecha_cambio'])) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 25px 10px; background: rgba(0,0,0,0.02); border-radius: 14px; color: #86868b; font-size: 13px;">
                            <i class="far fa-images" style="font-size: 26px; display: block; margin-bottom: 8px; opacity: 0.5;"></i>
                            Sin fotos adjuntas todavía.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- DOCK FLOTANTE DE ACCIONES (ATAJOS) -->
    <div class="floating-left-bar">
        <button type="button" class="floating-btn success" onclick="entregarReparacion()" title="Mandar a Caja (Entregar)">
            <i class="fas fa-handshake"></i>
        </button>
        <button type="button" class="floating-btn primary" onclick="guardarCambiosManual()" title="Forzar Guardado">
            <i class="fas fa-save"></i>
        </button>
        <button type="button" class="floating-btn warning" onclick="imprimirTicket()" title="Imprimir Ticket">
            <i class="fas fa-print"></i>
        </button>
        <a href="control.php" class="floating-btn secondary" title="Volver al Taller">
            <i class="fas fa-arrow-left"></i>
        </a>
    </div>
</div>

<!-- MODAL LIGHTBOX LIQUID GLASS PARA VER FOTOS CON ZOOM -->
<div id="modalLightbox" class="glass-modal-overlay" style="display:none; z-index: 99999;" onclick="cerrarLightbox(event)">
    <div class="lightbox-glass-box" onclick="event.stopPropagation()">
        <div class="lightbox-header">
            <div>
                <h4 id="lb-comentario" style="margin:0; font-size:15px; font-weight:800; color:#1d1d1f;">Evidencia Fotográfica</h4>
                <small id="lb-meta" style="color:#86868b; font-size:12px;"></small>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" class="lightbox-icon-btn" onclick="alternarZoomLightbox()" title="Acercar / Alejar Zoom"><i class="fas fa-search-plus" id="lb-zoom-icon"></i></button>
                <a id="lb-download" href="#" download class="lightbox-icon-btn" title="Descargar imagen" style="text-decoration:none;"><i class="fas fa-download"></i></a>
                <button type="button" class="lightbox-icon-btn danger" id="lb-delete-btn" title="Eliminar foto"><i class="fas fa-trash-alt"></i></button>
                <button type="button" class="lightbox-icon-btn close" onclick="cerrarLightbox()"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="lightbox-img-Viewport" id="lb-viewport">
            <img id="lb-img" src="" alt="Vista previa" onclick="alternarZoomLightbox()">
        </div>
    </div>
</div>

<!-- MODAL: REPRODUCTOR DE PATRÓN ANIMADO LIQUID GLASS -->
<div id="modalAnimacionPatron" class="glass-modal-overlay" style="display:none; z-index: 99999;" onclick="cerrarAnimacionPatron(event)">
    <div class="glass-modal-content" style="max-width: 400px; padding: 30px; text-align: center; position: relative;" onclick="event.stopPropagation()">
        <h3 style="margin-top:0; color:#1d1d1f; font-weight:800;">
            <i class="fas fa-project-diagram" style="color:#5856d6;"></i> Patrón de Desbloqueo
        </h3>
        <p style="color:#86868b; font-size:13px; margin-bottom: 25px;">Secuencia original: <b id="secuenciaText" style="color:#5856d6;"></b></p>
        
        <div style="position: relative; width: 280px; height: 280px; margin: 0 auto; background: rgba(0,0,0,0.02); border-radius: 24px; border: 1px solid rgba(0,0,0,0.05); padding: 25px; box-sizing: border-box;">
            <svg id="svgLineasPatron" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 1;"></svg>
            <div class="patron-grid" style="margin: 0; padding: 0; border: none; background: transparent; position: relative; z-index: 2; height: 100%; display: grid; grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(3, 1fr); gap: 15px; width: 100%;">
                <?php for($i=1; $i<=9; $i++): ?>
                    <div class="patron-dot anim-dot" id="anim-dot-<?= $i ?>" data-val="<?= $i ?>" style="width: 100%; height: 100%; aspect-ratio: 1/1; margin: auto;"></div>
                <?php endfor; ?>
            </div>
        </div>

        <div style="margin-top: 30px; display: flex; gap: 10px; justify-content: center;">
            <button type="button" class="glass-btn info" style="flex:1;" onclick="reiniciarAnimacionPatron()"><i class="fas fa-redo"></i> Repetir</button>
            <button type="button" class="glass-btn secondary" style="flex:1;" onclick="cerrarAnimacionPatron()"><i class="fas fa-times"></i> Cerrar</button>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMapaCaja" tabindex="-1" aria-hidden="true" style="display:none; background: rgba(0,0,0,0.5); backdrop-filter:blur(5px); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999;">
    <div class="modal-dialog modal-lg" style="margin: 5% auto; max-width: 800px;">
        <div class="modal-content" style="background: rgba(255,255,255,0.9); backdrop-filter:blur(20px); border-radius: 20px; box-shadow: 0 25px 50px rgba(0,0,0,0.2); border:1px solid white;">
            <div class="modal-header" style="padding: 20px; border-bottom: 1px solid rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center;">
                <h5 class="modal-title" style="margin: 0; font-weight:800; font-size:20px; color:#1d1d1f;"><i class="fas fa-box-open" style="color:#ff9500;"></i> Selecciona un Lugar</h5>
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

<div id="modalChecklist" class="glass-modal-overlay" style="display:none;">
    <div class="glass-modal-content" style="max-width: 450px; padding: 30px;">
        <h3 style="margin-top:0; color:#1d1d1f; font-weight:800; text-align:center;">
            <i class="fas fa-clipboard-check" style="color:#34c759; font-size: 28px; margin-bottom:10px; display:block;"></i> 
            Checklist de Calidad
        </h3>
        <p style="text-align:center; color:#86868b; font-size:14px; margin-bottom:25px;">Confirma que los siguientes componentes funcionan correctamente antes de finalizar.</p>
        <div class="checklist-container" style="display:flex; flex-direction:column; gap:12px; margin-bottom:25px;">
            <label class="check-item"><input type="checkbox" class="checklist-check"> <span>Carga / Centro de carga</span></label>
            <label class="check-item"><input type="checkbox" class="checklist-check"> <span>Señal / Wi-Fi / Bluetooth</span></label>
            <label class="check-item"><input type="checkbox" class="checklist-check"> <span>Touch y Display (Imagen)</span></label>
            <label class="check-item"><input type="checkbox" class="checklist-check"> <span>Cámaras (Frontal y Trasera)</span></label>
            <label class="check-item"><input type="checkbox" class="checklist-check"> <span>Audio (Altavoz y Micrófono)</span></label>
            <label class="check-item"><input type="checkbox" class="checklist-check"> <span>Botones (Encendido y Volumen)</span></label>
        </div>
        <div style="display:flex; gap:10px;">
            <button type="button" class="glass-btn secondary" style="flex:1; justify-content:center;" onclick="cancelarChecklist()">Cancelar</button>
            <button type="button" class="glass-btn success" style="flex:1; justify-content:center;" onclick="confirmarChecklist()"><i class="fas fa-check"></i> Todo en orden</button>
        </div>
    </div>
</div>

<script>
    const REPARACION_ID = <?= $id ?>;
    const TICKET_URL = "<?= $ticketUrl ?>";
    const CODIGO_BARRAS = "<?= $reparacion['codigo_barras'] ?? '' ?>";
    const ES_ADMIN = <?= (isset($esAdmin) && $esAdmin) ? 'true' : 'false' ?>;
    const TIPO_PASS_DB = "<?= $reparacion['tipo_pass'] ?? 'Ninguno' ?>";
    const CODIGO_PASS_DB = "<?= $reparacion['codigo_pass'] ?? '' ?>";
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> 
<script src="js/editar_reparacion.js?v=<?php echo time(); ?>"></script>
<?php include 'templates/footer.php'; ?>