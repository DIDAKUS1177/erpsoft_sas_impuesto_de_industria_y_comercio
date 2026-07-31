var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
var idUsuario = sessionStorage.getItem('id_Usuario');
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

        swal({
            title: 'Generar Translado',
            // text: 'Documentación a Generar para los 10 predios listados.',
            showCancelButton: true,
            allowOutsideClick: false,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Translado'
        }).then((result) => {
            if (result.value) {

                $.each(detallesNota, function(k, v) {
                    if(document.getElementById(v['codigoPredio']).checked==true){
                        contador = 1;
                    }
                });

                $.each(detallesNota, function(k, v) {
                    if(document.getElementById(v['codigoPredio']).checked==true){

                        //debugger;
                        switch (v['estado']) {
                            case 1:
                                if(v['investigaVur'] != null){
                                    nota.translado( v['idPredio'], v['anio'], v['estado'], v['nombre'], v['codigoPredio']);
                                }else{
                                    swal({
                                        type: 'error',
                                        title: 'Predios Sin VUR',
                                        text: 'Cargar datos del Vur Obligatorio',
                                    });
                                }
                                break;
                            case 2:
                                if(v['facturaSiPago'] == 0){
                                    nota.translado( v['idPredio'], v['anio'], v['estado'], v['nombre'], v['codigoPredio']);
                                }else{
                                    swal({
                                        type: 'error',
                                        title: 'Predios Con Factura ya Pagadao',
                                        text: 'El predio ya se encuentra con Pago.',
                                    });
                                }
                                break;
                            case 3:
                                nota.translado( v['idPredio'], v['anio'], v['estado'], v['nombre'], v['codigoPredio']);
                                break;
                            case 4:
                                nota.translado( v['idPredio'], v['anio'], v['estado'], v['nombre'], v['codigoPredio']);
                                break;
                            default:
                                nota.translado( v['idPredio'], v['anio'], v['estado'], v['nombre'], v['codigoPredio']);
                        }

 
                    }else{
                        if(contador <= 0){
                            swal({
                                type: 'error',
                                title: 'Predios NO seleccionados',
                                text: 'Seleccionar al menos 1 predio para realizar el translado.',
                            });
                        }
                    }

                });
            }
        })
    }
    

    /**
     * crearEmpresa: Método para abrir modal de creación
     */
        async crearDocumentosAtras() {
            var contador = 0;
    
            swal({
                title: 'Devolver al Estado Anterior',
                showCancelButton: true,
                allowOutsideClick: false,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Translado'
            }).then((result) => {
                if (result.value) {
    
                    $.each(detallesNota, function(k, v) {
                        if(document.getElementById(v['codigoPredio']).checked==true){
                            contador = 1;
                        }
                    });
    
                    $.each(detallesNota, function(k, v) {
                        if(document.getElementById(v['codigoPredio']).checked==true){
    
                            switch (v['estado']) {
                                case 1:
                                    nota.transladoAtras( v['idPredio'], v['anio'], 0);
                                    break;
                                case 2:
                                    nota.transladoAtras( v['idPredio'], v['anio'], 1);
                                    break;
                                case 3:
                                    nota.transladoAtras( v['idPredio'], v['anio'], 2);
                                    break;
                                case 4:
                                    nota.transladoAtras( v['idPredio'], v['anio'], 3);
                                    break;
                                case 5:
                                    nota.transladoAtras( v['idPredio'], v['anio'], 4);
                                    break;
                                default:
                                    nota.transladoAtras( v['idPredio'], v['anio'], 0);
                            }
    
     
                        }else{
                            if(contador <= 0){
                                swal({
                                    type: 'error',
                                    title: 'Predios NO seleccionados',
                                    text: 'Seleccionar al menos 1 predio para realizar el translado.',
                                });
                            }
                        }
    
                    });
                }
            })
        }
	
	/**
    * getNotas: Método para crear los Tranaladar predio pediodo a Investigación 
    **/
    translado(idPredio, anio, estado, nombre, codigo) {
		
		var estadonew = estado+1;

        console.log('estadonew ', estadonew,'estado',estado);

        $.ajax({
            url: '../business/controller/class.predialGestion.php',
            data: { funcion: 4, idPredio: idPredio, anio: anio, estado: estadonew},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('not ', arr);
                if (arr.ok == 1) {
                    if(estadonew == 3){
                        // Metodo genera Factura
                        nota.postPrediosGenerados(idPredio, codigo, anio, nombre);
                    }else if(estadonew == 4){
                        // Metodo genera publicacion
                        nota.postPrediosGeneradosPublicados(idPredio, codigo, anio, nombre);
                    }else if(estadonew == 5){
                        // Metodo genera constancia
                        nota.postPrediosGeneradosConstancia(idPredio, codigo, anio, nombre) ;
                    }
                    
                    swal({
                        type: 'success',
                        title: 'Translado Exitoso',
                        text: 'Predios Transladado a Investigación',
                    });
					nota.getNotas(estado);
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
     * getNotas: Método para crear los Tranaladar predio pediodo a Investigación 
    **/
    transladoAtras(idPredio, anio, estado) {

        $.ajax({
            url: '../business/controller/class.predialGestion.php',
            data: { funcion: 4, idPredio: idPredio, anio: anio, estado: estado},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('not ', arr);
                if (arr.ok == 1) {
                    
                    swal({
                        type: 'success',
                        title: 'Translado Exitoso',
                        text: 'Predios Transladados',
                    });
                    nota.getNotas(estado);
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
	
    async crearDocumentosAutoArchivo() {
       
        var fecha = $("#eve_FechaEvento").val();
        var fechaFinal = $("#eve_FechaEventoFinal").val();
        var contador = 0;

        swal({
            title: 'Generar Translado de Auto Archivo',
            showCancelButton: true,
            allowOutsideClick: false,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Auto Archivo'
        }).then((result) => {
            if (result.value) {

                $.each(detallesNota, function(k, v) {
                    if(document.getElementById(v['codigoPredio']).checked==true){
                        contador = 1;
                    }
                });

                $.each(detallesNota, function(k, v) {
                    if(document.getElementById(v['codigoPredio']).checked==true){
                        nota.autoArchivop( v['idPredio'], v['anio']);
                        swal({
                            type: 'success',
                            title: 'Predios Auto Archivo',
                            text: 'Predios Auto Archivo Exitosamente.',
                        });
                    }else{
                        if(contador <= 0){
                            swal({
                                type: 'error',
                                title: 'Predios NO seleccionados',
                                text: 'Seleccionar al menos 1 predio para realizar el translado de Auto Archivo.',
                            });
                        }
                    }
                });
            }
        })
    }


    
	/**
     * autoArchivop: Método para crear los Tranaladar predio pediodo a autoArchivop 
    **/
    autoArchivop(idPredio,anio) {
		
        $.ajax({
            url: '../business/controller/class.predialGestion.php',
            data: { funcion: 4, idPredio: idPredio, anio: anio, estado: 6},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('not ', arr);
                if (arr.ok == 1) {
                    
                    swal({
                        type: 'success',
                        title: 'AutoArchivo Exitoso',
                        text: 'AutoArchivo Generado Exitosamente',
                    });
					nota.getNotas(1);
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo AutoArchivo el predio.',
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
            url: '../business/controller/class.predialGestion.php',
            data: { funcion: 4, idPredio: idPredio,
                estado: 3, anio: anio},
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
     * postPrediosGeneradosPublicados: Método para crear los postPrediosGeneradosPublicados 
    **/
    postPrediosGeneradosPublicados(idPredio,codigoPredio, anio, nombre) {

        //var idUsuario = sessionStorage.getItem('id_Usuario');

        $.ajax({
            url: '../business/controller/class.predialGestion.php',
            data: { funcion: 4, idPredio: idPredio,
                estado: 4, anio: anio},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('not ', arr);

                if (arr.ok == 1) {
                    window.open('../extensiones/publicacionPredial.php?idPredio='+idPredio+'&codigo='+codigoPredio+'&nombre='+nombre+'&anio='+anio+'', '_blank');
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
     * postPrediosGeneradosConstancia: Método para crear los postPrediosGeneradosConstancia 
    **/
    postPrediosGeneradosConstancia(idPredio,codigoPredio, anio, nombre) {

        //var idUsuario = sessionStorage.getItem('id_Usuario');

        $.ajax({
            url: '../business/controller/class.predialGestion.php',
            data: { funcion: 4, idPredio: idPredio,
                estado: 5, anio: anio},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('not ', arr);

                if (arr.ok == 1) {
                    window.open('../extensiones/constanciaPredial.php?idPredio='+idPredio+'&codigo='+codigoPredio+'&nombre='+nombre+'&anio='+anio+'', '_blank');
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
    draw_table_documents(arrFilter, estado) {
        console.log('araar', arrFilter);
		
        $("#notasRegistradas").DataTable().destroy();
        $("#bodyNotasRegistradas").empty();
        var i=0;
		detallesNota = [];
		
        for (let not of arrFilter) {
            
            detallesNota[i]={ 
                "idPredio": not.id_predio,
                "codigoPredio": not.codigo,
                "nombre": not.nombrePropietario,
                "anio": not.anio,
				"estado": not.estadoMoroso,
                "investigaVur": not.folioInvestigacion,
                "facturaSiPago": not.pagado
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
            // FECHA ASIGNACIÓN A INVESTIGACIÓN
            if(not.fechaInvestigacion == null){
                var fechaAsignacionMoroso = "Sin Fecha";
            }else{
                var soloFecha = new Date(not.fechaInvestigacion['date']);
                var fechaAsignacionMoroso = `${soloFecha.getFullYear()}-${(soloFecha.getMonth() + 1).toString().padStart(2, '0')}-${soloFecha.getDate().toString().padStart(2, '0')}`;
            }

            //FECHA DE ASIGNACIÓN A FACTURA
            if(not.fechaFactura == null){
                var fechaFacturaMoroso = "Sin Fecha";
            }else{
                var soloFechaFactura = new Date(not.fechaFactura['date']);
                var fechaFacturaMoroso = `${soloFechaFactura.getFullYear()}-${(soloFechaFactura.getMonth() + 1).toString().padStart(2, '0')}-${soloFechaFactura.getDate().toString().padStart(2, '0')}`;
            }

            //FECHA DE ASIGNACIÓN A PUBLICACIÓN
            if(not.fechaPublicacion == null){
                var fechaPublicacionMoroso = "Sin Fecha";
            }else{
                var soloFechaPublicacion = new Date(not.fechaPublicacion['date']);
                var fechaPublicacionMoroso = `${soloFechaPublicacion.getFullYear()}-${(soloFechaPublicacion.getMonth() + 1).toString().padStart(2, '0')}-${soloFechaPublicacion.getDate().toString().padStart(2, '0')}`;
            }

            //FECHA DE ASIGNACIÓN A CONSTANCIA
            if(not.fechaConstancia == null){
                var fechaConstanciaMoroso = "Sin Fecha";
            }else{
                var soloFechaConstancia = new Date(not.fechaConstancia['date']);
                var fechaConstanciaMoroso = `${soloFechaConstancia.getFullYear()}-${(soloFechaConstancia.getMonth() + 1).toString().padStart(2, '0')}-${soloFechaConstancia.getDate().toString().padStart(2, '0')}`;
            }

            //FECHA DE ASIGNACIÓN A MANDAMIENTO
            if(not.fechaMandamiento == null){
                var fechaMandamientoMoroso = "Sin Fecha";
            }else{
                var soloFechaMandamiento = new Date(not.fechaMandamiento['date']);
                var fechaMandamientoMoroso = `${soloFechaMandamiento.getFullYear()}-${(soloFechaMandamiento.getMonth() + 1).toString().padStart(2, '0')}-${soloFechaMandamiento.getDate().toString().padStart(2, '0')}`;
            }


            //FECHA DE ASIGNACIÓN A AUTOARCHIVO
            if(not.fechaAutoArchivo == null){
                var fechaAutoArchivoMoroso = "Sin Fecha";
            }else{
                var soloFechaAutoarchivo = new Date(not.fechaAutoArchivo['date']);
                var fechaAutoArchivoMoroso = `${soloFechaAutoarchivo.getFullYear()}-${(soloFechaAutoarchivo.getMonth() + 1).toString().padStart(2, '0')}-${soloFechaAutoarchivo.getDate().toString().padStart(2, '0')}`;
            }

            if(not.folioInvestigacion == null){
                var botonVur = '<button type="button" class="btn btn-social-icon btn-secondary " data-toggle="tooltip" title="Agregar VUR" style="margin-right:5px" onclick="javascript:nota.getPreciosProductoById( '+ not.id_predio+','+"'"+codigo_predio+"'"+','+not.anio+')"><i class="icon-copy dw dw-money-1"></i></button>';
            }else{
                var botonVur = '';
            }

			var propie = " ' "+not.nombrePropietario+" ' ";

            if( not.pagado != 0){
                var generarFac = '';
                var solofechaPago = new Date(not.fechaPago['date']);
                var estadoPagoFecha = 'Fecha de Pago: '+`${solofechaPago.getFullYear()}-${(solofechaPago.getMonth() + 1).toString().padStart(2, '0')}-${solofechaPago.getDate().toString().padStart(2, '0')}`;
            }else{
                var generarFac = '<button type="button" class="btn btn-social-icon btn-success " data-toggle="tooltip" title="Generar Factura"  style="margin-right:5px" onclick="javascript:nota.cambiarEstado(1,'+ not.id_predio+','+"'"+codigo_predio+"'"+','+ultimo_anio_pago+', '+propie+' )"><i class="dw dw-checked"></i></button>' ;
                var estadoPagoFecha = 'Sin Pago';
            }

        if(estado == 1){
            $('#bodyNotasRegistradas').append(
                '<tr>' +
                '<td>' +
                '<input type="checkbox" id="'+codigo_predio+'" )"/>' +
                '</td>' +
                '<td>' +
                fechaAsignacionMoroso + 
                '</td>' +
                '<td>' +
                codigo_predio +
                '</td>' +
                '<td>' +
                not.nombrePropietario + '<br>'+
                'Cedula: '+ not.identificacionPropietario +
                '</td>' +
                '<td>' +
                direccion +
                '</td>' +
                '<td>' +
                    'Periodo: '+ ultimo_anio_pago + 
                '</td>' +
                '<td>' +
                botonVur +
                '</td>' +
                '</tr>'
            );
        }else if(estado == 2 ){

            $('#bodyNotasRegistradas').append(
                '<tr>' +
                '<td>' +
                '<input type="checkbox" id="'+codigo_predio+'" )"/>' +
                '</td>' +
                '<td>' +
                fechaFacturaMoroso + 
                '</td>' +
                '<td>' +
                codigo_predio + '<br><strong>' + estadoPagoFecha + '</strong>' +
                '</td>' +
                '<td>' +
                not.nombrePropietario +'<br>'+
                'Cedula: '+ not.identificacionPropietario +
                '</td>' +
                '<td>' +
                direccion +
                '</td>' +
                '<td>' +
				   'Periodo: '+ ultimo_anio_pago +
                '</td>' +
                '<td>' +
				'Generar Factura' + '<br>'+
                generarFac + 
                '</td>' +
                '</tr>'
            );

        }else if(estado == 3 ){

            $('#bodyNotasRegistradas').append(
                '<tr>' +
                '<td>' +
                '<input type="checkbox" id="'+codigo_predio+'" )"/>' +
                '</td>' +
                '<td>' +
                fechaPublicacionMoroso + 
                '</td>' +
                '<td>' +
                codigo_predio +
                '</td>' +
                '<td>' +
                not.nombrePropietario +'<br>'+
                'Cedula: '+ not.identificacionPropietario +
                '</td>' +
                '<td>' +
                direccion +
                '</td>' +
                '<td>' +
				'Periodo: '+ ultimo_anio_pago + '<br>'+
                '</td>' +
                '<td>' +
				'Generar Publicación' + '<br>'+
                '<button type="button" class="btn btn-social-icon btn-success " data-toggle="tooltip" title="Generar Publicación"  style="margin-right:5px" onclick="javascript:nota.cambiarEstado(2,'+ not.id_predio+','+"'"+codigo_predio+"'"+','+ultimo_anio_pago+', '+propie+' )">' +
                '<i class="dw dw-checked"></i>' +
                '</button>' +
                '</td>' +
                '</tr>'
            );

        }else if(estado == 4 ){

            var fechaActual = new Date(); // Fecha actual
            // Calcular diferencia en días
            var diferenciaDias = nota.calcularDiferenciaDias(fechaPublicacionMoroso, fechaActual);

            // Activar o desactivar el botón según la diferencia
            if (diferenciaDias > 60) {
                var botonCost = '<button type="button" class="btn btn-social-icon btn-success " data-toggle="tooltip" title="Generar Constancia"  style="margin-right:5px" onclick="javascript:nota.cambiarEstado(3,'+ not.id_predio+','+"'"+codigo_predio+"'"+','+ultimo_anio_pago+', '+propie+' )"><i class="dw dw-checked"></i></button>' ;
                var  calss = 'class="table-warning"'
                var selec = '<input type="checkbox" id="'+codigo_predio+'" )"/>';
            } else {
               var botonCost = 'Ha transcurrido '+ diferenciaDias +' dias desde la Publicación';
               var  calss = 'class="table-success"'
               var selec = '<input type="checkbox" id="'+codigo_predio+'"" disabled/>';
            }


            $('#bodyNotasRegistradas').append(
                '<tr '+calss+'>' +
                '<td>' +
                selec  +
                '</td>' +
                '<td>' +
                fechaConstanciaMoroso + 
                '</td>' +
                '<td>' +
                codigo_predio +
                '</td>' +
                '<td>' +
                not.nombrePropietario +'<br>'+
                'Cedula: '+ not.identificacionPropietario +
                '</td>' +
                '<td>' +
                direccion +
                '</td>' +
                '<td>' +
				'Periodo: '+ ultimo_anio_pago + '<br>'+
                'Fecha Publicación: ' + fechaPublicacionMoroso + 
                '</td>' +
                '<td>' +
				'Generar Constancia' + '<br>'+
                botonCost +
                '</td>' +
                '</tr>'
            );

        }else if(estado == 5 ){

            $('#bodyNotasRegistradas').append(
                '<tr>' +
                '<td>' +
                '<input type="checkbox" id="'+codigo_predio+'" )"/>' +
                '</td>' +
                '<td>' +
                fechaMandamientoMoroso + 
                '</td>' +
                '<td>' +
                codigo_predio +
                '</td>' +
                '<td>' +
                not.nombrePropietario +'<br>'+
                'Cedula: '+ not.identificacionPropietario +
                '</td>' +
                '<td>' +
                direccion +
                '</td>' +
                '<td>' +
                'Periodo: '+ ultimo_anio_pago + '<br>'+
                '</td>' +
                '<td>' +
                'Generar: Minuta - Mandamiento de Pago' + '<br>'+
				'<button type="button" class="btn btn-social-icon btn-success " data-toggle="tooltip" title="Generar Docuemnto"  style="margin-right:5px" onclick="javascript:nota.cambiarEstado(4)">' +
                '<i class="dw dw-checked"></i>' +
                '</button>' +
                '</td>' +
                '</tr>'
            );

        }else if(estado == 6 ){

            $('#bodyNotasRegistradas').append(
                '<tr>' +
                '<td>' +
                '' +
                '</td>' +
                '<td>' +
                fechaAutoArchivoMoroso + 
                '</td>' +
                '<td>' +
                codigo_predio +
                '</td>' +
                '<td>' +
                not.nombrePropietario +'<br>'+
                'Cedula: '+ not.identificacionPropietario +
                '</td>' +
                '<td>' +
                direccion +
                '</td>' +
                '<td>' +
                'Periodo: '+ ultimo_anio_pago + '<br>'+
                '</td>' +
                '<td>' +
                'Investigación: '+ fechaAsignacionMoroso +'<br>'+
                'Factura: ' + fechaFacturaMoroso +'<br>'+
                'Publicación:' + fechaPublicacionMoroso +'<br>'+
                'Constancia: ' + fechaConstanciaMoroso +'<br>'+
                'Mandamiento: ' + fechaMandamientoMoroso +'<br>'+
                '</td>' +
                '</tr>'
            );

        }

        }
		
		console.log('detallesNota ', detallesNota);
        nota.init_table();
    }

    async cambiarEstado(id, idPredio, codigo, anio, nombre) {
         
        console.log('codigo ', codigo);
        if(id == 1){

            var title = "¿Está seguro de generar factura?";
            var subtitle = "";
            var button = "Sí, Generar";
            var est = 0;
          
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
  		
					nota.postPrediosGenerados( idPredio, codigo, anio, nombre);

					swal({
						type: 'success',
						title: 'Factura Generada',
						text: 'Factura Generada exitosamente',
					});
									  
                     
                }
            })

        }else if (id == 2){
            var title = "¿Está seguro de generar Documento de Publicación?";
            var subtitle = "";
            var button = "Sí, Generar";
            var est = 0;
          
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
    
                    nota.postPrediosGeneradosPublicados(idPredio, codigo, anio, nombre) ;

                swal({
                    type: 'success',
                    title: 'Documento Generado',
                    text: 'Documento de Publicación Generado Exitosamente',
                });
                        
                     
                }
            })

        }else if (id == 3){
            var title = "¿Está seguro de generar Documento de Constancia de Publicación?";
            var subtitle = "";
            var button = "Sí, Generar";
            var est = 0;
          
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

                    nota.postPrediosGeneradosConstancia(idPredio, codigo, anio, nombre) ;

                swal({
                    type: 'success',
                    title: 'Documento Generado',
                    text: 'Documento de Constancia Generado Exitosamente',
                });
                        
                     
                }
            })        
        
        }else if (id == 4){
                var title = "¿Está seguro de generar Documento de Minuta de Pago y Mandamiento de Pago?";
                var subtitle = "";
                var button = "Sí, Generar";
                var est = 0;
            
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

                    swal({
                        type: 'success',
                        title: 'Documento Generado',
                        text: 'Documento de Mandamiendo Pago y Minuta Generado Exitosamente',
                    });
                            
                        
                    }
                })
        }


    }


	
    async getPreciosProductoById(idPredio, codigoPredio, anio) {
               
                $("#formCrearPrecios").trigger("reset");
                $("#modal-Precios").modal('hide');

                $("#pre_Folio").val('');
                $("#pre_Observaciones").val('');
                $("#file").val('');

                $("#formCrearPrecios").attr('action', 'javascript:nota.postVur('+ idPredio+','+"'"+codigoPredio+"'"+','+anio+')');
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
        
    }
	
    postVur(idPredio, codigoPredio, anio) {


        var pre_Folio = $("#pre_Folio").val();
        var pre_Observaciones = $("#pre_Observaciones").val();
        var file = $("#file")[0].files[0]; // Obtén el archivo como un objeto File
    
        if (file) {
            const maxSizeInMB = 3;
            const maxSizeInBytes = maxSizeInMB * 1024 * 1024; // Convertir MB a bytes
      
            if (file.size > maxSizeInBytes) {
              e.preventDefault(); // Detener el envío del formulario
                swal({
                    type: 'warning',
                    title: 'Archivo Pesado',
                    text: 'El archivo es demasiado grande. Máximo permitido:'+ maxSizeInMB +'MB.',
                });
              return;
            }
          }

        var formData = new FormData();
        formData.append("funcion", 7);
        formData.append("idPredio", idPredio);
        formData.append("codigoPredio", codigoPredio);
        formData.append("anio", anio);
        formData.append("pre_Folio", pre_Folio);
        formData.append("pre_Observaciones", pre_Observaciones);
    
        // Agrega el archivo si existe
        if (file) {
            formData.append("file", file);
        }

        $.ajax({            
            url: '../business/controller/class.predialGestion.php',
            data: formData,
            processData: false, 
            contentType: false, 
            //dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                if (arr.ok == 1) {

                    $("#modal-Precios").modal('hide');
                    swal({
                        type: 'success',
                        title: 'Datos Actualizados',
                        text: 'Datos Actualizados Exitosamente',
                    });
                          
                    nota.getNotas(1);
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la  PagosCaja',
                    });
                }

            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });

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
    }

    /**
     * getNotas: Método para consultar las notas 
     */
    getNotas(estado) {
		
		 $('#loading').show();
         $('#wrapper').addClass('body-load');

		 var botonAutoArchivo = document.getElementById("botonAutoArchivo");
         var botonEstadoCambiar = document.getElementById("botonEstadoCambiar");
         
         if (estado == 6) {
            botonAutoArchivo.style.display = "none"; // Ocultar el botón
            botonEstadoCambiar.style.display = "none"; // Ocultar el botón
        } else {
            botonAutoArchivo.style.display = "block"; // Mostrar el botón
            botonEstadoCambiar.style.display = "block"; // Mostrar el botón
        }

        $.ajax({
            url: '../business/controller/class.predialGestion.php',
            data: { funcion: 5, estado: estado, idUsuario: idUsuario, idRol: idRol},
            dataType: "json",
            type: "POST",
            success: function(arr) {

                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                console.log('not ', arr);

                if (arr.ok == 1) {
                    $("#bodyDetallesNota").empty();
                    nota.draw_table_documents(arr.datos, estado);
                    botonAutoArchivo.style.display = "block"; // Mostrar el botón
                    botonEstadoCambiar.style.display = "block"; // Mostrar el botón
                } else {
                    botonAutoArchivo.style.display = "none"; // Ocultar el botón
                    botonEstadoCambiar.style.display = "none"; // Ocultar el botón
                    console.log('notasdasdas');
                    $("#notasRegistradas").DataTable().destroy();
                    $("#bodyNotasRegistradas").empty();
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

    proceso(estado){
        nota.getNotas(estado);

    }

    NotaActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DMorosos").addClass('expand');
        $("#SMorosos").addClass('active');
        $("#SMorosos").addClass('show');
        $("#SubMenuLiquidacionPro").addClass('active');
    }

    calcularDiferenciaDias(fecha1, fecha2) {
        // Convertir las fechas a objetos Date
        const fechaInicio = new Date(fecha1);
        const fechaFin = new Date(fecha2);
        
        const milisegundosPorDia = 1000 * 60 * 60 * 24; // Milisegundos en un día
        const diferenciaMilisegundos = fechaFin - fechaInicio; // Diferencia en milisegundos
        return Math.floor(diferenciaMilisegundos / milisegundosPorDia); // Redondear a días
}
   
}

const nota = new Nota();

nota.NotaActivo();
nota.getAños();
nota.getNotas(1);
