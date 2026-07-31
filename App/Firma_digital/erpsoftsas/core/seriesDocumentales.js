/*    METDOS DEL MODULO DE DEPENDENCIA    */

var enable = true;
var idRol = localStorage.getItem('id_Rol');

class SeriesDocumentales {

    constructor() {}

    /**
     * crearUsuario: Método para abrir modal de creación de SeriesDocumentales.
     */
    async crearSeriesDocumentales() {

        //Parametro: 27 (2= Modulo SeriesDocumentales, 7:Permiso Crear SeriesDocumentales)
        var permiso = await _permisos.getPermisos(idRol, 623);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $("#formCrearSeriesDocumentales").trigger("reset");
            $("#btnCrearSeriesDocumentales").empty();
            $("#btnCrearSeriesDocumentales").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearSeriesDocumentales").attr('action', 'javascript:seriesDocumentales.postSeriesDocumentales()');
            $('#modal-SeriesDocumentales').modal({backdrop: 'static', keyboard: false})
            $("#modal-SeriesDocumentales").modal('show');
        }
    }


    /**
     * crearSubSeriesDocumentales: Método para abrir modal de creación de crearSubSeriesDocumentales.
     */
    async crearSubSeriesDocumentales(idSerieDocumental,nombreSerie) {

        //Parametro: 27 (2= Modulo SeriesDocumentales, 7:Permiso Crear SeriesDocumentales)
        var permiso = await _permisos.getPermisos(idRol, 623);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $("#nombreSerie").val(nombreSerie);
            $("#formCrearSubSeriesDocumentales").trigger("reset");
            $("#btnCrearSubSeriesDocumentales").empty();
            $("#btnCrearSubSeriesDocumentales").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearSubSeriesDocumentales").attr('action', 'javascript:seriesDocumentales.postSubSeriesDocumentales('+idSerieDocumental+')');
            $('#modal-SubSeriesDocumentales').modal({backdrop: 'static', keyboard: false})
            $("#modal-SubSeriesDocumentales").modal('show');
        }
    }

    

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de SeriesDocumentales 
     * @param type $arrFilter: Listado de objetos SeriesDocumentales
     */
    draw_table_documents(arrFilter) {
        
        $("#seriesDocumentalesRegistrados").DataTable().destroy();
        $("#bodySeriesDocumentalesRegistrados").empty();
        for (let cat of arrFilter) {
            if (cat.cat_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Serie Documental";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Serie Documental";
            }

                $('#bodySeriesDocumentalesRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    cat.cat_Id +
                    '</td>' +
                    '<td>' +
                    cat.cat_Nombre +
                    '</td>' +
                    '<td>' +
                    cat.cat_Descripcion +
                    '</td>' +

                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar SeriesDocumentales" style="margin-right:5px" onclick="javascript:seriesDocumentales.getSeriesDocumentalesById(' + cat.cat_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:seriesDocumentales.cambiarEstado(' + cat.cat_Id + ',' + cat.cat_Estado + ')">' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    '</td>' +

                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-primary " data-toggle="tooltip" title="Crear Sub-SeriesDocumentales" style="margin-right:5px" onclick="javascript:seriesDocumentales.crearSubSeriesDocumentales(' + cat.cat_Id + ','+"'"+ cat.cat_Nombre +"'"+')">' +
                    '<i class="dw dw-add"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon btn-primary"  title="Ver Sub Series" style="margin-left:5px" onclick="javascript:seriesDocumentales.verSubSeriesDocumentales(' + cat.cat_Id + ','+"'"+ cat.cat_Nombre +"'"+')" >' +
                    '<i class="dw dw-eye"></i>' +
                    '</button>' +
                    '</td>' +

                    


                    '</tr>'
                );
            
        }
        seriesDocumentales.init_table();
    }


        /**
     * verDetalleMovi: Método para consultar el 
     * detalle de los movimientos de la cuenta contabla enviada por parametro
     * @param type $idCuentaContable: Listado de Cuenta Contable
     */
    async verSubSeriesDocumentales(idSerieDocumental, nomSerieDocumental) {
        $('#loading').show();
        $('#wrapper').addClass('body-load');  

        var permiso = await _permisos.getPermisos(idRol, 622);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
            $('#loading').hide();
            $('#wrapper').removeClass('body-load');
        }else{

            $.ajax({
                url: '../business/controller/class.subCategoriasDocumental.php',
                data: { funcion: 3, idCategoria: idSerieDocumental },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');

                    $("#ltsLisSubSeries").DataTable().destroy();
                    $("#bodyLisSubSeries").empty();

                    console.log(arr.datos);
    
                    if (arr.ok == 1) {
    
                        for (let d of arr.datos) {

                            if (d.subc_Estado == 1) {
                                var icono = "dw dw-checked";
                                var clase = "btn-success";
                                var titulo = "Inactivar Sub Serie Documental";
                            } else {
                                var icono = "dw dw-ban";
                                var clase = "btn-danger";
                                var titulo = "Activar Sub Serie Documental";
                            }

                            if (d.subc_Sigla == null) { var sigla = "Sin Registro";} 
                                else { var sigla = d.subc_Sigla; }                            

                            $("#nombreSeriesDocumentales").val(nomSerieDocumental);
                            
                            $("#bodyLisSubSeries").append(
                                '<tr>' +
                                '<td  align="center">' + d.subc_Codigo + '</td>' +
                                '<td  align="center">' + d.subc_Nombre +'</td>' +
                                '<td  align="center">' + sigla + '</td>' +

                                '<td align="center">' +
                                '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar SeriesDocumentales" style="margin-right:5px" onclick="javascript:seriesDocumentales.getSubSeriesDocumentalesById(' + d.subc_Id  + ',' + idSerieDocumental + ','+"'"+ nomSerieDocumental +"'"+')">' +
                                '<i class="dw dw-edit2"></i>' +
                                '</button>' +
            
                                '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:seriesDocumentales.cambiarEstadoSub(' + d.subc_Id  + ',' + d.subc_Estado + ',' + idSerieDocumental + ','+"'"+ nomSerieDocumental +"'"+')">' +
                                '<i class="' + icono + '"></i>' +
                                '</button>' +
                                '</td>' +

                                '</tr>'
                            );
                        }

                        seriesDocumentales.init_table_detalle();
                        $('#modal-LisSubSeries').modal({backdrop: 'static', keyboard: false});
                        $("#modal-LisSubSeries").modal('show');

                    } else {
                        swal({
                            type: 'error',
                            title: 'Ocurrio un error al consultar Sub Series Documentales',
                            text: 'No Existen Sub Series Documentales.',
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
     * init_table_detalle: Método para asignar la
     * propiedad DataTable() a la tabla de productos
     */
    init_table_detalle() {
        
        $('#ltsLisSubSeries').DataTable({
            scrollCollapse: true,
            autoWidth: false,
            responsive: true,
            columnDefs: [
                { targets: "datatable-nosort", orderable: false,},
                { "width": "5%", "targets": 0 },
                { "width": "10%", "targets": 1 },
                { "width": "5%", "targets": 2 },
                { "width": "5%", "targets": 3 },
            ],
            aaSorting: [
                [0, "desc"]
            ],
            "lengthMenu": [
                [3, 5, 10, 25, 50, -1],
                [3, 5, 10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'No existen registros',
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
     * init_table: Método para asignar la
     * propiedad DataTable() a la tabla de SeriesDocumentales
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
                'emptyTable': 'Series Documentales registrados',
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
     * getSeriesDocumentales: Método para consultar SeriesDocumentales
     */
    getSeriesDocumentales() {
        
        $.ajax({
            url: '../business/controller/class.categoriasDocumental.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                if (arr.ok == 1) {
                    $("#bodySeriesDocumentalesRegistrados").empty();
                    seriesDocumentales.draw_table_documents(arr.datos);
                } else {
                    $("#seriesDocumentalesRegistrados").DataTable().destroy();
                    seriesDocumentales.init_table();
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * getUsuarioById: Método para consultar la
     * información de un SeriesDocumentales
     * @param type $id: llave primaria de la tabla SeriesDocumentales
     */
    async getSeriesDocumentalesById(id) {
        
        $('#loading').show();
        $('#wrapper').addClass('body-load');
        var permiso = await _permisos.getPermisos(idRol, 624);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.categoriasDocumental.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        console.log('arr editar ', arr);
                        for (let datos of arr.datos) {
                            $("#cat_IdDependencia").val(datos.cat_IdDependencia);
                            $("#cat_Nombre").val(datos.cat_Nombre);
                            $("#cat_Descripcion").val(datos.cat_Descripcion);
                            $("#cat_Sigla").val(datos.cat_Sigla);
                            $("#cat_Codigo").val(datos.cat_Codigo);
                        }
                        $("#formCrearSeriesDocumentales").attr('action', 'javascript:seriesDocumentales.editSeriesDocumentales(' + id + ')');
                        $("#btnCrearSeriesDocumentales").empty();
                        $("#btnCrearSeriesDocumentales").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $('#modal-SeriesDocumentales').modal({backdrop: 'static', keyboard: false})
                        $("#modal-SeriesDocumentales").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información dela SeriesDocumentales',
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
     * getSubSeriesDocumentalesById: Método para consultar la
     * información de un SeriesDocumentales
     * @param type $id: llave primaria de la tabla SeriesDocumentales
     */
    async getSubSeriesDocumentalesById(id, idSerieDocumental, nomSerieDocumental) {
    
        $('#loading').show();
        $('#wrapper').addClass('body-load');
        var permiso = await _permisos.getPermisos(idRol, 624);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.subCategoriasDocumental.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        console.log('arr editar ', arr);
                        $("#modal-LisSubSeries").modal('hide');
                        
                        $("#formCrearSubSeriesDocumentales").trigger("reset");
/*                        $("#formCrearSubSeriesDocumentales").empty();
                        $("#btnCrearSubSeriesDocumentales").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
*/
                        for (let datos of arr.datos) {
                            $("#subc_Nombre").val(datos.subc_Nombre);
                            $("#subc_Codigo").val(datos.subc_Codigo);
                            $("#subc_Sigla").val(datos.subc_Sigla);
                            $("#subc_Descripcion").val(datos.subc_Descripcion);
                        }
                        $("#formCrearSubSeriesDocumentales").attr('action', 'javascript:seriesDocumentales.editSubSeriesDocumentales(' + id + ',' + idSerieDocumental + ','+"'"+ nomSerieDocumental +"'"+')');
                        
                        $('#modal-SubSeriesDocumentales').modal({backdrop: 'static', keyboard: false})
                        $("#modal-SubSeriesDocumentales").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información dela Sub SeriesDocumentales',
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
     * postUsuario: Método para crear SeriesDocumentales
     */
    postSeriesDocumentales() {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var idDependencia = $("#cat_IdDependencia").val();
        var nombre = $("#cat_Nombre").val();
        var descripcion = $("#cat_Descripcion").val();
        var sigla = $("#cat_Sigla").val();
        var codigo = $("#cat_Codigo").val();

        $.ajax({
            url: '../business/controller/class.categoriasDocumental.php',
            data: { funcion: 1, cat_IdDependencia: idDependencia, cat_Nombre: nombre, cat_Descripcion: descripcion,
                cat_Sigla: sigla, cat_Codigo: codigo },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearSeriesDocumentales").trigger("reset");
                    $("#modal-SeriesDocumentales").modal('hide');
                    seriesDocumentales.getSeriesDocumentales();
                    swal({
                        type: 'success',
                        title: 'SeriesDocumentales creada',
                        text: 'SeriesDocumentales creada exitosamente',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la SeriesDocumentales',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }


    /**
     * postSubSeriesDocumentales: Método para crear postSubSeriesDocumentales
     */
    postSubSeriesDocumentales(idSerieDocumental) {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var idCategoria = idSerieDocumental;
        var nombre = $("#subc_Nombre").val();
        var descripcion = $("#subc_Descripcion").val();
        var sigla = $("#subc_Sigla").val();
        var codigo = $("#subc_Codigo").val();

        $.ajax({
            url: '../business/controller/class.subcategoriasDocumental.php',
            data: { funcion: 1, subc_IdCategoria: idCategoria, subc_Nombre: nombre, subc_Descripcion: descripcion,
                subc_Sigla: sigla, subc_Codigo: codigo },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearSubSeriesDocumentales").trigger("reset");
                    $("#modal-SubSeriesDocumentales").modal('hide');
                    seriesDocumentales.getSeriesDocumentales();
                    swal({
                        type: 'success',
                        title: 'Sub Serie Documental creada',
                        text: 'Sub Serie Documental creada exitosamente',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la Sub Serie Documental',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    

    /**
     * cambiarEstado: Método para cambiar el estado de los SeriesDocumentales
     * @param type $id_usuario:  llave primaria de la tabla SeriesDocumentales
     * @param type $estado: estado actual del SeriesDocumentales
     */
    async cambiarEstado(id_seriesDocumentales, estado) {

        var permiso = await _permisos.getPermisos(idRol, 625);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar la Serie Documental?";
                var subtitle = "Una vez inactivado la Serie Documental no podra usarse";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la Serie Documental?";
                var subtitle = "Una vez activado, la Serie Documental podrá usarse";
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
                        url: '../business/controller/class.categoriasDocumental.php',
                        data: { funcion: 4, id: id_seriesDocumentales, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {

                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                seriesDocumentales.getSeriesDocumentales();
                                swal({
                                    type: 'success',
                                    title: 'Serie Documental actualizado',
                                    text: 'Serie Documental actualizado exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el serie Documental',
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
     * cambiarEstadoSub: Método para cambiar el estado de los SeriesDocumentales
     * @param type $id_usuario:  llave primaria de la tabla SeriesDocumentales
     * @param type $estado: estado actual del SeriesDocumentales
     */
    async cambiarEstadoSub(id_subseriesDocumentales, estado, idSerieDocumental, nomSerieDocumental) {

        var permiso = await _permisos.getPermisos(idRol, 625);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar la Sub Serie Documental?";
                var subtitle = "Una vez inactivado la Sub Serie Documental no podra usarse";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la Sub Serie Documental?";
                var subtitle = "Una vez activado, la Sub Serie Documental podrá usarse";
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
                        url: '../business/controller/class.subCategoriasDocumental.php',
                        data: { funcion: 4, id: id_subseriesDocumentales, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {

                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                seriesDocumentales.verSubSeriesDocumentales(idSerieDocumental, nomSerieDocumental);
                                swal({
                                    type: 'success',
                                    title: 'Sub Serie Documental actualizado',
                                    text: 'Sub Serie Documental actualizado exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el Sub  serie Documental',
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
     * editUsuario: Método para actualizar un seriesDocumentales
     * @param type $id: llave primaria de la tabla seriesDocumentales
     */
    editSeriesDocumentales(id) {

        /*$('#loading').show();
        $('#wrapper').addClass('body-load');*/

        var idDependencia = $("#cat_IdDependencia").val();
        var nombre = $("#cat_Nombre").val();
        var descripcion = $("#cat_Descripcion").val();
        var sigla = $("#cat_Sigla").val();
        var codigo = $("#cat_Codigo").val();

        $.ajax({
            url: '../business/controller/class.categoriasDocumental.php',
            data: { funcion: 2, cat_Id: id, cat_IdDependencia: idDependencia, cat_Nombre: nombre, cat_Descripcion: descripcion,
                cat_Sigla: sigla, cat_Codigo: codigo },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearSeriesDocumentales").trigger("reset");
                    $("#modal-SeriesDocumentales").modal('hide');
                    seriesDocumentales.getSeriesDocumentales();
                    swal({
                        type: 'success',
                        title: 'Serie Documental actualizado',
                        text: 'Serie Documental actualizado exitosamente',
                    });
                }else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar el Serie Documental',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * editSubSeriesDocumentales: Método para actualizar un seriesDocumentales
     * @param type $id: llave primaria de la tabla seriesDocumentales
     */
    editSubSeriesDocumentales(id, idSerieDocumental, nomSerieDocumental) {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var subc_Nombre = $("#subc_Nombre").val();
        var subc_Descripcion = $("#subc_Descripcion").val();
        var subc_Sigla = $("#subc_Sigla").val();
        var subc_Codigo = $("#subc_Codigo").val();

        $.ajax({
            url: '../business/controller/class.subCategoriasDocumental.php',
            data: { funcion: 2, id: id, subc_IdCategoria : idSerieDocumental, subc_Nombre: subc_Nombre,
                subc_Descripcion: subc_Descripcion, sigla: subc_Sigla, codigo: subc_Codigo },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearSubSeriesDocumentales").trigger("reset");
                    $("#modal-SubSeriesDocumentales").modal('hide');
                    seriesDocumentales.verSubSeriesDocumentales(idSerieDocumental, nomSerieDocumental);
                    swal({
                        type: 'success',
                        title: 'Sub Serie Documental actualizado',
                        text: 'Sub Serie Documental actualizado exitosamente',
                    });
                }else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar el Sub Serie Documental',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }
    

    

    /**
     * getDependencias: Método para consultar las Dependencias
     */
    getDependencias() {

        $.ajax({
            url: '../business/controller/class.dependencia.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                $("#cat_IdDependencia").empty();
                $("#cat_IdDependencia").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#cat_IdDependencia").append('<option value="' + v['dep_Id'] + '">' + v['dep_Nombre'] + '</option>');
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
        $("#SubSeriesDocumentales").addClass('active');
    }
}

const seriesDocumentales = new SeriesDocumentales();

seriesDocumentales.getSeriesDocumentales();
seriesDocumentales.getDependencias();
seriesDocumentales.UsuarioActivo();
