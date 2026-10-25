<?php
include 'templates/header.php';
include 'config/conexion.php';

// VERIFICAR ESTADO DE LA CAJA PARA MOSTRAR ALERTA
$cajaAbierta = false;
try {
    // Buscamos explícitamente la palabra 'ABIERTA' para evitar el bug del ID 0
    $stmtCajaCheck = $conn->query("SELECT estado FROM caja_cierres WHERE estado = 'ABIERTA' LIMIT 1");
    if ($stmtCajaCheck && $stmtCajaCheck->fetchColumn() === 'ABIERTA') {
        $cajaAbierta = true;
    }
} catch(Exception $e) {}
?>

<link rel="stylesheet" href="/local3M/css/venta.css?v=<?php echo time(); ?>">

<div class="glass-container" style="max-width: 1200px;">
    
    <div class="page-title-wrap">
        <div class="title-desc">
            <h1><i class="fas fa-shopping-bag" style="color:#007aff; margin-right:10px;"></i>Punto de Venta</h1>
            <p>Escanea un código o busca artículos para enviarlos al carrito.</p>
        </div>
    </div>

    <!-- ALERTA DE CAJA CERRADA -->
    <?php if (!$cajaAbierta): ?>
    <div style="background: rgba(255, 59, 48, 0.1); border: 1px solid rgba(255, 59, 48, 0.3); border-radius: 16px; padding: 20px; margin-bottom: 25px; display: flex; align-items: center; gap: 15px; animation: fadeIn 0.5s ease;">
        <div style="background: white; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(255,59,48,0.2);">
            <i class="fas fa-lock" style="font-size: 22px; color: #ff3b30;"></i>
        </div>
        <div>
            <h3 style="margin: 0; color: #ff3b30; font-weight: 800; font-size: 16px;">El turno de caja está cerrado</h3>
            <p style="margin: 5px 0 0 0; color: #1d1d1f; font-size: 14px;">Podrás armar el carrito, pero el sistema bloqueará el cobro. <a href="cierre_caja.php" style="color: #007aff; font-weight: bold; text-decoration: none;">Abre el turno aquí</a> antes de cobrar.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- BARRA DE BÚSQUEDA Y ESCÁNER (LIQUID GLASS) -->
    <div class="glass-card" style="padding: 15px 20px; margin-bottom: 25px; display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
        
        <div class="search-filter-glass" style="flex: 2; min-width: 250px; position: relative;">
            <i class="fas fa-barcode" style="position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #007aff; font-size: 20px;"></i>
            <!-- El id="scanInput" es el que usa el Robot para encontrar la caja -->
            <input type="text" id="scanInput" class="glass-input" style="padding-left: 50px; font-size: 18px; font-weight: 700; color: #007aff;" placeholder="Escanea código de barras..." autofocus autocomplete="off">
        </div>
        
        <div class="search-filter-glass" style="flex: 1; min-width: 200px; position: relative;">
            <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #86868b;"></i>
            <input type="text" id="searchInput" class="glass-input" style="padding-left: 45px;" placeholder="Buscar por nombre..." autocomplete="off">
        </div>

    </div>

    <!-- GRID DE PRODUCTOS -->
    <div id="productsGrid" class="products-grid">
        <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #86868b;">
            <i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Cargando catálogo...
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/local3M/js/venta.js?v=<?php echo time(); ?>"></script>

<?php include 'templates/footer.php'; ?>