/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
var detallesNotaInsumo = [];
class NotaInsumo {

    constructor() {}

    /**
     * crearInsumo: Método para abrir modal de creación NOTA DE ENTRADA
     */
    async crearNotaInsumo() {
        var permiso = await _permisos.getPermisos(idRol, 1039);

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
            $("#formCrearNotaInsumo").trigger("reset");
            $("#formCrearNotaInsumo").attr('action', 'javascript:notaInsumo.postNotaInsumo()');

            $("#kar_Tipo").empty();
            $("#kar_Tipo").append('<option value="1">Entrada</option>');

            await notaInsumo.getinsumo();     
            await notaInsumo.getBodega();
            $("#modal_footer").empty();
            $("#modal_footer").append(
                '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>' +
                ' Cancelar' +
                '</button>' +
                '<button type="submit" class="btn btn-success btn-pill"><span class="ti-plus"></span>' +
                ' Crear' +
                '</button>'
            );
            $("#modal-NotaInsumo").modal('show');
        }
    }
    
    /**
     * crearInsumo: Método para abrir modal de creación NOTA SALIDA
     */
    async crearNotaInsumoS() {
        var permiso = await _permisos.getPermisos(idRol, 1039);

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
            $("#formCrearNotaInsumo").trigger("reset");
            $("#formCrearNotaInsumo").attr('action', 'javascript:notaInsumo.postNotaInsumo()');

            $("#kar_Tipo").empty();
            $("#kar_Tipo").append('<option value="2">Salida</option>');

            await notaInsumo.getinsumoExistencias();              
            await notaInsumo.getBodega();
            $("#modal_footer").empty();
            $("#modal_footer").append(
                '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>' +
                ' Cancelar' +
                '</button>' +
                '<button type="submit" class="btn btn-success btn-pill"><span class="ti-plus"></span>' +
                ' Crear' +
                '</button>'
            );
            $("#modal-NotaInsumo").modal('show');
        }
    }

    /**
     * pintarTable: Método para pintar la tabla inicial
     * de los detalles de la nota Insumo
     */
    pintarTable() {
        $("#detalleNotasInsumo").DataTable().destroy();
        $("#bodyDetallesNotasInsumo").empty();
        var cont = 0;
        if (detallesNotaInsumo.length > 0) {
            console.log('detallesNota2Insumo ', detallesNotaInsumo);
            for (let d of detallesNotaInsumo) {
                $("#bodyDetallesNotasInsumo").prepend(
                    '<tr>' +
                    '<td>' + d.nomInsumo + '</td>' +
                    '<td align="right">' + d.detkar_Cantidad + '</td>' +
                    '<td align="right">' + d.detkar_Costo + '</td>' +
                    '<td>' + d.nomBodega + '</td>' +
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-danger " data-toggle="tooltip" title="Eliminar detalle"  onclick="notaInsumo.eliminarDetalle(' + cont + ')">' +
                    '<i class="dw dw-ban"></i>' +
                    '</button>' +
                    '</td>' +
                    '</tr>'
                );
                cont++;
            }
        }

        $('#detalleNotasInsumo').DataTable({
            scrollCollapse: true,
            autoWidth: false,
            responsive: true,
            columnDefs: [{
                targets: "datatable-nosort",
                orderable: false,
            }],
            aaSorting: [
                [1, "desc"]
            ],
            //Se ordena por la fila 4 
            "order": [[ 4, "desc" ]],
            "lengthMenu": [
                [5, 10, 25, 50, -1],
                [5, 10, 25, 50, "All"]
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
     * draw_table_documents: Método para pintar la tabla 
     * de notas Insumo de entrada y salida 
     * @param type $arrFilter: Listado de objetos de tipo nota Insumo
     */
    draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        $("#notasInsumoRegistradas").DataTable().destroy();
        $("#bodyNotasInsumoRegistradas").empty();
        for (let not of arrFilter) {
            var icono = "dw dw-ban";
            var clase = "btn-danger";

            if (not.Kar_Estado == 1) {
                var disabled = "";
                var titulo = "Anular Nota Insumo";
            } else {
                var disabled = "disabled";
                var titulo = "Nota Insumo Anulada";
            }

            if (not.kar_Tipo == 1) {
                var NomTipo = "NE";
            } else {
                var NomTipo = "NS";
            }

            $('#bodyNotasInsumoRegistradas').append(
                '<tr>' +
                '<td>' +
                not.kar_Fecha +
                '</td>' +
                '<td>' +
                NomTipo +
                '</td>' +
                '<td>' +
                not.kar_Observaciones +
                '</td>' +

                '<td align="center">' +
                /*  '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar nota Insumo" style="margin-right:5px" onclick="javascript:notaInsumo.getNotaInsumoById('+not.kar_Id+')">'+
                     '<i class="mdi mdi-border-color"></i>'+
                 '</button>'+ */

                /*  '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:notaInsumo.cambiarEstado(' + not.kar_Id + ',' + not.Kar_Estado + ')" ' + disabled + ' style="margin-right: 5px">' +
                 '<i class="' + icono + '"></i>' +
                 '</button>' + */
                '<button type="button" class="btn btn-social-icon btn-success " data-toggle="tooltip" title="Ver detalles"  onclick="javascript:notaInsumo.verDetalle(' + not.kar_Id + ',' + not.kar_Tipo + ')" >' +
                '<span class="ti-eye"></span>' +
                '</button>' +
                '</td>' +

                '</tr>'
            );

        }
        notaInsumo.init_table();
    }

    /**
     * init_table: Método para asignar la
     * propiedad DataTable() a la tabla de Insumo 
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
            aaSorting: [
                [1, "desc"]
            ],
            "lengthMenu": [
                [10, 25, 50, -1],
                [10, 25, 50, "All"]
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
     * getNotas Insumo: Método para consultar las notas  Insumo
     */
    getNotasInsumo() {

        $.ajax({
            url: '../business/controller/class.notaInsumo.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('not ', arr);

                if (arr.ok == 1) {
                    notaInsumo.draw_table_documents(arr.datos);
                } else {
                    $("#notasInsumoRegistradas").DataTable().destroy();
                    notaInsumo.init_table();
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * getinsumo: Método para consultar los insumos
     */
    getinsumo() {

        $.ajax({
            url: '../business/controller/class.insumo.php',
            data: { funcion: 3, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#detkar_IdInsumo").empty();
                $("#detkar_IdInsumo").append('<option class"ulCombo" value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#detkar_IdInsumo").append('<option  class"ulCombo" value="' + v['ins_Id'] + '">' + v['ins_Nombre'] + '-'+ v['strTipoUnidad']+'</option>');
                    });
                }

                $("#detkar_IdInsumo").addClass('custom-select2');
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * getinsumo: Método para consultar las exixstencias de insumos
     */
    getinsumoExistencias() {

        $.ajax({
            url: '../business/controller/class.notaInsumo.php',
            data: { funcion: 6 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#detkar_IdInsumo").empty();
                $("#detkar_IdInsumo").append('<option class"ulCombo" value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#detkar_IdInsumo").append('<option  class"ulCombo" value="' + v['exi_Id'] + '">' + v['strNombreInsumo'] + '-'+v['exi_Cantidad'] +' '+ v['strNombreTipoUnidad']+'</option>');
                    });
                }

                $("#detkar_IdInsumo").addClass('custom-select2');
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }


    /**
     * getBodega: Método para consultar las bodegas
     */
    getBodega() {

        $.ajax({
            url: '../business/controller/class.bodega.php',
            data: { funcion: 3, estado: 1, tipo: 2 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#detkar_IdBodega").empty();
                $("#detkar_IdBodega").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#detkar_IdBodega").append('<option value="' + v['bod_Id'] + '">' + v['bod_Nombre'] + '</option>');
                    });
                }
                $("#detkar_IdBodega").addClass('custom-select2');
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }


    /**
     * verDetalle: Método para consultar el 
     * detalle de las notas Insumo
     * @param type $idNota: Listado de objetos de tipo nota Insumo
     */
    verDetalle(idNotaInsumo, idTipoKardex) {
        $.ajax({
            url: '../business/controller/class.notaInsumo.php',
            data: { funcion: 5, idNotaInsumo: idNotaInsumo, idTipoKardex: idTipoKardex },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('det ', arr)
                console.log('id ', idTipoKardex)
                $("#ltsDetallesNotaInsumo").DataTable().destroy();
                $("#bodyDetallesNotaInsumo").empty();

                if (arr.ok == 1) {

                    for (let d of arr.datos) {
                        $("#bodyDetallesNotaInsumo").append(
                            '<tr>' +
                            '<td>' + d.ins_Nombre + '</td>' +
                            '<td>' + d.bod_Nombre + '</td>' +
                            '<td class="numero" align="rigth">' + d.detkar_CantidadEntrada + '</td>' +
                            '<td class="numero" align="rigth">' + d.detkar_ValorEntrada + '</td>' +
                            '<td class="numero" align="rigth">' + d.detkar_CantidadSalida + '</td>' +
                            '<td class="numero" align="rigth">' + d.detkar_ValorSalida + '</td>' +
                            '<td class="numero" align="rigth">' + d.detkar_CantidadSaldo + '</td>' +
                            '<td class="numero" align="rigth">' + d.detkar_ValorSaldo + '</td>' +
                            '</tr>'
                        );
                    }

                    $('.numero').number(true, 2);
                    notaInsumo.init_table_detalle();
                    $("#modal-Detalles").modal('show');
                } else {
                    swal({
                        type: 'error',
                        title: 'Ocurrio un error al consultar la información',
                        text: 'Por favor intente nuevamente, si el problema persiste consulte con el adimistrador del sistema',
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
     * init_table_detalle: Método para asignar la
     * propiedad DataTable() a la tabla de Insumos
     */
    init_table_detalle() {
        $('#ltsDetallesNotaInsumo').DataTable({
            scrollCollapse: true,
            autoWidth: false,
            responsive: true,
            columnDefs: [{
                targets: "datatable-nosort",
                orderable: false,
            }],
            aaSorting: [
                [1, "desc"]
            ],
            "lengthMenu": [
                [10, 25, 50, -1],
                [10, 25, 50, "All"]
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

    agregarDetalle() {
        var pro = $("#detkar_IdInsumo").val();
        var cant = $("#detkar_Cantidad").val();
        var cost = $("#detkar_Costo").val();
        var bod = $("#detkar_IdBodega").val();
        console.log('pro ', pro, 'cant ', cant, 'cost ', cost, 'bod ', bod);
        if (pro != "" && cant != "" && cost != "" && bod != "") {
            var namePro = $('#detkar_IdInsumo option:selected').text();
            var nameBod = $('#detkar_IdBodega option:selected').text();
            detallesNotaInsumo.push({ "detkar_IdInsumo": pro, "detkar_Cantidad": cant, "detkar_Costo": cost, "detkar_IdBodega": bod, "nomInsumo": namePro, "nomBodega": nameBod });
        }
        notaInsumo.pintarTable();

        //$('#detkar_IdInsumo').val($('#detkar_IdInsumo > option:first').val());
        $('#detkar_IdInsumo').get(0).selectedIndex = 1;
        $('#detkar_IdBodega').get(0).selectedIndex = 1;
        $('#detkar_Cantidad').get(0).selectedIndex = 1;
        //$('#detkar_Cantidad').val('1');
        $('#detkar_Costo').get(0).selectedIndex = 1;

        console.log('detallesInsumo ', detallesNotaInsumo);
    }

    eliminarDetalle(posicion) {
        detallesNotaInsumo.splice(posicion, 1);
        notaInsumo.pintarTable();
    }

    /**
     * postNotaInsumo: Método para crear notas Insumo
     */
    postNotaInsumo() {

        /* $('#loading').show();
        $('#wrapper').addClass('body-load'); */

        if (detallesNotaInsumo.length < 1) {
            swal({
                type: 'warning',
                title: 'No existen Insumos asignados en el detalle de la  nota Insumo',
                text: 'Debe ingresar al menos un Insumo en el detalle de la nota Insumo',
            });
        } else {
            var tipo = $("#kar_Tipo").val()
            var Observaciones = $("#Kar_Observaciones").val();
            var det = JSON.stringify(detallesNotaInsumo);
            $.ajax({
                url: '../business/controller/class.notaInsumo.php',
                data: { funcion: 1, kar_Tipo: tipo, Observaciones: Observaciones, detallesNotaInsumo: det },
                type: 'POST',
                dataType: 'json',
                success: function(arr) {    

                    if (arr.ok == 1) {
                        $("#formCrearNotaInsumo").trigger("reset");
                        $("#modal-NotaInsumo").modal('hide');
                        $("#detalleNotasInsumo").DataTable().destroy();
                        $("#bodyDetallesNotasInsumo").empty();
                        detallesNotaInsumo = [];
                        notaInsumo.getNotasInsumo();
                        swal({
                            type: 'success',
                            title: 'Nota Insumo creada',
                            text: 'Nota Insumo creada correctamente',
                        });
                    } else if (arr.ok == 2) {
                        $("#formCrearNotaInsumo").trigger("reset");
                        $("#modal-NotaInsumo").modal('hide');
                        $("#detalleNotasInsumo").DataTable().destroy();
                        $("#bodyDetallesNotasInsumo").empty();
                        detallesNotaInsumo = [];
                        notaInsumo.getNotasInsumo();
                        swal({
                            type: 'warning',
                            title: 'Sin existencias',
                            text: "Uno o más Insumos relacionados en la nota Insumo no tienen existencias",
                        });
                    } else if (arr.ok == 3) {
                        $("#formCrearNotaInsumo").trigger("reset");
                        $("#modal-NotaInsumo").modal('hide');
                        $("#detalleNotasInsumo").DataTable().destroy();
                        $("#bodyDetallesNotasInsumo").empty();
                        detallesNotaInsumo = [];
                        notaInsumo.getNotasInsumo();
                        swal({
                            type: 'warning',
                            title: 'Sin existencias',
                            text: arr.mensaje,
                        });
                    } else {
                        swal({
                            type: 'error',
                            title: 'Ocurrió un error al intentar crear la nota Insumo',
                            text: arr.mensaje,
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

    async cambiarEstado(id_notaInsumo) {
        var permiso = await _permisos.getPermisos(idRol, 1041);
        var estado = 1;
        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de anular la nota Insumo?";
                var subtitle = "Una vez anulada, no podrá ser recuperada. Esta acción es irreversible";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar el Insumo?";
                var subtitle = "Una vez activado, podrá ser utilizado";
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
                        url: '../business/controller/class.Insumo.php',
                        data: { funcion: 4, id: id_notaInsumo, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);

                            if (arr.ok == 1) {
                                notaInsumo.getNotasInsumo();
                                swal({
                                    type: 'success',
                                    title: 'Nota Insumo anulada',
                                    text: 'Nota Insumo anulada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo anular la nota Insumo',
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

    editProducto(id) {
        /*   $('#loading').show();
          $('#wrapper').addClass('body-load'); */

        var nombre = $("#pro_Nombre").val();
        var cod = $("#pro_Codigo").val();
        var codBarras = $("#pro_CodBarras").val();
        var tipo = $("#pro_Tipo").val();
        var unidad = $("#pro_UnidadMed").val();

        $.ajax({
            url: '../business/controller/class.producto.php',
            data: { funcion: 2, id: id, nombre: nombre, codigo: cod, tipo: tipo, unidad: unidad },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);

                if (arr.ok == 1) {
                    $("#formCrearProducto").trigger("reset");
                    $("#modal-Producto").modal('hide');
                    prod.getProductos();
                    swal({
                        type: 'success',
                        title: 'Producto actualizado',
                        text: 'Producto actualizado exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Código duplicado',
                        text: arr.mensaje,
                    });
                } else if (arr.ok == 3) {
                    swal({
                        type: 'warning',
                        title: 'Identificación duplicada',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar el producto',
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


    NotaInsumoActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DProduccion").addClass('expand');
        $("#DProduccion").addClass('active');
        $("#SProduccion").addClass('show');
        $("#SubMenuNotaI").addClass('active');
    }

}

const notaInsumo = new NotaInsumo();


notaInsumo.NotaInsumoActivo();
notaInsumo.getNotasInsumo();

$(document).ready(function() {
    $("#detkar_Cantidad").number(true, 2);
    $("#detkar_Costo").number(true, 2);
});