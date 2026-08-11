/*    METDOS DEL MODULO DE DEPENDENCIA    */

var enable = true;
var idRol = localStorage.getItem('id_Rol');
var idContribuyente = localStorage.getItem('id_Contribuyente');

class Establecimientos {

    constructor() {}

    /**
     * crearUsuario: Método para abrir modal de creación de ActividadesComercio.
     */
    /**
     * verInformacionContribuyente: abre el modal con los datos del
     * contribuyente que tiene la sesion actual (localStorage id_Contribuyente),
     * al lado del boton de "Crear Establecimientos".
     */
    verInformacionContribuyente() {
        var idContribuyente = localStorage.getItem('id_Contribuyente');

        if (!idContribuyente) {
            swal({
                type: 'warning',
                title: 'Sin contribuyente asociado',
                text: 'No se encontró un contribuyente asociado a esta sesión.',
            });
            return;
        }

        $.ajax({
            url: '../business/controller/class.contribuyentes.php',
            type: 'POST',
            dataType: 'json',
            data: {
                funcion: 3, // Consultar Contribuyente
                ind_Id: idContribuyente
            },
            success: function (arr) {
                if (arr.ok != 1 || !arr.datos || !arr.datos.length) {
                    swal({
                        type: 'error',
                        title: 'No se pudo cargar la información',
                        text: arr.mensaje || 'Intente nuevamente.',
                    });
                    return;
                }

                var d = arr.datos[0];
                $("#formInfoContribuyente").trigger("reset");
                $("#infoContrib_ind_Id").val(d.ind_Id);
                $("#infoContrib_ind_Persona").val(d.ind_Persona);
                $("#infoContrib_ind_IdTipoDocumento").val(d.ind_IdTipoDocumento);
                $("#infoContrib_ind_NumeroIdentificacion").val(d.ind_NumeroIdentificacion);
                $("#infoContrib_ind_DV").val(d.ind_DV);
                $("#infoContrib_ind_PrimerNombre").val(d.ind_PrimerNombre);
                $("#infoContrib_ind_SegundoNombre").val(d.ind_SegundoNombre);
                $("#infoContrib_ind_PrimerApellido").val(d.ind_PrimerApellido);
                $("#infoContrib_ind_SegundoApellido").val(d.ind_SegundoApellido);
                $("#infoContrib_ind_Telefono").val(d.ind_Telefono);
                $("#infoContrib_ind_Email").val(d.ind_Email);
                $("#infoContrib_ind_Direccion").val(d.ind_Direccion);

                $('#modal-InfoContribuyente').modal({ backdrop: 'static', keyboard: false });
                $('#modal-InfoContribuyente').modal('show');
            },
            error: function () {
                swal({
                    type: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo consultar la información del contribuyente.',
                });
            }
        });
    }

    /**
     * guardarInformacionContribuyente: guarda los cambios hechos en el modal
     * de Información del Contribuyente.
     */
    guardarInformacionContribuyente() {
        var formData = $("#formInfoContribuyente").serialize() + "&funcion=2";

        $.ajax({
            url: '../business/controller/class.contribuyentes.php',
            type: 'POST',
            dataType: 'json',
            data: formData,
            success: function (arr) {
                if (arr.ok == 1) {
                    swal({
                        type: 'success',
                        title: 'Guardado',
                        text: 'La información del contribuyente se actualizó correctamente.',
                    });
                    $("#modal-InfoContribuyente").modal('hide');
                } else {
                    swal({
                        type: 'error',
                        title: 'No se pudo guardar',
                        text: arr.mensaje || 'Intente nuevamente.',
                    });
                }
            },
            error: function () {
                swal({
                    type: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo guardar la información del contribuyente.',
                });
            }
        });
    }

    async crearEstablecimientos() {

        establecimientos.limpiarTablaActividades();
        //Parametro: 27 (2= Modulo Usuario, 7:Permiso Crear Usuario)
        var permiso = await _permisos.getPermisos(idRol, 1640);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $("#formCrearEstablecimientos").trigger("reset");
            $("#btnCrearEstablecimientos").empty();
            $("#btnCrearEstablecimientos").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearEstablecimientos").attr('action', 'javascript:establecimientos.postEstablecimientos()');
            $('#modal-Establecimientos').modal({backdrop: 'static', keyboard: false})
            $("#modal-Establecimientos").modal('show');
        }
    }

    async crearEstablecimientosContribuyentes() {
        swal({
            type: 'info',
            title: 'Creación de establecimiento',
            text: 'Para la creación de un nuevo establecimiento diríjase de manera presencial a la secretaria de Hacienda del municipio de Paipa o realice el trámite a través del correo impuestos@paipa-boyaca.gov.co.',
            confirmButtonText: 'Entendido',
            allowOutsideClick: false,
            allowEscapeKey: false
        });
    }


    /**
     * draw_table_documents: Método para pintar la tabla 
     * de ActividadesComercio 
     * @param type $arrFilter: Listado de objetos ActividadesComercio
     */
    draw_table_documents(arrFilter) {

        $("#establecimientosRegistrados").DataTable().destroy();
        $("#bodyEstablecimientosRegistrados").empty();
        for (let dep of arrFilter) {
            if (dep.est_Activos == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Establecimiento";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Establecimiento";
            }

           // var soporteRit = '<a href="../extensiones/soporterit.php?codigo=' + dep.est_Id + '" target="_blank" title="Descargar RIT" class="btn btn-info btn-pill"><i class="fa fa-download"></i> RIT</a>';
            

                var soporteRit = '';

                if (dep.est_Id && dep.est_Id !== "null") {
                    soporteRit =
                        '<a href="../extensiones/ritActualizado.php?codigo=' + dep.est_Id + '" ' +
                        'target="_blank" class="btn btn-info btn-sm mr-1" ' +
                        'title="Descargar RIT">' +
                        '<i class="fa fa-download"></i>' +
                        '</a>';
                }


                $('#bodyEstablecimientosRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    dep.est_Nombre + 
                    '</td>' +
                    '<td>' +
                    dep.strNombreContribuyente +
                    '</td>' +
                    '<td>' +
                    dep.strDocumentoContribuyente +
                    '</td>' +
                    '<td>' +
                    dep.est_Direccion +
                    '</td>' +
                    '<td align="center" style="white-space:nowrap;">' +
                    
                    '<button type="button" class="btn btn-warning btn-sm mr-1" ' +
                        'data-toggle="tooltip" title="Editar Establecimiento" ' +
                        'onclick="establecimientos.editarEstablecimiento(' + dep.est_Id + ')">' +
                        '<i class="fa fa-pencil"></i>' +
                    '</button>' +
                           
                    '</td>'+      
                    '<td align="center" style="white-space:nowrap;">' +       
                    soporteRit +
                    
                    '</td>'+
/*
                        '<button type="button" class="btn btn-primary btn-sm mr-1" ' +
                            'data-toggle="tooltip" title="Crear Declaración" ' +
                            'onclick="establecimientos.crearDeclaracion(' + dep.est_Id + ',' + dep.est_IdContribuyente + ')">' +
                            '<i class="fa fa-file-text-o"></i>' +
                        '</button>' +

                        '<button type="button" class="btn btn-success btn-sm" ' +
                            'data-toggle="tooltip" title="Consultar Declaraciones" ' +
                            'onclick="establecimientos.consultarDeclaraciones(' + dep.est_Id + ')">' +
                            '<i class="fa fa-search"></i>' +
                        '</button>' +
*/


                    '</td>'+

                    '</tr>'
                );
            
        }
    
        establecimientos.init_table();
    }


    // 🔹 Método para cargar la información de un establecimiento en el formulario de edición
    editarEstablecimiento(id) {

        establecimientos.limpiarTablaActividades();
        
        $('#loading').show();
        $('#wrapper').addClass('body-load');

        $.ajax({
            url: '../business/controller/class.establecimientos.php',
            type: 'POST',
            dataType: 'json',
            data: {
                funcion: 3, // 👈 consultar por ID
                est_Id: id
            },
            success: function (arr) {

                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok !== 1) {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo cargar el establecimiento'
                    });
                    return;
                }   

                const d = arr.datos[0];
                console.log(arr);

                // 🔹 llenar campos
                $("#est_Codigo").val(d.est_Codigo);
                $("#est_Nombre").val(d.est_Nombre);
                if(d.est_Nombre === "Sin Establecimiento"){
                    $("#chkSinEstablecimiento").prop("checked", true);
                    $("#est_Nombre").prop("readonly", true);
                }else{
                    $("#chkSinEstablecimiento").prop("checked", false);
                    $("#est_Nombre").prop("readonly", false);
                }

                $("#est_Direccion").val(d.est_Direccion);
                $("#est_Pais").val(d.est_Pais);
                $("#est_Departamento").val(d.est_Departamento);
                $("#est_Ciudad").val(d.est_Ciudad);
                $("#est_Barrio").val(d.est_Barrio);
                $("#est_Correo").val(d.est_Correo);

                // $("#est_Telefono").val(d.est_Telefono);
                $("#est_Activos").val(d.est_Activos);
                $("#est_Area").val(d.est_Area);
                // $("#est_Persona").val(d.est_Persona);

                $("#est_OpcionUso").val(d.est_Opcion_uso);
                //$("#est_Causal").val(d.est_Causal);

                $("#est_Cedula_representante").val(d.est_Cedula_representante);
                $("#est_Nombre_representante").val(d.est_Nombre_representante);
                $("#est_Email_representante").val(d.est_Email_representante);

                //$("#est_EstadoRegistro").val(d.est_Estado_registro);
                $("#est_Matricula").val(d.est_Matricula);
                $("#est_Fecha_matricula").val(d.est_Fecha_matricula ? d.est_Fecha_matricula.date.substring(0,10) : '');
                $("#est_Fecha_inscripcion").val(d.est_Fecha_inscripcion ? d.est_Fecha_inscripcion.date.substring(0,10) : '');
                $("#est_Fecha_inicio").val(d.est_Fecha_inicio ? d.est_Fecha_inicio.date.substring(0,10) : '');

                $("#est_Exento").prop("checked", d.est_Exento == 1);
                $("#est_Excento_avisos").prop("checked", d.est_Excento_avisos == 1);

                $("#est_Rut").val(d.est_Rut);
                $("#est_Rut_segundo").val(d.est_Rut_segundo);
                $("#est_Rut_tercero").val(d.est_Rut_tercero);
                $("#est_Fecha_actividad").val(d.est_Fecha_actividad ? d.est_Fecha_actividad.date.substring(0,10) : '');

                $("#est_Cedula_contador").val(d.est_Cedula_contador);
                $("#est_Nombre_contador").val(d.est_Nombre_contador);
                // Los correos viven en el contribuyente (la declaracion es
                // una sola por contribuyente), por eso vienen del join.
                $("#ind_EmailContador").val(d.ind_EmailContador || '');
                $("#ind_EmailRevisor").val(d.ind_EmailRevisor || '');
                $("#est_Tarjeta_profesional").val(d.est_Tarjeta_profesional);
                $("#est_Cedula_revisor").val(d.est_Cedula_revisor);
                $("#est_Nombre_revisor").val(d.est_Nombre_revisor);
                $("#est_Tarjeta_profesional_revisor").val(d.est_Tarjeta_profesional_revisor);

                $("#est_Observacion_cierre").val(d.est_Observacion_cierre);
                
                $("#est_Autorizacion").prop("checked", d.est_Autorizacion == 1);

                
                establecimientos.cargarActividadesEditar(d.actividades);

/* Aun no se han activado 
$("#est_CodigoCatastral").val(d.est_CodigoCatastral);

$("#est_FechaCierre").val(d.est_FechaCierre ? d.est_FechaCierre.date.substring(0,10) : '');
$("#est_NoResolucion").val(d.est_NoResolucion);

                // 🔹 limpiar PDFs (no se pueden precargar)
                $("#est_Pdf1").val('');
                $("#est_Pdf2").val('');
                $("#est_Pdf3").val('');
*/
                // 🔹 configurar botón
                $("#btnCrearEstablecimientos").html(
                    '<span class="ti-reload"></span> Actualizar'
                );

                $("#formCrearEstablecimientos")
                    .attr('action', `javascript:establecimientos.postEditarEstablecimiento(${id})`);

                $('#modal-Establecimientos')
                    .modal({ backdrop: 'static', keyboard: false })
                    .modal('show');
            }
        });
    }


    cargarActividadesEditar(actividades){

    $("#tbodyActividadesEstablecimiento").empty();

    if(!actividades || actividades.length === 0){
        return;
    }

    actividades.forEach(function(act){

        let texto = act.acc_Codigo + " - " + act.acc_Nombre;

        $("#tbodyActividadesEstablecimiento").append(`

            <tr>

                <td>${act.acc_Codigo}</td>

                <td>
                    ${texto}
                    <input type="hidden"
                        class="ace_IdCodigoActividad"
                        value="${act.ace_IdCodigoActividad}">
                </td>

                <td style="display:none;">
                    ${act.ace_Anio}
                    <input type="hidden"
                        class="ace_Anio"
                        value="${act.ace_Anio}">
                </td>

                <td class="text-center">

                    <button class="btn btn-danger btn-sm"
                        onclick="$(this).closest('tr').remove()">
                        <i class="fa fa-trash"></i>
                    </button>

                </td>

            </tr>

        `);

    });

    $("#ace_IdCodigoActividad").val('').trigger('change');
    

}


// 🔹 Método para actualizar un establecimiento
    postEditarEstablecimiento(idEstablecimiento) {

    if (!$("#est_Autorizacion").is(":checked")) {
        swal({
            type: 'warning',
            title: 'Autorización requerida',
            text: 'Debe autorizar las notificaciones electrónicas para continuar.'
        });
        return;
    }

     let actividades = [];

    $("#tbodyActividadesEstablecimiento tr").each(function(){

        actividades.push({
            ace_IdCodigoActividad: $(this).find(".ace_IdCodigoActividad").val(),
            ace_Anio: $(this).find(".ace_Anio").val()
        });

    });

    $('#loading').show();
    $('#wrapper').addClass('body-load');

    let formData = {
        funcion: 2, // 🔴 editar establecimiento
        est_Id: idEstablecimiento,

        est_Codigo: $("#est_Codigo").val(),
        est_Nombre: $("#est_Nombre").val(),
        est_Direccion: $("#est_Direccion").val(),
        est_Pais: $("#est_Pais").val(),
        est_Departamento: $("#est_Departamento").val(),
        est_Ciudad: $("#est_Ciudad").val(),
        est_Barrio: $("#est_Barrio").val(),
        est_Correo: $("#est_Correo").val(),

        // est_Telefono: $("#est_Telefono").val(),
        //est_Activos: $("#est_Activos").val(),
        //est_Area: $("#est_Area").val(),
        est_Activos: 0,
        est_Area: 0,
        //est_Persona: $("#est_Persona").val(),

        est_Opcion_uso: $("#est_OpcionUso").val(),
        //est_Causal: $("#est_Causal").val(),
        
        est_Cedula_representante: $("#est_Cedula_representante").val(),
        est_Nombre_representante: $("#est_Nombre_representante").val(),
        est_Email_representante: $("#est_Email_representante").val(),

        //est_EstadoRegistro: $("#est_EstadoRegistro").val(),
        est_Matricula: $("#est_Matricula").val(),
        est_Fecha_matricula: $("#est_Fecha_matricula").val(),
        est_Fecha_inscripcion: $("#est_Fecha_inscripcion").val(),
        est_Fecha_inicio: $("#est_Fecha_inicio").val(),
        
        est_Excento_avisos: $("#est_Excento_avisos").is(":checked") ? 1 : 0,
        est_Exento: $("#est_Exento").is(":checked") ? 1 : 0,
        //est_Local_municipio: $("#est_Local_municipio").is(":checked") ? 1 : 0,

        est_Rut: $("#est_Rut").val(),
        est_Rut_segundo: $("#est_Rut_segundo").val(),
        est_Rut_tercero: $("#est_Rut_tercero").val(),
        est_Fecha_actividad: $("#est_Fecha_actividad").val(),

        est_Cedula_contador: $("#est_Cedula_contador").val(),
        est_Nombre_contador: $("#est_Nombre_contador").val(),
        est_Tarjeta_profesional: $("#est_Tarjeta_profesional").val(),

        est_Cedula_revisor: $("#est_Cedula_revisor").val(),
        est_Nombre_revisor: $("#est_Nombre_revisor").val(),
        est_Tarjeta_profesional_revisor: $("#est_Tarjeta_profesional_revisor").val(),

        est_Observacion_cierre: $("#est_Observacion_cierre").val(),
        est_Autorizacion: $("#est_Autorizacion").is(":checked") ? 1 : 0,

/*  Aun no se han activado
est_CodigoCatastral: $("#est_CodigoCatastral").val(),

est_FechaCierre: $("#est_FechaCierre").val(),
est_NoResolucion: $("#est_NoResolucion").val(),
*/
    };

     formData.actividades = JSON.stringify(actividades);

    $.ajax({
        url: '../business/controller/class.establecimientos.php',
        type: 'POST',
        dataType: 'json',
        data: formData,
        success: function (arr) {

            $('#loading').hide();
            $('#wrapper').removeClass('body-load');

            if (arr.ok == 1) {

                // Los correos de contador/revisor se guardan aparte porque
                // pertenecen al CONTRIBUYENTE, no al establecimiento (la
                // declaracion es una sola por contribuyente).
                $.ajax({
                    url: '../business/controller/class.establecimientos.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        funcion: 20,
                        est_Id: idEstablecimiento,
                        ind_EmailContador: $("#ind_EmailContador").val(),
                        ind_EmailRevisor:  $("#ind_EmailRevisor").val()
                    }
                });

                $("#formCrearEstablecimientos").trigger("reset");
                $("#modal-Establecimientos").modal('hide');
                establecimientos.getEstablecimientos();

                swal({
                    type: 'success',
                    title: 'Establecimiento actualizado',
                    text: 'La información fue actualizada correctamente'
                });

            } else if(arr.ok == 2){
            
                 swal({
                    type: 'error',
                    title: 'Establecimiento No Actualizado',
                    text: arr.mensaje
                });
            }else {
                swal({
                    type: 'error',
                    title: 'Error',
                    text: arr.mensaje || 'No se pudo actualizar el establecimiento'
                });
            }
        },
        error: function (XMLHttpRequest) {

            $('#loading').hide();
            $('#wrapper').removeClass('body-load');

            $("#formCrearEstablecimientos").trigger("reset");
            $("#modal-Establecimientos").modal('hide');
                //establecimientos.getEstablecimientos();

                swal({
                    type: 'success',
                    title: 'Establecimiento actualizado',
                    text: 'La información fue actualizada correctamente'
                });

/*
            swal({
                type: 'error',
                title: 'Error del servidor',
                text: 'No se recibió respuesta válida'
            });
*/
            console.log(XMLHttpRequest.responseText);
        }
    });
}



crearDeclaracion(idEstablecimiento,idContribuyente) {

    $('#loading').show();
    $('#wrapper').addClass('body-load');

    $.ajax({
        url: '../business/controller/class.declaracionesIca.php',
        type: 'POST',
        dataType: 'json',
        data:{
            funcion:1,
            dec_IdEstablecimiento:idEstablecimiento,
            dec_IdContribuyente:idContribuyente
        },
        success:function(arr){
            
            console.log(arr);
            $('#loading').hide();
            $('#wrapper').removeClass('body-load');

            if(arr.ok != 1){
                swal("Error","No se pudo crear la declaración","error");
                return;
            }

            const d = arr.datos;

            console.log(d.dec_Id);

            establecimientos.cargarActividades(idEstablecimiento);

            $("#numDeclaracion").val(d.dec_Id);
            $("#anioDeclaracion").val(d.dec_AnioDeclaracion);
            $("#periodoDeclaracion").val(d.dec_MesDeclaracion);

            $("#fechaDeclaracion").val(d.dec_FechaDeclaracion);
            $("#horaDeclaracion").val(d.dec_HoraDeclaracion);

            $("#opcionUso").val(d.dec_OpcionUso);

            $("#modal-CrearDeclaracion")
            .data("idDeclaracion", d.dec_Id);

            $("#btnValidarDeclaracion").prop("disabled", false);
            $("#btnDescargarPDF").prop("disabled", true);
            $("#btnGenerarOficial").prop("disabled", true);

            $('#modal-CrearDeclaracion').modal({
                backdrop:'static',
                keyboard:false
            });

            //establecimientos.cargarActividades(idEstablecimiento);

        }
    });
}


    consultarDeclaraciones(idEstablecimiento) {

    $("#tbodyDeclaraciones").empty();

        const declaraciones = [
            {
                anio: 2026,
                mes: "Abril",
                numero: "202600175",
                fecha_pago: "No Aplica",
                banco: "No APlica",
                valor: "44.000"
            }
        ];

        declaraciones.forEach(d => {
            $("#tbodyDeclaraciones").append(`
                <tr>
                    <td>${d.anio}</td>
                    <td>${d.mes}</td>
                    <td>${d.numero}</td>
                    <td>${d.fecha_pago}</td>
                    <td>${d.banco}</td>
                    <td style="text-align:right;">$ ${d.valor.toLocaleString()}</td>

                    <td class="text-center">
                      <a href="" 
                            class="btn btn-warning btn-sm"
                            title="Descargar Declaración">
                                <i class="fa fa-pencil"></i>
                            </a>
                    </td>                 

                    <td class="text-center">
                      <a href="../extensiones/declaracion.php?id=${d.numero}" 
                            target="_blank"
                            class="btn btn-outline-primary btn-sm"
                            title="Descargar Declaración">
                                <i class="fa fa-download"></i>
                            </a>
                    </td>


                    <td class="text-center">
                      <a href="../extensiones/declaracion.php?id=${d.numero}" 
                            target="_blank"
                            class="btn btn-outline-primary btn-sm"
                            title="Descargar Declaración">
                                <i class="fa fa-download"></i>
                            </a>
                    </td>

                    
                </tr>
            `);
        });


        $('#modal-ConsultarDeclaraciones').modal({
            backdrop: 'static',
            keyboard: false
        }).modal('show');

    }


    descargarDeclaracion(numeroDeclaracion) {

        // Ejemplo de URL (ajústala luego)
        window.open(
            `../extensiones/declaracion_pdf.php?numero=${numeroDeclaracion}`,
            '_blank'
        );
    }


    /**
     * init_table: Método para asignar la
     * propiedad DataTable() a la tabla de Dependencia
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
            "lengthMenu": [
                [5, 10, 25, 50, -1],
                [5, 10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'Establecimientos registrados',
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
     * getDependencia: Método para consultar conceptos
     */
    getEstablecimientos() {
        
        $.ajax({
            url: '../business/controller/class.establecimientos.php',
            data: { funcion: 3 , est_IdContribuyente : idContribuyente },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('getConceptos');
                console.log('arr ', arr);
                if (arr.ok == 1) {
                    $("#bodyEstablecimientosRegistrados").empty();
                    establecimientos.draw_table_documents(arr.datos);
                } else {
                    $("#establecimientosRegistrados").DataTable().destroy();
                    establecimientos.init_table();
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * getUsuarioById: Método para consultar la
     * información de un Dependencia
     * @param type $id: llave primaria de la tabla Dependencia
     */
    async getConceptosById(id) {
        
        $('#loading').show();
        $('#wrapper').addClass('body-load');
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
                url: '../business/controller/class.conceptos.php',
                data: { funcion: 3, acc_Id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        console.log('arr editar ', arr);
                        for (let datos of arr.datos) {
                            $("#con_Codigo").val(datos.con_Codigo);
                            $("#con_Nombre").val(datos.con_Nombre);
                            $("#con_Observaciones").val(datos.con_Observaciones);
                        }
                        $("#formCrearConceptos").attr('action', 'javascript:conceptos.editConceptos(' + id + ')');
                        $("#btnCrearConceptos").empty();
                        $("#btnCrearConceptos").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $('#modal-Conceptos').modal({backdrop: 'static', keyboard: false})
                        $("#modal-Conceptos").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información delos Conceptos',
                        });
                    }
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                }
            });
        }
    }

    postEstablecimientos() {

        if (!$("#est_Autorizacion").is(":checked")) {
            swal({
                type: 'warning',
                title: 'Autorización requerida',
                text: 'Debe autorizar las notificaciones electrónicas para continuar.'
            });
            return;
        }


        if($("#est_OpcionUso").val() == "3"){

            let fecha = $("#est_FechaCese").val();
            let pdf = $("#est_PdfCese")[0].files.length;

            if(!fecha){
                swal("Debe ingresar la fecha de cese");
                return;
            }

            if(pdf === 0){
                swal("Debe adjuntar el PDF de cese");
                return;
            }

        }


        let actividades = [];

        $("#tbodyActividadesEstablecimiento tr").each(function(){

            actividades.push({
                ace_IdCodigoActividad: $(this).find(".ace_IdCodigoActividad").val(),
                ace_Anio: $(this).find(".ace_Anio").val()
            });

        });

     
        $('#loading').show();
        $('#wrapper').addClass('body-load');

        let formData = {
            funcion: 1,
            //est_Codigo: $("#est_Codigo").val(),
            est_Codigo: establecimientos.generarCodigoUnico(),
            est_IdContribuyente: idContribuyente,
            est_Nombre: $("#est_Nombre").val(),
            est_Direccion: $("#est_Direccion").val(),
            est_Pais: $("#est_Pais").val(),
            est_Departamento: $("#est_Departamento").val(),
            est_Ciudad: $("#est_Ciudad").val(),
            est_Barrio: $("#est_Barrio").val(),
            est_Correo: $("#est_Correo").val(),

            //est_Telefono: $("#est_Telefono").val(),
            est_Activos: $("#est_Activos").val(),
            est_Area: $("#est_Area").val(),
            //est_Persona: $("#est_Persona").val(),

            est_Opcion_uso: $("#est_OpcionUso").val(),
            //est_Causal: $("#est_Causal").val(),
            est_Cedula_representante: $("#est_Cedula_representante").val(),
            est_Nombre_representante: $("#est_Nombre_representante").val(),
            est_Email_representante: $("#est_Email_representante").val(),

            //est_EstadoRegistro: $("#est_EstadoRegistro").val(),
            
            est_Matricula: $("#est_Matricula").val(),
            est_Fecha_matricula: $("#est_Fecha_matricula").val(),
            est_Fecha_inscripcion: $("#est_Fecha_inscripcion").val(),
            est_Fecha_inicio: $("#est_Fecha_inicio").val(),

            //est_LocalMunicipio: $("#est_LocalMunicipio").is(":checked") ? 1 : 0,
            est_Exento: $("#_est_Exento").is(":checked") ? 1 : 0,
            est_ExcentoAvisos: $("#est_ExcentoAvisos").is(":checked") ? 1 : 0,
            
            est_Rut: $("#est_Rut").val(),
            est_Rut_segundo: $("#est_Rut_segundo").val(),
            est_Rut_tercero: $("#est_Rut_tercero").val(),
            est_Fecha_actividad: $("#est_Fecha_actividad").val(),
            
            est_Cedula_contador: $("#est_Cedula_contador").val(),
            est_Nombre_contador: $("#est_Nombre_contador").val(),
            est_Tarjeta_contador: $("#est_Tarjeta_profesional").val(),

            est_Cedula_revisor: $("#est_Cedula_revisor").val(),
            est_Nombre_revisor: $("#est_Nombre_revisor").val(),
            est_Tarjeta_profesional_revisor: $("#est_Tarjeta_profesional_revisor").val(),

            est_Observacion_cierre: $("#est_Observacion_cierre").val(),
            est_Autorizacion: $("#est_Autorizacion").is(":checked") ? 1 : 0,

            est_Ind_camara_comercio: 1,
            est_Activo: 1,
            

            //est_CodigoCatastral: $("#est_CodigoCatastral").val(),
            //est_FechaCierre: $("#est_FechaCierre").val(),
            //est_NoResolucion: $("#est_NoResolucion").val()

        };

        formData.actividades = JSON.stringify(actividades);

        $.ajax({
            url: '../business/controller/class.establecimientos.php',
            type: 'POST',
            dataType: 'json',
            data: formData,
            success: function(arr) {
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearEstablecimientos").trigger("reset");
                    $("#modal-Establecimientos").modal('hide');
                    establecimientos.getEstablecimientos();

                    swal({
                        type: 'success',
                        title: 'Establecimiento creado',
                        text: 'El establecimiento fue creado correctamente',
                    });

                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: arr.mensaje || 'No se pudo crear el establecimiento',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Error:', XMLHttpRequest.responseText);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                swal({
                    type: 'error',
                    title: 'Error en el servidor',
                    text: 'No se recibió respuesta válida',
                });
            }
        });
    }

    generarCodigoUnico() {
        const timestamp = Date.now().toString().slice(-5); // últimos 5 dígitos del tiempo
        const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0'); // 3 dígitos

        return parseInt(timestamp + random); // número de 8 dígitos
    }

    /**
     * cambiarEstado: Método para cambiar el estado de los ActividadesComercio
     * @param type $id_usuario:  llave primaria de la tabla ActividadesComercio
     * @param type $estado: estado actual del ActividadesComercio
     */
    async cambiarEstado(id_actividadesComercio, estado) {

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
                var title = "¿Está seguro de inactivar el concepto?";
                var subtitle = "Una vez inactivado el concepto no podra usarse";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar el concepto?";
                var subtitle = "Una vez activada, el concepto podrá usarse";
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
                        url: '../business/controller/class.conceptos.php',
                        data: { funcion: 4, con_Id: id_actividadesComercio, con_Estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {

                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                conceptos.getConceptos();
                                swal({
                                    type: 'success',
                                    title: 'Conceptos actualizados',
                                    text: 'Conceptos actualizados exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el Conceptos',
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
     * editUsuario: Método para actualizar un ActividadesComercio
     * @param type $id: llave primaria de la tabla ActividadesComercio
     */
    editConceptos(id) {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var con_Codigo = $("#con_Codigo").val();
        var con_Nombre = $("#con_Nombre").val();
        var con_Observaciones = $("#con_Observaciones").val();

        // Luego podrías construir un objeto formData o un data: {} en tu ajax con estas variables
        var formData = {
        funcion: 2,
        con_Id    : id,
        con_Codigo: con_Codigo,
        con_Nombre: con_Nombre,
        con_Observaciones: con_Observaciones
        };

        $.ajax({
            url: '../business/controller/class.conceptos.php',
            data: formData,
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearConceptos").trigger("reset");
                    $("#modal-Conceptos").modal('hide');
                    conceptos.getConceptos();
                    swal({
                        type: 'success',
                        title: 'Conceptos actualizado',
                        text: 'Conceptos actualizado exitosamente',
                    });
                }else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar el Conceptos',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    validarOpcionUso() {
        const opcion = $("#opcionUso").val();

        if (opcion === "3") {
            $("#grupoDeclaracionCorrige").show();
        } else {
            $("#grupoDeclaracionCorrige").hide();
            $("#declaracionCorrige").val("");
        }
    }

    
    validarBasesActividades(){

        let totalBase = 0;

        $("#tbodyActividades tr").each(function(){

            let base = establecimientos.numero($(this).find(".base-gravable").val());
            totalBase += base;

        });

        let ingresosGravables = establecimientos.numero($('[data-campo="ingresos_gravables"]').val());

        if(totalBase !== ingresosGravables){

            swal({
                type: 'warning',
                title: 'Validación',
                text: 'La sumatoria de las bases gravables de las actividades debe ser igual al TOTAL INGRESOS GRAVABLES.'
            });

            return false;

        }

        return true;
    }

    /**
     * UsuarioActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    UsuarioActivo() {
        $("#accordion-menu li").removeClass("active show");
        $("#accordion-menu .submenu").css("display", "none");

        $("#MICAWeb").addClass("active show");
        $("#SubICAWeb").css("display", "block");
        $("#ICAWeb_RIT").addClass("active");
    }


    cargarAniosActividades(){
/*
        let anioActual = new Date().getFullYear();

        for(let i = anioActual; i <= 2030; i++){
            $("#ace_Anio").append(`<option value="${i}">${i}</option>`);
        }
*/


    let anio = 2025;

    $.ajax({
        url: '../business/controller/class.actividadesComercio.php',
        type: 'POST',
        dataType: 'json',
        data:{
            funcion: 3,
            acc_Anio: anio
        },
        success: function(arr){

            $("#ace_IdCodigoActividad").empty();

            arr.datos.forEach(function(a){

                $("#ace_IdCodigoActividad").append(`
                    <option value="${a.acc_Id}">
                        ${a.acc_Codigo} - ${a.acc_Nombre}
                    </option>
                `);

            });

        }
    });



        $("#ace_Anio").empty(); 
        $("#ace_Anio").append(`<option value="2025">2025</option>`);
        $("#ace_Anio").val("2025"); 
        
    }


   agregarActividad(){

    let idActividad = $("#ace_IdCodigoActividad").val();
    let texto = $("#ace_IdCodigoActividad option:selected").text();
    let anio = $("#ace_Anio").val();

    if(!idActividad){
        swal("Debe seleccionar una actividad");
        return;
    }

    if(!anio){
        swal("Debe ingresar el año");
        $("#ace_Anio").focus();
        return;
    }

    // evitar duplicados
    let existe = false;

    $("#tbodyActividadesEstablecimiento tr").each(function(){

        let act = $(this).find(".ace_IdCodigoActividad").val();
        let an  = $(this).find(".ace_Anio").val();

        if(act == idActividad && an == anio){
            existe = true;
        }

    });

    if(existe){
        swal("Esa actividad ya fue agregada");
        return;
    }

    let codigo = texto.split("-")[0];

    $("#tbodyActividadesEstablecimiento").append(`

        <tr>

            <td>${codigo}</td>

            <td>
                ${texto}
                <input type="hidden"
                    class="ace_IdCodigoActividad"
                    value="${idActividad}">
            </td>

            <td style="display:none;">
                ${anio}
                <input type="hidden"
                    class="ace_Anio"
                    value="${anio}">
            </td>

            <td class="text-center">

                <button class="btn btn-danger btn-sm"
                onclick="$(this).closest('tr').remove()">
                <i class="fa fa-trash"></i>
                </button>

            </td>

        </tr>

    `);

    $("#ace_IdCodigoActividad").val('').trigger('change');
   

}



limpiarTablaActividades(){

    $("#tbodyActividadesEstablecimiento").empty();

    $("#ace_IdCodigoActividad").val('').trigger('change');
    $("#ace_Anio").val('2025');

}

    /* Calcula  totales */
calcularIngresos(){

    let totalPais = establecimientos.numero($('[data-campo="ingresos_total_pais"]').val());
    let menosMunicipio = establecimientos.numero($('[data-campo="menos_fuera_municipio"]').val());

    let devoluciones = establecimientos.numero($('[data-campo="devoluciones"]').val());
    let exportaciones = establecimientos.numero($('[data-campo="exportaciones"]').val());
    let ventaActivos = establecimientos.numero($('[data-campo="venta_activos"]').val());
    let excluidas = establecimientos.numero($('[data-campo="actividades_excluidas"]').val());
    let otrasExentas = establecimientos.numero($('[data-campo="otras_exentas"]').val());

    // INGRESOS MUNICIPIO
    let ingresosMunicipio = totalPais - menosMunicipio;

    // INGRESOS GRAVABLES
    let ingresosGravables = ingresosMunicipio
        - devoluciones
        - exportaciones
        - ventaActivos
        - excluidas
        - otrasExentas;

    $('[data-campo="ingresos_municipio"]').val(ingresosMunicipio.toLocaleString('es-CO'));
    $('[data-campo="ingresos_gravables"]').val(ingresosGravables.toLocaleString('es-CO'));

}


    
   cargarActividades(idEstablecimiento){

    $.ajax({

        url:'../business/controller/class.declaracionesIca.php',
        type:'POST',
        dataType:'json',

        data:{
            funcion:5,
            est_Id:idEstablecimiento
        },

        success:function(arr){

            $("#tbodyActividades").empty();

            if(arr.ok != 1){
                return;
            }

            arr.datos.forEach(function(a){

                $("#tbodyActividades").append(`

                    <tr>

                        <td>
                            ${a.acc_Codigo} - ${a.acc_Nombre}
                            <input type="hidden"
                            class="actividad-id"
                            value="${a.ace_IdCodigoActividad}">
                        </td>

                        <td>
                            <input type="text"
                            class="form-control base-gravable"
                            value="0">
                        </td>

                        <td>
                            <input type="text"
                            class="form-control tarifa"
                            value="${a.acc_Tarifa}"readonly>
                        </td>

                        <td>
                            <input type="text"
                            class="form-control impuesto"
                            readonly
                            value="0">
                        </td>

                    </tr>

                `);

            });

        }

    });
}


    calcularImpuestoEnergia(){

        let impuestoEnergia = 0;

        $("#tablaEnergia tbody tr").each(function(){

            impuestoEnergia += establecimientos.numero(
                $(this).find(".impuesto-energia").val()
            );

        });

        let impuestoActividades = establecimientos.numero(
            $("#totalImpuesto").val()
        );

        let total = impuestoActividades + impuestoEnergia;

        $('[data-campo="industria_comercio"]').val(
            establecimientos.formatearCOP(total)
        );

    }

limpiarNumero(valor){
    if(!valor) return 0;
    return parseInt(valor.toString().replace(/\./g,'')) || 0;
}

limpiarEntero(valor){
    if(!valor) return 0;

    let numero = parseFloat(valor.toString().replace(/,/g,'')) || 0;

    return Math.floor(numero);
}

formatearCOP(numero){
    return numero.toLocaleString('es-CO');
}

numero(valor){
    if(!valor) return 0;
    return parseFloat(valor.toString().replace(/\./g,'')) || 0;
}


calcularTotalesActividades(){

    let totalBase = 0;
    let totalImpuesto = 0;

    $("#tbodyActividades tr").each(function(){

        let base = establecimientos.numero($(this).find(".base-gravable").val());
        let impuesto = establecimientos.numero($(this).find(".impuesto").val());

        totalBase += base;
        totalImpuesto += impuesto;

    });

    $("#totalBaseGravable").val(
        establecimientos.formatearCOP(totalBase)
    );

    $("#totalImpuesto").val(
        establecimientos.formatearCOP(totalImpuesto)
    );

}



actualizarDeclaracionIca(valor, numeroCampo){
    
    let anio  = $("#anioDeclaracion").val();
    let mes   = $("#periodoDeclaracion").val();
    let numero = $("#numDeclaracion").val();
    
    let idDeclaracion = $("#numDeclaracion").val();

    let valorLimpio = this.limpiarNumero(valor);   
    console.log(valorLimpio);
    $.ajax({
        url: '../business/controller/class.declaracionesICA.php',
        type: 'POST',
        dataType: 'json',
        data:{
            funcion: 7, 
            campoSeleccionado : numeroCampo,
            valorLimpio : valorLimpio,
            idDeclaracion: idDeclaracion,
            anio: anio,
            mes: mes,
            numero: numero
        },
        success: function(arr){

            $('#loading').hide();
            $('#wrapper').removeClass('body-load');

            if(arr.ok != 1){
                swal("Error","No se pudieron guardar las actividades","error");
                return;
            }       
            

            let d = arr.datos;
            console.log (arr.datos);
            $('[data-campo="industria_comercio"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto1)));
            $('[data-campo="avisos_tableros"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto2)));
            $('[data-campo="sobretasa_bomberil"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto3)));
            
            $('[data-campo="total_impuesto_cargo"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto4)));

            $('[data-campo="valor_exencion_exoneracion"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto5)));
            $('[data-campo="menos_retenciones"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto6)));
            $('[data-campo="menos_autoretenciones"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto7)));
            $('[data-campo="anticipo_anterior"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto8)));
            $('[data-campo="anticipo_siguiente"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto9)));
            $('[data-campo="sanciones"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto10)));
            $('[data-campo="saldo_favor_vigencias_anteriores"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto11)));
            
            
            $('[data-campo="total_saldo_a_cargo"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto12)));
            $('[data-campo="total_saldo_a_favor"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto13)));
            $('[data-campo="valor_a_pagar"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto14)));

            $('[data-campo="descuento_pronto_pago"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto15)));

            $('[data-campo="total_a_pagar"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto20)));

            swal({
                type: 'success',
                title: 'Liquidación Actualizada',
                text: 'Campos Actualizados'
            });



        }
    })

};







}

const establecimientos = new Establecimientos();

establecimientos.getEstablecimientos();
establecimientos.UsuarioActivo();

$(document).ready(function(){
    establecimientos.cargarAniosActividades();
     // Ocultar al iniciar
    establecimientos.validarOpcionUso();

    // Detectar cambio
    $("#opcionUso").on("change", function () {
        establecimientos.validarOpcionUso();
    });
});

$(document).on("keyup change",".campo-total",function(){

    let valor = establecimientos.limpiarNumero($(this).val());
    $(this).val(establecimientos.formatearCOP(valor));
    establecimientos.calcularIngresos();
});

$(document).on("change", ".campo-total", function(){

    let valor = $(this).val();
    let numeroCampo = $(this).attr("numeroCampo");
    
    establecimientos.actualizarDeclaracionIca(valor, numeroCampo);

});


$("#ace_Anio").on("change", function(){

    let anio = $(this).val();

    if(anio == "") return;

    $.ajax({
        url: '../business/controller/class.actividadesComercio.php',
        type: 'POST',
        dataType: 'json',
        data:{
            funcion: 3,
            acc_Anio: anio
        },
        success: function(arr){

            $("#ace_IdCodigoActividad").empty();

            arr.datos.forEach(function(a){

                $("#ace_IdCodigoActividad").append(`
                    <option value="${a.acc_Id}">
                        ${a.acc_Codigo} - ${a.acc_Nombre}
                    </option>
                `);

            });

        }
    });

});

$(document).on("keypress",".base-gravable",function(e){
    if(!/[0-9]/.test(String.fromCharCode(e.which))){
        e.preventDefault();
    }
});

$(document).on("input", ".base-gravable", function () {

    let fila = $(this).closest("tr");

    let base = establecimientos.numero(fila.find(".base-gravable").val());
    let tarifa = parseFloat(fila.find(".tarifa").val()) || 0;

    let impuesto = base * tarifa;

    fila.find(".impuesto").val(establecimientos.formatearCOP(impuesto));
    fila.find(".base-gravable").val(establecimientos.formatearCOP(base));

    establecimientos.calcularTotalesActividades();
    //establecimientos.calcularImpuestoEnergia();

});

$(document).on("input",".impuesto-energia",function(){

    let valor = establecimientos.numero($(this).val());

    $(this).val(
        establecimientos.formatearCOP(valor)
    );

    //establecimientos.calcularImpuestoEnergia();

});

$("#btnCrearDeclaracion").off("click").on("click", function () {

    // Aquí luego irá tu AJAX real
    // Simulación temporal:
    const numeroGenerado = "2024000" + Math.floor(Math.random() * 900 + 100);

    $("#numDeclaracion").val(numeroGenerado);

    // Activar botones
    $("#btnValidarDeclaracion").prop("disabled", false);
    $("#btnDescargarPDF").prop("disabled", true);
    $("#btnGenerarOficial").prop("disabled", true);


    swal({
        type: 'success',
        title: 'Declaración creada',
        text: 'Ahora puede liquidar e imprimir'
    });

});

$("#btnGenerarOficial").off("click").on("click", function () {


       swal({
                type: 'success',
                title: 'Declaración Actualizada',
                text: 'Puede proceder a firmarla en el boton de consulta.'
            });

    

        $('#modal-CrearDeclaracion').modal('hide');

    /*
    swal({
        title: 'Declaración con pago',
        text: '¿La declaración se genera con pago?',
        type: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí',
        cancelButtonText: 'No',
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d'
    }).then((result) => {

        // result.value === true  → Sí
        // result.dismiss === 'cancel' → No

        const conPago = result.value === true ? 1 : 0;

        // Aquí luego puedes guardar conPago en BD si lo necesitas
        // ejemplo: estado_pago = conPago

        $('#modal-CrearDeclaracion').modal('hide');

        swal({
            type: 'warning',
            title: 'Declaración Liquidada Exitosamente',
            text:  'Para la PRESENTACIÓN y PAGO de la Declaración diríjase a las entidades financieras o realice transferecia bancaria y envíe los respectivos soportes al correo: impuestos@paipa-boyaca.gov.co.',
            confirmButtonText: 'Entendido'
        });

    });
*/





});

/*
$("#btnValidarDeclaracion").on("click", function () {

     if(!establecimientos.validarBasesActividades()){
        return;
    }
    $("#btnDescargarPDF").prop("disabled", false);
    $("#btnGenerarOficial").prop("disabled", false);

    swal({
        type: 'success',
        title: 'Liquidación realizada',
        text: 'Cálculos aplicados correctamente'
    });

   
});
*/

$("#btnValidarDeclaracion").off("click").on("click", function () {

    if(!establecimientos.validarBasesActividades()){
        return;
    }

    let totales = {
        dec_TotalIngresos: establecimientos.numero($('[data-campo="ingresos_total_pais"]').val()),
        dec_IngresosFueraMunicipio: establecimientos.numero($('[data-campo="menos_fuera_municipio"]').val()),
        dec_IngresosDevoluciones: establecimientos.numero($('[data-campo="devoluciones"]').val()),
        dec_IngresosExportaciones: establecimientos.numero($('[data-campo="exportaciones"]').val()),
        dec_IngresosVentas: establecimientos.numero($('[data-campo="venta_activos"]').val()),
        dec_IngresosActividades: establecimientos.numero($('[data-campo="actividades_excluidas"]').val()),
        dec_IngresosOtrasActividades: establecimientos.numero($('[data-campo="otras_exentas"]').val()),
        dec_BaseGravable: establecimientos.numero($('[data-campo="ingresos_gravables"]').val())
    };

    let idDeclaracion = $("#numDeclaracion").val();
    let actividades = [];

    $("#tbodyActividades tr").each(function(){

        actividades.push({
            dia_IdDeclaracion: idDeclaracion,
            dia_IdActividad: $(this).find(".actividad-id").val(),
            dia_BaseGravable: establecimientos.numero($(this).find(".base-gravable").val()),
            dia_Tarifa: parseFloat($(this).find(".tarifa").val()) || 0,
            dia_ValorImpuesto: establecimientos.numero($(this).find(".impuesto").val())
        });

    });

    
    let anio  = $("#anioDeclaracion").val();
    let mes   = $("#periodoDeclaracion").val();
    let numero = $("#numDeclaracion").val();
    
    if(actividades.length === 0){
        swal("Error","No hay actividades para guardar","error");
        return;
    }

    //$('#loading').show();
    //$('#wrapper').addClass('body-load');

    $.ajax({
        url: '../business/controller/class.declaracionesICA.php',
        type: 'POST',
        dataType: 'json',
        data:{
            funcion: 6, 
            actividades: JSON.stringify(actividades),
            idDeclaracion: idDeclaracion,
            totales: JSON.stringify(totales),
            anio: anio,
            mes: mes,
            numero: numero
        },
        success: function(arr){

            $('#loading').hide();
            $('#wrapper').removeClass('body-load');

            if(arr.ok != 1){
                swal("Error","No se pudieron guardar las actividades","error");
                return;
            }       
            
            
            $("#btnDescargarPDF").prop("disabled", false);
            $("#btnGenerarOficial").prop("disabled", false);

            let d = arr.datos;
            console.log (arr.datos);
            $('[data-campo="industria_comercio"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto1)));
            $('[data-campo="avisos_tableros"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto2)));
            $('[data-campo="sobretasa_bomberil"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto3)));
            
            $('[data-campo="total_impuesto_cargo"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto4)));

            $('[data-campo="valor_exencion_exoneracion"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto5)));
            $('[data-campo="menos_retenciones"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto6)));
            $('[data-campo="menos_autoretenciones"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto7)));
            $('[data-campo="anticipo_anterior"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto8)));
            $('[data-campo="anticipo_siguiente"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto9)));
            $('[data-campo="sanciones"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto10)));
            $('[data-campo="saldo_favor_vigencias_anteriores"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto11)));
            
            
            $('[data-campo="total_saldo_a_cargo"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto12)));
            $('[data-campo="total_saldo_a_favor"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto13)));
            $('[data-campo="valor_a_pagar"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto14)));

            $('[data-campo="descuento_pronto_pago"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto15)));

            $('[data-campo="total_a_pagar"]').val(establecimientos.formatearCOP(establecimientos.limpiarEntero(d.dec_ValorConcepto20)));

            swal({
                type: 'success',
                title: 'Liquidación realizada',
                text: 'Actividades guardadas y SP ejecutado correctamente'
            });



        }
    })

});







// Activar bloque sanciones
$(document).on("change", "#chkSanciones", function(){

    if($(this).is(":checked")){
        $("#boxSanciones").slideDown();
    } else {
        $("#boxSanciones").slideUp();
        $("input[name='tipoSancion']").prop("checked", false);
        $("#inputOtraSancion").hide();
        $("#txtOtraSancion").val('');

    }

});

// Mostrar input "Otra"
$(document).on("change", "input[name='tipoSancion']", function(){

    if($(this).val() === "otra"){
        $("#inputOtraSancion").show();
    } else {
        $("#inputOtraSancion").hide();
        $("#txtOtraSancion").val('');
    }

});

// Validar si cuenta con establecimiento
$(document).on("change", "#chkSinEstablecimiento", function(){

    if($(this).is(":checked")){
        $("#est_Nombre").val("Sin Establecimiento");
        $("#est_Nombre").prop("readonly", true);
    }else{
        $("#est_Nombre").val("");
        $("#est_Nombre").prop("readonly", false);
    }

});




$(document).on("change", "#est_OpcionUso", function(){

    let valor = $(this).val();

    if(valor == "3"){
        $("#boxCeseActividades").slideDown();
    }else{
        $("#boxCeseActividades").slideUp();

        // limpiar campos
        $("#est_FechaCese").val('');
        $("#est_PdfCese").val('');
    }

});

/*
$(document).on("change", "#est_OpcionUso", function(){

    let valor = $(this).val();

    if(valor == "3"){ // Cese de Actividades
        $("#modalCeseActividades").modal("show");
    }

});

$("#btnGuardarCese").on("click", function(){

    let fecha = $("#fechaCese").val();
    let pdf = $("#pdfCese")[0].files[0];

    if(!fecha){
        swal("Debe seleccionar la fecha");
        return;
    }

    if(!pdf){
        swal("Debe cargar el PDF");
        return;
    }

    // puedes guardar en variables globales si necesitas enviarlo luego
    window.ceseData = {
        fecha: fecha,
        pdf: pdf
    };

    $("#modalCeseActividades").modal("hide");
});
*/
