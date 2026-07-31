// Declaración de variables
'use strict';

var idRol = sessionStorage.getItem('id_Rol');
console.log('idRol ', sessionStorage)
class BaseCaja {

    constructor() {}

    /**
     * crearBaseCaja: Método para abrir modal de creación
     */
    async crearBaseCaja() {
        var permiso = await _permisos.getPermisos(idRol, 1355);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {

            $("#bace_IdCaja").val('');
            $("#bace_IdVendedor").val('');
            $("#bace_Base").val('');            
            $("#txtBaseCaja").val('');
            
            $("#formBaseCaja").attr('action', 'javascript:bod.postBaseCaja();');
            $("#modal_footerBaseCaja").empty();
            $("#modal_footerBaseCaja").append(
                '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                '<span class="ti-close"></span> Cancelar' +
                '</button>' +
                '<button type="submit" class="btn btn-success btn-pill">' +
                '<span class="ti-plus"></span> Crear' +
                '</button>'
            );

            $("#modal-BaseCaja").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de BaseCajas 
     * @param type $arrFilter: Listado de obejtos de tipo BaseCaja
     */
    draw_table_documents(arrFilter) {

        $("#tblBaseCaja").DataTable().destroy();
        $("#tbodyBaseCaja").empty();
        for (let bod of arrFilter) {
            if (bod.bace_Cierre == 1) {
                var titulo = "Base de Caja Cerrada";
            } else {
                var titulo = "Base de Caja Abierta";
            }

            $('#tbodyBaseCaja').append(
                '<tr>' +
                '<td>' +
                bod.strNombreCaja +
                '</td>' +

                '<td>' +
                '$ ' +Number(parseInt(bod.bace_Base).toFixed(0)).toLocaleString('es-CO') +
                '</td>' +

                '<td>' +
                titulo +
                '</td>' +

                '<td align="center">' +
                '<button type="button" class="mb-1 btn btn-social-icon btn-warning " title="Editar Base" style="margin-right:5px" onclick="javascript:bod.getBaseCajaById(' + bod.bace_Id + ')">' +
                '<i class="dw dw-edit2"></i>' +
                '</button>' +

                '</td>' +
                '</tr>'
            );
        }
        bod.init_table();
    }

    /**
     * init_table: Método para asignar la
     * propiedad DataTable() a la tabla de roles
     */
    init_table() {
        $('.data-table').DataTable({
            scrollCollapse: true,
            autoWidth: false,
            responsive: true,
            columnDefs: [{
                targets: "datatable-nosort",
                orderable: false,
            }],
            "lengthMenu": [
                [10, 25, 50, -1],
                [10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'Base de Cajas Abiertas',
                "info": 'Mostrando _START_ a _END_ de _TOTAL_ Entradas',
                'infoEmpty': 'Mostrando 0 to 0 of 0 Entradas',
                'infoFiltered': '(Filtrado de _MAX_ total entradas)',
                'infoPostFix': '',
                'thousands': ',',
                'lengthMenu': 'Mostrar _MENU_ Entradas',
                'loadingRecords': 'Cargando...',
                'processing': 'Procesando...',
                'search': 'Buscar:',
                'searchPlaceholder': 'Buscar....',
                'zeroRecords': 'Sin resultados encontrados',
                'paginate': {
                    'first': 'Primero',
                    'last': 'Último',
                    'next': 'Siguiente',
                    'previous': 'Anterior',
                },
                paginate: {
                    next: '<i class="ion-chevron-right"></i>',
                    previous: '<i class="ion-chevron-left"></i>'
                }
            },


        });
    }

    /**
     * getBaseCaja: Método para consultar las
     * BaseCajas
     */
    getBaseCaja() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
         
         var idRol = sessionStorage.getItem('id_Rol');
         var idUsuario = sessionStorage.getItem('id_Usuario');

        $.ajax({
            url: '../business/controller/class.baseCaja.php',
            data: { funcion: 3, bace_Cierre: 0, idRol: idRol, bace_IdVendedor: idUsuario},
            dataType: "json",
            type: "POST",
            success: function(arr) {

                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    bod.draw_table_documents(arr.datos);
                } else {
                    bod.init_table();
                    /* swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo consultar las BaseCajas',
                    }); */
                }
                /*  $("#estado").append('<option value="">Selecione una opción</option>');
                 arrayDocs = arr;
                 $.each(arr, function (k, v){
                     $("#estado").append('<option value="'+v['ESTDOC_Id']+'">'+v['ESTDOC_Nombre']+'</option>');
                 });  */

            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * getBaseCajaById: Método para consultar la 
     * información de una BaseCaja
     * @param type $id: llave primaria de la tabla BaseCaja
     */
    async getBaseCajaById(id) {
        var permiso = await _permisos.getPermisos(idRol, 1356);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.baseCaja.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {

                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#bace_IdCaja").val(datos.bace_IdCaja);
                            $("#bace_IdVendedor").val(datos.bace_IdVendedor);
                            $("#bace_Base").val(datos.bace_Base);
                        }

                        $("#formBaseCaja").attr('action', 'javascript:bod.putBaseCaja(' + id + ');');
                        $("#modal_footerBaseCaja").empty();
                        $("#modal_footerBaseCaja").append(
                            '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                            '<span class="ti-close"></span> Cancelar' +
                            '</button>' +
                            '<button type="submit" class="btn btn-success btn-pill">' +
                            '<span class="ti-reload"></span> Actualizar' +
                            '</button>'
                        );

                        $("#modal-BaseCaja").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información de la BaseCaja',
                        });
                    }

                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                    //location.href = "../../login.html";
                }
            });
        }
    }

    /**
     * putBaseCaja: Método para actualizar la 
     * información de una BaseCaja
     * @param type $id: llave primaria de la tabla BaseCaja
     */
    putBaseCaja(id) {
        var caja = $("#bace_IdCaja").val();
        var vendedor = $("#bace_IdVendedor").val();
        var base = $("#bace_Base").val();

        $.ajax({
            url: '../business/controller/class.baseCaja.php',
            data: { funcion: 2, id: id , bace_IdCaja: caja,  bace_IdVendedor: vendedor, bace_Base: base},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                /*  $('#loading').hide();
                 $('#wrapper').removeClass('body-load'); */
                if (arr.ok == 1) {

                    $("#modal-BaseCaja").modal('hide');
                    bod.getBaseCaja();
                    swal({
                        type: 'success',
                        title: 'BaseCaja actualizada',
                        text: 'BaseCaja actualizada exitosamente',
                    });

                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'BaseCaja Abierta',
                        text: 'Ya existe una Base de Caja Abierta',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la BaseCaja',
                    });
                }
                /*  $("#estado").append('<option value="">Selecione una opción</option>');
                 arrayDocs = arr;
                 $.each(arr, function (k, v){
                     $("#estado").append('<option value="'+v['ESTDOC_Id']+'">'+v['ESTDOC_Nombre']+'</option>');
                 });  */

            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }


    
    /**
     * getImpuesto: Método para consultar los Vendedores
     */
    getVendedores() {
        
        var idRol = sessionStorage.getItem('id_Rol');
        var idUsuario = sessionStorage.getItem('id_Usuario');

        $.ajax({
            url: '../business/controller/class.usuarios.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                if (arr.ok == 1) {
                    if(idRol == 1){
                        $("#bace_IdVendedor").empty();
                        $("#bace_IdVendedor").append('<option value="">Seleccion una opción</option>');
                        $.each(arr.datos, function(k, v) {
                            if(v['usu_Id'] > 1){
                                $("#bace_IdVendedor").append('<option value="' + v['usu_Id'] + '">' + v['usu_Nombre'] + '</option>');
                            }
                        });
                    }else{
                        $("#bace_IdVendedor").empty();                    
                        $.each(arr.datos, function(k, v) {
                            if(v['usu_Id'] == idUsuario ){
                                $("#bace_IdVendedor").append('<option value="' + v['usu_Id'] + '">' + v['usu_Nombre'] + '</option>');
                            }
                        });
                    }
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    
    /**
     * getImpuesto: Método para consultar las Cajas
     */
    getCajas() {
        
        var idRol = sessionStorage.getItem('id_Rol');
        var idCaja = sessionStorage.getItem('id_caja');

        $.ajax({
            url: '../business/controller/class.sedesEmpresaCajas.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                if (arr.ok == 1) {
                    if(idRol == 1){
                        $("#bace_IdCaja").empty();
                        $("#bace_IdCaja").append('<option value="">Seleccion una opción</option>');
                        $.each(arr.datos, function(k, v) {
                            $("#bace_IdCaja").append('<option value="' + v['seemca_Id'] + '">' + v['seemca_Nombre'] + '</option>');
                        });
                    }else{
                        $("#bace_IdCaja").empty();                    
                        $.each(arr.datos, function(k, v) {
                            if(v['seemca_Id'] == idCaja ){
                                $("#bace_IdCaja").append('<option value="' + v['seemca_Id'] + '">' + v['seemca_Nombre'] + '</option>');
                            }
                        });
                    }
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });

    }


    /**
     * postBaseCaja: Método para crear BaseCajas
     */
    postBaseCaja() {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load'); */

        var caja = $("#bace_IdCaja").val();
        var vendedor = $("#bace_IdVendedor").val();
        var base = $("#bace_Base").val();

        $.ajax({
            url: '../business/controller/class.baseCaja.php',
            data: { funcion: 1, bace_IdCaja: caja, bace_IdVendedor: vendedor, bace_Base: base },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                /*  $('#loading').hide();
                 $('#wrapper').removeClass('body-load'); */
                if (arr.ok == 1) {

                    $("#modal-BaseCaja").modal('hide');
                    swal({
                        type: 'success',
                        title: 'BaseCaja creada',
                        text: 'Base de Caja creada exitosamente',
                    });
                    bod.getBaseCaja();
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Caja Abierta',
                        text: 'Ya existe una Base de Caja Abierta',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la  BaseCaja',
                    });
                }
                /*  $("#estado").append('<option value="">Selecione una opción</option>');
                 arrayDocs = arr;
                 $.each(arr, function (k, v){
                     $("#estado").append('<option value="'+v['ESTDOC_Id']+'">'+v['ESTDOC_Nombre']+'</option>');
                 });  */

            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * putEstados: Método para cambiar el estados 
     * de las BaseCajas
     * @param type $id_BaseCaja: llave primaria de la tabla BaseCaja
     * @param type $estado: estado actual de la BaseCaja
     */
    async putEstados(id_BaseCaja, estado) {
        var permiso = await _permisos.getPermisos(idRol, 313);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar la BaseCaja?";
                var subtitle = "Una vez inactivado la BaseCaja, no podrá asignarla a un producto";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la BaseCaja?";
                var subtitle = "Una vez activado la BaseCaja, podrá ser asignarla a un producto";
                var button = "Sí, activar";
                var est = 1;
            }
            swal({
                title: title,
                text: subtitle,
                type: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: button,
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.value) {
                    $('#loading').show();
                    $('#wrapper').addClass('body-load');
                    $.ajax({
                        url: '../business/controller/class.baseCaja.php',
                        data: { funcion: 4, id: id_BaseCaja, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                bod.getBaseCaja();
                                swal({
                                    type: 'success',
                                    title: 'BaseCaja actualizada',
                                    text: 'BaseCaja actualizada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar la BaseCaja',
                                });
                            }
                            /*  $("#estado").append('<option value="">Selecione una opción</option>');
                                arrayDocs = arr;
                                $.each(arr, function (k, v){
                                    $("#estado").append('<option value="'+v['ESTDOC_Id']+'">'+v['ESTDOC_Nombre']+'</option>');
                                });  */

                        },
                        error: function(XMLHttpRequest, textStatus, errorThrown) {
                            console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                            //location.href = "../../login.html";
                        }
                    });
                }
            })
        }
    }

    /**
     * BaseCajaActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    BaseCajaActivo() {
        $(".Menuactivo").removeClass('expand');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DAdministracion").addClass('expand');
        $("#DAdministracion").addClass('active');
        $("#SubAdminsitracion").addClass('show');
        $("#SubMenuBaseCaja").addClass('active');
    }

}
const bod = new BaseCaja();



bod.getBaseCaja();
bod.BaseCajaActivo();
bod.getVendedores();
bod.getCajas();
