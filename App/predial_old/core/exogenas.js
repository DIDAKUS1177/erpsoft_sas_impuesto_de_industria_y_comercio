// Declaración de variables
'use strict';

var idRol = sessionStorage.getItem('id_Rol');
console.log('idRol ', sessionStorage)
class Bodega {

    constructor() {}

    /**
     * crearBodega: Método para abrir modal de creación
     */
    async crearBodega() {
        var permiso = await _permisos.getPermisos(idRol, 311);

        if (permiso.ok != 1) {

            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                      'para obtenerlos comuniquese con el admininstrador del sistema',
            });

        } else {

            $("#txtBodega").val('');
            $("#pro_IdTipo").val('');
            $("#formBodega").attr('action', 'javascript:bod.postBodega();');
            $("#modal_footerBodega").empty();
            $("#modal_footerBodega").append(
                '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                '<span class="ti-close"></span> Cancelar' +
                '</button>' +
                '<button type="submit" class="btn btn-success btn-pill">' +
                '<span class="ti-plus"></span> Crear' +
                '</button>'
            );

            $("#modal-Bodega").modal('show');
        }
    }

    async xlsrearProducto() {

        $("#archivo_excel").val('');   

        $("#formCrearProductoXml").attr('action', 'javascript:bod.postXmlProducto()');
        $("#modal_footer-xml").empty();
        $("#modal_footer-xml").append(
            '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>' +
            ' Cancelar' +
            '</button>' +
            '<button type="submit" class="btn btn-success btn-pill"><span class="ti-plus"></span>' +
            ' Crear' +
            '</button>'
        );
        $('#modal-ProductoXml').modal({backdrop: 'static', keyboard: false});
        $("#modal-ProductoXml").modal('show');
    
    }

    /**
     * postXmlProducto: Método para crear 
     * productos por xml
     */
    postXmlProducto() {
     
     

        // Crear un objeto FormData para enviar el archivo
        // Obtén el formulario como elemento DOM
        var formElement = document.getElementById('formCrearProductoXml');

        // Crea FormData a partir del form: recoge todos los inputs, selects y textareas con name=""
        var formData = new FormData(formElement);

        //var formData = new FormData();
        formData.append('archivo_excel', $('#formCrearProductoXml input[name="archivo_excel"]')[0].files[0]); // Obtiene el archivo seleccionado
        formData.append('funcion', 9); // Obtiene el archivo seleccionado

        formData.append('kar_IdUsuario', sessionStorage.getItem('id_Usuario'));
        
        console.log('formData', formData);

        var input = document.getElementById('archivo_excel');
        var file = input.files[0];
        if (file) {
            var ext  = file.name.split('.').pop().toLowerCase();
            var mime = file.type;
            var size = file.size;                     // en bytes
            var maxSize = 5 * 1024 * 1024;            // 5 MB en bytes

            var allowedExt = ['xls','xlsx'];
            var allowedMime = [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ];

            if (allowedExt.indexOf(ext) === -1 || allowedMime.indexOf(mime) === -1) {
                alert('Solo se permiten archivos Excel (.xls, .xlsx).');
                return; // detiene el envío
            }

            if (size > maxSize) {
                alert('El archivo no puede pesar más de 5 MB.');
                return; // detiene el envío
            }

            // aquí sigue tu lógica de envío...
        }

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        $.ajax({
            url: '../business/controller/class.exogenas.php',
            data: formData,
            //dataType: "json",
            type: "POST",
            processData: false, // Evita que jQuery procese los datos
            contentType: false, // Evita que jQuery establezca el content-type
            success: function(arr) {

                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                
                if (arr.ok == 1) {

                    swal({
                        type: 'success',
                        title: 'Datos procesados',
                        text: 'El Formato fue cargado Exitosamente.',
                    });

                    $("#formCrearProductoXml").trigger("reset");
                    $("#modal-ProductoXml").modal('hide');
                    bod.getBodega();
                 
                } else {
                    swal({
                        type: 'error',
                        title: ''+arr.datos,
                        text: ''+arr.mensaje ,
                    });
                 
                }
                
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });       
    }

    

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de bodegas 
     * @param type $arrFilter: Listado de obejtos de tipo bodega
     */
    draw_table_documents(arrFilter) {

        $("#tblBodega").DataTable().destroy();
        $("#tbodyBodega").empty();
        for (let bod of arrFilter) {
            if (bod.bod_Estado == 1) {
                var estado = "PFE1";
                var documento = '<a href="../exogenas_formatos/PFE1.xlsx" target="_blank"> <i class="dw dw-download"></i> Descargar </a>';
            } else if (bod.bod_Estado == 2){
                var estado = "PFE2";
                var documento = '<a href="../exogenas_formatos/PFE2.xlsx" target="_blank"> <i class="dw dw-download"></i>Descargar </a>';
            }else if (bod.bod_Estado == 3){
                var estado = "PFE3";
                var documento = '<a href="../exogenas_formatos/PFE3.xlsx" target="_blank"> <i class="dw dw-download"></i>Descargar </a>';
            }else if (bod.bod_Estado == 4){
                var estado = "PFE4";
                var documento = '<a href="../exogenas_formatos/PFE4.xlsx" target="_blank"><i class="dw dw-download"></i> Descargar </a>';
            }else if (bod.bod_Estado == 5){
                var estado = "PFE5";
                var documento = '<a href="../exogenas_formatos/PFE5.xlsx" target="_blank"> <i class="dw dw-download"></i>Descargar </a>';
            }


            $('#tbodyBodega').append(
                '<tr>' +
                '<td>' +
                estado +
                '</td>' +
                '<td align="center">' +
                bod.bod_Nombre +
                '</td>' +
                '<td align="center">' +
                documento +
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
            columnDefs: [
                { targets: "datatable-nosort", orderable: false,},
                { "width": "5%", "targets": 0 },
                { "width": "8%", "targets": 1 },
                { "width": "5%", "targets": 2 }
            ],
            aaSorting: [
                [0, "asc"]
            ],
            "lengthMenu": [
                [10, 25, 50, -1],
                [10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'Roles registrados',
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
     * getBodega: Método para consultar las
     * bodegas
     */
    getBodega() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $.ajax({
            url: '../business/controller/class.bodega.php',
            data: { funcion: 3 },
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
                        text: 'No se pudo consultar las bodegas',
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
     * getBodegaById: Método para consultar la 
     * información de una bodega
     * @param type $id: llave primaria de la tabla bodega
     */
    async getBodegaById(id) {
        var permiso = await _permisos.getPermisos(idRol, 312);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.bodega.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {

                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#txtBodega").val(datos.bod_Nombre);
                            $("#pro_IdTipo").val(datos.bod_IdTipo);
                        }

                        $("#formBodega").attr('action', 'javascript:bod.putBodega(' + id + ');');
                        $("#modal_footerBodega").empty();
                        $("#modal_footerBodega").append(
                            '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                            '<span class="ti-close"></span> Cancelar' +
                            '</button>' +
                            '<button type="submit" class="btn btn-success btn-pill">' +
                            '<span class="ti-reload"></span> Actualizar' +
                            '</button>'
                        );

                        $("#modal-Bodega").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información de la bodega',
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
     * putBodega: Método para actualizar la 
     * información de una bodega
     * @param type $id: llave primaria de la tabla bodega
     */
    putBodega(id) {
        var nombre = $("#txtBodega").val();
        var tipo = $("#pro_IdTipo").val();
        $.ajax({
            url: '../business/controller/class.bodega.php',
            data: { funcion: 2, nombre: nombre, id: id , tipo: tipo},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                /*  $('#loading').hide();
                 $('#wrapper').removeClass('body-load'); */
                if (arr.ok == 1) {

                    $("#modal-Bodega").modal('hide');
                    bod.getBodega();
                    swal({
                        type: 'success',
                        title: 'Bodega actualizada',
                        text: 'Bodega actualizada exitosamente',
                    });

                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Bodega duplicada',
                        text: 'Ya existe una odega con el mismo nombre',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la bodega',
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
     * postBodega: Método para crear bodegas
     */
    postBodega() {
        /*  $('#loading').show();
         $('#wrapper').addClass('body-load'); */

        var nombre = $("#txtBodega").val();
        var tipo = $("#pro_IdTipo").val();

        $.ajax({
            url: '../business/controller/class.bodega.php',
            data: { funcion: 1, nombre: nombre, tipo: tipo },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                /*  $('#loading').hide();
                 $('#wrapper').removeClass('body-load'); */
                if (arr.ok == 1) {

                    $("#modal-Bodega").modal('hide');
                    swal({
                        type: 'success',
                        title: 'Bodega creada',
                        text: 'Bodega creada exitosamente',
                    });
                    bod.getBodega();
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Bodega duplicada',
                        text: 'Ya existe una bodega con el mismo nombre',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la  bodega',
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
     * de las bodegas
     * @param type $id_bodega: llave primaria de la tabla bodega
     * @param type $estado: estado actual de la bodega
     */
    async putEstados(id_bodega, estado) {
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
                var title = "¿Está seguro de inactivar la bodega?";
                var subtitle = "Una vez inactivado la bodega, no podrá asignarla a un producto";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la bodega?";
                var subtitle = "Una vez activado la bodega, podrá ser asignarla a un producto";
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
                        url: '../business/controller/class.bodega.php',
                        data: { funcion: 4, id: id_bodega, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                bod.getBodega();
                                swal({
                                    type: 'success',
                                    title: 'Bodega actualizada',
                                    text: 'Bodega actualizada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar la bodega',
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

    
    getBodegas() {

       $.ajax({
           url: '../business/controller/class.bodega.php',
           data: { funcion: 3 , tipo: 1},
           dataType: "json",
           type: "POST",
           success: function(arr) {
               console.log('arr ', arr);
               $("#pro_IdBodega").empty();
               $("#detkar_TipoDocumento").empty();
               if (arr.ok == 1) {
                   $.each(arr.datos, function(k, v) {
                       $("#pro_IdBodega").append('<option value="' + v['bod_Id'] + '">' + v['bod_Nombre'] + '</option>');
                       $("#detkar_TipoDocumento").append('<option value="' + v['bod_Id'] + '">' + v['bod_Nombre'] + '</option>');
                   });
               }
           },
           error: function(XMLHttpRequest, textStatus, errorThrown) {
               console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
           }
       });
   }

    /**
     * BodegaActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    BodegaActivo() {
        $(".Menuactivo").removeClass('expand');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');
        
        $("#DExogenas").addClass('expand active');
        $("#DExogenas").addClass('active');
        $("#SubExogenas").addClass('show');
        $("#SubMenuFormatos").addClass('active');
    }

}
const bod = new Bodega();



bod.getBodega();
bod.BodegaActivo();
bod.getBodegas();