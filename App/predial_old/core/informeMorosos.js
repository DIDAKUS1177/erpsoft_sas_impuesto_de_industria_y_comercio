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

   const opciones = await nota.obtenerOpciones();
        const inputOptions = opciones.datos.reduce((acc, item) => {
            acc[item.usu_Id] = item.usu_Nombre;
            return acc;
        }, {});
      
        const result = await swal({
            title: 'Transladar a Investigación',
            text: 'Proceso de Fiscalización',
            input: 'select',
            inputOptions: inputOptions,
            inputPlaceholder: 'Selecciona una opción',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: "Sí, Investigar",
            cancelButtonText: 'Cancelar'
        });
        
        const selectedOption = result.value;

        if (!selectedOption) {

            // Muestra una alerta si no se seleccionó ninguna opción
            swal({
                type: 'error',
                title: 'Selección obligatoria',
                text: 'Debe seleccionar una opción para continuar.'
            });
        } else {
			$('#loading').show();
			$('#wrapper').addClass('body-load');
                $.each(detallesNota, function(k, v) {
                    if(document.getElementById(v['codigoPredio']).checked==true){
                        contador = 1;
                    }
                });

                $.each(detallesNota, function(k, v) {
                    if(document.getElementById(v['codigoPredio']).checked==true){
						if(v['estadoMoroso'] >= 1){
								$('#loading').hide();
								$('#wrapper').removeClass('body-load');
							 swal({
                                type: 'error',
                                title: 'Predios con Proceso',
                                text: 'Predios ya tienen un proceso Activo.',
                            });
						}else{
								$('#loading').hide();
								$('#wrapper').removeClass('body-load');
							nota.translado( v['idPredio'], v['anio'],selectedOption);
						}
                        
                    }else{
                        if(contador <= 0){
							$('#loading').hide();
							$('#wrapper').removeClass('body-load');
                            swal({
                                type: 'error',
                                title: 'Predios No Seleccionados',
                                text: 'Seleccionar al menos 1 predio para realizar el proceso de Translado.',
                            });
                        }
                    }
                });

            }
    }
	
	
	obtenerOpciones() {
        return $.ajax({
            url: '../business/controller/class.usuarios.php', 
            data: { funcion: 3, usu_Rol: 2 }, 
            dataType: "json",
            type: "POST"
        });
    }


	
	/**
     * getNotas: Método para crear los Tranaladar predio pediodo a Investigación 
    **/
    translado(idPredio, anio, idUsuario) {

        $.ajax({
            url: '../business/controller/class.predialGestion.php',
            data: { funcion: 6, idPredio: idPredio, anio: anio, estado: 1, idUsuario: idUsuario},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('not ', arr);

                if (arr.ok == 1) {
                    swal({
                        type: 'success',
                        title: 'Translado Exitoso',
                        text: 'Predios Transladado a Investigación',
                    });
					nota.getNotas();
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo Transladar el predio.',
                    });
                }

            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
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
                        title: 'Translado Exitoso',
                        text: 'Predios Transladado a Investigación',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo Transladar el predio.',
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
                "anio": not.anio,
				"estadoMoroso": not.estadoMoroso
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
			
			if(not.estadoMoroso == 0){
                var estadomoro = 'Sin Proceso';
            }else{
                var estadomoro = 'Con Proceso';
            }
			
     
            $('#bodyNotasRegistradas').append(
                '<tr>' +
                '<td>' +
                '<input type="checkbox" id="'+codigo_predio+'" )"/>' +
                '</td>' +
                '<td>' +
                codigo_predio +
                '</td>' +
                '<td>' +
                not.nombrePropietario +
                '</td>' +
                '<td>' +
                not.identificacionPropietario +
                '</td>' +
                '<td>' +
                not.direccionPropietario +
                '</td>' +
                '<td>' +
                ultimo_anio_pago + 
                '</td>' +
                '<td>' +
                estadomoro + 
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

        console.log('fecha', fecha);
        console.log('fechaFinal', fechaFinal);
        
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
        $("#SubMenuLiquidacionListado").addClass('active');
    }
   
}

const nota = new Nota();

nota.NotaActivo();
nota.getAños();
nota.getNotas();
