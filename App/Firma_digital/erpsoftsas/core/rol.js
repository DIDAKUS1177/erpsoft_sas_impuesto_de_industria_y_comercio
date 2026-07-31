/* MODULO DE ROLES */

// Declaración de variables
var ConfigPermisosRol = new Map();
var idRol = localStorage.getItem('id_Rol');
class Rol {

    constructor() {}

    /**
     * crearRol: Método para abrir modal de creación de Rol
     */
    async crearRol() {
        //Parametro: 11 (1= Modulo Roles, 2:Permiso Crear Role)
        var permiso = await _permisos.getPermisos(idRol, 12);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $("#txtRol").val('');
            $("#tituloModalRol").empty();
            $("#tituloModalRol").append('Crear rol');
            $("#form-editRol").attr('action', 'javascript:rol.postRoles();');
            $("#btnModalRol").empty();
            $("#btnModalRol").append('Crear');
            $("#modal-editRol").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de roles 
     * @param type $arrFilter: Listado de obejtos de tipo rol
     */
    draw_table_documents(arrFilter) {

        $("#tblRol").DataTable().destroy();
        $("#tbodyRol").empty();
        for (let rol of arrFilter) {
            if (rol.rol_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Rol";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Rol";
            }
            if (rol.rol_Id != 1) {
                $('#tbodyRol').append(
                    '<tr>' +
                    '<td>' +
                    rol.rol_Nombre +
                    '</td>' +

                    '<td align="center">' +
                    '<button type="button" class="mb-1 btn  btn-warning " data-toggle="tooltip" title="Editar Rol" style="margin-right:5px" onclick="javascript:rol.getRolesById(' + rol.rol_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +
                    '</td>' +
                    '<td align="center">' +
                    '<button type="button" class="mb-1 btn  ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  style="margin-right:5px" onclick="javascript:rol.putEstados(' + rol.rol_Id + ',' + rol.rol_Estado + ')">' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    '</td>' +
                    '<td align="center">' +
                    '<button type="button" class="mb-1 btn  btn-danger " data-toggle="tooltip" title="Asignar permisos"  onclick="javascript:rol.verPermisos(' + rol.rol_Id + ',' + "'" + rol.rol_Nombre + "'" + ')">' +
                    '<span class="ti-alert"></span>' +
                    '</button>' +
                    '</td>' +
                    '</tr>'
                );
            }

        }
        rol.init_table();
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
     * formatearFecha: Método para dar formato a las fechas
     * @param type $fecha: fecha 
     */
    formatearFecha(fecha) {
        var arrFecha = fecha.split('T');
        var newFecha = arrFecha[0];
        return newFecha;
    }

    /**
     * getRoles: Método para consultar los
     * roles creados en el sistema
     */
    getRoles() {

        /* $('#loading').removeAttr('hidden');
        $('#wrapper').addClass('body-load'); */
        $.ajax({
            url: '../business/controller/class.rol.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                //console.log('arr ', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    rol.draw_table_documents(arr.datos);
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
     * getRolesById: Método para consultar la 
     * información de un rol
     * @param type $id: llave primaria de la tabla rol 
     */
    async getRolesById(id) {
        
        /*$('#loading').show();
        $('#wrapper').addClass('body-load');*/
        var permiso = await _permisos.getPermisos(idRol, 13);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.rol.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {

                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#txtRol").val(datos.rol_Nombre);
                            $("#txtDescripcion").val(datos.rol_Descripcion);
                            $("#form-editRol").attr('action', 'javascript:rol.editRol(' + datos.rol_Id + ')')
                            $("#btnModalRol").empty();
                            $("#btnModalRol").append('Actualizar');
                        }
                        $("#tituloModalRol").empty();
                        $("#tituloModalRol").append('Actualizar Rol');
                        $("#modal-editRol").modal('show');
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
     * editRol: Método para actualizar la 
     * información de un rol
     * @param type $id: llave primaria de la tabla rol 
     */
    editRol(id) {

        var nombre = $("#txtRol").val();
        var descripcion = $("#txtDescripcion").val();

        $.ajax({
            url: '../business/controller/class.rol.php',
            data: { funcion: 2, nombre: nombre, descripcion: descripcion, id: id },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                //console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#modal-editRol").modal('hide');
                    swal({
                        type: 'success',
                        title: 'Rol actualizado',
                        text: 'Rol actualizado exitosamente',
                    });
                    rol.getRoles();
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Rol duplicado',
                        text: 'Ya existe un rol con el mismo nombre',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar el rol',
                    });
                }

            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                swal({
                    type: 'error',
                    title: 'Error',
                    text: 'No se pudo actualizar el rol',
                });
            }
        });
    }

    /**
     * postRoles: Método para crear un rol
     */
    postRoles() {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var nombre = $("#txtRol").val();
        var descripcion = $("#txtDescripcion").val();

        $.ajax({
            url: '../business/controller/class.rol.php',
            data: { funcion: 1, nombre: nombre, descripcion: descripcion },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                //console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {

                    $("#modal-editRol").modal('hide');
                    swal({
                        type: 'success',
                        title: 'Rol creado',
                        text: 'Rol creado exitosamente',
                    });
                    rol.getRoles();
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Rol duplicado',
                        text: 'Ya existe un rol con el mismo nombre',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear el rol',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                swal({
                    type: 'error',
                    title: 'Error',
                    text: 'No se pudo crear el rol',
                });
            }
        });
    }

    /**
     * putEstados: Método para actualizar el
     * estado de un rol
     * @param type $id_rol: llave primaria de la tabla rol 
     * @param type $estado: estado actual del rol 
     */
    async putEstados(id_rol, estado) {

        var permiso = await _permisos.getPermisos(idRol, 14);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar el rol?";
                var subtitle = "Una vez inactivado el rol, no podrá asignarlo a un usuario";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar el rol?";
                var subtitle = "Una vez activado el rol, podrá ser asignarlo a un usuario";
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
                        url: '../business/controller/class.rol.php',
                        data: { funcion: 4, id: id_rol, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                rol.getRoles();
                                swal({
                                    type: 'success',
                                    title: 'Rol actualizado',
                                    text: 'Rol actualizado exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el rol',
                                });
                            }
                        },
                        error: function(XMLHttpRequest, textStatus, errorThrown) {
                            console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                        }
                    });
                }
            });
        }
    }

    /**
     * asignarPermisos: Método para mostrar de forma
     * ordenada los modulos y submodulos de la aplicación
     * y marcar los submodulos a los que tiene acceso el rol
     * @param type $id_rol: llave primaria de la tabla rol 
     */
    asignarPermisos(id_rol) {
        $('#loading').show();
        $('#wrapper').addClass('body-load');
        $("#ListRoles").empty();
        $("#ContenidoSubModulo").empty();
        $.ajax({
            url: '../business/controller/class.modulos.php',
            data: { funcion: 3 },
            type: 'POST',
            dataType: 'json',
            success: function(mod) {
                if (mod.ok == 1) {
                    ConfigPermisosRol.set('modulos', mod.datos);
                    $.each(mod.datos, function(k, v) {
                        $("#ListRoles").append('<li id="rol' + v['mod_Id'] + '"><a href="javascript:rol.filtrarRol(' + v['mod_Id'] + ')">' + v['mod_Nombre'] + '</a></li>');
                        $.ajax({
                            url: '../business/controller/class.subModulos.php',
                            data: { funcion: 3, id_modulo: v['mod_Id'] },
                            type: 'POST',
                            dataType: 'json',
                            success: function(subMod) {
                                $('#loading').hide();
                                $('#wrapper').removeClass('body-load');
                                if (subMod.ok == 1) {
                                    $("#ContenidoSubModulo").append('<div class="" id="Submodulos' + v['mod_Id'] + '" style="display : none"></div>');
                                    $.each(subMod.datos, function(k, j) {

                                        $("#Submodulos" + v['mod_Id']).append(

                                            '<div class="col-sm-6" style="margin-top: 10px;">' +
                                            '<div class="input-group">' +
                                            '<span class="input-group-addon">' +
                                            '<input type="checkbox" class="minimal" name="subMod" id="subMod' + j['subMod_Id'] + '" value="' + j['subMod_Id'] + '">' +
                                            '</span>' +
                                            '<input type="text" class="form-control" value="' + j['subMod_Nombre'] + '" readonly="">' +
                                            '</div>' +
                                            '</div>'

                                        );
                                    });

//                                    $('input[type="checkbox"].minimal, input[type="radio"].minimal').iCheck({
//                                        checkboxClass: 'icheckbox_minimal-blue',
//                                        radioClass   : 'iradio_minimal-blue',
//                                    });

                                } else {
                                    swal("No se pudo cargar la información", "", "error");
                                }
                                $.ajax({
                                    url: '../business/controller/class.permisos.php',
                                    data: { funcion: 3, id_rol: id_rol, id_modulo: v['mod_Id'] },
                                    type: 'POST',
                                    dataType: 'json',
                                    success: function(info) {
                                        console.log('info ', info);
                                        if (info.ok == 1) {
                                            $.each(info.datos, function(k, s) {
                                                var check = $('#subMod' + s['per_IdSubModulo']).parent();
                                                check.attr('class', 'icheckbox_minimal-blue checked');
                                                check.attr('aria-checked', 'true');
                                                $("#subMod" + s['per_IdSubModulo']).attr('checked', '');
                                            });
                                        }
                                    }
                                });
                            },
                            error: function(XMLHttpRequest, textStatus, errorThrown) {
                                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                            }
                        });

                    });

                    //$("#modal-AsignarPermisos").modal('show');

                    $("#ltsPermisos").attr('style', 'display:block');
                    $("#ltsRol").attr('style', 'display:none');

                } else {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo cargar la información',
                    });
                }
            }
        });
    }

    /**
     * getModulos: Método para consultar los módulos
     * de la aplicación
     */
    getModulos() {
        return $.ajax({
            url: '../business/controller/class.modulos.php',
            data: { funcion: 3 },
            type: 'POST',
            dataType: 'json'
        });
    }

    /**
     * getSubModulos: Método para consultar los submódulos
     * de la aplicación
     * @param type $idModulo: llave primaria de la tabla modulos 
     */
    getSubModulos(idModulo) {
        return $.ajax({
            url: '../business/controller/class.subModulos.php',
            data: { funcion: 3, id_modulo: idModulo },
            type: 'POST',
            dataType: 'json'
        });
    }

    /**
     * getPermisos: Método para consultar los submódulos
     * de la aplicación a los que el rol tiene acceso
     * @param type $idRol: llave primaria de la tabla rol 
     * @param type $idModulo: llave primaria de la tabla modulos 
     */
    getPermisos(idRol, idModulo) {
        return $.ajax({
            url: '../business/controller/class.permisos.php',
            data: { funcion: 3, id_rol: idRol, id_modulo: idModulo },
            type: 'POST',
            dataType: 'json'
        });
    }

    /**
     * filtrarRol: Método para mostrar los submódulos
     * de la aplicación dependiendo del
     * módulo seleccionado
     * @param type $id_modulo: llave primaria de la tabla modulos 
     */
    filtrarRol(id_modulo) {
        var modulos = ConfigPermisosRol.get('modulos');
        $("li").removeClass("active");
        $("#rol" + id_modulo).addClass('active');

        $.each(modulos, function(k, v) {
            var subModulos = document.getElementById('Submodulos' + v['mod_Id']);
            subModulos.setAttribute('style', 'display:none');
        });
        var subMod = document.getElementById('Submodulos' + id_modulo);
        subMod.removeAttribute('style');
    }

    /**
     * asignarPermisosRol: Método para asignar los permisos a un rol
     * @param type $id_rol: llave primaria de la tabla rol 
     */
    asignarPermisosRol(id_rol) {
        var contadorPermisos = 0;
        var check_no = 0;
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load');*/
        $.ajax({
            url: '../business/controller/class.subModulos.php',
            data: { funcion: 3 },
            type: 'POST',
            dataType: 'json',
            success: function(data) {

                if (data.ok == 1) {
                    var json = "[";

                    $.each(data.datos, function(k, v) {
                        contadorPermisos = contadorPermisos + 1;
                        if ($("#subMod" + v['subMod_Id']).length > 0) {
                            var Smodulo = document.getElementById("subMod" + v['subMod_Id']);

                            if (document.getElementById("subMod" + v['subMod_Id']).checked) {

                                json += "{";
                                json += "\"id_modulo\":\"" + v['subMod_IdModulo'] + "\",";
                                json += "\"id_sub_modulo\":\"" + v['subMod_Id'] + "\"";
                                json += "},";
                            } else {
                                check_no = check_no + 1;
                            }
                        }

                    });

                    json = json.substring(0, json.length - 1);
                    json += "]";
                    var valor = JSON.parse(json);
                    console.log('data: ', valor);
                    if (check_no < contadorPermisos) {
                        $.ajax({
                            url: '../business/controller/class.permisos.php',
                            data: { funcion: 1, id_rol: id_rol, data: json },
                            type: 'POST',
                            dataType: 'json',
                            success: function(json) {
                                /* $('#loading').hide();
                                $('#wrapper').removeClass('body-load'); */
                                if (json.ok = 1) {
                                    $("#modal-AsignarPermisos").modal('hide');
                                    swal({
                                        type: 'success',
                                        title: 'Permisos asignados',
                                        text: 'Los permisos fueron asignados correctamente al rol',
                                    });
                                    rol.getRoles();
                                } else {
                                    swal({
                                        type: 'error',
                                        title: 'Error',
                                        text: 'No se pudo asignar los permisos al rol',
                                    });
                                }
                            }
                        });
                    } else {
                        $('#loading').hide();
                        $('#wrapper').removeClass('body-load');
                        swal({
                            type: 'warning',
                            title: 'Seleccione al menos un permiso',
                            text: '',
                        });
                    }

                }

            }
        });
    }
    

    /**
     * verSubmodulo: Método para dejar seleccionado
     * el módulo al que el usuario hace clic 
     *  @param type $idMod: llave primaria de la tabla modulos 
     */
    verSubmodulo(idMod) {
        $(".act").removeClass('active');
        $("#Mod" + idMod).addClass('active');
        1

        $(".sub").removeAttr('style')
        $(".sub").attr('style', 'display : none');
        $("#Submodulos" + idMod).removeAttr('style');
    }

    /**
     * verPermisos: Método para ver los permisos del rol
     * @param type $idRol: llave primaria de la tabla rol
     * @param type $NomRol: Nombre del rol
     */
    async verPermisos(idRol, NomRol) {
        try {
            var info = true;
            var modulo = await rol.getModulos();
            var idRologueado = localStorage.getItem('id_Rol');
            var permiso = await _permisos.getPermisos(idRologueado, 15);
            console.log('moddd', idRol);

            if (permiso.ok != 1) {
                swal({
                    type: 'warning',
                    title: 'Error de privilegios',
                    text: 'Usted no tiene los privilegios para realizar esta acción,' +
                        'para obtenerlos comuniquese con el admininstrador del sistema',
                });
            } else {
                if (modulo.ok == 1) {

                    console.log('mod', modulo);
                    ConfigPermisosRol.set('modulos', modulo.datos);
                    $("#tituloRol").empty();
                    $("#tituloRol").append('Permisos del rol ' + NomRol + '');
                    $("#ltsModulos").empty();
                    $("#ltsModulos").append('<div class="list-group">');
                    $("#ltsSubModulos").empty();
                    var active = false;
                    $.each(modulo.datos, async function(k, v) {
                        if (active) {
                            var act = '';
                            var style = 'style="display:none;"'
                        } else {
                            var act = 'active';
                            active = true
                            var style = '';
                        }
                        $("#ltsModulos").append('<a href="javascript:rol.verSubmodulo(' + v['mod_Id'] + ');" class="list-group-item list-group-item-action ' + act + ' act" id="Mod' + v['mod_Id'] + '" >' + v['mod_Nombre'] + '</a>');
                        var subMod = await rol.getSubModulos(v['mod_Id']);
                        console.log('subMod', subMod);
                        if (subMod.ok == 1) {
                            $("#ltsSubModulos").append('<div class="row sub" id="Submodulos' + v['mod_Id'] + '" ' + style + '></div>');
                            $.each(subMod.datos, async function(k, j) {
    
                                $("#Submodulos" + v['mod_Id']).append(
                                    '<div class="col-sm-12 col-md-4 col-lg-4" style="margin-top:1%">' +
                                    '<input type="checkbox" class="switch-btn" data-color="#41ccba" id="subMod' + j['subMod_Id'] + '" value="' + j['subMod_Id'] + '"> ' +
                                    '<label class="ccontrol-label">' + j['subMod_Nombre'] + '' +
                                    '</label>' +
                                    '</div>'
                                );
                            });
    
                            var permisos = await rol.getPermisos(idRol, v['mod_Id']);
                            if (permisos.ok != 1) {
                                info = false
                            } else {
                                $.each(permisos.datos, function(k, s) {
                                    $("#subMod" + s['per_IdSubModulo']).attr('checked', true);
                                });
                            }
    
                            rol.initSwitch(subMod.datos);
    
                        } else {
                            info = false
                        }
                    });
    
                    $("#ltsModulos").append('</div>');
                    $("#ltsRol").attr('hidden', true);
                    $("#ltsPermisos").removeAttr('hidden');
                    $("#btnAsignarPermisos").attr('onclick', 'javascript:rol.asignarPermisosRol(' + idRol + ')');
                } else {
                    info = false
                }
                if (!info) {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo consultar la información',
                    });
                } else {
                    // rol.asginarPer(NomRol);
                }
            }

        } catch (error) {

        }
    }

    /**
     * initSwitch: Método para asginar la propiedad
     * swicth a los checkbox de los submodulos
     * @param type $data: listado de submodulos
     */
    initSwitch(data) {
        $.each(data, async function(k, j) {
            $('#subMod' + j['subMod_Id']).each(function() {
                new Switchery($(this)[0], $(this).data());
            });
        });

    }

    /**
     * back: Método para devolver a la interface
     * del listado de roles
     */
    back() {
        $("#ltsPermisos").attr('hidden', true);
        $("#ltsRol").removeAttr('hidden');
    }

    /**
     * RolActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    RolActivo() {
       $("#accordion-menu li").removeClass("active show");
        $("#accordion-menu .submenu").css("display", "none");
        
        $("#MConfig").addClass("active show");
        $("#SubConfig").css("display", "block");
        $("#Config_Roles").addClass("active");
    }
}

const rol = new Rol();

rol.getRoles();
rol.RolActivo();
