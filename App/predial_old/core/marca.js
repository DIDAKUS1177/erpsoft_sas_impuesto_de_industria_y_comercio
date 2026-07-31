/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
class Marca {

    constructor() {}

    /**
     * crearMarca: Método para abrir modal de creación
     */
    async crearMarca() {
        var permiso = await _permisos.getPermisos(idRol, 2079);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            //$("#clave").removeAttr('style');
            //$("#usu_Clave").attr('required', true);
            $("#formCrearMarca").trigger("reset");
            $("#btnCrearMarca").empty();
            $("#btnCrearMarca").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearMarca").attr('action', 'javascript:marca.postMarca()');
            $("#modal-Marca").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de marcas 
     * @param type $arrFilter: Listado de obejtos de tipo marcas
     */
    draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        $("#marcasRegistrados").DataTable().destroy();
        $("#bodyMarcasRegistrados").empty();
        for (let usu of arrFilter) {
            if (usu.mar_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Marca";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Marca";
            }

            if (usu.mar_Id) {

                $('#bodyMarcasRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    usu.mar_Descripcion +
                    '</td>' +                  
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar Marca" style="margin-right:5px" onclick="javascript:marca.getMarcaById(' + usu.mar_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:marca.cambiarEstado(' + usu.mar_Id + ',' + usu.mar_Estado + ')">' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    '</td>' +

                    '</tr>'
                );
            }
        }
        marca.init_table();
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
                'emptyTable': 'Marcas registradas',
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
    getMarcas() {
        $('#loading').show();
         $('#wrapper').addClass('body-load');

        $.ajax({
            url: '../business/controller/class.marca.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#bodyMarcasRegistrados").empty();
                    marca.draw_table_documents(arr.datos);
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
     * getMarcaById: Método para consultar la
     * información de un Marca
     * @param type $id: llave primaria de la tabla marcas
     */
    async getMarcaById(id) {
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
                url: '../business/controller/class.marca.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#mar_Nombre").val(datos.mar_Descripcion);
                        }

                        $("#formCrearMarca").attr('action', 'javascript:marca.editMarca(' + id + ')');
                        $("#btnCrearMarca").empty();
                        $("#btnCrearMarca").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $("#modal-Marca").modal('show');
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
     * postMarca: Método para crear 
     * marcas
     */
    postMarca() {
        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var nombre = $("#mar_Nombre").val();

        $.ajax({
            url: '../business/controller/class.marca.php',
            data: { funcion: 1, nombre: nombre},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearMarca").trigger("reset");
                    $("#modal-Marca").modal('hide');
                    marca.getMarcas();
                    swal({
                        type: 'success',
                        title: 'Marca creada',
                        text: 'Marca creada exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Marca duplicada',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la marca',
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
     * de los marcas
     * @param type $id_Marca:  llave primaria de la tabla marcas
     * @param type $estado: estado actual del Marca
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
                var title = "¿Está seguro de inactivar la marca?";
                var subtitle = "Una vez inactivada la Marca no podrá utilizala";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la marca?";
                var subtitle = "Una vez activada la marca podrá utilizala";
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
                        url: '../business/controller/class.marca.php',
                        data: { funcion: 4, id: id_marca, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');

                            if (arr.ok == 1) {
                                marca.getMarcas();
                                swal({
                                    type: 'success',
                                    title: 'Marca actualizada',
                                    text: 'Marca actualizada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el marca',
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
     * editMarca: Método para actualizar un 
     * Marca
     * @param type $id: llave primaria de la tabla marcas
     */
    editMarca(id) {
          $('#loading').show();
          $('#wrapper').addClass('body-load');

        var nombre = $("#mar_Nombre").val();

        $.ajax({
            url: '../business/controller/class.marca.php',
            data: { funcion: 2, id: id, nombre: nombre},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearMarca").trigger("reset");
                    $("#modal-Marca").modal('hide');
                    marca.getMarcas();
                    swal({
                        type: 'success',
                        title: 'Marca actualizada',
                        text: 'Marca actualizada exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Marca duplicada',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la Marca',
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
     * MarcaActivo: Método para activar el menú y facilitar
     * la navegación al Marca permitendole saber en
     * que lugar esta
     */
    MarcaActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DInventario").addClass('expand');
        $("#DInventario").addClass('active');
        $("#SInventario").addClass('show');
        $("#SubMenuMarcas").addClass('active');

    }

}

const marca = new Marca();

marca.getMarcas();
//marca.getRoles();
marca.MarcaActivo();