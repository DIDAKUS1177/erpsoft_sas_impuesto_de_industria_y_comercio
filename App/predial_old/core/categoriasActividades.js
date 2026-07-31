/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
class CategoriasActividades {

    constructor() {}

    /**
     * crearCategoriasActividades: Método para abrir modal de creación
     */
    async crearCategoriasActividades() {
        var permiso = await _permisos.getPermisos(idRol, 2493);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            
            $("#caa_Nombre").val('');
            $("#formCrearCategoriasActividades").trigger("reset");
            $("#btnCrearCategoriasActividades").empty();
            $("#btnCrearCategoriasActividades").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearCategoriasActividades").attr('action', 'javascript:categoriasActividades.postCategoriasActividades()');
            $("#modal-CategoriasActividades").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de categoriasActividades 
     * @param type $arrFilter: Listado de obejtos de tipo categoriasActividades
     */
    draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        $("#categoriasActividadesRegistrados").DataTable().destroy();
        $("#bodyCategoriasActividadesRegistrados").empty();
        for (let usu of arrFilter) {
            if (usu.caa_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar CategoriasActividades";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar CategoriasActividades";
            }

            if (usu.caa_Id) {

                $('#bodyCategoriasActividadesRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    usu.caa_Nombre +
                    '</td>' +                  
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-ion btn-warning " data-toggle="tooltip" title="Editar CategoriasActividades" style="margin-right:5px" onclick="javascript:categoriasActividades.getCategoriasActividadesById(' + usu.caa_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:categoriasActividades.cambiarEstado(' + usu.caa_Id + ',' + usu.caa_Estado + ')">' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    '</td>' +

                    '</tr>'
                );
            }
        }
        categoriasActividades.init_table();
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
                'emptyTable': 'Categorias Actividades registradas',
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
     * getCategoriasActividades: Método para consultar 
     * categoriasActividades
     */
    getCategoriasActividades() {
        $('#loading').show();
         $('#wrapper').addClass('body-load');

        $.ajax({
            url: '../business/controller/class.categoriasActividades.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#bodyCategoriasActividadesRegistrados").empty();
                    categoriasActividades.draw_table_documents(arr.datos);
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo consultar los roles',
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
     * getCategoriasActividadesById: Método para consultar la
     * información de un CategoriasActividades
     * @param type $id: llave primaria de la tabla categoriasActividades
     */
    async getCategoriasActividadesById(id) {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        var permiso = await _permisos.getPermisos(idRol, 2080);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.categoriasActividades.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#caa_Nombre").val(datos.caa_Nombre);
                        }

                        $("#formCrearCategoriasActividades").attr('action', 'javascript:categoriasActividades.editCategoriasActividades(' + id + ')');
                        $("#btnCrearCategoriasActividades").empty();
                        $("#btnCrearCategoriasActividades").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $("#modal-CategoriasActividades").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información del rol',
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
     * postCategoriasActividades: Método para crear 
     * categoriasActividades
     */
    postCategoriasActividades() {
        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var nombre = $("#caa_Nombre").val();

        $.ajax({
            url: '../business/controller/class.categoriasActividades.php',
            data: { funcion: 1, nombre: nombre},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearCategoriasActividades").trigger("reset");
                    $("#modal-CategoriasActividades").modal('hide');
                    categoriasActividades.getCategoriasActividades();
                    swal({
                        type: 'success',
                        title: 'Categorias Actividades creada',
                        text: 'Categorias Actividades creada exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Categorias Actividades duplicada',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la categoriasActividades',
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
     * cambiarEstado: Método para cambiar el estado
     * de los categoriasActividades
     * @param type $id_CategoriasActividades:  llave primaria de la tabla categoriasActividades
     * @param type $estado: estado actual del CategoriasActividades
     */
    async cambiarEstado(id_marca, estado) {
        var permiso = await _permisos.getPermisos(idRol, 2081);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar la categoriasActividades?";
                var subtitle = "Una vez inactivada la CategoriasActividades no podrá utilizala";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la categoriasActividades?";
                var subtitle = "Una vez activada la categoriasActividades podrá utilizala";
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
                        url: '../business/controller/class.categoriasActividades.php',
                        data: { funcion: 4, id: id_marca, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');

                            if (arr.ok == 1) {
                                categoriasActividades.getCategoriasActividades();
                                swal({
                                    type: 'success',
                                    title: 'Categorias Actividades actualizada',
                                    text: 'Categorias Actividades actualizada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el categoriasActividades',
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
     * editCategoriasActividades: Método para actualizar un 
     * CategoriasActividades
     * @param type $id: llave primaria de la tabla categoriasActividades
     */
    editCategoriasActividades(id) {
          $('#loading').show();
          $('#wrapper').addClass('body-load');

        var nombre = $("#caa_Nombre").val();

        $.ajax({
            url: '../business/controller/class.categoriasActividades.php',
            data: { funcion: 2, id: id, nombre: nombre},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearCategoriasActividades").trigger("reset");
                    $("#modal-CategoriasActividades").modal('hide');
                    categoriasActividades.getCategoriasActividades();
                    swal({
                        type: 'success',
                        title: 'Categorias Actividades actualizada',
                        text: 'Categorias Actividades actualizada exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Categorias Actividades duplicada',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la Categorias Actividades',
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
     * CategoriasActividadesActivo: Método para activar el menú y facilitar
     * la navegación al CategoriasActividades permitendole saber en
     * que lugar esta
     */
    CategoriasActividadesActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DEventos").addClass('expand');
        $("#DEventos").addClass('active');
        $("#SubEventos").addClass('show');
        $("#SubMenuCategoriasActividades").addClass('active');

    }

}

const categoriasActividades = new CategoriasActividades();

categoriasActividades.getCategoriasActividades();
//categoriasActividades.getRoles();
categoriasActividades.CategoriasActividadesActivo();