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
     * pintarTable: Método para pintar la tabla inicial
     * de los detalles de la nota
     */
    pintarTable() {
        $("#detalleNotas").DataTable().destroy();
        $("#bodyDetallesNotas").empty();
        var cont = 0;
        var totalp = 0;
        var totalImpuestos = 0;
        var totalBruto = 0;

        if (detallesNota.length > 0) {
            console.log('detallesNota2 ', detallesNota);
            for (let d of detallesNota) {
                $("#bodyDetallesNotas").prepend(
                    '<tr>' +
                    '<td>' + d.nomProducto + '</td>' +
                    '<td align="right">' + d.detkar_CostoText + '</td>' +
                    '<td align="right">' + d.detkar_Cantidad + '</td>' +
                    //'<td align="right">' + d.detkar_Costo + '</td>' +
                    '<td>' + d.nomBodega + '</td>' +
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-danger " data-toggle="tooltip" title="Eliminar detalle"  onclick="nota.eliminarDetalle(' + cont + ')">' +
                    '<i class="dw dw-ban"></i>' +
                    '</button>' +
                    '</td>' +
                    '</tr>'
                );
                
                totalp = totalp + d.detkar_CostoText;
                totalImpuestos = totalImpuestos + d.detkar_Impuesto;
                totalBruto = totalBruto + d.detkar_Bruto;
                cont++;
            }
            $("#total").val(totalp);
            $("#totalImpuestos").val(totalImpuestos);
            $("#totalBruto").val(totalBruto);
        }

        $('#detalleNotas').DataTable({
            scrollCollapse: true,
            autoWidth: false,
            responsive: true,   
            columnDefs: [{
                targets: "datatable-nosort",
                orderable: true,
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

        $("#notasRegistradas").DataTable().destroy();
        $("#bodyNotasRegistradas").empty();
        for (let not of arrFilter) {
            var icono = "dw dw-ban";
            var clase = "btn-danger";

            if (not.doc_Estado != 1 ) {
                var disabled = "";
                var titulo = "Factura Anulada";
                var clasee = "btn-light";
                var anulada= "disabled";
            } else {

                if(not.strCierreFactura == 1){
                    var anulada= "disabled";
                    var disabled = "disabled";
                    var titulo = "Factura ya Cerrada";
                    var clasee = "btn-dark";
                }else{
                    var anulada= "";
                    var disabled = "disabled";
                    var titulo = "Anular Anulada";
                    var clasee = "btn-danger";
                }
                
            }

            if (not.kar_Tipo == 1) {
                var NomTipo = "NE";
            } else {
                var NomTipo = "NS";
            }

            if (not.doc_MotivoAnulacion == null) {
                var anulacion = '';
            } else {
                var anulacion = '<br><strong>Motivo Anulación</strong><br><span>'+not.doc_MotivoAnulacion+'</span>';
            }
            
            var nom_empresa = sessionStorage.getItem('nom_tipoImpresora');
            if (nom_empresa == 1) {
                // FACTURA 80 mm
                var tipoImpresora = '<a href="../extensiones/tcpdf/pdf/factura.php?codigo=' + not.doc_Id + '" target="_blank" class="btn btn-success btn-pill"><span class="ti-plus"></span></a>' ;
            } else {
                // FACTURA 58 mm
                var tipoImpresora = '<a href="../extensiones/tcpdf/pdf/factura_58mm.php?codigo=' + not.doc_Id + '" target="_blank" class="btn btn-success btn-pill"><span class="ti-plus"></span></a>' ;
            }

            $('#bodyNotasRegistradas').append(
                '<tr>' +
                '<td>' +
                not.doc_Fecha +
                '</td>' +
                '<td>' +
                not.doc_Prefijo +'-'+not.doc_Numero +
                '</td>' +
                '<td>' +
                not.strNombreCliente +
                '</td>' +
                '<td>' +                 
                '$ '+ Number(parseInt(not.doc_ValorNeto).toFixed(0)).toLocaleString('es-CO') +
                '</td>' +

                '<td align="center">' +
                /*  '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar nota" style="margin-right:5px" onclick="javascript:nota.getNotaById('+not.kar_Id+')">'+
                     '<i class="mdi mdi-border-color"></i>'+
                 '</button>'+ */

                /*  '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:nota.cambiarEstado(' + not.kar_Id + ',' + not.Kar_Estado + ')" ' + disabled + ' style="margin-right: 5px">' +
                 '<i class="' + icono + '"></i>' +
                 '</button>' + 
                '<button type="button" class="btn btn-social-icon btn-success " data-toggle="tooltip" title="Ver detalles"  onclick="../extensiones/tcpdf/pdf/factura.php?codigo=' + not.doc_Id + '" >' +
                '<span class="ti-eye"></span>' +
                '</button>' +*/

                tipoImpresora +
                
                '</td>' +

                '<td>' +
                    '<button type="button" class="btn btn-social-icon ' + clasee + ' " title="' + titulo + '"  style="margin-right:5px" onclick="javascript:nota.anularFactura(' + not.doc_Id + ',' + not.doc_Estado + ')" '+anulada+'>' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    anulacion +
                '</td>' +

                '</tr>'
            );

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
                var subtitle = "Una vez anulada, no podra Activar la factura nuevamente";
                var button = "Sí, Anular";
                var est = 0;
            } else {
                //var title = "¿Está seguro de activar el producto?";
                //var subtitle = "Una vez activado, podrá ser utilizado";
                //var button = "Sí, activar";
                //var est = 1;
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
                        "detkar_CostoUnitario": d.detDoc_ValorUnitario, 
                        "detkar_IdBodega": 1,
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
                                    nota.getNotas();
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
                [0, "desc"]
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
     * getNotas: Método para consultar las notas 
     */
    getNotas() {

        var idVendedor = sessionStorage.getItem('id_Usuario');

        var idRol = sessionStorage.getItem('id_Rol');

        var n =  new Date();
        var y = n.getFullYear();
        var m = n.getMonth()+1;        
        var d = n.getDate();
        if(d<10){ d='0'+d; }
        if(m<10){ m='0'+m; }
        var fechafull = y + "-" + m + "-" + d;

        if(idRol == 1){
            $.ajax({
                url: '../business/controller/class.factura.php',
                data: { funcion: 3 },
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

        }else{
            $.ajax({
                url: '../business/controller/class.factura.php',
                data: { funcion: 3, doc_IdVendedor: idVendedor },
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

        // Cargar Modal de Facturación        
        // nota.crearNota();
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
                            '<td>' + d.pro_Nombre + '</td>' +
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

    NotaActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DFacturacion").addClass('expand');
        $("#DFacturacion").addClass('active');
        $("#SFacturacion").addClass('show');
        $("#SubMenuFacturaT").addClass('active');
    }

}

const nota = new Nota();


nota.NotaActivo();
nota.getNotas();

$(document).ready(function() {
    $("#detkar_Cantidad").number(true, 2);
    $("#detkar_Costo").number(true, 0);
});


 

