/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;


class Insumo {

    constructor() {}

    /**
     * crearInsumo: Método para abrir modal de creación
     */
    async crearInsumo() {
        var permiso = await _permisos.getPermisos(idRol, 727);

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
            $("#formCrearInsumo").trigger("reset");
            prod.getCodigo();
            $("#formCrearInsumo").attr('action', 'javascript:prod.postInsumo()');
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
            $("#modal-Insumo").modal('show');
        }
    }

       /**
     * init_table: Método para asignar la
     * propiedad DataTable() a la tabla de Insumos
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
                'emptyTable': 'No hay Insumos',
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
     * de Insumos 
     * @param type $arrFilter: Listado de obejtos de tipo bodega
     */
    draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        $("#insumosRegistrados").DataTable().destroy();
        $("#bodyInsumosRegistrados").empty();
        for (let pro of arrFilter) {
            if (pro.ins_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Insumo";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Insumo";
            }
/*
            if (pro.pro_Tipo == 1) {
                var NomTipo = "Insumo";
            } else {
                var NomTipo = "Servicio";
            }
      */ 
            if ((pro.ins_ReferenciaNombre1 == null) || (pro.ins_ReferenciaNombre1 == '')) {
                var refe1 = 'No Aplica';
            } else {
                var refe1 = pro.ins_ReferenciaNombre1+" = "+pro.ins_ReferenciaValor1;
            }

            if ((pro.ins_ReferenciaNombre2 == null) || (pro.ins_ReferenciaNombre2 == '')) {
                var refe2 = 'No Aplica';
            } else {
                var refe2 = pro.ins_ReferenciaNombre2+" = "+pro.ins_ReferenciaValor2;
            }

            if (pro.strTotalStock == null) {
                var TotalStock = "0";
                var color= "bg-danger"
            } else {
                if (pro.strTotalStock >=100) {
                    var color= "bg-success"
                }else if(pro.strTotalStock >10 && pro.strTotalStock <100 ){
                    var color= "bg-warning"
                }else if(pro.strTotalStock <=10 ){
                    var color= "bg-danger"
                }
                var TotalStock = pro.strTotalStock;
            }
            

            $('#bodyInsumosRegistrados').append(
                '<tr>' +
                '<td>' +
                pro.ins_Codigo +
                '</td>' +
                '<td>' +
                pro.ins_Nombre +
                '</td>' +
                '<td>' +
                pro.strNombreCategoria +
                '</td>' +
                '<td>' +
                pro.strNombreSubCategoria +
                '</td>' +
                '<td class="'+ color +'">' +
                TotalStock +
                '</td>' +
                
                '<td align="center">' +
                '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar Insumo" style="margin-right:5px" onclick="javascript:prod.getInsumoById(' + pro.ins_Id + ')">' +
                '<i class="dw dw-edit2"></i>' +
                '</button>' +

                '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:prod.cambiarEstado(' + pro.ins_Id + ',' + pro.ins_Estado + ')">' +
                '<i class="' + icono + '"></i>' +
                '</button>' +
                '</td>' +

                '</tr>'
            );

        }
        prod.init_table();
    }

 
    /**
     * getInsumos: Método para consultar los 
     * Insumos registrados
     */
    getInsumos() {

        $.ajax({
            url: '../business/controller/class.insumo.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);

                if (arr.ok == 1) {
                    $("#bodyInsumosRegistrados").empty();
                    prod.draw_table_documents(arr.datos);
                } else {
                    $("#InsumosRegistrados").DataTable().destroy();
                    prod.init_table();
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
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
                url: '../business/controller/class.insumo.php',
                data: { funcion: 8 },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    console.log('arr EEEE ', arr);
                    $("#ins_Codigo").attr('readonly', true);
                    if (arr.ok == 1) {
                        //$.each(arr.datos, function(k, v) {
                            $("#ins_Codigo").val(arr.datos[0]['id']);
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
     * getUnidadMedida: Método para consultar las
     * unidades de medida de los Insumos
     */
    getUnidadMedida() {

        $.ajax({
            url: '../business/controller/class.insumo.php',
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
     * getImpuesto: Método para consultar los Proveedores
     */
    getProveedor() {

        $.ajax({
            url: '../business/controller/class.proveedores.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                $("#ins_IdProveedor").empty();
                $("#ins_IdProveedor").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#ins_IdProveedor").append('<option value="' + v['prov_Id'] + '">' + v['prov_Nombre'] + '</option>');
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
     * getBodega: Método para consultar las Tipo Cantidad
     */
    getTipoCantidad() {

        $.ajax({
            url: '../business/controller/class.insumo.php',
            data: { funcion: 6, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#ins_IdTipoCantidad").empty();
                $("#ins_IdTipoCantidad").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#ins_IdTipoCantidad").append('<option value="' + v['tica_Id'] + '">' + v['tica_Nombre'] + '</option>');
                    });
                }
                $("#ins_IdTipoCantidad").addClass('custom-select2');
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * getBodega: Método para consultar las Tipo Unidad
     */
    getTipoUnidad() {

        $.ajax({
            url: '../business/controller/class.insumo.php',
            data: { funcion: 7, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#ins_IdTipoUnidad").empty();
                $("#ins_IdTipoUnidad").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#ins_IdTipoUnidad").append('<option value="' + v['tiuni_Id'] + '">' + v['tiuni_Nombre'] + '</option>');
                    });
                }
                $("#ins_IdTipoUnidad").addClass('custom-select2');
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
                $("#ins_IdCategoria").empty();
                $("#ins_IdCategoria").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#ins_IdCategoria").append('<option value="' + v['cate_Id'] + '">' + v['cate_Descripcion'] + '</option>');
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

        $.ajax({
            url: '../business/controller/class.subCategoria.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#ins_IdSubCategoria").empty();
                $("#ins_IdSubCategoria").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#ins_IdSubCategoria").append('<option value="' + v['subCate_Id'] + '">' + v['subCate_Descripcion'] + '</option>');
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
     * getInsumoById: Método para consultar la 
     * información de un Insumo
     * @param type $id: llave primaria de la tabla Insumos
     */
    async getInsumoById(id) {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        var permiso = await _permisos.getPermisos(idRol, 728);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.insumo.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    //$("#ins_Codigo").attr('readonly', false);
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#ins_Codigo").val(datos.ins_Codigo);
                            $("#ins_Nombre").val(datos.ins_Nombre);
                            $("#ins_CodBarras").val(datos.ins_CodBarras);
                            $("#ins_IdProveedor").val(datos.ins_IdProveedor);
                            $("#ins_IdCategoria").val(datos.ins_IdCategoria);
                            $("#ins_IdSubCategoria").val(datos.ins_IdSubCategoria);
                            $("#ins_IdTipoCantidad").val(datos.ins_IdTipoCantidad);
                            $("#ins_IdTipoUnidad").val(datos.ins_IdTipoUnidad);
                            $("#ins_ReferenciaNombre1").val(datos.ins_ReferenciaNombre1);
                            $("#ins_ReferenciaValor1").val(datos.ins_ReferenciaValor1);
                            $("#ins_ReferenciaNombre2").val(datos.ins_ReferenciaNombre2);
                            $("#ins_ReferenciaValor2").val(datos.ins_ReferenciaValor2);
                        }

                        $("#formCrearInsumo").attr('action', 'javascript:prod.editInsumo(' + id + ')');
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
                        $("#modal-Insumo").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información del Insumo',
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
     * postInsumo: Método para crear 
     * Insumos
     */
    postInsumo() {
        /* $('#loading').show();
        $('#wrapper').addClass('body-load'); */

        var cod = $("#ins_Codigo").val();
        var nombre = $("#ins_Nombre").val();
        var codBarras = $("#ins_CodBarras").val();
        var IdProveedor = $("#ins_IdProveedor").val();
        var IdCategoria = $("#ins_IdCategoria").val();
        var IdSubCategoria = $("#ins_IdSubCategoria").val();
        var IdTipoCantidad = $("#ins_IdTipoCantidad").val();
        var IdTipoUnidad = $("#ins_IdTipoUnidad").val();
        var ReferenciaNombre1 = $("#ins_ReferenciaNombre1").val();
        var ReferenciaValor1 = $("#ins_ReferenciaValor1").val();
        var ReferenciaNombre2 = $("#ins_ReferenciaNombre2").val();
        var ReferenciaValor2 = $("#ins_ReferenciaValor2").val();
        //var imagen = $("#imagen").val();  

        $.ajax({
            url: '../business/controller/class.insumo.php',
            data: { funcion: 1, codigo: cod, 
                nombre: nombre, 
                codBarras: codBarras, 
                idProveedor: IdProveedor, 
                idCategoria: IdCategoria,
                idSubCategoria: IdSubCategoria, 
                idTipoCantidad: IdTipoCantidad, 
                idTipoUnidad: IdTipoUnidad, 
                referenciaNombre1: ReferenciaNombre1, 
                referenciaValor1: ReferenciaValor1, 
                referenciaNombre2: ReferenciaNombre2,
                referenciaValor2: ReferenciaValor2},
            dataType: "json",
            type: "POST",
            success: function(arr) {

                if (arr.ok == 1) {
                    $("#formCrearInsumo").trigger("reset");
                    $("#modal-Insumo").modal('hide');
                    prod.getInsumos();
                    swal({
                        type: 'success',
                        title: 'Insumo creado',
                        text: 'Insumo creado exitosamente',
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
     * estado de los Insumos
     * @param type $id_pro: llave primaria de la tabla Insumos
     * @param type $estado: estado actual del Insumo
     */
    async cambiarEstado(id_pro, estado) {
        var permiso = await _permisos.getPermisos(idRol, 429);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar el Insumo?";
                var subtitle = "Una vez inactivado, no podrá ser utilizado";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar el Insumo?";
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
                        url: '../business/controller/class.insumo.php',
                        data: { funcion: 4, id: id_pro, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);

                            if (arr.ok == 1) {
                                prod.getInsumos();
                                swal({
                                    type: 'success',
                                    title: 'Insumo actualizado',
                                    text: 'Insumo actualizado exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el Insumo',
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
     * cambiarEstado: Método para Editar el Insumo
     * @param type $id: llave primaria de la tabla Insumos
     */
    editInsumo(id) {
        /*   $('#loading').show();
          $('#wrapper').addClass('body-load'); */

        var cod = $("#ins_Codigo").val();
        var nombre = $("#ins_Nombre").val();
        var codBarras = $("#ins_CodBarras").val();
        var IdProveedor = $("#ins_IdProveedor").val();
        var IdCategoria = $("#ins_IdCategoria").val();
        var IdSubCategoria = $("#ins_IdSubCategoria").val();
        var IdTipoCantidad = $("#ins_IdTipoCantidad").val();
        var IdTipoUnidad = $("#ins_IdTipoUnidad").val();
        var ReferenciaNombre1 = $("#ins_ReferenciaNombre1").val();
        var ReferenciaValor1 = $("#ins_ReferenciaValor1").val();
        var ReferenciaNombre2 = $("#ins_ReferenciaNombre2").val();
        var ReferenciaValor2 = $("#ins_ReferenciaValor2").val();

        //console.log('iva ', iva, ' ica ', ica, ' com ', com)
        $.ajax({
            url: '../business/controller/class.insumo.php',
            data: { funcion: 2, codigo: cod, id: id,
                nombre: nombre, 
                codBarras: codBarras, 
                idProveedor: IdProveedor, 
                idCategoria: IdCategoria,
                idSubCategoria: IdSubCategoria, 
                idTipoCantidad: IdTipoCantidad, 
                idTipoUnidad: IdTipoUnidad, 
                referenciaNombre1: ReferenciaNombre1, 
                referenciaValor1: ReferenciaValor1, 
                referenciaNombre2: ReferenciaNombre2,
                referenciaValor2: ReferenciaValor2},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);

                if (arr.ok == 1) {
                    $("#formCrearInsumo").trigger("reset");
                    $("#modal-Insumo").modal('hide');
                    prod.getInsumos();
                    swal({
                        type: 'success',
                        title: 'Insumo actualizado',
                        text: 'Insumo actualizado exitosamente',
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
                        text: 'No se pudo actualizar el Insumo',
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
     * BodegaActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    InsumoActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DProduccion").addClass('expand');
        $("#DProduccion").addClass('active');
        $("#SProduccion").addClass('show');
        $("#SubMenuInsumo").addClass('active');
    }

}

const prod = new Insumo();
//prod.getUnidadMedida();
prod.getProveedor();
prod.getInsumos();
prod.getCategorias();
prod.getSubCategorias();
prod.InsumoActivo();
prod.getCodigo();
prod.getTipoCantidad();
prod.getTipoUnidad();