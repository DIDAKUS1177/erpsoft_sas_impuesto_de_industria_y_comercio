/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
class Eventos {

    constructor() {}

    /**
     * crearMarca: Método para abrir modal de creación
     */
    async crearEventos() {
        var permiso = await _permisos.getPermisos(idRol, 2493);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {

            $("#eve_Nombre").val('');
            $("#eve_Descripcion").val('');
            $("#eve_FechaEvento").val('');
            $("#eve_NombreCliente").val('');
            $("#eve_TelefonoCliente").val('');
            $("#eve_Email").val('');
            $("#eve_LugarEvento").val('');
            $("#eve_ValorEvento").val('');
            $("#eve_Notas").val('');

            $("#formCrearEventos").trigger("reset");
            $("#btnCrearEventos").empty();
            $("#btnCrearEventos").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearEventos").attr('action', 'javascript:eventos.postEventos()');
            $("#modal-Eventos").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de marcas 
     * @param type $arrFilter: Listado de obejtos de tipo marcas
     */
    draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        $("#eventosRegistrados").DataTable().destroy();
        $("#bodyEventosRegistrados").empty();
        for (let usu of arrFilter) {

            if (usu.eve_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Eventos";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Eventos";
            }

            if (usu.eve_Id) {

                $('#bodyEventosRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    usu.eve_Nombre +
                    '</td>' +                  
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar Evento" style="margin-right:5px" onclick="javascript:eventos.getEventosById(' + usu.eve_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:eventos.cambiarEstado(' + usu.eve_Id + ',' + usu.eve_Estado + ')">' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    '</td>' +

                    '</tr>'
                );
            }
        }
        eventos.init_table();
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
                [5, 10, 25, 50, -1],
                [5, 10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'Eventos registradas',
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
     * getMarcas: Método para consultar 
     * marcas
     */
    getEventos(estado_evento) {
        $('#loading').show();
         $('#wrapper').addClass('body-load');

        $.ajax({
            url: '../business/controller/class.eventos.php',
            data: { funcion: 3 , estado: estado_evento},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#bodyEventosRegistrados").empty();
                    eventos.draw_table_documents(arr.datos);
                } else {
                    eventos.init_table();
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * getMarcaById: Método para consultar la
     * información de un Marca
     * @param type $id: llave primaria de la tabla marcas
     */
    async getEventosById(id) {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        var permiso = await _permisos.getPermisos(idRol, 2493);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.eventos.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#eve_Nombre").val(datos.eve_Nombre);
                            $("#eve_Descripcion").val(datos.eve_Descripcion);
                            $("#eve_FechaEvento").val(datos.eve_FechaEvento);
                            $("#eve_NombreCliente").val(datos.eve_NombreCliente);
                            $("#eve_TelefonoCliente").val(datos.eve_TelefonoCliente);
                            $("#eve_Email").val(datos.eve_Email);
                            $("#eve_LugarEvento").val(datos.eve_LugarEvento);
                            $("#eve_ValorEvento").val(datos.eve_ValorEvento);
                            $("#eve_Notas").val(datos.eve_Notas);
                        }

                        $("#formCrearEventos").attr('action', 'javascript:eventos.editEventos(' + id + ')');
                        $("#btnCrearEventos").empty();
                        $("#btnCrearEventos").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $("#modal-Eventos").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información del Eventos',
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
     * postMarca: Método para crear 
     * marcas
     */
    postEventos() {
        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var nombre = $("#eve_Nombre").val();
        var descripcion = $("#eve_Descripcion").val();
        var fechaEventos = $("#eve_FechaEvento").val();
        var nombreCliente = $("#eve_NombreCliente").val();
        var telefonoCliente = $("#eve_TelefonoCliente").val();
        var email = $("#eve_Email").val();
        var lugarEvento = $("#eve_LugarEvento").val();
        var valorEvento = $("#eve_ValorEvento").val();
        var notas = $("#eve_Notas").val();

        $.ajax({
            url: '../business/controller/class.eventos.php',
            data: { funcion: 1, nombre: nombre, descripcion: descripcion, fechaEventos: fechaEventos,
                nombreCliente: nombreCliente,telefonoCliente: telefonoCliente,email: email,
                lugarEvento: lugarEvento,valorEvento: valorEvento,notas: notas},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearEventos").trigger("reset");
                    $("#modal-Eventos").modal('hide');
                    eventos.getEventos(1);
                    swal({
                        type: 'success',
                        title: 'Eventos creada',
                        text: 'Eventos creada exitosamente',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la Eventos',
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
     * cambiarEstado: Método para cambiar el estado
     * de los marcas
     * @param type $id_Marca:  llave primaria de la tabla marcas
     * @param type $estado: estado actual del Marca
     */
    async cambiarEstado(id_eventos, estado) {
        var permiso = await _permisos.getPermisos(idRol, 2493);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar la eventos?";
                var subtitle = "Una vez inactivada la Eventos no podrá utilizala";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la eventos?";
                var subtitle = "Una vez activada la eventos podrá utilizala";
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
                    $('#loading').show();9
                    $('#wrapper').addClass('body-load');
                    $.ajax({
                        url: '../business/controller/class.eventos.php',
                        data: { funcion: 4, id: id_eventos, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');

                            if (arr.ok == 1) {
                                eventos.getEventos(1);
                                swal({
                                    type: 'success',
                                    title: 'Eventos actualizada',
                                    text: 'Eventos actualizada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el Eventos',
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
     * editMarca: Método para actualizar un 
     * Marca
     * @param type $id: llave primaria de la tabla marcas
     */
    editEventos(id) {
        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var nombre = $("#eve_Nombre").val();
        var descripcion = $("#eve_Descripcion").val();
        var fechaEventos = $("#eve_FechaEvento").val();
        var nombreCliente = $("#eve_NombreCliente").val();
        var telefonoCliente = $("#eve_TelefonoCliente").val();
        var email = $("#eve_Email").val();
        var lugarEvento = $("#eve_LugarEvento").val();
        var valorEvento = $("#eve_ValorEvento").val();
        var notas = $("#eve_Notas").val();

        $.ajax({
            url: '../business/controller/class.eventos.php',
            data: { funcion: 2, id: id, nombre: nombre, descripcion: descripcion, fechaEventos: fechaEventos,
                nombreCliente: nombreCliente,telefonoCliente: telefonoCliente,email: email,
                lugarEvento: lugarEvento,valorEvento: valorEvento,notas: notas},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearEventos").trigger("reset");
                    $("#modal-Eventos").modal('hide');
                    eventos.getEventos(1);
                    swal({
                        type: 'success',
                        title: 'Eventos actualizada',
                        text: 'Eventos actualizada exitosamente',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la Eventos',
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
     * MarcaActivo: Método para activar el menú y facilitar
     * la navegación al Marca permitendole saber en
     * que lugar esta
    */
    EventosActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DEventos").addClass('expand');
        $("#DEventos").addClass('active');
        $("#DEventos").addClass('show');
        $("#SubMenuEventos").addClass('active');
    }

}

const eventos = new Eventos();

eventos.getEventos(1);
eventos.EventosActivo();