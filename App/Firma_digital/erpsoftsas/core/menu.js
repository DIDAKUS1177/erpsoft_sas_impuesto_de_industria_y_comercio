var idRol = localStorage.getItem('id_Rol');

/*
 * QUIEN ENTRA A CADA PANTALLA (interruptores del panel de Roles, 2026-09-29)
 *
 * Antes el menu miraba numeros de boton (1639, 1640, 1641...) que no decian
 * que accion permitian, y el servidor decidia por el numero del rol. Ahora el
 * menu, la guardia de pagina y el servidor miran los mismos interruptores
 * ('ica.ver', 'alcaldia.recaudo.cargar'...). Varios separados por espacio =
 * "alguno de estos".
 *
 * Entrar escribiendo la direccion tampoco salta el permiso (guardiaDePagina),
 * y aunque lo saltara, el servidor rebota cada accion.
 */
var PERMISO_PAGINA = {
    'contribuyentes.php':            'alcaldia.contribuyentes.ver',
    'establecimientostodos.php':     'alcaldia.establecimientos.ver',
    'recaudo.php':                   'alcaldia.recaudo.cargar alcaldia.recaudo.asignar',
    'icawebrit.php':                 'rit.ver',
    'establecimientos.php':          'establecimientos.ver',
    'icawebpresentar.php':           'ica.editar ica.firmar ica.presentar',
    'icawebconsultar.php':           'ica.ver',
    'reteicapresentar.php':          'reteica.editar reteica.firmar reteica.presentar',
    'reteicaconsultar.php':          'reteica.ver',
    'autoretencionpresentar.php':    'autorreteica.editar autorreteica.firmar autorreteica.presentar',
    'autoretencionconsultar.php':    'autorreteica.ver',
    'configuracion.php':             'parametros.municipio',
    'actividadescomercio.php':       'parametros.ver',
    'conceptos.php':                 'parametros.ver',
    'grupotarifa.php':               'parametros.ver',
    'consultaspazysalvopredial.php': 'predial.consultar',
    'usuario.php':                   'usuarios.ver',
    'rol.php':                       'roles.ver'
};

/* Pantallas de UN contribuyente: la Alcaldia solo entra gestionando a alguien. */
var PAGINAS_DEL_CONTRIBUYENTE = ['icawebrit.php', 'establecimientos.php', 'icawebpresentar.php',
    'icawebconsultar.php', 'reteicapresentar.php', 'reteicaconsultar.php',
    'autoretencionpresentar.php', 'autoretencionconsultar.php'];

/* A donde lleva cada opcion del menu (segundo argumento de validarIngreso). */
var DESTINO_MENU = {
    1: 'usuario.php', 2: 'rol.php', 3: 'actividadesComercio.php', 4: 'contribuyentes.php',
    5: 'grupoTarifa.php', 6: 'conceptos.php', 7: 'establecimientos.php', 8: 'documentosRadicados.php',
    9: 'informesGestorRadicados.php', 10: 'informesModulos.php',
    11: 'recaudo.php',                 // recaudo por codigo de barras (archivo Asobancaria)
    12: 'configuracion.php',           // municipio y bancos
    13: 'establecimientosTodos.php',   // directorio de la Alcaldia
    100: 'consultasPazySalvoPredial.php',
    101: 'icaWebRit.php', 102: 'icaWebConsultar.php', 103: 'icaWebPresentar.php',
    104: 'reteicaConsultar.php', 105: 'reteicaPresentar.php',
    106: 'autoretencionConsultar.php', 107: 'autoretencionPresentar.php'
};

class Menu {

    constructor() {}

    paginaActual() {
        return (location.pathname.split('/').pop() || '').toLowerCase();
    }

    /** ¿La sesion puede entrar a esta pantalla? */
    puedeEntrar(pagina) {
        pagina = String(pagina || '').toLowerCase();
        var necesita = PERMISO_PAGINA[pagina];
        if (necesita && !erpPuede(necesita)) { return false; }
        // Un funcionario sin "Gestionar a un contribuyente" no tiene sobre
        // quien trabajar en las pantallas del contribuyente.
        if (PAGINAS_DEL_CONTRIBUYENTE.indexOf(pagina) !== -1
            && ErpPermisos.esAlcaldia() && !ErpPermisos.gestionaOtros()) {
            return false;
        }
        return true;
    }

    /**
     * Al empezar a gestionar a un contribuyente: la primera de sus pantallas
     * que el rol puede usar (la preferida si puede). Un funcionario con solo
     * "Ver declaraciones de ICA" no tiene por qué caer en el RIT, que no ve.
     * null si no puede ninguna.
     */
    pantallaDelContribuyente(preferida) {
        var orden = [preferida, 'icaWebRit.php', 'establecimientos.php', 'icaWebPresentar.php',
                     'icaWebConsultar.php', 'reteicaPresentar.php', 'reteicaConsultar.php',
                     'autoretencionPresentar.php', 'autoretencionConsultar.php'];
        for (var i = 0; i < orden.length; i++) {
            if (orden[i] && this.puedeEntrar(orden[i])) { return orden[i]; }
        }
        return null;
    }

    /**
     * Clic en el menu. `permiso` es el interruptor (o varios); si llega un
     * numero es un boton de antes y se pregunta al servidor como antes.
     */
    async validarIngreso(permiso, valor) {
        var destino = DESTINO_MENU[valor] || 'dashboard.php';
        var ok;
        if (/^\d+$/.test(String(permiso))) {
            var r = await _permisos.getPermisos(idRol, permiso);
            ok = r && r.ok == 1;
        } else {
            ok = erpPuede(permiso) && this.puedeEntrar(destino);
        }
        if (!ok) {
            menu.mensajeError();
            return;
        }
        window.location = destino;
    }

    mensajeError() {
        swal({
            type: 'warning',
            title: 'Sin permiso',
            text: 'Su rol no tiene permiso para esta opción. Si la necesita, pídasela al administrador del sistema.'
        });
    }

    /**
     * Si la pantalla no es para este rol, se sale de UNA -antes de que carguen
     * sus datos y rebote cada peticion- y el aviso sale en Inicio.
     */
    guardiaDePagina() {
        if (!ErpPermisos.hay()) { return; }   // se revisa cuando lleguen del servidor
        var pagina = this.paginaActual();
        if (this.puedeEntrar(pagina)) { return; }
        try { sessionStorage.setItem('avisoSinPermiso', pagina); } catch (e) { /* sin aviso */ }
        window.location.replace('dashboard.php');
    }

    avisoSinPermiso() {
        var pagina = null;
        try {
            pagina = sessionStorage.getItem('avisoSinPermiso');
            sessionStorage.removeItem('avisoSinPermiso');
        } catch (e) { /* sin aviso */ }
        if (!pagina) { return; }
        swal({
            type: 'warning',
            title: 'Sin permiso',
            text: 'Su rol no tiene permiso para entrar a esa pantalla. Si la necesita, pídasela al administrador del sistema.'
        });
    }

    ocultarTodoElMenu() {
        // Oculta módulos y submódulos completos
        $("#accordion-menu > li").hide();
        $(".submenu li").hide();
    }

    /**
     * Muestra lo que el rol puede usar. Cada <li> dice su interruptor en
     * data-permiso; un grupo con submenu se ve si alguno de los suyos se ve.
     * Las pantallas del contribuyente (data-contribuyente), para la Alcaldia,
     * son las de quien gestiona: ContribActivo (dist/menu.php) las muestra
     * solo con alguien elegido.
     */
    mostrarMenuPorPermisos() {
        var $menu = $("#accordion-menu");

        /*
         * Sin permisos guardados no se adivina: solo "Inicio" mientras llegan
         * del servidor (sesion abierta antes de esta version, otro navegador,
         * datos borrados). Un error aqui dejaria el menu tapado para siempre.
         */
        if (!ErpPermisos.hay()) {
            $menu.find("> li").hide();
            $("#MInicio").show();
            return;
        }

        var sinContribuyente = ErpPermisos.esAlcaldia() && !ErpPermisos.gestionaOtros();

        $menu.find("li[data-permiso]").each(function () {
            var $li = $(this);
            var ok = erpPuede($li.attr('data-permiso'));
            if (ok && sinContribuyente && $li.closest('#accordion-menu > li').is('[data-contribuyente]')) {
                ok = false;
            }
            $li.toggleClass('erp-permitido', ok).toggle(ok);
        });
        $menu.find("> li.dropdown").each(function () {
            var $sub = $(this).find(".submenu li[data-permiso]");
            if ($sub.length) {
                var ok = $sub.filter('.erp-permitido').length > 0;
                $(this).toggleClass('erp-permitido', ok).toggle(ok);
            }
        });

        // "Inicio" no tiene permiso propio: es la portada. Si llego hasta aqui
        // ya tiene sesion, y sin el no habria forma de volver al tablero.
        $("#MInicio").show();

        // El bloque del contribuyente y la barra "Gestionando a" (dist/menu.php).
        if (window.ContribActivo && ContribActivo.pintarBarra) { ContribActivo.pintarBarra(); }
    }

    /**
     * Botones fijos de las pantallas ("Crear ...") que dicen su interruptor en
     * data-permiso-crear: sin el, no se ofrecen.
     */
    aplicarBotonesDePermiso() {
        $('[data-permiso-crear]').each(function () {
            $(this).toggle(erpPuede($(this).attr('data-permiso-crear')));
        });
    }

    /**
     * Levanta el velo del menu.
     *
     * La hoja de estilos lo deja oculto (.menu-cargando) para que no se
     * alcance a ver lo que el usuario no tiene permitido mientras carga.
     * Se llama SIEMPRE al terminar de aplicar permisos, con o sin exito:
     * un menu que se queda tapado para siempre seria peor que el destello
     * que se quiso evitar.
     */
    revelarMenu() {
        $("#accordion-menu").removeClass("menu-cargando");
    }



    activarInicio() {
        this.limpiarMenu();
        $("#MInicio").addClass("active");
    }






    /* ============================================================
        RETE ICA
    ============================================================ */

    activarReteICADeclaraciones() {
        $("#accordion-menu li").removeClass("active show");
        $("#accordion-menu .submenu").css("display", "none");

        $("#MReteICA").addClass("active show");
        $("#SubReteICA").css("display", "block");
        $("#ReteICA_Declaraciones").addClass("active");
    }

    activarReteICAPresentar() {
        $("#accordion-menu li").removeClass("active show");
        $("#accordion-menu .submenu").css("display", "none");

        $("#MReteICA").addClass("active show");
        $("#SubReteICA").css("display", "block");
        $("#ReteICA_Presentar").addClass("active");
    }

    /* ============================================================
        AUTO RETENCIÓN
    ============================================================ */
    activarAutoRetDeclaraciones() {
        $("#accordion-menu li").removeClass("active show");
        $("#accordion-menu .submenu").css("display", "none");

        $("#MAutoretencion").addClass("active show");
        $("#SubAutoretencion").css("display", "block");
        $("#AutoRet_Declaraciones").addClass("active");
    }

    activarAutoRetPresentar() {
        $("#accordion-menu li").removeClass("active show");
        $("#accordion-menu .submenu").css("display", "none");

        $("#MAutoretencion").addClass("active show");
        $("#SubAutoretencion").css("display", "block");
        $("#AutoRet_Presentar").addClass("active");
    }

}

const menu = new Menu();

// Antes de que carguen los scripts de la pantalla: si no es para este rol,
// se sale sin que alcance a pedir sus datos.
menu.guardiaDePagina();

$(document).ready(function () {
    /*
     * Sin setTimeout: los permisos los guarda login.js en localStorage al
     * iniciar sesion, asi que ya estan cuando carga cualquier pantalla. (Hubo
     * una espera de 300 ms en la que el menu se veia ENTERO -incluidos los
     * modulos de administracion- y luego se recortaba: el destello que
     * reporto el cliente.)
     *
     * El try/finally garantiza que el menu se destape pase lo que pase: la
     * hoja de estilos lo deja oculto, y un error aqui lo dejaria invisible.
     */
    try {
        menu.ocultarTodoElMenu();
        menu.mostrarMenuPorPermisos();
    } catch (e) {
        console.error('menu: fallo aplicando permisos', e);
        $("#accordion-menu > li").hide();
        $("#MInicio").show();
    } finally {
        menu.revelarMenu();
    }
    menu.aplicarBotonesDePermiso();
    menu.avisoSinPermiso();

    // Los permisos vigentes, por si el administrador cambio el rol despues
    // del login. Si cambiaron, se repinta el menu y se revisa la pantalla; las
    // que pintan botones segun el permiso escuchan 'erp:permisos'.
    ErpPermisos.refrescar().then(function (r) {
        if (!r || !r.cambio) { return; }
        try { menu.mostrarMenuPorPermisos(); } finally { menu.revelarMenu(); }
        menu.aplicarBotonesDePermiso();
        menu.guardiaDePagina();
        $(document).trigger('erp:permisos', [r.datos]);
    }, function () { /* sin red: se queda con los guardados */ });
});
