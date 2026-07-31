var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
var detallesNota = [];
class Nota {

    constructor() {}

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de notas de entrada y salida 
     * @param type $arrFilter: Listado de objetos de tipo nota
     */
    draw_table_documents(arrFilter) {
        console.log('araar', arrFilter);

        $("#notasRegistradas").DataTable().destroy();
        $("#bodyNotasRegistradas").empty();

        for (let not of arrFilter) {
            
            if(not.codigo_predio == null){
                var codigo_predio= 0 ;
            }else{
                var codigo_predio = not.codigo_predio;
            }
    
            if(not.codigo_predio_anterior == null){
                var codigo_predio_anterior = 0;
            }else{
                var codigo_predio_anterior = not.codigo_predio_anterior;
            }

            var direccion = '"'+not.direccion+'"';

            var fecha = 2020;
            var fechaFinal = 2023;
            
            if(not.ultimo_anio_pago == null){
                var ultimo_anio_pago = 0;
            }else{
                var ultimo_anio_pago = not.ultimo_anio_pago;
            }
            // var notificacion = '<a href="../extensiones/notificacionPersonalGenerado.php?codigo='+codigo_predio+'&fecha='+fecha+'&fechaFinal='+fechaFinal+'" target="_blank" class="btn btn-success btn-pill"><span class="ti-plus"></span></a>' ;
            // var minuta = '<a href="../extensiones/minutaMandatoGenerado.php?codigo='+codigo_predio+'&fecha='+fecha+'&fechaFinal='+fechaFinal+'" target="_blank" class="btn btn-success btn-pill"><span class="ti-plus"></span></a>' ;
     
            var notificacion = '<a href="../extensiones/notificacionPersonalGenerado.php?codigo='+codigo_predio+'" target="_blank" class="btn btn-success btn-pill"><span class="ti-plus"></span></a>' ;
            var minuta = '<a href="../extensiones/minutaMandatoGenerado.php?codigo='+codigo_predio+'" target="_blank" class="btn btn-success btn-pill"><span class="ti-plus"></span></a>' ;

            $('#bodyNotasRegistradas').append(
                '<tr>' +
                '<td>' +
                codigo_predio +
                '</td>' +
				'<td>' +
                not.nombre +
                '</td>' +
                '<td>' +
                direccion +
                '</td>' +
				'<td>' +
                not.nom_usu_documentacion +
                '</td>' +
                '<td>' +
                notificacion +
                '</td>' +
                '<td>' +
                minuta +
                '</td>' +
                '</tr>'
            );
        }
        nota.init_table();
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
                [5, 10, 25, 50, -1],
                [5, 10, 25, 50, "All"]
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
     * getNotas: Método para consultar las notas 
     */
    getNotas() {

        var fechaAnio = $("#fechaAnio").val();
        var fechaMes = $("#fechaMes").val();
		var fechaDia = $("#fechaDia").val();

        $.ajax({
            url: '../business/controller/class.predialGestion.php',
            data: { funcion: 2, fechaMes: fechaMes, fechaAnio: fechaAnio, fechaDia: fechaDia},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('not ', arr);

                if (arr.ok == 1) {
                    $("#bodyDetallesNota").empty();
                    nota.draw_table_documents(arr.datos);
                } else {
                    $("#notasRegistradas").DataTable().destroy();
                    nota.init_table();
					
					  swal({
                        type: 'error',
                        title: 'Sin Documentos',
                        text: 'Año y mes sin Documentos generados',
                    }); 
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
     
    }

    NotaActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DPredial").addClass('expand');
        $("#SPredial").addClass('active');
        $("#SPredial").addClass('show');
        $("#SubMenuPreDoc").addClass('active');
    }
   
}

const nota = new Nota();

nota.NotaActivo();
nota.getNotas();
