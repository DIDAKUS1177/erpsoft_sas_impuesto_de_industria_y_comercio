/*    METDOS DEL MODULO DE DOCUMENTOS RADICADOS    */

var enable = true;
var idRol = localStorage.getItem('id_Rol');
var idUsuario = localStorage.getItem('id_Usuario');

class DocumentosRadicados {

    constructor() {}

    /**
     * crearUsuario: Método para abrir modal de creación de DocumentosRadicados.
     */
    async crearDocumentosRadicados() {

        //Parametro: 27 (2= Modulo DocumentosRadicados, 7:Permiso Crear DocumentosRadicados)
        var permiso = await _permisos.getPermisos(idRol, 623);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $("#formCrearDocumentosRadicados").trigger("reset");
            $("#btnCrearDocumentosRadicados").empty();
            $("#btnCrearDocumentosRadicados").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearDocumentosRadicados").attr('action', 'javascript:documentosRadicados.postDocumentosRadicados()');
            $('#modal-DocumentosRadicados').modal({backdrop: 'static', keyboard: false})
            $("#modal-DocumentosRadicados").modal('show');
        }
    }


    /**
     * crearSubDocumentosRadicados: Método para abrir modal de creación de crearSubDocumentosRadicados.
     */
    async crearSubDocumentosRadicados(idSerieDocumental,nombreSerie) {

        //Parametro: 27 (2= Modulo DocumentosRadicados, 7:Permiso Crear DocumentosRadicados)
        var permiso = await _permisos.getPermisos(idRol, 623);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $("#nombreSerie").val(nombreSerie);
            $("#formCrearSubDocumentosRadicados").trigger("reset");
            $("#btnCrearSubDocumentosRadicados").empty();
            $("#btnCrearSubDocumentosRadicados").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearSubDocumentosRadicados").attr('action', 'javascript:documentosRadicados.postSubDocumentosRadicados('+idSerieDocumental+')');
            $('#modal-SubDocumentosRadicados').modal({backdrop: 'static', keyboard: false})
            $("#modal-SubDocumentosRadicados").modal('show');
        }
    }

    

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de DocumentosRadicados 
     * @param type $arrFilter: Listado de objetos DocumentosRadicados
     */
    draw_table_documents(arrFilter) {
        
        $("#documentosRadicadosRegistrados").DataTable().destroy();
        $("#bodyDocumentosRadicadosRegistrados").empty();
        for (let pe of arrFilter) {
            if (pe.pe_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Serie Documental";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Serie Documental";
            }

            if (pe.pe_Prioridad == 1) {
                var priorid = "Urgente";
            } else if (pe.pe_Prioridad == 2) {
                var priorid = "Normal";
            } else {
                var priorid = "Baja";
            }

            if (pe.strEstadoActual != null) {
                var estadoActual = pe.strEstadoActual;
                var soporteRadicado = '<a href="../soportesRadicados/'+pe.pe_Id+'/Numero_Radicado_'+pe.pe_Id+'.pdf" target="_blank" title="Soporte Radicado"  class="btn btn-success btn-pill"><span class="ti-plus"></span></a>'
                // Codigo para generar el soporte 
                //var soporteRadicado = '<a href="../extensiones/radicado.php?codigo=' + pe.pe_Id + '" target="_blank" title="Soporte Radicado"  class="btn btn-success btn-pill"><span class="ti-plus"></span></a>' ;
            }else{
                var estadoActual = "Sin Asignar";
                var soporteRadicado ='';
            }

            var color = 'style="background-color:'+pe.strEstadoActualColor+'"';

                $('#bodyDocumentosRadicadosRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    pe.created_at +
                    '</td>' +
                    '<td>' +
                    pe.pe_Id +
                    '</td>' +
                    '<td>' +
                    pe.strTipoDocumento +
                    '</td>' +
                    '<td>' +
                    pe.strIdDependencia +
                    '</td>' +
                    '<td>' +
                    priorid +
                    '</td>' +

                    '<td align="center" '+color+'>' +
                    estadoActual +
                    '</td>' +

                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Actualizar Radicado" style="margin-right:5px" onclick="javascript:documentosRadicados.getRadicadoById(' + pe.pe_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon btn-primary " data-toggle="tooltip" title="Gestionar Solicitud" style="margin-right:5px" onclick="javascript:documentosRadicados.gestionarSolicitud(' + pe.pe_Id + ')">' +
                    '<i class="dw dw-add"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon btn-info " data-toggle="tooltip" title="Trazabilidad de la Solicitud" style="margin-right:5px" onclick="javascript:documentosRadicados.verTrazabilidadSolicitud(' + pe.pe_Id + ')">' +
                    '<i class="dw dw-add"></i>' +
                    '</button>' +
                    
                    soporteRadicado + 

                    '</td>' +

                    '</tr>'
                );
            
        }
        documentosRadicados.init_table();
    }
   

     /**
     * verTrazabilidadSolicitud: Método para consultar el 
     * detalle de las verTrazabilidadSolicitud
     * @param type $idNota: Listado de objetos de tipo nota
     */
     async verTrazabilidadSolicitud(idNota) {

        $('#loading').show();
        $('#wrapper').addClass('body-load');
        var permiso = await _permisos.getPermisos(idRol, 734);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {

            $.ajax({
                url: '../business/controller/class.peticiones.php',
                data: { funcion: 7, idRadicado: idNota },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    console.log('det ', arr)
                    $("#ltsTrazabilidadRadicados").DataTable().destroy();
                    $("#bodyTrazabilidadRadicados").empty();

                    if (arr.ok == 1) {

                        for (let d of arr.datos) {
                            if(d.tra_Observaciones  == null){
                                var obser = 'Sin Registro';
                            }else{
                                var obser = d.tra_Observaciones ;
                            }
                            $("#bodyTrazabilidadRadicados").append(
                                '<tr>' +
                                '<td>' + d.created_at + '</td>' +
                                '<td>' + d.strNombreResponsable + '</td>' +
                                '<td class="numero" align="rigth">' + d.tra_Cambios + '</td>' +
                                '<td class="numero" align="rigth">' + obser + '</td>' +
                                '<td>' + d.strNombreUsuario + '</td>' +                            
                                //'<td class="numero" align="rigth">' + d.strPdfSubidos + '</td>' +
                                '</tr>'
                            );
                        }

                        documentosRadicados.init_table_detalle();

                        $("#modal-TrazabilidadRadicados").modal('show');

                        const pdfDiv = document.getElementById("pdfTotales");
                        // Obtiene la URL base de manera dinámica, por ejemplo:
                        const baseUrl = window.location.origin + "/erpsoftsas/";

                        if (Array.isArray(arr.pdf) && arr.pdf.length > 0) {
                            pdfDiv.innerHTML = "";

                            arr.pdf.forEach(folderData => {
                                const folderName = folderData.folder;
                                const files = folderData.files;

                                const folderContainer = document.createElement("div");
                                folderContainer.classList.add("folder-container");

                                const folderTitle = document.createElement("h5");
                                folderTitle.textContent = `Carpeta: ${folderName}`;
                                folderContainer.appendChild(folderTitle);

                                const fileList = document.createElement("ul");
                                fileList.classList.add("file-list");

                                files.forEach(file => {
                                    const fileItem = document.createElement("li");

                                    const fileLink = document.createElement("a");
                                    // Construye la URL completa con la ruta devuelta en `file`
                                    fileLink.href = baseUrl + file; 
                                    fileLink.target = "_blank";
                                    fileLink.textContent = `Abrir: ${file.split('/').pop()}`;

                                    fileItem.appendChild(fileLink);
                                    fileList.appendChild(fileItem);
                                });

                                folderContainer.appendChild(fileList);
                                pdfDiv.appendChild(folderContainer);
                            });
                        } else {
                            pdfDiv.innerHTML = "<p>No se encontraron carpetas o archivos.</p>";
                        }
                    } else {
                        swal({
                            type: 'error',
                            title: 'Ocurrio un error al consultar la información',
                            text: 'Por favor intente nuevamente, si el problema persiste consulte con el adimistrador del sistema',
                        });
                    }
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                }
            });
        }
    }

    /**
     * init_table_detalle: Método para asignar la
     * propiedad DataTable() a la tabla de trazabilidad
     */
    init_table_detalle() {
        $('#ltsTrazabilidadRadicados').DataTable({
            scrollCollapse: true,
            autoWidth: false,
            responsive: true,
            columnDefs: [
                {
                targets: "_all",
                className: 'text-wrap'
            },
            { "width": "10%", "targets": 0 },
            { "width": "10%", "targets": 1 },
            { "width": "30%", "targets": 2 },
            { "width": "40%", "targets": 3 }
            ],
            aaSorting: [
                [0, "desc"]
            ],
            "lengthMenu": [
                [5, 10, 25, 50, -1],
                [5, 10, 25, 50, "All"]
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
     * gestionarSolicitud: Método para consultar la
     * información de un gestionarSolicitud
     * @param type $id: llave primaria de la tabla gestionarSolicitud
     */
    async gestionarSolicitud(id) {
        
        $('#loading').show();
        $('#wrapper').addClass('body-load');
        var permiso = await _permisos.getPermisos(idRol, 729);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.peticiones.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        console.log('arr editar ', arr);
                        for (let datos of arr.datos) {
                            
                            //$("#tra_IdEstadoTipoPeticion").val(datos.strEstadoActual);
                            document.getElementById("tra_IdEstadoTipoPeticion").innerText = datos.strEstadoActual;

                            $("#pe_IdTipoPeticionOriginalCam").val(datos.pe_IdTipoPeticion);
                            $("#pe_IdDependenciaOriginalCam").val(datos.pe_IdDependencia);
                            $("#pe_IdCategoriaOriginalCam").val(datos.pe_IdCategoria);
                            $("#pe_IdSubCategoriaOriginalCam").val(datos.pe_IdSubCategoria);

                            var estadoActualTiPe = datos.pe_IdEstadoTiposPeticion;
                            var idTipoPeticionTiPe = datos.pe_IdTipoPeticion;                            
                        }
                        
                        documentosRadicados.getEstadosTiposPeticion(idTipoPeticionTiPe,estadoActualTiPe);
                        
                        document.getElementById("gestionRadicadoNombre").innerText = "Gestión del Radicado # "+id;
                        
                        $("#formCrearGestionRadicados").attr('action', 'javascript:documentosRadicados.gestionarRadicado(' + id + ')');
                        $("#btnCrearGestionRadicados").empty();
                        $("#btnCrearGestionRadicados").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $('#modal-GestionRadicados').modal({backdrop: 'static', keyboard: false})
                        $("#modal-GestionRadicados").modal('show');

                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información del Radicado.',
                        });
                    }
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                }
            });
        }
    }

    /**
     * getRadicadoById: Método para consultar la
     * información de un getRadicadoById
     * @param type $id: llave primaria de la tabla getRadicadoById
     */
    async getRadicadoById(id) {
        
        $('#loading').show();
        $('#wrapper').addClass('body-load');
        var permiso = await _permisos.getPermisos(idRol, 733);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.peticiones.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        console.log('arr editar ', arr);
                        for (let datos of arr.datos) {
                            
                            $("#pe_IdTipoPeticionOriginal").val(datos.pe_IdTipoPeticion);
                            $("#pe_NombreCompletoOriginal").val(datos.pe_NombreCompleto);
                            $("#pe_NumeroIdentificacionOriginal").val(datos.pe_NumeroIdentificacion);
                            $("#pe_DireccionOriginal").val(datos.pe_Direccion);
                            $("#pe_TelefonoOriginal").val(datos.pe_Telefono);
                            $("#pe_CorreoElectronicoOriginal").val(datos.pe_CorreoElectronico);
                            $("#pe_IdDependenciaOriginal").val(datos.pe_IdDependencia);
                            $("#pe_IdCategoriaOriginal").val(datos.pe_IdCategoria);
                            $("#pe_IdSubCategoriaOriginal").val(datos.pe_IdSubCategoria);
                            $("#pe_DescripcionOriginal").val(datos.pe_Descripcion);
                            $("#pe_ObservacionesOriginal").val(datos.pe_Observaciones);
                            $("#pe_PrioridadOriginal").val(datos.pe_Prioridad);
                            $("#pe_FormaRecepcionOriginal").val(datos.pe_FormaRecepcion);
                            $("#pe_NumeroFoliosOriginal").val(datos.pe_NumeroFolios);

                            $("#pe_IdTipoPeticion").val(datos.pe_IdTipoPeticion);
                            $("#pe_NombreCompleto").val(datos.pe_NombreCompleto);
                            $("#pe_NumeroIdentificacion").val(datos.pe_NumeroIdentificacion);
                            $("#pe_Direccion").val(datos.pe_Direccion);
                            $("#pe_Telefono").val(datos.pe_Telefono);
                            $("#pe_CorreoElectronico").val(datos.pe_CorreoElectronico);
                            $("#pe_IdDependencia").val(datos.pe_IdDependencia);
                            $("#pe_IdCategoria").val(datos.pe_IdCategoria);
                            $("#pe_IdSubCategoria").val(datos.pe_IdSubCategoria);
                            $("#pe_Descripcion").val(datos.pe_Descripcion);
                            $("#pe_Observaciones").val(datos.pe_Observaciones);
                            $("#pe_Prioridad").val(datos.pe_Prioridad);
                            $("#pe_FormaRecepcion").val(datos.pe_FormaRecepcion);
                            $("#pe_NumeroFolios").val(datos.pe_NumeroFolios);
                            
                        }
                        
                        document.getElementById("exampleModalFormTitle").innerText = "Radicado # "+id;

                        const pdfDiv = document.getElementById("pdfLinks");

                        if (arr.pdf != 0) {
                            pdfDiv.innerHTML = arr.pdf;
                        } 
                        
                        $("#formCrearRadicadoActualizar").attr('action', 'javascript:documentosRadicados.editRadicado(' + id + ')');
                        $("#btnCrearRadicadoActualizar").empty();
                        $("#btnCrearRadicadoActualizar").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $('#modal-RadicadoActualizar').modal({backdrop: 'static', keyboard: false})
                        $("#modal-RadicadoActualizar").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información del Radicado.',
                        });
                    }
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                }
            });
        }
    }


    /**
     * init_table: Método para asignar la
     * propiedad DataTable() a la tabla de DocumentosRadicados
     */
    init_table() {
        $('.data-table').DataTable({
            scrollCollapse: true,
            autoWidth: false,
            responsive: true,
            columnDefs: [
                { targets: "datatable-nosort", orderable: false,},
                { "width": "5%", "targets": 0 },
                { "width": "5%", "targets": 1 },
                { "width": "5%", "targets": 2 }
            ],
            "lengthMenu": [
                [5, 10, 25, 50, -1],
                [5, 10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'Documentos Radicados registrados',
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
     * getDocumentosRadicados: Método para consultar DocumentosRadicados
     */
    getDocumentosRadicados(fechaInicio, fechaFinal) {

        var idResponsable = localStorage.getItem('id_Usuario');
        var idRol = localStorage.getItem('id_Rol');
        
        if(fechaInicio == 0){
            var n = new Date();
            var y = n.getFullYear();
            var m = n.getMonth() + 1; // Mes actual
            var d = n.getDate();
            
            // Ajuste para restar un mes
            var nMesAtras = new Date();
            nMesAtras.setMonth(nMesAtras.getMonth() - 1);
            var mm = nMesAtras.getMonth() + 1; // Mes anterior (ya ajustado)
            
            // Asegurarse de que el día y los meses sean siempre de dos dígitos
            if(d < 10){ d = '0' + d; }
            if(m < 10){ m = '0' + m; }
            if(mm < 10){ mm = '0' + mm; }
            
            var fechaFinal = y + "-" + m + "-" + d; // Fecha actual
            var fechaInicio = nMesAtras.getFullYear() + "-" + mm + "-" + d; // Fecha un mes atrás
            
            console.log("Fecha inicio (actual): " + fechaInicio);
            console.log("Fecha final (un mes atrás): " + fechaFinal);
        }
        console.log('fechaInicio ', fechaInicio);
        console.log('fechaFinal ', fechaFinal);


        $.ajax({
            url: '../business/controller/class.peticiones.php',
            data: { funcion: 5, idRol: idRol ,fechaInicial: fechaInicio, fechaFinal: fechaFinal,
                    idResponsable: idResponsable},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                if (arr.ok == 1) {
                    $("#bodyDocumentosRadicadosRegistrados").empty();
                    documentosRadicados.draw_table_documents(arr.datos);
                } else {
                    $("#documentosRadicadosRegistrados").DataTable().destroy();
                    $("#bodyDocumentosRadicadosRegistrados").empty();
                    documentosRadicados.init_table();
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * getUsuarioById: Método para consultar la
     * información de un DocumentosRadicados
     * @param type $id: llave primaria de la tabla DocumentosRadicados
     */
    async getDocumentosRadicadosById(id) {
        
        $('#loading').show();
        $('#wrapper').addClass('body-load');
        var permiso = await _permisos.getPermisos(idRol, 624);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.categoriasDocumental.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        console.log('arr editar ', arr);
                        for (let datos of arr.datos) {
                            $("#cat_IdDependencia").val(datos.cat_IdDependencia);
                            $("#cat_Nombre").val(datos.cat_Nombre);
                            $("#cat_Descripcion").val(datos.cat_Descripcion);
                            $("#cat_Sigla").val(datos.cat_Sigla);
                            $("#cat_Codigo").val(datos.cat_Codigo);
                        }
                        $("#formCrearDocumentosRadicados").attr('action', 'javascript:documentosRadicados.editDocumentosRadicados(' + id + ')');
                        $("#btnCrearDocumentosRadicados").empty();
                        $("#btnCrearDocumentosRadicados").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $('#modal-DocumentosRadicados').modal({backdrop: 'static', keyboard: false})
                        $("#modal-DocumentosRadicados").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información dela DocumentosRadicados',
                        });
                    }
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                }
            });
        }
    }

    /**
     * postUsuario: Método para crear DocumentosRadicados
     */
    postDocumentosRadicados() {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var idDependencia = $("#cat_IdDependencia").val();
        var nombre = $("#cat_Nombre").val();
        var descripcion = $("#cat_Descripcion").val();
        var sigla = $("#cat_Sigla").val();
        var codigo = $("#cat_Codigo").val();

        $.ajax({
            url: '../business/controller/class.categoriasDocumental.php',
            data: { funcion: 1, cat_IdDependencia: idDependencia, cat_Nombre: nombre, cat_Descripcion: descripcion,
                cat_Sigla: sigla, cat_Codigo: codigo },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {                    
                    $("#formCrearDocumentosRadicados").trigger("reset");
                    $("#modal-DocumentosRadicados").modal('hide');
                    documentosRadicados.getDocumentosRadicados(0,0);
                    swal({
                        type: 'success',
                        title: 'DocumentosRadicados creada',
                        text: 'DocumentosRadicados creada exitosamente',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la DocumentosRadicados',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }


    /**
     * postSubDocumentosRadicados: Método para crear postSubDocumentosRadicados
     */
    postSubDocumentosRadicados(idSerieDocumental) {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var idCategoria = idSerieDocumental;
        var nombre = $("#subc_Nombre").val();
        var descripcion = $("#subc_Descripcion").val();
        var sigla = $("#subc_Sigla").val();
        var codigo = $("#subc_Codigo").val();

        $.ajax({
            url: '../business/controller/class.subcategoriasDocumental.php',
            data: { funcion: 1, subc_IdCategoria: idCategoria, subc_Nombre: nombre, subc_Descripcion: descripcion,
                subc_Sigla: sigla, subc_Codigo: codigo },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearSubDocumentosRadicados").trigger("reset");
                    $("#modal-SubDocumentosRadicados").modal('hide');
                    documentosRadicados.getDocumentosRadicados(0,0);
                    swal({
                        type: 'success',
                        title: 'Sub Serie Documental creada',
                        text: 'Sub Serie Documental creada exitosamente',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la Sub Serie Documental',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    

    /**
     * cambiarEstado: Método para cambiar el estado de los DocumentosRadicados
     * @param type $id_usuario:  llave primaria de la tabla DocumentosRadicados
     * @param type $estado: estado actual del DocumentosRadicados
     */
    async cambiarEstado(id_documentosRadicados, estado) {

        var permiso = await _permisos.getPermisos(idRol, 625);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar la Serie Documental?";
                var subtitle = "Una vez inactivado la Serie Documental no podra usarse";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la Serie Documental?";
                var subtitle = "Una vez activado, la Serie Documental podrá usarse";
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
                        url: '../business/controller/class.categoriasDocumental.php',
                        data: { funcion: 4, id: id_documentosRadicados, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {

                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                documentosRadicados.getDocumentosRadicados(0,0);
                                swal({
                                    type: 'success',
                                    title: 'Serie Documental actualizado',
                                    text: 'Serie Documental actualizado exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el serie Documental',
                                });
                            }
                        },
                        error: function(XMLHttpRequest, textStatus, errorThrown) {
                            console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                        }
                    });
                }
            })
        }
    }

    /**
     * gestionarRadicado: Método para actualizar un gestionarRadicado
     * @param type $id: llave primaria de la tabla gestionarRadicado
     */
    gestionarRadicado(id) {
/*
        $('#loading').show();
        $('#wrapper').addClass('body-load');
*/
        var tra_IdEstadoTipoPeticionNew = $("#tra_IdEstadoTipoPeticionNew").val();
        var tra_Observaciones = $("#tra_Observaciones").val();

        var pe_IdTipoPeticion = $("#pe_IdTipoPeticionOriginalCam").val();
        var pe_IdDependencia = $("#pe_IdDependenciaOriginalCam").val();
        var pe_IdCategoria = $("#pe_IdCategoriaOriginalCam").val();
        var pe_IdSubCategoria = $("#pe_IdSubCategoriaOriginalCam").val();     
        
        var idDependenciaResponsable = $("#pe_IdDependenciaOriginalCam option:selected").attr('data-extra');
        var cambiosEstadoo = 'Cambio de Estado a:'+$("#tra_IdEstadoTipoPeticionNew").find('option:selected').text();

        var nomEstadoNew =$("#tra_IdEstadoTipoPeticionNew").find('option:selected').text();
         // Crear FormData y agregar los campos
         var formData = new FormData();
         formData.append('funcion', 6);
         formData.append('tra_IdPeticion', id);
         formData.append('tra_IdTipoPeticion', pe_IdTipoPeticion);
         formData.append('tra_IdEstadoTipoPeticion', tra_IdEstadoTipoPeticionNew);
         
         formData.append('tra_IdDependencia', pe_IdDependencia);
         formData.append('tra_IdCategoria', pe_IdCategoria);
         formData.append('tra_IdSubCategoria', pe_IdSubCategoria);

         formData.append('tra_Cambios', cambiosEstadoo);
         formData.append('tra_Observaciones', tra_Observaciones);
       
         formData.append('pe_IdDependenciaResponsable', idDependenciaResponsable);
         formData.append('tra_IdUsuario', idUsuario);
         formData.append('nomEstadoNew', nomEstadoNew);
         
         
         // Agregar archivos PDF
         var pdfFiles = $('#doc_Anexos')[0].files;
         for (var i = 0; i < pdfFiles.length; i++) {
             formData.append('doc_Anexos[]', pdfFiles[i]);
         }
         for (let [clave, valor] of formData.entries()) {
            console.log(clave, valor);
        }
         console.log('formData', formData);


        $.ajax({
            url: '../business/controller/class.peticiones.php',
            data: formData,
            dataType: "json",
            type: "POST",
            contentType: false,
            processData: false,
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearGestionRadicados").trigger("reset");
                    $("#modal-GestionRadicados").modal('hide');
                    documentosRadicados.getDocumentosRadicados(0,0);
                    swal({
                        type: 'success',
                        title: 'Radicado Actualizado',
                        text: 'Radicado Actualizado Exitosamente',
                    });
                }else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar el Radicado',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });

    }


    /**
     * editRadicado: Método para actualizar un documentosRadicados
     * @param type $id: llave primaria de la tabla documentosRadicados
     */
    editRadicado(id) {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        let cambios = ""; // Variable para almacenar los cambios detectados

        var pe_IdTipoPeticion = $("#pe_IdTipoPeticion").val();
        var pe_NombreCompleto = $("#pe_NombreCompleto").val();
        var pe_NumeroIdentificacion = $("#pe_NumeroIdentificacion").val();
        var pe_Direccion = $("#pe_Direccion").val();
        var pe_Telefono = $("#pe_Telefono").val();
        var pe_CorreoElectronico = $("#pe_CorreoElectronico").val();
        var pe_IdDependencia = $("#pe_IdDependencia").val();
        var pe_IdCategoria = $("#pe_IdCategoria").val();
        var pe_IdSubCategoria = $("#pe_IdSubCategoria").val();
        var pe_Descripcion = $("#pe_Descripcion").val();
        var pe_Observaciones = $("#pe_Observaciones").val();
        var pe_Prioridad = $("#pe_Prioridad").val();
        var pe_FormaRecepcion = $("#pe_FormaRecepcion").val();
        var pe_NumeroFolios = $("#pe_NumeroFolios").val();

        var idDependenciaResponsable = $("#pe_IdDependencia option:selected").attr('data-extra');
        var idEstadoTiposPeticion = $("#pe_IdTipoPeticion option:selected").attr('data-extra');

        var idDependenciaResponsableOriginal = $("#pe_IdDependenciaOriginal option:selected").attr('data-extra');
        var idEstadoTiposPeticionOriginal = $("#pe_IdTipoPeticionOriginal option:selected").attr('data-extra');

        // Lista de IDs de los campos
        const campos = [
            "pe_IdTipoPeticion",
            "pe_NombreCompleto",
            "pe_NumeroIdentificacion",
            "pe_Direccion",
            "pe_Telefono",
            "pe_CorreoElectronico",
            "pe_IdDependencia",
            "pe_IdCategoria",
            "pe_IdSubCategoria",
            "pe_Descripcion",
            "pe_Observaciones",
            "pe_Prioridad",
            "pe_FormaRecepcion",
            "pe_NumeroFolios"
        ];
    
        campos.forEach((idCampo) => {
            // Obtener el valor actual del campo
            const valorActual = document.getElementById(idCampo).value;
            // Obtener el valor original del campo
            const valorOriginal = document.getElementById(idCampo + "Original").value;
    
            // Comparar valores
            if (valorActual !== valorOriginal) {
                // Si son diferentes, agregar el cambio a la variable cambios
                cambios += `Campo: ${idCampo}, Valor Anterior: ${valorOriginal}, Valor Nuevo: ${valorActual}\n`;
            }
        });

        
        if(idDependenciaResponsable !==  idDependenciaResponsableOriginal){
            cambios += `Campo: idDependenciaResponsable, Valor Anterior: ${idDependenciaResponsable}, Valor Nuevo:  ${idDependenciaResponsableOriginal} \n`;
        }
        if(idEstadoTiposPeticion !==  idEstadoTiposPeticionOriginal){
            cambios += `Campo: idEstadoTiposPeticion, Valor Anterior: ${idEstadoTiposPeticion}, Valor Nuevo: ${idEstadoTiposPeticionOriginal} \n`;
        }

        $.ajax({
            url: '../business/controller/class.peticiones.php',
            data: { funcion: 2,id: id, pe_IdTipoPeticion: pe_IdTipoPeticion, pe_NombreCompleto: pe_NombreCompleto,pe_NumeroIdentificacion: pe_NumeroIdentificacion,
                pe_Direccion: pe_Direccion, pe_Telefono: pe_Telefono, pe_CorreoElectronico: pe_CorreoElectronico,
                pe_IdDependencia: pe_IdDependencia, pe_IdCategoria: pe_IdCategoria, pe_IdSubCategoria: pe_IdSubCategoria,
                pe_Descripcion: pe_Descripcion, pe_Observaciones: pe_Observaciones, pe_Prioridad: pe_Prioridad, 
                pe_FormaRecepcion: pe_FormaRecepcion, pe_NumeroFolios: pe_NumeroFolios, idDependenciaResponsable: idDependenciaResponsable,
                pe_IdEstadoTiposPeticion: idEstadoTiposPeticion, cambios: cambios ,usuarioLogueado: idUsuario},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearRadicadoActualizar").trigger("reset");
                    $("#modal-RadicadoActualizar").modal('hide');
                    documentosRadicados.getDocumentosRadicados(0,0);
                    swal({
                        type: 'success',
                        title: 'Radicado actualizado',
                        text: 'Radicado Actualizado Exitosamente',
                    });
                }else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar el Serie Documental',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }


        /**
     * getIdTipoPeticion: Método para consultar las getIdTipoPeticion
     */
    getIdTipoPeticion() {

        $.ajax({
            url: '../business/controller/class.tiposPeticiones.php',
            data: { funcion: 3, estado:1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                $("#pe_IdTipoPeticion").empty();
                $("#pe_IdTipoPeticion").append('<option value="">Seleccion una opción</option>');
                $("#pe_IdTipoPeticionOriginal").empty();
                $("#pe_IdTipoPeticionOriginal").append('<option value="">Seleccion una opción</option>');
                $("#pe_IdTipoPeticionOriginalCam").empty();
                $("#pe_IdTipoPeticionOriginalCam").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#pe_IdTipoPeticion").append('<option  data-extra="' + v['strIdEstadoInicial'] + '" value="' + v['tipe_Id'] + '">' + v['tipe_Nombre'] + '</option>');
                        $("#pe_IdTipoPeticionOriginal").append('<option  data-extra="' + v['strIdEstadoInicial'] + '" value="' + v['tipe_Id'] + '">' + v['tipe_Nombre'] + '</option>');
                        $("#pe_IdTipoPeticionOriginalCam").append('<option  data-extra="' + v['strIdEstadoInicial'] + '" value="' + v['tipe_Id'] + '">' + v['tipe_Nombre'] + '</option>');
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

      /**
     * getIdDependencia: Método para consultar las getIdDependencia
     */
      getIdDependencia() {

        $.ajax({
            url: '../business/controller/class.dependencia.php',
            data: { funcion: 3, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                $("#pe_IdDependencia").empty();
                $("#pe_IdDependencia").append('<option value="">Seleccion una opción</option>');
                $("#pe_IdDependenciaOriginal").empty();
                $("#pe_IdDependenciaOriginal").append('<option value="">Seleccion una opción</option>');
                $("#pe_IdDependenciaOriginalCam").empty();
                $("#pe_IdDependenciaOriginalCam").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#pe_IdDependencia").append('<option data-extra="' + v['dep_IdResponsable'] + '" value="' + v['dep_Id'] + '">' + v['dep_Nombre'] + '</option>');
                        $("#pe_IdDependenciaOriginal").append('<option data-extra="' + v['dep_IdResponsable'] + '" value="' + v['dep_Id'] + '">' + v['dep_Nombre'] + '</option>');
                        $("#pe_IdDependenciaOriginalCam").append('<option data-extra="' + v['dep_IdResponsable'] + '" value="' + v['dep_Id'] + '">' + v['dep_Nombre'] + '</option>');
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    
    }

    /**
     * getIdSerieDocumental: Método para consutlar los series
     */
    getIdSerieDocumental() {
        $("#pe_IdCategoria").empty();        
        $("#pe_IdCategoria").append('<option value="">Seleccione un Departamento</option>');
        $("#pe_IdCategoriaOriginal").empty();        
        $("#pe_IdCategoriaOriginal").append('<option value="">Seleccione un Departamento</option>');
        $("#pe_IdCategoriaOriginalCam").empty();        
        $("#pe_IdCategoriaOriginalCam").append('<option value="">Seleccione un Departamento</option>');

        $.ajax({
            url: '../business/controller/class.categoriasDocumental.php',
            data: { funcion: 3, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#pe_IdCategoria").append('<option value="' + v['cat_Id'] + '">' + v['cat_Nombre'] + '</option>');
                        $("#pe_IdCategoriaOriginal").append('<option value="' + v['cat_Id'] + '">' + v['cat_Nombre'] + '</option>');
                        $("#pe_IdCategoriaOriginalCam").append('<option value="' + v['cat_Id'] + '">' + v['cat_Nombre'] + '</option>');
                    });

                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

     /**
     * getIdSubSeriesDoc: Método para consutlar los  Sub series
     */
     getIdSubSeriesDoc() {

         var pe_IdCategoria = $("#pe_IdCategoria").val();

        $("#pe_IdSubCategoria").empty();
        $("#pe_IdSubCategoria").append('<option value="">Seleccione un Sub Serie</option>');
        $("#pe_IdSubCategoria").empty();
        $("#pe_IdSubCategoriaOriginal").append('<option value="">Seleccione un Sub Serie</option>');
        $("#pe_IdSubCategoriaCam").empty();
        $("#pe_IdSubCategoriaOriginalCam").append('<option value="">Seleccione un Sub Serie</option>');

        $.ajax({
            url: '../business/controller/class.subCategoriasDocumental.php',
            data: { funcion: 3, idCategoria: pe_IdCategoria, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#pe_IdSubCategoria").append('<option value="' + v['subc_Id'] + '">' + v['subc_Nombre'] + '</option>');
                        $("#pe_IdSubCategoriaOriginal").append('<option value="' + v['subc_Id'] + '">' + v['subc_Nombre'] + '</option>');
                        $("#pe_IdSubCategoriaOriginalCam").append('<option value="' + v['subc_Id'] + '">' + v['subc_Nombre'] + '</option>');
                    });
                }                
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }


    /**
     * getEstadosTiposPeticion: Método para consutlar los  getEstadosTiposPeticion
     */
    getEstadosTiposPeticion(idTipoPeticion,estadoActual) {

        $("#tra_IdEstadoTipoPeticionNew").empty();
        $("#tra_IdEstadoTipoPeticionNew").append('<option value="">Seleccione un Estado</option>');

        console.log('idTipoPeticion', idTipoPeticion);
        console.log('estadoActual', estadoActual);
        
        $.ajax({
            url: '../business/controller/class.estadosTiposPeticiones.php',
            data: { funcion: 3, idTipoPeticion: idTipoPeticion},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);

                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        if(v['estipe_Id'] !== estadoActual){
                            $("#tra_IdEstadoTipoPeticionNew").append('<option value="' + v['estipe_Id'] + '">' + v['strNombreEstado'] + '</option>');
                        }
                        
                    });
                }                
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    

    /**
     * UsuarioActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    UsuarioActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DGestorDocumental").addClass('expand active');
        $("#DGestorDocumental").addClass('active');
        $("#SubGestorDocumental").addClass('show');
        $("#SubVerDocumentos").addClass('active');
    }
}

const documentosRadicados = new DocumentosRadicados();

documentosRadicados.getDocumentosRadicados(0,0);
documentosRadicados.UsuarioActivo();
documentosRadicados.getIdTipoPeticion();

documentosRadicados.getIdDependencia();
documentosRadicados.getIdSerieDocumental();
documentosRadicados.getIdSubSeriesDoc();



$(function() {
    
    // Obtener la fecha de hoy
    var today = moment();

    // Obtener la fecha de un mes atrás
    var lastMonth = moment().subtract(1, 'months');

    $('input[name="daterange"]').daterangepicker({
      opens: 'left',
      startDate: lastMonth, // Fecha de inicio por defecto (un mes atrás)
      endDate: today, // Fecha final por defecto (hoy)
      locale: {
        format: 'YYYY-MM-DD',
        separator: " - ",
        applyLabel: "Aplicar",
        cancelLabel: "Cancelar",
        fromLabel: "Desde",
        toLabel: "Hasta",
        customRangeLabel: "Rango personalizado",
        daysOfWeek: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'],
        monthNames: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
        firstDay: 1 // Para que la semana comience en lunes
      }
    }, function(start, end, label) {

        // Obtener los valores de los inputs
        var fechaInicio = start.format('YYYY-MM-DD') ;
        var fechaFinal = end.format('YYYY-MM-DD');
    
        // Llamar a la función nota.getNotas() pasando las fechas
        documentosRadicados.getDocumentosRadicados(fechaInicio, fechaFinal);

        console.log("Rango de fechas: " + start.format('YYYY-MM-DD') + ' a ' + end.format('YYYY-MM-DD'));
    });
  });