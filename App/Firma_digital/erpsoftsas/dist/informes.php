<?php
    require_once '../business/globals.php';
    include_once('../business/class.sessions.php');
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Informes | ERPSOFTSAS</title>

	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta http-equiv="Expires" content="0">
	<meta http-equiv="Cache-Control" content="no-cache, mustrevalidate">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<link rel="icon" type="image/png" sizes="32x32" href="../vendors/images/favicon-32x32.png">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" type="text/css" href="../vendors/styles/core.css">
	<link rel="stylesheet" type="text/css" href="../vendors/styles/icon-font.min.css">
	<link rel="stylesheet" type="text/css" href="../vendors/styles/style.css">
	<link rel="stylesheet" type="text/css" href="../src/plugins/sweetalert2/sweetalert2.css">
	<link rel="stylesheet" type="text/css" href="../src/styles/loading.css">
	<style>
		/* Informes (2026-10-14): los filtros con el mismo estilo de Consultar
		   (.filtros-declaraciones, dist/menu.php) y los dos botones de descarga. */
		.informe-filtros .campo select, .informe-filtros .campo input { min-width: 190px; }
		/* La casilla del NIT, igual que las listas de .filtros-declaraciones. */
		.informe-filtros .campo input {
			height: 34px; font-size: 13px; padding: 0 8px; border: 1px solid #D1D5DB;
			border-radius: 5px; background: #FFFFFF; color: var(--erp-texto);
		}
		.informe-filtros .campo input:focus { outline: 2px solid var(--erp-primario, #1fa49d); outline-offset: 1px; }
		.informe-acciones { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
		.informe-acciones .btn-informe {
			display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 18px;
			font-size: 14px; font-weight: 600; border-radius: 8px;
			border: 1px solid var(--erp-primario, #1fa49d); color: var(--erp-primario, #1fa49d); background: #fff;
			transition: background .15s, color .15s;
		}
		.informe-acciones .btn-informe:hover, .informe-acciones .btn-informe:focus-visible {
			background: var(--erp-primario, #1fa49d); color: #fff;
		}
		.informe-acciones .btn-informe[disabled] { opacity: .6; cursor: wait; }
		.informe-ayuda { font-size: 13px; color: #6B7280; margin: 14px 0 0; max-width: 70ch; }

		/* Pestañas (2026-11-10): «Informe rápido» (el de siempre) e «Informe general»
		   (core/informeGeneral.js). Es un tablist de verdad: flechas, Inicio y Fin. */
		.ig-pestanas { display: flex; gap: 4px; margin-bottom: 14px; border-bottom: 2px solid var(--erp-borde, #E5E7EB); }
		.ig-pestanas [role="tab"] {
			background: none; border: 0; border-bottom: 3px solid transparent; margin-bottom: -2px;
			padding: 10px 18px; font-size: 14px; font-weight: 600; color: var(--erp-texto-tenue, #6B7280); cursor: pointer;
		}
		.ig-pestanas [role="tab"]:hover { color: var(--erp-texto, #1F2937); }
		.ig-pestanas [role="tab"][aria-selected="true"] { color: var(--erp-primario-hover, #178a84); border-bottom-color: var(--erp-primario, #1fa49d); }
		.ig-pestanas [role="tab"]:focus-visible { outline: 2px solid var(--erp-primario, #1fa49d); outline-offset: -2px; }

		/* Informe general: tarjetas con bloques numerados (qué filas, filtros, columnas). */
		#panelGeneral .card-box { margin-bottom: 18px; }
		.ig-cab { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
		.ig-num {
			flex: 0 0 auto; width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
			background: var(--erp-primario-suave, #E6F6F5); color: var(--erp-primario-hover, #178a84); font-weight: 700; font-size: 13px;
		}
		.ig-cab h5 { margin: 0; font-size: 16px; font-weight: 700; color: var(--erp-primario-hover, #178a84); }
		.ig-cab .ig-opc { font-size: 13px; font-weight: 400; color: var(--erp-texto-tenue, #6B7280); }
		.ig-sub { font-size: 13px; color: var(--erp-texto-tenue, #6B7280); margin: 0 0 12px; }
		.ig-ayuda { display: block; font-size: 12px; color: var(--erp-texto-tenue, #6B7280); margin-top: 3px; max-width: 46ch; }
		.ig-aviso { margin: 10px 0 0; padding: 8px 12px; font-size: 13px; border-radius: 6px; background: #FFFBEB; border: 1px solid #FCD34D; color: #78350F; }
		.ig-error { margin: 10px 0 0; padding: 8px 12px; font-size: 13px; border-radius: 6px; background: #FEF2F2; border: 1px solid #FCA5A5; color: #991B1B; }

		.ig-plantilla { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; }
		.ig-plantilla .campo { flex: 1 1 280px; max-width: 460px; }
		.ig-btn {
			display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; font-size: 13px; font-weight: 600;
			border-radius: 8px; border: 1px solid var(--erp-primario, #1fa49d); color: var(--erp-primario-hover, #178a84); background: #fff; cursor: pointer;
		}
		.ig-btn:hover:not([disabled]), .ig-btn:focus-visible { background: var(--erp-primario, #1fa49d); color: #fff; }
		.ig-btn.ig-btn-lleno { background: var(--erp-primario, #1fa49d); color: #fff; }
		.ig-btn.ig-btn-lleno:hover:not([disabled]) { background: var(--erp-primario-hover, #178a84); }
		.ig-btn.ig-btn-peligro { border-color: #DC2626; color: #B91C1C; }
		.ig-btn.ig-btn-peligro:hover:not([disabled]), .ig-btn.ig-btn-peligro:focus-visible { background: #DC2626; color: #fff; }
		.ig-btn[disabled] { opacity: .45; cursor: not-allowed; }

		/* Tarjetas-radio: el input real está dentro, así el teclado y los lectores funcionan. */
		.ig-tarjetas { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 12px; margin-top: 12px; }
		.ig-tarjeta { position: relative; display: block; margin: 0; cursor: pointer; }
		.ig-tarjeta input { position: absolute; opacity: 0; inset: 0; width: 100%; height: 100%; margin: 0; cursor: pointer; }
		.ig-tarjeta .ig-tj {
			display: block; height: 100%; padding: 12px 14px 12px 40px; position: relative;
			border: 1px solid #D1D5DB; border-radius: 8px; background: #fff; transition: border-color .12s, background .12s;
		}
		.ig-tarjeta .ig-tj::before {
			content: ""; position: absolute; left: 14px; top: 15px; width: 16px; height: 16px; border-radius: 50%;
			border: 2px solid #9CA3AF; background: #fff; box-sizing: border-box;
		}
		.ig-tarjeta .ig-tj b { display: block; font-size: 14px; color: var(--erp-texto, #1F2937); }
		.ig-tarjeta .ig-tj span { display: block; font-size: 12.5px; color: var(--erp-texto-tenue, #6B7280); margin-top: 2px; }
		.ig-tarjeta input:checked + .ig-tj { border-color: var(--erp-primario, #1fa49d); box-shadow: inset 0 0 0 1px var(--erp-primario, #1fa49d); background: var(--erp-primario-suave, #E6F6F5); }
		.ig-tarjeta input:checked + .ig-tj::before { border-color: var(--erp-primario, #1fa49d); background: radial-gradient(circle, var(--erp-primario, #1fa49d) 0 45%, #fff 50%); }
		.ig-tarjeta input:focus-visible + .ig-tj { outline: 2px solid var(--erp-primario, #1fa49d); outline-offset: 2px; }
		.ig-tarjeta input:disabled + .ig-tj { opacity: .5; cursor: not-allowed; }

		/* Filtros: rejilla de campos por subgrupo. */
		.ig-subgrupo { margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--erp-borde, #E5E7EB); }
		.ig-subgrupo:first-of-type { border-top: 0; padding-top: 0; margin-top: 0; }
		.ig-rotulo { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--erp-texto-tenue, #6B7280); margin: 0 0 10px; }
		.ig-rejilla { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px 14px; }
		.ig-rejilla .campo { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
		.ig-rejilla .campo.ig-ancho { grid-column: 1 / -1; }
		.ig-rejilla label, .ig-etiqueta {
			font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--erp-texto-tenue, #6B7280); margin: 0;
		}
		.ig-rejilla select, .ig-rejilla input[type="text"], .ig-rejilla input[type="date"], .ig-rejilla input[type="number"], .ig-plantilla select, .ig-ordenar select {
			width: 100%; height: 36px; font-size: 13px; padding: 0 8px; border: 1px solid #D1D5DB; border-radius: 6px; background: #fff; color: var(--erp-texto, #1F2937);
		}
		.ig-rejilla select:focus, .ig-rejilla input:focus, .ig-plantilla select:focus, .ig-panel input:focus, .ig-ordenar select:focus, #igModalGuardar .form-control:focus {
			outline: 2px solid var(--erp-primario, #1fa49d); outline-offset: 1px;
		}
		.ig-par { display: flex; gap: 8px; align-items: center; }
		/* Un rango (Desde/Hasta) son dos casillas: ocupa dos columnas de la rejilla; en una
		   sola columna las casillas se encogen en vez de pisar a la vecina (revisado 2026-11-10). */
		.ig-par input { flex: 1 1 0; min-width: 0; }
		.ig-rejilla .campo:has(.ig-par) { grid-column: span 2; }
		.ig-par span { font-size: 12px; color: var(--erp-texto-tenue, #6B7280); }
		.ig-chips { display: flex; flex-wrap: wrap; gap: 8px; }
		.ig-chips.ig-largo { max-height: 132px; overflow-y: auto; padding: 2px; }
		.ig-chip {
			min-height: 30px; padding: 0 12px; font-size: 12px; font-weight: 600; border-radius: 999px;
			border: 1px solid #D1D5DB; background: #fff; color: var(--erp-texto, #1F2937); cursor: pointer;
		}
		.ig-chip:hover { border-color: var(--erp-primario, #1fa49d); }
		.ig-chip[aria-pressed="true"] { background: var(--erp-primario, #1fa49d); border-color: var(--erp-primario, #1fa49d); color: #fff; }
		.ig-chip:focus-visible { outline: 2px solid var(--erp-primario-hover, #178a84); outline-offset: 2px; }

		/* Columnas: casillas por grupo y panel del orden. */
		.ig-columnas { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 20px; align-items: start; }
		@media (max-width: 991px) { .ig-columnas { grid-template-columns: 1fr; } }
		.ig-grupos { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 16px 18px; align-content: start; }
		.ig-grupo { border: 0; padding: 0; margin: 0; min-width: 0; }
		.ig-grupo legend { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--erp-texto-tenue, #6B7280); margin-bottom: 8px; width: auto; }
		.ig-grupo label { display: flex; align-items: flex-start; gap: 8px; margin: 0 0 6px; font-size: 13px; font-weight: 600; color: var(--erp-texto, #1F2937); cursor: pointer; line-height: 1.3; }
		.ig-grupo input { margin-top: 2px; accent-color: var(--erp-primario, #1fa49d); width: 15px; height: 15px; flex: 0 0 auto; }
		.ig-panel { background: var(--erp-primario-suave, #E6F6F5); border: 1px solid var(--erp-primario-borde, #B9E5E2); border-radius: 10px; padding: 14px; }
		.ig-panel-cab { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
		.ig-panel-cab h6 { margin: 0; font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
		.ig-panel-cab .ig-cuenta { background: var(--erp-primario, #1fa49d); color: #fff; border-radius: 999px; padding: 1px 10px; font-size: 12px; }
		.ig-enlace { background: none; border: 0; padding: 4px 2px; font-size: 13px; font-weight: 600; color: var(--erp-primario-hover, #178a84); text-decoration: underline; cursor: pointer; }
		.ig-enlace[disabled] { opacity: .4; cursor: not-allowed; text-decoration: none; }
		.ig-enlace:focus-visible { outline: 2px solid var(--erp-primario, #1fa49d); outline-offset: 2px; }
		.ig-lista { list-style: none; margin: 10px 0 0; padding: 0; max-height: 460px; overflow-y: auto; }
		.ig-lista li { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
		.ig-lista .ig-n { flex: 0 0 24px; font-size: 12px; color: var(--erp-texto-tenue, #6B7280); text-align: right; }
		.ig-lista input[type="text"] { flex: 1 1 auto; min-width: 0; height: 34px; font-size: 13px; padding: 0 8px; border: 1px solid #D1D5DB; border-radius: 6px; background: #fff; }
		.ig-icono {
			flex: 0 0 auto; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;
			border: 1px solid #D1D5DB; border-radius: 6px; background: #fff; color: var(--erp-texto, #1F2937); cursor: pointer; font-size: 14px; line-height: 1;
		}
		.ig-icono:hover:not([disabled]), .ig-icono:focus-visible { border-color: var(--erp-primario, #1fa49d); color: var(--erp-primario-hover, #178a84); }
		.ig-icono[disabled] { opacity: .35; cursor: not-allowed; }
		.ig-icono:focus-visible { outline: 2px solid var(--erp-primario, #1fa49d); outline-offset: 1px; }
		.ig-ordenar { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--erp-borde, #E5E7EB); }
		.ig-ordenar .campo { display: flex; flex-direction: column; gap: 4px; min-width: 190px; }

		/* Resultado: vista previa corta y Excel. */
		.ig-acciones { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
		.ig-barra { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; margin-top: 14px; }
		.ig-barra[hidden] { display: none; }
		.ig-buscar { display: flex; align-items: center; gap: 10px; flex: 1 1 280px; max-width: 520px; }
		.ig-buscar input { flex: 1; height: 36px; border: 1px solid #D1D5DB; border-radius: 6px; padding: 0 10px; font-size: 14px; }
		.ig-orden-vista, .ig-excel-vista { font-size: 13px; color: var(--erp-texto-tenue, #6B7280); margin: 0; }
		.ig-excel-vista { display: flex; align-items: center; gap: 6px; flex-basis: 100%; }
		.ig-ordena { all: unset; cursor: pointer; font: inherit; font-weight: 700; white-space: nowrap; }
		.ig-ordena:hover, .ig-ordena:focus-visible { color: var(--erp-primario, #1fa49d); text-decoration: underline; }
		.ig-conteo { font-size: 13px; color: var(--erp-texto-tenue, #6B7280); margin: 12px 0 8px; }
		.ig-conteo b { color: var(--erp-texto, #1F2937); }
		.ig-tabla-caja { overflow-x: auto; border: 1px solid var(--erp-borde, #E5E7EB); border-radius: 8px; }
		.ig-tabla { width: 100%; border-collapse: collapse; font-size: 13px; }
		.ig-tabla th { background: #F9FAFB; font-size: 12px; font-weight: 700; text-align: left; white-space: nowrap; padding: 8px 10px; border-bottom: 1px solid var(--erp-borde, #E5E7EB); }
		.ig-tabla td { padding: 7px 10px; border-bottom: 1px solid #F3F4F6; white-space: nowrap; max-width: 320px; overflow: hidden; text-overflow: ellipsis; }
		.ig-tabla .ig-der { text-align: right; font-variant-numeric: tabular-nums; }
		.ig-tabla tfoot td { font-weight: 700; background: #F9FAFB; border-top: 2px solid var(--erp-borde, #E5E7EB); }
		.ig-pie { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: 10px; font-size: 13px; color: var(--erp-texto-tenue, #6B7280); }
		.ig-pie select { height: 32px; font-size: 13px; border: 1px solid #D1D5DB; border-radius: 6px; padding: 0 6px; background: #fff; }
		.ig-filtros-txt { font-size: 12.5px; color: var(--erp-texto-tenue, #6B7280); margin: 0 0 8px; }
		#igModalGuardar .ig-vis label { display: flex; gap: 8px; align-items: flex-start; font-weight: 600; font-size: 13px; margin-bottom: 6px; }
		@media (max-width: 575px) {
			.ig-pestanas [role="tab"] { padding: 10px 12px; font-size: 13px; }
			.ig-rejilla { grid-template-columns: 1fr; } .ig-rejilla .campo:has(.ig-par) { grid-column: auto; }
		}
	</style>
</head>
<body>
	<div id="loading" class="loading" hidden></div>
	<div id="wrapper" class="wrapper">

		<?php include 'menu.php'; ?>
		<div class="mobile-menu-overlay"></div>

		<div class="main-container">

			<!-- Pestañas (2026-11-10): el Informe rápido es el de siempre, sin tocar;
			     el Informe general (core/informeGeneral.js) arma el informe a la medida. -->
			<div class="ig-pestanas" role="tablist" aria-label="Tipo de informe" id="igPestanas">
				<button type="button" role="tab" id="tabRapido" aria-controls="panelRapido" aria-selected="true" tabindex="0">Informe rápido</button>
				<button type="button" role="tab" id="tabGeneral" aria-controls="panelGeneral" aria-selected="false" tabindex="-1" hidden>Informe general</button>
			</div>

			<div id="panelRapido" role="tabpanel" aria-labelledby="tabRapido">
			<div class="card-box mb-30">
				<div class="pd-20">
					<h4 class="h4 mb-1">Informes de declaraciones</h4>
					<small class="text-muted">
						Declaraciones presentadas, pagadas o las dos, de todos los contribuyentes o de uno, en PDF o Excel.
					</small>
				</div>

				<div class="pb-20 px-3">
					<div class="filtros-declaraciones informe-filtros">
						<div class="campo">
							<label for="infModulo">Impuesto</label>
							<select id="infModulo">
								<option value="ica">Industria y Comercio</option>
								<option value="reteica">Retención de ICA</option>
								<option value="autorreteica">Autorretención de ICA</option>
							</select>
						</div>
						<div class="campo">
							<label for="infEstado">Declaraciones</label>
							<select id="infEstado">
								<option value="pagadas">Pagadas</option>
								<option value="sinpagar">Presentadas sin pagar</option>
								<option value="todas">Todas las presentadas (pagadas o no)</option>
							</select>
						</div>
						<div class="campo">
							<label for="infAnio">Año</label>
							<select id="infAnio"></select>
						</div>
						<div class="campo" id="campoPeriodo" style="display:none;">
							<label for="infPeriodo">Período</label>
							<select id="infPeriodo"></select>
						</div>
						<!-- Rango de fechas (2026-10-20): de pago en las pagadas, de
						     presentación en las demás; el rótulo lo dice (informePagos.js). -->
						<div class="campo">
							<label for="infDesde" id="lblDesde">Pagadas desde</label>
							<input type="date" id="infDesde" min="2000-01-01" max="9999-12-31">
						</div>
						<div class="campo">
							<label for="infHasta" id="lblHasta">Pagadas hasta</label>
							<input type="date" id="infHasta" min="2000-01-01" max="9999-12-31">
						</div>
						<div class="campo">
							<label for="infDocumento">Contribuyente (NIT o cédula)</label>
							<input type="text" id="infDocumento" inputmode="numeric" maxlength="12" placeholder="Todos (sin DV)" autocomplete="off">
						</div>
					</div>

					<div class="informe-acciones">
						<button type="button" class="btn-informe" data-formato="pdf">
							<i class="fa fa-file-pdf-o"></i> Descargar PDF
						</button>
						<button type="button" class="btn-informe" data-formato="xlsx">
							<i class="fa fa-file-excel-o"></i> Descargar Excel
						</button>
					</div>
					<p class="informe-ayuda">
						El PDF se abre en otra pestaña; el Excel queda en Descargas. Sin NIT o cédula salen todos
						los contribuyentes; el NIT va sin dígito de verificación. Las fechas son un rango: las pagadas
						por fecha de pago, las demás por fecha de presentación (las dos casillas son opcionales).
						"Sin pagar" deja fuera las de $0 y las que ya tienen una corrección presentada.
					</p>
				</div>
			</div>
			</div><!-- /panelRapido -->

			<div id="panelGeneral" role="tabpanel" aria-labelledby="tabGeneral" hidden>
				<div id="igCarga" class="card-box"><div class="estado-vacio"><div class="ev-icono"><i class="fa fa-spinner fa-spin"></i></div><div class="ev-titulo">Cargando el Informe general…</div></div></div>
				<div id="igError" class="card-box" hidden></div>

				<div id="igContenido" hidden>
					<!-- Plantilla -->
					<div class="card-box"><div class="pd-20">
						<h4 class="h4 mb-1">Informe general</h4>
						<p class="ig-sub">Elija una plantilla o arme el informe: qué filas, qué filtros y qué columnas. Las plantillas compartidas las ve todo el municipio.</p>
						<div class="ig-plantilla">
							<div class="campo">
								<label class="ig-etiqueta" for="igPlantilla">Plantilla</label>
								<select id="igPlantilla" aria-describedby="igPlantillaAyuda"></select>
							</div>
							<button type="button" class="ig-btn" id="igGuardar"><i class="fa fa-save"></i> Guardar</button>
							<button type="button" class="ig-btn" id="igGuardarComo"><i class="fa fa-clone"></i> Guardar como</button>
							<button type="button" class="ig-btn ig-btn-peligro" id="igBorrar" aria-label="Borrar la plantilla" title="Borrar la plantilla"><i class="fa fa-trash"></i></button>
						</div>
						<small class="ig-ayuda" id="igPlantillaAyuda" style="max-width:none"></small>
						<div id="igAvisos" role="status" hidden></div>
					</div></div>

					<!-- 1. Qué filas trae -->
					<div class="card-box"><div class="pd-20">
						<div class="ig-cab"><span class="ig-num" aria-hidden="true">1</span><h5>Qué filas trae</h5></div>
						<div class="ig-rejilla" id="igCampoImpuesto" style="max-width:360px">
							<div class="campo">
								<label for="igImpuesto">Impuesto</label>
								<select id="igImpuesto"></select>
							</div>
						</div>
						<div class="ig-tarjetas" role="radiogroup" aria-label="Qué filas trae el informe" id="igModos"></div>
					</div></div>

					<!-- 2. Filtros -->
					<div class="card-box"><div class="pd-20">
						<div class="ig-cab"><span class="ig-num" aria-hidden="true">2</span><h5>Filtros <span class="ig-opc">(todos opcionales)</span></h5></div>
						<div id="igFiltros"></div>
					</div></div>

					<!-- 3. Columnas -->
					<div class="card-box"><div class="pd-20">
						<div class="ig-cab"><span class="ig-num" aria-hidden="true">3</span><h5>Columnas</h5></div>
						<div class="ig-columnas">
							<div class="ig-grupos" id="igGrupos"></div>
							<div class="ig-panel" id="igPanel">
								<div class="ig-panel-cab">
									<h6 id="igPanelTitulo">Columnas del informe <span class="ig-cuenta" id="igCuenta" aria-live="polite">0</span></h6>
									<button type="button" class="ig-enlace" id="igQuitarTodas">Quitar todas</button>
								</div>
								<p class="ig-sub" style="margin:6px 0 0;font-size:12.5px">Use las flechas para ordenar. Puede cambiar el título de cada columna.</p>
								<ol class="ig-lista" id="igLista" aria-labelledby="igPanelTitulo"></ol>
								<div class="ig-ordenar" id="igOrdenar">
									<div class="campo">
										<label class="ig-etiqueta" for="igOrdenCol">Ordenar las filas por</label>
										<select id="igOrdenCol"></select>
									</div>
									<div class="campo" style="min-width:140px">
										<label class="ig-etiqueta" for="igOrdenDir">Sentido</label>
										<select id="igOrdenDir"><option value="asc">Ascendente</option><option value="desc">Descendente</option></select>
									</div>
								</div>
							</div>
						</div>
					</div></div>

					<!-- Resultado -->
					<div class="card-box"><div class="pd-20">
						<div class="ig-acciones">
							<button type="button" class="ig-btn ig-btn-lleno" id="igVista"><i class="fa fa-eye"></i> Ver vista previa</button>
							<button type="button" class="ig-btn" id="igExcel"><i class="fa fa-file-excel-o"></i> Descargar Excel</button>
							<button type="button" class="ig-btn" id="igPdf"><i class="fa fa-file-pdf-o"></i> Descargar PDF</button>
						</div>
						<div id="igMensaje" role="alert" hidden></div>
						<!-- Buscador y orden de la vista previa: los aplica el servidor a TODAS las filas, no solo a la página. -->
						<div id="igBarra" class="ig-barra" hidden>
							<label class="ig-etiqueta" for="igBuscar">Buscar en el informe</label>
							<div class="ig-buscar">
								<input type="search" id="igBuscar" maxlength="60" autocomplete="off" placeholder="Documento, nombre o número de declaración">
								<button type="button" class="ig-enlace" id="igBuscarLimpiar" style="display:none">Quitar búsqueda</button>
							</div>
							<div class="ig-orden-vista">
								<span id="igOrdenVistaTxt"></span>
								<button type="button" class="ig-enlace" id="igOrdenVistaQuitar" style="display:none">Quitar este orden</button>
							</div>
							<label id="igExcelVistaCaja" class="ig-excel-vista" style="display:none">
								<input type="checkbox" id="igExcelVista" checked> El Excel y el PDF llevan la misma búsqueda y el mismo orden de la vista previa
							</label>
						</div>
						<div id="igResultado" aria-live="polite"></div>
					</div></div>
				</div>
			</div><!-- /panelGeneral -->

		</div>
	</div>

	<!-- Guardar como (plantilla nueva) -->
	<div class="modal fade" id="igModalGuardar" tabindex="-1" role="dialog" aria-labelledby="igModalTitulo" aria-hidden="true">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="igModalTitulo">Guardar como plantilla</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
				</div>
				<form id="igFormGuardar" novalidate>
					<div class="modal-body">
						<div class="form-group">
							<label for="igNombre">* Nombre</label>
							<input type="text" class="form-control" id="igNombre" autocomplete="off" required>
						</div>
						<div class="form-group">
							<label for="igDescripcion">Descripción (opcional)</label>
							<textarea class="form-control" id="igDescripcion" rows="2"></textarea>
						</div>
						<fieldset class="form-group ig-vis" id="igVisibilidad">
							<legend style="font-size:14px;font-weight:600">¿Quién la puede usar?</legend>
							<label><input type="radio" name="igVis" value="PRIVADA" checked> <span>Solo yo (privada)</span></label>
							<label id="igVisMunicipio"><input type="radio" name="igVis" value="MUNICIPIO"> <span>Todo el municipio (compartida)</span></label>
						</fieldset>
						<div class="ig-error" id="igModalError" role="alert" hidden></div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-success" id="igModalOk">Guardar plantilla</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<script src="../vendors/scripts/core.js"></script>
	<script src="../vendors/scripts/script.min.js"></script>
	<script src="../vendors/scripts/process.js"></script>
	<script src="../vendors/scripts/layout-settings.js"></script>
	<script src="../src/plugins/sweetalert2/sweetalert2.all.js"></script>
	<script src="../src/plugins/sweetalert2/sweet-alert.init.js"></script>
	<!-- menu.js ya lo carga menu.php. -->
	<script src="../core/informePagos.js?v=<?php echo time(); ?>"></script>
	<script src="../core/numeros.js?v=<?php echo time(); ?>"></script>
	<script src="../core/informeGeneral.js?v=<?php echo time(); ?>"></script>
</body>
</html>
