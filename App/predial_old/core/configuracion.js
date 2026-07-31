// Declaración de variables
'use strict';

var idRol = sessionStorage.getItem('id_Rol');
console.log('idRol ', sessionStorage)
class Configuracion {

    constructor() {}

    /**
     * crearBodega: Método para abrir modal de creación
     */
    async crearConfiguracion() {
        var permiso = await _permisos.getPermisos(idRol, 311);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {

            $("#con_NombreDirector").val('');
            $("#con_Resolucion").val('');
            $("#formConfiguracion").attr('action', 'javascript:bod.postConfiguracion();');
            $("#modal_footerConfiguracion").empty();
            $("#modal_footerConfiguracion").append(
                '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                '<span class="ti-close"></span> Cancelar' +
                '</button>' +
                '<button type="submit" class="btn btn-success btn-pill">' +
                '<span class="ti-plus"></span> Crear' +
                '</button>'
            );

            $("#modal-Configuracion").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de bodegas 
     * @param type $arrFilter: Listado de obejtos de tipo bodega
     */
    draw_table_documents(arrFilter) {

        $("#tblConfiguracion").DataTable().destroy();
        $("#tbodyConfiguracion").empty();
        for (let bod of arrFilter) {
            if (bod.con_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Configuracion";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Configuracion";
            }
            $('#tbodyConfiguracion').append(
                '<tr>' +
                '<td>' +
                bod.con_NombreDirector +
                '</td>' +

                '<td>' +
                bod.con_Resolucion +
                '</td>' +

                '<td align="center">' +
                '<button type="button" class="mb-1 btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar Director" style="margin-right:5px" onclick="javascript:bod.getConfiguracionById(' + bod.con_Id + ')">' +
                '<i class="dw dw-edit2"></i>' +
                '</button>' +
                '<button type="button" class="mb-1 btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  style="margin-right:5px" onclick="javascript:bod.putEstados(' + bod.con_Id + ',' + bod.con_Estado + ')">' +
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
                'emptyTable': 'Directores registrados',
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
     * Configuracion
     */
    getConfiguracion() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $.ajax({
            url: '../business/controller/class.configuracion.php',
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
                    /* swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo consultar las bodegas',
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
     * getBodegaById: Método para consultar la 
     * información de una bodega
     * @param type $id: llave primaria de la tabla bodega
     */
    async getConfiguracionById(id) {

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
                url: '../business/controller/class.configuracion.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {

                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#con_NombreDirector").val(datos.con_NombreDirector);
                            $("#con_Resolucion").val(datos.con_Resolucion);
                        }

                        $("#formConfiguracion").attr('action', 'javascript:bod.putConfiguracion(' + id + ');');
                        $("#modal_footerConfiguracion").empty();
                        $("#modal_footerConfiguracion").append(
                            '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                            '<span class="ti-close"></span> Cancelar' +
                            '</button>' +
                            '<button type="submit" class="btn btn-success btn-pill">' +
                            '<span class="ti-reload"></span> Actualizar' +
                            '</button>'
                        );

                        $("#modal-Configuracion").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información de la Configuracion',
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
     * información de una Configuracion
     * @param type $id: llave primaria de la tabla Configuracion
     */
    putConfiguracion(id) {
        var nombreDirector = $("#con_NombreDirector").val();
        var resolucion = $("#con_Resolucion").val();
        $.ajax({
            url: '../business/controller/class.configuracion.php',
            data: { funcion: 2, nombreDirector: nombreDirector, id: id , resolucion: resolucion},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                /*  $('#loading').hide();
                 $('#wrapper').removeClass('body-load'); */
                if (arr.ok == 1) {

                    $("#modal-Configuracion").modal('hide');
                    bod.getConfiguracion();
                    swal({
                        type: 'success',
                        title: 'Configuracion actualizada',
                        text: 'Configuracion actualizada exitosamente',
                    });

                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Nombre Director duplicado',
                        text: 'Ya existe una Director con el mismo nombre',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la Configuracion',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * postBodega: Método para crear Configuracion
     */
    postConfiguracion() {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load'); */

        var nombreDirector = $("#con_NombreDirector").val();
        var resolucion = $("#con_Resolucion").val();

        $.ajax({
            url: '../business/controller/class.configuracion.php',
            data: { funcion: 1, nombreDirector: nombreDirector, resolucion: resolucion },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                if (arr.ok == 1) {

                    $("#modal-Configuracion").modal('hide');
                    swal({
                        type: 'success',
                        title: 'Configuracion creada',
                        text: 'Configuracion creada exitosamente',
                    });
                    bod.getConfiguracion();
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Director duplicado',
                        text: 'Ya existe una Director con el mismo nombre',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la Configuracion',
                    });
                }

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
    async putEstados(id_configuracion, estado) {
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
                var title = "¿Está seguro de inactivar el Director?";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar el Director?";
                var button = "Sí, activar";
                var est = 1;
            }
            swal({
                title: title,
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
                        url: '../business/controller/class.configuracion.php',
                        data: { funcion: 4, id: id_configuracion, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                bod.getConfiguracion();
                                swal({
                                    type: 'success',
                                    title: 'Configuracion actualizada',
                                    text: 'Configuracion actualizada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar la Configuracion',
                                });
                            }

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
    ConfiguracionActivo() {
        $(".Menuactivo").removeClass('expand');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');
        
        $("#DConfiguracion").addClass('expand active');
        $("#DConfiguracion").addClass('active');
        $("#DConfiguracion").addClass('show');
        $("#SubMenuDirector").addClass('active');
    }

}
const bod = new Configuracion();



bod.getConfiguracion();
bod.ConfiguracionActivo();
