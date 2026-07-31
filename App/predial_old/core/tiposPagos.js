/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
class TiposPagos {

    constructor() {}

    /**
     * crearTiposPagos: Método para abrir modal de creación
     */
    async crearTiposPagos() {
        var permiso = await _permisos.getPermisos(idRol, 2597);

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
            $("#formCrearTiposPagos").trigger("reset");
            $("#btnCrearTiposPagos").empty();
            $("#btnCrearTiposPagos").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearTiposPagos").attr('action', 'javascript:tiposPagos.postTiposPagos()');
            $("#modal-TiposPagos").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de tiposPagos 
     * @param type $arrFilter: Listado de obejtos de tipo tiposPagos
     */
    draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        $("#tiposPagosRegistrados").DataTable().destroy();
        $("#bodyTiposPagosRegistrados").empty();
        for (let usu of arrFilter) {
            if (usu.tipa_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Tipo de Ingreso/Egreso";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Tipo de Ingreso/Egreso";
            }

            if (usu.tipa_IdTipo == 1) {
                var tipoNombre = "Salida";
            } else {
                var tipoNombre = "Entrada";
            }

            if(usu.tipa_Id != 4){
                $('#bodyTiposPagosRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    usu.tipa_Nombre +
                    '</td>' +   
                    '<td>' +
                    tipoNombre +
                    '</td>' +                   
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar Tipo de Ingreso/Egreso" style="margin-right:5px" onclick="javascript:tiposPagos.getTiposPagosById(' + usu.tipa_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:tiposPagos.cambiarEstado(' + usu.tipa_Id + ',' + usu.tipa_Estado + ')">' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    '</td>' +

                    '</tr>'
                );   
            }         
        }
        tiposPagos.init_table();
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
                'emptyTable': 'TiposPagos registradas',
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
     * tiposPagos
     */
    getTiposPagos() {
        $('#loading').show();
         $('#wrapper').addClass('body-load');

        $.ajax({
            url: '../business/controller/class.tiposPagos.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#bodyTiposPagosRegistrados").empty();
                    tiposPagos.draw_table_documents(arr.datos);
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
     * información de un TiposPagos
     * @param type $id: llave primaria de la tabla tiposPagos
     */
    async getTiposPagosById(id) {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        var permiso = await _permisos.getPermisos(idRol, 2598);

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
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#txtNombre").val(datos.tipa_Nombre);    
                            $("#txtTipo").val(datos.tipa_IdTipo);                 
                        }

                        $("#formCrearTiposPagos").attr('action', 'javascript:tiposPagos.editTiposPagos(' + id + ')');
                        $("#btnCrearTiposPagos").empty();
                        $("#btnCrearTiposPagos").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $("#modal-TiposPagos").modal('show');
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
     * tiposPagos
     */
    postTiposPagos() {
        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var nombre = $("#txtNombre").val();
        var tipo = $("#txtTipo").val();      

        $.ajax({
            url: '../business/controller/class.tiposPagos.php',
            data: { funcion: 1, nombre: nombre, idTipo: tipo},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearTiposPagos").trigger("reset");
                    $("#modal-TiposPagos").modal('hide');
                    tiposPagos.getTiposPagos();
                    swal({
                        type: 'success',
                        title: 'TiposPagos creada',
                        text: 'TiposPagos creada exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'TiposPagos duplicada',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la tiposPagos',
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
     * de los tiposPagos
     * @param type $id_TiposPagos:  llave primaria de la tabla tiposPagos
     * @param type $estado: estado actual del TiposPagos
     */
    async cambiarEstado(id_tipoPago, estado) {
        var permiso = await _permisos.getPermisos(idRol, 2599);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar el tipo Pago?";
                var subtitle = "Una vez inactivada el Tipo Pago no podrá utilizala";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar el tipo Pago?";
                var subtitle = "Una vez activada el tipo Pago podrá utilizala";
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
                        data: { funcion: 4, id: id_tipoPago, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');

                            if (arr.ok == 1) {
                                tiposPagos.getTiposPagos();
                                swal({
                                    type: 'success',
                                    title: 'TiposPagos actualizada',
                                    text: 'TiposPagos actualizada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el tiposPagos',
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
     * TiposPagos
     * @param type $id: llave primaria de la tabla tiposPagos
     */
    editTiposPagos(id) {
          $('#loading').show();
          $('#wrapper').addClass('body-load');

        var nombre = $("#txtNombre").val();
        var tipo = $("#txtTipo").val();

        $.ajax({
            url: '../business/controller/class.tiposPagos.php',
            data: { funcion: 2, id: id, nombre: nombre, idTipo: tipo},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearTiposPagos").trigger("reset");
                    $("#modal-TiposPagos").modal('hide');
                    tiposPagos.getTiposPagos();
                    swal({
                        type: 'success',
                        title: 'TiposPagos actualizada',
                        text: 'TiposPagos actualizada exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'TiposPagos duplicada',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la TiposPagos',
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
     * la navegación al TiposPagos permitendole saber en
     * que lugar esta
     */
    TiposPagosActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DConfiguracion").addClass('expand');
        $("#DConfiguracion").addClass('active');
        $("#SubConfiguracion").addClass('show');
        $("#SubMenuTiposPagos").addClass('active');

    }

}

const tiposPagos = new TiposPagos();

tiposPagos.getTiposPagos();
tiposPagos.TiposPagosActivo();