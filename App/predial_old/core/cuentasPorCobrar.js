/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
var detallesNota = [];
class Nota {

    constructor() {}

    /**
     * crearNota: Método para abrir modal de creación
     */
    async crearNota() {
        var permiso = await _permisos.getPermisos(idRol, 2391);

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
            $("#formCrearNota").trigger("reset");
            $("#formCrearNota").attr('action', 'javascript:nota.postNota()');

            //await nota.getFormasPago();
            await nota.getBodega();
            $("#modal_footer").empty();
            $("#modal_footer").append(
                '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>' +
                ' Cancelar' +
                '</button>' +
                '<button type="submit" class="btn btn-success btn-pill"><span class="ti-plus"></span>' +
                ' Crear' +
                '</button>'
            );
            $("#modal-Nota").modal('show');
        }
    }

    /**
     * pintarTable: Método para pintar la tabla inicial
     * de los detalles de la nota
     */
    pintarTable() {
        $("#detalleNotas").DataTable().destroy();
        $("#bodyDetallesNotas").empty();
        var cont = 0;
        if (detallesNota.length > 0) {
            console.log('detallesNota2 ', detallesNota);
            for (let d of detallesNota) {
                $("#bodyDetallesNotas").prepend(
                    '<tr>' +
                    '<td>' + d.nomProducto + '</td>' +
                    '<td align="right">' + d.detkar_Cantidad + '</td>' +
                    '<td align="right">' + d.detkar_CostoUnitario + '</td>' +
                    '<td>' + d.nomBodega + '</td>' +
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-danger " data-toggle="tooltip" title="Eliminar detalle"  onclick="nota.eliminarDetalle(' + cont + ')">' +
                    '<i class="dw dw-ban"></i>' +
                    '</button>' +
                    '</td>' +
                    '</tr>'
                );
                cont++;
            }
        }

        $('#detalleNotas').DataTable({
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
     * de notas de entrada y salida 
     * @param type $arrFilter: Listado de objetos de tipo nota
     */
    draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        var idVendedor = sessionStorage.getItem('id_Usuario');

        $("#notasRegistradas").DataTable().destroy();
        $("#bodyNotasRegistradas").empty();
        for (let not of arrFilter) {
        
                var icono = "dw dw-ban";
                var clase = "btn-danger";
    
                if ( not.strValorTotalAbonos == null) {
                    var totalAbonos = 0;
                } else {
                    var totalAbonos = not.strValorTotalAbonos;
                }
    
                var nom_empresa = sessionStorage.getItem('nom_tipoImpresora');
                if (nom_empresa == 1) {
                    // FACTURA 80 mm
                    var tipoImpresora = '<a href="../extensiones/tcpdf/pdf/factura.php?codigo=' + not.teso_IdDocumento + '" target="_blank" class="btn btn-success btn-pill"><span class="ti-plus"></span></a>' ;
                } else {
                    // FACTURA 58 mm
                    var tipoImpresora = '<a href="../extensiones/tcpdf/pdf/factura_58mm.php?codigo=' + not.teso_IdDocumento + '" target="_blank" class="btn btn-success btn-pill"><span class="ti-plus"></span></a>' ;
                }
                var totalNota = Math.trunc(not.teso_Importe);
                
                if (not.teso_EstadoPago == 0) {
                    var estadoPago = "No Saldada";
                    var btncargar = '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Abonar/Saldar Cuenta" style="margin-right:5px" onclick="javascript:nota.getAbonoCuentaById('+not.teso_IdDocumento+','+totalNota+','+ "'"+not.strNumeroPrefijo+"'"+','+not.teso_Id+')"><i class="dw dw-edit2"></i></button>';
                }else if(not.teso_EstadoPago == 1) {
                    var estadoPago = "Saldada";
                    var btncargar = ' ';                    
                } else{
                    var estadoPago = "No Aplica";
                }

                if(totalAbonos == 0){
                    var btnanular = '<button type="button" class="btn btn-social-icon btn-danger" title="Anular Factura"  style="margin-right:5px" onclick="javascript:nota.anularFactura(' + not.teso_IdDocumento + ',1)"><i class="dw dw-ban"></i></button>';
                }else{
                    var btnanular=' ';
                }


                
                if(not.strEstadoFactura == 1){

                if(idRol == 1){

                    $('#bodyNotasRegistradas').append(
                        '<tr>' +
                        '<td>' +
                        not.strNumeroPrefijo +
                        '</td>' +
                        '<td>' +
                        not.teso_FechaCreacion +
                        '</td>' +
                        '<td>' +
                        not.strNombreCliente +
                        '</td>' +
                        '<td>' +
                        '$ '+ Number(parseInt(not.teso_Importe).toFixed(0)).toLocaleString('es-CO') +
                        //Math.trunc(not.strValorTotalCuenta) +
                        '</td>' +
                        '<td>' +
                        '$ '+ Number(parseInt(totalAbonos).toFixed(0)).toLocaleString('es-CO') +
                        '</td>' +
                        '<td>' +
                        '$ '+ Number(parseInt(not.teso_Importe) - parseInt(totalAbonos)).toLocaleString('es-CO') +
                        '</td>' +
                        '<td>' +
                        estadoPago +
                        '</td>' +
                        '<td align="center">' +
                        btnanular +
                        btncargar +
                        tipoImpresora +
                        '</td>' +
        
                        '</tr>'
                    );
                    
                }else{
                
                if(not.strVendedor == idVendedor){

                    $('#bodyNotasRegistradas').append(
                        '<tr>' +
                        '<td>' +
                        not.strNumeroPrefijo +
                        '</td>' +
                        '<td>' +
                        not.teso_FechaCreacion +
                        '</td>' +
                        '<td>' +
                        not.strNombreCliente +
                        '</td>' +
                        '<td>' +
                        '$ '+ Number(parseInt(not.teso_Importe).toFixed(0)).toLocaleString('es-CO') +
                        //Math.trunc(not.strValorTotalCuenta) +
                        '</td>' +
                        '<td>' +
                        '$ '+ Number(parseInt(totalAbonos).toFixed(0)).toLocaleString('es-CO') +
                        '</td>' +
                        '<td>' +
                        '$ '+ Number(parseInt(not.teso_Importe) - parseInt(totalAbonos)).toLocaleString('es-CO') +
                        '</td>' +
                        '<td>' +
                        estadoPago +
                        '</td>' +
                        '<td align="center">' +
                        btncargar +
                        tipoImpresora +
                        '</td>' +
        
                        '</tr>'
                    );
                }
                }
            }
        }
        nota.init_table();
    }

    /**
     * Anular Factura 
     * @param type $id_Venta: llave primaria de la tabla factura Documentos
     */
    async anularFactura(id_pro, estado) {
        var permiso = await _permisos.getPermisos(idRol, 625);
        var id_caja = sessionStorage.getItem('id_caja');

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de Anular la Factura?";
                var subtitle = "Describa a continuación el motivo de anulación:";
                var button = "Sí, Anular";
                var est = 0;
            } else {
              
            }
            swal({
                title: title,
                text: subtitle,
                input: 'text',
                type: 'question',
                inputPlaceholder: 'Ingresar Motivo de Anulación',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: button,
                cancelButtonText: 'Cancelar',
                inputValidator: nombre => {
                    // Si el valor es válido, debes regresar undefined. Si no, una cadena
                    if (!nombre) {
                        return "Por favor escribir motivo de Anulación";
                    } else {
                        return undefined;
                    }
                }
            }).then((result) => {
                if (result.value) {
                    /*  $('#loading').show();
                        $('#wrapper').addClass('body-load'); */
                        var moti= result.value;
                    $.ajax({
                        url: '../business/controller/class.factura.php',
                        data: { funcion: 4, id: id_pro, estado: est, motivo: moti, doc_IdSerieCaja: id_caja},
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            if (arr.ok == 1) {
                                // Metodo para devolver a inventario.
                                var Observaciones= 'Factura Anulada N° '+id_pro;
                                //Obtener detalles de la factura
                                var det = nota.getFacturaDetal(id_pro, Observaciones);
                                //console.log('Este es el error NOTA', det);
                                    // var detallesNota = det ;

                            }else{
                                swal({
                                    type: 'error',
                                    title: 'Error',
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
            })
        }
    }


    // Metodo para Consultar detalle de la factutra ha anular
    getFacturaDetal (id_doc, observaciones){

        $.ajax({
            url: '../business/controller/class.nota.php',
            data: { funcion: 6, idDoc: id_doc },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('detalles nota anulada ', arr);
                var i=0;
                for (let d of arr.datos) {
                    detallesNota[i]={ 
                        "detkar_IdProducto": d.detDoc_IdProducto,
                        "detkar_Cantidad": d.detDoc_Cantidad,                                
                        "detkar_CostoUnitario": d.costoActivo, 
                        "detkar_IdBodega": d.idBodega,
                        "detkar_Costo": d.detDoc_ValorTotal 
                    };
                    i++;
                }
                var datos = JSON.stringify(detallesNota);
                 
                 console.log('detalles nota anulada en array ', datos);

                 // Metodo para devolver stock de productos, de la factura anulada.
                        $.ajax({
                            url: '../business/controller/class.nota.php',
                            data: { funcion: 1, kar_Tipo: 1,  detallesNota: datos, Observaciones: observaciones},
                            type: 'POST',
                            dataType: 'json',
                            success: function(arr) {
                                if (arr.ok == 1) {
                                    swal({
                                        type: 'success',
                                        title: 'Factura Anulada',
                                        text: 'Factura Anulada exitosamente',
                                    });
                                    $("#detalleNotas").DataTable().destroy();
                                    $("#bodyDetallesNotas").empty();
                                    nota.getNotas(0);
                                }else{
                                    swal({
                                        type: 'error',
                                        title: 'Error',
                                        text: arr.mensaje,
                                    });
                                }
                            },
                            error: function(XMLHttpRequest, textStatus, errorThrown) {
                                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                            }
                        });

//                console.log('array detalle ', detallesNota);
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });

    }


    /**
     * init_table: Método para asignar la
     * propiedad DataTable() a la tabla de productos
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
     * getCategoriaById: Método para consultar la
     * información de un Categoria
     * @param type $id: llave primaria de la tabla Categorias
     */
        async getAbonoCuentaById(id, totalNota, strNumeroPrefijo, idTesoreria) {

            var permiso = await _permisos.getPermisos(idRol, 23111);

            if (permiso.ok != 1) {
                swal({
                    type: 'warning',
                    title: 'Error de privilegios',
                    text: 'Usted no tiene los privilegios para realizar esta acción,' +
                        'para obtenerlos comuniquese con el admininstrador del sistema',
                });
            } else {
                
                $("#formCrearAbonosCuentas").attr('action', 'javascript:nota.postAbonosCuentas(' + id + ','+totalNota+','+"'"+strNumeroPrefijo+"'"+','+idTesoreria+')');
                $("#btnCrearAbonosCuentas").empty();
                $("#modal_footer").empty();
                $("#modal_footer").append(
                    '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>' +
                    ' Cancelar' +
                    '</button>' +
                    '<button type="submit" class="btn btn-success btn-pill"><span class="ti-plus"></span>' +
                    ' Crear' +
                    '</button>'
                );
                $("#modal-AbonosCuentas").modal('show');
            
            }
        }

     /**
     * postAbonosCuentas: Método para insertar abonos cuentas a credito
     */
      postAbonosCuentas(id, totalNota, strNumeroPrefijo, idTesoreria) {
        var Kar_Valor = $("#Kar_Valor").val();
        var kar_Cuentas = $("#kar_Cuentas").val();
        var id_caja = sessionStorage.getItem('id_caja');

        $.ajax({
            url: '../business/controller/class.cuentasPorCobrar.php',
            data: { funcion: 1, id: id, Kar_Valor: Kar_Valor, kar_Cuentas: kar_Cuentas, totalNota: totalNota, 
                    strNumeroPrefijo: strNumeroPrefijo, idTesoreria: idTesoreria, doc_IdSerieCaja: id_caja},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('abono', arr);
                /*  $('#loading').hide();
                 $('#wrapper').removeClass('body-load'); */
                if (arr.ok == 1) {

                    $("#modal-AbonosCuentas").modal('hide');
                    nota.getNotas(0);
                    swal({
                        type: 'success',
                        title: 'Abono Aplicado',
                        text: 'Abono Aplicado Exitosamente',
                    });

                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo Aplicar el abono',
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
     * getNotas: Método para consultar las notas 
     */
    getNotas(estadoFactura) {

        $.ajax({
            url: '../business/controller/class.factura.php',
            data: { funcion: 11, idFormaPago: 50, estadoPago: estadoFactura},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('not ', arr);

                if (arr.ok == 1) {
                    nota.draw_table_documents(arr.datos);
                } else {
                    $("#notasRegistradas").DataTable().destroy();
                    nota.init_table();
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });

    
        
    }

    /**
     * getFormasPago: Método para consultar las Formas de Pago
     */
    getFormasPago() {

        $.ajax({
            url: '../business/controller/class.formasPago.php',
            data: { funcion: 3, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#kar_Cuentas").empty();
                $("#kar_Cuentas").append('<option class"ulCombo" value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                        $.each(arr.datos, function(k, v) {
                            if(v['forpa_Id']  != 50){    
                                $("#kar_Cuentas").append('<option  class"ulCombo" value="' + v['forpa_Id'] + '">' + v['forpa_Descripcion'] + '</option>');
                            }
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
     * getBodega: Método para consultar las bodegas
     */
    getBodega() {

        $.ajax({
            url: '../business/controller/class.bodega.php',
            data: { funcion: 3, estado: 1 , tipo: 1},
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
     * detalle de las notas
     * @param type $idNota: Listado de objetos de tipo nota
     */
    verDetalle(idNota) {
        $.ajax({
            url: '../business/controller/class.nota.php',
            data: { funcion: 5, idNota: idNota },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('det ', arr)
                $("#ltsDetallesNota").DataTable().destroy();
                $("#bodyDetallesNota").empty();

                if (arr.ok == 1) {

                    for (let d of arr.datos) {
                        $("#bodyDetallesNota").append(
                            '<tr>' +
                            '<td>' + d.detkar_NombreFacturar + '</td>' +
                            '<td>' + d.bod_Nombre + '</td>' +
                            '<td class="numero" align="rigth">' + d.detkar_CantidadEntrada + '</td>' +
                            '<td class="numero" align="rigth">' + d.detkar_ValorUnitario + '</td>' +
                            '<td class="numero" align="rigth">' + d.detkar_ValorEntrada + '</td>' +
                            //'<td class="numero" align="rigth">' + d.detkar_ValorSalida + '</td>' +
                            //'<td class="numero" align="rigth">' + d.detkar_CantidadSaldo + '</td>' +
                            //'<td class="numero" align="rigth">' + d.detkar_ValorSaldo + '</td>' +
                            '</tr>'
                        );
                    }

                    $('.numero').number(true, 2);
                    nota.init_table_detalle();
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
     * propiedad DataTable() a la tabla de productos
     */
    init_table_detalle() {
        $('#ltsDetallesNota').DataTable({
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
        var pro = $("#detkar_IdProducto").val();
        var cant = $("#detkar_Cantidad").val();
        var cost = $("#detkar_Costo").val();
        var bod = $("#detkar_IdBodega").val();
        console.log('pro ', pro, 'cant ', cant, 'cost ', cost, 'bod ', bod);
        if (pro != "" && cant != "" && cost != "" && bod != "") {
            var namePro = $('#detkar_IdProducto option:selected').text();
            var nameBod = $('#detkar_IdBodega option:selected').text();
            detallesNota.push({ "detkar_IdProducto": pro, "detkar_Cantidad": cant, "detkar_CostoUnitario": cost, "detkar_IdBodega": bod, "nomProducto": namePro, "nomBodega": nameBod });
        }
        nota.pintarTable();

        $('#detkar_IdProducto').val($('#detkar_IdProducto > option:first').val());


        $('#detkar_IdBodega').get(0).selectedIndex = 1;
        $('#detkar_Cantidad').val('');
        $('#detkar_Costo').val('');

        console.log('detallesNota ', detallesNota);
    }

    eliminarDetalle(posicion) {
        detallesNota.splice(posicion, 1);
        nota.pintarTable();
    }

    /**
     * postNota: Método para crear notas
     */
    postNota() {

        /* $('#loading').show();
        $('#wrapper').addClass('body-load'); */

        if (detallesNota.length < 1) {
            swal({
                type: 'warning',
                title: 'No existen productos asignados en el detalle de la  nota',
                text: 'Debe ingresar al menos un producto en el detalle de la nota',
            });
        } else {
            var tipo = $("#kar_Tipo").val()
            var Observaciones = $("#Kar_Observaciones").val();
            var det = JSON.stringify(detallesNota);
            $.ajax({
                url: '../business/controller/class.nota.php',
                data: { funcion: 1, kar_Tipo: tipo, Observaciones: Observaciones, detallesNota: det },
                type: 'POST',
                dataType: 'json',
                success: function(arr) {

                    if (arr.ok == 1) {
                        $("#formCrearNota").trigger("reset");
                        $("#modal-Nota").modal('hide');
                        $("#detalleNotas").DataTable().destroy();
                        $("#bodyDetallesNotas").empty();
                        detallesNota = [];
                        nota.getNotas(0);
                        swal({
                            type: 'success',
                            title: 'Nota creada',
                            text: 'Nota creada correctamente',
                        });
                    } else if (arr.ok == 2) {
                        $("#formCrearNota").trigger("reset");
                        $("#modal-Nota").modal('hide');
                        $("#detalleNotas").DataTable().destroy();
                        $("#bodyDetallesNotas").empty();
                        detallesNota = [];
                        nota.getNotas(0);
                        swal({
                            type: 'warning',
                            title: 'Sin existencias',
                            text: "Uno o más productos relacionados en la nota no tienen existencias",
                        });
                    } else if (arr.ok == 3) {
                        $("#formCrearNota").trigger("reset");
                        $("#modal-Nota").modal('hide');
                        $("#detalleNotas").DataTable().destroy();
                        $("#bodyDetallesNotas").empty();
                        detallesNota = [];
                        nota.getNotas(0);
                        swal({
                            type: 'warning',
                            title: 'Sin existencias',
                            text: arr.mensaje,
                        });
                    } else {
                        swal({
                            type: 'error',
                            title: 'Ocurrió un error al intentar crear la nota',
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

    async cambiarEstado(id_nota) {
//        var permiso = await _permisos.getPermisos(idRol, 521);
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
                var title = "¿Está seguro de anular la nota?";
                var subtitle = "Una vez anulada, no podrá ser recuperada. Esta acción es irreversible";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar el producto?";
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
                        url: '../business/controller/class.nota.php',
                        data: { funcion: 4, id: id_nota, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);

                            if (arr.ok == 1) {
                                nota.getNotas(0);
                                swal({
                                    type: 'success',
                                    title: 'Nota anulada',
                                    text: 'Nota anulada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo anular la nota',
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


    NotaActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DTesoreria").addClass('expand');
        $("#DTesoreria").addClass('active');
        $("#SFTesoreria").addClass('show');
        $("#SubMenuCuentasPorCobrar").addClass('active');
    }

}

const nota = new Nota();


nota.NotaActivo();
nota.getNotas(0);
nota.getFormasPago();


$(document).ready(function() {
    $("#detkar_Cantidad").number(true, 2);
    $("#detkar_Costo").number(true, 2);
});