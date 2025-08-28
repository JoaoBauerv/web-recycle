<?php
require 'banco.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReciclaFácil - Coleta e Separação de Recicláveis</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #2c3e50;
            overflow-x: hidden;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        header {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(39, 174, 96, 0.3);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.8rem;
            font-weight: bold;
            color: white;
        }

        .logo i {
            font-size: 2rem;
            color: #f1c40f;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }

        nav a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #f1c40f;
        }

        .cta-nav {
            background: linear-gradient(45deg, #f1c40f, #f39c12);
            color: white !important;
            padding: 0.7rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(241, 196, 15, 0.3);
        }

        .cta-nav:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(241, 196, 15, 0.4);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.9), rgba(46, 204, 113, 0.8)),
                        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="white" opacity="0.1"/></svg>');
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            position: relative;
        }

        .hero-content {
            max-width: 700px;
            animation: fadeInUp 1s ease;
        }

        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .hero-highlight {
            color: #f1c40f;
            font-weight: 800;
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 25px;
            font-size: 1rem;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }

        .btn-primary {
            background: linear-gradient(45deg, #f1c40f, #f39c12);
            color: white;
            box-shadow: 0 4px 15px rgba(241, 196, 15, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-secondary:hover {
            background: white;
            color: #27ae60;
        }

        /* Services Section */
        .services {
            padding: 4rem 0;
            background: #f8f9fa;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #2c3e50;
        }

        .section-subtitle {
            text-align: center;
            margin-bottom: 3rem;
            font-size: 1.1rem;
            color: #7f8c8d;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .service-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            text-align: center;
            border-top: 4px solid #27ae60;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .service-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #27ae60;
        }

        .service-card h3 {
            font-size: 1.4rem;
            margin-bottom: 1rem;
            color: #2c3e50;
        }

        /* How it Works */
        .process {
            padding: 4rem 0;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
        }

        .process-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .process-step {
            background: rgba(255,255,255,0.1);
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .step-number {
            background: #f1c40f;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 1rem;
        }

        /* Contact Section */
        .contact {
            padding: 4rem 0;
            background: #2c3e50;
            color: white;
        }

        .contact-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .contact-info {
            background: rgba(255,255,255,0.1);
            padding: 2rem;
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .contact-icon {
            background: #f1c40f;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Footer */
        footer {
            background: #1a252f;
            color: white;
            text-align: center;
            padding: 2rem 0;
        }

        .materials-list {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-top: 2rem;
        }

        .materials-list h3 {
            color: #27ae60;
            margin-bottom: 1rem;
            text-align: center;
        }

        .materials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .material-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .material-item i {
            color: #27ae60;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.2rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-recycle"></i>
                    ReciclaFácil
                </div>
                <nav>
                    <ul>
                        <li><a href="#inicio">Início</a></li>
                        <li><a href="#servicos">Serviços</a></li>
                        <li><a href="#processo">Como Funciona</a></li>
                        <li><a href="#contato">Contato</a></li>
                        <li><a href="<?=$url_base?>/index2.php" class="cta-nav">Acesso</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="hero" id="inicio">
            <div class="hero-content">
                <h1>Coleta e Separação de <span class="hero-highlight">Recicláveis</span></h1>
                <p>Facilitamos a coleta dos seus materiais recicláveis e realizamos a separação adequada para encaminhamento aos centros de reciclagem.</p>
                
                <div class="hero-buttons">
                    <a href="#coleta" class="btn btn-primary">
                        <i class="fas fa-truck"></i>
                        Solicitar Coleta
                    </a>
                    <a href="#servicos" class="btn btn-secondary">
                        <i class="fas fa-info-circle"></i>
                        Nossos Serviços
                    </a>
                </div>
            </div>
        </section>

        <section class="services" id="servicos">
            <div class="container">
                <h2 class="section-title fade-in">Nossos Serviços</h2>
                <p class="section-subtitle fade-in">Soluções simples e eficazes para a gestão dos seus recicláveis</p>
                
                <div class="services-grid">
                    <div class="service-card fade-in">
                        <div class="service-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <h3>Coleta Domiciliar</h3>
                        <p>Coletamos seus materiais recicláveis diretamente na sua casa ou empresa, de forma pontual e confiável.</p>
                    </div>
                    
                    <div class="service-card fade-in">
                        <div class="service-icon">
                            <i class="fas fa-sort"></i>
                        </div>
                        <h3>Separação Especializada</h3>
                        <p>Realizamos a separação adequada dos materiais coletados, classificando-os por tipo para maximizar o reaproveitamento.</p>
                    </div>
                </div>

                <div class="materials-list fade-in">
                    <h3>Materiais que Coletamos</h3>
                    <div class="materials-grid">
                        <div class="material-item">
                            <i class="fas fa-file"></i>
                            <span>Papel e Papelão</span>
                        </div>
                        <div class="material-item">
                            <i class="fas fa-wine-bottle"></i>
                            <span>Plásticos</span>
                        </div>
                        <div class="material-item">
                            <i class="fas fa-cog"></i>
                            <span>Metais</span>
                        </div>
                        <div class="material-item">
                            <i class="fas fa-laptop"></i>
                            <span>Eletrônicos Básicos</span>
                        </div>
                        <div class="material-item">
                            <i class="fas fa-tint"></i>
                            <span>Óleo de Cozinha</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="process" id="processo">
            <div class="container">
                <h2 class="section-title fade-in">Como Funciona</h2>
                <p class="section-subtitle fade-in">Processo simples em apenas 3 passos</p>
                
                <div class="process-steps">
                    <div class="process-step fade-in">
                        <div class="step-number">1</div>
                        <h3>Você Solicita</h3>
                        <p>Entre em contato conosco e agende a coleta dos seus materiais recicláveis.</p>
                    </div>
                    
                    <div class="process-step fade-in">
                        <div class="step-number">2</div>
                        <h3>Nós Coletamos</h3>
                        <p>Nossa equipe vai até o local combinado e coleta todos os materiais recicláveis.</p>
                    </div>
                    
                    <div class="process-step fade-in">
                        <div class="step-number">3</div>
                        <h3>Separamos e Encaminhamos</h3>
                        <p>Fazemos a separação adequada e encaminhamos os materiais para os centros de reciclagem.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="contact" id="contato">
            <div class="container">
                <h2 class="section-title fade-in">Entre em Contato</h2>
                <p class="section-subtitle fade-in">Estamos prontos para ajudar com a coleta dos seus recicláveis</p>
                
                <div class="contact-content">
                    <div class="contact-info fade-in">
                        <h3>Informações de Contato</h3>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <strong>Endereço</strong><br>
                                Rua dos Recicláveis, 456<br>
                                Centro - Campinas, SP
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div>
                                <strong>Telefone</strong><br>
                                (19) 9999-COLETA<br>
                                (19) 3333-4567
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <strong>E-mail</strong><br>
                                contato@reciclafacil.com.br
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <strong>Horário</strong><br>
                                Segunda a Sexta: 8h às 17h<br>
                                Sábado: 8h às 12h
                            </div>
                        </div>
                    </div>
                    
                    <div class="contact-info fade-in" id="coleta">
                        <h3>Solicite sua Coleta</h3>
                        <p>Para solicitar a coleta, entre em contato através dos nossos canais de atendimento.</p>
                        
                        <div style="margin-top: 2rem;">
                            <a href="tel:(19)99999999" class="btn btn-primary" style="width: 100%; justify-content: center; margin-bottom: 1rem;">
                                <i class="fas fa-phone"></i>
                                Ligar Agora
                            </a>
                            
                            <a href="https://wa.me/5519999999999" class="btn btn-secondary" style="width: 100%; justify-content: center; background: #25d366; border: none; color: white;">
                                <i class="fab fa-whatsapp"></i>
                                WhatsApp
                            </a>
                        </div>
                        
                        <div style="margin-top: 2rem; font-size: 0.9rem;">
                            <p><strong>Área de Atendimento:</strong></p>
                            <p>Campinas e região metropolitana</p>
                            <p><strong>Coleta mínima:</strong> 5kg de materiais</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; text-align: left; margin-bottom: 2rem;">
                <div>
                    <h3 style="color: #27ae60; margin-bottom: 1rem;">ReciclaFácil</h3>
                    <p>Facilitando a coleta e separação de materiais recicláveis para um futuro mais sustentável.</p>
                </div>
                
                <div>
                    <h3 style="color: #27ae60; margin-bottom: 1rem;">Serviços</h3>
                    <p>• Coleta Domiciliar</p>
                    <p>• Coleta Empresarial</p>
                    <p>• Separação de Materiais</p>
                </div>
                
                <div>
                    <h3 style="color: #27ae60; margin-bottom: 1rem;">Contato</h3>
                    <p><i class="fas fa-phone"></i> (19) 9999-COLETA</p>
                    <p><i class="fas fa-envelope"></i> contato@reciclafacil.com.br</p>
                    <p><i class="fas fa-map-marker-alt"></i> Campinas, SP</p>
                </div>
            </div>
            
            <div style="text-align: center; padding-top: 2rem; border-top: 1px solid #34495e;">
                <p>&copy; 2024 ReciclaFácil. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script>
        // Scroll Animation Observer
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, { threshold: 0.1 });

        // Observe all fade-in elements
        document.querySelectorAll('.fade-in').forEach(el => {
            observer.observe(el);
        });

        // Smooth Scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    const headerHeight = document.querySelector('header').offsetHeight;
                    const targetPosition = target.offsetTop - headerHeight - 20;
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Header Background Change on Scroll
        window.addEventListener('scroll', () => {
            const header = document.querySelector('header');
            if (window.scrollY > 100) {
                header.style.background = 'rgba(39, 174, 96, 0.95)';
                header.style.backdropFilter = 'blur(15px)';
            } else {
                header.style.background = 'linear-gradient(135deg, #27ae60, #2ecc71)';
            }
        });

        console.log('ReciclaFácil - Sistema carregado!');
    </script>
</body>
</html>