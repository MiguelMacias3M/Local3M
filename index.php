<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3M TECHNOLOGY | Expertos en Reparación Móvil</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/landing.css">
</head>
<body>

    <nav class="navbar">
        <div class="container nav-container">
            <div class="logo">3M TECHNOLOGY</div>
            <ul class="nav-links">
                <li><a href="#inicio">Inicio</a></li>
                <li><a href="#servicios">Servicios</a></li>
                <li><a href="#nosotros">Nosotros</a></li>
                <li><a href="#contacto">Contacto</a></li>
                <li><a href="login.php" class="btn-login"><i class="fas fa-lock"></i> Acceso Taller</a></li>
            </ul>
            <div class="menu-toggle" id="mobile-menu">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </nav>

    <header id="inicio" class="hero">
        <div class="hero-content">
            <h1>Revive tu Dispositivo con los Expertos</h1>
            <p>Especialistas en reparación de celulares, software, desbloqueos y accesorios en Aguascalientes.</p>
            <a href="#contacto" class="btn-primary">Cotizar Ahora <i class="fas fa-arrow-right"></i></a>
        </div>
    </header>

    <section id="servicios" class="section">
        <div class="container">
            <h2 class="section-title">Nuestros Servicios</h2>
            <div class="services-grid">
                <div class="service-card">
                    <i class="fas fa-mobile-alt"></i>
                    <h3>Reparación de Hardware</h3>
                    <p>Cambio de pantallas, baterías, centros de carga y reparación de componentes internos.</p>
                </div>
                <div class="service-card">
                    <i class="fas fa-code"></i>
                    <h3>Software y Desbloqueos</h3>
                    <p>Solución a errores de sistema, cuentas Google, liberaciones de compañía y actualizaciones.</p>
                </div>
                <div class="service-card">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Mantenimiento Preventivo</h3>
                    <p>Limpieza interna, optimización y revisión general para alargar la vida de tu equipo.</p>
                </div>
                <div class="service-card">
                    <i class="fas fa-headphones"></i>
                    <h3>Accesorios y Gadgets</h3>
                    <p>Venta de cargadores originales, fundas, audífonos y protectores de pantalla.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="nosotros" class="section bg-light">
        <div class="container split-container">
            <div class="about-text">
                <h2 class="section-title text-left">Nuestra Historia</h2>
                <p><strong>3M TECHNOLOGY</strong> nació con la misión de ofrecer soluciones tecnológicas confiables y honestas en Aguascalientes.</p>
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
                <img src="uploads/celrep.jpg" alt="Taller de Reparación">
            </div>
        </div>
    </section>

    <section id="contacto" class="section">
        <div class="container">
            <h2 class="section-title">Contáctanos</h2>
            <div class="contact-grid">
                <div class="contact-info">
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <h4>Ubicación</h4>
                            <p>Adolfo López Mateos #101, La Punta, Cosío, Ags</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-phone-alt"></i>
                        <div>
                            <h4>Teléfono / WhatsApp</h4>
                            <p>449 491 21 64</p> </div>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <div>
                            <h4>Horario</h4>
                            <p>Lunes a Sábado: 09:00 AM - 09:00 PM</p>
                        </div>
                    </div>
                    
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
                
                <form class="contact-form">
                    <input type="text" placeholder="Tu Nombre" required>
                    <input type="tel" placeholder="Tu Teléfono" required>
                    <textarea rows="5" placeholder="¿En qué podemos ayudarte?" required></textarea>
                    <button type="submit" class="btn-primary">Enviar Mensaje</button>
                </form>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> 3M TECHNOLOGY. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
        // Menú Responsivo para el Sitio Web
        const mobileMenu = document.getElementById('mobile-menu');
        const navLinks = document.querySelector('.nav-links');

        mobileMenu.addEventListener('click', () => {
            navLinks.classList.toggle('active');
        });
    </script>
</body>
</html>