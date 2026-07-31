/*    METDOS DEL MODULO DE GESTION ESTADOS    */

var enable = true;
var idRol = localStorage.getItem('id_Rol');

class GestionEstados {

    constructor() {}

    /**
     * crearUsuario: Método para abrir modal de creación de GestionEstados.
     */
    async crearGestionEstados() {

        //Parametro: 27 (2= Modulo GestionEstados, 7:Permiso Crear GestionEstados)
        var permiso = await _permisos.getPermisos(idRol, 519);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $("#formCrearGestionEstados").trigger("reset");
            $("#btnCrearGestionEstados").empty();
            $("#btnCrearGestionEstados").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearGestionEstados").attr('action', 'javascript:gestionEstados.postGestionEstados()');
            $('#modal-GestionEstados').modal({backdrop: 'static', keyboard: false})
            $("#modal-GestionEstados").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de GestionEstados 
     * @param type $arrFilter: Listado de objetos GestionEstados
     */
    draw_table_documents(arrFilter) {
        
        $("#gestionEstadosRegistrados").DataTable().destroy();
        $("#bodyGestionEstadosRegistrados").empty();
        for (let est of arrFilter) {
            if (est.est_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Estado";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Estado";
            }

            var color = 'style="background-color:'+est.est_Color+'"';

                $('#bodyGestionEstadosRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    est.est_Id +
                    '</td>' +
                    '<td '+color+'>' +
                    est.est_Nombre +
                    '</td>' +
                    '<td>' +
                    est.est_Descripcion +
                    '</td>' +

                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar GestionEstados" style="margin-right:5px" onclick="javascript:gestionEstados.getGestionEstadosById(' + est.est_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:gestionEstados.cambiarEstado(' + est.est_Id + ',' + est.est_Estado + ')">' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    '</td>' +

                    '</tr>'
                );
            
        }
        gestionEstados.init_table();
    }

    /**
     * init_table: Método para asignar la
     * propiedad DataTable() a la tabla de GestionEstados
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
                [5, 10, 25, 50, -1],
                [5, 10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'Gestion de Estados registrados',
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
     * getGestionEstados: Método para consultar GestionEstados
     */
    getGestionEstados() {
        
        $.ajax({
            url: '../business/controller/class.estados.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                if (arr.ok == 1) {
                    $("#bodyGestionEstadosRegistrados").empty();
                    gestionEstados.draw_table_documents(arr.datos);
                } else {
                    $("#gestionEstadosRegistrados").DataTable().destroy();
                    gestionEstados.init_table();
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * getUsuarioById: Método para consultar la
     * información de un GestionEstados
     * @param type $id: llave primaria de la tabla GestionEstados
     */
    async getGestionEstadosById(id) {
        
        $('#loading').show();
        $('#wrapper').addClass('body-load');
        var permiso = await _permisos.getPermisos(idRol, 520);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.estados.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        console.log('arr editar ', arr);
                        for (let datos of arr.datos) {
                            $("#est_Nombre").val(datos.est_Nombre);
                            $("#est_Descripcion").val(datos.est_Descripcion);
                            $("#est_Color").val(datos.est_Color);
                        }
                        $("#formCrearGestionEstados").attr('action', 'javascript:gestionEstados.editGestionEstados(' + id + ')');
                        $("#btnCrearGestionEstados").empty();
                        $("#btnCrearGestionEstados").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $('#modal-GestionEstados').modal({backdrop: 'static', keyboard: false})
                        $("#modal-GestionEstados").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información dela GestionEstados',
                        });
                    }
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                }
            });
        }
    }

    /**
     * postUsuario: Método para crear GestionEstados
     */
    postGestionEstados() {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var nombre = $("#est_Nombre").val();
        var descripcion = $("#est_Descripcion").val();
        var color = $("#est_Color").val();

        $.ajax({
            url: '../business/controller/class.estados.php',
            data: { funcion: 1, est_Nombre: nombre, est_Descripcion: descripcion, est_Color: color},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearGestionEstados").trigger("reset");
                    $("#modal-GestionEstados").modal('hide');
                    gestionEstados.getGestionEstados();
                    swal({
                        type: 'success',
                        title: 'Estado creada',
                        text: 'Estado creada exitosamente',
                    });
                }else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Nombre duplicado',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear el Estado',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * cambiarEstado: Método para cambiar el estado de los GestionEstados
     * @param type $id_usuario:  llave primaria de la tabla GestionEstados
     * @param type $estado: estado actual del GestionEstados
     */
    async cambiarEstado(id_gestionEstados, estado) {

        var permiso = await _permisos.getPermisos(idRol, 521);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar el Estado?";
                var subtitle = "Una vez inactivado el Estado no podra usarse";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar el Estado?";
                var subtitle = "Una vez activado, el Estado podrá usarse";
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
                        url: '../business/controller/class.estados.php',
                        data: { funcion: 4, id: id_gestionEstados, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {

                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                gestionEstados.getGestionEstados();
                                swal({
                                    type: 'success',
                                    title: 'Estado actualizado',
                                    text: 'Estado actualizado exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el Estado',
                                });
                            }
                        },
                        error: function(XMLHttpRequest, textStatus, errorThrown) {
                            console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                        }
                    });
                }
            })
        }
    }

    /**
     * editUsuario: Método para actualizar un gestionEstados
     * @param type $id: llave primaria de la tabla gestionEstados
     */
    editGestionEstados(id) {

        /*$('#loading').show();
        $('#wrapper').addClass('body-load');*/

        var nombre = $("#est_Nombre").val();
        var descripcion = $("#est_Descripcion").val();
        var color = $("#est_Color").val();

        $.ajax({
            url: '../business/controller/class.estados.php',
            data: { funcion: 2, id: id, est_Nombre: nombre, est_Descripcion: descripcion, est_Color: color },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearGestionEstados").trigger("reset");
                    $("#modal-GestionEstados").modal('hide');
                    gestionEstados.getGestionEstados();
                    swal({
                        type: 'success',
                        title: 'Estado actualizado',
                        text: 'Estado actualizado exitosamente',
                    });
                }else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Nombre duplicado',
                        text: arr.mensaje,
                    });
                }else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar el Estado',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * UsuarioActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    UsuarioActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DConfiguracion").addClass('expand active');
        $("#DConfiguracion").addClass('active');
        $("#SubConfiguracion").addClass('show');
        $("#SubGestionEstados").addClass('active');
    }
}

const gestionEstados = new GestionEstados();

gestionEstados.getGestionEstados();
gestionEstados.UsuarioActivo();
