<?php
// NOTA: No necesitamos session_start() aquí porque header.php ya lo hace.
// Tampoco necesitamos verificar $_SESSION['nombre'] manualmente antes,
// porque header.php también hace esa validación y redirige si no hay sesión.

include 'templates/header.php';
?>

<link rel="stylesheet" href="css/encargos.css">

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-clipboard-list"></i> Libro de Encargos</h4>
                    <span class="badge bg-light text-primary" id="contador-pendientes"></span>
                </div>
                
                <div class="card-body">
                    <div class="input-group mb-4">
                        <input type="text" id="nuevo-encargo" class="form-control form-control-lg" placeholder="Escribe el encargo" autocomplete="off">
                        <button class="btn btn-success" type="button" onclick="agregarEncargo()">
                            <i class="fas fa-plus"></i> Agregar
                        </button>
                    </div>

                    <h5 class="text-muted mb-3">Pendientes</h5>
                    <ul id="lista-pendientes" class="list-group mb-4">
                        <div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>
                    </ul>

                    <h5 class="text-muted mb-3 mt-5" style="font-size:0.9rem;">Completados Recientemente</h5>
                    <ul id="lista-completados" class="list-group opacity-75">
                        </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="js/encargos.js"></script>

<?php include 'templates/footer.php'; ?>