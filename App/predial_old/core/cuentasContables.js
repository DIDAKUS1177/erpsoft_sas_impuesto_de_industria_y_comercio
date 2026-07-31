/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
var num = 1;
class CuentasContables {

    constructor() {}

    /**
     * crearFormasPago: Método para abrir modal de creación
     */
    async crearMovimientoContable() {
        var permiso = await _permisos.getPermisos(idRol, 2388);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            document.getElementById('nomTipo').style.display = "none";   
            document.getElementById('cuco_TipoSalida').style.display = "none"; 
            document.getElementById('nomSubTipo').style.display = "none";   
            document.getElementById('cuco_SubTipoSalida').style.display = "none"; 
            $("#formCrearCuentasContables").trigger("reset");
            $("#btnCrearCuentasContables").empty();
            $("#btnCrearCuentasContables").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearCuentasContables").attr('action', 'javascript:cuentasContables.postCuentasContables()');
            $("#modal-CuentasContables").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de FormasPagos 
     * @param type $arrFilter: Listado de obejtos de tipo FormasPagos
     */
async draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        $("#cuentasContablesRegistrados").DataTable().destroy();
        $("#bodyCuentasContablesRegistrados").empty();

        var totalValorProveedor = 0;

        for (let usu of arrFilter) {
            if (usu.cuco_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Movimiento Contable";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Movimiento Contable";
            }

            // Valor neto en cuentas.
            var valorReal = parseInt(usu.entradas)-parseInt(usu.salidas) ;
           
            //var num = cuentasContables.validarmoviContable(valorReal);
            var permiso = await _permisos.getPermisos(idRol, 2394);

            if (permiso.ok != 1) {
                var mos = '**********';
            } else {
                var mos = Number(parseInt(valorReal).toFixed(0)).toLocaleString('es-CO');
            }
          
            if (usu.forpa_Id != 1) {

                $('#bodyCuentasContablesRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    usu.forpa_Descripcion +
                    '</td>' +
                    //'<td>' +
                    //tipoEntrada +
                    //'</td>' +
                    '<td>' +
                    '$ '+ mos +
                    '</td>' +
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-primary" data-toggle="tooltip" title="Ver detalles del Movimiento" style="margin-right:5px" onclick="javascript:cuentasContables.verDetalle(' + usu.forpa_Id +','+"'"+usu.forpa_Descripcion+"'"+')" >' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    //'<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  style="margin-right:5px" onclick="javascript:cuentasContables.cambiarEstado(' + usu.cuco_Id + ',' + usu.cuco_Estado + ')">' +
                    //'<i class="' + icono + '"></i>' +
                    //'</button>' +

                    '</td>' +

                    '</tr>'
                );

                if (permiso.ok != 1) {
                    var totalValorProveedor = '**********';
                } else {
                    var totalValorProveedor = totalValorProveedor + parseInt(valorReal);
                }
                
            }
        }
        if (permiso.ok != 1) {
            $("#valor_Total").val('Valor Total: '+'$ '+ totalValorProveedor);
        } else {
            $("#valor_Total").val('Valor Total: '+'$ '+ Number(parseInt(totalValorProveedor).toFixed(0)).toLocaleString('es-CO') );
        }
        
        cuentasContables.init_table();
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
                'emptyTable': 'Cuentas Contables registrados',
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
                [3, "desc"]
            ],
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
     * getFormasPagos: Método para consultar 
     * FormasPagos
     */
    getcuentasContables() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $.ajax({
            url: '../business/controller/class.cuentasContables.php',
            data: { funcion: 5 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#bodyCuentasContablesRegistrados").empty();
                    cuentasContables.draw_table_documents(arr.datos);
                } else {
                    $("#cuentasContablesRegistrados").DataTable().destroy();
                    cuentasContables.init_table();
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }


    /**
     * verDetalle: Método para consultar el 
     * detalle de los movimientos de la cuenta contabla enviada por parametro
     * @param type $idCuentaContable: Listado de Cuenta Contable
     */
    async verDetalle(idCuentaContable, nombreCuenta) {
            $('#loading').show();
            $('#wrapper').addClass('body-load');  

            var permiso = await _permisos.getPermisos(idRol, 2389);

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
                    url: '../business/controller/class.cuentasContables.php',
                    data: { funcion: 3, idCuentaContable: idCuentaContable },
                    dataType: "json",
                    type: "POST",
                    success: function(arr) {
                        $('#loading').hide();
                        $('#wrapper').removeClass('body-load');
    
                        console.log('det ', arr)
                        $("#ltsDetallesNota").DataTable().destroy();
                        $("#bodyDetallesNota").empty();
        
                        if (arr.ok == 1) {
        
                            for (let d of arr.datos) {
    
                                if (d.cuco_IdTipoMovimiento == 1) {
                                    var tipoMovi = "Entrada";
                                } else {
                                    var tipoMovi = "Salida";
                                }
    
                                $("#nombreCuenta").val(nombreCuenta);
                                
                                $("#bodyDetallesNota").append(
                                    '<tr>' +
                                    '<td  align="center">' + tipoMovi +'</td>' +
                                    '<td>' + d.cuco_Observacion + '</td>' +
                                    '<td  align="center">$ ' + Number(parseInt(d.cuco_Valor).toFixed(0)).toLocaleString('es-CO') + '</td>' +
                                    '<td  align="center">' + d.cuco_FechaCreacion + '</td>' +
                                    '</tr>'
                                );
                            }
    
                            cuentasContables.init_table_detalle();
    
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

        }
    


    /**
     * postFormasPago: Método para crear 
     * FormasPagos
     */
    postCuentasContables() {
        /* $('#loading').show();
        $('#wrapper').addClass('body-load'); */
        var id_caja = sessionStorage.getItem('id_caja');

        var tipoMovimiento = $("#cuco_TipoMovimiento").val();
        var numeroCuenta = $("#cuco_IdNumeroCuenta").val();
        
        /*if(tipoMovimiento == 1){
            var cuco_TipoSalida = 0;
            var tipoSalida = '';
        }else{
        */
            var cuco_TipoSalida = $("#cuco_TipoSalida").val();
            var tipoSalida = $("#cuco_TipoSalida").find('option:selected').text()+': ';
            var cuco_SubTipoSalida = $("#cuco_SubTipoSalida").val();
            var subtipoSalida = $("#cuco_SubTipoSalida").find('option:selected').text()+': ';
        //}
        
        var valor = $("#cuco_Valor").val();
        var idDocumento = 0;
        
        var observacion = tipoSalida+' '+$("#cuco_Observaciones").val();

        $.ajax({
            url: '../business/controller/class.cuentasContables.php',
            data: { funcion: 1, idTipoMovimiento: tipoMovimiento, idCuentaContable: numeroCuenta, IdTipoSalida: cuco_TipoSalida, subtipoSalida: cuco_SubTipoSalida,
                    idDocumento:  idDocumento, valor: valor, observacion: observacion, doc_IdSerieCaja: id_caja},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('Movimiento cuenta', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearCuentasContables").trigger("reset");
                    $("#modal-CuentasContables").modal('hide');
                    cuentasContables.getcuentasContables();
                    swal({
                        type: 'success',
                        title: 'Movimiento Cuenta Contable Creada',
                        text: 'Movimiento creado exitosamente',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear el Movimiento Contable',
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
     * cambiarEstado: Método para cambiar el estado
     * de los FormasPagos
     * @param type $id_FormasPago:  llave primaria de la tabla FormasPagos
     * @param type $estado: estado actual del FormasPago
     */
    async cambiarEstado(id_CuentaContable, estado) {
        //var permiso = await _permisos.getPermisos(idRol, 1875);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar el Movimiento Contable?";
                var subtitle = "Una vez inactivado el movimiento quedara bloqueado totalmente";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la formasPago?";
                var subtitle = "Una vez activado el movimeinto quedara desbloqueado totalmente";
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
                        url: '../business/controller/class.cuentasContables.php',
                        data: { funcion: 4, id: id_CuentaContable, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                cuentasContables.getcuentasContables();
                                swal({
                                    type: 'success',
                                    title: 'Movimiento Contable actualizado',
                                    text: 'Movimiento actualizado exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el Movimiento',
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

  
    /**
     * formasPago: Método para consultar formas de pago (Cuentas Contables)
     */
        formasPago() {

            $.ajax({
                url: '../business/controller/class.factura.php',
                data: { funcion: 7 },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    console.log('arr ', arr);
                    $("#cuco_IdNumeroCuenta").empty();
                    $("#cuco_IdNumeroCuenta").append('<option value="">Seleccion una opción</option>');
                    if (arr.ok == 1) {
                        $.each(arr.datos, function(k, v) {   
                            if( v['forpa_Id'] != 1){                     
                                $("#cuco_IdNumeroCuenta").append('<option value="' + v['forpa_Id'] + '">' + v['forpa_Descripcion'] + '</option>');
                            }
                        });
                    }
                    //$("#cuco_IdNumeroCuenta").addClass('custom-select2');
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                    //location.href = "../../login.html";
                }
            });
        }

    /**
    * activarCampos: Metodo para activar campo de ingresar
    */
    activarCampos(obj){
        console.log(obj);
        
        cuentasContables.tiposPagoCaja(obj) ;

        if(obj == 2){
            document.getElementById('nomTipo').style.display = "";
            document.getElementById('cuco_TipoSalida').style.display = "";           
            document.getElementById('nomSubTipo').style.display = "";
            document.getElementById('cuco_SubTipoSalida').style.display = "";           
        }else if(obj == 1){
            document.getElementById('nomTipo').style.display = "";
            document.getElementById('cuco_TipoSalida').style.display = "";           
            document.getElementById('nomSubTipo').style.display = "";
            document.getElementById('cuco_SubTipoSalida').style.display = "";  
        }else{
            document.getElementById('nomTipo').style.display = "none";   
            document.getElementById('cuco_TipoSalida').style.display = "none";   
            document.getElementById('nomSubTipo').style.display = "none";   
            document.getElementById('cuco_SubTipoSalida').style.display = "none";   
        }

    }

    
    /**
     * tiposPagoCaja: Método para consultar las tipos de pagos
     */
     tiposPagoCaja(obj) {

        $.ajax({
            url: '../business/controller/class.tiposPagos.php',
            data: { funcion: 3 , idTipo: obj},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                if (arr.ok == 1) {
                    $("#cuco_TipoSalida").empty();
                    //$("#cuco_TipoSalida").append('<option value="">Seleccion una opción</option>');
                    $.each(arr.datos, function(k, v) {
                        $("#cuco_TipoSalida").append('<option value="' + v['tipa_Id'] + '">' + v['tipa_Nombre'] + '</option>');
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });


        $.ajax({
            url: '../business/controller/class.tiposPagos.php',
            data: { funcion: 5 , idTipo: obj},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                if (arr.ok == 1) {
                    $("#cuco_SubTipoSalida").empty();
                    //$("#cuco_TipoSalida").append('<option value="">Seleccion una opción</option>');
                    $.each(arr.datos, function(k, v) {
                        $("#cuco_SubTipoSalida").append('<option value="' + v['subtipa_Id'] + '">' + v['subtipa_Nombre'] + '</option>');
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
     * cuentasContablesActivo: Método para activar el menú y facilitar
     * la navegación al FormasPago permitendole saber en
     * que lugar esta
     */
    cuentasContablesActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');
        
        $("#DTesoreria").addClass('expand active');
        $("#DTesoreria").addClass('active');
        $("#SFTesoreria").addClass('show');
        $("#SubMenuCuentasContables").addClass('active');

    }

}

const cuentasContables = new CuentasContables();

cuentasContables.getcuentasContables();
cuentasContables.cuentasContablesActivo();
cuentasContables.formasPago();
//cuentasContables.tiposPagoCaja();