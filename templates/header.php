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
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                    <a href="/local3M/encargos.php" class="sub-btn"><i class="fas fa-list" style="color:#ff9500;"></i> Encargos</a>
                </div>
            </div>

            <div class="module-group">
                <div class="module-core">
                    <div class="icon-box" style="color: #ff9500;"><i class="fas fa-box-open"></i></div>
                    Inventario
                </div>
                <div class="module-sub">
                    <a href="/local3M/vitrina.php" class="sub-btn"><i class="fas fa-mobile-alt" style="color:#007aff;"></i> Vitrina</a>
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
                        <a href="/local3M/gastos.php" class="sub-btn"><i class="fas fa-receipt" style="color:#ff3b30;"></i> Control de gastos</a>
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
        
        <!-- AQUÍ VA EL SCROLL: La lista de productos toma el espacio restante -->
        <div class="carrito-body" style="flex-grow: 1; overflow-y: auto; padding-bottom: 10px;">
            <ul id="lista-items-carrito" class="lista-items">
                <li class="item-vacio">El carrito está vacío</li>
            </ul>
        </div>
        
        <!-- FOOTER DE COBRO: Fijo, compacto y sin duplicados -->
        <div class="carrito-footer" style="padding: 15px; border-top: 1px solid rgba(0,0,0,0.08); background: #f9f9f9; flex-shrink: 0;">
            
            <!-- ÚNICO TOTAL A PAGAR -->
            <div class="fila-total" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border: none; padding: 0;">
                <span style="font-size: 15px; font-weight: 800; color: #1d1d1f;">Total a Pagar:</span>
                <span class="monto-total" style="font-size: 22px; font-weight: 800; color: #1d1d1f;">$<span id="total-carrito">0.00</span></span>
            </div>
            
            <div class="seccion-cobro">
                <style>
                .payment-methods-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 6px;
                    margin-bottom: 12px;
                }
                .pm-card {
                    background: #ffffff;
                    border: 1px solid rgba(0, 0, 0, 0.1);
                    border-radius: 8px;
                    padding: 8px 4px;
                    text-align: center;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    color: #86868b;
                    font-weight: 700;
                    font-size: 10px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 4px;
                }
                .pm-card i { font-size: 16px; }
                .pm-card.active {
                    background: rgba(0, 122, 255, 0.08);
                    border-color: #007aff;
                    color: #007aff;
                }
                </style>

                <!-- BOTONES DE MÉTODO DE PAGO -->
                <div style="margin-bottom: 10px;">
                    <label style="font-size: 12px; font-weight: 700; color: #1d1d1f; margin-bottom: 6px; display: block;">Método de Pago:</label>
                    <input type="hidden" id="metodo-pago" value="Efectivo">
                    
                    <div class="payment-methods-grid">
                        <div class="pm-card active" onclick="seleccionarMetodo('Efectivo', this)">
                            <i class="fas fa-money-bill-wave" style="color: #34c759;"></i><span>Efectivo</span>
                        </div>
                        <div class="pm-card" onclick="seleccionarMetodo('Terminal', this)">
                            <i class="fas fa-credit-card" style="color: #ff9500;"></i><span>Terminal</span>
                        </div>
                        <div class="pm-card" onclick="seleccionarMetodo('Transferencia', this)">
                            <i class="fas fa-mobile-alt" style="color: #5856d6;"></i><span>Transf.</span>
                        </div>
                        <div class="pm-card" onclick="seleccionarMetodo('Mixto', this)">
                            <i class="fas fa-random" style="color: #007aff;"></i><span>Mixto</span>
                        </div>
                    </div>
                </div>
                
                <!-- COBRO NORMAL COMPACTO -->
                <div id="cobro-normal">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; background: #ffffff; border: 1px solid rgba(0,0,0,0.1); padding: 6px 10px; border-radius: 8px;">
                        <label for="paga-con" style="font-weight: 700; font-size: 12px; margin: 0; color: #86868b;">Paga con:</label>
                        <div style="display: flex; align-items: center; width: 60%;">
                            <span style="font-weight: bold; color: #1d1d1f; font-size: 14px;">$</span>
                            <input type="number" id="paga-con" placeholder="0.00" onkeyup="calcularCambio()" onchange="calcularCambio()" style="border: none; outline: none; width: 100%; text-align: right; font-weight: 800; font-size: 16px; color: #1d1d1f; background: transparent;">
                        </div>
                    </div>
                    <div class="fila-cambio" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding: 0 5px;">
                        <span style="font-size: 12px; font-weight: 700; color: #86868b;">Cambio:</span>
                        <span style="font-size: 15px; font-weight: 800;">$<span id="cambio-carrito">0.00</span></span>
                    </div>
                </div>

                <!-- COBRO MIXTO COMPACTO -->
                <div id="cobro-mixto" style="display: none; background: rgba(0,122,255,0.03); border: 1px solid rgba(0,122,255,0.2); padding: 10px; border-radius: 8px; margin-bottom: 12px;">
                    <label style="font-size: 10px; font-weight: 800; color: #007aff; text-transform: uppercase; margin-bottom: 8px; display: block; text-align: center;">Distribuir el Pago</label>
                    
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                        <span style="font-size: 11px; font-weight: 700;">💵 Efectivo:</span>
                        <input type="number" id="mixto-efectivo" style="width: 55%; padding: 4px 8px; text-align: right; font-weight: 800; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px; outline: none;" placeholder="0.00" onkeyup="calcularCambioMixto()" onchange="calcularCambioMixto()">
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                        <span style="font-size: 11px; font-weight: 700;">💳 Terminal:</span>
                        <input type="number" id="mixto-terminal" style="width: 55%; padding: 4px 8px; text-align: right; font-weight: 800; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px; outline: none;" placeholder="0.00" onkeyup="calcularCambioMixto()" onchange="calcularCambioMixto()">
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 11px; font-weight: 700;">📱 Transf:</span>
                        <input type="number" id="mixto-transferencia" style="width: 55%; padding: 4px 8px; text-align: right; font-weight: 800; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px; outline: none;" placeholder="0.00" onkeyup="calcularCambioMixto()" onchange="calcularCambioMixto()">
                    </div>
                    
                    <div style="border-top: 1px dashed rgba(0,0,0,0.15); padding-top: 8px; display: flex; justify-content: space-between; font-weight: 800; font-size: 12px;">
                        <span>Estado:</span>
                        <span id="estado-mixto" style="color: #ff3b30;">Faltan $0.00</span>
                    </div>
                </div>
            </div>
            
            <button class="btn-procesar-cobro" onclick="procesarCobroGlobal()" style="width: 100%; background: #007aff; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 800; font-size: 14px; cursor: pointer; transition: 0.2s;">
                <i class="fas fa-check-circle"></i> Cobrar e Imprimir
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