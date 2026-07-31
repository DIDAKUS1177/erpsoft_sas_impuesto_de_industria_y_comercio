/*
 * Pantalla de carga.
 *
 * La version anterior calculaba la duracion con
 *   -(perfData.loadEventEnd - perfData.navigationStart)
 * pero este script corre ANTES de que dispare el evento load, momento en
 * el que loadEventEnd todavia vale 0. La resta devolvia navigationStart
 * (un timestamp epoch), y el "% 60 * 100" lo convertia en el segundo
 * actual del reloj: la barra duraba entre 0 y 5900 ms al azar, sin
 * relacion alguna con lo que tardaba la pagina.
 *
 * Ahora el preloader se cierra cuando la pagina termina de cargar de
 * verdad, con un minimo corto para que no parpadee.
 */

(function () {
	'use strict';

	var MINIMO_VISIBLE = 400;   // evita el parpadeo en cargas instantaneas
	var MAXIMO_ESPERA  = 4000;  // red de seguridad si algun recurso se cuelga

	var inicio   = Date.now();
	var cerrado  = false;

	var $barra   = $('#bar1');
	var $porcien = $('#percent1');

	// Avance suave hasta 90%: el 100% se pinta al cerrar, para que la
	// barra no quede llena mientras la pagina sigue cargando.
	var progreso = 0;
	var timer = setInterval(function () {
		if (progreso >= 90) { return; }
		progreso += Math.max(1, Math.round((90 - progreso) / 12));
		pintar(progreso);
	}, 40);

	function pintar(valor) {
		$barra.css('width', valor + '%');
		$porcien.text(valor + '%');
	}

	function cerrar() {
		if (cerrado) { return; }
		cerrado = true;

		clearInterval(timer);
		pintar(100);

		var transcurrido = Date.now() - inicio;
		var restante = Math.max(0, MINIMO_VISIBLE - transcurrido);

		setTimeout(function () {
			$('.pre-loader').fadeOut(250);
		}, restante);
	}

	// Si la pagina ya termino de cargar, cerrar de inmediato.
	if (document.readyState === 'complete') {
		cerrar();
	} else {
		$(window).on('load', cerrar);
	}

	// Nunca dejar la pantalla de carga colgada indefinidamente.
	setTimeout(cerrar, MAXIMO_ESPERA);
})();
