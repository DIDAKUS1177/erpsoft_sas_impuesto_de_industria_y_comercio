/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
class SubTiposPagos {

    constructor() {}

    /**
     * crearTiposPagos: Método para abrir modal de creación
     */
    async crearSubTiposPagos() {
        var permiso = await _permisos.getPermisos(idRol, 26101);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $("#txtNombre").val('');
            $("#txtTipo").val(''); 
            $("#formCrearSubTiposPagos").trigger("reset");
            $("#btnCrearSubTiposPagos").empty();
            $("#btnCrearSubTiposPagos").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearSubTiposPagos").attr('action', 'javascript:subTiposPagos.postSubTiposPagos()');
            $("#modal-SubTiposPagos").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de subTiposPagos 
     * @param type $arrFilter: Listado de obejtos de tipo subTiposPagos
     */
    draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        $("#subTiposPagosRegistrados").DataTable().destroy();
        $("#bodySubTiposPagosRegistrados").empty();
        for (let usu of arrFilter) {
            if (usu.subtipa_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar SubTipo de Ingreso/Egreso";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar SubTipo de Ingreso/Egreso";
            }

            if (usu.subtipa_IdTipo == 1) {
                var tipoNombre = "Salida";
            } else {
                var tipoNombre = "Entrada";
            }

            
                $('#bodySubTiposPagosRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    usu.subtipa_Nombre +
                    '</td>' +     
                    '<td>' +
                    tipoNombre +
                    '</td>' +                
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar SubTipo de Ingreso/Egreso" style="margin-right:5px" onclick="javascript:subTiposPagos.getSubTiposPagosById(' + usu.subtipa_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:subTiposPagos.cambiarEstado(' + usu.subtipa_Id + ',' + usu.subtipa_Estado + ')">' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    '</td>' +

                    '</tr>'
                );   
                 
        }
        subTiposPagos.init_table();
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
                'emptyTable': 'SubTiposPagos registradas',
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
     * getTiposPagos: Método para consultar 
     * subTiposPagos
     */
    getSubTiposPagos() {
        $('#loading').show();
         $('#wrapper').addClass('body-load');

        $.ajax({
            url: '../business/controller/class.tiposPagos.php',
            data: { funcion: 5 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#bodySubTiposPagosRegistrados").empty();
                    subTiposPagos.draw_table_documents(arr.datos);
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
     * getTiposPagosById: Método para consultar la
     * información de un SubTiposPagos
     * @param type $id: llave primaria de la tabla subTiposPagos
     */
    async getSubTiposPagosById(id) {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        var permiso = await _permisos.getPermisos(idRol, 26102);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.tiposPagos.php',
                data: { funcion: 5, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#txtNombre").val(datos.subtipa_Nombre);      
                            $("#txtTipo").val(datos.subtipa_IdTipo);               
                        }

                        $("#formCrearSubTiposPagos").attr('action', 'javascript:subTiposPagos.editSubTiposPagos(' + id + ')');
                        $("#btnCrearSubTiposPagos").empty();
                        $("#btnCrearSubTiposPagos").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $("#modal-SubTiposPagos").modal('show');
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
     * postTiposPagos: Método para crear 
     * subTiposPagos
     */
    postSubTiposPagos() {
        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var nombre = $("#txtNombre").val();
        var tipo = $("#txtTipo").val();  

        $.ajax({
            url: '../business/controller/class.tiposPagos.php',
            data: { funcion: 6, nombre: nombre, idTipo: tipo},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearSubTiposPagos").trigger("reset");
                    $("#modal-SubTiposPagos").modal('hide');
                    subTiposPagos.getSubTiposPagos();
                    swal({
                        type: 'success',
                        title: 'SubTiposPagos creada',
                        text: 'SubTiposPagos creada exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'SubTiposPagos duplicada',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la subTiposPagos',
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
     * de los subTiposPagos
     * @param type $id_TiposPagos:  llave primaria de la tabla subTiposPagos
     * @param type $estado: estado actual del SubTiposPagos
     */
    async cambiarEstado(id_subtipoPago, estado) {
        var permiso = await _permisos.getPermisos(idRol, 26103);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar el Subtipo Pago?";
                var subtitle = "Una vez inactivada el SubTipo Pago no podrá utilizala";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar el Subtipo Pago?";
                var subtitle = "Una vez activada el Subtipo Pago podrá utilizala";
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
                        url: '../business/controller/class.tiposPagos.php',
                        data: { funcion: 8, id: id_subtipoPago, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');

                            if (arr.ok == 1) {
                                subTiposPagos.getSubTiposPagos();
                                swal({
                                    type: 'success',
                                    title: 'SubTiposPagos actualizada',
                                    text: 'SubTiposPagos actualizada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el subTiposPagos',
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
     * editTiposPagos: Método para actualizar un 
     * SubTiposPagos
     * @param type $id: llave primaria de la tabla subTiposPagos
     */
    editSubTiposPagos(id) {
          $('#loading').show();
          $('#wrapper').addClass('body-load');

        var nombre = $("#txtNombre").val();
        var tipo = $("#txtTipo").val();      

        $.ajax({
            url: '../business/controller/class.tiposPagos.php',
            data: { funcion: 7, id: id, nombre: nombre, idTipo: tipo},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearSubTiposPagos").trigger("reset");
                    $("#modal-SubTiposPagos").modal('hide');
                    subTiposPagos.getSubTiposPagos();
                    swal({
                        type: 'success',
                        title: 'SubTiposPagos actualizada',
                        text: 'SubTiposPagos actualizada exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'SubTiposPagos duplicada',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la SubTiposPagos',
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
     * TiposPagosActivo: Método para activar el menú y facilitar
     * la navegación al SubTiposPagos permitendole saber en
     * que lugar esta
     */
    SubTiposPagosActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DConfiguracion").addClass('expand');
        $("#DConfiguracion").addClass('active');
        $("#SubConfiguracion").addClass('show');
        $("#SubMenuSubTiposPagos").addClass('active');

    }

}

const subTiposPagos = new SubTiposPagos();

subTiposPagos.getSubTiposPagos();
subTiposPagos.SubTiposPagosActivo();