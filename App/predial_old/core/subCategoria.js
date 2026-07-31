/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
class SubCategoria {

    constructor() {}

    /**
     * crearSubCategoria: Método para abrir modal de creación
     */
    async crearSubCategoria() {
        var permiso = await _permisos.getPermisos(idRol, 935);

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
            $("#formCrearSubCategoria").trigger("reset");
            $("#btnCrearSubCategoria").empty();
            $("#btnCrearSubCategoria").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearSubCategoria").attr('action', 'javascript:subCategoria.postSubCategoria()');
            $("#modal-SubCategoria").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de SubCategorias 
     * @param type $arrFilter: Listado de obejtos de tipo SubCategorias
     */
    draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        $(".data-table").DataTable().destroy();
        $("#bodySubCategoriasRegistrados").empty();
        for (let usu of arrFilter) {
            if (usu.subCate_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar SubCategoria";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar SubCategoria";
            }

            if (usu.subCate_Id) {

                $('#bodySubCategoriasRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    usu.subCate_Descripcion +
                    '</td>' +
                    '<td>' +
                    usu.strNombreCategoria +
                    '</td>' +
                  
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar SubCategoria" style="margin-right:5px" onclick="javascript:subCategoria.getSubCategoriaById(' + usu.subCate_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:subCategoria.cambiarEstado(' + usu.subCate_Id + ',' + usu.subCate_Estado + ')">' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    '</td>' +

                    '</tr>'
                );
            }
        }
        subCategoria.init_table();
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
                'emptyTable': ' registrados',
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
     * getSubCategorias: Método para consultar 
     * SubCategorias
     */
    getSubCategorias() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $.ajax({
            url: '../business/controller/class.subCategoria.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {

                    //$("#bodySubCategoriasRegistrados").empty();
                    subCategoria.draw_table_documents(arr.datos);
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
     * getSubCategoriaById: Método para consultar la
     * información de un SubCategoria
     * @param type $id: llave primaria de la tabla SubCategorias
     */
    async getSubCategoriaById(id) {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        var permiso = await _permisos.getPermisos(idRol, 936);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.subCategoria.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#cate_Nombre").val(datos.subCate_Descripcion);
                            $("#cate_IdCategoria").val(datos.subCate_IdCategoria);
                            //$("#usu_Correo").val(datos.usu_Correo);
                            //$("#usu_Clave").val(datos.usu_Password);
                           // $("#usu_Rol").val(datos.usu_Rol);

                        }
                        //$("#clave").attr('style', 'display:none');
                        //$("#usu_Clave").removeAttr('required');
                        $("#formCrearSubCategoria").attr('action', 'javascript:subCategoria.editSubCategoria(' + id + ')');
                        $("#btnCrearSubCategoria").empty();
                        $("#btnCrearSubCategoria").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $("#modal-SubCategoria").modal('show');
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
     * postSubCategoria: Método para crear 
     * SubCategorias
     */
    postSubCategoria() {
        /* $('#loading').show();
        $('#wrapper').addClass('body-load'); */

        var nombre = $("#cate_Nombre").val();
        var categoria = $("#cate_IdCategoria").val();
//       console.log('rol ', idTipo);
        $.ajax({
            url: '../business/controller/class.subCategoria.php',
            data: { funcion: 1, nombre: nombre, idCategoria: categoria },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearSubCategoria").trigger("reset");
                    $("#modal-SubCategoria").modal('hide');
                    subCategoria.getSubCategorias();
                    swal({
                        type: 'success',
                        title: 'SubCategoria creada',
                        text: 'SubCategoria creada exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'SubCategoria duplicada',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la SubCategoria',
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
     * de los SubCategorias
     * @param type $id_SubCategoria:  llave primaria de la tabla SubCategorias
     * @param type $estado: estado actual del SubCategoria
     */
    async cambiarEstado(id_SubCategoria, estado) {
        var permiso = await _permisos.getPermisos(idRol, 937);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar la SubCategoria?";
                var subtitle = "Una vez inactivado, la SubCategoria no podrá utilizala";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la SubCategoria?";
                var subtitle = "Una vez activada, la SubCategoria podrá utilizala";
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
                        url: '../business/controller/class.subCategoria.php',
                        data: { funcion: 4, id: id_SubCategoria, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                subCategoria.getSubCategorias();
                                swal({
                                    type: 'success',
                                    title: 'SubCategoria actualizado',
                                    text: 'Uusario actualizado exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el SubCategoria',
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
     * editSubCategoria: Método para actualizar un 
     * SubCategoria
     * @param type $id: llave primaria de la tabla SubCategorias
     */
    editSubCategoria(id) {
        /*   $('#loading').show();
          $('#wrapper').addClass('body-load'); */
        var nombre = $("#cate_Nombre").val();
        var categoria = $("#cate_IdCategoria").val();

        $.ajax({
            url: '../business/controller/class.subCategoria.php',
            data: { funcion: 2, id: id, nombre: nombre, idCategoria: categoria  },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearSubCategoria").trigger("reset");
                    $("#modal-SubCategoria").modal('hide');
                    subCategoria.getSubCategorias();
                    swal({
                        type: 'success',
                        title: 'SubCategoria actualizado',
                        text: 'SubCategoria actualizado exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'SubCategoria duplicado',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la SubCategoria',
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
    getCategorias() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $("#cate_IdCategoria").empty();
        $("#cate_IdCategoria").append('<option value="">Seleccione una Categoria</option>');
        $.ajax({
            url: '../business/controller/class.categoria.php',
            data: { funcion: 3, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {

                    $.each(arr.datos, function(k, v) {
                        $("#cate_IdCategoria").append('<option value="' + v['cate_Id'] + '">' + v['cate_Descripcion'] + '</option>');
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
     * SubCategoriaActivo: Método para activar el menú y facilitar
     * la navegación al SubCategoria permitendole saber en
     * que lugar esta
     */
    SubCategoriaActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DInventario").addClass('expand');
        $("#DInventario").addClass('active');
        $("#SInventario").addClass('show');
        $("#SubMenuSubCategorias").addClass('active');
        
    }

}

const subCategoria = new SubCategoria();

subCategoria.getSubCategorias();
subCategoria.getCategorias();
subCategoria.SubCategoriaActivo();