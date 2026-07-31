var idRol = sessionStorage.getItem('id_Rol');

class Menu {

    constructor() {}

    getMenu() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $.ajax({
            url: '../business/controller/class.modulos.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log(arr);
                /*  $('#loading').hide();
                 $('#wrapper').removeClass('body-load');  */
                if (arr.ok == 1) {
                    //$("#sidebar-menu").empty(); 
                    $.each(arr.datos, function(k, v) {

                    });
                } else {
                    /* swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo consultar los roles',
                     }); */
                }

                $("#sidebar-menu").append(
                    '<li  class="has-sub Menuactivo" id="MenuConfiguracion">' +
                    '<a class="sidenav-item-link" href="javascript:void(0)"' +
                    'aria-expanded="false" aria-controls="configuracion" data-toggle="collapse" data-target="#configuracion" >' +
                    '<i class="mdi mdi-cogs"></i>' +
                    '<span class="nav-text">Administración</span><b class="caret"></b>' +
                    '</a>' +
                    '<ul  class="collapse showMenu"  id="configuracion"' +
                    'data-parent="#sidebar-menu">' +
                    '<div class="sub-menu">' +
                    '<li>' +
                    '<a class="subMenuactivo" id="SubMenuRol" href="rol.php">' +
                    '<i class="mdi mdi-account-multiple"></i>' +
                    '<span class="nav-text">Roles</span>' +
                    '</a>' +
                    '</li>' +
                    '<li>' +
                    '<a class="subMenuactivo" id="SubMenuUsuario" href="usuario.php">' +
                    '<i class="mdi mdi-account"></i>' +
                    '<span class="nav-text">Usuarios</span>' +
                    '</a>' +
                    '</li>' +
                    '</div>' +
                    '</ul>' +
                    '</li>' +
                    '<li  class="has-sub Menuactivo" id="MenuInventario">' +
                    '<a class="sidenav-item-link" href="javascript:void(0)"' +
                    'aria-expanded="false" aria-controls="inventario" data-toggle="collapse" data-target="#inventario" >' +
                    '<i class="mdi mdi-package-variant-closed"></i>' +

                    '<span class="nav-text">Inventario</span><b class="caret"></b>' +
                    '</a>' +
                    '<ul  class="collapse showMenu"  id="inventario"' +
                    'data-parent="#sidebar-menu">' +
                    '<div class="sub-menu">' +
                    '<li  class="subMenuactivo" id="SubMenuProducto">' +
                    '<a class="sidenav-item-link" href="rol.php">' +
                    '<i class="mdi mdi-buffer"></i>' +
                    '<span class="nav-text">Productos</span>' +
                    '</a>' +
                    '</li>' +
                    '<li class="subMenuactivo"  id="SubMenuNota">' +
                    '<a class="sidenav-item-link" href="usuario.php">' +
                    '<i class="mdi mdi-palette-swatch"></i>' +
                    '<span class="nav-text">Entradas/Salidas</span>' +
                    '</a>' +
                    '</li>' +
                    '<li class="subMenuactivo"  id="SubMenuBodega">' +
                    '<a class="sidenav-item-link" href="bodega.php">' +
                    '<i class="mdi mdi-package"></i>' +
                    '<span class="nav-text">Bodegas</span>' +
                    '</a>' +
                    '</li>' +
                    '</div>' +
                    '</ul>' +
                    '</li>'
                );
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

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
                    window.location = 'producto.php';
                    break;
                case 4:
                    window.location = 'nota.php';
                    break;
                case 5:
                    window.location = 'bodega.php';
                    break;
                case 6:
                    window.location = 'estados.php';
                    break;
                case 7:
                    window.location = 'factura.php';
                    break;
                case 8:
                    window.location = 'insumos.php';
                    break;
                case 9:
                    window.location = 'categoria.php';
                    break;
                case 10:
                    window.location = 'subCategoria.php';
                    break;
                case 11:
                    window.location = 'notaInsumos.php';
                    break;
                case 12:
                    window.location = 'cotizadorInsumos.php';
                    break;
                case 13:
                    window.location = 'ordenesInsumos.php';
                    break;
                case 14:
                    window.location = 'baseCaja.php';
                    break;
                case 15:
                    window.location = 'pagosCaja.php';
                    break;
                case 16:
                    window.location = 'cierreCaja.php';
                    break;
                case 17:
                    window.location = 'proveedores.php';
                    break;
                case 18:
                    window.location = 'clientes.php';
                    break;
                case 19:
                    window.location = 'formasPago.php';
                    break;
                case 20:
                    //window.open('https://app.aliaddo.com/Home', '_blank');
                    break;
                case 21:
                    window.location = 'facturaTotales.php';
                    break;
                case 22:
                    window.location = 'informes.php';
                    break;
                case 23:
                    window.location = 'informesPorModulos.php';
                    break;
                case 24:
                    window.location = 'notaTotales.php';
                    break;
                case 25:
                    window.location = 'listadoPredios.php';
                    break;
                case 26:
                    window.location = 'prediosDocumentos.php';
                    break;
                case 27:
                    window.location = 'generarDocumentosPredios.php';
                    break;
                case 28:
                    window.location = 'resolucion.php';
                    break;
                case 29:
                    window.location = 'configuracion.php';
                    break;
                case 30:
                    window.location = 'cuentasContables.php';
                    break;
                case 31:
                    window.location = 'informeCuentas.php';
                    break;
                case 32:
                    window.location = 'cuentasPorPagar.php';
                    break;
                case 33:
                    window.location = 'eventos.php';
                    break;
                case 34:
                    window.location = 'proveedoresEventos.php';
                    break;
                case 35:
                    window.location = 'actividadesEventos.php';
                    break;
                case 36:
                    window.location = 'ingresosEventos.php';
                    break;
                case 37:
                    window.location = 'egresosEventos.php';
                    break;
                case 38:
                    window.location = 'informesEventos.php';
                    break;
                case 39:
                    window.location = 'cuentasPorPagarP.php';
                    break;
                case 40:
                    window.location = 'cuentasPorPagarSaldadas.php';
                    break;
                case 41:
                    window.location = 'categoriasActividades.php';
                    break;
                case 42:
                    window.location = 'tiposPagos.php';
                    break;
                case 43:
                    window.location = 'subTiposPagos.php';
                    break;
                case 44:
                    window.location = 'informesSalidas.php';
                    break;
                case 45:
                    window.location = 'crearOrdenesMesas.php';        
                    break;
                case 46:
                    window.location = 'ordenesMesas.php';    
                    break;
                case 47:
                    window.location = 'mesasSedes.php';
                    break;
                case 48:
                    window.location = 'cuentasPorCobrar.php';
                    break;
                case 49:
                    window.location = 'informesMorosos.php';
                    break;
                case 50:
                    window.location = 'gestionMorosos.php';
                    break;
                case 51:
                    window.location = 'procesoFiscalizacion.php';
                    break;
                case 52:
                    window.location = 'informeMorosos.php';
					 break;
                case 53:
                    window.location = 'autoArchivo.php';
                    break;
                case 54:
                    window.location = 'hojadeVida.php';
                    break;
                case 55:
                    window.location = 'exogenas.php';
                    break;
                case 56:
                    window.location = 'informeExogenas.php';
                    break;
                case 57:
                    window.location = 'consultaExogenas.php';
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


}

const menu = new Menu();