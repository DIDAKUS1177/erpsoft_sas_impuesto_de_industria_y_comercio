// Declaración de variables
'use strict';

var idRol = sessionStorage.getItem('id_Rol');
console.log('idRol ', sessionStorage)
class Proveedores {

    constructor() {}

    /**
     * crearProveedores: Método para abrir modal de creación
     */
    async crearProveedores() {
        var permiso = await _permisos.getPermisos(idRol, 1665);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {

            $("#txtProveedores").val('');
            $("#txtRazonSocial").val('');
            $("#txtNit").val('');
            $("#txtDireccion").val('');
            $("#selec_IdDepartamento").val('');
            $("#selec_IdCiudad").val('');
            $("#txtTelefono").val('');
            $("#usu_Correo").val('');
            $("#selec_IdTipoPersona").val('');
            $("#formProveedores").attr('action', 'javascript:bod.postProveedores();');
            $("#modal_footerProveedores").empty();
            $("#modal_footerProveedores").append(
                '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                '<span class="ti-close"></span> Cancelar' +
                '</button>' +
                '<button type="submit" class="btn btn-success btn-pill">' +
                '<span class="ti-plus"></span> Crear' +
                '</button>'
            );

            $("#modal-Proveedores").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de proveedoress 
     * @param type $arrFilter: Listado de obejtos de tipo proveedores
     */
    draw_table_documents(arrFilter) {

        $("#tblProveedores").DataTable().destroy();
        $("#tbodyProveedores").empty();
        for (let bod of arrFilter) {
            if (bod.prov_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Proveedores";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Proveedores";
            }
            $('#tbodyProveedores').append(
                '<tr>' +
                '<td>' +
                bod.prov_Nombre +
                '</td>' +
                '<td>' +
                bod.prov_Nit +
                '</td>' +
                '<td>' +
                bod.prov_Telefono +
                '</td>' +

                '<td align="center">' +
                '<button type="button" class="mb-1 btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar Proveedores" style="margin-right:5px" onclick="javascript:bod.getProveedoresById(' + bod.prov_Id + ')">' +
                '<i class="dw dw-edit2"></i>' +
                '</button>' +
                '<button type="button" class="mb-1 btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  style="margin-right:5px" onclick="javascript:bod.putEstados(' + bod.prov_Id + ',' + bod.prov_Estado + ')">' +
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
     * getProveedores: Método para consultar las
     * proveedoress
     */
    getProveedores() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $.ajax({
            url: '../business/controller/class.proveedores.php',
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
                        text: 'No se pudo consultar las proveedoress',
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
     * getProveedoresById: Método para consultar la 
     * información de una proveedores
     * @param type $id: llave primaria de la tabla proveedores
     */
    async getProveedoresById(id) {
        var permiso = await _permisos.getPermisos(idRol, 1666);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.proveedores.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {

                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#txtProveedores").val(datos.prov_Nombre);
                            $("#txtRazonSocial").val(datos.prov_RazonSocial);
                            $("#txtNit").val(datos.prov_Nit);
                            $("#txtDireccion").val(datos.prov_Direccion);
                            $("#selec_IdDepartamento").val(datos.prov_IdDepartamento);
                            $("#selec_IdCiudad").val(datos.prov_IdCiudad);
                            $("#txtTelefono").val(datos.prov_Telefono);
                            $("#usu_Correo").val(datos.prov_Email);
                            $("#selec_IdTipoPersona").val(datos.prov_IdTipoPersona);
                        }
                        //bod.getIdDepartamentos();

                        $("#formProveedores").attr('action', 'javascript:bod.putProveedores(' + id + ');');
                        $("#modal_footerProveedores").empty();
                        $("#modal_footerProveedores").append(
                            '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                            '<span class="ti-close"></span> Cancelar' +
                            '</button>' +
                            '<button type="submit" class="btn btn-success btn-pill">' +
                            '<span class="ti-reload"></span> Actualizar' +
                            '</button>'
                        );

                        $("#modal-Proveedores").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información de los proveedores.',
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
     * putProveedores: Método para actualizar la 
     * información de una proveedores
     * @param type $id: llave primaria de la tabla proveedores
     */
    putProveedores(id) {
        var nombre = $("#txtProveedores").val();
        var razonsocial = $("#txtRazonSocial").val();
        var nit = $("#txtNit").val();
        var direccion = $("#txtDireccion").val();
        var idDepartamento = $("#selec_IdDepartamento").val();
        var idCiudad = $("#selec_IdCiudad").val();
        var telefono = $("#txtTelefono").val();
        var email = $("#usu_Correo").val();
        var idTipoPersona = $("#selec_IdTipoPersona").val();

        $.ajax({
            url: '../business/controller/class.proveedores.php',
            data: { funcion: 2, idpro: id, nombre: nombre, razonSocial: razonsocial, nit: nit, direccion: direccion,
                idDepartamento: idDepartamento, idCiudad: idCiudad, telefono: telefono, email: email, 
                idTipoPersona: idTipoPersona},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                /*  $('#loading').hide();
                 $('#wrapper').removeClass('body-load'); */
                if (arr.ok == 1) {

                    $("#modal-Proveedores").modal('hide');
                    bod.getProveedores();
                    swal({
                        type: 'success',
                        title: 'Proveedor actualizado',
                        text: 'Proveedor actualizado exitosamente',
                    });

                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Proveedor duplicado',
                        text: 'Ya existe una proveedor con la Razon Social',
                    });
                }else if (arr.ok == 3) {
                    swal({
                        type: 'warning',
                        title: 'Proveedor duplicado',
                        text: 'Ya existe una proveedor con el mismo NIT',
                    });
                }else if (arr.ok == 4) {
                    swal({
                        type: 'warning',
                        title: 'Proveedor duplicado',
                        text: 'Ya existe una proveedor con el mismo nombre',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la proveedores',
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
     * postProveedores: Método para crear proveedoress
     */
    postProveedores() {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load'); */
        var nombre = $("#txtProveedores").val();
        var razonsocial = $("#txtRazonSocial").val();
        var nit = $("#txtNit").val();
        var direccion = $("#txtDireccion").val();
        var idDepartamento = $("#selec_IdDepartamento").val();
        var idCiudad = $("#selec_IdCiudad").val();
        var telefono = $("#txtTelefono").val();
        var email = $("#usu_Correo").val();
        var idTipoPersona = $("#selec_IdTipoPersona").val();
        

        $.ajax({
            url: '../business/controller/class.proveedores.php',
            data: { funcion: 1, nombre: nombre, razonSocial: razonsocial, nit: nit, direccion: direccion,
                idDepartamento: idDepartamento, idCiudad: idCiudad, telefono: telefono, email: email, 
                idTipoPersona: idTipoPersona },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                /*  $('#loading').hide();
                 $('#wrapper').removeClass('body-load'); */
                if (arr.ok == 1) {
                    $("#modal-Proveedores").modal('hide');
                    swal({
                        type: 'success',
                        title: 'Proveedor creada',
                        text: 'Proveedor creada exitosamente',
                    });
                    bod.getProveedores();
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Proveedor duplicado',
                        text: 'Ya existe una proveedor con la Razon Social',
                    });
                }else if (arr.ok == 3) {
                    swal({
                        type: 'warning',
                        title: 'Proveedor duplicado',
                        text: 'Ya existe una proveedor con el mismo NIT',
                    });
                }else if (arr.ok == 4) {
                    swal({
                        type: 'warning',
                        title: 'Proveedor duplicado',
                        text: 'Ya existe una proveedor con el mismo nombre',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la  proveedores',
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
                        if(v['tip_Id'] != 1){
                            $("#selec_IdTipoPersona").append('<option value="' + v['tip_Id'] + '">' + v['tip_Descripcion'] + '</option>');
                        }                      
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
     * de las proveedoress
     * @param type $id_proveedores: llave primaria de la tabla proveedores
     * @param type $estado: estado actual de la proveedores
     */
    async putEstados(id_proveedores, estado) {
        var permiso = await _permisos.getPermisos(idRol, 1667);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar la proveedores?";
                var subtitle = "Una vez inactivado la proveedores, no podrá asignarla a un producto";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la proveedores?";
                var subtitle = "Una vez activado la proveedores, podrá ser asignarla a un producto";
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
                        url: '../business/controller/class.proveedores.php',
                        data: { funcion: 4, id: id_proveedores, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                bod.getProveedores();
                                swal({
                                    type: 'success',
                                    title: 'Proveedores actualizada',
                                    text: 'Proveedores actualizada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar la proveedores',
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
     * ProveedoresActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    ProveedoresActivo() {
        $(".Menuactivo").removeClass('expand');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DAdministracion").addClass('expand');
        $("#DAdministracion").addClass('active');
        $("#SubAdminsitracion").addClass('show');
        $("#SubMenuProveedores").addClass('active');
    }

}
const bod = new Proveedores();


bod.getTipoPersona();
bod.getIdDepartamentos();
bod.getIdMunicipios();
bod.getProveedores();
bod.ProveedoresActivo();