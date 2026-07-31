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

            if (not.Kar_Estado == 1) {
                var disabled = "";
                var titulo = "Anular Nota";
            } else {
                var disabled = "disabled";
                var titulo = "Nota Anulada";
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

                '</tr>'
            );

        }
        nota.init_table();
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
                $("#detkar_IdProducto").empty();
                $('#detkar_IdProducto').autofocus;
                $("#detkar_IdProducto").append('<option class"ulCombo" codigobarras ="" value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        // Busqueta por COD_BARRAS Y NOMBRE
                        $("#detkar_IdProducto").append('<option  class"ulCombo" nombreProducto ="' + v['pro_Nombre'] + '" value="' + v['pro_Id'] + '">' + v['pro_CodBarras'] + ' / ' + v['pro_Nombre'] + '  </option>');

                        // Busqueta por COD_BARRAS
                        //$("#detkar_IdProducto").append('<option  class"ulCombo" nombreProducto ="' + v['pro_Nombre'] + '" value="' + v['pro_Id'] + '">' + v['pro_CodBarras'] + ' </option>');

                        // Busqueta por NOMBRE
                        //$("#detkar_IdProducto").append('<option  class"ulCombo" nombreProducto ="' + v['pro_Nombre'] + '" value="' + v['pro_Id'] + '">' + v['pro_Nombre'] + '  </option>');
                    });
                }

                $("#detkar_IdProducto").addClass('custom-select2');
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
         var costTex = $("#detkar_Costo").find('option:selected').text()*$("#detkar_Cantidad").val();

        // Se obtiene el valor del precio del INPUT.
        // var costTex = $("#detkar_Costo").val()*$("#detkar_Cantidad").val();
        
        //*********************************************************** */
        // Se obtiene el valor del precio del SELECT. 
         var costUni = $("#detkar_Costo").find('option:selected').text();
        
        // Se obtiene el valor del precio del INPUT.
        // var costUni = $("#detkar_Costo").val();
        //*********************************************************** */

        var bod = $("#detkar_IdBodega").val();
        var impuesto = (costTex * $("#impuesto").val())/100;
        var bruto = costTex - impuesto;
        console.log('impuesto ', cost,'-bruto-',bruto);

        console.log('cantidad ', cant,'-stock-',stockActivo);

        //if(cant <= stockActivo){
            // Valido que todos los campos esten con datos.
            //console.log('pro ', pro, 'cant ', cant, 'cost ', cost, 'bod ', bod);
            if (pro != "" && cant != "" && cost != "" && bod != "") {
                //if (pro != "" && cant != "" && bod != "") {
                //var namePro = $('#detkar_IdProducto option:selected').text();  
                //var namePro = $('#detkar_IdProducto option:selected').text();  
                var namePro = $('#detkar_IdProducto option:selected').attr('nombreProducto');
                
                var nameBod = $('#detkar_IdBodega option:selected').text();
         
                detallesNota.push({ "detkar_Bruto": bruto, "detkar_Impuesto":impuesto,
                                    "detkar_IdProducto": pro, "detkar_Cantidad": cant, 
                                    "detkar_Costo": cost, "detkar_CostoUnitario": costUni,
                                    "detkar_CostoText": costTex, "detkar_IdBodega": bod,
                                    "nomProducto": namePro, "nomBodega": nameBod });
                            
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
        $('#detkar_IdProducto').get(0).selectedIndex = 0;

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

    /**
     * postNota: Método para crear notas
     */
     async postInforme() {
      
    //    } else {
            var mesInicial = $("#month").val();
            var diaInicial = $("#day").val();
            var anoInicial = $("#year").val();
            var mesFinal = $("#monthFinal").val();
            var diaFinal = $("#dayFinal").val();
            var anoFinal = $("#yearFinal").val();

            var fechaInicial = anoInicial+'/'+mesInicial+'/'+diaInicial;
            var fechaFinal = anoFinal+'/'+mesFinal+'/'+diaFinal;

            console.log('inicial', fechaInicial, 'final', fechaFinal);

            $.ajax({
                url: '../business/controller/class.factura.php',
                data: { funcion: 8, fechaInicial: fechaInicial, fechaFinal: fechaFinal },
                type: 'POST',
                dataType: 'json',
                success: function(arr) {

                    if (arr.ok == 1) {

                        console.log('Informes ', arr);

                    } else if (arr.ok == 0) {
                        swal({
                            type: 'warning',
                            title: 'No Hay Facturas',
                            text: "No existen facturas creadas en el rango de fechas seleccionadas.",
                        });
                    } else {
                        swal({
                            type: 'error',
                            title: 'Ocurrió un error al intentar Generar Informe',
                            text: arr.mensaje,
                        });
                    }

                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                    //location.href = "../../login.html";
                }
            });

 //       }
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


    NotaActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DInformes").addClass('expand');
        $("#DInformes").addClass('active');
        $("#SInformes").addClass('show');
        $("#SubMenuInfoFac").addClass('active');
    }


    //******************************************************************//
    
    /**
     * getClientes: Método para consultar Informes FACTURACIÓN
     */
    getModulos() {
		
		$("#mod_IdModuloMorosos").append('<option value="3">Morosos</option>'); 

        $("#mod_IdModulo").append('<option value="1">Con Documentación</option>',
                                  '<option value="2">Sin Documentación</option>'); 
                                  $("#select_Cuentas").empty();
       
        var today = new Date();
        var year = today.getFullYear();

        for (let i = 1990; i <= year; i++) {
            $("#id_anioPago").append('<option value="' + i + '">' + i + '</option>');
			$("#id_anioPagoFinal").append('<option value="' + i + '">' + i + '</option>');
            $("#txtFechaAnio").append('<option value="' + i + '">' + i + '</option>');
        }

        for (let i = 1; i <= 12; i++) {
            $("#txtFechaMes").append('<option value="' + i + '">' + i + '</option>');
        }
                          
                                  
    }


    /**
      * getInactivar: Activar y Inactivar campos que no son necesarios para unos reportes.
    */
       getInactivar() {

       if($("#mod_IdModulo").val() == 2){   
            $('#txtFechaInicio').removeAttr("required");    
            $('#txtFechaFinal').removeAttr("required");

            $('#fechaInicial').hide();
            $("#fechaFinal").hide();
            $("#labelUltimoAnio").hide();
            
       } else{
            $('#txtFechaInicio').prop("required", true);
            $('#txtFechaFinal').prop("required", true);

            $('#fechaInicial').show();
            $("#fechaFinal").show();
            $("#labelUltimoAnio").show();
       }
      
    }

    /**
    * getInactivar: Activar y Inactivar campos que no son necesarios para unos reportes.
    */
        getActivarSede() {

            if(($("#mod_IdModulo").val() == 2) ){   
                    $('#id_anioPago').prop("required", true);
                    $("#id_anioPago").show();
                    $("#labelUltimoAnio").show();

                    $('#txtFechaMes').removeAttr("required");    
                    $('#txtFechaAnio').removeAttr("required");
        
                    $('#txtFechaMes').hide();
                    $("#txtFechaAnio").hide();
                    $("#labeltxtFechaInicio").hide();
                    $("#labeltxtFechaFinal").hide();

            } else{     
                    $('#id_anioPago').removeAttr("required");
                    $("#id_anioPago").hide();
                    $("#labelUltimoAnio").hide();

                    $('#txtFechaMes').prop("required", true);
                    $('#txtFechaAnio').prop("required", true);
        
                    $('#txtFechaMes').show();
                    $("#txtFechaAnio").show();
                    $("#labeltxtFechaInicio").show();
                    $("#labeltxtFechaFinal").show();
            }           
        }

   
    focusMethod = function getFocus() {
        document.getElementById("select2-detkar_IdProducto-container").focus();
      }

}

const nota = new Nota();


nota.NotaActivo();
nota.getModulos();

$(document).ready(function() {
    $("#detkar_Cantidad").number(true, 2);
    $("#detkar_Costo").number(true, 0);
});





