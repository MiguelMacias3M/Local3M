<?php
// 1. Iniciamos la sesión (SOLO AQUÍ)
session_start();

// 2. SOLUCIÓN DE CACHÉ (El paso clave)
// Estas 3 líneas FUERZAN al navegador a no guardar la página.
header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
header('Pragma: no-cache'); // HTTP 1.0.
header('Expires: 0'); // Proxies.

// 3. Verificamos si el usuario está logueado
if (!isset($_SESSION['nombre'])) {
    // Si no hay sesión, lo redirigimos al login
    //
    // ESTE ES EL GRAN CAMBIO: Usamos la ruta absoluta
    //
    header('Location: /local3M/index.php'); 
    exit(); // Detiene la ejecución del resto de la página
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
            <ul class="navbar-menu">
                
                <li><a href="/local3M/dashboard.php"><i class="fas fa-home"></i> Panel</a></li>
                <li><a href="/local3M/reparacion.php"><i class="fas fa-plus-circle"></i> Nueva Reparación</a></li>
                <li><a href="/local3M/control.php"><i class="fas fa-clipboard-list"></i> Control</a></li>
                <li><a href="/local3M/venta.php"><i class="fas fa-cash-register"></i> Venta</a></li>
                <li><a href="/local3M/caja.php"><i class="fas fa-piggy-bank"></i> Caja</a></li>
                <li><a href="/local3M/inventario.php"><i class="fas fa-boxes"></i> Mercancía</a></li>
                
                </ul>
        </div>
        
        <div class="navbar-user">
            <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
            <span> l </span>
            <a href="/local3M/logout.php" class="logout-button">  Cerrar Sesión</a>
        </div>
    </nav>

    <div class="container">