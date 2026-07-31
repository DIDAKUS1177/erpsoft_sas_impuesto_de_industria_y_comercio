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
        var permiso = await _permisos.getPermisos(idRol, 623);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {

            //var input = document.getElementById('detkar_IdProducto');
            //input.focus();
            //input.select();
            
            $("#clave").removeAttr('style');
            $("#usu_Clave").attr('required', true);
            $("#formCrearNota").trigger("reset");
            $("#formCrearNota").attr('action', 'javascript:nota.postNota()');

            await nota.getProducto();
            //await nota.getBodega();
            await nota.getClientes();
            await nota.formasPago();
            await nota.cargarFacturacion();
            await nota.validarVigenciaResolucion();

            // ------- DATOS DE FACTARACION -------------------------------------
            var IdBodega = sessionStorage.getItem('id_Bodega');
            var NomBodega = sessionStorage.getItem('nom_Bodega');
            var NomSede = sessionStorage.getItem('nom_Sede');
            var NomCaja = sessionStorage.getItem('nom_Caja');

            $("#sede").val(NomSede + ' / ' + NomCaja);            
            $("#detkar_IdBodega").empty();
            $("#detkar_IdBodega").append('<option value="' +  IdBodega + '">' + NomBodega + '</option>');
            $("#detkar_IdBodega").addClass('custom-select2');
            // -------------------------------------------------------------------
            
            $("#modal_footer").empty();
            $("#modal_footer").append(
                '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>' +
                ' Cancelar' +
                '</button>' +
                '<button type="submit" class="btn btn-success btn-pill"><span class="ti-plus"></span>' +
                ' Crear' +
                '</button>'
            );
            //$("#modal-Nota").modal('show');
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
        var totalp = 0;
        var totalImpuestos = 0;
        var totalBruto = 0;

        if (detallesNota.length > 0) {
            console.log('detallesNota2 ', detallesNota);
            for (let d of detallesNota) {
                $("#bodyDetallesNotas").prepend(
                    '<tr>' +
                    '<td>' + d.nomProducto + '</td>' +
                    //'<td align="right">' + d.detkar_CostoText + '</td>' +
                    '<td align="right"> <input type="text" class="form-control" id="detkar_PrecioModi'+ cont +'" name="detkar_PrecioModi'+ cont +'" value= "'+ d.detkar_CostoUnitario +'" style="text-align: right;" onChange="nota.actualizarPrecioPro(' + cont + ')"></input> </td>' +
                    '<td align="right">' + d.detkar_CostoText + '</td>' +
                    '<td align="right">' + d.detkar_Cantidad + '</td>' +
                    //'<td align="right">' + d.detkar_Costo + '</td>' +
                    //'<td>' + d.nomBodega + '</td>' +
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
                [3,5, 10, 25, 50, -1],
                [3,5, 10, 25, 50, "All"]
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

            if (not.doc_Estado != 1) {
                var disabled = "";
                var titulo = "Nota Nota";
                var clasee = "btn-light";
                var anulada= "disabled";
            } else {
                var disabled = "disabled";
                var titulo = "Anular Anulada";
                var clasee = "btn-danger";
                var anulada= "";
            }

            if (not.kar_Tipo == 1) {
                var NomTipo = "NE";
            } else {
                var NomTipo = "NS";
            }

            $('#bodyNotasRegistradas').append(
                '<tr>' +
                '<td>' +
                not.doc_Fecha +
                '</td>' +
                '<td>' +
                not.doc_Prefijo +
                '</td>' +
                '<td>' +
                not.doc_Numero +
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

                // FACTURA 80 mm
                '<a href="../extensiones/tcpdf/pdf/factura.php?codigo=' + not.doc_Id + '" target="_blank" class="btn btn-success btn-pill">' +
                
                // FACTURA 58 mm
                //'<a href="../extensiones/tcpdf/pdf/factura_58mm.php?codigo=' + not.doc_Id + '" target="_blank" class="btn btn-success btn-pill">' +

                '<span class="ti-plus"></span>' +
                '</a>'+
                
                '</td>' +
                
                '<td>' +
                    '<button type="button" class="btn btn-social-icon ' + clasee + ' " data-toggle="tooltip" title="' + titulo + '"  style="margin-right:5px" onclick="javascript:nota.anularFactura(' + not.doc_Id + ',' + not.doc_Estado + ')" '+anulada+'>' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
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
                        data: { funcion: 4, id: id_pro, estado: est, motivo: moti},
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
     * getNotas: Método para consultar las notas 
     */
    getNotas() {

        var idVendedor = sessionStorage.getItem('id_Usuario');

        var n =  new Date();
        var y = n.getFullYear();
        var m = n.getMonth()+1;        
        var d = n.getDate();
        if(d<10){ d='0'+d; }
        if(m<10){ m='0'+m; }
        var fechafull = y + "-" + m + "-" + d;

        $.ajax({
            url: '../business/controller/class.factura.php',
            data: { funcion: 3, doc_IdVendedor: idVendedor, doc_Fecha: fechafull },
            //data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('not ', arr);

                if (arr.ok == 1) {
                    $("#bodyDetallesNota").empty();
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
        // Cargar Modal de Facturación        
        // nota.crearNota();
    }

    /**
     * getProducto: Método para consultar los productos
     */
    getProducto() {

        $.ajax({
            url: '../business/controller/class.producto.php',
            data: { funcion: 3, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
               // $("#detkar_IdProducto").empty();
                //$('#detkar_IdProducto').autofocus;
                //$("#detkar_IdProducto").append('<option class"ulCombo" codigobarras ="" value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        // Busqueta por COD_BARRAS Y NOMBRE
                        
                        $("#tablaTatil").append('<button type="button" nombreProducto ="' + v['pro_Nombre'] + '" name="detkar_IdProducto" id="detkar_IdProducto"  value="' + v['pro_Id'] + '" class="btn btn-primary" onclick="nota.cargarPrecios()">' + v['pro_Nombre'] + '</button>');

                        //$("#tablaTatil").append('<a nombreProducto ="' + v['pro_Nombre'] + '" name="detkar_IdProducto" id="detkar_IdProducto"  value="' + v['pro_Id'] + '" class="btn btn-primary" onclick="nota.cargarPrecios()" href="#">' + v['pro_Nombre'] + '</a>');


                        // Busqueta por COD_BARRAS
                        //$("#detkar_IdProducto").append('<option  class"ulCombo" nombreProducto ="' + v['pro_Nombre'] + '" value="' + v['pro_Id'] + '">' + v['pro_CodBarras'] + ' </option>');

                        // Busqueta por NOMBRE
                        //$("#detkar_IdProducto").append('<option  class"ulCombo" nombreProducto ="' + v['pro_Nombre'] + '" value="' + v['pro_Id'] + '">' + v['pro_Nombre'] + '  </option>');
                    });
                }

               // $("#detkar_IdProducto").addClass('custom-select2');
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

    agregarDetalle() {

        var stockActivo = $("#detkar_Stock").val();
        var pro = $("#detkar_IdProducto").val();
        var cant = $("#detkar_Cantidad").val();
        var cost = $("#detkar_Costo").val()*$("#detkar_Cantidad").val();

        //*********************************************************** */
        // Se obtiene el valor del precio del SELECT. 
         //var costTex = $("#detkar_Costo").find('option:selected').text()*$("#detkar_Cantidad").val();
         var costTex = $("#detkar_Costo").val()*$("#detkar_Cantidad").val();

        // Se obtiene el valor del precio del INPUT.
        // var costTex = $("#detkar_Costo").val()*$("#detkar_Cantidad").val();
        
        //*********************************************************** */
        // Se obtiene el valor del precio del SELECT. 
         //var costUni = $("#detkar_Costo").find('option:selected').text();
         var costUni = $("#detkar_Costo").val();
        
        // Se obtiene el valor del precio del INPUT.
        // var costUni = $("#detkar_Costo").val();
        //*********************************************************** */
        var bod = $("#detkar_IdBodega").val();
        

        var id_impu= '1.';

        if($("#impuesto").val() == 5){
            var num= '05';
        }else if($("#impuesto").val() == 8){
            var num='08';
        }else{
            var num= $("#impuesto").val();
        }
        var impu = id_impu+num;
        var bruto =(costTex / impu);    
        
        var impuesto = costTex - bruto;
        //var impuesto = (costTex * $("#impuesto").val())/100;
        
        console.log('impuesto ', cost,'-bruto-',bruto);
        console.log('cantidad ', cant,'-stock-',stockActivo);

        //if(cant <= stockActivo){
            // Valido que todos los campos esten con datos.
            //console.log('pro ', pro, 'cant ', cant, 'cost ', cost, 'bod ', bod);
            if (pro != "" && cant != "" && cost != "" && bod != "") {
                //if (pro != "" && cant != "" && bod != "") {
                //var namePro = $('#detkar_IdProducto option:selected').text();  
                //var namePro = $('#detkar_IdProducto option:selected').text();  
                
                //var namePro = $('#detkar_IdProducto option:selected').attr('nombreProducto');
                var namePro = $('#detkar_IdProducto').attr('nombreProducto');
                
                var nameBod = $('#detkar_IdBodega option:selected').text();
         
                detallesNota.push({ "detkar_Bruto": bruto, "detkar_Impuesto":impuesto,
                                    "detkar_IdProducto": pro, "detkar_Cantidad": cant, 
                                    "detkar_Costo": cost, "detkar_CostoUnitario": costUni,
                                    "detkar_CostoText": costTex, "detkar_IdBodega": bod,
                                    "nomProducto": namePro, "nomBodega": nameBod, "idImpuesto": impu });
                            
                //detallesNota.push({ "detkar_IdProducto": pro, "detkar_Cantidad": cant, "detkar_IdBodega": bod, "nomProducto": namePro, "nomBodega": nameBod });
            }else if(cost == "") {
                swal({
                    type: 'warning',
                    title: 'No tiene Precio',
                    text: 'El producto no tiene Precio de venta creado',
                });
            }

        //}else{

            //swal({
            //    type: 'warning',
            //    title: 'Stock Insuficiente',
            //    text: 'La cantidad supera el Stock',
            //});

        //}

        nota.pintarTable();

        //$('#detkar_IdProducto').val($('#detkar_IdProducto > option:first').val());
        //$('#detkar_IdProducto').get(0).selectedIndex = 0;

        //$('#detkar_IdBodega').get(0).selectedIndex = 1;
        var IdBodega = sessionStorage.getItem('id_Bodega');
        var NomBodega = sessionStorage.getItem('nom_Bodega');

        $("#detkar_IdBodega").empty();
        $("#detkar_IdBodega").append('<option value="' +  IdBodega + '">' + NomBodega + '</option>');

        $('#detkar_Cantidad').val(1);
        $('#detkar_Descuento').val(0);
        $('#detkar_Stock').val();
        //$('#detkar_Cantidad').get(0).selectedIndex = 1;
        //$('#detkar_Costo').get(0).selectedIndex = 1;

        console.log('detallesNota ', detallesNota);
    }

    eliminarDetalle(posicion) {
        detallesNota.splice(posicion, 1);
        nota.pintarTable();
    }

    actualizarPrecioPro(posicion) {
        //detallesNota.splice(posicion, 1);
        //nota.pintarTable();
        var cost = $("#detkar_PrecioModi"+posicion).val()*detallesNota[posicion].detkar_Cantidad;
        var costTex = $("#detkar_PrecioModi"+posicion).val()*detallesNota[posicion].detkar_Cantidad;
        var costUni = $("#detkar_PrecioModi"+posicion).val();
        var bruto =(costTex / detallesNota[posicion].idImpuesto);    
        var impuesto = costTex - bruto;

        detallesNota[posicion].detkar_Bruto = bruto;
        detallesNota[posicion].detkar_Costo = cost;
        detallesNota[posicion].detkar_CostoText = costTex;
        detallesNota[posicion].detkar_CostoUnitario = costUni;
        detallesNota[posicion].detkar_Impuesto = impuesto;
        
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
            var tipo = 2
            var doc_IdTipoDocumento = $("#id_tipoDocumento").val();
            if(doc_IdTipoDocumento == 1){
                var tip = 'Remisión';
            }else{var tip = 'Factura';}
            var doc_Prefijo = $("#prefijo").val();
            var doc_Numero = $("#numero").val();
            var Observaciones = tip+'= '+doc_Prefijo+'-'+doc_Numero;
            var doc_IdSerieCaja= $("#id_caja").val();
            var doc_IdCliente = $("#doc_IdCliente").val();
            var doc_IdVendedor = $("#id_vendedor").val(); 
            
            var doc_ValorBruto = $("#totalBruto").val();
            var doc_ValorImpuestos  = $("#totalImpuestos").val();
            var doc_ValorNeto = $("#total").val();
            var doc_IdFormaPago = $("#doc_IdFormaPago").val();
            var valor_dado = $("#valor_dado").val();
            var campo_personalizado = $("#campo_personalizado").val();
            
            var vueltas = valor_dado - doc_ValorNeto;
            console.log('VUELTAS', vueltas);
            
            console.log('Este es el errorrr', doc_Prefijo,'-',doc_Numero,'-',doc_IdSerieCaja,'-',doc_IdCliente);
            console.log('Este es el errorrr', doc_IdVendedor,'-',doc_IdTipoDocumento,'-',doc_ValorBruto,'-',doc_ValorImpuestos);
            var det = JSON.stringify(detallesNota);
            $.ajax({
                url: '../business/controller/class.nota.php',
                data: { funcion: 1, kar_Tipo: tipo, Observaciones: Observaciones, detallesNota: det },
                type: 'POST',
                dataType: 'json',
                success: function(arr) {

                    if (arr.ok == 1) {

                        // Crear la Docuemnto Factura 
                        $.ajax({
                            url: '../business/controller/class.factura.php',
                            data: { funcion: 1, 
                                doc_Prefijo: doc_Prefijo, 
                                doc_Numero: doc_Numero, 
                                doc_IdSerieCaja: doc_IdSerieCaja, 
                                doc_IdCliente: doc_IdCliente, 
                                doc_IdVendedor: doc_IdVendedor, 
                                doc_IdTipoDocumento: doc_IdTipoDocumento, 
                                doc_ValorBruto: doc_ValorBruto, 
                                doc_ValorImpuestos: doc_ValorImpuestos, 
                                doc_ValorNeto: doc_ValorNeto, 
                                doc_IdFormaPago: doc_IdFormaPago, 
                                Observaciones: Observaciones, detallesNota: det,
                                campo_personalizado: campo_personalizado },
                            type: 'POST',
                            dataType: 'json',
                            success: function(arr) {
            
                                if (arr.ok == 1) {
                                    $("#formCrearNota").trigger("reset");
                                    $("#modal-Nota").modal('hide');
                                    $("#detalleNotas").DataTable().destroy();
                                    $("#bodyDetallesNotas").empty();
                                    detallesNota = [];
                                    nota.getNotas();

                                    swal({
                                        title: 'Factura Creada',
                                        text: 'VALOR A DEVOLVER:'+vueltas,
                                        type: 'question',
                                        showCancelButton: true,
                                        confirmButtonColor: '#3085d6',
                                        cancelButtonColor: '#d33',
                                        confirmButtonText: 'Imprimir',
                                        cancelButtonText: 'No Imprimir'
                                    }).then((result) => {
                                        if (result.value) {
                                            /*$('#loading').show();
                                            $('#wrapper').addClass('body-load');*/
//                                            window.location = '../extensiones/tcpdf/pdf/factura.php?codigo='+arr.datos;
                                            // FACTURA DE 80 mm
                                            window.open('../dist/factura.php','_self');
                                            window.open('../extensiones/tcpdf/pdf/factura.php?codigo='+arr.datos+'', '_blank');

                                            // FACTURA DE 58 0mm
                                            // window.open('../extensiones/tcpdf/pdf/factura_58mm.php?codigo='+arr.datos+'', '_blank');
                                        }else{
                                            window.open('../dist/factura.php','_self');
                                        }
                                            
                                        
                                    })


/*

                                    swal({
                                        type: 'success',
                                        title: 'Factura Creada',
                                        text: 'VALOR A DEVOLVER:'+vueltas,
                                    }, 
                                    function(){
                                       //  window.location.href = "predial/dist/producto.php";
                                       window.location = 'extensiones/tcpdf/pdf/factura.php?codigo='+$codigoventa+'';
                                    })

                                    */
                                }  else {
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



                    } else if (arr.ok == 2) {
                        $("#formCrearNota").trigger("reset");
                        $("#modal-Nota").modal('hide');
                        $("#detalleNotas").DataTable().destroy();
                        $("#bodyDetallesNotas").empty();
                        detallesNota = [];
                        nota.getNotas();
                        swal({
                            type: 'warning',
                            title: 'Sin existencias',
                            text: "Uno o más productos relacionados en la nota no tiene existencias Iniciales.",
                        });
                    } else if (arr.ok == 3) {
                        $("#formCrearNota").trigger("reset");
                        $("#modal-Nota").modal('hide');
                        $("#detalleNotas").DataTable().destroy();
                        $("#bodyDetallesNotas").empty();
                        detallesNota = [];
                        nota.getNotas();
                        swal({
                            type: 'warning',
                            title: 'Sin existencias',
                            text: arr.mensaje,
                        });
                    } else {
                        swal({
                            type: 'error',
                            title: 'Ocurrió un error al intentar crear la nota 1',
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
        var permiso = await _permisos.getPermisos(idRol, 521);
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
                                nota.getNotas();
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

        $("#DFacturacion").addClass('expand');
        $("#DFacturacion").addClass('active');
        $("#SFacturacion").addClass('show');
        $("#SubMenuFactura").addClass('active');
    }


    //******************************************************************//
    
    /**
     * getClientes: Método para consultar los clientes
     */
    getClientes() {

        var f = new Date();
        $("#fecha_dia").val(f.getDate() + "/" + (f.getMonth() +1) + "/" + f.getFullYear());


        $.ajax({
            url: '../business/controller/class.cliente.php',
            data: { funcion: 3, estado: 1},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#doc_IdCliente").empty();
                //$("#doc_IdCliente").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#doc_IdCliente").append('<option value="' + v['cli_Id'] + '">'+ v['cli_Identificacion']+' / '+v['cli_RazonSocial'] + ' </option>');
                    });
                }
                $("#doc_IdCliente").addClass('custom-select2');
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }


    /**
     * getClientes: Método para consulta la sede logueada
     */
    getSedeCaja() {

        $.ajax({
            url: '../business/controller/class.cliente.php',
            data: { funcion: 3, estado: 1},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#doc_IdCliente").empty();
                //$("#doc_IdCliente").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#doc_IdCliente").append('<option value="' + v['cli_Id'] + '">' + v['cli_RazonSocial'] + '</option>');
                    });
                }
                $("#doc_IdCliente").addClass('custom-select2');
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * getClientes: Método para visualziar calculadora tactil
     */
    cargarCalculadora(numero) {
       
        //Cliente
        if(numero == 1){
            $("#activadorFoco").val('doc_IdCliente');
        //Cantidad
        }else if (numero == 2){
            $("#activadorFoco").val('detkar_Cantidad');
        //Producto
        }else if(numero == 3){
            $("#activadorFoco").val('detkar_IdProducto');
        //Precio Producto
        }else if(numero == 4){
            $("#activadorFoco").val();
        //Valor Pagado
        }else if(numero == 5){
            $("#activadorFoco").val('valor_dado');
        }

    }

    /**
     * getClientes: Método para ingresar precio al INPUT NAME=detkar_Costo
     */
     cargarValuePrecio(num) {

        if(num == 'c'){
            $("#"+$("#activadorFoco").val()).val(0);
        }else{
            var ini = $("#"+$("#activadorFoco").val()).val();
            var total = ini + num;
            $("#"+$("#activadorFoco").val()).val(total);
            total= 0;
        }
    
    }

     /*
     cargarValuePrecio(num) {
        
        var tipo = $("#activador").val();
        var tipoNumero = $("#activadorProducto").val();
        
        console.log('numerol: ', num);
        console.log('activador: ', tipo);

        // Campo de Precio del Producto.
        if(tipo == 1){
            console.log('entro: ', tipo);
            if(num == 'c'){
                $("#detkar_Costo").val(0);
            }else{
                var ini = $("#detkar_Costo").val();
                var total = ini + num;
                $("#detkar_Costo").val(total);
                total= 0;
            }
        // Campo de Valor ingresado del Producto.
        }else if(tipo == 2){
            if(num == 'c'){
                $("#valor_dado").val(0);
            }else{
                var ini = $("#valor_dado").val();
                var total = ini + num;
                $("#valor_dado").val(total);
                total= 0;
            }
        }else if(tipo == 3){
            if(num == 'c'){
                $("#detkar_PrecioModi"+tipoNumero).val(0);
            }else{
                var ini = $("#detkar_PrecioModi"+tipoNumero).val();
                var total = ini + num;
                $("#detkar_PrecioModi"+tipoNumero).val(total);
                total= 0;
            }
        }
        if (tipoNumero >= 1){
            nota.actualizarPrecioPro(tipoNumero);
        }
        
    }
    */

   

    /**
     * getClientes: Método para cargar los precios del producto
     */
    cargarPrecios() {

        var detkar_IdProducto = $("#detkar_IdProducto").val();
        //var detkar_IdProducto = document.getElementById("detkar_IdProducto").value;
        var detkar_Descuento = $("#detkar_Descuento").val();
        
        console.log('precios: ', detkar_IdProducto);
        nota.cargarStock();

        $.ajax({
            url: '../business/controller/class.preciosVenta.php',
            data: { funcion: 3, preVen_IdProducto: detkar_IdProducto},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#detkar_Costo").empty();
                //$("#doc_IdCliente").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {      
                        // Cargamos el precio en un SELECT      
                        var finla_valor = v['preVen_PrecioNeto'] - detkar_Descuento ;

                        //$("#detkar_Costo").append('<option value="' + v['preVen_Id'] + '">' + finla_valor + '</option>');
                        
                        // Cargamos el precio en un IMPUT para editarlo manualmente
                         $("#detkar_Costo").val(finla_valor);

                        $("#impuesto").val(v['strIdImpuesto']);
                    });
                }
                //$("#detkar_Costo").addClass('custom-select2');                
                nota.agregarDetalle();
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
            
        });
        
    }


    /**
     * getCargar Stock: Método para cargar el stock del producto
     */
    cargarStock() {

        var detkar_IdProducto = $("#detkar_IdProducto").val();
        $("#detkar_Stock").empty();
        $.ajax({
            url: '../business/controller/class.producto.php',
            data: { funcion: 3, id: detkar_IdProducto},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);

                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {      
                        $("#detkar_Stock").val(v['strTotalStock']);
                    });
                }

                //nota.agregarDetalle();
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
            
        });
        
    }
    

    /**
     * getFacturacion: Método para cargar los datos de Facturacion
     */
    cargarFacturacion() {

        var IdCaja = sessionStorage.getItem('id_caja');
        var id_Usu = sessionStorage.getItem('id_Usuario');
        
        console.log('IdCaja ', IdCaja);

        $.ajax({
            url: '../business/controller/class.factura.php',
            data: { funcion: 9, seemca_Id: IdCaja},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {                        
                        $("#prefijo").val(v['reso_Prefijo']);  
                        if(!$.trim(v['strMaxId'])){
                            $("#numero").val(1); 
                        }else{$("#numero").val(v['strMaxId']); }
                        $("#id_caja").val(v['seemca_Id']); 
                        $("#id_vendedor").val(id_Usu); 
                        $("#id_tipoDocumento").val(v['reso_IdTipoDocumento']); 
                    })                                    
                }else{
                    $("#numero").empty();
                    $("#prefijo").empty();
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * getFacturacion: Método para validar vigencia de Resolución
     */
     validarVigenciaResolucion() {

        var IdCaja = sessionStorage.getItem('id_caja');
        
        console.log('IdCaja ', IdCaja);

        $.ajax({
            url: '../business/controller/class.factura.php',
            data: { funcion: 9, seemca_Id: IdCaja},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {   

                        if(v['reso_IdTipoDocumento'] == 1){
                            $("#modal-Nota").modal('show'); 
                        }else{
                            var num = parseInt(v['reso_NumeroFinal'])-5; 

                            const fecha = new Date();
                            var day = fecha.getDate();
                            var month = fecha.getMonth() + 1;
                            var year = fecha.getFullYear();
                            var monthh=0;
    
                            if(month < 10){
                                monthh = "0"+month;
                            }else{ monthh = month; }
    
                            var fechaTotal = year + "-" + monthh + "-" + day;
                            

                            if(parseInt(v['strMaxId']) == parseInt(num)){ 
                                
                                $("#modal-Nota").modal('show'); 
                                swal({
                                    type: 'warning',
                                    title: 'Quedan menos de 5 consecutivos para su expiración',
                                    text: 'Contáctese con el Administrador',
                                });
                                
                            /*}else if (fechaTotal  ==  fechavalidar){
                                $("#modal-Nota").modal('show'); 
                                swal({
                                    type: 'warning',
                                    title: 'Quedan menos de 5 dias para vencer la fecha de autorización. Fecha Vencimiento: '.v['reso_FechaVencimiento'],
                                    text: 'Contáctese con el Administrador',
                                });

*/
                            }else{

                                //Valida COnsecutivo habilitado por resolución
                                console.log('ID: ',v['strMaxId']);
                                if(v['strMaxId'] == null){
                                    $("#modal-Nota").modal('show');
                                    console.log('ENTRO: ',v['strMaxId']);
                                }else{
                                    if(parseInt(v['strMaxId']) <= parseInt(v['reso_NumeroFinal'])){ 
                                    
                                    console.log('ID MAX ', v['strMaxId'],' CONSECU: ',v['reso_NumeroFinal']);
                                    
                                    if(fechaTotal  <  v['reso_FechaVencimiento'] ){                                  
                                        $("#modal-Nota").modal('show'); 
                                    
                                    }else{
                                        $("#formCrearNota").empty();
                                        $("#modal-Nota").modal('hide');
                                        swal({
                                            type: 'warning',
                                            title: 'Fecha de la Resolución Vencida',
                                            text: 'Contáctese con el Administrador',
                                        });
                                        // Mensaje de Fecha de vencimiento activa.   
                                    }

                                }else{
                                    $("#formCrearNota").empty();
                                    $("#modal-Nota").modal('hide');
                                    swal({
                                        type: 'warning',
                                        title: 'Consecutivo de la Resolución Vencida',
                                        text: 'Contáctese con el Administrador',
                                    });
                                    // Mensaje de Consucutivo se finalizo.
                                }
                                    
                                }

                            }

                        }
                    })                                    
                }else{
                    $("#numero").empty();
                    $("#prefijo").empty();
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);                
            }
        });
    }



    /**
     * getClientes: Método para cargar los precios del producto
     */
    formasPago() {

        $.ajax({
            url: '../business/controller/class.factura.php',
            data: { funcion: 7 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#doc_IdFormaPago").empty();
                $("#doc_IdFormaPago").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {                        
                        $("#doc_IdFormaPago").append('<option value="' + v['forpa_Id'] + '">' + v['forpa_Descripcion'] + '</option>');
                    });
                }
                $("#doc_IdFormaPago").addClass('custom-select2');
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }
    

    focusMethod = function getFocus() {
        document.getElementById("select2-detkar_IdProducto-container").focus();
      }

}

const nota = new Nota();

nota.NotaActivo();
nota.getNotas();
nota.crearNota();

$(document).ready(function() {
    $("#detkar_Cantidad").number(true, 2);
    $("#detkar_Costo").number(true, 0);
    //$("#detkar_Descuento").number(true, 0);
});


 

