/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
class Categoria {

    constructor() {}

    /**
     * crearCategoria: Método para abrir modal de creación
     */
    async crearCategoria() {
        var permiso = await _permisos.getPermisos(idRol, 831);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $("#clave").removeAttr('style');
            $("#usu_Clave").attr('required', true);
            $("#formCrearCategoria").trigger("reset");
            $("#btnCrearCategoria").empty();
            $("#btnCrearCategoria").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearCategoria").attr('action', 'javascript:categoria.postCategoria()');
            $("#modal-Categoria").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de Categorias 
     * @param type $arrFilter: Listado de obejtos de tipo Categorias
     */
    draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        $("#categoriasRegistrados").DataTable().destroy();
        $("#bodyCategoriasRegistrados").empty();
        for (let usu of arrFilter) {
            if (usu.cate_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Categoria";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Categoria";
            }

            if (usu.cate_IdTipo == 1) {
                var IdTipo = "Productos";
            } else {
                var IdTipo = "Insumos";
            }

            if (usu.cate_Id) {

                $('#bodyCategoriasRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    usu.cate_Descripcion +
                    '</td>' +
                    '<td>' +
                    IdTipo +
                    '</td>' +
                  
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar Categoria" style="margin-right:5px" onclick="javascript:categoria.getCategoriaById(' + usu.cate_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:categoria.cambiarEstado(' + usu.cate_Id + ',' + usu.cate_Estado + ')">' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    '</td>' +

                    '</tr>'
                );
            }
        }
        categoria.init_table();
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
     * getCategorias: Método para consultar 
     * Categorias
     */
    getCategorias() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $.ajax({
            url: '../business/controller/class.categoria.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#bodyCategoriasRegistrados").empty();
                    categoria.draw_table_documents(arr.datos);
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
     * getCategoriaById: Método para consultar la
     * información de un Categoria
     * @param type $id: llave primaria de la tabla Categorias
     */
    async getCategoriaById(id) {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        var permiso = await _permisos.getPermisos(idRol, 832);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.categoria.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#cate_Nombre").val(datos.cate_Descripcion);
                            $("#cate_IdTipo").val(datos.cate_IdTipo);
                            //$("#usu_Correo").val(datos.usu_Correo);
                            //$("#usu_Clave").val(datos.usu_Password);
                           // $("#usu_Rol").val(datos.usu_Rol);

                        }
                        //$("#clave").attr('style', 'display:none');
                        //$("#usu_Clave").removeAttr('required');
                        $("#formCrearCategoria").attr('action', 'javascript:categoria.editCategoria(' + id + ')');
                        $("#btnCrearCategoria").empty();
                        $("#btnCrearCategoria").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $("#modal-Categoria").modal('show');
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
     * postCategoria: Método para crear 
     * Categorias
     */
    postCategoria() {
        /* $('#loading').show();
        $('#wrapper').addClass('body-load'); */

        var nombre = $("#cate_Nombre").val();
        var idTipo = $("#cate_IdTipo").val();
//       console.log('rol ', idTipo);
        $.ajax({
            url: '../business/controller/class.categoria.php',
            data: { funcion: 1, nombre: nombre, idTipo: idTipo },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearCategoria").trigger("reset");
                    $("#modal-Categoria").modal('hide');
                    categoria.getCategorias();
                    swal({
                        type: 'success',
                        title: 'Categoria creada',
                        text: 'Categoria creada exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Categoria duplicada',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la categoria',
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
     * de los Categorias
     * @param type $id_Categoria:  llave primaria de la tabla Categorias
     * @param type $estado: estado actual del Categoria
     */
    async cambiarEstado(id_categoria, estado) {
        var permiso = await _permisos.getPermisos(idRol, 833);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar la categoria?";
                var subtitle = "Una vez inactivado, la Categoria no podrá utilizala";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la categoria?";
                var subtitle = "Una vez activada, la categoria podrá utilizala";
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
                        url: '../business/controller/class.categoria.php',
                        data: { funcion: 4, id: id_categoria, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                categoria.getCategorias();
                                swal({
                                    type: 'success',
                                    title: 'Categoria actualizado',
                                    text: 'Uusario actualizado exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el categoria',
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
     * editCategoria: Método para actualizar un 
     * Categoria
     * @param type $id: llave primaria de la tabla Categorias
     */
    editCategoria(id) {
        /*   $('#loading').show();
          $('#wrapper').addClass('body-load'); */
        var nombre = $("#cate_Nombre").val();
        var idTipo = $("#cate_IdTipo").val();

        $.ajax({
            url: '../business/controller/class.categoria.php',
            data: { funcion: 2, id: id, nombre: nombre, idTipo: idTipo},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearCategoria").trigger("reset");
                    $("#modal-Categoria").modal('hide');
                    categoria.getCategorias();
                    swal({
                        type: 'success',
                        title: 'Categoria actualizado',
                        text: 'Categoria actualizado exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Categoria duplicado',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la Categoria',
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
     * getRoles: Método para consutlar los 
     * roles activos
     */
    getRoles() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $("#usu_Rol").empty();
        $("#usu_Rol").append('<option value="">Seleccione un rol</option>');
        $.ajax({
            url: '../business/controller/class.rol.php',
            data: { funcion: 3, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {

                    $.each(arr.datos, function(k, v) {
                        $("#usu_Rol").append('<option value="' + v['rol_Id'] + '">' + v['rol_Nombre'] + '</option>');
                    });

                } else {

                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * CategoriaActivo: Método para activar el menú y facilitar
     * la navegación al Categoria permitendole saber en
     * que lugar esta
     */
    CategoriaActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DInventario").addClass('expand');
        $("#DInventario").addClass('active');
        $("#SInventario").addClass('show');
        $("#SubMenuCategorias").addClass('active');

    }

}

const categoria = new Categoria();

categoria.getCategorias();
categoria.getRoles();
categoria.CategoriaActivo();