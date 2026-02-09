<?php
// 1. Iniciamos la sesión (SOLO AQUÍ)
session_start();

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
                <li><a href="/local3M/gastos.php"><i class="far fa-money-bill-alt"></i> Gastos</a></li>
                <li><a href="/local3M/usuarios.php"><i class="fas fa-users-cog"></i> Usuarios</a></li>
                <li><a href="/local3M/encargos.php"><i class="fas fa-clipboard-list"></i> Encargos</a></li>
            </ul>
        </div>
        
        <div class="navbar-user">
            <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
            <a href="/local3M/logout.php" class="logout-button"> Cerrar Sesión</a>
        </div>
    </nav>

    <div class="container">