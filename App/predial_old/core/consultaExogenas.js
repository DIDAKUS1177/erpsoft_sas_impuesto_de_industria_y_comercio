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
     
        $('#loading').show();
        $('#wrapper').addClass('body-load');

            swal({
                type: 'success',
                title: 'Datos procesados',
                text: 'La Nota de Entrada han sido cargada Exitosamente.',
            });

            $("#formCrearProductoXml").trigger("reset");
            $("#modal-ProductoXml").modal('hide');

        $('#loading').hide();
        $('#wrapper').removeClass('body-load');
                 
       
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
            if (bod.exo_IdTipoDocumento == 1) {
                var estado = "PFE1";
                var documento = '<a href="../exogenas/'+bod.exo_IdUsuario+'/PFE1.xlsx" target="_blank"><i class="dw dw-download"></i> Descargar </a>';
            } else if (bod.exo_IdTipoDocumento == 2){
                var estado = "PFE2";
                var documento = '<a href="../exogenas/'+bod.exo_IdUsuario+'/PFE2.xlsx" target="_blank"><i class="dw dw-download"></i> Descargar </a>';
            }else if (bod.exo_IdTipoDocumento == 3){
                var estado = "PFE3";
                var documento = '<a href="../exogenas/'+bod.exo_IdUsuario+'/PFE3.xlsx" target="_blank"><i class="dw dw-download"></i> Descargar </a>';
            }else if (bod.exo_IdTipoDocumento == 4){
                var estado = "PFE4";
                var documento = '<a href="../exogenas/'+bod.exo_IdUsuario+'/PFE4.xlsx" target="_blank"><i class="dw dw-download"></i> Descargar </a>';
            }else if (bod.exo_IdTipoDocumento == 5){
                var estado = "PFE5";
                var documento = '<a href="../exogenas/'+bod.exo_IdUsuario+'/PFE5.xlsx" target="_blank"> <i class="dw dw-download"></i>Descargar </a>';
            }

            $('#tbodyBodega').append(
                '<tr>' +
                '<td>' +
                bod.exo_FechaCreacion +
                '</td>' +
                '<td>' +
                bod.strNombre +'<br> '+  'N°: '+ bod.strCedula +
                '</td>' +
                '<td align="center">' +
                estado + ' - ' + bod.exo_Anio +
                '</td>' +
                '<td align="center">' +
                documento +
                '</td>' +
                '<td align="center">' +
				
                   // '<button type="button" class="mb-1 btn btn-social-icon btn-danger" data-toggle="tooltip" title="Eliminar Exogena"  style="margin-right:5px" onclick="javascript:bod.putEstados('+bod.exo_Id+')">' +
                  //  '<i class="dw dw-delete-3"></i>' +
                    //'</button>' +
				
                '</td>' +

                '<td align="center">' +
                    '<button type="button" class="mb-1 btn btn-social-icon btn-info" data-toggle="tooltip" title="Acta Exogena"  style="margin-right:5px" onclick="javascript:bod.acta('+bod.exo_Id+','+"'"+bod.strNombre+"'"+','+bod.strCedula+', '+bod.exo_IdTipoDocumento+')">' + 
                    '<i class="dw dw-download"></i>' +
                    '</button>' +
                '</td>' +
                
                '</tr>'
            );
        }
        bod.init_table();
    }

    /**
     * acta: Método para cambiar el estados
     * de las bodegas
     * @param type $id_bodega: llave primaria de la tabla bodega
     * @param type $estado: estado actual de la bodega
     *  */
    async acta(idExogena, nombre, cedula, idTipo) {
              
            window.open('../extensiones/recibidoExogenas.php?idExogena='+idExogena+'&nombre='+nombre+'&cedula='+cedula+'&formato='+idTipo+'', '_blank');
            swal({
                type: 'success',
                title: 'Acta Generada',
                text: 'Acta Generada Exitosamente',
            });
         
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
                { "width": "10%", "targets": 0 },
                { "width": "20%", "targets": 1 },
                { "width": "20%", "targets": 2 }
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
                'emptyTable': 'Exogenas registrados',
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
    getExogenas() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */

        var idUsuario = sessionStorage.getItem('id_Usuario');

    if (idUsuario <= 3) {
                $.ajax({
            url: '../business/controller/class.exogenas.php',
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
    }else {
               $.ajax({
            url: '../business/controller/class.exogenas.php',
            data: { funcion: 3 , idUsuario: idUsuario},
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
                    bod.getExogenas();
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
                    bod.getExogenas();
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
    async putEstados(idExogena) {

/*
        var permiso = await _permisos.getPermisos(idRol, 313);
        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
*/
            var title = "¿Está seguro de eliminar Formato de EXOGENA?";
            var subtitle = "Una vez eliminado, no podrá recuperarce";
            var button = "Sí, inactivar";
             
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
                        url: '../business/controller/class.exogenas.php',
                        data: { funcion: 4, id: idExogena},
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                bod.getExogenas();
                                swal({
                                    type: 'success',
                                    title: 'Exogena Eliminada',
                                    text: 'Exogena Eliminada Exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar la Exogenas',
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
//        }
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
               $("#detkar_IdBodegaXml").empty();
               if (arr.ok == 1) {
                   $.each(arr.datos, function(k, v) {
                       $("#pro_IdBodega").append('<option value="' + v['bod_Id'] + '">' + v['bod_Nombre'] + '</option>');
                       $("#detkar_IdBodegaXml").append('<option value="' + v['bod_Id'] + '">' + v['bod_Nombre'] + '</option>');
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
        $("#SubVerFormatos").addClass('active');

    }

}
const bod = new Bodega();



bod.getExogenas();
bod.BodegaActivo();
bod.getBodegas();