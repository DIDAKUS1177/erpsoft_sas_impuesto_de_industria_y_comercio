/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
class Mesas {

    constructor() {}

    /**
     * crearSubCategoria: Método para abrir modal de creación
     */
    async crearMesas() {
        var permiso = await _permisos.getPermisos(idRol, 27109);

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
            $("#formCrearMesas").trigger("reset");
            $("#btnCrearMesas").empty();
            $("#btnCrearMesas").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearMesas").attr('action', 'javascript:mesas.postMesas()');
            $("#modal-Mesas").modal('show');
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
        $("#bodyMesasRegistrados").empty();
        for (let usu of arrFilter) {

            if (usu.seemma_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Mesa";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Mesa";
            }

            if (usu.seemma_Id) {

                $('#bodyMesasRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    usu.strNombreSede +
                    '</td>' +
                    '<td>' +
                    usu.seemma_Nombre +
                    '</td>' +
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar Mesa" style="margin-right:5px" onclick="javascript:mesas.getMesasById(' + usu.seemma_Id  + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:mesas.cambiarEstado(' + usu.seemma_Id  + ',' + usu.seemma_Estado + ')">' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    '</td>' +

                    '</tr>'
                );
            }
        }
        mesas.init_table();
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
    getMesas() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $.ajax({
            url: '../business/controller/class.sedesEmpresaMesas.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    mesas.draw_table_documents(arr.datos);
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
    async getMesasById(id) {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        var permiso = await _permisos.getPermisos(idRol, 27109);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.sedesEmpresaMesas.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#seemma_Nombre").val(datos.seemma_Nombre);
                            $("#seemma_IdSedeEmpresa").val(datos.seemma_IdSedeEmpresa);
                        }
                        
                        $("#formCrearMesas").attr('action', 'javascript:mesas.editMesas(' + id + ')');
                        $("#btnCrearMesas").empty();
                        $("#btnCrearMesas").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $("#modal-Mesas").modal('show');
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
    postMesas() {
        /* $('#loading').show();
        $('#wrapper').addClass('body-load'); */

        var nombre = $("#seemma_Nombre").val();
        var categoria = $("#seemma_IdSedeEmpresa").val();
//       console.log('rol ', idTipo);
        $.ajax({
            url: '../business/controller/class.sedesEmpresaMesas.php',
            data: { funcion: 1, nombre: nombre, IdSedeEmpresa: categoria },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearMesas").trigger("reset");
                    $("#modal-Mesas").modal('hide');
                    mesas.getMesas();
                    swal({
                        type: 'success',
                        title: 'Mesa creada',
                        text: 'Mesa creada exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Mesa duplicada',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la Mesa',
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
        var permiso = await _permisos.getPermisos(idRol, 27109);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar la Mesa?";
                var subtitle = "Una vez inactivada, la Mesa no podrá utilizala";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la Mesa?";
                var subtitle = "Una vez activada, la Mesa podrá utilizala";
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
                        url: '../business/controller/class.sedesEmpresaMesas.php',
                        data: { funcion: 4, id: id_SubCategoria, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                mesas.getMesas();
                                swal({
                                    type: 'success',
                                    title: 'Mesa actualizada',
                                    text: 'Mesa actualizada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el Mesa',
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
    editMesas(id) {
        /*   $('#loading').show();
          $('#wrapper').addClass('body-load'); */
        var nombre = $("#seemma_Nombre").val();
        var categoria = $("#seemma_IdSedeEmpresa").val();

        $.ajax({
            url: '../business/controller/class.sedesEmpresaMesas.php',
            data: { funcion: 2, id: id, nombre: nombre, IdSedeEmpresa: categoria  },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearMesas").trigger("reset");
                    $("#modal-Mesas").modal('hide');
                    mesas.getMesas();
                    swal({
                        type: 'success',
                        title: 'Mesa actualizado',
                        text: 'Mesa actualizada exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Mesa duplicada',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la Mesa',
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
    getSedes() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $("#seemma_IdSedeEmpresa").empty();
        $("#seemma_IdSedeEmpresa").append('<option value="">Seleccione una Sede</option>');
        $.ajax({
            url: '../business/controller/class.empresa.php',
            data: { funcion: 5 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {

                    $.each(arr.datos, function(k, v) {
                        $("#seemma_IdSedeEmpresa").append('<option value="' + v['seem_Id'] + '">' + v['seem_Nombre'] + '</option>');
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
     * SubCategoriaActivo: Método para activar el menú y facilitar
     * la navegación al SubCategoria permitendole saber en
     * que lugar esta
     */
    SubCategoriaActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DRestaurante").addClass('expand');
        $("#DRestaurante").addClass('active');
        $("#SubMenuMesas").addClass('show');
        $("#SubMenuMesas").addClass('active');
        
    }

}

const mesas = new Mesas();

mesas.getMesas();
mesas.getSedes();
mesas.SubCategoriaActivo();