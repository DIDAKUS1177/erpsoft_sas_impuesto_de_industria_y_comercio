/*
 * Pantalla de carga.
 *
 * Historia de este archivo, por si vuelve a tocarse:
 *
 * 1) La version original calculaba la duracion con
 *      -(perfData.loadEventEnd - perfData.navigationStart)
 *    pero este script corre ANTES de que dispare el evento load, momento en
 *    el que loadEventEnd todavia vale 0. La resta devolvia navigationStart
 *    (un timestamp epoch) y el "% 60 * 100" lo convertia en el segundo actual
 *    del reloj: la barra duraba entre 0 y 5900 ms al azar, sin relacion con lo
 *    que tardaba la pagina.
 *
 * 2) Se corrigio para cerrarla en el evento load real, pero con un minimo
 *    visible de 400ms. Como en esta aplicacion CADA navegacion es una carga
 *    de pagina completa (menu.validarIngreso hace window.location = ...), esa
 *    espera minima salia SIEMPRE, incluso en pantallas que cargan en menos de
 *    100ms: el sistema se sentia mas lento de lo que realmente es.
 *
 * 3) Ahora la pantalla arranca OCULTA (regla .pre-loader{display:none} en
 *    dist/menu.php) y solo se muestra si la pagina sigue cargando pasado el
 *    umbral. Es el patron habitual: en cargas rapidas el usuario no ve nada y
 *    la navegacion se siente inmediata; en cargas lentas sigue habiendo aviso
 *    de que el sistema esta trabajando, que era el proposito original.
 */

(function () {
	'use strict';

	// Por debajo de este umbral no se muestra nada: el parpadeo de una
	// pantalla de carga que dura 200ms molesta mas de lo que informa.
	var UMBRAL_MOSTRAR = 350;
	// Si llego a mostrarse, se mantiene un momento para que no parpadee.
	var MINIMO_VISIBLE = 300;
	// Red de seguridad por si algun recurso se cuelga.
	var MAXIMO_ESPERA  = 8000;

	var $pantalla = $('.pre-loader');
	if (!$pantalla.length) { return; }

	var $barra   = $('#bar1');
	var $porcien = $('#percent1');

	var mostrada   = false;
	var cerrado    = false;
	var mostradaEn = 0;
	var progreso   = 0;
	var timerBarra = null;

	function pintar(valor) {
		$barra.css('width', valor + '%');
		$porcien.text(valor + '%');
	}

	function mostrar() {
		if (mostrada || cerrado) { return; }
		mostrada   = true;
		mostradaEn = Date.now();

		$pantalla.css('display', '');   // deja mandar al CSS de la plantilla

		// Avance suave hasta 90%: el 100% se pinta al cerrar, para que la
		// barra no quede llena mientras la pagina sigue cargando.
		pintar(0);
		timerBarra = setInterval(function () {
			if (progreso >= 90) { return; }
			progreso += Math.max(1, Math.round((90 - progreso) / 12));
			pintar(progreso);
		}, 40);
	}

	function cerrar() {
		if (cerrado) { return; }
		cerrado = true;

		if (timerBarra) { clearInterval(timerBarra); }

		// Nunca llego a mostrarse: no hay nada que cerrar y, sobre todo, no se
		// introduce ninguna espera artificial.
		if (!mostrada) { return; }

		pintar(100);

		var visibleDurante = Date.now() - mostradaEn;
		var restante = Math.max(0, MINIMO_VISIBLE - visibleDurante);

		setTimeout(function () {
			$pantalla.fadeOut(250);
		}, restante);
	}

	if (document.readyState === 'complete') {
		cerrar();
		return;
	}

	setTimeout(mostrar, UMBRAL_MOSTRAR);
	$(window).on('load', cerrar);
	setTimeout(cerrar, MAXIMO_ESPERA);
})();
