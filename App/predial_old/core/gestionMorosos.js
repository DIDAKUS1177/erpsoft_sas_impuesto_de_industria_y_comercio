var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
var detallesNota = [];
class Nota {

    constructor() {}

      /**
     * crearEmpresa: Método para abrir modal de creación
     */
      async crearDocumentos() {
       
        var fecha = $("#eve_FechaEvento").val();
        var fechaFinal = $("#eve_FechaEventoFinal").val();
        var contador = 0;
		  
		  console.log('detalles', detallesNota);
		  
        swal({
            title: 'Generar Documentos',
            //text: 'Documentación a Generar para los 10 predios listados.',
            showCancelButton: true,
            allowOutsideClick: false,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Generar'
        }).then((result) => {
            if (result.value) {
				$.each(detallesNota, function(k, v) {
                    if(document.getElementById(v['codigoPredio']).checked){
                        contador = 1;
						console.log('si hay');
                    }
					console.log('no hay');
                });
				
				if(contador <= 0){
					swal({
						type: 'error',
						title: 'Predios NO seleccionados',
						text: 'Seleccionar al menos 1 predio para realizar el proceso.',
					});
				}else{
					$.each(detallesNota, function(k, v) {

						if(document.getElementById(v['codigoPredio']).checked){
							nota.postPrediosGenerados( v['idPredio'], v['codigoPredio'], v['anio'], v['nombre']);
							document.getElementById(v['codigoPredio']).checked=false;
						}
						
					});
				}				
				
            }
        })
    }


    /**
     * getNotas: Método para crear los PrediosGenerados 
    **/
    postPrediosGenerados(idPredio,codigoPredio, anio, nombre) {

        var idUsuario = sessionStorage.getItem('id_Usuario');

        $.ajax({
            url: '../business/controller/class.prediosGenerados.php',
            data: { funcion: 4, idUsuario: idUsuario, idPredio: idPredio,
                codigoPredio: codigoPredio, anio: anio},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('not ', arr);

                if (arr.ok == 1) {
                    window.open('../extensiones/liquidacionPredial.php?idPredio='+idPredio+'&codigo='+codigoPredio+'&nombre='+nombre+'&anio='+anio+'', '_blank');
                    //window.open('../extensiones/minutaMandato.php?codigo='+codigoPredio+'&fecha='+fecha+'&fechaFinal='+fechaFinal+'&NomUsu='+NomUsu+'&idResolucion='+id+'', '_blank');
                    swal({
                        type: 'success',
                        title: 'Documentos Generados',
                        text: 'Documentos Generados Exitosamente',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo Guardar el predio Generado',
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
     * de notas de entrada y salida 
     * @param type $arrFilter: Listado de objetos de tipo nota
     */
    draw_table_documents(arrFilter) {
        console.log('araar', arrFilter);

        $("#notasRegistradas").DataTable().destroy();
        $("#bodyNotasRegistradas").empty();
        var i=0;

        for (let not of arrFilter) {
            
            detallesNota[i]={ 
                "idPredio": not.id_predio,
                "codigoPredio": not.codigo,
                "nombre": not.nombrePropietario,
                "anio": not.anio
            };
            i++;
            
            if(not.codigo == null){
                var codigo_predio= 0 ;
            }else{
                var codigo_predio = not.codigo;
            }

            var direccion = '"'+not.direccionPropietario+'"';
            
            if(not.anio == null){
                var ultimo_anio_pago = 0;
            }else{
                var ultimo_anio_pago = not.anio;
            }
			
			if(not.estadoMoroso <= 0){
                var estadoMorosoo = 'Sin Documentación';
            }else{
                var estadoMorosoo = 'Con Documentación';
            }
     
     
            $('#bodyNotasRegistradas').append(
                '<tr>' +
                '<td>' +
				'<input type="checkbox" id="'+codigo_predio+'" value="first_checkbox" )"/>' +
                '</td>' +
                '<td>' +
                codigo_predio +
                '</td>' +
                '<td>' +
                not.nombrePropietario +
                '</td>' +
                '<td>' +
                direccion +
                '</td>' +
                '<td>' +
                ultimo_anio_pago + 
                '</td>' +
                '<td>' +
                estadoMorosoo + 
                '</td>' +
                '</tr>'
            );
        }
        nota.init_table();
    }


    listadoPredios(codigoEnviado){

        detallesNota.push({ "detkar_Bruto": bruto, "detkar_Impuesto":impuesto,
            "detkar_IdProducto": pro, "detkar_Cantidad": cant, 
            "detkar_Costo": cost, "detkar_CostoUnitario": costUni,
            "detkar_CostoText": costTex, "detkar_IdBodega": bod,
            "nomProducto": namePro, "nomBodega": nameBod, "idImpuesto": impu });
    
    }
    /**
     * init_table: Método para asignar la
     * propiedad DataTable() a la tabla de productos
     */
    init_table() {
        $('.data-table').DataTable({
            "scrollY":        "200px",
            "scrollCollapse": true,
            "paging":         false,
            searching: true,
            aaSorting: [
                [1, "asc"]
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
/*			
            scrollCollapse: true,
            autoWidth: false,
            responsive: true,
            columnDefs: [{
                targets: "datatable-nosort",
                orderable: false,
            }],
            "lengthMenu": [
                [10, 20, -1],
                [10, 20, "All"]
            ],		
			aaSorting: [
                [1, "asc"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'Datos Registrados',
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
*/		
    }

    /**
     * getNotas: Método para consultar las notas 
     */
    getNotas() {
        
        var fecha = $("#eve_FechaEvento").val();
        var fechaFinal = $("#eve_FechaEventoFinal").val();

        $.ajax({
            url: '../business/controller/class.predialGestion.php',
            data: { funcion: 3, fecha: fecha, fechaFinal: fechaFinal},
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
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
     
    }

    
    getAños() {
        $("#select_Cuentas").empty();
       
        var today = new Date();
        var year = today.getFullYear();

        for (let i = 1990; i <= year; i++) {
            $("#eve_FechaEvento").append('<option value="' + i + '">' + i + '</option>');    
            $("#eve_FechaEventoFinal").append('<option value="' + i + '">' + i + '</option>');    
          }

    }

    NotaActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DMorosos").addClass('expand');
        $("#SMorosos").addClass('active');
        $("#SMorosos").addClass('show');
        $("#SubMenuLiquidacion").addClass('active');
    }
   
}

const nota = new Nota();

nota.NotaActivo();
nota.getAños();
nota.getNotas();
