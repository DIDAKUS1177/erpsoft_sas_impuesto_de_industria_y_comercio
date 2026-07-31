/*    METDOS DEL MODULO DE DEPENDENCIA    */

var enable = true;
var idRol = localStorage.getItem('id_Rol');

class Dependencia {

    constructor() {}

    /**
     * crearUsuario: Método para abrir modal de creación de Dependencia.
     */
    async crearDependencia() {

        //Parametro: 27 (2= Modulo Usuario, 7:Permiso Crear Usuario)
        var permiso = await _permisos.getPermisos(idRol, 311);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $("#formCrearDependencia").trigger("reset");
            $("#btnCrearDependencia").empty();
            $("#btnCrearDependencia").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearDependencia").attr('action', 'javascript:dependencia.postDependencia()');
            $('#modal-Dependencia').modal({backdrop: 'static', keyboard: false})
            $("#modal-Dependencia").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de Dependencia 
     * @param type $arrFilter: Listado de objetos Dependencia
     */
    draw_table_documents(arrFilter) {
        
        $("#dependenciaRegistrados").DataTable().destroy();
        $("#bodyDependenciaRegistrados").empty();
        for (let dep of arrFilter) {
            if (dep.dep_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Dependencia";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Dependencia";
            }

                $('#bodyDependenciaRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    dep.dep_Nombre +
                    '</td>' +
                    '<td>' +
                    dep.dep_Descripcion +
                    '</td>' +

                    '<td align="center">' +
                    dep.strResponsable +
                    '</td>' +
                    
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar Dependencia" style="margin-right:5px" onclick="javascript:dependencia.getDependenciaById(' + dep.dep_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:dependencia.cambiarEstado(' + dep.dep_Id + ',' + dep.dep_Estado + ')">' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    '</td>' +
//                    '<td align="center">' +
//                    '<button type="button" class="btn btn-social-icon btn-primary " data-toggle="tooltip" title="Gestionar Responsables" style="margin-right:5px" onclick="javascript:dependencia.gestionarResponables(' + dep.dep_Id + ','+"'"+ dep.dep_Nombre +"'"+')">' +
//                    '<i class="dw dw-add"></i>' +
//                    '</button>' +
//                     '</td>' +

                    '</tr>'
                );
            
        }
        dependencia.init_table();
    }

    /**
     * init_table: Método para asignar la
     * propiedad DataTable() a la tabla de Dependencia
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
                'emptyTable': 'Dependencia registrados',
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
     * getDependencia: Método para consultar Dependencia
     */
    getDependencia() {
        
        $.ajax({
            url: '../business/controller/class.dependencia.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                if (arr.ok == 1) {
                    $("#bodyDependenciaRegistrados").empty();
                    dependencia.draw_table_documents(arr.datos);
                } else {
                    $("#dependenciaRegistrados").DataTable().destroy();
                    dependencia.init_table();
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * getUsuarioById: Método para consultar la
     * información de un Dependencia
     * @param type $id: llave primaria de la tabla Dependencia
     */
    async getDependenciaById(id) {
        
        $('#loading').show();
        $('#wrapper').addClass('body-load');
        var permiso = await _permisos.getPermisos(idRol, 312);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.dependencia.php',
                data: { funcion: 3, dep_Id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        console.log('arr editar ', arr);
                        for (let datos of arr.datos) {
                            $("#dep_Nombre").val(datos.dep_Nombre);
                            $("#dep_Descripcion").val(datos.dep_Descripcion);
                            $("#dep_Sigla").val(datos.dep_Sigla);
                            $("#dep_Codigo").val(datos.dep_Codigo);
                            $("#dep_IdResponsable").val(datos.dep_IdResponsable);
                        }
                        $("#formCrearDependencia").attr('action', 'javascript:dependencia.editDependencia(' + id + ')');
                        $("#btnCrearDependencia").empty();
                        $("#btnCrearDependencia").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $('#modal-Dependencia').modal({backdrop: 'static', keyboard: false})
                        $("#modal-Dependencia").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información dela Dependencia',
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
     * postUsuario: Método para crear Dependencia
     */
    postDependencia() {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var nombre = $("#dep_Nombre").val();
        var descripcion = $("#dep_Descripcion").val();
        var sigla = $("#dep_Sigla").val();
        var codigo = $("#dep_Codigo").val();
        var idResponsable = $("#dep_IdResponsable").val();

        $.ajax({
            url: '../business/controller/class.dependencia.php',
            data: { funcion: 1, dep_Nombre: nombre, dep_Descripcion: descripcion,
                dep_Sigla: sigla, dep_Codigo: codigo, dep_IdResponsable: idResponsable},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearDependencia").trigger("reset");
                    $("#modal-Dependencia").modal('hide');
                    dependencia.getDependencia();
                    swal({
                        type: 'success',
                        title: 'Dependencia creada',
                        text: 'Dependencia creada exitosamente',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la Dependencia',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * cambiarEstado: Método para cambiar el estado de los Dependencia
     * @param type $id_usuario:  llave primaria de la tabla Dependencia
     * @param type $estado: estado actual del Dependencia
     */
    async cambiarEstado(id_dependencia, estado) {

        var permiso = await _permisos.getPermisos(idRol, 313);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar la Dependencia?";
                var subtitle = "Una vez inactivado la Dependencia no podra usarse";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la Dependencia?";
                var subtitle = "Una vez activado, la Dependencia podrá usarse";
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
                        url: '../business/controller/class.dependencia.php',
                        data: { funcion: 4, id: id_dependencia, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {

                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                dependencia.getDependencia();
                                swal({
                                    type: 'success',
                                    title: 'Dependencia actualizado',
                                    text: 'Dependencia actualizado exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el dependencia',
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
     * editUsuario: Método para actualizar un dependencia
     * @param type $id: llave primaria de la tabla dependencia
     */
    editDependencia(id) {

        /*$('#loading').show();
        $('#wrapper').addClass('body-load');*/

        var nombre = $("#dep_Nombre").val();
        var descripcion = $("#dep_Descripcion").val();
        var sigla = $("#dep_Sigla").val();
        var codigo = $("#dep_Codigo").val();
        var idResponsable = $("#dep_IdResponsable").val();

        $.ajax({
            url: '../business/controller/class.dependencia.php',
            data: { funcion: 2, dep_Id: id, dep_Nombre: nombre, dep_Descripcion: descripcion,
                dep_Sigla: sigla, dep_Codigo: codigo, dep_IdResponsable: idResponsable},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearDependencia").trigger("reset");
                    $("#modal-Dependencia").modal('hide');
                    dependencia.getDependencia();
                    swal({
                        type: 'success',
                        title: 'Dependencia actualizado',
                        text: 'Dependencia actualizado exitosamente',
                    });
                }else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar el Dependencia',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * getResponables: Método para consultar los Responsables
     */
    getResponsables() {

        $.ajax({
            url: '../business/controller/class.usuarios.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                $("#dep_IdResponsable").empty();
                $("#dep_IdResponsable").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#dep_IdResponsable").append('<option value="' + v['usu_Id'] + '">' + v['usu_Nombre'] + '</option>');
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
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

        $("#DCatalogos").addClass('expand active');
        $("#DCatalogos").addClass('active');
        $("#SubCatalogos").addClass('show');
        $("#SubDependencias").addClass('active');
    }
}

const dependencia = new Dependencia();

dependencia.getDependencia();
dependencia.UsuarioActivo();
dependencia.getResponsables();
