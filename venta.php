<?php
include 'templates/header.php';
?>

<link rel="stylesheet" href="/local3M/css/venta.css?v=<?php echo time(); ?>">

<div class="glass-container" style="max-width: 1200px;">
    
    <div class="page-title-wrap">
        <div class="title-desc">
            <h1><i class="fas fa-shopping-bag" style="color:#007aff; margin-right:10px;"></i>Punto de Venta</h1>
            <p>Escanea un código o busca artículos para enviarlos al carrito.</p>
        </div>
    </div>

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