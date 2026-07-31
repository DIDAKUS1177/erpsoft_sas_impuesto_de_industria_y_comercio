/*    METDOS DEL MODULO DE DEPENDENCIA    */

var enable = true;
var idRol = localStorage.getItem('id_Rol');

class GrupoTarifa {

    constructor() {}

    /**
     * crearUsuario: Método para abrir modal de creación de ActividadesComercio.
     */
    async crearGrupoTarifa() {

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
            $("#formCrearGrupoTarifa").trigger("reset");
            $("#btnCrearGrupoTarifa").empty();
            $("#btnCrearGrupoTarifa").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearGrupoTarifa").attr('action', 'javascript:grupoTarifa.postGrupoTarifa()');
            $('#modal-GrupoTarifa').modal({backdrop: 'static', keyboard: false})
            $("#modal-GrupoTarifa").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de ActividadesComercio 
     * @param type $arrFilter: Listado de objetos ActividadesComercio
     */
    draw_table_documents(arrFilter) {

        $("#grupoTarifaRegistrados").DataTable().destroy();
        $("#bodyGrupoTarifaRegistrados").empty();
        for (let dep of arrFilter) {
            if (dep.gru_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Grupo Tarifa";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Grupo Tarifa";
            }

                $('#bodyGrupoTarifaRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    dep.gru_Codigo +
                    '</td>' +
                    '<td>' +
                    dep.gru_Nombre +
                    '</td>' +
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar Grupo Tarifa" style="margin-right:5px" onclick="javascript:grupoTarifa.getGrupoTarifaById(' + dep.gru_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:grupoTarifa.cambiarEstado(' + dep.gru_Id + ',' + dep.gru_Estado + ')">' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    '</td>' +
                    '</tr>'
                );
            
        }
        grupoTarifa.init_table();
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
            columnDefs: [
                { targets: "datatable-nosort", orderable: false,},
                { "width": "10%", "targets": 0 },
                { "width": "20%", "targets": 1 },
                { "width": "20%", "targets": 2 }
            ],
            "lengthMenu": [
                [5, 10, 25, 50, -1],
                [5, 10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'grupos tarifa registrados',
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
     * getDependencia: Método para consultar grupos tarifa
     */
    getGrupoTarifa() {
        
        $.ajax({
            url: '../business/controller/class.grupoTarifa.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                if (arr.ok == 1) {
                    $("#bodyGrupoTarifaRegistrados").empty();
                    grupoTarifa.draw_table_documents(arr.datos);
                } else {
                    $("#grupoTarifaRegistrados").DataTable().destroy();
                    grupoTarifa.init_table();
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
    async getGrupoTarifaById(id) {
        
        $('#loading').show();
        $('#wrapper').addClass('body-load');
        var permiso = await _permisos.getPermisos(idRol, 312);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuníquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.grupoTarifa.php',
                data: { funcion: 3, gru_Id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        console.log('arr editar ', arr);
                        for (let datos of arr.datos) {
                            $("#gru_Codigo").val(datos.gru_Codigo);
                            $("#gru_Nombre").val(datos.gru_Nombre);
                        }
                        $("#formCrearGrupoTarifa").attr('action', 'javascript:grupoTarifa.editGrupoTarifa(' + id + ')');
                        $("#btnCrearGrupoTarifa").empty();
                        $("#btnCrearGrupoTarifa").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $('#modal-GrupoTarifa').modal({backdrop: 'static', keyboard: false})
                        $("#modal-GrupoTarifa").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información delos Grupo Tarifa',
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
     * postUsuario: Método para crear ActividadesComercio
     */
    postGrupoTarifa() {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        // Tomamos los valores del formulario
        var gru_Codigo = $("#gru_Codigo").val();
        var gru_Nombre = $("#gru_Nombre").val();

        // Luego podrías construir un objeto formData o un data: {} en tu ajax con estas variables
        var formData = {
        funcion: 1,
        gru_Codigo: gru_Codigo,
        gru_Nombre: gru_Nombre
        };

        $.ajax({
            url: '../business/controller/class.grupoTarifa.php',
            data: formData,
            dataType: "json",
            type: "POST",
            success: function(arr) {
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearGrupoTarifa").trigger("reset");
                    $("#modal-GrupoTarifa").modal('hide');
                    grupoTarifa.getGrupoTarifa();
                    swal({
                        type: 'success',
                        title: 'Grupo Tarifa creada',
                        text: 'Grupo Tarifa creada exitosamente',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear el Grupo Tarifa',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * cambiarEstado: Método para cambiar el estado de los ActividadesComercio
     * @param type $id_usuario:  llave primaria de la tabla ActividadesComercio
     * @param type $estado: estado actual del ActividadesComercio
     */
    async cambiarEstado(id_actividadesComercio, estado) {

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
                var title = "¿Está seguro de inactivar el grupo de tarifa?";
                var subtitle = "Una vez inactivado, el grupo de tarifa no podrá usarse";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar el grupo de tarifa?";
                var subtitle = "Una vez activada, el grupo de tarifa podrá usarse";
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
                        url: '../business/controller/class.grupoTarifa.php',
                        data: { funcion: 4, gru_Id: id_actividadesComercio, gru_Estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {

                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                grupoTarifa.getGrupoTarifa();
                                swal({
                                    type: 'success',
                                    title: 'Grupo Tarifa actualizados',
                                    text: 'Grupo Tarifa actualizados exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el Grupo Tarifa',
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
     * editUsuario: Método para actualizar un ActividadesComercio
     * @param type $id: llave primaria de la tabla ActividadesComercio
     */
    editGrupoTarifa(id) {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var gru_Codigo = $("#gru_Codigo").val();
        var gru_Nombre = $("#gru_Nombre").val();

        // Luego podrías construir un objeto formData o un data: {} en tu ajax con estas variables
        var formData = {
        funcion: 2,
        gru_Id    : id,
        gru_Codigo: gru_Codigo,
        gru_Nombre: gru_Nombre
        };

        $.ajax({
            url: '../business/controller/class.grupoTarifa.php',
            data: formData,
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearGrupoTarifa").trigger("reset");
                    $("#modal-GrupoTarifa").modal('hide');
                    grupoTarifa.getGrupoTarifa();
                    swal({
                        type: 'success',
                        title: 'Grupo Tarifa actualizado',
                        text: 'Grupo Tarifa actualizado exitosamente',
                    });
                }else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar el Grupo Tarifa',
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
        $("#accordion-menu li").removeClass("active show");
        $("#accordion-menu .submenu").css("display", "none");

        $("#MICAAlcaldia").addClass("active show");
        $("#SubICAAlcaldia").css("display", "block");

        $("#MICA_DatosBasicos").addClass("active show");
        $("#SubICA_DatosBasicos").css("display", "block");

        $("#ICA_GrupoTarifario").addClass("active");
    }
}

const grupoTarifa = new GrupoTarifa();

grupoTarifa.getGrupoTarifa();
grupoTarifa.UsuarioActivo();
