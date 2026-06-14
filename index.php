<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3M TECHNOLOGY | Expertos en Reparación Móvil</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- NAVBAR LIQUID GLASS -->
    <nav class="navbar">
        <div class="container nav-container">
            <div class="logo-group">
                <div class="logo-3m">3M TECHNOLOGY</div>
                <div class="logo-tech">REPARACIÓN MÓVIL</div>
            </div>
            <ul class="nav-links">
                <li><a href="#inicio">Inicio</a></li>
                <li><a href="#servicios">Servicios</a></li>
                <li><a href="#nosotros">Nosotros</a></li>
                <li><a href="#rastreo">Estado de la reparacion</a></li>
                <li><a href="#contacto">Contacto</a></li>
                <li><a href="login.php" class="btn-login"><i class="fas fa-lock"></i> Taller</a></li>
            </ul>
            <div class="menu-toggle" id="mobile-menu">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <header id="inicio" class="hero">
        <div class="hero-content glass-card">
            <h1>Revive tu Dispositivo con los Expertos</h1>
            <p>Especialistas en reparación de celulares, software, desbloqueos y accesorios en Aguascalientes.</p>
            <a href="#servicios" class="btn-primary">Descubre más <i class="fas fa-arrow-down"></i></a>
        </div>
    </header>

    <!-- SERVICIOS -->
    <section id="servicios" class="section">
        <div class="container">
            <h2 class="section-title text-center">Nuestros Servicios</h2>
            <div class="services-grid">
                <div class="glass-card service-card">
                    <div class="icon-wrapper"><i class="fas fa-mobile-alt"></i></div>
                    <h3>Reparación de Hardware</h3>
                    <p>Cambio de pantallas, baterías, centros de carga y reparación de componentes internos.</p>
                </div>
                <div class="glass-card service-card">
                    <div class="icon-wrapper"><i class="fas fa-code"></i></div>
                    <h3>Software y Desbloqueos</h3>
                    <p>Solución a errores de sistema, cuentas Google, liberaciones de compañía y actualizaciones.</p>
                </div>
                <div class="glass-card service-card">
                    <div class="icon-wrapper"><i class="fas fa-shield-alt"></i></div>
                    <h3>Mantenimiento Preventivo</h3>
                    <p>Limpieza interna, optimización y revisión general para alargar la vida de tu equipo.</p>
                </div>
                <div class="glass-card service-card">
                    <div class="icon-wrapper"><i class="fas fa-headphones"></i></div>
                    <h3>Accesorios y Gadgets</h3>
                    <p>Venta de cargadores originales, fundas, audífonos y protectores de pantalla.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- HISTORIA -->
    <section id="nosotros" class="section">
        <div class="container split-container glass-card">
            <div class="about-text">
                <h2 class="section-title text-left">Nuestra Historia</h2>
                <p><strong>3M TECHNOLOGY</strong> nació con la misión de ofrecer soluciones tecnológicas confiables y honestas en la región.</p>
                <p>Lo que comenzó como un pequeño proyecto de pasión por la tecnología, hoy es un taller especializado donde la calidad y la transparencia son nuestra firma. Nos capacitamos constantemente para resolver las fallas más complejas de los dispositivos modernos.</p>
                <div class="stats">
                    <div class="stat-item">
                        <span class="number">+1000</span>
                        <span class="label">Equipos Reparados</span>
                    </div>
                    <div class="stat-item">
                        <span class="number">100%</span>
                        <span class="label">Garantizado</span>
                    </div>
                </div>
            </div>
            <div class="about-image">
                <img src="https://www.diyfixtool.com/cdn/shop/articles/Jonathan_Strange_0c06e937-faa1-4ede-a486-ce52d9449feb.png?v=1772242116" alt="Taller de Reparación 3M Technology" style="border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); width: 100%; height: auto; object-fit: cover;">
            </div>
        </div>
    </section>

    <!-- SECCIÓN: RASTREO DE EQUIPO -->
    <section id="rastreo" class="section" style="padding-bottom: 20px;">
        <div class="container">
            <div class="glass-card tracking-card text-center" style="margin: 0 auto;">
                <h2 class="section-title" style="text-align: center; margin-bottom: 10px;">
                    <i class="fas fa-search-location" style="color: #007aff;"></i> Revisar el estado de tu reparación
                </h2>
                <p style="text-align: center; color: #86868b; margin-bottom: 25px;">Ingresa el folio de tu orden y los últimos 4 dígitos de tu número celular para conocer el estado de tu reparación de forma segura.</p>
                
                <form id="form-rastreo" class="tracking-form" onsubmit="rastrearEquipo(event)">
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 15px;">
                        <div class="search-box" style="flex: 1; min-width: 200px;">
                            <i class="fas fa-barcode"></i>
                            <input type="text" id="folio_rastreo" class="glass-input" placeholder="Folio (Ej. REP-0015)" required style="margin-bottom:0;">
                        </div>
                        <div class="search-box" style="flex: 1; min-width: 200px;">
                            <i class="fas fa-phone"></i>
                            <!-- Input bloqueado a exactamente 4 números numéricos -->
                            <input type="text" id="tel_rastreo" class="glass-input" placeholder="Últimos 4 dígitos del celular" maxlength="4" pattern="\d{4}" required style="margin-bottom:0;" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary" id="btn-buscar-rastreo" style="width: 100%;">Buscar mi Equipo</button>
                </form>

                <!-- Aquí se mostrará el resultado con animación -->
                <div id="resultado-rastreo" style="display:none; margin-top: 25px; text-align: left;"></div>
            </div>
        </div>
    </section>

    <!-- CONTACTO -->
    <section id="contacto" class="section">
        <div class="container">
            <h2 class="section-title text-center">Contáctanos</h2>
            <div class="contact-grid">
                <div class="glass-card contact-info">
                    <div class="info-item">
                        <div class="icon-wrapper-small"><i class="fas fa-map-marker-alt"></i></div>
                        <div>
                            <h4>Ubicación</h4>
                            <p>C. Adolfo López Mateos #101, La Punta, Cosío, Ags.</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="icon-wrapper-small"><i class="fas fa-phone-alt"></i></div>
                        <div>
                            <h4>Teléfono / WhatsApp</h4>
                            <p>449 491 2164</p> 
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="icon-wrapper-small"><i class="fas fa-clock"></i></div>
                        <div>
                            <h4>Horario</h4>
                            <p>Lunes a Sábado: 09:00 AM - 09:00 PM</p>
                        </div>
                    </div>
                    <div class="social-links mt-4">
                        <!-- Botón de Facebook -->
                        <a href="https://www.facebook.com/share/1JDDRQwe4C/?mibextid=wwXIfr" target="_blank" class="social-btn" title="Síguenos en Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        
                        <!-- Botón de Instagram -->
                        <a href="#" class="social-btn" title="Síguenos en Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        
                        <!-- Botón de WhatsApp -->
                        <a href="https://wa.me/524494912164?text=Hola,%20vengo%20de%20su%20página%20web%20y%20me%20gustaría%20información%20sobre%20una%20reparación." target="_blank" class="social-btn whatsapp" title="Escríbenos por WhatsApp">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    </div>
                </div>
                
                <div class="glass-card">
                    <h3 style="margin-top:0;">Envíanos un mensaje</h3>
                    <form class="contact-form" onsubmit="event.preventDefault();">
                        <input type="text" class="glass-input" placeholder="Tu Nombre" required>
                        <input type="tel" class="glass-input" placeholder="Tu Teléfono" required>
                        <textarea rows="4" class="glass-input" placeholder="¿En qué podemos ayudarte?" required></textarea>
                        <button type="submit" class="btn-primary" style="width:100%;">Enviar Mensaje</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> 3M TECHNOLOGY. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // --- LECTOR DE CÓDIGO QR (AUTO-LLENADO) ---
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const folioDesdeQR = urlParams.get('folio');
            
            if (folioDesdeQR) {
                const inputFolio = document.getElementById('folio_rastreo');
                if (inputFolio) {
                    inputFolio.value = folioDesdeQR;
                    // Movemos la pantalla suavemente hacia la sección de rastreo
                    document.getElementById('rastreo').scrollIntoView({ behavior: 'smooth' });
                    
                    // Opcional: Pequeña alerta para avisarle al cliente
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'info',
                        title: 'Folio detectado. Ingresa los 4 dígitos de tu celular para consultar.',
                        showConfirmButton: false,
                        timer: 4000
                    });
                }
            }
        });

        // --- MENÚ MÓVIL ---
        const mobileMenu = document.getElementById('mobile-menu');
        const navLinks = document.querySelector('.nav-links');

        if(mobileMenu) {
            mobileMenu.addEventListener('click', () => {
                navLinks.classList.toggle('active');
            });
        }

        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('active');
            });
        });

        // --- LÓGICA DE RASTREO DE EQUIPOS (CON SEGURIDAD) ---
        function rastrearEquipo(e) {
            e.preventDefault();
            
            // Obtenemos los valores de ambos inputs
            const folioInput = document.getElementById('folio_rastreo');
            const telInput = document.getElementById('tel_rastreo');
            
            if (!folioInput || !telInput) return;
            
            const folio = folioInput.value.trim();
            const tel = telInput.value.trim();
            
            const btn = document.getElementById('btn-buscar-rastreo');
            const resultado = document.getElementById('resultado-rastreo');

            if (!folio || !tel) return;

            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validando...';
            btn.disabled = true;
            resultado.style.display = 'none';

            // Enviamos el folio y el teléfono a la API
            fetch(`api/consultar_estado_publico.php?folio=${encodeURIComponent(folio)}&tel=${encodeURIComponent(tel)}`)
                .then(res => res.json())
                .then(data => {
                    btn.innerHTML = 'Buscar mi Equipo';
                    btn.disabled = false;

                    if (data.success) {
                        let icon = "fa-tools";
                        let color = "#ff9500"; 
                        if(data.estado.toLowerCase().includes('reparado') || data.estado.toLowerCase().includes('listo') || data.estado.toLowerCase().includes('entregado')) {
                            icon = "fa-check-circle";
                            color = "#34c759"; 
                        }

                        resultado.innerHTML = `
                            <div style="background: rgba(255,255,255,0.8); padding: 20px; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                                <div style="font-size: 40px; color: ${color};"><i class="fas ${icon}"></i></div>
                                <div style="flex: 1; min-width: 200px;">
                                    <h3 style="margin: 0; font-size: 18px;">${data.marca} ${data.modelo}</h3>                                    <p style="margin: 5px 0 0 0; color: #86868b; font-size: 14px;">Cliente: ${data.cliente}</p>
                                    <div style="margin-top: 10px;">
                                        <span style="background: ${color}20; color: ${color}; padding: 6px 12px; border-radius: 8px; font-weight: 700; font-size: 14px;">
                                            Estado: ${data.estado}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        `;
                        resultado.style.display = 'block';
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'No Autorizado',
                            text: data.error ? 'Error BD: ' + data.error : data.message,
                            confirmButtonColor: '#007aff'
                        });
                    }
                })
                .catch(err => {
                    btn.innerHTML = 'Buscar mi Equipo';
                    btn.disabled = false;
                    Swal.fire('Error', 'Hubo un problema de conexión al buscar tu folio.', 'error');
                    console.error("Detalle del error:", err);
                });
        }
    </script>
</body>
</html>