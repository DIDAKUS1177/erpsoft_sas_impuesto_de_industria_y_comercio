// Declaración de variables
'use strict';

var idRol = sessionStorage.getItem('id_Rol');
console.log('idRol ', sessionStorage)
class CierreCaja {

    constructor() {}

    /**
     * crearCierreCaja: Método para abrir modal de creación
     */
    async crearCierreCaja() {
        var permiso = await _permisos.getPermisos(idRol, 1561);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {

            //$("#paca_IdCaja").val('');
            //$("#paca_IdVendedor").val('');
            $("#paca_IdCaja").val('');
            $("#paca_Descuadre").val('');
            $("#paca_ObservacionesCierre").val('');
            $("#formCierreCaja").attr('action', 'javascript:cierreCaja.postCierreCaja();');
            $("#modal_footerCierreCaja").empty();
            $("#modal_footerCierreCaja").append(
                '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                '<span class="ti-close"></span> Cancelar' +
                '</button>' +
                '<button type="submit" class="btn btn-success btn-pill">' +
                '<span class="ti-plus"></span> Crear' +
                '</button>'
            );

            $("#modal-CierreCaja").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de CierreCajas 
     * @param type $arrFilter: Listado de obejtos de tipo CierreCaja
     */
    draw_table_documents(arrFilter) {

        $("#tblCierreCaja").DataTable().destroy();
        $("#tbodyCierreCaja").empty();
        for (let bod of arrFilter) {
            if (bod.paca_Cierre == 1) {
                var titulo = "Cierre de Caja Cerrada";
            } else {
                var titulo = "Cierre de Caja Abierta";
            }

            var nom_empresa = sessionStorage.getItem('nom_tipoImpresora');
            if (nom_empresa == 1) {
                // FACTURA 80 mm
                var tipoImpresoraConsulta = '<a style="margin-right:5px" title="Descargar Cierre Caja" href="../extensiones/tcpdf/pdf/informeCierreTotal_80mm.php?codigo='+bod.cica_Id+'" target="_blank" class="btn btn-success btn-pill">' ;
            } else {
                // FACTURA 58 mm
                var tipoImpresoraConsulta = '<a style="margin-right:5px" title="Descargar Cierre Caja" href="../extensiones/tcpdf/pdf/informeCierreTotal_58mm.php?codigo='+bod.cica_Id+'" target="_blank" class="btn btn-success btn-pill">' ;
            }

            $('#tbodyCierreCaja').append(
                '<tr>' +
                '<td>' +
                bod.strNombreCaja +
                '</td>' +

                '<td>' +
                bod.strNombreVendedor +
                '</td>' +                

                '<td>' +
                bod.cica_Fecha +
                '</td>' +

                '<td>' +
                 '$ '+ Number(parseInt(bod.cica_Total).toFixed(0)).toLocaleString('es-CO') +
                '</td>' +

                '<td align="center">' +
                tipoImpresoraConsulta +
                '<i class="dw dw-edit2"></i>' +
                '</a>' +
                '<a style="margin-right:5px" title="Descargar Informe General" href="../extensiones/tcpdf/pdf/informeCierreTotalGeneral_80mm.php?codigo='+bod.cica_Id+'" target="_blank" class="btn btn-primary btn-pill">' +
                '<i class="dw dw-edit2"></i>' +
                '</a>' +
                '</td>' +
                '</tr>'
            );
        }
        cierreCaja.init_table();
    }

    /**
     * init_table: Método para asignar la
     * propiedad DataTable() a la tabla de roles
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
                [2, "desc"]
            ],
            "lengthMenu": [
                [10, 25, 50, -1],
                [10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'Cierre de Cajas Abiertas',
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
     * getcargarDocClientes: Método para Crear Documento 
     */
    cargarDoc() {

        var paca_IdCaja = $("#paca_IdCaja").val();
        var nom_empresa = sessionStorage.getItem('nom_tipoImpresora');
        if (nom_empresa == 1) {
            // FACTURA 80 mm
            var tipoImpresora = '<a href="../extensiones/tcpdf/pdf/informeCierre_80mm.php?codigo='+paca_IdCaja+'" target="_blank" class="btn btn-success btn-pill">';
        } else {
            // FACTURA 58 mm
            var tipoImpresora = '<a href="../extensiones/tcpdf/pdf/informeCierre_58mm.php?codigo='+paca_IdCaja+'" target="_blank" class="btn btn-success btn-pill">';
        }
      
        
        //console.log('precios: ', detkar_IdProducto);
        $.ajax({
            url: '../business/controller/class.cierreCaja.php',
            data: { funcion: 5, cica_IdCaja: paca_IdCaja},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                $("#paca_IdVendedor").empty();
                $("#paca_Vendedor").empty();
                
                if (arr.ok == 1)  {
                    $.each(arr.datos, function(k, v) {
                        if(null == (v['nomVendedor'])){
                            $("#paca_Vendedor").val('No hay Facturas por Cerrar');
                            $("#modal_footerCierreCaja").empty();
                            $("#baseVisual").val(0);
                        }else{
                            $("#paca_Vendedor").val(v['nomVendedor']);
                            $("#paca_IdVendedor").val(v['idVendedor']);
                            $("#paca_Total").val(v['total']);

                            if(v['totalAbonos'] == null){
                                $("#paca_TotalEfectivo").val(v['totalEfectivo']);
                                console.log('entro', arr);
                            }else{
                                $("#paca_TotalEfectivo").val(parseInt(v['totalEfectivo'])+parseInt(v['totalAbonos'])); 
                                console.log('no entro', arr);
                            }
                            
                            $("#paca_Base").val(v['baseCaja']);
                            $("#paca_Pagos").val(v['pagosCaja']);

                            if($('#paca_Base').val() == ''){
                                var baseT= 0;
                            }else{
                                var baseT= $("#paca_Base").val();
                            }

                            if($('#paca_Pagos').val() == ''){
                                var pagoT= 0;
                            }else{
                                var pagoT= $("#paca_Pagos").val();
                            }

                            if($('#paca_TotalEfectivo').val() == ''){
                                var efectivoT= 0;
                            }else{
                                var efectivoT= $("#paca_TotalEfectivo").val();
                            }

                            var totaln = (parseInt(baseT) + parseInt(efectivoT)) - parseInt(pagoT);
                            $("#baseVisual").val(totaln);
                        }
                    });
                }else if(arr.ok == 3){
                    $.each(arr.datos, function(k, v) {
                        $("#paca_Vendedor").val(v['nomVendedor']);
                        $("#paca_IdVendedor").val(v['idVendedor']);
                        $("#paca_Total").val(0);
                        $("#paca_TotalEfectivo").val(0);
                        $("#paca_Base").val(v['baseCaja']);
                        $("#paca_Pagos").val(v['pagosCaja']);
                        $("#paca_Descuadre").val(0);

                        if($('#paca_Base').val() == ''){
                            var baseT= 0;
                        }else{
                            var baseT= $("#paca_Base").val();
                        }

                        if($('#paca_Pagos').val() == ''){
                            var pagoT= 0;
                        }else{
                            var pagoT= $("#paca_Pagos").val();
                        }

                        var totaln= parseInt(baseT) - parseInt(pagoT);
                        $("#baseVisual").val(totaln);
                    });
                }else{
                    $("#paca_Vendedor").val('No hay Facturas nueva');
                    $("#modal_footerCierreCaja").empty();
                    $("#baseVisual").val(0);
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });

        $("#modal_footerCierreCaja").empty();
        $("#modal_footerCierreCaja").append(
            tipoImpresora +
            '<span class="ti-plus"></span> Descargar Informe' +
            '</a>'+
            '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
            '<span class="ti-close"></span> Cancelar' +
            '</button>' +
            '<button type="submit" class="btn btn-success btn-pill">' +
            '<span class="ti-plus"></span> Crear' +
            '</button>'
        );

    }


    /**
     * getCierreCaja: Método para consultar las
     * CierreCajas
     */
    getCierreCaja() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */

         var idRol = sessionStorage.getItem('id_Rol');
         var idUsuario = sessionStorage.getItem('id_Usuario');

        $.ajax({
            url: '../business/controller/class.cierreCaja.php',
            data: { funcion: 3, paca_Cierre: 0, idRol: idRol, cica_IdVendedor: idUsuario},
            dataType: "json",
            type: "POST",
            success: function(arr) {

                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    cierreCaja.draw_table_documents(arr.datos);
                } else {
                    cierreCaja.init_table();
                    /* swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo consultar las CierreCajas',
                    }); */
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


    /**
     * getCierreCajaById: Método para consultar la 
     * información de una CierreCaja
     * @param type $id: llave primaria de la tabla CierreCaja
     */
    async getCierreCajaById(id) {
        var permiso = await _permisos.getPermisos(idRol, 1562);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.cierreCaja.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {

                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#paca_IdCaja").val(datos.paca_IdCaja);
                            $("#paca_IdVendedor").val(datos.paca_IdVendedor);
                            $("#paca_Valor").val(datos.paca_Valor);
                            $("#paca_Observaciones").val(datos.paca_Observaciones);
                        }

                        $("#formCierreCaja").attr('action', 'javascript:cierreCaja.putCierreCaja(' + id + ');');
                        $("#modal_footerCierreCaja").empty();
                        $("#modal_footerCierreCaja").append(
                            '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                            '<span class="ti-close"></span> Cancelar' +
                            '</button>' +
                            '<button type="submit" class="btn btn-success btn-pill">' +
                            '<span class="ti-reload"></span> Actualizar' +
                            '</button>'
                        );

                        $("#modal-CierreCaja").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información de la CierreCaja',
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

    /**
     * putCierreCaja: Método para actualizar la 
     * información de una CierreCaja
     * @param type $id: llave primaria de la tabla CierreCaja
     */
    putCierreCaja(id) {

        var caja = $("#paca_IdCaja").val();
        var vendedor = $("#paca_IdVendedor").val();
        var valor = $("#paca_Valor").val();
        var observaciones = $("#paca_Observaciones").val();

        $.ajax({
            url: '../business/controller/class.cierreCaja.php',
            data: { funcion: 2, id: id , paca_IdCaja: caja,  paca_IdVendedor: vendedor, 
                paca_Valor: valor, paca_Observaciones: observaciones},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                /*  $('#loading').hide();
                 $('#wrapper').removeClass('body-load'); */
                if (arr.ok == 1) {

                    $("#modal-CierreCaja").modal('hide');
                    cierreCaja.getCierreCaja();
                    swal({
                        type: 'success',
                        title: 'CierreCaja actualizada',
                        text: 'CierreCaja actualizada exitosamente',
                    });

                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'CierreCaja Abierta',
                        text: 'Ya existe una Cierre de Caja Abierta',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la CierreCaja',
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


    
    /**
     * getImpuesto: Método para consultar los Vendedores
     */
    getVendedores() {

        $.ajax({
            url: '../business/controller/class.usuarios.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                $("#paca_IdVendedor").empty();
                $("#paca_IdVendedor").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#paca_IdVendedor").append('<option value="' + v['usu_Id'] + '">' + v['usu_Nombre'] + '</option>');
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
     * getImpuesto: Método para consultar las Cajas
     */
    getCajas() {

        var idRol = sessionStorage.getItem('id_Rol');
        var idCaja = sessionStorage.getItem('id_caja');

        $.ajax({
            url: '../business/controller/class.sedesEmpresaCajas.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                console.log('caja', idCaja);
                
                if (arr.ok == 1) {
                    if(idRol == 1){
                        $("#paca_IdCaja").empty();
                        $("#paca_IdCaja").append('<option value="">Seleccion una opción</option>');
                        $.each(arr.datos, function(k, v) {
                            $("#paca_IdCaja").append('<option value="' + v['seemca_Id'] + '">' + v['seemca_Nombre'] + '</option>');
                        });
                    }else{
                        $("#paca_IdCaja").empty();     
                        $("#paca_IdCaja").append('<option value="">Seleccion una opción</option>');               
                        $.each(arr.datos, function(k, v) {
                            if(v['seemca_Id'] == idCaja ){
                                $("#paca_IdCaja").append('<option value="' + v['seemca_Id'] + '">' + v['seemca_Nombre'] + '</option>');
                            }
                        });
                    }
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });

    }


    /**
     * postCierreCaja: Método para crear CierreCajas
     */
    postCierreCaja() {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load'); */
        var idcaja = $("#paca_IdCaja").val();
        var idvendedor = $("#paca_IdVendedor").val();
        var descuadre = $("#paca_Descuadre").val();
        var paca_ObservacionesCierre = $("#paca_ObservacionesCierre").val();
        var total = $("#paca_Total").val();
        var totalEfectivo = $("#paca_TotalEfectivo").val();
        var base = $("#paca_Base").val();
        var pagos = $("#paca_Pagos").val();

        if ($('#crearBase').prop("checked")){
            var crearBase=1;
        }else{
            var crearBase=0;
        }
        console.log('crearBase', crearBase);
        
        $.ajax({
            url: '../business/controller/class.cierreCaja.php',
            data: { funcion: 1, cica_IdCaja: idcaja, cica_IdVendedor :idvendedor, paca_ObservacionesCierre: paca_ObservacionesCierre,
                        cica_Descuadre: descuadre, cica_Total: total, cica_TotalEfectivo: totalEfectivo, cica_Base: base, cica_Pagos: pagos, cica_CrearBase: crearBase},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                /*  $('#loading').hide();
                 $('#wrapper').removeClass('body-load'); */
                if (arr.ok == 1) {

                    $("#modal-CierreCaja").modal('hide');
                    swal({
                        type: 'success',
                        title: 'CierreCaja creada',
                        text: 'Cierre de Caja creada exitosamente',
                    });
                    cierreCaja.getCierreCaja();
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Caja Abierta',
                        text: 'Ya existe una Cierre de Caja Abierta',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la  CierreCaja',
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

    /**
     * putEstados: Método para cambiar el estados 
     * de las CierreCajas
     * @param type $id_CierreCaja: llave primaria de la tabla CierreCaja
     * @param type $estado: estado actual de la CierreCaja
     */
    async putEstados(id_CierreCaja, estado) {
        //var permiso = await _permisos.getPermisos(idRol, 313);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar la CierreCaja?";
                var subtitle = "Una vez inactivado la CierreCaja, no podrá asignarla a un producto";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la CierreCaja?";
                var subtitle = "Una vez activado la CierreCaja, podrá ser asignarla a un producto";
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
                        url: '../business/controller/class.CierreCaja.php',
                        data: { funcion: 4, id: id_CierreCaja, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                cierreCaja.getCierreCaja();
                                swal({
                                    type: 'success',
                                    title: 'CierreCaja actualizada',
                                    text: 'CierreCaja actualizada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar la CierreCaja',
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

    /**
     * CierreCajaActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    CierreCajaActivo() {
        $(".Menuactivo").removeClass('expand');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DAdministracion").addClass('expand');
        $("#DAdministracion").addClass('active');
        $("#SubAdminsitracion").addClass('show');
        $("#SubMenuCierreCaja").addClass('active');
    }

}
const cierreCaja = new CierreCaja();

cierreCaja.getCajas();
cierreCaja.getCierreCaja();
cierreCaja.CierreCajaActivo();

cierreCaja.getVendedores();

