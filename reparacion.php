<?php
// 1. Incluimos el header
include 'templates/header.php';
include 'config/conexion.php';

// 2. Generar id_transaccion único
$id_transaccion = uniqid('trans_');

// 3. Usuario sesión
$usuario_sesion = $_SESSION['nombre'] ?? 'Sistema';
?>

<link rel="stylesheet" href="/local3M/css/reparacion.css?v=<?php echo time(); ?>">

<div class="container glass-container">
    
    <div class="page-title" style="margin-bottom: 25px;">
        <h1 style="margin: 0; font-weight: 800; font-size: 26px;"><i class="fas fa-tools" style="color:#007aff;"></i> Nueva Orden de Reparación</h1>
        <p style="color: #86868b; margin: 5px 0 0 0; font-size: 14px;">Registra los datos del cliente y añade los equipos al carrito de servicio.</p>
    </div>

    <div class="reparacion-grid">
        <div>
            <form id="reparacionForm" onsubmit="return false;">
                
                <div class="glass-card" style="padding: 25px; margin-bottom: 20px;">
                    <h2 class="section-title"><i class="fas fa-user-circle"></i> Datos del Cliente</h2>
                    
                    <div style="margin-bottom: 15px;">
                        <label class="glass-label">Nombre del Cliente <span class="text-danger">*</span></label>
                        <input id="nombre_cliente" class="glass-input" type="text" placeholder="Nombre completo" required>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label class="glass-label">Teléfono (10 dígitos) <span class="text-danger">*</span></label>
                        <input id="telefono" class="glass-input" type="tel" maxlength="10" placeholder="Ej: 4491234567" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                    </div>
                    
                    <div>
                        <label class="glass-label">Información Extra (Contraseña, Patrón, Detalles visuales)</label>
                        <textarea id="info_extra" class="glass-input" rows="2" placeholder="Ej: Equipo con pantalla estrellada, PIN 1234"></textarea>
                    </div>
                </div>

                <div class="glass-card" style="padding: 25px;">
                    <h2 class="section-title"><i class="fas fa-mobile-alt"></i> Datos del Equipo</h2>

                    <div style="margin-bottom: 15px;">
                        <label class="glass-label">Falla o Servicio a realizar <span class="text-danger">*</span></label>
                        <input id="tipo_reparacion" class="glass-input" type="text" placeholder="Ej: Cambio de pantalla, batería," required>
                    </div>

                    <div class="row-2-col" style="margin-bottom: 15px;">
                        <div>
                            <label class="glass-label">Marca <span class="text-danger">*</span></label>
                            <input id="marca_celular" class="glass-input" type="text" placeholder="Ej: Apple, Samsung" required>
                        </div>
                        <div>
                            <label class="glass-label">Modelo <span class="text-danger">*</span></label>
                            <input id="modelo" class="glass-input" type="text" placeholder="Ej: iPhone 13 Pro" required>
                        </div>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label class="glass-label"><i class="far fa-clock"></i> Promesa de Entrega (Opcional)</label>
                        <input type="datetime-local" class="glass-input" id="fecha_estimada">
                        <small style="color:#86868b; display:block; margin-top:5px; font-size:12px;">Si se deja vacío, el estado será "Pendiente".</small>
                    </div>

                    <div class="row-3-col" style="margin-bottom: 20px; align-items: end;">
                        <div>
                            <label class="glass-label">Costo Total ($) <span class="text-danger">*</span></label>
                            <input id="monto" class="glass-input" type="number" value="0" min="0" oninput="calcularDeuda()" style="font-size: 16px; font-weight: bold; color: #1d1d1f;" required>
                        </div>
                        <div>
                            <label class="glass-label" style="color: #34c759;">Adelanto / Abono ($) <span class="text-danger">*</span></label>
                            <input id="adelanto" class="glass-input" type="number" value="0" min="0" oninput="calcularDeuda()" style="font-size: 16px; font-weight: bold; color: #34c759; border-color: rgba(52,199,89,0.3);" required>
                        </div>
                        <div>
                            <label class="glass-label" style="color: #ff3b30;">Resta ($)</label>
                            <input id="deuda" class="glass-input bg-readonly" type="number" value="0" readonly style="font-size: 16px; font-weight: bold; color: #ff3b30;">
                        </div>
                    </div>
                    
                    <button type="button" class="glass-btn success" style="width: 100%; height: 48px; font-size: 15px;" onclick="agregarAlCarrito()">
                        <i class="fas fa-cart-plus"></i> Añadir Equipo a la Orden
                    </button>
                </div>
            </form>
        </div>

        <div>
            <div class="glass-card" style="padding: 25px; position: sticky; top: 20px;">
                <h2 class="section-title"><i class="fas fa-shopping-cart"></i> Equipos en la Orden</h2>
                
                <ul id="lista-carrito" class="glass-cart-list">
                    <li class="empty-cart"><i class="fas fa-box-open" style="font-size: 30px; display: block; margin-bottom: 10px; color: #c7c7cc;"></i>Aún no hay equipos agregados.</li>
                </ul>

                <div class="glass-totals">
                    <span class="total-label">Saldo Pendiente de la Orden:</span>
                    <span id="total-deuda" class="total-amount">$0.00</span>
                </div>
                
                <button id="btn-registrar" type="button" class="glass-btn primary" style="width: 100%; height: 55px; font-size: 16px; margin-top: 15px;" onclick="enviarCarrito()" disabled>
                    <i class="fas fa-check-circle"></i> Confirmar y Registrar Orden
                </button>
            </div>
        </div>
    </div>
</div> 

<script>
    const ID_TRANSACCION = "<?php echo $id_transaccion; ?>";
    const USUARIO_SESION = "<?php echo htmlspecialchars($usuario_sesion); ?>";
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/local3M/js/reparacion.js?v=<?php echo time(); ?>"></script>

<?php include 'templates/footer.php'; ?>