<?php
// 1. Iniciamos la sesión en CADA página
session_start();

// 2. Verificamos si el usuario está logueado
if (!isset($_SESSION['nombre'])) {
    // Si no hay sesión, lo redirigimos al login
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- El título puede cambiar, pero ponemos uno por defecto -->
    <title>Panel - 3M TECHNOLOGY</title> 
    
    <!-- Hoja de estilos principal del panel -->
    <link rel="stylesheet" href="css/panel.css">

    <!-- Incluimos Font Awesome para los iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/panel.css">
    
</head>
<body>

    <!-- Barra de Navegación -->
    <nav class="navbar">
        <div class="navbar-left">
            <div class="navbar-brand">3M TECHNOLOGY</div>
            <!-- El Menú Principal -->
            <ul class="navbar-menu">
                <li><a href="api/dashboard.php"><i class="fas fa-home"></i> Panel Principal</a></li>
                <li><a href="reparacion.php"><i class="fas fa-tools"></i> Reparaciones</a></li>
                <!-- Próximos módulos -->
                <!-- <li><a href="clientes.php"><i class="fas fa-users"></i> Clientes</a></li> -->
                <!-- <li><a href="inventario.php"><i class="fas fa-box"></i> Inventario</a></li> -->
            </ul>
        </div>
        
        <div class="navbar-user">
            <!-- Usamos la variable de sesión para saludar -->
            <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
            <a href="logout.php" class="logout-button">Cerrar Sesión</a>
        </div>
    </nav>

    <!-- Contenido del Panel (se cierra en footer.php) -->
    <div class="container">
