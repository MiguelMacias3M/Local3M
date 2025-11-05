<?php
// 1. Incluimos el header (seguridad, menú, etc.)
include 'templates/header.php';
include 'conexion.php'; // Incluimos la conexión a la BD

// 2. Generar un id_transaccion único para agrupar las reparaciones
// Lo pasaremos a JavaScript
$id_transaccion = uniqid('trans_');

// 3. Pasamos el nombre de usuario a JavaScript
$usuario_sesion = $_SESSION['nombre'] ?? 'Sistema';

?>

<link rel="stylesheet" href="/local3M/css/reparacion.css">

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
            <input id="telefono" class="form-input" placeholder="Ej: 5512345678" type="tel" name="telefono" maxlength="10" required>
            
            <label for="info_extra">Información Extra (Contraseña, Patrón, Detalles):</label>
            <textarea id="info_extra" class="form-textarea" name="info_extra" rows="2" placeholder="Ej: Contraseña '1234', patrón 'L'"></textarea>

            <hr class="form-divider">

            <h2><i class="fas fa-mobile-alt"></i> Datos del Equipo</h2>

            <label for="tipo_reparacion">Tipo de Reparación:</label>
            <input id="tipo_reparacion" class="form-input" placeholder="Ej: Cambio de pantalla, Mantenimiento" type="text" name="tipo_reparacion" required>

            <label for="marca_celular">Marca:</label>
            <input id="marca_celular" class="form-input" placeholder="Ej: Samsung, iPhone, Dell" type="text" name="marca_celular" required>

            <label for="modelo">Modelo:</label>
            <input id="modelo" class="form-input" placeholder="Ej: Galaxy A51, 11 Pro Max, Vostro 3400" type="text" name="modelo" required>

            <div class="form-row">
                <div class="form-col">
                    <label for="monto">Monto Total ($):</label>
                    <input class="form-input" id="monto" type="number" step="0.01" name="monto" value="0" oninput="calcularDeuda()" required>
                </div>
                <div class="form-col">
                    <label for="adelanto">Adelanto ($):</label>
                    <input class="form-input" id="adelanto" type="number" step="0.01" name="adelanto" value="0" oninput="calcularDeuda()" required>
                </div>
            </div>

            <label for="deuda">Deuda Pendiente ($):</label>
            <input class="form-input" id="deuda" type="number" name="deuda" value="0" readonly>
            
            <button type-="button" class="form-button btn-add" onclick="agregarAlCarrito()">
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
            <span id="total-deuda">$0.00</span>
        </div>
        
        <button id="btn-registrar" type="button" class="form-button btn-register" onclick="enviarCarrito()" disabled>
            <i class="fas fa-check-circle"></i> Registrar Orden
        </button>
    </div>

</div> <script>
    const ID_TRANSACCION = "<?php echo $id_transaccion; ?>";
    const USUARIO_SESION = "<?php echo htmlspecialchars($usuario_sesion); ?>";
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/local3M/js/reparacion.js"></script>

<?php
// 3. Incluimos el footer (cierre de HTML, script anti-caché, etc.)
include 'templates/footer.php';
?>
