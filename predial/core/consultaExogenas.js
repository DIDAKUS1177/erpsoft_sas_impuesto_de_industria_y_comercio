// Declaración de variables
'use strict';

var idRol = sessionStorage.getItem('id_Rol');
console.log('idRol ', sessionStorage)
class Bodega {

    constructor() {}
    
    /**
     * draw_table_documents: Método para pintar la tabla 
     * de bodegas 
     * @param type $arrFilter: Listado de obejtos de tipo bodega
     */
      draw_table_documents(arrFilter) {

        $("#tblBodega").DataTable().destroy();
        $("#tbodyBodega").empty();
        let rutaExcel = '';
        let uniqueId = '';

        for (let bod of arrFilter) {
            let extension = (bod.exo_Anio == 2024) ? 'xlsx' : 'xlsm';

            let anioActual = new Date().getFullYear();
            let anioAnterior = anioActual - 2;
            let eliminar = '';

            if(bod.exo_Anio == anioAnterior){
                eliminar = '';
            }else {
                eliminar = '<button type="button" class="mb-1 btn btn-social-icon btn-danger" data-toggle="tooltip" title="Eliminar Exogena"  style="margin-right:5px" onclick="javascript:bod.putEstados('+bod.exo_Id+')"><i class="dw dw-delete-3"></i></button>';
            }

            let estado = "PFE" + bod.exo_IdTipoDocumento;

            rutaExcel = '../exogenas/' + bod.exo_IdUsuario + '/' + bod.exo_Anio + '/' + estado + '.' + extension;
            uniqueId  = bod.exo_Id + '_' + Math.random().toString(36).substring(7);

            var documento =
                '<span id="excel_'+uniqueId+'"><a href="'+rutaExcel+'" target="_blank"><i class="dw dw-download"></i> Excel </a></span><br>';
                
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
                    eliminar +
                '</td>' +

                '<td align="center">' +
                    '<button type="button" class="mb-1 btn btn-social-icon btn-info" data-toggle="tooltip" title="Acta Exogena"  style="margin-right:5px" onclick="javascript:bod.acta('+bod.exo_Id+')">' + 
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
    async acta(idExogena) {
              
            window.open('../extensiones/recibidoExogenas.php?idExogena='+idExogena+'', '_blank');
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
                'emptyTable': 'No existen Exogenas registrados',
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
         //$('#loading').show();
		
         $('#loading').removeAttr('hidden').show();
         $('#wrapper').addClass('body-load');  

        var idUsuario = sessionStorage.getItem('id_Usuario');

        let anio = $('#filtroAnio').val();

    if (idUsuario <= 3) {
                $.ajax({
            url: '../business/controller/class.exogenas.php',
            data: { 
                funcion: 3,
                anio: anio
            },
            dataType: "json",
            type: "POST",
            success: function(arr) {

                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
				
                if (arr.ok == 1) {
                    bod.draw_table_documents(arr.datos);
                } else {
                    $("#tblBodega").DataTable().destroy();

                    $('#tbodyBodega').empty();
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
				 $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }else {
               $.ajax({
            url: '../business/controller/class.exogenas.php',
            data: { funcion: 3 , idUsuario: idUsuario, anio: anio},
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
				   $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

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

$(document).ready(function () {

    // 🔥 CARGAR AÑOS DINÁMICOS
    let anioActual = new Date().getFullYear();

    for (let i = 2024; i <= anioActual; i++) {
        $('#filtroAnio').append(`<option value="${i}">${i}</option>`);
    }

    // 🔥 EVENTO FILTRO
    $('#filtroAnio').on('change', function(){
        bod.getExogenas();
    });

});