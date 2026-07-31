// Declaración de variables
'use strict';

var idRol = sessionStorage.getItem('id_Rol');
console.log('idRol ', sessionStorage)
class PagosCaja {

    constructor() {}

    /**
     * crearPagosCaja: Método para abrir modal de creación
     */
    async crearPagosCaja() {
        var permiso = await _permisos.getPermisos(idRol, 1458);

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
            $("#paca_Valor").val('');
            $("#paca_Observaciones").val('');
            document.getElementById('paca_IdTipoPago').disabled = false;
            document.getElementById('paca_IdSubTipoPago').disabled = false;
            document.getElementById('paca_Valor').disabled = false;
            
            $("#formPagosCaja").attr('action', 'javascript:bod.postPagosCaja();');
            $("#modal_footerPagosCaja").empty();
            $("#modal_footerPagosCaja").append(
                '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                '<span class="ti-close"></span> Cancelar' +
                '</button>' +
                '<button type="submit" class="btn btn-success btn-pill">' +
                '<span class="ti-plus"></span> Crear' +
                '</button>'
            );

            $("#modal-PagosCaja").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de PagosCajas 
     * @param type $arrFilter: Listado de obejtos de tipo PagosCaja
     */
    draw_table_documents(arrFilter) {

        $("#tblPagosCaja").DataTable().destroy();
        $("#tbodyPagosCaja").empty();
        for (let bod of arrFilter) {
            if (bod.paca_Cierre == 1) {
                var titulo = "Pagos de Caja Cerrada";
                var activo = "disabled";
            } else {
                var titulo = "Pagos de Caja Abierta";
                var activo = "";
            }

            if (bod.strNombreTipoPago == null) {
                var tituloTipo = "No Tiene Tipo Asociado";
            } else {
                var tituloTipo = bod.strNombreTipoPago;
            }

            $('#tbodyPagosCaja').append(
                '<tr>' +
                '<td>' +
                bod.strNombreCaja +
                '</td>' +

                '<td>' +
                bod.paca_FechaCreacion +
                '</td>' +

                '<td>' +
                tituloTipo +
                '</td>' +

                '<td>' +
                '$ ' +Number(parseInt(bod.paca_Valor).toFixed(0)).toLocaleString('es-CO') +
                '</td>' +                

                '<td>' +
                bod.paca_Observaciones +
                '</td>' +

                '<td>' +
                titulo +
                '</td>' +

                '<td align="center">' +
                '<button type="button" class="mb-1 btn btn-social-icon btn-warning " title="Editar Pago" style="margin-right:5px" onclick="javascript:bod.getPagosCajaById(' + bod.paca_Id + ')" '+activo+'>' +
                '<i class="dw dw-edit2"></i>' +
                '</button>' +

                '</td>' +
                '</tr>'
            );
        }
        bod.init_table();
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
            "lengthMenu": [
                [10, 25, 50, -1],
                [10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'Pagos de Cajas Abiertas',
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
     * getPagosCaja: Método para consultar las
     * PagosCajas
     */
    getPagosCaja(estado_Ver) {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */

         var idRol = sessionStorage.getItem('id_Rol');
         var idUsuario = sessionStorage.getItem('id_Usuario');

        $.ajax({
            url: '../business/controller/class.pagosCaja.php',
            data: { funcion: 3, paca_Cierre: estado_Ver, idRol: idRol, paca_IdVendedor: idUsuario},
            //data: { funcion: 3, idRol: idRol, paca_IdVendedor: idUsuario},
            dataType: "json",
            type: "POST",
            success: function(arr) {

                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    bod.draw_table_documents(arr.datos);
                } else {
                    bod.init_table();
                    /* swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo consultar las PagosCajas',
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
     * getPagosCajaById: Método para consultar la 
     * información de una PagosCaja
     * @param type $id: llave primaria de la tabla PagosCaja
     */
    async getPagosCajaById(id) {
        var permiso = await _permisos.getPermisos(idRol, 1459);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.pagosCaja.php',
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
                            $("#paca_IdTipoPago").val(datos.paca_IdTipoPago);
                            $("#paca_IdSubTipoPago").val(datos.paca_IdSubTipoPago);
                            $("#paca_Valor").val(datos.paca_Valor);
                            $("#paca_Observaciones").val(datos.paca_Observaciones);
                        } 

                        if($("#paca_IdTipoPago").val() == 4){
                            document.getElementById('paca_IdTipoPago').disabled = true;
                            document.getElementById('paca_IdSubTipoPago').disabled = true;
                            document.getElementById('paca_Valor').disabled = true;
                            document.getElementById('select_Cuentas').disabled = true;
                            document.getElementById('select_Cuentas').style.display = "";
                            document.getElementById('labelCuentaT').style.display = ""; 
                        }else{
                            document.getElementById('paca_IdTipoPago').disabled = false;
                            document.getElementById('paca_IdSubTipoPago').disabled = false;
                            document.getElementById('paca_Valor').disabled = false;
                            document.getElementById('select_Cuentas').disabled = false;
                            document.getElementById('select_Cuentas').style.display = "none";
                            document.getElementById('labelCuentaT').style.display = "none"; 
                        }              
                        
                        $("#formPagosCaja").attr('action', 'javascript:bod.putPagosCaja(' + id + ');');
                        $("#modal_footerPagosCaja").empty();
                        $("#modal_footerPagosCaja").append(
                            '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                            '<span class="ti-close"></span> Cancelar' +
                            '</button>' +
                            '<button type="submit" class="btn btn-success btn-pill">' +
                            '<span class="ti-reload"></span> Actualizar' +
                            '</button>'
                        );

                        $("#modal-PagosCaja").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información de la PagosCaja',
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
     * putPagosCaja: Método para actualizar la 
     * información de una PagosCaja
     * @param type $id: llave primaria de la tabla PagosCaja
     */
    putPagosCaja(id) {

        var caja = $("#paca_IdCaja").val();
        var vendedor = $("#paca_IdVendedor").val();
        var idTipoPago = $("#paca_IdTipoPago").val();
        var idSubTipoPago = $("#paca_IdSubTipoPago").val();
        var valor = $("#paca_Valor").val();
        var observaciones = $("#paca_Observaciones").val();
        var cuentaTransferir = $("#select_Cuentas").val();

        $.ajax({
            url: '../business/controller/class.pagosCaja.php',
            data: { funcion: 2, id: id , paca_IdCaja: caja,  paca_IdVendedor: vendedor, 
                paca_Valor: valor, paca_IdTipoPago: idTipoPago,paca_IdSubTipoPago: idSubTipoPago, 
                paca_Observaciones: observaciones, cuentaTransferir: cuentaTransferir},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                /*  $('#loading').hide();
                 $('#wrapper').removeClass('body-load'); */
                if (arr.ok == 1) {

                    $("#modal-PagosCaja").modal('hide');
                    bod.getPagosCaja(0);
                    swal({
                        type: 'success',
                        title: 'PagosCaja actualizada',
                        text: 'PagosCaja actualizada exitosamente',
                    });

                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'PagosCaja Abierta',
                        text: 'Ya existe una Pagos de Caja Abierta',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la PagosCaja',
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

        var idRol = sessionStorage.getItem('id_Rol');
        var idUsuario = sessionStorage.getItem('id_Usuario');

        $.ajax({
            url: '../business/controller/class.usuarios.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                if (arr.ok == 1) {
                    if(idRol == 1){
                        $("#paca_IdVendedor").empty();
                        $("#paca_IdVendedor").append('<option value="">Seleccion una opción</option>');
                        $.each(arr.datos, function(k, v) {
                            if(v['usu_Id'] > 1){
                                $("#paca_IdVendedor").append('<option value="' + v['usu_Id'] + '">' + v['usu_Nombre'] + '</option>');
                            }
                        });
                    }else{
                        $("#paca_IdVendedor").empty();                 
                        $.each(arr.datos, function(k, v) {
                            if(v['usu_Id'] == idUsuario ){
                                $("#paca_IdVendedor").append('<option value="' + v['usu_Id'] + '">' + v['usu_Nombre'] + '</option>');
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
                if (arr.ok == 1) {
                    if(idRol == 1){
                        $("#paca_IdCaja").empty();
                        $("#paca_IdCaja").append('<option value="">Seleccion una opción</option>');
                        $.each(arr.datos, function(k, v) {
                            $("#paca_IdCaja").append('<option value="' + v['seemca_Id'] + '">' + v['seemca_Nombre'] + '</option>');
                        });
                    }else{
                        $("#paca_IdCaja").empty();     
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
     * tiposPagoCaja: Método para consultar las tipos de pagos
     */
    tiposPagoCaja() {

        $.ajax({
            url: '../business/controller/class.tiposPagos.php',
            data: { funcion: 3, idTipo: 2 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                if (arr.ok == 1) {
                    $("#paca_IdTipoPago").empty();
                    $("#paca_IdTipoPago").append('<option value="">Seleccion una opción</option>');
                    $.each(arr.datos, function(k, v) {
                        $("#paca_IdTipoPago").append('<option value="' + v['tipa_Id'] + '">' + v['tipa_Nombre'] + '</option>');
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
     * subtiposPagoCaja: Método para consultar los Sub tipos de pagos
     */
    subtiposPagoCaja() {

        $.ajax({
            url: '../business/controller/class.tiposPagos.php',
            data: { funcion: 5, idTipo: 2 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                if (arr.ok == 1) {
                    $("#paca_IdSubTipoPago").empty();
                    $("#paca_IdSubTipoPago").append('<option value="">Seleccion una opción</option>');
                    $.each(arr.datos, function(k, v) {
                        $("#paca_IdSubTipoPago").append('<option value="' + v['subtipa_Id'] + '">' + v['subtipa_Nombre'] + '</option>');
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
     * postPagosCaja: Método para crear PagosCajas
     */
    postPagosCaja() {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load'); */

        var caja = $("#paca_IdCaja").val();
        var vendedor = $("#paca_IdVendedor").val();
        var idTipoPago = $("#paca_IdTipoPago").val();
        var idSubTipoPago = $("#paca_IdSubTipoPago").val();
        var valor = $("#paca_Valor").val();
        var observaciones = $("#paca_Observaciones").val();
        var cuentaTransferir = $("#select_Cuentas").val();

        $.ajax({
            url: '../business/controller/class.pagosCaja.php',
            data: { funcion: 1, paca_IdCaja: caja, paca_IdVendedor: vendedor, 
                    paca_Valor: valor, paca_IdTipoPago: idTipoPago, paca_IdSubTipoPago: idSubTipoPago, 
                    paca_Observaciones: observaciones, cuentaTransferir: cuentaTransferir},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                /*  $('#loading').hide();
                 $('#wrapper').removeClass('body-load'); */
                if (arr.ok == 1) {

                    $("#modal-PagosCaja").modal('hide');
                    swal({
                        type: 'success',
                        title: 'PagosCaja creada',
                        text: 'Pagos de Caja creada exitosamente',
                    });
                    bod.getPagosCaja(0);
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Caja Abierta',
                        text: 'Ya existe una Pagos de Caja Abierta',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la  PagosCaja',
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
     * de las PagosCajas
     * @param type $id_PagosCaja: llave primaria de la tabla PagosCaja
     * @param type $estado: estado actual de la PagosCaja
     */
    async putEstados(id_PagosCaja, estado) {
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
                var title = "¿Está seguro de inactivar la PagosCaja?";
                var subtitle = "Una vez inactivado la PagosCaja, no podrá asignarla a un producto";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la PagosCaja?";
                var subtitle = "Una vez activado la PagosCaja, podrá ser asignarla a un producto";
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
                        url: '../business/controller/class.pagosCaja.php',
                        data: { funcion: 4, id: id_PagosCaja, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                bod.getPagosCaja(0);
                                swal({
                                    type: 'success',
                                    title: 'PagosCaja actualizada',
                                    text: 'PagosCaja actualizada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar la PagosCaja',
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

    */
        activarSupervisor(){   

            var caja = $("#paca_IdTipoPago").val();

            if (caja == 4){
                document.getElementById('select_Cuentas').style.display = "";
                document.getElementById('labelCuentaT').style.display = ""; 
                
            }else{   
                document.getElementById('select_Cuentas').style.display = "none";
                document.getElementById('labelCuentaT').style.display = "none"; 
            }   

        }
        

        getCuentas() {
            /* $('#loading').show();
                $('#wrapper').addClass('body-load');  */
            $("#select_Cuentas").empty();
            //$("#select_Cuentas").append('<option value="">Seleccione un Cuenta</option>');
            $.ajax({
                url: '../business/controller/class.formasPago.php',
                data: { funcion: 3, estado: 1 },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    console.log('arr', arr)
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
    
                        $.each(arr.datos, function(k, v) {
                            if(v['forpa_Id'] != 1){
                                $("#select_Cuentas").append('<option value="' + v['forpa_Id'] + '">' + v['forpa_Descripcion'] + '</option>');    
                            }
                            
                        });
    
                    } else {  }
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                    //location.href = "../../login.html";
                }
            });
        }


    /**
     * PagosCajaActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    PagosCajaActivo() {
        $(".Menuactivo").removeClass('expand');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DAdministracion").addClass('expand');
        $("#DAdministracion").addClass('active');
        $("#SubAdminsitracion").addClass('show');
        $("#SubMenuPagosCaja").addClass('active');
    }

}
const bod = new PagosCaja();

bod.getPagosCaja(0);
bod.PagosCajaActivo();
bod.getVendedores();
bod.tiposPagoCaja();
bod.subtiposPagoCaja();
bod.getCajas();
bod.getCuentas();
