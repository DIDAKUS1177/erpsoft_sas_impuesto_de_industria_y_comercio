/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;


class Producto {

    constructor() {}

    /**
     * crearProducto: Método para abrir modal de creación
     */
    async crearProducto() {
        var permiso = await _permisos.getPermisos(idRol, 415);
        $('#div_stock').show();
        $('#div_Bodega').show();
        
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
            $("#formCrearProducto").trigger("reset");
            prod.getCodigo();
            $("#formCrearProducto").attr('action', 'javascript:prod.postProducto()');
            $("#modal_footer").empty();
            $("#modal_footer").append(
                '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>' +
                ' Cancelar' +
                '</button>' +
                '<button type="submit" class="btn btn-success btn-pill"><span class="ti-plus"></span>' +
                ' Crear' +
                '</button>'
            );
           // $("#pro_porIva").number(true, 2);
            //$("#pro_porIca").number(true, 2);
            //$("#pro_porCom").number(true, 2);
            $("#modal-Producto").modal('show');
        }
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
            "lengthMenu": [
                [10, 25, 50, -1],
                [10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'Productos registrados',
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
     * de productos 
     * @param type $arrFilter: Listado de obejtos de tipo bodega
     */
    draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        $("#productosRegistrados").DataTable().destroy();
        $("#bodyProductosRegistrados").empty();
        for (let pro of arrFilter) {
            if (pro.pro_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Producto";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Producto";
            }

            if (pro.pro_Tipo == 1) {
                var NomTipo = "Producto";
            } else {
                var NomTipo = "Servicio";
            }

            if (pro.strTotalStock == null) {
                var TotalStock = "0";
            } else {
                var TotalStock = pro.strTotalStock;
            }
            

            $('#bodyProductosRegistrados').append(
                '<tr>' +
                '<td>' +
                pro.pro_Codigo +
                '</td>' +
                '<td>' +
                pro.pro_Nombre +
                '</td>' +
                '<td>' +
                pro.pro_CodBarras +
                '</td>' +
                '<td>' +
                pro.strPrecioVenta +
                '</td>' +
                '<td>' +
                pro.strPrecioCompra +
                '</td>' +
                '<td style="text-align: center;">' +
                TotalStock +
                '</td>' +
                '<td style="text-align: right;">' +
                pro.strNombreCategoria +
                '</td>' +
                
                '<td align="center">' +
                '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar producto" style="margin-right:5px" onclick="javascript:prod.getProductoById(' + pro.pro_Id + ')">' +
                '<i class="dw dw-edit2"></i>' +
                '</button>' +

                '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  style="margin-right:5px" onclick="javascript:prod.cambiarEstado(' + pro.pro_Id + ',' + pro.pro_Estado + ')">' +
                '<i class="' + icono + '"></i>' +
                '</button>' +

                '<button type="button" class="btn btn-social-icon btn-secondary " data-toggle="tooltip" title="Agregar Precios" style="margin-right:5px" onclick="javascript:prod.getPreciosProductoById(' + pro.pro_Id + ')">' +
                '<i class="icon-copy dw dw-money-1"></i>' +
                '</button>' +

                '<button type="button" class="btn btn-social-icon btn-info " data-toggle="tooltip" title="Ver Stocks" style="margin-right:5px" onclick="javascript:prod.getVerStocks(' + pro.pro_Id + ')">' +
                '<i class="icon-copy dw dw-diagram"></i>' +
                '</button>' +

                '</td>' +

                '</tr>'
            );

        }
        prod.init_table();
    }

 
    /**
     * getProductos: Método para consultar los 
     * productos registrados
     */
    getProductos() {
        $('#loading').show();
        $('#wrapper').addClass('body-load');

        $.ajax({
            url: '../business/controller/class.producto.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#bodyProductosRegistrados").empty();
                    prod.draw_table_documents(arr.datos);
                } else {
                    $("#productosRegistrados").DataTable().destroy();
                    prod.init_table();
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * getUnidadMedida: Método para consultar las
     * unidades de medida de los productos
     */
    getUnidadMedida() {

        $.ajax({
            url: '../business/controller/class.producto.php',
            data: { funcion: 5 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#pro_UnidadMed").empty();
                $("#pro_UnidadMed").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#pro_UnidadMed").append('<option value="' + v['uniM_Id'] + '">' + v['uniM_Nombre'] + '</option>');
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
     * getUnidadMedida: Método para consultar las
     * categorias
     */
    getCategorias() {

        $.ajax({
            url: '../business/controller/class.categoria.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#pro_Categoria").empty();
                $("#pro_Categoria").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#pro_Categoria").append('<option value="' + v['cate_Id'] + '">' + v['cate_Descripcion'] + '</option>');
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
     * getBodegas: Método para consultar las
     * Bodegas
     */
     getBodegas() {

        $.ajax({
            url: '../business/controller/class.bodega.php',
            data: { funcion: 3 , tipo: 1},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#pro_IdBodega").empty();
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#pro_IdBodega").append('<option value="' + v['bod_Id'] + '">' + v['bod_Nombre'] + '</option>');
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
     * getUnidadMedida: Método para consultar las
     * Subcategorias
     */
    getSubCategorias() {

        var id_catee = $("#pro_Categoria").val();

        $.ajax({
            url: '../business/controller/class.subCategoria.php',
            data: { funcion: 3 , subCate_IdCategoria: id_catee },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#pro_SubCategoria").empty();
                $("#pro_SubCategoria").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#pro_SubCategoria").append('<option value="' + v['subCate_Id'] + '">' + v['subCate_Descripcion'] + '</option>');
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
     * getUnidadMedida: Método para consultar las
     * Subcategorias
     */
    getProveedores() {

        $.ajax({
            url: '../business/controller/class.proveedores.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#pro_IdProveedor").empty();
                $("#pro_IdProveedor").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#pro_IdProveedor").append('<option value="' + v['prov_Id'] + '">' + v['prov_Nombre'] + '</option>');
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
     * getUnidadMedida: Método para consultar las
     * MARCAS
     */
    getMarcas() {

        $.ajax({
            url: '../business/controller/class.marca.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#pro_IdMarca").empty();
                $("#pro_IdMarca").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#pro_IdMarca").append('<option value="' + v['mar_Id'] + '">' + v['mar_Descripcion'] + '</option>');
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
     * getCodigo: Método para consultar el max id de producto
     */
    getCodigo() {

        $.ajax({
            url: '../business/controller/class.producto.php',
            data: { funcion: 6 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr EEEE ', arr.datos[0]['id']);
                console.log('arr EEEE ', arr);
                $("#pro_Codigo").attr('readonly', true);
                if (arr.ok == 1) {
                    //$.each(arr.datos, function(k, v) {
                        if(arr.datos[0]['id'] == null){
                            $("#pro_Codigo").val(1);
                        }else{
                            $("#pro_Codigo").val(arr.datos[0]['id']);
                        }
                    //});
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * getImpuesto: Método para consultar los impuestos
     */
    getImpuesto() {

        $.ajax({
            url: '../business/controller/class.impuestos.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                $("#pro_IdImpuesto").empty();
                $("#pro_IdImpuesto").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#pro_IdImpuesto").append('<option value="' + v['imp_Id'] + '">' + v['imp_Descripcion'] + '</option>');
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
     * getProductoById: Método para consultar la 
     * información de un producto
     * @param type $id: llave primaria de la tabla productos
     */
    async getProductoById(id) {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        var permiso = await _permisos.getPermisos(idRol, 416);
        $('#div_stock').hide();
        $('#div_Bodega').hide();

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.producto.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    //$("#pro_Codigo").attr('readonly', false);
                    if (arr.ok == 1) {                        
                        for (let datos of arr.datos) {
                            $("#pro_Codigo").val(datos.pro_Codigo);
                            $("#pro_Nombre").val(datos.pro_Nombre);
                            $("#pro_CodBarras").val(datos.pro_CodBarras);
                            $("#pro_Tipo").val(datos.pro_Tipo);
                            $("#pro_UnidadMed").val(datos.pro_UnidadMed);
                            $("#pro_CantidadMed").val(datos.pro_CantidadMed);
                            $("#pro_UsaStoks").val(datos.pro_UsaStoks);
                            $("#pro_IdImpuesto").val(datos.pro_IdImpuesto);
                            $("#pro_Categoria").val(datos.pro_Categoria);
                            $("#pro_SubCategoria").val(datos.pro_SubCategoria);
                            $("#pro_IdMarca").val(datos.pro_IdMarca);
                            $("#pro_IdProveedor").val(datos.pro_IdProveedor);

                            $("#pro_costo").val(datos.strPrecioCompra);
                            $("#pro_PrecioVenta").val(datos.strPrecioVenta);  
                            $("#pro_Costo_Id").val(datos.strPrecioCompraId);
                            $("#pro_PrecioVenta_Id").val(datos.strPrecioVentaId);  
                        }

                        $("#formCrearProducto").attr('action', 'javascript:prod.editProducto(' + id + ')');
                        $("#modal_footer").empty();
                        $("#modal_footer").append(
                            '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>' +
                            ' Cancelar' +
                            '</button>' +
                            '<button type="submit" class="btn btn-success btn-pill"><span class="ti-reload"></span>' +
                            ' Actualzar' +
                            '</button>'
                        );
                        //$("#pro_porIva").number(true, 2);
                        //$("#pro_porIca").number(true, 2);
                        //$("#pro_porCom").number(true, 2);
                        $("#modal-Producto").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información del producto',
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
     * getPreciosProductoById: Método para consultar la 
     * información de un producto
     * @param type $id: llave primaria de la tabla productos
     */
    async getPreciosProductoById(id) {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        var permiso = await _permisos.getPermisos(idRol, 416);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                      'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.preciosVenta.php',
                data: { funcion: 3, preVen_IdProducto: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            
                            $("#pre_Preciouno").val(arr.datos[0].preVen_PrecioNeto);
                            $("#pre_Preciouno_Id").val(arr.datos[0].preVen_Id);
                    
                            //$("#pre_Preciodos").val(arr.datos[1].preVen_PrecioNeto);
                            //$("#pre_Preciodos_Id").val(arr.datos[1].preVen_Id);
                            
                        }

                        $("#formCrearPrecios").attr('action', 'javascript:prod.editPrecios(' + id + ')');
                        $("#modal_footer_1").empty();
                        $("#modal_footer_1").append(
                            '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>' +
                            ' Cancelar' +
                            '</button>' +
                            '<button type="submit" class="btn btn-success btn-pill"><span class="ti-reload"></span>' +
                            ' Actualzar' +
                            '</button>'
                        );

                        $("#modal-Precios").modal('show');
                    } else {
                        $("#formCrearPrecios").trigger("reset");
                        $("#modal-Precios").modal('hide');

                        $("#formCrearPrecios").attr('action', 'javascript:prod.postPrecios(' + id + ')');
                        $("#modal_footer_1").empty();
                        $("#modal_footer_1").append(
                            '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>' +
                            ' Cancelar' +
                            '</button>' +
                            '<button type="submit" class="btn btn-success btn-pill"><span class="ti-reload"></span>' +
                            ' Crear' +
                            '</button>'
                        );

                        $("#modal-Precios").modal('show');

                        //swal({
                        //    type: 'error',
                        //    title: 'Error',
                        //    text: 'No se pudo consultar la información del producto',
                        //});
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
     * getVerStocks: Método para consultar los
     * stoks del producto
     * @param type $id: llave primaria de la tabla productos
     */
          async getVerStocks(id) {
/**
           var permiso = await _permisos.getPermisos(idRol, 416);
    
            if (permiso.ok != 1) {
                swal({
                    type: 'warning',
                    title: 'Error de privilegios',
                    text: 'Usted no tiene los privilegios para realizar esta acción,' +
                          'para obtenerlos comuniquese con el admininstrador del sistema',
                });
            } else {
 */
                $.ajax({
                    url: '../business/controller/class.producto.php',
                    data: { funcion: 7, id_Producto: id },
                    dataType: "json",
                    type: "POST",
                    success: function(arr) {
                        $('#loading').hide();
                        $('#wrapper').removeClass('body-load');
                        if (arr.ok == 1) {
                            $("#formCrearStock").attr('action', 'javascript:prod.editPrecios(' + id + ')');
                            $("#modal_body_1").empty();
                            
                            for (let datos of arr.datos) {
                                $("#modal_body_1").append(
                                    '<label>' + datos.nombre + '</label>' +
                                    '<input type="text" class="form-control" value="' + datos.cantidad + '" readonly></input>'
                                );
                            }

                            $("#modal-Stock").modal('show');                            
                           
                        } else {
                            $("#modal_body_1").empty();
                            $("#modal_body_1").append(
                                '<label>No hay stock en Bodegas</label>' 
                            );    
                            $("#modal-Stock").modal('show');

                        }
                    },
                    error: function(XMLHttpRequest, textStatus, errorThrown) {
                        console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                        //location.href = "../../login.html";
                    }
                });
//            }
        }

    /**
     * getPrecios: Método para consultar los precios del
     * producto enviado por parametro 
     * @param type $id: llave primaria de la tabla productos
     */
    async postPrecios(id) {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        //var permiso = await _permisos.getPermisos(idRol, 463);
        //$("#modal-Precios").modal('show');
        
        var precioUno = $("#pre_Preciouno").val();
        //var precioDos = $("#pre_Preciodos").val();

        //for(var i = 1 ; i<= 2; i++){
            
            $.ajax({
                url: '../business/controller/class.preciosVenta.php',
                data: { funcion: 1, precioNeto: precioUno, idProducto: id, idTarifa: 1},
                dataType: "json",
                type: "POST",
                success: function(arr) {
    
                    if (arr.ok == 1) {
                        $("#formCrearPrecio").trigger("reset");
                        $("#modal-Precio").modal('hide');
                        prod.getProductos();
                        swal({
                            type: 'success',
                            title: 'Precio creado',
                            text: 'Precio creado exitosamente',
                        });
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo crear el usuario',
                        });
                    }
    
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                    //location.href = "../../login.html";
                }
            });

/*
            $.ajax({
                url: '../business/controller/class.preciosVenta.php',
                data: { funcion: 1, precioNeto: precioDos, idProducto: id, idTarifa: 2},
                dataType: "json",
                type: "POST",
                success: function(arr) {
    
                    if (arr.ok == 1) {
                        $("#formCrearPrecio").trigger("reset");
                        $("#modal-Precios").modal('hide');
                        prod.getProductos();
                        swal({
                            type: 'success',
                            title: 'Precio creado',
                            text: 'Precio creado exitosamente',
                        });
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo crear el usuario',
                        });
                    }
    
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                    //location.href = "../../login.html";
                }
            });
            */
            $("#modal-Precios").modal('hide');
        //}
    }


    /**
     * postProducto: Método para crear 
     * productos
     */
    postProducto() {
        /* $('#loading').show();
        $('#wrapper').addClass('body-load'); */        
        var cod = $("#pro_Codigo").val();
        var nombre = $("#pro_Nombre").val();
        var codBarras = $("#pro_CodBarras").val();
        var tipo = $("#pro_Tipo").val();
        var unidad = $("#pro_UnidadMed").val();
        var cantidadMed = $("#pro_CantidadMed").val();
        var UsaStoks = $("#pro_UsaStoks").val();
        var IdImpuesto = $("#pro_IdImpuesto").val();
        var Categoria = $("#pro_Categoria").val();
        var SubCategoria = $("#pro_SubCategoria").val();
        var IdMarca = $("#pro_IdMarca").val();
        var IdProveedor = $("#pro_IdProveedor").val();  

        var pro_costo = $("#pro_costo").val();
        var pro_PrecioVenta = $("#pro_PrecioVenta").val();  
        var pro_StockInicial = $("#pro_StockInicial").val();  
        
        var pro_IdBodega = $("#pro_IdBodega").val();  

        var observaciones = 'Creación Producto';


        $.ajax({
            url: '../business/controller/class.producto.php',
            data: { funcion: 1, codigo: cod, nombre: nombre, codBarras: codBarras, 
                    tipo: tipo, unidad: unidad, cantidadMed: cantidadMed, UsaStoks: UsaStoks, 
                    IdImpuesto: IdImpuesto, Categoria: Categoria, SubCategoria: SubCategoria, 
                    IdMarca: IdMarca, IdProveedor: IdProveedor, pro_PrecioVenta: pro_PrecioVenta},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                var detallesNota = [];

                detallesNota.push({ "detkar_IdProducto":arr.id , "detkar_Cantidad": pro_StockInicial, 
                "detkar_CostoUnitario": pro_costo, "detkar_IdBodega": pro_IdBodega});
                
                var det = JSON.stringify(detallesNota);

                if (arr.ok == 1) {

                    $.ajax({
                        url: '../business/controller/class.nota.php',
                        data: { funcion: 1, kar_Tipo: 1, Observaciones: observaciones, detallesNota: det },
                        type: 'POST',
                        dataType: 'json',
                        success: function(arr) {
        
                            if (arr.ok == 1) {

                                $("#formCrearProducto").trigger("reset");
                                $("#modal-Producto").modal('hide');
                                prod.getProductos();
                                swal({
                                    type: 'success',
                                    title: 'Producto creado',
                                    text: 'Producto creado exitosamente',
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

                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Código duplicado',
                        text: arr.mensaje,
                    });
                } else if (arr.ok == 3) {
                    swal({
                        type: 'warning',
                        title: 'Código de Barras duplicado',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear el usuario',
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
     * cambiarEstado: Método para cambiar el 
     * estado de los productos
     * @param type $id_pro: llave primaria de la tabla productos
     * @param type $estado: estado actual del producto
     */
    async cambiarEstado(id_pro, estado) {
        var permiso = await _permisos.getPermisos(idRol, 417);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar el producto?";
                var subtitle = "Una vez inactivado, no podrá ser utilizado";
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
                    /*  $('#loading').show();
                     $('#wrapper').addClass('body-load'); */
                    $.ajax({
                        url: '../business/controller/class.producto.php',
                        data: { funcion: 4, id: id_pro, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);

                            if (arr.ok == 1) {
                                prod.getProductos();
                                swal({
                                    type: 'success',
                                    title: 'Producto actualizado',
                                    text: 'Uusario actualizado exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el producto',
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
     * cambiarEstado: Método para Editar el producto
     * @param type $id: llave primaria de la tabla productos
     */
    editProducto(id) {
        /*   $('#loading').show();
          $('#wrapper').addClass('body-load'); */
        
        
        var cod = $("#pro_Codigo").val();
        var nombre = $("#pro_Nombre").val();
        var codBarras = $("#pro_CodBarras").val();
        var tipo = $("#pro_Tipo").val();
        var unidad = $("#pro_UnidadMed").val();
        var cantidadMed = $("#pro_CantidadMed").val();
        var UsaStoks = $("#pro_UsaStoks").val();
        var IdImpuesto = $("#pro_IdImpuesto").val();
        var Categoria = $("#pro_Categoria").val();
        var SubCategoria = $("#pro_SubCategoria").val();
        var IdMarca = $("#pro_IdMarca").val();
        var IdProveedor = $("#pro_IdProveedor").val();  

        var pro_costo = $("#pro_costo").val();
        var pro_costo_Id = $("#pro_Costo_Id").val();
        var pro_PrecioVenta = $("#pro_PrecioVenta").val();
        var pro_PrecioVenta_Id = $("#pro_PrecioVenta_Id").val();
        //console.log('iva ', iva, ' ica ', ica, ' com ', com)

        $.ajax({
            url: '../business/controller/class.producto.php',
            data: { funcion: 2, id: id, codigo: cod, nombre: nombre, codBarras: codBarras, 
                tipo: tipo, unidad: unidad, cantidadMed: cantidadMed, UsaStoks: UsaStoks, 
                IdImpuesto: IdImpuesto, Categoria: Categoria, SubCategoria: SubCategoria, 
                IdMarca: IdMarca, IdProveedor: IdProveedor,
                pro_costo: pro_costo, pro_costo_Id: pro_costo_Id, 
                pro_PrecioVenta: pro_PrecioVenta, pro_PrecioVenta_Id: pro_PrecioVenta_Id},
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
                        title: 'Código de Barras duplicado',
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

    /**
    * cambiarEstado: Método para Editar el producto
    * @param type $id: llave primaria de la tabla productos
    */
    editPrecios(id) {
        /*   $('#loading').show();
          $('#wrapper').addClass('body-load'); */

          var precioUnoId = $("#pre_Preciouno_Id").val();
          var precioUno = $("#pre_Preciouno").val();
         // var precioDosId = $("#pre_Preciodos_Id").val();
         // var precioDos = $("#pre_Preciodos").val();

          $.ajax({
            url: '../business/controller/class.preciosVenta.php',
            data: { funcion: 2, id: precioUnoId, idTarifa: 1, idProducto: id, precioNeto: precioUno },
            dataType: "json",
            type: "POST",
            success: function(arr) {

                if (arr.ok == 1) {
                    $("#formCrearPrecio").trigger("reset");
                    $("#modal-Precios").modal('hide');
                    prod.getProductos();
                    swal({
                        type: 'success',
                        title: 'Precio Editado',
                        text: 'Precio Editado exitosamente',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear el usuario',
                    });
                }

            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });

//******************************************************************************************************/
/*
        $.ajax({
            url: '../business/controller/class.preciosVenta.php',
            data: { funcion: 2, id: precioDosId, idTarifa: 2, idProducto: id, precioNeto: precioDos },
            dataType: "json",
            type: "POST",
            success: function(arr) {

                if (arr.ok == 1) {
                    $("#formCrearPrecio").trigger("reset");
                    $("#modal-Precio").modal('hide');
                    prod.getProductos();
                    swal({
                        type: 'success',
                        title: 'Precio Editado',
                        text: 'Precio Editado exitosamente',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear el usuario',
                    });
                }

            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
        */

    }


    /**
    * activarCampos: Metodo para activar campo de ingresar
    */
     getstock(obj){
        console.log(obj);
        if(obj == 0){
            document.getElementById('pro_StockInicial').style.display = "none";   
            document.getElementById('pro_labelStock').style.display = "none";     
            document.getElementById('pro_IdBodega').style.display = "none";   
            document.getElementById('pro_LabelIdBodega').style.display = "none";     
        }else{
            document.getElementById('pro_StockInicial').style.display = "";
            document.getElementById('pro_labelStock').style.display = "";  
            document.getElementById('pro_IdBodega').style.display = "";   
            document.getElementById('pro_LabelIdBodega').style.display = "";     
        }
    }
    
    

    /**
     * BodegaActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    ProductoActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DInventario").addClass('expand');
        $("#DInventario").addClass('active');
        $("#SInventario").addClass('show');
        $("#SubMenuProducto").addClass('active');
    }

}

const prod = new Producto();
prod.getUnidadMedida();
prod.getCategorias();
prod.getBodegas();
prod.getSubCategorias();
prod.getProveedores();
prod.getMarcas();
prod.getImpuesto();
prod.getCodigo();
prod.getProductos();
prod.ProductoActivo();