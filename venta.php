<?php
include 'templates/header.php';
?>

<!-- Estilos específicos -->
<link rel="stylesheet" href="/css/venta.css?v=1.0">

<div class="page-title">
    <h1>Punto de Venta</h1>
    <p>Escanea productos o búscalos manualmente para vender.</p>
</div>

<div class="venta-container">
    
    <!-- LADO IZQUIERDO: PRODUCTOS -->
    <div class="products-area content-box">
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

        <!-- Grid de Productos (Se llena con JS) -->
        <div id="productsGrid" class="products-grid">
            <!-- Spinner inicial -->
            <div class="spinner"></div>
        </div>
    </div>

    <!-- LADO DERECHO: CARRITO -->
    <div class="cart-area content-box">
        <div class="cart-header">
            <h2><i class="fas fa-shopping-cart"></i> Carrito</h2>
            <button class="btn-clear" onclick="limpiarCarrito()"><i class="fas fa-trash"></i></button>
        </div>

        <div class="cart-items-container">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Prod.</th>
                        <th>Cant.</th>
                        <th>Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="cartBody">
                    <!-- Items del carrito -->
                </tbody>
            </table>
        </div>

        <div class="cart-footer">
            <div class="cart-total">
                <span>Total:</span>
                <span id="cartTotalDisplay">$0.00</span>
            </div>
            <button id="btnFinalizar" class="btn-checkout" onclick="finalizarVenta()">
                <i class="fas fa-check-circle"></i> COBRAR
            </button>
        </div>
    </div>

</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/js/venta.js"></script>

<?php include 'templates/footer.php'; ?>