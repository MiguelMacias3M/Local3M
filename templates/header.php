<?php
// 1. Iniciamos la sesión (SOLO AQUÍ)
session_start();

// 2. SOLUCIÓN DE CACHÉ
// Forzamos al navegador a no guardar caché
header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
header('Pragma: no-cache'); // HTTP 1.0.
header('Expires: 0'); // Proxies.

// 3. Verificamos si el usuario está logueado
if (!isset($_SESSION['nombre'])) {
    // Si no hay sesión, lo redirigimos al login
    header('Location: ../index.php'); // <-- IMPORTANTE: ../ para salir de /templates
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel - 3M TECHNOLOGY</title> 
    
    <link rel="stylesheet" href="css/panel.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    </head>
<body>

    <nav class="navbar">
        <div class="navbar-left">
            <div class="navbar-brand">3M TECHNOLOGY</div>
            <ul class="navbar-menu">
                <li><a href="../dashboard.php"><i class="fas fa-home"></i> Panel Principal</a></li>
                <li><a href="../reparacion.php"><i class="fas fa-tools"></i> Reparaciones</a></li>
                </ul>
        </div>
        
        <div class="navbar-user">
            <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
            
            <a href="logout.php" class="logout-button">Cerrar Sesión</a>
        </div>
    </nav>

    <div class="container">