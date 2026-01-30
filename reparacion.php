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

<div class="page-title">
    <h1>Nueva Orden de Reparación</h1>
    <p>Rellena los datos del cliente y añade las reparaciones al carrito.</p>
</div>

<div class="reparacion-grid">

    <div class="form-container content-box">
        <form id="reparacionForm" class="main-form" onsubmit="return false;">
            
            <h2><i class="fas fa-user-circle"></i> Datos del Cliente</h2>
            
            <label for="nombre_cliente">Nombre del Cliente:</label>
            <input id="nombre_cliente" class="form-input" type="text" name="nombre_cliente" required>
            
            <label for="telefono">Teléfono (10 dígitos):</label>
            <input id="telefono" class="form-input" placeholder="Ej: 4491234567" type="tel" name="telefono" maxlength="10" required>
            
            <label for="info_extra">Información Extra (Contraseña, Patrón):</label>
            <textarea id="info_extra" class="form-textarea" name="info_extra" rows="2" placeholder="Ej: Contraseña '1234', patrón 'L'"></textarea>

            <hr class="form-divider">

            <h2><i class="fas fa-mobile-alt"></i> Datos del Equipo</h2>

            <label for="tipo_reparacion">Tipo de Reparación:</label>
            <input id="tipo_reparacion" class="form-input" placeholder="Ej: Cambio de pantalla" type="text" name="tipo_reparacion" required>

            <label for="marca_celular">Marca:</label>
            <input id="marca_celular" class="form-input" placeholder="Ej: Samsung, Apple" type="text" name="marca_celular" required>

            <label for="modelo">Modelo:</label>
            <input id="modelo" class="form-input" placeholder="Ej: A51, iPhone 11" type="text" name="modelo" required>

            <label for="fecha_estimada"><i class="far fa-clock"></i> Fecha Promesa de Entrega:</label>
            <input type="datetime-local" class="form-input" id="fecha_estimada">
            <small style="display:block; color:#666; margin-top:-10px; margin-bottom:15px; font-size: 0.9em;">
                <i class="fas fa-info-circle"></i> Si lo dejas vacío, quedará como "Pendiente".
            </small>

            <div class="form-row">
                <div class="form-col">
                    <label for="monto">Monto Total ($):</label>
                    <input class="form-input" id="monto" type="number" name="monto" value="0" oninput="calcularDeuda()" required>
                </div>
                <div class="form-col">
                    <label for="adelanto">Adelanto ($):</label>
                    <input class="form-input" id="adelanto" type="number" name="adelanto" value="0" oninput="calcularDeuda()" required>
                </div>
            </div>

            <label for="deuda">Deuda Pendiente ($):</label>
            <input class="form-input" id="deuda" type="number" name="deuda" value="0" readonly>
            
            <button type="button" class="form-button btn-add" onclick="agregarAlCarrito()">
                <i class="fas fa-cart-plus"></i> Agregar al Carrito
            </button>

        </form>
    </div>

    <div class="carrito-container content-box">
        <h2><i class="fas fa-shopping-cart"></i> Carrito de Reparaciones</h2>
        
        <ul id="lista-carrito">
            <li>El carrito está vacío.</li>
        </ul>

        <div id="contenedor-total">
            <strong id="total-label">Deuda Total:</strong>
            <span id="total-deuda">$0</span>
        </div>
        
        <button id="btn-registrar" type="button" class="form-button btn-register" onclick="enviarCarrito()" disabled>
            <i class="fas fa-check-circle"></i> Registrar Orden
        </button>
    </div>

</div> 

<script>
    const ID_TRANSACCION = "<?php echo $id_transaccion; ?>";
    const USUARIO_SESION = "<?php echo htmlspecialchars($usuario_sesion); ?>";
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/local3M/js/reparacion.js?v=<?php echo time(); ?>"></script>

<?php
include 'templates/footer.php';
?>