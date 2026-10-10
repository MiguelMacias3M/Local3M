<script>
    const toggleButton = document.getElementById('menu-toggle');
    const menu = document.getElementById('navbar-menu');
    if (toggleButton && menu) {
        toggleButton.addEventListener('click', () => {
            menu.classList.toggle('active');
        });
    }
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) { window.location.reload(); }
    });
</script>

<!-- LA LÍNEA VITAL DEL CARRITO GLOBAL -->
<script src="/local3M/js/carrito_global.js?v=<?php echo time(); ?>"></script>

<!-- LIBRERÍA PARA LEER CÓDIGOS DE BARRAS CON LA CÁMARA -->
<script src="https://unpkg.com/html5-qrcode"></script>

<!-- ============================================== -->
<!-- OMNIBAR: BUSCADOR GLOBAL SPOTLIGHT + CÁMARA    -->
<!-- ============================================== -->
<style>
.glass-spotlight-overlay { position: fixed !important; top: 0 !important; left: 0 !important; width: 100vw !important; height: 100vh !important; background: rgba(0, 0, 0, 0.6) !important; backdrop-filter: blur(8px) !important; -webkit-backdrop-filter: blur(8px) !important; z-index: 9999999 !important; display: none; flex-direction: column; justify-content: flex-start !important; align-items: center !important; padding-top: 15vh !important; animation: fadeInSpotlight 0.2s ease-out; overflow-y: auto; }
.spotlight-search-box { background: rgba(255, 255, 255, 0.95) !important; width: 90% !important; max-width: 650px !important; border-radius: 20px !important; box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3) !important; display: flex !important; align-items: center !important; padding: 15px 25px !important; border: 1px solid rgba(255,255,255,1) !important; position: relative !important; overflow: hidden !important; transition: transform 0.1s; box-sizing: border-box !important; margin-bottom: 15px; }
.spotlight-search-box::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #007aff, #00ffff, #34c759); }
.spotlight-search-box i { font-size: 28px !important; color: #007aff !important; margin-right: 15px !important; }
.spotlight-input { flex: 1 !important; border: none !important; background: transparent !important; font-family: 'Poppins', sans-serif !important; font-size: 22px !important; font-weight: 600 !important; color: #1d1d1f !important; outline: none !important; margin: 0 !important; padding: 0 !important; width: 100%; }
.spotlight-input::placeholder { color: #c7c7cc !important; font-weight: 500 !important; }

/* Botón de Cámara Liquid Glass */
.btn-camera-scanner { background: rgba(0, 122, 255, 0.1); color: #007aff; border: none; border-radius: 12px; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; font-size: 20px; cursor: pointer; transition: 0.2s; margin-left: 10px; flex-shrink: 0; }
.btn-camera-scanner:hover { background: #007aff; color: white; transform: scale(1.05); }

/* Contenedor del video de la cámara */
#reader-container { width: 90%; max-width: 650px; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: none; animation: slideDownSmooth 0.3s ease; border: 1px solid rgba(0,0,0,0.1); }
#reader { width: 100%; border: none; }
#reader video { object-fit: cover; border-radius: 20px; }

.spotlight-hint { position: absolute; top: calc(100% + 5px); left: 0; width: 100%; text-align: center; color: rgba(255,255,255,0.7); font-family: 'Poppins', sans-serif; font-size: 13px; font-weight: 500; }
@keyframes fadeInSpotlight { from { opacity: 0; transform: scale(0.95) translateY(-20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.shake-error { animation: shake 0.4s cubic-bezier(.36,.07,.19,.97) both; }
@keyframes shake { 10%, 90% { transform: translate3d(-2px, 0, 0); } 20%, 80% { transform: translate3d(4px, 0, 0); } 30%, 50%, 70% { transform: translate3d(-6px, 0, 0); border-color: #ff3b30 !important; } 40%, 60% { transform: translate3d(6px, 0, 0); } }
@media (max-width: 600px) { .spotlight-search-box { padding: 12px 15px !important; } .spotlight-input { font-size: 16px !important; } .spotlight-search-box i { font-size: 20px !important; margin-right: 10px !important; } .btn-camera-scanner { width: 40px; height: 40px; font-size: 18px; } }
</style>

<div id="modalSpotlight" class="glass-spotlight-overlay">
    <div class="spotlight-search-box">
        <i id="iconSpotlight" class="fas fa-search"></i>
        <input type="text" id="inputSpotlight" class="spotlight-input" placeholder="Escanea o escribe un código..." autocomplete="off">
        <button id="btnScannerCamera" class="btn-camera-scanner" title="Escanear con Cámara"><i class="fas fa-camera"></i></button>
        <div class="spotlight-hint">Presiona <b>F2</b> o usa la cámara en tu celular</div>
    </div>
    
    <!-- Aquí se mostrará la cámara -->
    <div id="reader-container">
        <div id="reader"></div>
        <button onclick="detenerCamara()" style="width: 100%; padding: 15px; background: #ff3b30; color: white; border: none; font-weight: bold; cursor: pointer; font-family: 'Poppins', sans-serif; font-size: 15px;"><i class="fas fa-times"></i> Cancelar Escáner</button>
    </div>
</div>

<script>
let html5QrCode; // Variable global para la cámara

document.addEventListener('DOMContentLoaded', () => {
    const modalSpotlight = document.getElementById('modalSpotlight');
    const inputSpotlight = document.getElementById('inputSpotlight');
    const btnScannerCamera = document.getElementById('btnScannerCamera');

    if (modalSpotlight && inputSpotlight) {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'F2' || (e.ctrlKey && e.code === 'Space')) {
                e.preventDefault(); abrirSpotlight();
            }
            if (e.key === 'Escape' && modalSpotlight.style.display === 'flex') { cerrarSpotlight(); }
        });

        // Modificamos el clic fuera para no cerrar si estamos escaneando
        modalSpotlight.addEventListener('click', (e) => { 
            if (e.target === modalSpotlight) cerrarSpotlight(); 
        });

        inputSpotlight.addEventListener('keydown', (e) => { 
            if (e.key === 'Enter') { e.preventDefault(); procesarBusquedaGlobal(); } 
        });

        // -----------------------------------------------------
        // LÓGICA DE LA CÁMARA MEJORADA PARA ETIQUETAS TÉRMICAS
        // -----------------------------------------------------
        btnScannerCamera.addEventListener('click', () => {
            const readerContainer = document.getElementById('reader-container');
            
            if (readerContainer.style.display === 'block') {
                detenerCamara();
            } else {
                readerContainer.style.display = 'block';
                inputSpotlight.placeholder = "Apuntando a etiqueta...";
                
                html5QrCode = new Html5Qrcode("reader");
                html5QrCode.start(
                    { facingMode: "environment" }, // Usa la cámara trasera principal
                    {
                        fps: 30, // Máxima velocidad de escaneo
                        qrbox: function(viewfinderWidth, viewfinderHeight) {
                            // Cuadro dinámico: 90% del ancho de la pantalla y solo 120px de alto. 
                            // Perfecto para códigos largos de impresoras 58mm
                            return { width: viewfinderWidth * 0.9, height: 120 };
                        },
                        experimentalFeatures: {
                            useBarCodeDetectorIfSupported: true // Usa el motor nativo del celular (lo hace rapidísimo)
                        }
                    },
                    (decodedText, decodedResult) => {
                        detenerCamara();
                        inputSpotlight.value = decodedText;
                        if (navigator.vibrate) navigator.vibrate(200); 
                        procesarBusquedaGlobal(); 
                    },
                    (errorMessage) => {
                        // Se ignora silenciosamente mientras busca
                    }
                ).catch((err) => {
                    detenerCamara();
                    Swal.fire({toast: true, position: 'top-end', icon: 'error', title: 'Permiso de cámara denegado', showConfirmButton: false, timer: 3000});
                });
            }
        });

    // -----------------------------------------------------
    // FUNCIONES BASE DEL SPOTLIGHT
    // -----------------------------------------------------
    function abrirSpotlight() { modalSpotlight.style.display = 'flex'; inputSpotlight.value = ''; setTimeout(() => inputSpotlight.focus(), 50); }
    
    window.detenerCamara = function() {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop().then(() => {
                document.getElementById('reader-container').style.display = 'none';
                inputSpotlight.placeholder = "Escanea o escribe un código...";
            }).catch(err => console.log("Error deteniendo cámara", err));
        } else {
            document.getElementById('reader-container').style.display = 'none';
        }
    }

    function cerrarSpotlight() { 
        detenerCamara();
        modalSpotlight.style.display = 'none'; 
        inputSpotlight.blur(); 
    }

    async function procesarBusquedaGlobal() {
        const codigo = inputSpotlight.value.trim();
        if (!codigo) return;
        inputSpotlight.disabled = true;
        const icon = document.getElementById('iconSpotlight');
        icon.className = 'fas fa-spinner fa-spin'; 

        let fd = new FormData(); fd.append('codigo', codigo);

        try {
            const res = await fetch('/local3M/api/buscador_global.php', { method: 'POST', body: fd });
            const json = await res.json();

            if (json.success) {
                window.location.href = '/local3M/' + json.url; 
            } else {
                const searchBox = document.querySelector('.spotlight-search-box');
                searchBox.classList.add('shake-error');
                setTimeout(() => searchBox.classList.remove('shake-error'), 400);
                inputSpotlight.value = ''; inputSpotlight.placeholder = 'No encontrado...';
                inputSpotlight.disabled = false; inputSpotlight.focus(); icon.className = 'fas fa-search';
            }
        } catch (e) { inputSpotlight.disabled = false; icon.className = 'fas fa-search'; }
    }

    // ---------------------------------------------------------
    // ROBOT AUTO-EJECUTOR (Añade a Venta o Busca en Mercancía)
    // ---------------------------------------------------------
    const urlParams = new URLSearchParams(window.location.search);
    const searchCodigo = urlParams.get('search');
    
    if (searchCodigo) {
        setTimeout(() => {
            const inputTarget = document.getElementById('codigo_barras') || document.getElementById('codigo') || document.getElementById('buscar') || document.querySelector('input[type="search"]') || document.querySelector('input[placeholder*="código" i]') || document.querySelector('input[placeholder*="barras" i]');
            
            if (inputTarget) {
                inputTarget.value = searchCodigo;
                inputTarget.focus();
                
                ['keydown', 'keypress', 'keyup'].forEach(type => {
                    inputTarget.dispatchEvent(new KeyboardEvent(type, { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true }));
                });
                
                if (typeof jQuery !== 'undefined') {
                    let e = jQuery.Event("keypress"); e.which = 13; e.keyCode = 13;
                    jQuery(inputTarget).trigger(e);
                    jQuery(inputTarget).trigger('input').trigger('change');
                } else {
                    inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
                    inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
                }

                if (window.location.pathname.includes('venta.php') && inputTarget.form) {
                    const btnSubmit = inputTarget.form.querySelector('button[type="submit"], input[type="submit"]');
                    if (btnSubmit) btnSubmit.click();
                }
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }, 600);
    }
});
</script>
<!-- ============================================== -->