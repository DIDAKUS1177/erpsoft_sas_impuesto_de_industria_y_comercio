/*    METODOS DEL MODULO DE PETICIONES    */

var enable = true;
var idRol = localStorage.getItem('id_Rol');

class Peticiones {

    constructor() {}

    /**
     * crearPeticiones: Método para abrir modal de creación de Peticiones.
     */
    async crearPeticiones() {

        //Parametro: 27 (2= Modulo Peticiones, 7:Permiso Crear Peticiones)
        var permiso = await _permisos.getPermisos(idRol, 728);

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
            $("#formCrearPeticiones").trigger("reset");
            $("#btnCrearPeticiones").empty();
            $("#btnCrearPeticiones").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearPeticiones").attr('action', 'javascript:peticiones.postPeticiones()');
            $('#modal-Peticiones').modal({backdrop: 'static', keyboard: false})
            $("#modal-Peticiones").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de peticiones 
     * @param type $arrFilter: Listado de objetos Peticiones
     */
    draw_table_documents(arrFilter) {
        
        //console.log('arr', arrFilter);
        $("#peticionesRegistrados").DataTable().destroy();
        $("#bodyPeticionesRegistrados").empty();
        for (let pe of arrFilter) {
            if (pe.pe_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Peticiones";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Peticiones";
            }

            if (pe.pe_NumeroDocumento != '') {
                var numeroDocumento = usu.usu_NumeroDocumento;
            } else {
                var numeroDocumento = "Sin Registro";
            }

            if (pe.pe_Correo != '') {
                var correo = usu.usu_Correo;
            } else {
                var correo = "Sin Registro";
            }

                $('#bodyPeticionesRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    pe.pe_Nombre +
                    '</td>' +
                    '<td>' +
                    numeroDocumento +
                    '</td>' +
                    '<td>' +
                    correo +
                    '</td>' +
                    '<td>' +
                    pe.pe_NombreRol +
                    '</td>' +

                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar Peticiones" style="margin-right:5px" onclick="javascript:peticiones.getPeticionesById(' + usu.usu_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:peticiones.cambiarEstado(' + usu.usu_Id + ',' + usu.usu_Estado + ')">' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    '</td>' +

                    '</tr>'
                );
            
        }
        peticiones.init_table();
    }

    /**
     * init_table: Método para asignar la
     * propiedad DataTable() a la tabla de Peticiones
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
                'emptyTable': 'Peticiones registrados',
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
     * getPeticiones: Método para consultar Peticiones
     */
    getPeticiones() {
        
        /*$('#loading').show();
        $('#wrapper').addClass('body-load'); */ 
        $.ajax({
            url: '../business/controller/class.peticiones.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                /*$('#loading').hide();
                $('#wrapper').removeClass('body-load');*/
                if (arr.ok == 1) {
                    $("#bodyPeticionesRegistrados").empty();
                    peticiones.draw_table_documents(arr.datos);
                } else {
                    $("#peticionesRegistrados").DataTable().destroy();
                    peticiones.init_table();
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * getPeticionesById: Método para consultar la
     * información de un peticiones
     * @param type $id: llave primaria de la tabla peticiones
     */
    async getPeticionesById(id) {
        
        /*$('#loading').show();
        $('#wrapper').addClass('body-load');*/
        var permiso = await _permisos.getPermisos(idRol, 28);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.peticiones.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        console.log('arr editar ', arr);
                        for (let datos of arr.datos) {
                            $("#usu_Nombre").val(datos.usu_Nombre);
                            $("#usu_Documento").val(datos.usu_NumeroDocumento);
                            $("#usu_Correo").val(datos.usu_Correo);
                            $("#usu_Rol").val(datos.usu_Rol);
                            $("#usu_Peticiones").val(datos.usu_Peticiones);
                            $("#usu_Sede").val(datos.strIdSede);
                            $("#usu_Cajas").val(datos.strIdCaja);
                            $("#IdPeticionesCaja").val(datos.strIdPeticionesCaja);
                        }
                        $("#clave").attr('style', 'display:none');
                        $("#usu_Clave").removeAttr('required');
                        $("#usu_Clave").val('');
                        $("#formCrearPeticiones").attr('action', 'javascript:peticiones.editPeticiones(' + id + ')');
                        $("#btnCrearPeticiones").empty();
                        $("#btnCrearPeticiones").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $('#modal-Peticiones').modal({backdrop: 'static', keyboard: false})
                        $("#modal-Peticiones").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información del Peticiones',
                        });
                    }
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                }
            });
        }
    }

    /**
     * postPeticiones: Método para crear peticiones
     */
    postPeticiones() {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var pe_IdTipoPeticion = $("#pe_IdTipoPeticion").val();
        var doc_Nombre = $("#doc_Nombre").val();
        var mail = $("#usu_Correo").val();
        var clave = $("#usu_Clave").val();
        var rol = $("#usu_Rol").val();
        var usu = $("#usu_Peticiones").val();

        var nombre = $("#usu_Nombre").val();
        var documento = $("#usu_Documento").val();
        var mail = $("#usu_Correo").val();
        var clave = $("#usu_Clave").val();
        var rol = $("#usu_Rol").val();
        var usu = $("#usu_Peticiones").val();

        var sede = $("#usu_Sede").val();
        var caja = $("#usu_Cajas").val();

        $.ajax({
            url: '../business/controller/class.peticiones.php',
            data: { funcion: 1, nombre: nombre, numeroDocumento: documento, 
                    email: mail, id_rol: rol, clave: clave, peticiones: usu, sede: sede, caja: caja},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                //console.log('Peticiones', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearPeticiones").trigger("reset");
                    $("#modal-Peticiones").modal('hide');
                    peticiones.getPeticiones();
                    swal({
                        type: 'success',
                        title: 'Peticiones creado',
                        text: 'Peticiones creado exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Email duplicado',
                        text: arr.mensaje,
                    });
                } else if (arr.ok == 3) {
                    swal({
                        type: 'warning',
                        title: 'Identificación duplicada',
                        text: arr.mensaje,
                    });
                }else if (arr.ok == 4) {
                    swal({
                        type: 'warning',
                        title: 'Peticiones duplicado',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear el peticiones',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * cambiarEstado: Método para cambiar el estado de los peticiones
     * @param type $id_peticiones:  llave primaria de la tabla peticiones
     * @param type $estado: estado actual del peticiones
     */
    async cambiarEstado(id_peticiones, estado) {

        var permiso = await _permisos.getPermisos(idRol, 29);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar el peticiones?";
                var subtitle = "Una vez inactivado, el peticiones no podrá ingresar a la plataforma";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar el peticiones?";
                var subtitle = "Una vez activado, el peticiones podrá ingresar a la plataforma";
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
                        url: '../business/controller/class.peticiones.php',
                        data: { funcion: 4, id: id_peticiones, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            //console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                peticiones.getPeticiones();
                                swal({
                                    type: 'success',
                                    title: 'Peticiones actualizado',
                                    text: 'Uusario actualizado exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el peticiones',
                                });
                            }
                        },
                        error: function(XMLHttpRequest, textStatus, errorThrown) {
                            console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                        }
                    });
                }
            })
        }
    }

    /**
     * editPeticiones: Método para actualizar un Peticiones
     * @param type $id: llave primaria de la tabla peticiones
     */
    editPeticiones(id) {

        /*$('#loading').show();
        $('#wrapper').addClass('body-load');*/

        var nombre = $("#usu_Nombre").val();
        var usu = $("#usu_Peticiones").val();
        var documento = $("#usu_Documento").val();
        var mail = $("#usu_Correo").val();
        var clave = $("#usu_Clave").val();
        var rol = $("#usu_Rol").val();
        var sede = $("#usu_Sede").val();
        var caja = $("#usu_Cajas").val();
        var IdPeticionesCaja = $("#IdPeticionesCaja").val();

        $.ajax({
            url: '../business/controller/class.peticiones.php',
            data: { funcion: 2, id: id, nombre: nombre, numeroDocumento: documento, IdPeticionesCaja: IdPeticionesCaja,
                    email: mail, clave: clave, id_rol: rol, peticiones: usu, sede: sede, caja: caja },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearPeticiones").trigger("reset");
                    $("#modal-Peticiones").modal('hide');
                    peticiones.getPeticiones();
                    swal({
                        type: 'success',
                        title: 'Peticiones actualizado',
                        text: 'Peticiones actualizado exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Email duplicado',
                        text: arr.mensaje,
                    });
                } else if (arr.ok == 3) {
                    swal({
                        type: 'warning',
                        title: 'Identificación duplicada',
                        text: arr.mensaje,
                    });
                }else if (arr.ok == 4) {
                    swal({
                        type: 'warning',
                        title: 'Peticiones duplicado',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar el peticiones',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                swal({
                    type: 'error',
                    title: 'Error',
                    text: 'No se pudo actualizar el peticiones',
                });
            }
        });
    }

    /**
     * getRoles: Método para consutlar los Roles activos
     */
    getRoles() {

        $('#loading').show();
        $('#wrapper').addClass('body-load');
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
                    $("#usu_Rol").append('<option value="">No Hay Datos</option>');
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * PeticionesActivo: Método para activar el menú y facilitar
     * la navegación al peticiones permitendole saber en
     * que lugar esta
     */
    PeticionesActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DConfiguracion").addClass('expand active');
        $("#DConfiguracion").addClass('active');
        $("#SubConfiguracion").addClass('show');
        $("#SubMenuPeticiones").addClass('active');
    }
}

const peticiones = new Peticiones();

peticiones.getPeticiones();
peticiones.getRoles();
peticiones.PeticionesActivo();
