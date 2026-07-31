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

        var NomUsu = sessionStorage.getItem('NomUsu');

        swal({
            title: 'Generar Documentos',
            text: 'Documentación a Generar para los 10 predios listados.',
            showCancelButton: true,
            allowOutsideClick: false,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Generar'
        }).then((result) => {
            if (result.value) {
                $.each(detallesNota, function(k, v) {
                    // window.open('../extensiones/notificacionPersonal.php?codigo='+v['codigoPredio']+'&fecha='+fecha+'&fechaFinal='+fechaFinal+'&NomUsu='+NomUsu+'', '_blank');
                    // window.open('../extensiones/minutaMandato.php?codigo='+v['codigoPredio']+'&fecha='+fecha+'&fechaFinal='+fechaFinal+'&NomUsu='+NomUsu+'', '_blank');
                    nota.postPrediosGenerados(v['codigoPredio'], fecha, fechaFinal);
                });
            }
        })

    }


    async crearDocumentosUno() {
       
            $("#formCrearDocumentos").attr('action', 'javascript:nota.postDocumentos()');
            $("#modal_footer").empty();
            $("#modal_footer").append(
                '<button type="submit" class="btn btn-success btn-pill"><span class="ti-plus"></span>' +
                ' Crear' +
                '</button>'
            );
            $("#modal-Documentos").modal('show');
    }


    /**
     * postEmpresa: Método para crear 
     * Documentos
     */
    postDocumentos() {

        var fecha = $("#eve_FechaEvento").val();
        var fechaFinal = $("#eve_FechaEventoFinal").val();
        var emp_NumPredioDos = $("#emp_NumPredioUno").val();
        var NomUsu = sessionStorage.getItem('NomUsu');

        var datos = JSON.stringify(detallesNota);
                 
        console.log('Predios a Listar:', datos);

        swal({
            title: 'Generar Documentación',
            text: 'Documentación a Generar del predio '+emp_NumPredioDos,
            showCancelButton: true,
            allowOutsideClick: false,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Generar'
        }).then((result) => {
            if (result.value) {
                // Almacena Predio generado
                nota.postPrediosGenerados(emp_NumPredioDos, fecha, fechaFinal);

                //window.open('../extensiones/notificacionPersonal.php?codigo='+emp_NumPredioDos+'&fecha='+fecha+'&fechaFinal='+fechaFinal+'&NomUsu='+NomUsu+'', '_blank');
                // window.open('../extensiones/minutaMandato.php?codigo='+emp_NumPredioDos+'&fecha='+fecha+'&fechaFinal='+fechaFinal+'&NomUsu='+NomUsu+'', '_blank');
                
            }
        })

        $("#formCrearDocumentos").trigger("reset");
        $("#modal-Documentos").modal('hide');
    }


    /**
     * getNotas: Método para crear los PrediosGenerados 
     */
    postPrediosGenerados(codigoPredio, fecha, fechaFinal) {

        var idUsuario = sessionStorage.getItem('id_Usuario');
        var NomUsu = sessionStorage.getItem('NomUsu');

        $.ajax({
            url: '../business/controller/class.prediosGenerados.php',
            data: { funcion: 1, idUsuario: idUsuario, codigoPredio: codigoPredio, 
                        fecha: fecha, fechaFinal: fechaFinal},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('not ', arr);

                if (arr.ok == 1) {
                    var id = arr.mensaje;
                    window.open('../extensiones/notificacionPersonal.php?codigo='+codigoPredio+'&fecha='+fecha+'&fechaFinal='+fechaFinal+'&NomUsu='+NomUsu+'&idResolucion='+id+'', '_blank');
                    window.open('../extensiones/minutaMandato.php?codigo='+codigoPredio+'&fecha='+fecha+'&fechaFinal='+fechaFinal+'&NomUsu='+NomUsu+'&idResolucion='+id+'', '_blank');
                    swal({
                        type: 'success',
                        title: 'Documento Generado',
                        text: 'Documento Generado Exitosamente',
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
                "codigoPredio": not.codigo_predio
            };
            i++;
            
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
            
 
            if(not.ultimo_anio_pago == null){
                var ultimo_anio_pago = 0;
            }else{
                var ultimo_anio_pago = not.ultimo_anio_pago;
            }
     
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
                ultimo_anio_pago + 
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
                [20, 30, 50, -1],
                [20, 30, 50, "All"]
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
        
        var fecha = $("#eve_FechaEvento").val();

        $.ajax({
            url: '../business/controller/class.predialGestion.php',
            data: { funcion: 1, FechaGestion: fecha},
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

        $("#DPredial").addClass('expand');
        $("#SPredial").addClass('active');
        $("#SPredial").addClass('show');
        $("#SubMenuLisPredial").addClass('active');
    }
   
}

const nota = new Nota();

nota.NotaActivo();
nota.getAños();
nota.getNotas();
