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

        permisos = JSON.parse(permisos);
        console.log('Permisos desde localStorage:', permisos);  

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
        }
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

$(document).ready(function() {
    setTimeout(() => {
       menu.ocultarTodoElMenu();
       menu.mostrarMenuPorPermisos();
    }, 300);
});