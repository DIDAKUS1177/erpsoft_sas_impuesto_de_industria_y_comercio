<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');

    // Color institucional centralizado: mismo origen que usa el panel interno
    // (dist/menu.php en /erpsoftsas/), asi que un cambio en config.municipio.php
    // rebrandea el portal publico y el aplicativo interno a la vez.
    $configPath = __DIR__ . '/../../config.municipio.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    }
    if (!defined('MUNICIPIO_NOMBRE'))       define('MUNICIPIO_NOMBRE', 'Alcaldía de Paipa');
    if (!defined('MUNICIPIO_COLOR'))        define('MUNICIPIO_COLOR', '#1fa49d');
    if (!defined('MUNICIPIO_COLOR_OSCURO')) define('MUNICIPIO_COLOR_OSCURO', '#17756f');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- Basic Page Info -->
    <meta charset="utf-8">
    <title>Portal Tributario | Alcaldía de Paipa</title>

    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Expires" content="0">
    <meta http-equiv="Last-Modified" content="0">
    <meta http-equiv="Cache-Control" content="no-cache, mustrevalidate">
    <meta http-equiv="Pragma" content="no-cache">

    <!-- Site favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="../vendors/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../vendors/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../vendors/images/favicon-16x16.png">

    <!-- Mobile Specific Metas: sin maximum-scale, que bloqueaba el zoom -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Google Fonts: Inter para texto (igual que el resto del sitio) +
         Source Serif 4 para los titulos, que le da caracter institucional
         al portal publico sin salirse de la familia tipografica del resto. -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" type="text/css" href="../vendors/styles/core.css">
    <link rel="stylesheet" type="text/css" href="../vendors/styles/icon-font.min.css">
    <link rel="stylesheet" type="text/css" href="../src/plugins/datatables/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="../src/plugins/datatables/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="../vendors/styles/style.css">
    <!-- CSS de Bootstrap 5 (el modal "Estampillas" usa data-bs-*) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* =========================================================
           TOKENS DE MARCA
           Salen de config.municipio.php; cambiar esas dos constantes
           rebrandea login, panel interno y este portal a la vez.
           ========================================================= */
        :root {
            --pd-primario:        <?php echo MUNICIPIO_COLOR; ?>;
            --pd-primario-oscuro: <?php echo MUNICIPIO_COLOR_OSCURO; ?>;
            --pd-primario-suave:  #E7F5F4;
            --pd-primario-borde:  #BFE4E1;

            --pd-tinta:       #16211F;
            --pd-tinta-tenue: #55655F;
            --pd-papel:       #F7F9F8;
            --pd-tarjeta:     #FFFFFF;
            --pd-borde:       #E3E9E7;

            --serif: "Source Serif 4", Georgia, "Times New Roman", serif;
            --sans:  "Inter", -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            background: var(--pd-papel) !important;
            color: var(--pd-tinta);
            font-family: var(--sans);
        }

        .pd-ltr-20 { padding: 0 !important; }

        /* Cuando el contenido es mas corto que la ventana, el pie de pagina
           se quedaba flotando con un vacio blanco debajo -se veia como si
           la pagina se "cortara" sin llegar al final de la pantalla-.
           Con este layout el <main> crece para ocupar el espacio sobrante
           y el footer siempre queda pegado al borde inferior. */
        html, body { height: 100%; }
        .pd-ltr-20 {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .pd-main { flex: 1 0 auto; }

        /* =========================================================
           HERO
           Antes: un lavado blanco plano al 50% sobre toda la foto,
           que le quitaba color a la imagen sin mejorar la lectura del
           texto. Ahora: un degrado teal solo donde va el texto, para
           que la foto se vea nitida a la derecha y el texto legible
           a la izquierda.
           ========================================================= */
        .pd-hero {
            position: relative;
            min-height: 380px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 28px clamp(20px, 4vw, 56px) 30px;
            background-image:
                linear-gradient(100deg,
                    rgba(12, 43, 41, .90) 0%,
                    rgba(12, 43, 41, .72) 32%,
                    rgba(12, 43, 41, .18) 62%,
                    rgba(12, 43, 41, 0) 80%),
                url('../vendors/images/paipa.jpg');
            background-size: cover;
            background-position: center;
            color: #FFFFFF;
            overflow: hidden;
            /* Se solapa 1px con la seccion siguiente: a ciertos niveles de
               zoom del navegador el redondeo de subpixeles entre esta caja
               y <main> dejaba una linea clara visible (fondo del body
               asomando). El solape la elimina sin recortar contenido. */
            margin-bottom: -1px;
        }

        .pd-hero::after {
            /* Filete dorado superior: un solo toque de color distinto
               al teal, tomado del escudo, usado una unica vez. */
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #C9A44B, var(--pd-primario) 65%);
        }

        .pd-hero-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: auto;
            padding-bottom: 22px;
        }

        .pd-marca {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .pd-marca img {
            height: 60px;
            width: auto;
            filter: drop-shadow(0 2px 6px rgba(0,0,0,.35));
        }
        .pd-marca-texto .pd-eyebrow {
            font-family: var(--sans);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #BFE4E1;
            margin: 0 0 2px;
        }
        .pd-marca-texto h1 {
            font-family: var(--serif);
            font-size: clamp(19px, 2.4vw, 25px);
            font-weight: 600;
            margin: 0;
            line-height: 1.15;
            text-wrap: balance;
        }

        .pd-acceso {
            flex: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 8px;
            background: rgba(255,255,255,.10);
            border: 1px solid rgba(255,255,255,.55);
            color: #FFFFFF !important;
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none !important;
            backdrop-filter: blur(2px);
            transition: background .15s ease, transform .15s ease;
            white-space: nowrap;
        }
        .pd-acceso:hover {
            background: rgba(255,255,255,.20);
            color: #FFFFFF !important;
            transform: translateY(-1px);
        }
        .pd-acceso svg { width: 15px; height: 15px; flex: none; }

        .pd-hero-lema {
            max-width: 540px;
            font-family: var(--serif);
            font-size: clamp(22px, 3.2vw, 31px);
            font-weight: 600;
            line-height: 1.28;
            text-wrap: balance;
            margin: 0 0 6px;
        }
        .pd-hero-sub {
            max-width: 560px;
            font-size: 14px;
            color: #DCEEEC;
            margin: 0;
        }

        /* =========================================================
           TRAMITES (tarjetas de servicio)
           ========================================================= */
        .pd-main { padding: 16px clamp(20px, 4vw, 40px) 4px; }

        .pd-seccion-titulo {
            font-family: var(--sans);
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--pd-tinta-tenue);
            margin: 0 0 14px;
        }

        .pd-servicios {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 14px;
        }

        .pd-servicio {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 14px;
            width: 100%;
            padding: 28px 24px;
            background: var(--pd-tarjeta);
            border: 1px solid var(--pd-borde);
            border-radius: 12px;
            text-align: left;
            cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }
        .pd-servicio:hover,
        .pd-servicio:focus-visible {
            transform: translateY(-3px);
            border-color: var(--pd-primario-borde);
            box-shadow: 0 10px 24px -8px rgba(23, 117, 111, .28);
        }
        .pd-servicio:focus-visible { outline: 2px solid var(--pd-primario); outline-offset: 2px; }

        .pd-servicio-icono {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 54px;
            height: 54px;
            border-radius: 12px;
            background: var(--pd-primario-suave);
            color: var(--pd-primario-oscuro);
            flex: none;
        }
        .pd-servicio-icono svg { width: 28px; height: 28px; }

        .pd-servicio-titulo {
            font-family: var(--sans);
            font-size: 16px;
            font-weight: 700;
            color: var(--pd-tinta);
            line-height: 1.3;
        }
        .pd-servicio-desc {
            font-size: 13.5px;
            color: var(--pd-tinta-tenue);
            line-height: 1.45;
            margin: -6px 0 0;
        }
        .pd-servicio-ir {
            margin-top: auto;
            padding-top: 4px;
            font-size: 12px;
            font-weight: 600;
            color: var(--pd-primario-oscuro);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .pd-servicio-ir svg { width: 12px; height: 12px; transition: transform .15s ease; }
        .pd-servicio:hover .pd-servicio-ir svg { transform: translateX(3px); }

        .pd-servicio.en-desarrollo .pd-servicio-icono { background: #F1F3F2; color: #8A9793; }

        /* =========================================================
           PIE DE PAGINA (banner unico)
           Antes Contacto/Normatividad eran tarjetas blancas sueltas
           -identicas en estilo a las tarjetas de tramites, sin nada que
           las distinguiera como "informacion de pie de pagina"- y
           encima una franja aparte con los creditos. Ahora es una sola
           banda con fondo propio y 3 columnas (Contacto, Normatividad,
           Plataforma), y una linea final de copyright. Es el patron
           estandar de pie de sitio: toda la info de referencia vive en
           un solo lugar, visualmente distinto del contenido de arriba.
           ========================================================= */
        .pd-footer-banda {
            margin-top: 16px;
            background: var(--pd-primario-suave);
            border-top: 1px solid var(--pd-primario-borde);
        }

        .pd-footer-cols {
            max-width: 1180px;
            margin: 0 auto;
            padding: 16px clamp(20px, 4vw, 40px) 12px;
            display: grid;
            grid-template-columns: 1.1fr 1.1fr .8fr;
            gap: 24px;
        }
        @media (max-width: 820px) {
            .pd-footer-cols { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 560px) {
            .pd-footer-cols { grid-template-columns: 1fr; }
        }

        .pd-footer-cols h2 {
            font-family: var(--sans);
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            color: var(--pd-primario-oscuro);
            margin: 0 0 10px;
        }

        .pd-contacto-fila {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            font-size: 13.5px;
            color: var(--pd-tinta-tenue);
            margin-bottom: 8px;
        }
        .pd-contacto-fila:last-child { margin-bottom: 0; }
        .pd-contacto-fila svg {
            width: 17px; height: 17px; flex: none; margin-top: 1px;
            color: var(--pd-primario-oscuro);
        }
        .pd-contacto-fila a { color: var(--pd-tinta); font-weight: 600; text-decoration: none; }
        .pd-contacto-fila a:hover { color: var(--pd-primario-oscuro); text-decoration: underline; }

        .pd-normativa { list-style: none; margin: 0; padding: 0; }
        .pd-normativa li { margin-bottom: 5px; }
        .pd-normativa li:last-child { margin-bottom: 0; }
        .pd-normativa a {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13.5px;
            font-weight: 500;
            color: var(--pd-tinta);
            text-decoration: none;
            padding: 3px 0;
        }
        .pd-normativa a svg { width: 15px; height: 15px; flex: none; color: var(--pd-tinta-tenue); }
        .pd-normativa a:hover { color: var(--pd-primario-oscuro); }
        .pd-normativa a:hover svg { color: var(--pd-primario-oscuro); }

        /* Columna 3: logo real de ERPSoft -el mismo archivo que ya usa
           el pie del panel interno (dist/dashboard.php), copiado a
           predial/vendors/images/ porque los dos sitios los sirven
           contenedores distintos (8080 vs 8081) y no comparten vendors. */
        .pd-footer-marca { display: flex; flex-direction: column; gap: 7px; }
        .pd-footer-marca img { height: 34px; width: auto; align-self: flex-start; }
        .pd-footer-marca p { font-size: 12.5px; color: var(--pd-tinta-tenue); margin: 0; max-width: 22ch; }
        .pd-footer-marca a { display: inline-block; }
        .pd-footer-marca a:hover { opacity: .85; }

        .pd-footer-copy {
            max-width: 1180px;
            margin: 0 auto;
            padding: 6px clamp(20px, 4vw, 40px) 10px;
            border-top: 1px solid var(--pd-primario-borde);
            font-size: 12px;
            color: var(--pd-tinta-tenue);
        }
        .pd-footer-copy strong { color: var(--pd-tinta); font-weight: 600; }

        @media (prefers-reduced-motion: reduce) {
            .pd-servicio, .pd-acceso, .pd-servicio-ir svg { transition: none !important; }
        }
    </style>

</head>
<body>
    <div class="pd-ltr-20">

        <!-- ============ HERO ============ -->
        <header class="pd-hero">
            <div class="pd-hero-top">
                <div class="pd-marca">
                    <img src="../vendors/images/banner-img.png" alt="Escudo Alcaldía de Paipa">
                    <div class="pd-marca-texto">
                        <p class="pd-eyebrow">Secretaría de Hacienda</p>
                        <h1>Dirección de Impuestos, Rentas y Jurisdicción Coactiva</h1>
                    </div>
                </div>

                <a id="btnAccesoAlcaldia" href="https://sistema.erpsoftsas.com/predial" target="_blank" class="pd-acceso">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    Acceso Alcaldía
                </a>
            </div>

            <p class="pd-hero-lema">Portal Tributario de Paipa</p>
            <p class="pd-hero-sub">Consulta, liquida y presenta tus obligaciones tributarias con el municipio desde un solo lugar.</p>
        </header>

        <!-- ============ TRAMITES ============ -->
        <main class="pd-main">

            <p class="pd-seccion-titulo">Trámites en línea</p>
            <div class="pd-servicios">

                <button type="button" class="pd-servicio" onclick="window.open('https://psepaipa.erpsoftsas.com/', '_blank')">
                    <span class="pd-servicio-icono">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
                    </span>
                    <span class="pd-servicio-titulo">Impuesto Predial</span>
                    <p class="pd-servicio-desc">Consulta el estado de cuenta y paga tu impuesto predial.</p>
                    <span class="pd-servicio-ir">Ir al trámite <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                </button>

                <button type="button" class="pd-servicio" onclick="window.open('https://sistema.erpsoftsas.com/predial', '_blank')">
                    <span class="pd-servicio-icono">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="13" y2="13"/></svg>
                    </span>
                    <span class="pd-servicio-titulo">Información Exógena</span>
                    <p class="pd-servicio-desc">Reporta la información exógena solicitada por el municipio.</p>
                    <span class="pd-servicio-ir">Ir al trámite <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                </button>

                <button type="button" class="pd-servicio" onclick="window.location.href='../../erpsoftsas/index.php'">
                    <span class="pd-servicio-icono">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><line x1="9" y1="9" x2="9" y2="9.01"/><line x1="9" y1="13" x2="9" y2="13.01"/><line x1="9" y1="17" x2="9" y2="17.01"/><line x1="15" y1="9" x2="15" y2="9.01"/><line x1="15" y1="13" x2="15" y2="13.01"/><line x1="15" y1="17" x2="15" y2="17.01"/></svg>
                    </span>
                    <span class="pd-servicio-titulo">Industria y Comercio</span>
                    <p class="pd-servicio-desc">Presenta y consulta tus declaraciones de ICA.</p>
                    <span class="pd-servicio-ir">Ir al trámite <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                </button>

                <button type="button" class="pd-servicio en-desarrollo" data-bs-toggle="modal" data-bs-target="#modalEnDesarrollo">
                    <span class="pd-servicio-icono">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="13" rx="2"/><path d="M7 8V6a5 5 0 0 1 10 0v2"/><circle cx="12" cy="14" r="2"/></svg>
                    </span>
                    <span class="pd-servicio-titulo">Estampillas</span>
                    <p class="pd-servicio-desc">Próximamente disponible en el portal.</p>
                    <span class="pd-servicio-ir">Próximamente</span>
                </button>

            </div>

        </main>

        <!-- ============ PIE DE PAGINA: Contacto · Normatividad · Plataforma ============ -->
        <footer class="pd-footer-banda">
            <div class="pd-footer-cols">
                <section>
                    <h2>Contacto</h2>
                    <div class="pd-contacto-fila">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <span>Secretaría de Hacienda – Dirección de Impuestos, Rentas y Jurisdicción Coactiva<br>
                        Tel: <a href="tel:3185309285">318 530 9285</a></span>
                    </div>
                    <div class="pd-contacto-fila">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22 6 12 13 2 6"/></svg>
                        <a href="mailto:impuestos@paipa-boyaca.gov.co">impuestos@paipa-boyaca.gov.co</a>
                    </div>
                    <div class="pd-contacto-fila">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <span>Carrera 22 # 25-14, primer piso</span>
                    </div>
                </section>

                <section>
                    <h2>Normatividad</h2>
                    <ul class="pd-normativa">
                        <li><a href="https://www.paipa-boyaca.gov.co/Transparencia/Normatividad/Acuerdo%20019%20de%202022.pdf" target="_blank"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Estatuto de Rentas Municipal</a></li>
                        <li><a href="https://www.paipa-boyaca.gov.co/Transparencia/Normatividad/Acuerdo%20018%20de%202024.pdf" target="_blank"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Régimen Sancionatorio</a></li>
                        <li><a href="https://www.paipa-boyaca.gov.co/Transparencia/Normatividad/Resolucion%20122-2143%20de%202024.pdf" target="_blank"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Calendario Tributario 2025</a></li>
                        <li><a href="https://www.paipa-boyaca.gov.co/Transparencia/Normatividad/Resolucion%20122-155%20de%202024.pdf" target="_blank"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Información Exógena Resolución 122-0155</a></li>
                        <li><a href="https://www.paipa-boyaca.gov.co/Transparencia/Normatividad/RESOLUCI%C3%93N%20122-0090%20DE%202025.pdf" target="_blank"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Información Exógena Resolución 122-0090</a></li>
                    </ul>
                </section>

                <section class="pd-footer-marca">
                    <h2>Plataforma</h2>
                    <a href="https://ERPSOFTSAS.com" target="_blank">
                        <img src="../vendors/images/erpsoftsas-logo.svg" alt="Sistemas ERPSoft SAS">
                    </a>
                    <p>Solución tecnológica desarrollada por ERPSOFTSAS para la gestión tributaria municipal.</p>
                </section>
            </div>

            <p class="pd-footer-copy"><strong><?php echo htmlspecialchars(MUNICIPIO_NOMBRE); ?></strong> · Portal Tributario · <?php echo date('Y'); ?></p>
        </footer>
    </div>

    <!-- Contenedores de ApexCharts: dashboard.js los instancia (aunque nunca
         llama a .render()); se mantienen vacios y fuera de vista para no
         arriesgar un error de "elemento no encontrado" en la consola. -->
    <div id="chart" style="display:none"></div>
    <div id="chart2" style="display:none"></div>
    <div id="chart4" style="display:none"></div>

    <div class="modal fade" id="modalEnDesarrollo" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalLabel">Módulo en desarrollo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    Este módulo aún se encuentra en desarrollo. Próximamente estará disponible.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- js -->
    <script src="../vendors/scripts/core.js"></script>
    <script src="../vendors/scripts/script.min.js"></script>
    <script src="../vendors/scripts/process.js"></script>
    <script src="../vendors/scripts/layout-settings.js"></script>
    <script src="../src/plugins/apexcharts/apexcharts.min.js"></script>
    <script src="../src/plugins/datatables/js/jquery.dataTables.min.js"></script>
    <script src="../src/plugins/datatables/js/dataTables.bootstrap4.min.js"></script>
    <script src="../src/plugins/datatables/js/dataTables.responsive.min.js"></script>
    <script src="../src/plugins/datatables/js/responsive.bootstrap4.min.js"></script>
    <script src="../vendors/scripts/dashboard.js"></script>
    <script src="../core/menu.js"></script>
    <script src="../core/Permisos.js"></script>
    <script src="../core/datosVisuales.js"></script>

    <!-- JS de Bootstrap 5 (el modal "Estampillas" usa data-bs-*) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
