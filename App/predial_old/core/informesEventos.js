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

        $("#DEventos").addClass('expand');
        $("#DEventos").addClass('active');
        $("#SubEventos").addClass('show');
        $("#SubMenuInformeEventos").addClass('active');
    }


    //******************************************************************//
    
    /**
     * getClientes: Método para consultar Informes FACTURACIÓN
     */
    getModulos() {

        $("#mod_IdModulo").append('<option value="1">Por Utilidad</option>'); 
        
    /*
        $.ajax({
            url: '../business/controller/class.cliente.php',
            data: { funcion: 3, estado: 1},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#mod_IdModulo").empty();
                //$("#doc_IdCliente").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#mod_IdModulo").append('<option value="' + v['cli_Id'] + '">' + v['cli_RazonSocial'] + '</option>');
                    });
                }
                $("#mod_IdModulo").addClass('custom-select2');
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    */
    }


    /**
     * getClientes: Método para consultar Informes FACTURACIÓN
     */
         getPorModulos() {

            $("#mod_IdModulos").append('<option value="1">Inventario</option>',
                                      '<option value="2">Clientes</option>'); 
   
        }
       
    /**
     * getModulosCuentas: Método para consultar Informes FACTURACIÓN
     */
     getModulosCuentas() {

        $("#mod_IdModuloCuentas").append('<option value="8">Por Cuentas</option>',
                                  '<option value="9">Informe General</option>'); 
    }

    /**
     * getCuentas: Método para consultar Informes FACTURACIÓN
     */
    getCuentas() {
        $.ajax({
            url: '../business/controller/class.formasPago.php',
            data: { funcion: 3, estado: 1},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#id_cuentas").empty();
                $("#id_cuentas").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        if(v['forpa_Id'] != 1){
                            $("#id_cuentas").append('<option value="' + v['forpa_Id'] + '">' + v['forpa_Descripcion'] + '</option>');
                        }
                    });
                }
                $("#id_cuentas").addClass('custom-select2');
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });

    }

    /**
     * getBodegas: Método para consultar cantidad de bodegas
    */
    async getBodegas() {
        $.ajax({
            url: '../business/controller/class.bodega.php',
            data: { funcion: 3, estado: 1},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('bodegas ', arr);
                $("#cantidBodegas").val(arr.datos.length)
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });

    }


//******************************************************************//

    /**
     * getModulosFinanciero: Método para consultar Informes EVENTOS ACTIVIDADES
     */
     getModulosFinanciero() {
        $("#mod_IdModuloFinanciero").append('<option value="2">Por Evento / Actividad</option>'); 
    }

    /**
     * getModulosFinanciero: Método para consultar Informes ACTIVIDADES
     */
    getActividad() {
        $("#mod_IdActividad").append('<option value="3">Por Actividad</option>'); 
        $("#mod_IdModuloEventoPyG").append('<option value="4">Informe P y G</option>'); 
    }
    
    

    /**
      * getInactivar: Activar y Inactivar campos que no son necesarios para unos reportes.
    */
       getInactivar() {

       if($("#mod_IdModuloFinanciero").val() == 2){   
            $('#month').removeAttr("required");
            $('#day').removeAttr("required");
            $('#year').removeAttr("required");
            $('#monthFinal').removeAttr("required");
            $('#dayFinal').removeAttr("required");
            $('#yearFinal').removeAttr("required");

            $("#month").hide();
            $("#day").hide();
            $("#year").hide();
            $("#monthFinal").hide();
            $("#dayFinal").hide();
            $("#yearFinal").hide();
            $('#fechaInicial').hide();
            $("#fechaFinal").hide();
       } else{
            $('#month').prop("required", true);
            $('#day').prop("required", true);
            $('#year').prop("required", true);
            $('#monthFinal').prop("required", true);
            $('#dayFinal').prop("required", true);
            $('#yearFinal').prop("required", true);

            $("#month").show();
            $("#day").show();
            $("#year").show();
            $("#monthFinal").show();
            $("#dayFinal").show();
            $("#yearFinal").show();
            $('#fechaInicial').show();
            $("#fechaFinal").show();
       }
      
    }

    /**
    * getInactivar: Activar y Inactivar campos que no son necesarios para unos reportes.
    */
        getActivarSede() {

        if(($("#mod_IdModulo").val() == 2) || ($("#mod_IdModulo").val() == 7 )){   
                $('#id_sedes').prop("required", true);
                $("#id_sedes").show();

        } else{     
                $('#id_sedes').removeAttr("required");
                $("#id_sedes").hide();
        }           
        }

        /**
    * getInactivar: Activar y Inactivar campos que no son necesarios para unos reportes.
    */
    getActivarCuentas() {

        if(($("#mod_IdModuloCuentas").val() == 8) ){   
                $('#id_cuentas').prop("required", true);
                $("#id_cuentas").show();

        } else{     
                $('#id_cuentas').removeAttr("required");
                $("#id_cuentas").hide();
        }           
    }

    /**
     * getCajaSede: Método para consulta las  cajas de cada sede activa.
     */
    getCajaSede() {

        $.ajax({
            url: '../business/controller/class.eventos.php',
            data: { funcion: 3, estado: 1},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#id_Eventos").empty();
                $("#id_Eventos").append('<option value="">Seleccione una opción</option>');
                $("#id_EventosPyg").empty();
                $("#id_EventosPyg").append('<option value="">Seleccione una opción</option>');
                $("#id_EventosAct").empty();
                $("#id_EventosAct").append('<option value="">Seleccione una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#id_Eventos").append('<option value="' + v['eve_Id'] + '">' + v['eve_Nombre'] + '</option>');
                        $("#id_EventosPyg").append('<option value="' + v['eve_Id'] + '">' + v['eve_Nombre'] + '</option>');
                        $("#id_EventosAct").append('<option value="' + v['eve_Id'] + '">' + v['eve_Nombre'] + '</option>');
                        
                    });
                }
                $("#id_Eventos").addClass('custom-select2');
                $("#id_EventosPyg").addClass('custom-select2');
                //$("#id_EventosAct").addClass('custom-select2');
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
    cargarCalculadora(tipo) {
       
        $("#activador").val(tipo);
        
        console.log('numerol: ', tipo);
        $("#modal-Calc").modal('show');
    }

    /**
     * getClientes: Método para ingresar precio al INPUT NAME=detkar_Costo
     */
    cargarValuePrecio(num) {
        
        var tipo = $("#activador").val();
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
        }
        
            
         
    }

    /**
     * getClientes: Método para cargar los precios del producto
     */
    cargarPrecios() {

        var detkar_IdProducto = $("#detkar_IdProducto").val();
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

                        $("#detkar_Costo").append('<option value="' + v['preVen_Id'] + '">' + finla_valor + '</option>');
                        
                        // Cargamos el precio en un IMPUT para editarlo manualmente
                        // $("#detkar_Costo").val(v['preVen_PrecioNeto']);

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
            data: { funcion: 6, seemca_Id: IdCaja},
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
                //$("#doc_IdCliente").append('<option value="">Seleccion una opción</option>');
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


     /**
     * actividadesEventos: Método para cargar las actividades de los Eventos.
     */
     actividadesEventos() {

        var idEvento = $("#id_EventosAct").val();

        $.ajax({
            url: '../business/controller/class.actividades.php',
            data: { funcion: 3, IdEvento:  idEvento},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#id_ActEvento").empty();
                $("#id_ActEvento").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {                        
                        $("#id_ActEvento").append('<option value="' + v['eva_Id'] + '">' + v['eva_Descripcion'] + '</option>');
                    });
                }
                $("#id_ActEvento").addClass('custom-select2');
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
nota.getBodegas();
nota.getNotas();
nota.getModulos();
nota.getPorModulos();
nota.getCuentas();
nota.getModulosCuentas();
nota.getModulosFinanciero();
nota.getActividad();
nota.getCajaSede();

$(document).ready(function() {
    $("#detkar_Cantidad").number(true, 2);
    $("#detkar_Costo").number(true, 0);
});





