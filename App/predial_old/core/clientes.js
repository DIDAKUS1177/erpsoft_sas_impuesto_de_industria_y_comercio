// Declaración de variables
'use strict';

var idRol = sessionStorage.getItem('id_Rol');
console.log('idRol ', sessionStorage)
class Clientes {

    constructor() {}

    /**
     * crearClientes: Método para abrir modal de creación
     */
    async crearClientes() {
        var permiso = await _permisos.getPermisos(idRol, 1769);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {

            $("#txtClientes").val('');
            $("#txtRazonSocial").val('');
            $("#txtDocumento").val('');
            $("#txtDireccion").val('');
            $("#selec_IdDepartamento").val('');
            $("#selec_IdCiudad").val('');
            $("#txtTelefono").val('');
            $("#usu_Correo").val('');
            $("#selec_IdTipoPersona").val('');

            $("#formClientes").attr('action', 'javascript:bod.postClientes();');
            $("#modal_footerClientes").empty();
            $("#modal_footerClientes").append(
                '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                '<span class="ti-close"></span> Cancelar' +
                '</button>' +
                '<button type="submit" class="btn btn-success btn-pill">' +
                '<span class="ti-plus"></span> Crear' +
                '</button>'
            );

            $("#modal-Clientes").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de clientess 
     * @param type $arrFilter: Listado de obejtos de tipo clientes
     */
    draw_table_documents(arrFilter) {

        $("#tblClientes").DataTable().destroy();
        $("#tbodyClientes").empty();
        for (let bod of arrFilter) {
            if (bod.cli_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Clientes";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Clientes";
            }

            if (bod.cli_Identificacion === null) {
                var identifica = "No Tiene" ;
            } else {
                var identifica = bod.cli_Identificacion ;
            }

            if (bod.cli_Telefono === null) {
                var telef = "No Tiene" ;
            } else {
                var telef = bod.cli_Telefono ;
            }

            $('#tbodyClientes').append(
                '<tr>' +
                '<td>' +
                bod.cli_Nombre +
                '</td>' +
                '<td>' +
                identifica +
                '</td>' +
                '<td>' +
                telef +
                '</td>' +

                '<td align="center">' +
                '<button type="button" class="mb-1 btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar Clientes" style="margin-right:5px" onclick="javascript:bod.getClientesById(' + bod.cli_Id + ')">' +
                '<i class="dw dw-edit2"></i>' +
                '</button>' +
                '<button type="button" class="mb-1 btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  style="margin-right:5px" onclick="javascript:bod.putEstados(' + bod.cli_Id + ',' + bod.cli_Estado + ')">' +
                '<i class="' + icono + '"></i>' +
                '</button>' +
                '</td>' +
                '</tr>'
            );
        }
        bod.init_table();
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
                'emptyTable': 'Clientes registrados',
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
     * getClientes: Método para consultar las
     * clientes
     */
    getClientes() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $.ajax({
            url: '../business/controller/class.cliente.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {

                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    bod.draw_table_documents(arr.datos);
                } else {
                    bod.init_table();
                    /* swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo consultar las clientess',
                    }); */
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
     * getClientesById: Método para consultar la 
     * información de una clientes
     * @param type $id: llave primaria de la tabla clientes
     */
    async getClientesById(id) {
        var permiso = await _permisos.getPermisos(idRol, 1770);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.cliente.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {

                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#txtClientes").val(datos.cli_Nombre);
                            $("#txtRazonSocial").val(datos.cli_RazonSocial);
                            $("#txtDocumento").val(datos.cli_Identificacion);
                            $("#txtDireccion").val(datos.cli_Direccion);
                            $("#selec_IdDepartamento").val(datos.cli_IdDepartamento);
                            $("#selec_IdCiudad").val(datos.cli_IdCiudad);
                            $("#txtTelefono").val(datos.cli_Telefono);
                            $("#usu_Correo").val(datos.cli_Correo);
                            $("#selec_IdTipoPersona").val(datos.cli_IdTipoPersona);
                        }
                        //bod.getIdDepartamentos();

                        $("#formClientes").attr('action', 'javascript:bod.putClientes(' + id + ');');
                        $("#modal_footerClientes").empty();
                        $("#modal_footerClientes").append(
                            '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                            '<span class="ti-close"></span> Cancelar' +
                            '</button>' +
                            '<button type="submit" class="btn btn-success btn-pill">' +
                            '<span class="ti-reload"></span> Actualizar' +
                            '</button>'
                        );

                        $("#modal-Clientes").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información de los clientes.',
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
     * putClientes: Método para actualizar la 
     * información de una clientes
     * @param type $id: llave primaria de la tabla clientes
     */
    putClientes(id) {
        var nombre = $("#txtClientes").val();
        var razonsocial = $("#txtRazonSocial").val();
        var nit = $("#txtDocumento").val();
        var direccion = $("#txtDireccion").val();
        var idDepartamento = $("#selec_IdDepartamento").val();
        var idCiudad = $("#selec_IdCiudad").val();
        var telefono = $("#txtTelefono").val();
        var email = $("#usu_Correo").val();
        var idTipoPersona = $("#selec_IdTipoPersona").val();

        $.ajax({
            url: '../business/controller/class.cliente.php',
            data: { funcion: 2, idcli: id, nombre: nombre, razon_social: razonsocial, identificacion: nit, 
                    direccion: direccion,idDepartamento: idDepartamento, idCiudad: idCiudad, telefono: telefono, correo: email, 
                    idTipoPersona: idTipoPersona},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                /*  $('#loading').hide();
                 $('#wrapper').removeClass('body-load'); */
                if (arr.ok == 1) {

                    $("#modal-Clientes").modal('hide');
                    bod.getClientes();
                    swal({
                        type: 'success',
                        title: 'Cliente actualizado',
                        text: 'Cliente actualizado exitosamente',
                    });

                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Cliente duplicado',
                        text: 'Ya existe un cliente con la misma identificación.',
                    });
                }else if (arr.ok == 3) {
                    swal({
                        type: 'warning',
                        title: 'Cliente duplicado',
                        text: 'Ya existe una Cliente con el mismo Nombre.',
                    });
                }else if (arr.ok == 4) {
                    swal({
                        type: 'warning',
                        title: 'Cliente duplicado',
                        text: 'Ya existe una cliente con la misma Razon Social.',
                    });
                }else if (arr.ok == 5) {
                    swal({
                        type: 'warning',
                        title: 'Cliente duplicado',
                        text: 'Ya existe un cliente con el mismo Correo.',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la clientes',
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
     * postClientes: Método para crear clientess
     */
    postClientes() {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load'); */
        var nombre = $("#txtClientes").val();
        var razonsocial = $("#txtRazonSocial").val();
        var nit = $("#txtDocumento").val();
        var direccion = $("#txtDireccion").val();
        var idDepartamento = $("#selec_IdDepartamento").val();
        var idCiudad = $("#selec_IdCiudad").val();
        var telefono = $("#txtTelefono").val();
        var email = $("#usu_Correo").val();
        var idTipoPersona = $("#selec_IdTipoPersona").val();      

        $.ajax({
            url: '../business/controller/class.cliente.php',
            data: { funcion: 1, nombre: nombre, razon_social: razonsocial, identificacion: nit, 
                direccion: direccion,idDepartamento: idDepartamento, idCiudad: idCiudad, telefono: telefono, correo: email, 
                idTipoPersona: idTipoPersona },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                /*  $('#loading').hide();
                 $('#wrapper').removeClass('body-load'); */
                if (arr.ok == 1) {
                    $("#modal-Clientes").modal('hide');
                    swal({
                        type: 'success',
                        title: 'Cliente creada',
                        text: 'Cliente creado exitosamente',
                    });
                    bod.getClientes();
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Cliente duplicado',
                        text: 'Ya existe una cliente con la misma Identificación.',
                    });
                }else if (arr.ok == 3) {
                    swal({
                        type: 'warning',
                        title: 'Cliente duplicado',
                        text: 'Ya existe una cliente con el mismo Nombre.',
                    });
                }else if (arr.ok == 4) {
                    swal({
                        type: 'warning',
                        title: 'Cliente duplicado',
                        text: 'Ya existe una cliente con la misma Razon Social.',
                    });
                }else if (arr.ok == 5) {
                    swal({
                        type: 'warning',
                        title: 'Cliente duplicado',
                        text: 'Ya existe un cliente con el mismo Correo.',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la  clientes',
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
    getTipoPersona() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $("#selec_IdTipoPersona").empty();
        $("#selec_IdTipoPersona").append('<option value="">Seleccione una Tipo Persona</option>');
        $.ajax({
            url: '../business/controller/class.tipoPersona.php',
            data: { funcion: 3, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {

                    $.each(arr.datos, function(k, v) {
                        $("#selec_IdTipoPersona").append('<option value="' + v['tip_Id'] + '">' + v['tip_Descripcion'] + '</option>');
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
     * getRoles: Método para consutlar los 
     * departamentos
     */
    getIdDepartamentos() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        //$("#selec_IdDepartamento").empty();
        $("#selec_IdDepartamento").append('<option value="">Seleccione un Departamento</option>');
        $.ajax({
            url: '../business/controller/class.divipola.php',
            data: { funcion: 3, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {

                    $.each(arr.datos, function(k, v) {
                        $("#selec_IdDepartamento").append('<option value="' + v['dep_Id'] + '">' + v['dep_Nombre'] + '</option>');
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
     * getRoles: Método para consutlar los 
     * municipios
     */
    getIdMunicipios() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
         var id_depar = $("#selec_IdDepartamento").val();

        $("#selec_IdCiudad").empty();
        $("#selec_IdCiudad").append('<option value="">Seleccione un Municipio</option>');
        $.ajax({
            url: '../business/controller/class.divipola.php',
            data: { funcion: 4, estado: 1, departamento_id :id_depar },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {

                    $.each(arr.datos, function(k, v) {
                        $("#selec_IdCiudad").append('<option value="' + v['mun_Id'] + '">' + v['mun_Nombre'] + '</option>');
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
     * putEstados: Método para cambiar el estados 
     * de las clientess
     * @param type $id_clientes: llave primaria de la tabla clientes
     * @param type $estado: estado actual de la clientes
     */
    async putEstados(id_clientes, estado) {
        var permiso = await _permisos.getPermisos(idRol, 1771);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar la clientes?";
                var subtitle = "Una vez inactivado la clientes, no podrá asignarla a un producto";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la clientes?";
                var subtitle = "Una vez activado la clientes, podrá ser asignarla a un producto";
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
                        url: '../business/controller/class.cliente.php',
                        data: { funcion: 4, id: id_clientes, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                bod.getClientes();
                                swal({
                                    type: 'success',
                                    title: 'Clientes actualizada',
                                    text: 'Clientes actualizada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar la clientes',
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
     * ClientesActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    ClientesActivo() {
        $(".Menuactivo").removeClass('expand');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DAdministracion").addClass('expand');
        $("#DAdministracion").addClass('active');
        $("#SubAdminsitracion").addClass('show');
        $("#SubMenuClientes").addClass('active');
    }

}
const bod = new Clientes();


bod.getTipoPersona();
bod.getIdDepartamentos();
bod.getIdMunicipios();
bod.getClientes();
bod.ClientesActivo();