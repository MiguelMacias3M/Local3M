<?php
// Revisamos si ya hay una sesión activa
session_start();
if (isset($_SESSION['nombre'])) {
    //
    // CORRECCIÓN: Usar la misma ruta absoluta que en header.php
    //
    header('Location: /local3M/dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - 3M-TECNOLOGY</title>

    <!-- CAMBIO: Ruta absoluta al CSS -->
    <link rel="stylesheet" href="/local3M/css/login.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <div class="login-card">
      <h1 class="logo-text">3M-TECHNOLOGY</h1>
      
        <form id="loginForm">
            <input type="text" 
                   id="nombre" 
                   class="login-input" 
                   name="nombre" 
                   placeholder="Nombre de usuario" 
                   required="required" />

            <input type="password" 
                   id="password" 
                   class="login-input" 
                   name="password" 
                   autocomplete="off" 
                   placeholder="Contraseña" 
                   required="required" />

            <!--
              AQUÍ ESTÁ EL CAMBIO:
              - Añadido id="loginButton"
              - Añadidos spans para el texto y el loader
            -->
            <button class="login-button" type="submit" id="loginButton">
                <span class="button-text">Iniciar sesión</span>
                <span class="button-loader"></span>
            </button>
        </form>

    </div>

    <!-- CAMBIO: Ruta absoluta al JS -->
    <script src="/local3M/js/login.js"></script>
</body>
</html>