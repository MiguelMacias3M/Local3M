<?php
// 1. Iniciamos la sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. SOLUCIÓN DE CACHÉ
header('Cache-Control: no-cache, no-store, must-revalidate'); 
header('Pragma: no-cache'); 
header('Expires: 0'); 

// 3. SEGURIDAD: LÍMITE DE TIEMPO
$tiempo_inactividad = 1800; 
if (isset($_SESSION['ultimo_acceso'])) {
    if (time() - $_SESSION['ultimo_acceso'] > $tiempo_inactividad) {
        session_unset();
        session_destroy();
        header('Location: /local3M/login.php?motivo=inactividad');
        exit();
    }
}
$_SESSION['ultimo_acceso'] = time();

// 4. Verificamos sesión
if (!isset($_SESSION['nombre'])) {
    header('Location: /local3M/login.php'); 
    exit(); 
}

$esAdmin = (isset($_SESSION['rol']) && strtolower($_SESSION['rol']) === 'admin');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3M TECHNOLOGY - Panel</title> 
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/local3M/css/panel.css">
    <link rel="stylesheet" href="/local3M/css/header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

    <nav class="navbar">
        <div class="nav-left">
            <button class="btn-menu-trigger" onclick="toggleMenu()">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="logo-group">
                <div class="logo-3m">3M</div>
                <div class="logo-tech">TECHNOLOGY</div>
            </div>
        </div>
        
        <div class="navbar-user">
            <span><i class="far fa-user"></i> <?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
            <a href="/local3M/logout.php" class="logout-button">Cerrar Sesión</a>
        </div>
    </nav>

    <div class="launchpad-overlay" id="menuCristal" onclick="toggleMenu()">
        <h2 class="launchpad-title">Módulos</h2>
        <p class="launchpad-subtitle">Gestiona tu negocio de forma inteligente</p>
        
        <div class="launchpad-grid" onclick="event.stopPropagation()">
            
            <div class="module-group">
                <div class="module-core">
                    <div class="icon-box" style="color: #007aff;"><i class="fas fa-store"></i></div>
                    Mostrador
                </div>
                <div class="module-sub">
                    <a href="/local3M/dashboard.php" class="sub-btn"><i class="fas fa-home"></i> Inicio</a>
                    <a href="/local3M/venta.php" class="sub-btn"><i class="fas fa-cash-register" style="color:#34c759;"></i> Punto de Venta</a>
                    <a href="/local3M/apartados.php" class="sub-btn"><i class="fas fa-book" style="color:#ff9500;"></i> Apartados</a>
                    <a href="/local3M/encargos.php" class="sub-btn"><i class="fas fa-list" style="color:#ff9500;"></i> Encargos</a>
                </div>
            </div>

            <div class="module-group">
                <div class="module-core">
                    <div class="icon-box" style="color: #ff9500;"><i class="fas fa-box-open"></i></div>
                    Inventario
                </div>
                <div class="module-sub">
                    <a href="/local3M/equipos.php" class="sub-btn"><i class="fas fa-mobile-alt" style="color:#007aff;"></i> Vitrina</a>
                    <a href="/local3M/productos.php" class="sub-btn"><i class="fas fa-boxes" style="color:#8e8e93;"></i> Productos</a>
                    <a href="/local3M/mercancia.php" class="sub-btn"><i class="fas fa-list" style="color:#5856d6;"></i> Mercancía</a>
                </div>
            </div>

            <div class="module-group">
                <div class="module-core">
                    <div class="icon-box" style="color: #ff2d55;"><i class="fas fa-tools"></i></div>
                    Taller
                </div>
                <div class="module-sub">
                    <a href="/local3M/reparacion.php" class="sub-btn"><i class="fas fa-plus-circle" style="color:#ff2d55;"></i> Nueva Orden</a>
                    <a href="/local3M/control.php" class="sub-btn"><i class="fas fa-tasks" style="color:#5856d6;"></i> Control</a>
                </div>
            </div>

            <div class="module-group">
                <div class="module-core">
                    <div class="icon-box" style="color: #34c759;"><i class="fas fa-chart-pie"></i></div>
                    Finanzas
                </div>
                <div class="module-sub">
                    <a href="/local3M/caja.php" class="sub-btn"><i class="fas fa-cash-register" style="color:#1d1d1f;"></i> Caja</a>
                    <?php if ($esAdmin): ?>
                        <a href="/local3M/gastos.php" class="sub-btn"><i class="fas fa-receipt" style="color:#ff3b30;"></i> Gastos</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($esAdmin): ?>
            <div class="module-group">
                <div class="module-core">
                    <div class="icon-box" style="color: #5856d6;"><i class="fas fa-users-cog"></i></div>
                    Equipo
                </div>
                <div class="module-sub">
                    <a href="/local3M/usuarios.php" class="sub-btn"><i class="fas fa-users"></i> Usuarios</a>
                    <a href="/local3M/bonos.php" class="sub-btn"><i class="fas fa-trophy" style="color:#ffcc00;"></i> Rendimiento</a>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        function toggleMenu() {
            const menu = document.getElementById('menuCristal');
            menu.classList.toggle('active');
        }
    </script>

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
            
            <div class="seccion-cobro">
                <div style="margin-bottom: 10px;">
                    <label style="font-size: 0.9em; color: #555;">Método de Pago:</label>
                    <select id="metodo-pago" class="form-input" style="width: 100%; margin-top: 5px;" onchange="cambiarMetodoPago()">
                        <option value="Efectivo">💵 Efectivo</option>
                        <option value="Transferencia">📱 Transferencia</option>
                        <option value="Terminal">💳 Terminal / Tarjeta</option>
                    </select>
                </div>
                
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

    <script>
        let tiempoInactivo = 0;
        const limiteAviso = 25 * 60; // 25 minutos
        const limiteExpiracion = 30 * 60; // 30 minutos
        let avisoMostrado = false;

        function resetearTiempo() { tiempoInactivo = 0; }

        document.addEventListener('mousemove', resetearTiempo);
        document.addEventListener('keydown', resetearTiempo);
        document.addEventListener('click', resetearTiempo);
        document.addEventListener('scroll', resetearTiempo);

        setInterval(() => {
            tiempoInactivo++;
            if (tiempoInactivo === limiteAviso && !avisoMostrado) {
                avisoMostrado = true;
                Swal.fire({
                    title: '⏳ Sesión a punto de caducar',
                    text: 'Llevas 25 minutos inactivo. Por seguridad, tu sesión se cerrará en 5 minutos.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, seguir trabajando',
                    cancelButtonText: 'Cerrar sesión',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('/local3M/api/extender_sesion.php')
                            .then(res => res.json())
                            .then(data => {
                                if(data.success) {
                                    tiempoInactivo = 0;
                                    avisoMostrado = false;
                                } else {
                                    window.location.href = '/local3M/logout.php';
                                }
                            });
                    } else {
                        window.location.href = '/local3M/logout.php';
                    }
                });
            }

            if (tiempoInactivo >= limiteExpiracion) {
                window.location.href = '/local3M/logout.php';
            }
        }, 1000);

        setInterval(() => {
            if (tiempoInactivo < 600) {
                fetch('/local3M/api/extender_sesion.php');
            }
        }, 600000); 
    </script>