/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
class FormasPago {

    constructor() {}

    /**
     * crearFormasPago: Método para abrir modal de creación
     */
    async crearFormasPago() {
        var permiso = await _permisos.getPermisos(idRol, 1873);

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
            $("#formCrearFormasPago").trigger("reset");
            $("#btnCrearFormasPago").empty();
            $("#btnCrearFormasPago").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearFormasPago").attr('action', 'javascript:formasPago.postFormasPago()');
            $("#modal-FormasPago").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de FormasPagos 
     * @param type $arrFilter: Listado de obejtos de tipo FormasPagos
     */
    draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        $("#formasPagoRegistrados").DataTable().destroy();
        $("#bodyFormasPagoRegistrados").empty();
        for (let usu of arrFilter) {
            if (usu.forpa_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar FormasPago";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar FormasPago";
            }

            if (usu.forpa_Saldada == 1) {
                var IdTipo = "Saldada";
            } else {
                var IdTipo = "No Saldada";
            }

            if (usu.forpa_Id != 1 ) {
                if(usu.forpa_Id != 50){

                    $('#bodyFormasPagoRegistrados').append(
                        '<tr>' +
                        '<td>' +
                        usu.forpa_Descripcion +
                        '</td>' +
                        '<td>' +
                        IdTipo +
                        '</td>' +
                    
                        '<td align="center">' +
                        '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar FormasPago" style="margin-right:5px" onclick="javascript:formasPago.getFormasPagoById(' + usu.forpa_Id + ')">' +
                        '<i class="dw dw-edit2"></i>' +
                        '</button>' +

                        '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:formasPago.cambiarEstado(' + usu.forpa_Id + ',' + usu.forpa_Estado + ')">' +
                        '<i class="' + icono + '"></i>' +
                        '</button>' +
                        '</td>' +

                        '</tr>'
                    );
                }
            }
        }
        formasPago.init_table();
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
                'emptyTable': 'Formas de Pago registrados',
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
     * getFormasPagos: Método para consultar 
     * FormasPagos
     */
    getFormasPago() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $.ajax({
            url: '../business/controller/class.formasPago.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#bodyFormasPagoRegistrados").empty();
                    formasPago.draw_table_documents(arr.datos);
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo consultar los Formas de Pago',
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
     * getFormasPagoById: Método para consultar la
     * información de un FormasPago
     * @param type $id: llave primaria de la tabla FormasPagos
     */
    async getFormasPagoById(id) {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        var permiso = await _permisos.getPermisos(idRol, 1874);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.formasPago.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#forpa_Nombre").val(datos.forpa_Descripcion);
                            $("#forpa_Saldada").val(datos.forpa_Saldada);
                            //$("#usu_Correo").val(datos.usu_Correo);
                            //$("#usu_Clave").val(datos.usu_Password);
                           // $("#usu_Rol").val(datos.usu_Rol);

                        }
                        //$("#clave").attr('style', 'display:none');
                        //$("#usu_Clave").removeAttr('required');
                        $("#formCrearFormasPago").attr('action', 'javascript:formasPago.editFormasPago(' + id + ')');
                        $("#btnCrearFormasPago").empty();
                        $("#btnCrearFormasPago").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $("#modal-FormasPago").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información de la forma de pago',
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
     * postFormasPago: Método para crear 
     * FormasPagos
     */
    postFormasPago() {
        /* $('#loading').show();
        $('#wrapper').addClass('body-load'); */

        var nombre = $("#forpa_Nombre").val();
        var idTipo = $("#forpa_Saldada").val();
//       console.log('rol ', idTipo);
        $.ajax({
            url: '../business/controller/class.formasPago.php',
            data: { funcion: 1, nombre: nombre, forpa_Saldada: idTipo },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearFormasPago").trigger("reset");
                    $("#modal-FormasPago").modal('hide');
                    formasPago.getFormasPago();
                    swal({
                        type: 'success',
                        title: 'FormasPago creada',
                        text: 'FormasPago creada exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'FormasPago duplicada',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la formasPago',
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
     * de los FormasPagos
     * @param type $id_FormasPago:  llave primaria de la tabla FormasPagos
     * @param type $estado: estado actual del FormasPago
     */
    async cambiarEstado(id_formasPago, estado) {
        var permiso = await _permisos.getPermisos(idRol, 1875);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar la formasPago?";
                var subtitle = "Una vez inactivado, la FormasPago no podrá utilizala";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la formasPago?";
                var subtitle = "Una vez activada, la formasPago podrá utilizala";
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
                        url: '../business/controller/class.formasPago.php',
                        data: { funcion: 4, id: id_formasPago, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                formasPago.getFormasPago();
                                swal({
                                    type: 'success',
                                    title: 'FormasPago actualizado',
                                    text: 'Uusario actualizado exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el formasPago',
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
     * editFormasPago: Método para actualizar un 
     * FormasPago
     * @param type $id: llave primaria de la tabla FormasPagos
     */
    editFormasPago(id) {
        /*   $('#loading').show();
          $('#wrapper').addClass('body-load'); */
        var nombre = $("#forpa_Nombre").val();
        var idTipo = $("#forpa_Saldada").val();

        $.ajax({
            url: '../business/controller/class.formasPago.php',
            data: { funcion: 2, id: id, nombre: nombre, forpa_Saldada: idTipo},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearFormasPago").trigger("reset");
                    $("#modal-FormasPago").modal('hide');
                    formasPago.getFormasPago();
                    swal({
                        type: 'success',
                        title: 'FormasPago actualizado',
                        text: 'FormasPago actualizado exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'FormasPago duplicado',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la FormasPago',
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
     * FormasPagoActivo: Método para activar el menú y facilitar
     * la navegación al FormasPago permitendole saber en
     * que lugar esta
     */
    FormasPagoActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');
        
        $("#DConfiguracion").addClass('expand active');
        $("#DConfiguracion").addClass('active');
        $("#SubConfiguracion").addClass('show');
        $("#SubMenuFormasPago").addClass('active');

    }

}

const formasPago = new FormasPago();

formasPago.getFormasPago();
formasPago.FormasPagoActivo();