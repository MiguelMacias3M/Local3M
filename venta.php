<?php
include 'templates/header.php';
?>

<link rel="stylesheet" href="/local3M/css/venta.css?v=1.0">

<div class="page-title">
    <h1>Catálogo de Productos</h1>
    <p>Escanea productos o búscalos manualmente para agregarlos al carrito global.</p>
</div>

<div class="venta-container" style="display: block;">
    
    <div class="products-area content-box" style="width: 100%;">
        <div class="search-bar-container">
            <div class="scan-input-wrap">
                <i class="fas fa-barcode"></i>
                <input type="text" id="scanInput" class="scan-input" placeholder="Escanea código de barras aquí..." autofocus>
            </div>
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Buscar por nombre...">
            </div>
        </div>

        <div id="productsGrid" class="products-grid">
            <div class="spinner"></div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/local3M/js/venta.js"></script>

<?php include 'templates/footer.php'; ?>