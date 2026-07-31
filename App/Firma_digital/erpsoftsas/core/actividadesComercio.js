/*    METDOS DEL MODULO DE DEPENDENCIA    */

var enable = true;
var idRol = localStorage.getItem('id_Rol');

class ActividadesComercio {

    constructor() {}

    /**
     * crearUsuario: Método para abrir modal de creación de ActividadesComercio.
     */
    async crearActividadesComercio() {

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
            $("#formCrearActividadesComercio").trigger("reset");
            $("#btnCrearActividadesComercio").empty();
            $("#btnCrearActividadesComercio").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearActividadesComercio").attr('action', 'javascript:actividadesComercio.postActividadesComercio()');
            $('#modal-ActividadesComercio').modal({backdrop: 'static', keyboard: false})
            $("#modal-ActividadesComercio").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de ActividadesComercio 
     * @param type $arrFilter: Listado de objetos ActividadesComercio
     */
    draw_table_documents(arrFilter) {
        
        $("#actividadesComercioRegistrados").DataTable().destroy();
        $("#bodyActividadesComercioRegistrados").empty();
        for (let dep of arrFilter) {
            if (dep.acc_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Actividad Comercio";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Actividad Comercio";
            }

            if (dep.acc_GrupoTarifa == 1) {
                var strGrupoTarifa = "Comercial";
            } else if (dep.acc_GrupoTarifa == 2) {
                var strGrupoTarifa = "Servicio Financiero";
            } else if (dep.acc_GrupoTarifa == 3) {
                var strGrupoTarifa = "Industrial";
            } else if (dep.acc_GrupoTarifa == 4) {
                var strGrupoTarifa = "Otros";
            } else if (dep.acc_GrupoTarifa == 5) {
                var strGrupoTarifa = "Servicios";
            }

            //console.log('dep ', dep);
                $('#bodyActividadesComercioRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    dep.acc_Codigo +
                    '</td>' +
                    '<td>' +
                    dep.acc_Nombre + 
                    '</td>' +
                    '<td>' +
                    dep.acc_TarifaConsulta +
                    '</td>' +
                    '<td>' +
                    strGrupoTarifa +
                    '</td>' +
                    
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar ActividadesComercio" style="margin-right:5px" onclick="javascript:actividadesComercio.getActividadesComercioById(' + dep.acc_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:actividadesComercio.cambiarEstado(' + dep.acc_Id + ',' + dep.acc_Estado + ')">' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    '</td>' +
                    '</tr>'
                );
            
        }
        actividadesComercio.init_table();
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
                'emptyTable': 'ActividadesComercio registrados',
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
     * getDependencia: Método para consultar ActividadesComercio
     */
    getActividadesComercio() {
        
        $.ajax({
            url: '../business/controller/class.actividadesComercio.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                if (arr.ok == 1) {
                    $("#bodyActividadesComercioRegistrados").empty();
                    actividadesComercio.draw_table_documents(arr.datos);
                } else {
                    $("#actividadesComercioRegistrados").DataTable().destroy();
                    actividadesComercio.init_table();
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
    async getActividadesComercioById(id) {
        
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
                url: '../business/controller/class.actividadesComercio.php',
                data: { funcion: 3, acc_Id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        console.log('arr editar ', arr);
                        for (let datos of arr.datos) {
                            $("#acc_Codigo").val(datos.acc_Codigo);
                            $("#acc_Anio").val(datos.acc_Anio);
                            $("#acc_Nombre").val(datos.acc_Nombre);
                            $("#acc_Tarifa").val(datos.acc_TarifaConsulta);
                            $("#acc_IdGrupoTarifa").val(datos.acc_IdGrupoTarifa);
                            $("#acc_Exento").val(datos.acc_Exento);
                        }
                        $("#formCrearActividadesComercio").attr('action', 'javascript:actividadesComercio.editActividadesComercio(' + id + ')');
                        $("#btnCrearActividadesComercio").empty();
                        $("#btnCrearActividadesComercio").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $('#modal-ActividadesComercio').modal({backdrop: 'static', keyboard: false})
                        $("#modal-ActividadesComercio").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información delos ActividadesComercio',
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
    postActividadesComercio() {

        var acc_Tarifa = $("#acc_Tarifa").val();

        if (!actividadesComercio.validarTarifa(acc_Tarifa)) {
            swal({
                type: 'warning',
                title: 'Tarifa inválida',
                text: 'Debe tener formato 0.000 (ejemplo: 0.004)'
            });
            return false;
        }

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        // Tomamos los valores del formulario
        var acc_Codigo = $("#acc_Codigo").val();
        var acc_Anio = $("#acc_Anio").val();
        var acc_Nombre = $("#acc_Nombre").val();
        var acc_Tarifa = $("#acc_Tarifa").val();
        var acc_GrupoTarifa = $("#acc_GrupoTarifa").val();
        var acc_Exento = $("#acc_Exento").val();

        // Luego podrías construir un objeto formData o un data: {} en tu ajax con estas variables
        var formData = {
        funcion: 1,
        acc_Codigo: acc_Codigo,
        acc_Anio: acc_Anio,
        acc_Nombre: acc_Nombre,
        acc_Tarifa: acc_Tarifa,
        acc_GrupoTarifa: acc_GrupoTarifa,
        acc_Exento: acc_Exento
        };

        
        $.ajax({
            url: '../business/controller/class.actividadesComercio.php',
            data: formData,
            dataType: "json",
            type: "POST",
            success: function(arr) {
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearActividadesComercio").trigger("reset");
                    $("#modal-ActividadesComercio").modal('hide');
                    actividadesComercio.getActividadesComercio();
                    swal({
                        type: 'success',
                        title: 'ActividadesComercio creada',
                        text: 'ActividadesComercio creada exitosamente',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la ActividadesComercio',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
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
                var title = "¿Está seguro de inactivar la Actividad Comercio?";
                var subtitle = "Una vez inactivado la Actividad Comercio no podra usarse";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la Actividad Comercio?";
                var subtitle = "Una vez activada, la Actividad Comercio podrá usarse";
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
                        url: '../business/controller/class.actividadesComercio.php',
                        data: { funcion: 4, acc_Id: id_actividadesComercio, acc_Estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {

                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                actividadesComercio.getActividadesComercio();
                                swal({
                                    type: 'success',
                                    title: 'Actividad Comercio actualizado',
                                    text: 'Actividad Comercio actualizado exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar la Actividad Comercio',
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
    editActividadesComercio(id) {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var acc_Codigo = $("#acc_Codigo").val();
        var acc_Anio = $("#acc_Anio").val();
        var acc_Nombre = $("#acc_Nombre").val();
        var acc_Tarifa = $("#acc_Tarifa").val();
        var acc_GrupoTarifa = $("#acc_GrupoTarifa").val();
        var acc_Exento = $("#acc_Exento").val();

        // Luego podrías construir un objeto formData o un data: {} en tu ajax con estas variables
        var formData = {
        funcion: 2,
        acc_Id    : id,
        acc_Codigo: acc_Codigo,
        acc_Anio: acc_Anio,
        acc_Nombre: acc_Nombre,
        acc_Tarifa: acc_Tarifa,
        acc_GrupoTarifa: acc_GrupoTarifa,
        acc_Exento: acc_Exento
        };

        console.log('formData ', formData);

        $.ajax({
            url: '../business/controller/class.actividadesComercio.php',
            data: formData,
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearActividadesComercio").trigger("reset");
                    $("#modal-ActividadesComercio").modal('hide');
                    actividadesComercio.getActividadesComercio();
                    swal({
                        type: 'success',
                        title: 'Actividad Comercio actualizado',
                        text: 'Actividad Comercio actualizado exitosamente',
                    });
                }else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar el Actividad Comercio',
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

        $("#ICA_Actividades").addClass("active");
    }

    validarTarifa(valor) {
        const regex = /^\d\.\d{3}$/;
        return regex.test(valor);
    }

}

const actividadesComercio = new ActividadesComercio();

actividadesComercio.getActividadesComercio();
actividadesComercio.UsuarioActivo();
