/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;


class crearFactura {

    constructor() { }

    async crearProducto(){
        var permiso =  await _permisos.getPermisos(idRol,415);
        
        if(permiso.ok != 1){
            Swal.fire({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,'+
                     'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        }else{
            $("#clave").removeAttr('style');
            $("#usu_Clave").attr('required',true);
            $("#formCrearProducto").trigger("reset");
            $("#formCrearProducto").attr('action','javascript:prod.postProducto()');
            $("#modal_footer").empty();
            $("#modal_footer").append(
                '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><i class="fas fa-reply"'+
                'aria-hidden="true"></i>'+
                'Cancelar'+
                '</button>'+
                '<button type="submit" class="btn btn-success btn-pill"><i class="fas fa-check"'+
                    'aria-hidden="true"></i>'+
                'Crear'+
                '</button>'
            );
            $("#modal-Producto").modal('show');
        }    
    }
    draw_table_documents(arrFilter) {
        console.log('arr',arrFilter);
        
        $("#productosRegistrados").DataTable().destroy();
        $("#bodyProductosRegistrados").empty();
        for (let pro of arrFilter) {
            if(pro.pro_Estado == 1){
                var icono = "mdi mdi-check";
                var clase = "btn-success";
                var titulo = "Inactivar Producto";
            }else{
                var icono = "mdi mdi-close";
                var clase = "btn-danger";
                var titulo = "Activar Producto";
            }
            
            if(pro.pro_Tipo == 1){
                var NomTipo = "Producto";
            }else{
                var NomTipo = "Servicio";
            }
                        
            $('#bodyProductosRegistrados').append(
                '<tr>'+
                    '<td>'
                        +pro.pro_Codigo+
                    '</td>'+
                    '<td>'
                        +pro.pro_Nombre+
                    '</td>'+
                    '<td>'
                        +NomTipo+
                    '</td>'+
                    '<td>'
                        +pro.strNombreUnidad+
                    '</td>'+
                    
                    '<td align="center">'+
                        '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar producto" style="margin-right:5px" onclick="javascript:prod.getProductoById('+pro.pro_Id+')">'+
                            '<i class="mdi mdi-border-color"></i>'+
                        '</button>'+

                        '<button type="button" class="btn btn-social-icon '+clase+' " data-toggle="tooltip" title="'+titulo+'"  onclick="javascript:prod.cambiarEstado('+pro.pro_Id+','+pro.pro_Estado+')">'+
                            '<i class="'+icono+'"></i>'+
                        '</button>'+
                    '</td>'+ 

                '</tr>'
            );
              
        }
        prod.init_table();
    }
    
    init_table() {
        console.log('entro')
        $("#listadoProductos").DataTable({
            responsive: true,
            paging: false,
            lengthChange: true,
            searching: false,
           
            aLengthMenu: [[20, 30, 50, 75, -1], [20, 30, 50, 75, "All"]],
            pageLength: 20,
            dom: '<"row justify-content-between top-information"lf>rt<"row justify-content-between bottom-information"ip><"clear">',
            /* columnDefs: [
                { "orderable": false, targets: [5] },
            ],
             */language: {
                'decimal': '',
                'emptyTable': 'Listado de productos',
                "info": 'Mostrando _START_ a _END_ de _TOTAL_ Entradas',
                'infoEmpty': 'Mostrando 0 to 0 of 0 Entradas',
                'infoFiltered': '(Filtrado de _MAX_ total entradas)',
                'infoPostFix': '',
                'thousands': ',',
                'lengthMenu': 'Mostrar _MENU_ Entradas',
                'loadingRecords': 'Cargando...',
                'processing': 'Procesando...',
                'search': 'Buscar:',
                'searchPlaceholder': 'descripción, código',
                'zeroRecords': 'Sin resultados encontrados',
                'paginate': {
                    'first': 'Primero',
                    'last': 'Último',
                    'next': 'Siguiente',
                    'previous': 'Anterior',
                }
            }
        });
    }

    formatearFecha(fecha) {
        var arrFecha = fecha.split('T');
        var newFecha = arrFecha[0];
        return newFecha;
    }

    getProductos() {
       
        $.ajax({
            url: '../business/controller/class.producto.php',
            data: {funcion : 3},
            dataType: "json",
            type: "POST",
            success: function (arr) {
                console.log('arr ',arr);
               
                if(arr.ok == 1){
                    $("#bodyProductosRegistrados").empty();
                    prod.draw_table_documents(arr.datos);
                }else{
                    $("#productosRegistrados").DataTable().destroy();
                    prod.init_table();
                }
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    getUnidadMedida() {
        
        $.ajax({
             url: '../business/controller/class.producto.php',
             data: {funcion : 5},
             dataType: "json",
             type: "POST",
             success: function (arr) {
                console.log('arr ',arr);
                $("#pro_UnidadMed").empty();
                $("#pro_UnidadMed").append('<option value="">Seleccion una opción</option>');
                if(arr.ok == 1){
                    $.each(arr.datos, function (k, v){
                        $("#pro_UnidadMed").append('<option value="'+v['uniM_Id']+'">'+v['uniM_Nombre']+'</option>');
                    });
                }
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }
    
    async getProductoById(id) {
       /*  $('#loading').show();
        $('#wrapper').addClass('body-load');  */
        var permiso =  await _permisos.getPermisos(idRol,416);
        
        if(permiso.ok != 1){
            Swal.fire({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,'+
                     'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        }else{
            $.ajax({
                url: '../business/controller/class.producto.php',
                data: {funcion : 3, id : id},
                dataType: "json",
                type: "POST",
                success: function (arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load'); 
                    if(arr.ok == 1){
                        for (let datos of arr.datos){
                            $("#pro_Nombre").val(datos.pro_Nombre);
                            $("#pro_Codigo").val(datos.pro_Codigo);
                            $("#pro_CodBarras").val(datos.pro_CodBarras);
                            $("#pro_Tipo").val(datos.pro_Tipo);
                            $("#pro_UnidadMed").val(datos.pro_UnidadMed);
                            
                        }
                    
                        $("#formCrearProducto").attr('action','javascript:prod.editProducto('+id+')');
                        $("#modal_footer").empty();
                        $("#modal_footer").append(
                            '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><i class="fas fa-reply"'+
                            'aria-hidden="true"></i>'+
                            'Cancelar'+
                            '</button>'+
                            '<button type="submit" class="btn btn-success btn-pill"><i class="fas fa-check"'+
                                'aria-hidden="true"></i>'+
                            'Actualzar'+
                            '</button>'
                        );
                        $("#modal-Producto").modal('show'); 
                    }else{
                        Swal.fire({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información del producto',
                        });
                    }
                },
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                    //location.href = "../../login.html";
                }
            });
        }    
    }

    postProducto() {
        /* $('#loading').show();
        $('#wrapper').addClass('body-load'); */
        
        var nombre = $("#pro_Nombre").val();
        var cod = $("#pro_Codigo").val();
        var codBarras = $("#pro_CodBarras").val();
        var tipo = $("#pro_Tipo").val();
        var unidad = $("#pro_UnidadMed").val();
        
        $.ajax({
            url: '../business/controller/class.producto.php',
            data: {funcion : 1, nombre: nombre, codigo : cod, tipo: tipo,  unidad: unidad},
            dataType: "json",
            type: "POST",
            success: function (arr) {
               
                if(arr.ok == 1){
                    $("#formCrearProducto").trigger("reset");
                    $("#modal-Producto").modal('hide'); 
                    prod.getProductos();
                    Swal.fire({
                        type: 'success',
                        title: 'Producto creado',
                        text: 'Producto creado exitosamente',
                    });
                }else if(arr.ok == 2){
                    Swal.fire({
                        type: 'warning',
                        title: 'Código duplicado',
                        text: arr.mensaje,
                    });
                }else if(arr.ok == 3){
                    Swal.fire({
                        type: 'warning',
                        title: 'Identificación duplicada',
                        text: arr.mensaje,
                    });
                }else{
                    Swal.fire({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear el usuario',
                    });
                }
              
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }
    
    async cambiarEstado(id_pro,estado){
        var permiso =  await _permisos.getPermisos(idRol,417);
        
        if(permiso.ok != 1){
            Swal.fire({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,'+
                     'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        }else{
            if(estado == 1){
                var title = "¿Está seguro de inactivar el producto?";
                var subtitle = "Una vez inactivado, no podrá ser utilizado";
                var button = "Sí, inactivar";
                var est = 0;
            }else{
                var title = "¿Está seguro de activar el producto?";
                var subtitle = "Una vez activado, podrá ser utilizado";
                var button = "Sí, activar";
                var est = 1;
            }
            Swal.fire({
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
                        url: '../business/controller/class.producto.php',
                        data: {funcion : 4, id: id_pro, estado: est},
                        dataType: "json",
                        type: "POST",
                        success: function (arr) {
                            console.log('roles',arr);
                            
                            if(arr.ok == 1){
                                prod.getProductos();
                                Swal.fire({
                                    type: 'success',
                                    title: 'Producto actualizado',
                                    text: 'Uusario actualizado exitosamente',
                                });
                            }else{
                                Swal.fire({
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
                        error: function (XMLHttpRequest, textStatus, errorThrown) {
                            console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                            //location.href = "../../login.html";
                        }
                    });
                }
            })
        }    
    }
    
    editProducto(id){
      /*   $('#loading').show();
        $('#wrapper').addClass('body-load'); */
        
        var nombre = $("#pro_Nombre").val();
        var cod = $("#pro_Codigo").val();
        var codBarras = $("#pro_CodBarras").val();
        var tipo = $("#pro_Tipo").val();
        var unidad = $("#pro_UnidadMed").val();

        $.ajax({
            url: '../business/controller/class.producto.php',
            data: {funcion : 2, id: id,nombre: nombre, codigo : cod, tipo: tipo,  unidad: unidad},
            dataType: "json",
            type: "POST",
            success: function (arr) {
                console.log('roles',arr);
                
                if(arr.ok == 1){
                    $("#formCrearProducto").trigger("reset");
                    $("#modal-Producto").modal('hide'); 
                    prod.getProductos();
                    Swal.fire({
                        type: 'success',
                        title: 'Producto actualizado',
                        text: 'Producto actualizado exitosamente',
                    });
                }else if(arr.ok == 2){
                    Swal.fire({
                        type: 'warning',
                        title: 'Código duplicado',
                        text: arr.mensaje,
                    });
                }else if(arr.ok == 3){
                    Swal.fire({
                        type: 'warning',
                        title: 'Identificación duplicada',
                        text: arr.mensaje,
                    });
                }else{
                    Swal.fire({
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
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    enableButton() {
        enable = !enable;
        document.getElementById('buttonCrearUsuario').disabled = enable;
    }
    
    
    crearFacturaActiva(){
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');
        
        $("#MenuFacturacion").addClass('expand');
        $("#MenuFacturacion").addClass('active');
        $("#facturacion").addClass('show');
        $("#SubMenuCrearFactura").addClass('active');
    }
       
}

const crefac = new crearFactura();
/* fac.getUnidadMedida();
fac.getProductos(); */
crefac.crearFacturaActiva();
crefac.init_table();

