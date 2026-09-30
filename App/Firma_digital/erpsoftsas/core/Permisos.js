/*
 * PERMISOS DEL ROL EN PANTALLA (panel de Roles, 2026-09-29)
 *
 * Cada accion tiene un interruptor en el panel de Roles ('ica.firmar',
 * 'alcaldia.cese'...). Lo que tiene prendido el rol de la sesion lo trae el
 * login (class.permisos.php funcion 6) y queda en localStorage.erpPermisos:
 *
 *   erpPuede('ica.firmar')                 ¿tiene ese interruptor?
 *   erpPuede('ica.editar ica.presentar')   ¿alguno de estos?
 *   ErpPermisos.gestionaOtros()            ¿trabaja sobre cualquier
 *                                          contribuyente? (barra "Gestionando a")
 *
 * Esto NO es la seguridad: el servidor revisa cada accion (PermisosRol). Es
 * para no mostrar botones que van a rebotar. El administrador puede todo.
 *
 * Cada pantalla vuelve a pedir los permisos al cargar (refrescar): si el
 * administrador cambio el rol, se nota sin tener que cerrar sesion.
 */
var ErpPermisos = (function () {
    var LLAVE = 'erpPermisos';

    function leer() {
        try {
            var p = JSON.parse(localStorage.getItem(LLAVE));
            if (p && Array.isArray(p.claves)) { return p; }
        } catch (e) { /* sin permisos guardados */ }
        return null;
    }

    var datos = leer();

    /*
     * Sin permisos guardados (una sesión abierta antes de esta versión, otro
     * navegador, datos borrados) se piden AQUÍ, en espera, antes de que corran
     * los scripts de la pantalla: si llegaran después, cada pantalla ya habría
     * escondido sus botones creyendo que el rol no puede nada (pasó en el RIT:
     * sin Guardar ni Firmar). Solo ocurre esa primera vez; después están en
     * localStorage y se refrescan sin esperar (refrescar()).
     */
    if (!datos && localStorage.getItem('id_Usuario')) {
        try {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '../business/controller/class.permisos.php', false);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('funcion=6');
            var r = JSON.parse(xhr.responseText);
            if (r && r.ok == 1 && r.datos && Array.isArray(r.datos.claves)) {
                datos = r.datos;
                try { localStorage.setItem(LLAVE, JSON.stringify(datos)); } catch (e) { /* modo privado */ }
            }
        } catch (e) { /* sin red o sin sesión: el menú queda en Inicio */ }
    }

    function lista(claves) {
        if (Array.isArray(claves)) { return claves; }
        return String(claves == null ? '' : claves).split(/[\s,]+/).filter(Boolean);
    }

    /** ¿Tiene ALGUNO de estos interruptores? (texto con espacios o arreglo) */
    function puede(claves) {
        if (!datos) { return false; }
        if (datos.admin) { return true; }
        return lista(claves).some(function (c) { return datos.claves.indexOf(c) !== -1; });
    }

    function guardar(p) {
        datos = p;
        try { localStorage.setItem(LLAVE, JSON.stringify(p)); } catch (e) { /* modo privado */ }
    }

    /**
     * Trae del servidor los permisos vigentes. Resuelve {cambio, datos}; si
     * no hay sesion, el aviso global de dist/menu.php lleva al login.
     */
    function refrescar(url) {
        return $.ajax({
            url: url || '../business/controller/class.permisos.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 6 }
        }).then(function (r) {
            if (r && r.ok == 1 && r.datos && Array.isArray(r.datos.claves)) {
                var antes = JSON.stringify(datos);
                guardar(r.datos);
                return { cambio: antes !== JSON.stringify(r.datos), datos: r.datos };
            }
            return { cambio: false, datos: datos };
        });
    }

    return {
        puede: puede,
        datos: function () { return datos; },
        hay: function () { return !!datos; },
        guardar: guardar,
        refrescar: refrescar,
        esAdmin: function () { return !!(datos && datos.admin); },
        esAlcaldia: function () { return !!(datos && datos.alcaldia); },
        gestionaOtros: function () { return !!(datos && (datos.admin || datos.gestiona)); },
        tipo: function () { return datos ? datos.tipo : ''; }
    };
})();

window.erpPuede = ErpPermisos.puede;

/*
 * Compatibilidad: las pantallas preguntan todavia por el "boton" de antes
 * (311 crear, 312 editar, 313 estado, 1639, 1640, 27/28/29...), que depende
 * de la pantalla. Aqui se traduce al interruptor que ese boton significa EN
 * ESA PANTALLA. Lo que no esta en la tabla se pregunta al servidor como antes
 * (pantallas que este sistema ya no usa).
 */
var EQUIVALENCIAS_BOTON = {
    'actividadescomercio.php': { 311: 'parametros.actividades', 312: 'parametros.actividades', 313: 'parametros.actividades' },
    'conceptos.php':           { 311: 'parametros.conceptos',   312: 'parametros.conceptos',   313: 'parametros.conceptos' },
    'grupotarifa.php':         { 311: 'parametros.grupos',      312: 'parametros.grupos',      313: 'parametros.grupos' },
    'contribuyentes.php':      { 1639: 'alcaldia.contribuyentes.editar', 312: 'alcaldia.contribuyentes.editar', 313: 'alcaldia.contribuyentes.editar' },
    'usuario.php':             { 27: 'usuarios.editar', 28: 'usuarios.editar', 29: 'usuarios.estado' },
    // El formulario de establecimiento (propio y sus copias en el RIT y el ICA).
    'establecimientos.php':    { 1640: 'establecimientos.editar' },
    'icawebrit.php':           { 1640: 'establecimientos.editar' },
    'icawebpresentar.php':     { 1640: 'establecimientos.editar' },
    'icawebconsultar.php':     { 1640: 'establecimientos.editar' }
};

class Permisos {

    constructor() { }

    getPermisos(idRol, idBoton) {
        var pagina = (location.pathname.split('/').pop() || '').toLowerCase();
        var clave = (EQUIVALENCIAS_BOTON[pagina] || {})[idBoton];
        if (clave && ErpPermisos.hay()) {
            return Promise.resolve({ ok: ErpPermisos.puede(clave) ? 1 : 0, clave: clave });
        }
        return $.ajax({
            url: '../business/controller/class.permisos.php',
            data: {funcion : 3, id_rol: idRol, id_boton : idBoton},
            dataType: "json",
            type: "POST"
        });
    }

    getvalidarSesison() {

        if(localStorage.getItem('id_Usuario') == null ){
            window.location = '../index.php';
        }

    }

}
const _permisos = new Permisos();

_permisos.getvalidarSesison();
