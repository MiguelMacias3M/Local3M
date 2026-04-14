<?php
// 1. Iniciamos la sesión (SOLO SI NO ESTÁ INICIADA YA)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. SOLUCIÓN DE CACHÉ
header('Cache-Control: no-cache, no-store, must-revalidate'); 
header('Pragma: no-cache'); 
header('Expires: 0'); 

// 3. Verificamos sesión
if (!isset($_SESSION['nombre'])) {
    header('Location: /local3M/login.php'); 
    exit(); 
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel - 3M TECHNOLOGY</title> 
    
    <link rel="stylesheet" href="/local3M/css/panel.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
</head>
<body>

    <nav class="navbar">
        <div class="navbar-left">
            <div class="navbar-brand">3M TECHNOLOGY</div>
            
            <button class="menu-toggle" id="menu-toggle">
                <i class="fas fa-bars"></i>
            </button>

            <ul class="navbar-menu" id="navbar-menu">
                <li><a href="/local3M/dashboard.php"><i class="fas fa-home"></i> Panel</a></li>
                <li><a href="/local3M/reparacion.php"><i class="fas fa-plus-circle"></i> Nueva Reparación</a></li>
                <li><a href="/local3M/control.php"><i class="fas fa-clipboard-list"></i> Control</a></li>
                <li><a href="/local3M/venta.php"><i class="fas fa-donate"></i> Venta</a></li>
                <li><a href="/local3M/productos.php"><i class="fas fa-boxes"></i> Productos</a></li>
                <li><a href="/local3M/mercancia.php"><i class="fas fa-clipboard-list"></i> Mercancía</a></li>
                <li><a href="/local3M/caja.php"><i class="fas fa-cash-register"></i> Caja</a></li>
                
                <?php if (isset($_SESSION['rol']) && strtolower($_SESSION['rol']) === 'admin'): ?>
                    <li><a href="/local3M/gastos.php"><i class="far fa-money-bill-alt"></i> Gastos</a></li>
                    <li><a href="/local3M/usuarios.php"><i class="fas fa-users-cog"></i> Usuarios</a></li>
                    <li><a href="/local3M/bonos.php"><i class="fas fa-trophy" style="color: #f1c40f;"></i> Rendimiento</a></li>
                <?php endif; ?>
                <li><a href="/local3M/encargos.php"><i class="fas fa-clipboard-list"></i> Encargos</a></li>
            </ul>
        </div>
        
        <div class="navbar-user">
            <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
            <a href="/local3M/logout.php" class="logout-button"> Cerrar Sesión</a>
        </div>
    </nav>

    <div class="container">
    <div id="btn-carrito-global" class="btn-carrito-flotante" onclick="toggleCarrito()">
    <i class="fas fa-shopping-cart"></i>
    <span id="badge-carrito" class="badge-carrito">0</span>
</div>

<div id="panel-carrito-global" class="panel-carrito">
    <div class="carrito-header">
        <h2><i class="fas fa-cash-register"></i> Punto de Venta</h2>
        <button class="btn-cerrar-carrito" onclick="toggleCarrito()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <div class="carrito-body">
        <ul id="lista-items-carrito" class="lista-items">
            <li class="item-vacio">El carrito está vacío</li>
        </ul>
    </div>
    
    <div class="carrito-footer">
        <div class="fila-total">
            <span>Total a Pagar:</span>
            <span class="monto-total">$<span id="total-carrito">0.00</span></span>
        </div>
        <div style="margin-bottom: 10px;">
            <label style="font-size: 0.9em; color: #555;">Método de Pago:</label>
            <select id="metodo-pago" class="form-input" style="width: 100%; margin-top: 5px;" onchange="cambiarMetodoPago()">
                <option value="Efectivo">💵 Efectivo</option>
                <option value="Transferencia">📱 Transferencia</option>
                <option value="Terminal">💳 Terminal / Tarjeta</option>
            </select>
        </div>
        <div class="seccion-cobro">
            <div class="input-group">
                <label for="paga-con">Paga con:</label>
                <div class="input-moneda">
                    <span>$</span>
                    <input type="number" id="paga-con" placeholder="0.00" onkeyup="calcularCambio()" onchange="calcularCambio()">
                </div>
            </div>
            <div class="fila-cambio">
                <span>Cambio:</span>
                <span class="monto-cambio">$<span id="cambio-carrito">0.00</span></span>
            </div>
        </div>
        
        <button class="btn-procesar-cobro" onclick="procesarCobroGlobal()">
            <i class="fas fa-check-circle"></i> Cobrar e Imprimir Ticket
        </button>
    </div>
</div>

<div id="overlay-carrito" class="overlay-carrito" onclick="toggleCarrito()"></div>