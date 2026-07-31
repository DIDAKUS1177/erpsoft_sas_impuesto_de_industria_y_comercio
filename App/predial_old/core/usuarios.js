/*    METDOS DEL MODULO DE USUARIOS    */

var enable = true;
var idRol = sessionStorage.getItem('id_Rol');

class Usuario {

    constructor() {}

    /**
     * crearUsuario: Método para abrir modal de creación de Usuario.
     */
    async crearUsuario() {

        //Parametro: 27 (2= Modulo Usuario, 7:Permiso Crear Usuario)
        var permiso = await _permisos.getPermisos(idRol, 27);

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
            $("#formCrearUsuario").trigger("reset");
            $("#btnCrearUsuario").empty();
            $("#btnCrearUsuario").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearUsuario").attr('action', 'javascript:usuario.postUsuario()');
            $("#modal-Usuario").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de usuarios 
     * @param type $arrFilter: Listado de objetos Usuarios
     */
    draw_table_documents(arrFilter) {
        
        //console.log('arr', arrFilter);
        $("#usuariosRegistrados").DataTable().destroy();
        $("#bodyUsuariosRegistrados").empty();
        for (let usu of arrFilter) {
            if (usu.usu_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Usuario";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Usuario";
            }

            if (usu.usu_Id >1) {

                $('#bodyUsuariosRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    usu.usu_Nombre +
                    '</td>' +
                    '<td>' +
                    usu.usu_NumeroDocumento +
                    '</td>' +
                    '<td>' +
                    usu.usu_Correo +
                    '</td>' +
                    '<td>' +
                    usu.usu_NombreRol +
                    '</td>' +

                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar Usuario" style="margin-right:5px" onclick="javascript:usuario.getUsuarioById(' + usu.usu_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:usuario.cambiarEstado(' + usu.usu_Id + ',' + usu.usu_Estado + ')">' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    '</td>' +

                    '</tr>'
                );
            }
        }
        usuario.init_table();
    }

    /**
     * init_table: Método para asignar la
     * propiedad DataTable() a la tabla de Usuarios
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
     * getUsuarios: Método para consultar Usuarios
     */
    getUsuarios() {
        
        /*$('#loading').show();
        $('#wrapper').addClass('body-load'); */ 
        $.ajax({
            url: '../business/controller/class.usuarios.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                //console.log('arr ', arr);
                /*$('#loading').hide();
                $('#wrapper').removeClass('body-load');*/
                if (arr.ok == 1) {
                    $("#bodyUsuariosRegistrados").empty();
                    usuario.draw_table_documents(arr.datos);
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
            }
        });
    }

    /**
     * getUsuarioById: Método para consultar la
     * información de un usuario
     * @param type $id: llave primaria de la tabla usuarios
     */
    async getUsuarioById(id) {
        
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
                url: '../business/controller/class.usuarios.php',
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
                            //$("#usu_Clave").val(datos.usu_Password);
                            $("#usu_Rol").val(datos.usu_Rol);
                            $("#usu_Usuario").val(datos.usu_Usuario);
                            $("#usu_Sede").val(datos.strIdSede);
                            $("#usu_Cajas").val(datos.strIdCaja);
                            $("#IdUsuarioCaja").val(datos.strIdUsuarioCaja);
                        }
                        $("#clave").attr('style', 'display:none');
                        $("#usu_Clave").removeAttr('required');
                        $("#usu_Clave").val('');
                        $("#formCrearUsuario").attr('action', 'javascript:usuario.editUsuario(' + id + ')');
                        $("#btnCrearUsuario").empty();
                        $("#btnCrearUsuario").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $("#modal-Usuario").modal('show');
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
                }
            });
        }
    }

    /**
     * postUsuario: Método para crear usuarios
     */
    postUsuario() {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var nombre = $("#usu_Nombre").val();
        var documento = $("#usu_Documento").val();
        var mail = $("#usu_Correo").val();
        var clave = $("#usu_Clave").val();
        var rol = $("#usu_Rol").val();
        var usu = $("#usu_Usuario").val();

        var sede = $("#usu_Sede").val();
        var caja = $("#usu_Cajas").val();
        console.log('rol ', rol);

        $.ajax({
            url: '../business/controller/class.usuarios.php',
            data: { funcion: 1, nombre: nombre, numeroDocumento: documento, 
                    email: mail, id_rol: rol, clave: clave, usuario: usu, sede: 1, caja: 1},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                //console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearUsuario").trigger("reset");
                    $("#modal-Usuario").modal('hide');
                    usuario.getUsuarios();
                    swal({
                        type: 'success',
                        title: 'Usuario creado',
                        text: 'Usuario creado exitosamente',
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
                        title: 'Uuario duplicado',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear el usuario',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * cambiarEstado: Método para cambiar el estado de los usuarios
     * @param type $id_usuario:  llave primaria de la tabla usuarios
     * @param type $estado: estado actual del usuario
     */
    async cambiarEstado(id_usuario, estado) {

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
                var title = "¿Está seguro de inactivar el usuario?";
                var subtitle = "Una vez inactivado, el usuario no podrá ingresar a la plataforma";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar el usuario?";
                var subtitle = "Una vez activado, el usuario podrá ingresar a la plataforma";
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
                        url: '../business/controller/class.usuarios.php',
                        data: { funcion: 4, id: id_usuario, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            //console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                usuario.getUsuarios();
                                swal({
                                    type: 'success',
                                    title: 'Usuario actualizado',
                                    text: 'Uusario actualizado exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el usuario',
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
     * editUsuario: Método para actualizar un Usuario
     * @param type $id: llave primaria de la tabla usuarios
     */
    editUsuario(id) {

        /*$('#loading').show();
        $('#wrapper').addClass('body-load');*/

        var nombre = $("#usu_Nombre").val();
        var usu = $("#usu_Usuario").val();
        var documento = $("#usu_Documento").val();
        var mail = $("#usu_Correo").val();
        var clave = $("#usu_Clave").val();
        var rol = $("#usu_Rol").val();
        var sede = $("#usu_Sede").val();
        var caja = $("#usu_Cajas").val();
        var IdUsuarioCaja = $("#IdUsuarioCaja").val();

        $.ajax({
            url: '../business/controller/class.usuarios.php',
            data: { funcion: 2, id: id, nombre: nombre, numeroDocumento: documento, IdUsuarioCaja: IdUsuarioCaja,
                    email: mail, clave: clave, id_rol: rol, usuario: usu, sede: 1, caja: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearUsuario").trigger("reset");
                    $("#modal-Usuario").modal('hide');
                    usuario.getUsuarios();
                    swal({
                        type: 'success',
                        title: 'Usuario actualizado',
                        text: 'Usuario actualizado exitosamente',
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
                        title: 'Uuario duplicado',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar el usuario',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                swal({
                    type: 'error',
                    title: 'Error',
                    text: 'No se pudo actualizar el usuario',
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
     * getIdSedes: Método para consutlar los 
     * Sedes
     */
    getIdSedes() {
        /* $('#loading').show();
            $('#wrapper').addClass('body-load');  */
        
        $("#usu_Sede").empty();            
        $("#usu_Sede").append('<option value="">Seleccione una Sede</option>');
        $("#usu_Sede").append('<option value="0">No Aplica</option>');

        $.ajax({
            url: '../business/controller/class.empresa.php',
            data: { funcion: 5 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                /*$('#loading').hide();
                $('#wrapper').removeClass('body-load');*/
                if (arr.ok == 1) {

                    $.each(arr.datos, function(k, v) {
                        $("#usu_Sede").append('<option value="' + v['seem_Id'] + '">' + v['seem_Nombre'] + '</option>');
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
     * getIdCajas: Método para consutlar los 
     * cajas
     */
    getIdCajas() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
         var id_sede = $("#usu_Sede").val();

        $("#usu_Cajas").empty();
        $("#usu_Cajas").append('<option value="">Seleccione una Caja</option>');
        $("#usu_Cajas").append('<option value="0">No Aplica</option>');
        $.ajax({
            url: '../business/controller/class.sedesEmpresaCajas.php',
            data: { funcion: 3, IdSedeEmpresa :id_sede },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                /*$('#loading').hide();
                $('#wrapper').removeClass('body-load');*/

                if (arr.ok == 1) {

                    $.each(arr.datos, function(k, v) {
                        $("#usu_Cajas").append('<option value="' + v['seemca_Id'] + '">' + v['seemca_Nombre'] + '</option>');
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
     * UsuarioActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    UsuarioActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DConfiguracion").addClass('expand active');
        $("#DConfiguracion").addClass('active');
        $("#SubConfiguracion").addClass('show');
        $("#SubMenuUsuario").addClass('active');
    }

}

const usuario = new Usuario();

usuario.getUsuarios();
usuario.getRoles();
usuario.UsuarioActivo();

usuario.getIdCajas();
usuario.getIdSedes();