/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
class EventosAct {

    constructor() {}

    /**
     * crearMarca: Método para abrir modal de creación
     */
    async crearEventosAct() {
        var permiso = await _permisos.getPermisos(idRol, 2493);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {

            $("#eva_IdProyecto").val('');          
            $("#eva_IdProveedor").val('');          
            $("#eva_IdCategoria").val('');          
            $("#eva_Descripcion").val('');
            $("#eva_ValorEvento").val('');            

            $("#formCrearEventosAct").trigger("reset");
            $("#btnCrearEventosAct").empty();
            $("#btnCrearEventosAct").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearEventosAct").attr('action', 'javascript:eventosAct.postEventosAct()');
            $("#modal-EventosAct").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de marcas 
     * @param type $arrFilter: Listado de obejtos de tipo marcas
     */
    draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        $("#eventosActRegistrados").DataTable().destroy();
        $("#bodyEventosActRegistrados").empty();
        for (let usu of arrFilter) {

            if (usu.eva_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Eventos";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Eventos";
            }


            $('#bodyEventosActRegistrados').append(
                '<tr>' +
                '<td>' +
                usu.strNombreProyecto +
                '</td>' +
                '<td>' +
                usu.strRazonSocialProveedor +
                '</td>' +
                '<td>' +
                usu.eva_Descripcion + ' - $' + Number(parseInt(usu.eva_Valor).toFixed(0)).toLocaleString('es-CO') +
                '</td>' +                  
                '<td align="center">' +
                '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar Actividad" style="margin-right:5px" onclick="javascript:eventosAct.getEventosActById(' + usu.eva_Id + ')">' +
                '<i class="dw dw-edit2"></i>' +
                '</button>' +

                '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:eventosAct.cambiarEstado(' + usu.eva_Id + ',' + usu.eva_Estado + ')">' +
                '<i class="' + icono + '"></i>' +
                '</button>' +
                '</td>' +

                '</tr>'
            );
            
        }
        eventosAct.init_table();
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
                'emptyTable': 'Actividades registradas',
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
    getEventosAct() {
        $('#loading').show();
         $('#wrapper').addClass('body-load');

        $.ajax({
            url: '../business/controller/class.actividades.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#bodyEventosActRegistrados").empty();
                    eventosAct.draw_table_documents(arr.datos);
                } else {
                    eventosAct.init_table();
                    /*
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo consultar los Actividades.',
                    });
                    */
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
    async getEventosActById(id) {
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
                url: '../business/controller/class.actividades.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                         
                            $("#eva_IdProyecto").val(datos.eva_IdProyecto);
                            $("#eva_IdProveedor").val(datos.eva_IdProveedor);
                            $("#eva_IdCategoria").val(datos.eva_IdCategoria);
                            $("#eva_Descripcion").val(datos.eva_Descripcion);
                            $("#eva_ValorEvento").val(datos.eva_Valor);                            
                        }

                        $("#formCrearEventosAct").attr('action', 'javascript:eventosAct.editEventosAct(' + id + ')');
                        $("#btnCrearEventosAct").empty();
                        $("#btnCrearEventosAct").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $("#modal-EventosAct").modal('show');
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
    postEventosAct() {
        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var idProyecto = $("#eva_IdProyecto").val();
        var idProveedor = $("#eva_IdProveedor").val();
        var idCategoria = $("#eva_IdCategoria").val();
        var descripcion = $("#eva_Descripcion").val();
        var valorEvento = $("#eva_ValorEvento").val();

        $.ajax({
            url: '../business/controller/class.actividades.php',
            data: { funcion: 1, idProyecto: idProyecto, idProveedor: idProveedor, idCategoria: idCategoria,
                descripcion: descripcion,valorEvento: valorEvento},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearEventosAct").trigger("reset");
                    $("#modal-EventosAct").modal('hide');
                    eventosAct.getEventosAct();
                    swal({
                        type: 'success',
                        title: 'Actividad creada',
                        text: 'Actividad creada exitosamente',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la Actividades',
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
    async cambiarEstado(id_actividad, estado) {
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
                var title = "¿Está seguro de inactivar la actividades?";
                var subtitle = "Una vez inactivada la actividades no podrá utilizala";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la actividades?";
                var subtitle = "Una vez activada la actividades podrá utilizala";
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
                        url: '../business/controller/class.actividades.php',
                        data: { funcion: 4, id: id_actividad, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');

                            if (arr.ok == 1) {
                                eventosAct.getEventosAct();
                                swal({
                                    type: 'success',
                                    title: 'Actividad actualizada',
                                    text: 'Actividad actualizada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el Actividad',
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
    editEventosAct(id) {
        $('#loading').show();
        $('#wrapper').addClass('body-load');
        
        var idProyecto = $("#eva_IdProyecto").val();
        var idProveedor = $("#eva_IdProveedor").val();
        var idCategoria = $("#eva_IdCategoria").val();
        var descripcion = $("#eva_Descripcion").val();
        var valorEvento = $("#eva_ValorEvento").val();

        $.ajax({
            url: '../business/controller/class.actividades.php',
            data: { funcion: 2, id: id, idProyecto: idProyecto, idProveedor: idProveedor, idCategoria: idCategoria,
                descripcion: descripcion,valorEvento: valorEvento},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearEventosAct").trigger("reset");
                    $("#modal-EventosAct").modal('hide');
                    eventosAct.getEventosAct();
                    swal({
                        type: 'success',
                        title: 'Actividad actualizada',
                        text: 'Actividad actualizada exitosamente',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la Actividad',
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
     * getIdProyectos: Método para consutlar los 
     * departamentos
     */
      getIdProyectos() {

        $("#eva_IdProyecto").append('<option value="">Seleccione un Evento</option>');
        $.ajax({
            url: '../business/controller/class.eventos.php',
            data: { funcion: 3, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#eva_IdProyecto").append('<option value="' + v['eve_Id'] + '">' + v['eve_Nombre'] + '</option>');
                    });
                } else { }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
    * getIdProyectos: Método para consutlar los 
    * departamentos
    */
    getIdProveedores() {

        $("#eva_IdProveedor").append('<option value="">Seleccione un Proveedor</option>');
        $.ajax({
            url: '../business/controller/class.proveedoresEventos.php',
            data: { funcion: 3, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#eva_IdProveedor").append('<option value="' + v['prov_Id'] + '">' + v['prov_RazonSocial'] + '</option>');
                    });
                } else { }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }



    getIdCategorias() {

        $("#eva_IdCategoria").append('<option value="">Seleccione un Categoria</option>');
        $.ajax({
            url: '../business/controller/class.categoriasActividades.php',
            data: { funcion: 3, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#eva_IdCategoria").append('<option value="' + v['caa_Id'] + '">' + v['caa_Nombre'] + '</option>');
                    });
                } else { }
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
    EventosActivoAct() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DEventos").addClass('expand');
        $("#DEventos").addClass('active');
        $("#DEventos").addClass('show');
        $("#SubMenuActEventos").addClass('active');
    }

}

const eventosAct = new EventosAct();

eventosAct.getEventosAct();
eventosAct.EventosActivoAct();
eventosAct.getIdProveedores();
eventosAct.getIdProyectos();
eventosAct.getIdCategorias();
