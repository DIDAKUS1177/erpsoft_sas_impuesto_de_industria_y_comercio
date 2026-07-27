// Declaración de variables
'use strict';

var idRol = sessionStorage.getItem('id_Rol');
console.log('idRol ', sessionStorage)
class Bodega {

    constructor() {}

    /**
     * crearBodega: Método para abrir modal de creación
     */
    async crearBodega() {
        var permiso = await _permisos.getPermisos(idRol, 311);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {

            $("#txtBodega").val('');
            $("#pro_IdTipo").val('');
            $("#formBodega").attr('action', 'javascript:bod.postBodega();');
            $("#modal_footerBodega").empty();
            $("#modal_footerBodega").append(
                '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                '<span class="ti-close"></span> Cancelar' +
                '</button>' +
                '<button type="submit" class="btn btn-success btn-pill">' +
                '<span class="ti-plus"></span> Crear' +
                '</button>'
            );

            $("#modal-Bodega").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de bodegas 
     * @param type $arrFilter: Listado de obejtos de tipo bodega
     */
    draw_table_documents(arrFilter) {

        $("#tblBodega").DataTable().destroy();
        $("#tbodyBodega").empty();
        for (let bod of arrFilter) {
            if (bod.bod_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Bodega";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Bodega";
            }
            $('#tbodyBodega').append(
                '<tr>' +
                '<td>' +
                bod.bod_Nombre +
                '</td>' +

                '<td align="center">' +
                '<button type="button" class="mb-1 btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar Bodega" style="margin-right:5px" onclick="javascript:bod.getBodegaById(' + bod.bod_Id + ')">' +
                '<i class="dw dw-edit2"></i>' +
                '</button>' +
                '<button type="button" class="mb-1 btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  style="margin-right:5px" onclick="javascript:bod.putEstados(' + bod.bod_Id + ',' + bod.bod_Estado + ')">' +
                '<i class="' + icono + '"></i>' +
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
                'emptyTable': 'Roles registrados',
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
     * getBodega: Método para consultar las
     * bodegas
     */
    getBodega() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $.ajax({
            url: '../business/controller/class.bodega.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {

                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    bod.draw_table_documents(arr.datos);
                } else {
                    bod.init_table();
                }
           

            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * getBodegaById: Método para consultar la 
     * información de una bodega
     * @param type $id: llave primaria de la tabla bodega
     */
    async getBodegaById(id) {
        var permiso = await _permisos.getPermisos(idRol, 312);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.bodega.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {

                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#txtBodega").val(datos.bod_Nombre);
                            $("#pro_IdTipo").val(datos.bod_IdTipo);
                        }

                        $("#formBodega").attr('action', 'javascript:bod.putBodega(' + id + ');');
                        $("#modal_footerBodega").empty();
                        $("#modal_footerBodega").append(
                            '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                            '<span class="ti-close"></span> Cancelar' +
                            '</button>' +
                            '<button type="submit" class="btn btn-success btn-pill">' +
                            '<span class="ti-reload"></span> Actualizar' +
                            '</button>'
                        );

                        $("#modal-Bodega").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información de la bodega',
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
     * putBodega: Método para actualizar la 
     * información de una bodega
     * @param type $id: llave primaria de la tabla bodega
     */
    putBodega(id) {
        var nombre = $("#txtBodega").val();
        var tipo = $("#pro_IdTipo").val();
        $.ajax({
            url: '../business/controller/class.bodega.php',
            data: { funcion: 2, nombre: nombre, id: id , tipo: tipo},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                /*  $('#loading').hide();
                 $('#wrapper').removeClass('body-load'); */
                if (arr.ok == 1) {

                    $("#modal-Bodega").modal('hide');
                    bod.getBodega();
                    swal({
                        type: 'success',
                        title: 'Bodega actualizada',
                        text: 'Bodega actualizada exitosamente',
                    });

                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Bodega duplicada',
                        text: 'Ya existe una odega con el mismo nombre',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la bodega',
                    });
                }

            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                
            }
        });
    }

    /**
     * postBodega: Método para crear bodegas
     */
    postBodega() {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load'); */

        var nombre = $("#txtBodega").val();
        var tipo = $("#pro_IdTipo").val();

        $.ajax({
            url: '../business/controller/class.bodega.php',
            data: { funcion: 1, nombre: nombre, tipo: tipo },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                /*  $('#loading').hide();
                 $('#wrapper').removeClass('body-load'); */
                if (arr.ok == 1) {

                    $("#modal-Bodega").modal('hide');
                    swal({
                        type: 'success',
                        title: 'Bodega creada',
                        text: 'Bodega creada exitosamente',
                    });
                    bod.getBodega();
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Bodega duplicada',
                        text: 'Ya existe una bodega con el mismo nombre',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la  bodega',
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
     * de las bodegas
     * @param type $id_bodega: llave primaria de la tabla bodega
     * @param type $estado: estado actual de la bodega
     */
    async putEstados(id_bodega, estado) {
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
                var title = "¿Está seguro de inactivar la bodega?";
                var subtitle = "Una vez inactivado la bodega, no podrá asignarla a un producto";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la bodega?";
                var subtitle = "Una vez activado la bodega, podrá ser asignarla a un producto";
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
                        url: '../business/controller/class.bodega.php',
                        data: { funcion: 4, id: id_bodega, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                bod.getBodega();
                                swal({
                                    type: 'success',
                                    title: 'Bodega actualizada',
                                    text: 'Bodega actualizada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar la bodega',
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
     * BodegaActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    BodegaActivo() {
        $(".Menuactivo").removeClass('expand');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');
        
        $("#DConfiguracion").addClass('expand active');
        $("#DConfiguracion").addClass('active');
        $("#SubConfiguracion").addClass('show');
        $("#SubMenuBodega").addClass('active');
    }

}
const bod = new Bodega();



bod.getBodega();
bod.BodegaActivo();