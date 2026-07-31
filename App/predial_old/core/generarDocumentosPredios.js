/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;


class Empresa {

    constructor() {}

    /**
     * crearEmpresa: Método para abrir modal de creación
     */
    async crearEmpresa() {
        var permiso = await _permisos.getPermisos(idRol, 415);
        $('#div_stock').show();
        
        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
       
            $("#imageSoporte").val('');
            $("#formCrearEmpresa").trigger("reset");
            $("#formCrearEmpresa").attr('action', 'javascript:prod.postEmpresa()');
            $("#modal_footer_Empresa").empty();
            $("#modal_footer_Empresa").append(
                //'<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>' +
                //' Cancelar' +
                //'</button>' +
                '<button type="submit" class="btn btn-success btn-pill"><span class="ti-plus"></span>' +
                ' Crear' +
                '</button>'
            );
        }
    }

    /**
     * init_table: Método para asignar la
     * propiedad DataTable() a la tabla de empresas
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
                [3, 5, 10, 25, 50, -1],
                [3, 5, 10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'Sedes Empresas registrados',
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
    * init_table: Método para asignar la
    * propiedad DataTable() a la tabla de Ver Cajas
    */
    init_table_VerCajas() {

        $('.data-table-VerCajas').DataTable({
            scrollCollapse: true,
            autoWidth: false,
            responsive: true,
            columnDefs: [{
                targets: "datatable-nosort",
                orderable: false,
                className: "text-center",
            }],
            "lengthMenu": [
                [2, 5, 10, 25, 50, -1],
                [2, 5, 10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'Cajas Registrados',
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
     * de empresas 
     * @param type $arrFilter: Listado de obejtos de tipo bodega
     */
    draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        $("#empresasRegistrados").DataTable().destroy();
        $("#bodyEmpresasRegistrados").empty();
        for (let pro of arrFilter) {
            $('#bodyEmpresasRegistrados').append(
                '<tr>' +

                '<td>' +
                pro.seem_Nombre +
                '</td>' +
                '<td>' +
                pro.seem_Direccion +
                '</td>' +
                
                '<td align="center">' +
                '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar empresa" style="margin-right:5px" onclick="javascript:prod.getSedeEmpresaById(' + pro.seem_Id + ')">' +
                '<i class="dw dw-edit2"></i>' +
                '</button>' +
                '</td>' +

                '<td align="center">' +
                '<button type="button" class="btn btn-social-icon btn-info " data-toggle="tooltip" title="Ver Cajas" style="margin-right:5px" onclick="javascript:prod.getVerCajas(' + pro.seem_Id + ')">' +
                '<i class="icon-copy dw dw-diagram"></i>' +
                '</button>' +
                '</td>' +

                '</tr>'
            );
        }
        prod.init_table();
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de empresas 
     * @param type $arrFilter: Listado de obejtos de tipo bodega
     */
     draw_table_documents_VerCajas(arrFilter) {
        console.log('arr', arrFilter);

        $("#VerCajasRegistrados").DataTable().destroy();
        $("#bodyVerCajasRegistrados").empty();

        for (let pro of arrFilter) {

            if(pro.strNumeroResolucion == null){
                var RESOpos = 'POS: No Aplica';
            }else{
                var RESOpos = 'POS: '+ pro.strNumeroResolucion;
            }

            if(pro.strNumeroResolucionRemi == null){
                var RESOremi = 'Remisión: No Aplica';
            }else{
                var RESOremi = 'Remisión: '+pro.strNumeroResolucionRemi;
            }

            $('#bodyVerCajasRegistrados').append(
                '<tr>' +

                '<td>' +
                pro.seemca_Nombre +
                '</td>' +
                '<td>' +
                RESOpos + ' _ ' +RESOremi  +
                '</td>' +

                //'<td>' +
                //+RESOremi  +
                //'</td>' +
                //'<td>' +
                //pro.seemca_FechaCreacion  +
                //'</td>' +
                
                '<td align="center">' +
                '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar empresa" style="margin-right:5px" onclick="javascript:prod.getCajaById(' + pro.seemca_Id + ')">' +
                '<i class="dw dw-edit2"></i>' +
                '</button>' +
                '</td>' +

                '</tr>'
            );
        }

        prod.init_table_VerCajas();
    }

 
    /**
     * getSedesEmpresas: Método para consultar los 
     * Sedes Empresas registrados
     */
     getSedesEmpresas() {
        /*$('#loading').show();
        $('#wrapper').addClass('body-load');*/

        $.ajax({
            url: '../business/controller/class.empresa.php',
            data: { funcion: 5 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#bodyEmpresasRegistrados").empty();
                    prod.draw_table_documents(arr.datos);
                } else {
                    $("#empresasRegistrados").DataTable().destroy();
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
     * unidades de medida de los empresas
     */
    getUnidadMedida() {

        $.ajax({
            url: '../business/controller/class.empresa.php',
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
            url: '../business/controller/class.marcas.php',
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
     * getCodigo: Método para consultar el max id de empresa
     */
    getCodigo() {

        $.ajax({
            url: '../business/controller/class.empresa.php',
            data: { funcion: 6 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr EEEE ', arr.datos[0]['id']);
                $("#pro_Codigo").attr('readonly', true);
                if (arr.ok == 1) {
                    //$.each(arr.datos, function(k, v) {
                        $("#pro_Codigo").val(arr.datos[0]['id']);
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
     * getSedeEmpresaById: Método para consultar la 
     * información de un empresa
     * @param type $id: llave primaria de la tabla empresas
     */
       async getSedeEmpresaById(id) {
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
                url: '../business/controller/class.empresa.php',
                data: { funcion: 5, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    
                    if (arr.ok == 1) {                        
                        for (let datos of arr.datos) {
                            $("#seem_IdEmpresa").val(datos.seem_IdEmpresa);
                            $("#seem_IdBodega").val(datos.seem_IdBodega);
                            $("#seem_Nombre").val(datos.seem_Nombre);
                            $("#seem_Telefono").val(datos.seem_Telefono);
                            $("#seem_Direccion").val(datos.seem_Direccion);
                            $("#seem_IdDepartamento").val(datos.seem_IdDepartamento);
                            $("#seem_IdMunicipio").val(datos.seem_IdMunicipio);
                            $("#seem_Email").val(datos.seem_Email);
                        }

                        $("#formCrearSedeEmpresa").attr('action', 'javascript:prod.editSedeEmpresa(' + id + ')');
                        $("#modal_footer_SedeEmpresa").empty();
                        $("#modal_footer_SedeEmpresa").append(
                            //'<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>' +
                            //' Cancelar' +
                            //'</button>' +
                            '<button type="submit" class="btn btn-success btn-pill"><span class="ti-reload"></span>' +
                            ' Actualizar' +
                            '</button>'
                        );
                        //$("#pro_porIva").number(true, 2);
                        //$("#pro_porIca").number(true, 2);
                        //$("#pro_porCom").number(true, 2);
                        $("#modal-SedeEmpresa").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información del empresa',
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
     * getCajaById: Método para consultar la 
     * información de una Caja
     * @param type $id: llave primaria de la tabla empresas
     */
         async getCajaById(id) {
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
                    url: '../business/controller/class.sedesEmpresaCajas.php',
                    data: { funcion: 3, id: id },
                    dataType: "json",
                    type: "POST",
                    success: function(arr) {
                        /*$('#loading').hide();
                        $('#wrapper').removeClass('body-load');*/
                        
                        if (arr.ok == 1) {          
                            for (let datos of arr.datos) {
                                $("#seemca_Nombre").val(datos.seemca_Nombre);
                                $("#seemca_Serial").val(datos.seemca_Serial);
                                $("#seemca_CodigoCaja").val(datos.seemca_CodigoCaja);
                                $("#seemca_IdResolucion").val(datos.seemca_IdResolucion);
                                $("#seemca_IdResolucionRemi").val(datos.seemca_IdResolucionRemi);
                                $("#seemca_IdSedeEmpresa").val(datos.seemca_IdSedeEmpresa);
                            }
    
                            $("#formCrearEditarCajas").attr('action', 'javascript:prod.editCajas(' + id + ')');
                            $("#modal_footer_EditarCajas").empty();
                            $("#modal_footer_EditarCajas").append(

                                '<button type="submit" class="btn btn-success btn-pill"><span class="ti-reload"></span>' +
                                ' Actualizar' +
                                '</button>'
                            );
                            
                            //$("#modal-CrearCajas").modal('show');

                        } else {
                            swal({
                                type: 'error',
                                title: 'Error',
                                text: 'No se pudo consultar la información del empresa',
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
     * getVerCajas: Método para consultar la 
     * información de un empresa
     * @param type $id: llave primaria de la tabla empresas
     */
         async getVerCajas(id) {
            /* $('#loading').show();
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
                    url: '../business/controller/class.sedesEmpresaCajas.php',
                    data: { funcion: 3, IdSedeEmpresa: id },
                    dataType: "json",
                    type: "POST",
                    success: function(arr) {

                        console.log('arr ', arr);
                        $('#loading').hide();
                        $('#wrapper').removeClass('body-load');

                        $("#formCrearEditarCajas").trigger("reset");
                        $("#modal_footer_EditarCajas").empty();
        
                        if (arr.ok == 1) {
                            $("#bodyVerCajasRegistrados").empty();
                            prod.draw_table_documents_VerCajas(arr.datos);
                        } else {
                            $("#VerCajasRegistrados").DataTable().destroy();
                            prod.init_table_VerCajas();
                        }
                        
                        //$("#formCrearEditarCajas").trigger("reset");
                        $("#modal-VerCajas").modal('show');

                    },
                    error: function(XMLHttpRequest, textStatus, errorThrown) {
                        console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                        //location.href = "../../login.html";
                    }
                });
            }
        }
    

     /**
     * editSedeEmpresa: Método para Editar el Sede
     * @param type $id: llave primaria de la tabla Sede
     */
      editSedeEmpresa(id) {
        /*$('#loading').show();
          $('#wrapper').addClass('body-load'); */

        var IdEmpresa = $("#seem_IdEmpresa").val();
        var IdBodega = $("#seem_IdBodega").val();
        var Nombre = $("#seem_Nombre").val();
        var Telefono = $("#seem_Telefono").val();
        var Direccion = $("#seem_Direccion").val();
        var IdDepartamento = $("#seem_IdDepartamento").val();
        var IdMunicipio = $("#seem_IdMunicipio").val();
        var Email = $("#seem_Email").val();

        $.ajax({
            url: '../business/controller/class.empresa.php',
            data: { funcion: 6, id: id, IdEmpresa: IdEmpresa, IdBodega: IdBodega, Nombre: Nombre, 
                Telefono: Telefono, Direccion: Direccion, IdDepartamento: IdDepartamento,
                 IdMunicipio: IdMunicipio, Email: Email},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);

                if (arr.ok == 1) {
                    $("#formCrearSedeEmpresa").trigger("reset");
                    $("#modal-SedeEmpresa").modal('hide');
                    prod.getSedesEmpresas();
                    swal({
                        type: 'success',
                        title: 'Sede actualizado',
                        text: 'Sede actualizada exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Nombre duplicado',
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
                        text: 'No se pudo actualizar el empresa',
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
     * getEmpresaById: Método para consultar la 
     * información de un empresa
     * @param type $id: llave primaria de la tabla empresas
     */
    async getEmpresaById(id) {
        $('#loading').show();
        $('#wrapper').addClass('body-load');
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
                url: '../business/controller/class.empresa.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    //$("#pro_Codigo").attr('readonly', false);
                    if (arr.ok == 1) {                        
                        for (let datos of arr.datos) {
                            $("#emp_Nombre").val(datos.emp_Nombre);
                            $("#emp_NombreComercial").val(datos.emp_NombreComercial);
                            $("#emp_Nit").val(datos.emp_Nit);
                            $("#emp_IdDepartamento").val(datos.emp_IdDepartamento);
                            $("#emp_IdMunicipio").val(datos.emp_IdMunicipio);
                            $("#emp_Email").val(datos.emp_Email);
                            $("#emp_SitioWeb").val(datos.emp_SitioWeb);
                            $("#emp_TipoImpresora").val(datos.emp_TipoImpresora);
                            $("#emp_TipoPantalla").val(datos.emp_TipoPantalla);
                            $("#emp_TextoFactura").val(datos.emp_TextoFactura);

                            $("#imagenCargar").show();
                            $("#imagenCargar").attr("src","../extensiones/tcpdf/pdf/"+datos.emp_UrlSoporteLogo);

                        }

                        $("#formCrearEmpresa").attr('action', 'javascript:prod.editEmpresa(' + id + ')');
                        $("#modal_footer_Empresa").empty();
                        $("#modal_footer_Empresa").append(
                            //'<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>' +
                            //' Cancelar' +
                            //'</button>' +
                            '<button type="submit" class="btn btn-success btn-pill"><span class="ti-reload"></span>' +
                            ' Generar Documentación' +
                            '</button>'
                        );
                        //$("#pro_porIva").number(true, 2);
                        //$("#pro_porIca").number(true, 2);
                        //$("#pro_porCom").number(true, 2);
                        //$("#modal-Empresa").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información del empresa',
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
     * getPreciosEmpresaById: Método para consultar la 
     * información de un empresa
     * @param type $id: llave primaria de la tabla empresas
     */
    async getPreciosEmpresaById(id) {
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
                data: { funcion: 3, preVen_IdEmpresa: id },
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
                        //    text: 'No se pudo consultar la información del empresa',
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
     * stoks del empresa
     * @param type $id: llave primaria de la tabla empresas
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
                    url: '../business/controller/class.empresa.php',
                    data: { funcion: 7, id_Empresa: id },
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
     * empresa enviado por parametro 
     * @param type $id: llave primaria de la tabla empresas
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
                data: { funcion: 1, precioNeto: precioUno, idEmpresa: id, idTarifa: 1},
                dataType: "json",
                type: "POST",
                success: function(arr) {
    
                    if (arr.ok == 1) {
                        $("#formCrearPrecio").trigger("reset");
                        $("#modal-Precio").modal('hide');
                        prod.getEmpresas();
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
                data: { funcion: 1, precioNeto: precioDos, idEmpresa: id, idTarifa: 2},
                dataType: "json",
                type: "POST",
                success: function(arr) {
    
                    if (arr.ok == 1) {
                        $("#formCrearPrecio").trigger("reset");
                        $("#modal-Precios").modal('hide');
                        prod.getEmpresas();
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
     * postEmpresa: Método para crear 
     * empresas
     */
    postEmpresa() {


        swal({
            title: 'Documentos Generados',
            text: '',
            type: 'question',
            showCancelButton: true,
            allowOutsideClick: false,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Descargar'
        }).then((result) => {
            if (result.value) {
                window.open('../extensiones/word.php', '_blank');
                
            }
        })


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

        var observaciones = 'Creación Empresa';

/*
        $.ajax({
            url: '../business/controller/class.empresa.php',
            data: { funcion: 1, codigo: cod, nombre: nombre, codBarras: codBarras, 
                    tipo: tipo, unidad: unidad, cantidadMed: cantidadMed, UsaStoks: UsaStoks, 
                    IdImpuesto: IdImpuesto, Categoria: Categoria, SubCategoria: SubCategoria, 
                    IdMarca: IdMarca, IdProveedor: IdProveedor, pro_PrecioVenta: pro_PrecioVenta},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                var detallesNota = [];

                detallesNota.push({ "detkar_IdEmpresa":arr.id , "detkar_Cantidad": pro_StockInicial, 
                "detkar_CostoUnitario": pro_costo, "detkar_IdBodega": 1});
                
                var det = JSON.stringify(detallesNota);

                if (arr.ok == 1) {

                    $.ajax({
                        url: '../business/controller/class.nota.php',
                        data: { funcion: 1, kar_Tipo: 1, Observaciones: observaciones, detallesNota: det },
                        type: 'POST',
                        dataType: 'json',
                        success: function(arr) {
        
                            if (arr.ok == 1) {

                                $("#formCrearEmpresa").trigger("reset");
                                $("#modal-Empresa").modal('hide');
                                prod.getEmpresas();
                                swal({
                                    type: 'success',
                                    title: 'Empresa creado',
                                    text: 'Empresa creado exitosamente',
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
                                    text: "Uno o más empresas relacionados en la nota no tiene existencias Iniciales.",
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

        */
    }

    /**
     * cambiarEstado: Método para cambiar el 
     * estado de los empresas
     * @param type $id_pro: llave primaria de la tabla empresas
     * @param type $estado: estado actual del empresa
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
                var title = "¿Está seguro de inactivar el empresa?";
                var subtitle = "Una vez inactivado, no podrá ser utilizado";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar el empresa?";
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
                        url: '../business/controller/class.empresa.php',
                        data: { funcion: 4, id: id_pro, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);

                            if (arr.ok == 1) {
                                prod.getEmpresas();
                                swal({
                                    type: 'success',
                                    title: 'Empresa actualizado',
                                    text: 'Uusario actualizado exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el empresa',
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
     * cambiarEstado: Método para Editar el empresa
     * @param type $id: llave primaria de la tabla empresas
     */
    editEmpresa(id) {
        /*   $('#loading').show();
          $('#wrapper').addClass('body-load'); */

          swal({
            title: 'Documentos Generados',
            text: '',
            type: 'question',
            showCancelButton: true,
            allowOutsideClick: false,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Descargar'
        }).then((result) => {
            if (result.value) {
                var ventana = window.open('../extensiones/word.php');
//                ventana.window.close();                
            }
        })

/*        
        var Nombre = $("#emp_Nombre").val();
        var NombreComercial = $("#emp_NombreComercial").val();
        var Nit = $("#emp_Nit").val();
        var IdDepartamento = $("#emp_IdDepartamento").val();
        var IdMunicipio = $("#emp_IdMunicipio").val();
        var Email = $("#emp_Email").val();
        var SitioWeb = $("#emp_SitioWeb").val();
        var TipoImpresora = $("#emp_TipoImpresora").val();
        var TipoPantalla = $("#emp_TipoPantalla").val();
        var TextoFactura = $("#emp_TextoFactura").val();

        var formData = new FormData(document.getElementById("formCrearEmpresa"));

       // AJAX PARA ALMACENAR LA IMAGEN EN EL SERVIDOR

       $.ajax({
        url: '../business/controller/class.guardarArchivo.php',
        data: { formData },
        type: "post",
        dataType: "html",
        data: formData,
        cache: false,
        contentType: false,
        processData: false,
        success: function(arr) {
            console.log('imagen', arr);

            if (arr == 'error') {   
                swal({
                    type: 'error',
                    title: 'La extensión o el tamaño de los archivos no es correcta',
                    text: 'Se permiten archivos .jpeg y de 200 kb máximo.',
                });
            } else { 
                
            // Ajax para Editar la empresa

                $.ajax({
                    url: '../business/controller/class.empresa.php',
                    data: { funcion: 2, id: id, Nombre: Nombre, NombreComercial: NombreComercial, Nit: Nit, 
                        IdDepartamento: IdDepartamento, IdMunicipio: IdMunicipio, Email: Email, SitioWeb: SitioWeb,
                        urlSoporteLogo: arr, TipoImpresora: TipoImpresora, TipoPantalla: TipoPantalla, TextoFactura: TextoFactura},
                    dataType: "json",
                    type: "POST",
                    success: function(arr) {
                        console.log('roles', arr);

                        if (arr.ok == 1) {
                            //$("#formCrearEmpresa").trigger("reset");
                            //$("#modal-Empresa").modal('hide');
                            //prod.getEmpresas();
                            swal({
                                type: 'success',
                                title: 'Empresa actualizado',
                                text: 'Empresa actualizado exitosamente',
                            });

                            window.location.reload()
                            
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
                                text: 'No se pudo actualizar el empresa',
                            });
                        }
                        /*  $("#estado").append('<option value="">Selecione una opción</option>');
                        arrayDocs = arr;
                        $.each(arr, function (k, v){
                            $("#estado").append('<option value="'+v['ESTDOC_Id']+'">'+v['ESTDOC_Nombre']+'</option>');
                        });  
                    },
                    error: function(XMLHttpRequest, textStatus, errorThrown) {
                        console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                        //location.href = "../../login.html";
                    }
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
     * cambiarEstado: Método para Editar el empresa
     * @param type $id: llave primaria de la tabla empresas
     */
    editCajas(id) {
        /*   $('#loading').show();
          $('#wrapper').addClass('body-load'); */

        var Nombre = $("#seemca_Nombre").val();
        var Serial = $("#seemca_Serial").val();
        var CodigoCaja = $("#seemca_CodigoCaja").val();
        
        var IdSedeEmpresa = $("#seemca_IdSedeEmpresa").val();
        var IdResolucion = $("#seemca_IdResolucion").val();
        var IdResolucionRemi = $("#seemca_IdResolucionRemi").val();

        $.ajax({
            url: '../business/controller/class.sedesEmpresaCajas.php',
            data: { funcion: 2, id: id, Nombre: Nombre, Serial: Serial, CodigoCaja: CodigoCaja, IdSedeEmpresa: IdSedeEmpresa,
                IdResolucion: IdResolucion, IdResolucionRemi: IdResolucionRemi},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);

                if (arr.ok == 1) {

                    $("#formCrearEditarCajas").trigger("reset");
                    $("#modal-VerCajas").modal('hide');

                    swal({
                        type: 'success',
                        title: 'Caja Actualizado',
                        text: 'Caja Actualizado exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',                        
                        title: 'Nombre Duplicado',
                        text: arr.mensaje,
                    });
                } else if (arr.ok == 3) {
                    swal({
                        type: 'warning',
                        title: 'Resolución POS Asociada a otra Caja',
                        text: arr.mensaje,
                    });
                }else if (arr.ok == 4) {
                    swal({
                        type: 'warning',
                        title: 'Resolución REMISIÓN Asociada a otra Caja',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar el Caja',
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
     * getIdDepartamentos: Método para consutlar los 
     * departamentos
     */
      getIdDepartamentos() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $("#emp_IdDepartamento").empty();        
        $("#emp_IdDepartamento").append('<option value="">Seleccione un Departamento</option>');

        $.ajax({
            url: '../business/controller/class.divipola.php',
            data: { funcion: 3, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {

                    $.each(arr.datos, function(k, v) {
                        $("#emp_IdDepartamento").append('<option value="' + v['dep_Id'] + '">' + v['dep_Nombre'] + '</option>');
                    });

                } else {

                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }
    

        /**
     * getIdDepartamentos: Método para consutlar los 
     * departamentos
     */
        getIdDepartamentosSedes() {
            /* $('#loading').show();
             $('#wrapper').addClass('body-load');  */
            
            $("#seem_IdDepartamento").empty();            
            $("#seem_IdDepartamento").append('<option value="">Seleccione un Departamento</option>');
            $.ajax({
                url: '../business/controller/class.divipola.php',
                data: { funcion: 3, estado: 1 },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    console.log('arr', arr)
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
    
                        $.each(arr.datos, function(k, v) {
                            $("#seem_IdDepartamento").append('<option value="' + v['dep_Id'] + '">' + v['dep_Nombre'] + '</option>');
                        });
    
                    } else {
    
                    }
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                    //location.href = "../../login.html";
                }
            });
        }
    
        

    /**
     * getIdMunicipios: Método para consutlar los 
     * municipios
     */
    getIdMunicipios() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
         var id_depar = $("#emp_IdDepartamento").val();

        $("#emp_IdMunicipio").empty();
        $("#emp_IdMunicipio").append('<option value="">Seleccione un Municipio</option>');
        $.ajax({
            url: '../business/controller/class.divipola.php',
            data: { funcion: 4, estado: 1, departamento_id :id_depar },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {

                    $.each(arr.datos, function(k, v) {
                        $("#emp_IdMunicipio").append('<option value="' + v['mun_Id'] + '">' + v['mun_Nombre'] + '</option>');
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
     * getIdMunicipiosSedes: Método para consutlar los 
     * municipios
     */
     getIdMunicipiosSedes() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
         var id_depar = $("#seem_IdDepartamento").val();

        $("#seem_IdMunicipio").empty();
        
        $("#seem_IdMunicipio").append('<option value="">Seleccione un Municipio</option>');
        $.ajax({
            url: '../business/controller/class.divipola.php',
            data: { funcion: 4, estado: 1, departamento_id :id_depar },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {

                    $.each(arr.datos, function(k, v) {
                        $("#seem_IdMunicipio").append('<option value="' + v['mun_Id'] + '">' + v['mun_Nombre'] + '</option>');
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
     * getIdEmpresas: Método para consutlar los 
     * empresas
     */
    getIdEmpresas() {
        /* $('#loading').show();
        $('#wrapper').addClass('body-load');  */

        $("#seem_IdEmpresa").empty();
        $("#seem_IdEmpresa").append('<option value="">Seleccione un Empresa</option>');
        $.ajax({
            url: '../business/controller/class.empresa.php',
            data: { funcion: 3},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                /*$('#loading').hide();
                $('#wrapper').removeClass('body-load');*/

                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#seem_IdEmpresa").append('<option value="' + v['emp_Id'] + '">' + v['emp_Nombre'] + '</option>');
                    });

                } else {

                }
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
                $("#seem_IdBodega").empty();
                $("#seem_IdBodega").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#seem_IdBodega").append('<option value="' + v['bod_Id'] + '">' + v['bod_Nombre'] + '</option>');
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
     * getIdResolucion: Método para consultar las resolucion pos
     */
     getIdResolucion() {
         
            $.ajax({
                url: '../business/controller/class.resolucion.php',
                data: { funcion: 3, tipo_Documento: 2 },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    console.log('arr ', arr);
                    $("#seemca_IdResolucion").empty();
                    $("#seemca_IdResolucion").append('<option value="">Seleccion una opción</option>');
                    $("#seemca_IdResolucion").append('<option value="0">No Aplica</option>');
                    if (arr.ok == 1) {
                        $.each(arr.datos, function(k, v) {
                            $("#seemca_IdResolucion").append('<option value="' + v['reso_Id'] + '">N°:' + v['reso_Numero'] + '-Prefijo: ' + v['reso_Prefijo'] + '</option>');
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
     * getIdResolucionRemi: Método para consultar las resolucion REMI
     */
     getIdResolucionRemi() {
         
        $.ajax({
            url: '../business/controller/class.resolucion.php',
            data: { funcion: 3, tipo_Documento: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#seemca_IdResolucionRemi").empty();
                $("#seemca_IdResolucionRemi").append('<option value="">Seleccion una opción</option>');
                $("#seemca_IdResolucionRemi").append('<option value="0">No Aplica</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#seemca_IdResolucionRemi").append('<option value="' + v['reso_Id'] + '">Prefijo: ' + v['reso_Prefijo'] + '</option>');
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
     * EmpresaActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    EmpresaActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DPredial").addClass('expand');
        $("#SPredial").addClass('active');
        $("#SPredial").addClass('show');
        $("#SubMenuGenepredial").addClass('active');

    }

}

const prod = new Empresa();

prod.getIdEmpresas();
prod.getBodega();
prod.getEmpresaById(1);
prod.getIdDepartamentos();
prod.getIdDepartamentosSedes();
prod.getIdMunicipios();
prod.getIdMunicipiosSedes();

prod.getIdResolucion();
prod.getIdResolucionRemi();

prod.getSedesEmpresas();
prod.EmpresaActivo();