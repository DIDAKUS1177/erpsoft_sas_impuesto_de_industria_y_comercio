// Declaración de variables
'use strict';

var idRol = sessionStorage.getItem('id_Rol');
console.log('idRol ', sessionStorage)
class Bodega {

    constructor() {}

  
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
     
        let tipo = $('#detkar_TipoDocumento').val();

        if (!tipo) {
            alert('No hay formatos disponibles para este año');
            return;
        }

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

            // var allowedExt = ['xlsm'];
            var allowedExt = ['xlsm', 'xml'];

            var allowedMime = [
                'application/vnd.ms-excel.sheet.macroEnabled.12', // 🔥 xlsm
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ];

            if (allowedExt.indexOf(ext) === -1) {
                // alert('Solo se permiten archivos Excel (.xlsm).');
                alert('Solo se permiten archivos (.xlsm o .xml).');
                return;
            }

            // Validación opcional (no bloqueante)
            if (mime && !mime.includes('excel')) {
                console.warn('MIME no reconocido:', mime);
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
                var documento = '<a href="../exogenas_formatos/PFE1.xlsm" target="_blank"> <i class="dw dw-download"></i> Descargar </a>';
            } else if (bod.bod_Estado == 2){
                var estado = "PFE2";
                var documento = '<a href="../exogenas_formatos/PFE2.xlsm" target="_blank"> <i class="dw dw-download"></i>Descargar </a>';
            }else if (bod.bod_Estado == 3){
                var estado = "PFE3";
                var documento = '<a href="../exogenas_formatos/PFE3.xlsm" target="_blank"> <i class="dw dw-download"></i>Descargar </a>';
            }else if (bod.bod_Estado == 4){
                var estado = "PFE4";
                var documento = '<a href="../exogenas_formatos/PFE4.xlsm" target="_blank"><i class="dw dw-download"></i> Descargar </a>';
            }else if (bod.bod_Estado == 5){
                var estado = "PFE5";
                var documento = '<a href="../exogenas_formatos/PFE5.xlsm" target="_blank"> <i class="dw dw-download"></i>Descargar </a>';
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

// Código para cargar el año actual y el año anterior en el select del modal de carga de productos por xml
document.addEventListener("DOMContentLoaded", function () {

    const select = document.getElementById("kar_AnioXml");
    const anioActual = new Date().getFullYear();
    const anioAnterior = anioActual - 1;

    const option = document.createElement("option");
    option.value = anioAnterior;
    option.text = anioAnterior;

    select.appendChild(option);

});

// Código para cargar los tipos de documentos disponibles según el año seleccionado en el modal 
// de carga de productos por xml
$('#kar_AnioXml').on('change', function () {
    let anio = $(this).val();

    if (!anio) return;

    $.ajax({
        url: '../business/controller/class.exogenas.php',
        type: 'POST',
        dataType: 'json',
        data: {
            funcion: 3,
            anio: anio,
            idUsuario: sessionStorage.getItem('id_Usuario')
        },
        success: function (arr) {

            let formatosCargados = [];

            if (arr.ok == 1) {
                // 🔴 SOLO tipos ya creados
                formatosCargados = arr.datos.map(x => parseInt(x.exo_IdTipoDocumento));
            }

            // 🔵 TODOS LOS FORMATOS POSIBLES
            let formatos = [
                {id:1, nombre:'PFE1 - INGRESOS ORDINARIOS POR VENTA DE BIENES Y SERVICIOS.'},
                {id:2, nombre:'PFE2 - INGRESOS ORDINARIOS POR PRESTACION DE SERVICIOS (TELEVISIÓN,INTERNET, TELEFONIA FIJA, <br>TELEFONIA MOVIL, NAVEGACIÓN MOVIL Y/O SERVICIO DE DATOS'},
                {id:3, nombre:'PFE3 - INGRESOS ORDINARIOS POR VENTA DE BIENES Y SERVICIOS <br>(COMERCIALIZACIÓN DEL SERVICIO DOMICILIARIO DE ENERGIA)'},
                {id:4, nombre:'PFE4 - INGRESOS OBTENIDOS FUERA DE PAIPA'},
                {id:5, nombre:'PFE5 - COMPRA DE BIENES Y SERVICIOS EN CONDICIÓN DE ADQUIRIENTE'}
            ];

            // 🔴 LIMPIAR SELECT
            $("#detkar_TipoDocumento").empty();
            $("#detkar_TipoDocumento").append('<option value="">Seleccione una opción</option>');

            // 🔵 AGREGAR SOLO LOS QUE NO EXISTEN
            formatos.forEach(f => {
                if (!formatosCargados.includes(f.id)) {
                    $("#detkar_TipoDocumento").append(
                        `<option value="${f.id}">${f.nombre}</option>`
                    );
                }
            });

            // 🔥 SI TODOS EXISTEN
            if (formatos.length === formatosCargados.length) {
               $("#detkar_TipoDocumento").append(
                    `<option value="" disabled selected>Todos los formatos ya fueron cargados</option>`
                );
            }
        }
    });
});
