var idRol = localStorage.getItem('id_Rol');

class Menu {

    constructor() {}

    async validarIngreso(idper, valor) {

        console.log('per ', idper, ' rol ', idRol)
        var permiso = await _permisos.getPermisos(idRol, idper);
        console.log('permiso ', permiso)

        if (permiso.ok != 1) {
            menu.mensajeError();
        } else {
            switch (valor) {
                case 1:
                    window.location = 'usuario.php';
                    break;
                case 2:
                    window.location = 'rol.php';
                    break;
                case 3:
                    window.location = 'actividadesComercio.php';
                    break;
                case 4:
                    window.location = 'contribuyentes.php';
                    break;
                case 5:
                    //window.location = 'tasasInteres.php';
                    window.location = 'grupoTarifa.php';
                    break;
                case 6:
                    window.location = 'conceptos.php';
                    break;
                case 7:
                    window.location = 'establecimientos.php';
                    //window.location = 'dasboard.php';
                    break;
                case 8:
                    window.location = 'documentosRadicados.php';
                    break;
                case 9:
                    window.location = 'informesGestorRadicados.php';
                    break;
                case 10:
                    window.location = 'informesModulos.php';
                    break;
                case 11:
                    // Recaudo por codigo de barras (archivo Asobancaria).
                    window.location = 'recaudo.php';
                    break;
                
                case 100:
                    window.location = 'consultasPazySalvoPredial.php';
                    break;

                case 101:
                    window.location = 'icaWebRit.php';
                    break;
                case 102:
                    window.location = 'icaWebConsultar.php';
                    break;
                case 103:
                    window.location = 'icaWebPresentar.php';
                    break;
                case 104:
                    window.location = 'reteicaConsultar.php';
                    break;
                case 105:
                    window.location = 'reteicaPresentar.php';
                    break;
                case 106:
                    window.location = 'autoretencionConsultar.php';
                    break;
                case 107:
                    window.location = 'autoretencionPresentar.php';
                    break;
                

                default:
                    window.location = 'dasboard.php';
                    break;
            }
        }

    }

       mensajeError() {
        swal({
            type: 'warning',
            title: 'Error de privilegios',
            text: 'Usted no tiene los privilegios para realizar esta acción,' +
                'para obtenerlos comuniquese con el admininstrador del sistema',
        });
    }


    ocultarTodoElMenu() {
        // Oculta módulos y submódulos completos
        $("#accordion-menu > li").hide();
        $(".submenu li").hide();
    }


    mostrarMenuPorPermisos() {
        let permisos = localStorage.getItem('permisosRol');
        let id_Rol = localStorage.getItem('id_Rol');

        try {
            permisos = JSON.parse(permisos);
        } catch (e) {
            permisos = null;
        }

        /*
         * Sin permisos en localStorage no se adivina: se deja solo "Inicio".
         *
         * Antes esto reventaba en el forEach de mas abajo, y como el menu
         * ahora nace oculto por CSS, una excepcion lo habria dejado tapado
         * para siempre. Pasa si alguien entra con la sesion viva pero el
         * localStorage limpio (otro navegador, modo privado, borrar datos).
         */
        if (id_Rol != 1 && !Array.isArray(permisos)) {
            console.warn('menu: no hay permisos en localStorage; solo se muestra Inicio.');
            $("#accordion-menu > li").hide();
            $("#MInicio").show();
            return;
        }

        if(id_Rol == 1){
            // Mostrar todo el menú para el rol administrador
            $("#accordion-menu > li").show();
            $(".submenu li").show();
            return;
        }else{
            $("#accordion-menu > li").hide();
            $(".submenu li").hide();

            permisos.forEach(p => {
                if (p.per_Estado == 1) {
                    $(`.menu_${p.per_IdBoton}`).show();
                    $(`.menu_${p.per_IdBoton}`).closest("li.dropdown").show();
                }
            });

            // "Inicio" no es un modulo con permiso propio -es la portada, un
            // enlace directo a dashboard.php-, asi que no tiene ninguna clase
            // menu_XXXX que lo pueda revelar y quedaba oculto para todos los
            // roles menos el administrador. Se muestra siempre: si el usuario
            // llego hasta aqui ya tiene sesion, y sin esto los roles externos
            // se quedan sin forma de volver al tablero.
            $("#MInicio").show();
        }
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

$(document).ready(function () {
    /*
     * Sin setTimeout.
     *
     * Aqui habia una espera de 300ms antes de aplicar los permisos. Durante
     * esos 300ms el menu se veia ENTERO -incluidos los modulos de
     * administracion- y luego se recortaba. Era justo el destello que
     * reportó el cliente.
     *
     * La espera no hacia falta: los permisos los guarda login.js en
     * localStorage al iniciar sesion, asi que ya estan cuando carga
     * cualquier pantalla interna. Se aplica de una.
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
});
