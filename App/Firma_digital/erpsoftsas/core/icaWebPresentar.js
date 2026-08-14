/*    METDOS DEL MODULO DE DEPENDENCIA    */

var enable = true;
var idRol = localStorage.getItem('id_Rol');
var idContribuyente = localStorage.getItem('id_Contribuyente');

class Establecimientos {

    constructor() {}

    /**
     * crearUsuario: Método para abrir modal de creación de ActividadesComercio.
     */
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
            if (typeof Geografia !== 'undefined') {
                Geografia.poblar('est_Departamento', 'est_Ciudad');
            }
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

        // Se guarda para que pintarAccionesDeclaracionContribuyente() tenga
        // un establecimiento de referencia al crear la primera declaracion
        // (solo para auditoria; la declaracion en si es del contribuyente).
        establecimientos._ultimoListado = arrFilter;

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


                // Sin columna de Acciones: la declaracion es UNA por
                // contribuyente (no por establecimiento), asi que sus
                // botones ya no se repiten en cada fila -viven una sola vez
                // en la barra de arriba, ver pintarAccionesDeclaracionContribuyente()-.
                // Esta fila solo describe el establecimiento en si.
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
                    '</tr>'
                );

        }

        establecimientos.init_table();

        // Todos los establecimientos listados aqui son del MISMO
        // contribuyente (la pantalla siempre filtra por idContribuyente),
        // asi que basta con pintar la barra de declaracion una vez.
        establecimientos.pintarAccionesDeclaracionContribuyente();
    }


    /**
     * Barra unica (arriba de la tabla) con el estado y las acciones de la
     * declaracion DEL CONTRIBUYENTE actual. Reemplaza lo que antes era un
     * boton "Crear Declaración" + un cluster de acciones repetido en CADA
     * fila de establecimiento -con 2 o 3 establecimientos, la misma
     * declaracion aparecia 2 o 3 veces, dando la impresion de que cada
     * establecimiento tenia la suya propia, cuando en realidad es una sola-.
     */
    pintarAccionesDeclaracionContribuyente() {

        // Antes esto pintaba una barra con SOLO la ultima declaracion. Esa
        // barra la reemplazo el listado completo, pero la funcion se conserva
        // con el mismo nombre porque es la que llaman los flujos de firmar,
        // presentar y corregir para refrescar la pantalla al terminar.
        establecimientos.consultarDeclaraciones(null, idContribuyente);
    }

    /**
     * Devuelve el HTML de las acciones de la ULTIMA declaracion de un
     * establecimiento. Delega en DeclaracionesUI (core/declaraciones.ui.js),
     * el mismo modulo que usa icaWebConsultar.js, para que ambas pantallas
     * se mantengan sincronizadas.
     */
    htmlAcciones(d) {
        return DeclaracionesUI.htmlAcciones(d, 'establecimientos');
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
                $("#est_Direccion").val(d.est_Direccion);
                // Unico pais del catalogo. Se fuerza a 'Colombia' en vez de respetar
                // lo guardado porque los registros viejos tienen "1" (el value del
                // <option> fijo anterior), que no matchearia ninguna opcion y dejaria
                // el select en blanco; al guardar, el dato viejo queda saneado.
                $("#est_Pais").val('Colombia');
                // Ver nota en icaWebRit.js: catalogo completo + preseleccion.
                if (typeof Geografia !== 'undefined') {
                    Geografia.poblar('est_Departamento', 'est_Ciudad', d.est_Departamento, d.est_Ciudad);
                }
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

                <td>
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

            // Antes esto reseteaba el formulario, cerraba el modal y
            // mostraba un swal de EXITO -en el camino de ERROR-. Cualquier
            // fallo real (caida de red, 500, un Warning de PHP rompiendo el
            // JSON, el mismo modo de falla que ya tumbo el boton "Liquidar"
            // en produccion) se veia identico a un guardado exitoso, sin
            // ninguna señal de que el cambio NO se guardo. El formulario ya
            // NO se resetea ni el modal se cierra, para que la persona no
            // pierda lo que escribio y pueda reintentar.
            swal({
                type: 'error',
                title: 'No se pudo guardar',
                text: 'No se pudo actualizar el establecimiento. Revise su conexión e intente de nuevo; si persiste, avise a soporte.'
            });

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
                // El backend ya distingue casos reales (p.ej. "esta
                // declaración ya fue presentada, genere una corrección") de
                // errores genericos: mostrar SU mensaje en vez de uno fijo
                // evita que el usuario vea "no se pudo crear" sin saber por
                // que, cuando el sistema si sabe la razon exacta.
                swal("Error", arr.mensaje || "No se pudo crear la declaración", "error");
                return;
            }

            const d = arr.datos;

            console.log(d.dec_Id);

            // Las actividades se cargan del CONTRIBUYENTE (agregadas de
            // todos sus establecimientos), no de este establecimiento en
            // particular: la declaracion es una sola por contribuyente.
            establecimientos.cargarActividadesContribuyente(idContribuyente);

            $("#numDeclaracion").val(d.dec_Id);
            $("#anioDeclaracion").val(d.dec_AnioDeclaracion);
            $("#periodoDeclaracion").val(d.dec_MesDeclaracion);

            $("#fechaDeclaracion").val(d.dec_FechaDeclaracion);
            $("#horaDeclaracion").val(d.dec_HoraDeclaracion);

            $("#opcionUso").val(d.dec_OpcionUso);

            $("#modal-CrearDeclaracion")
            .data("idDeclaracion", d.dec_Id);

            // "Finalizar Declaración" (antes "Validar") es la primera accion
            // disponible al crear: nace habilitado. Quedaba deshabilitado
            // por una linea heredada de cuando existia un boton "Validar"
            // aparte que lo habilitaba a el -ese boton ya no esta en el
            // formulario, asi que nada volvia a habilitarlo nunca y la
            // liquidacion completa quedaba imposible de guardar.
            $("#btnGenerarOficial").prop("disabled", false);

              // 🔥 AQUÍ ESTÁ LA CLAVE
            $("#btnDescargarPDF")
                .prop("disabled", true)
                .attr(
                    "onclick",
                    "window.open('../extensiones/declaracion.php?dec_Id=" + d.dec_Id + "', '_blank')"
                );


            // Declaracion recien creada: el progreso arranca en "Liquidar".
            $('#stepperDeclaracion').html(DeclaracionesUI.stepperHtml(d));

            $('#modal-CrearDeclaracion').modal({
                backdrop:'static',
                keyboard:false
            });

            //establecimientos.cargarActividades(idEstablecimiento);

        }
    });
}

consultarDeclaraciones(idEstablecimiento, idContribuyente) {

    // Se guarda para que las acciones de la tabla (firmar, presentar,
    // corregir...) sepan a que contribuyente refrescar.
    establecimientos._idContribuyenteActual = idContribuyente || null;

    $("#tbodyDeclaraciones").html(
        '<tr><td colspan="8" class="text-center text-muted py-4">' +
            '<i class="fa fa-spinner fa-spin"></i> Cargando declaraciones...' +
        '</td></tr>'
    );

    $.ajax({
        url: '../business/controller/class.declaracionesIca.php',
        type: 'POST',
        dataType: 'json',
        // La declaracion es del contribuyente, no del establecimiento: se
        // filtra por ahi cuando se conoce.
        data: idContribuyente
            ? { funcion: 8, dec_IdContribuyente: idContribuyente }
            : { funcion: 8, dec_IdEstablecimiento: idEstablecimiento },
        success: function(resp){

            if(resp.ok != 1 || !Array.isArray(resp.datos) || resp.datos.length === 0){
                $("#tbodyDeclaraciones").html(
                    '<tr><td colspan="8" class="text-center text-muted py-4">' +
                        'Aún no hay declaraciones. Usa "Crear Declaración" para empezar.' +
                    '</td></tr>'
                );
                return;
            }

            // Antes solo se pintaba resp.datos[0] -la ultima-, asi que las
            // declaraciones de periodos anteriores no se veian por ningun
            // lado. Ademas se emitian 7 celdas contra una cabecera de 8
            // (faltaba Estado), y las columnas salian corridas.
            var filas = '';
            resp.datos.forEach(function (d) {

                var fechaPago = d.dec_FechaPago || 'No aplica';
                var banco     = d.dec_BancoPago || 'No aplica';
                var valor     = d.dec_ValorPago || 0;

                // Hay declaraciones antiguas con dec_NumeroDeclaracion en
                // NULL, que se pintaban con el texto literal "null".
                var numero = d.dec_NumeroDeclaracion || d.dec_Id;

                filas +=
                    '<tr>' +
                        '<td>' + d.dec_AnioDeclaracion + '</td>' +
                        '<td>' + DeclaracionesUI.nombreMes(d.dec_MesDeclaracion) + '</td>' +
                        '<td>' + numero + '</td>' +
                        '<td>' + DeclaracionesUI.chipEstado(d) + '</td>' +
                        '<td>' + fechaPago + '</td>' +
                        '<td>' + banco + '</td>' +
                        '<td style="text-align:right;">$ ' + Number(valor).toLocaleString() + '</td>' +
                        '<td class="text-center" style="white-space:nowrap;">' +
                            DeclaracionesUI.htmlAcciones(d, 'establecimientos') +
                        '</td>' +
                    '</tr>';
            });

            $("#tbodyDeclaraciones").html(filas);
        },
        error: function(){
            $("#tbodyDeclaraciones").html(
                '<tr><td colspan="8" class="text-center text-danger py-4">' +
                    '<i class="fa fa-exclamation-triangle"></i> No se pudieron cargar las declaraciones.' +
                '</td></tr>'
            );
        }
    });
}

    nombreMes(mes){
        return DeclaracionesUI.nombreMes(mes);
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
                'emptyTable': 'No hay establecimientos registrados',
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

        // La tabla de establecimientos se quito de esta pantalla. Quedan
        // llamadas a esta funcion en los flujos de guardado del modal de
        // establecimiento (hoy sin boton que los abra); sin esta guarda
        // reventarian contra un elemento que ya no existe.
        if ($("#bodyEstablecimientosRegistrados").length === 0) { return; }

        // Antes la tabla se quedaba completamente vacia (sin ninguna fila)
        // mientras llegaba la respuesta, lo que se sentia como una pantalla
        // rota en vez de "cargando".
        $("#bodyEstablecimientosRegistrados").html(
            '<tr><td colspan="4" class="text-center text-muted py-4">' +
                '<i class="fa fa-spinner fa-spin"></i> Cargando establecimientos...' +
            '</td></tr>'
        );

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
                    $("#bodyEstablecimientosRegistrados").empty();
                    $("#establecimientosRegistrados").DataTable().destroy();
                    establecimientos.init_table();
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                $("#bodyEstablecimientosRegistrados").html(
                    '<tr><td colspan="4" class="text-center text-danger py-4">' +
                        '<i class="fa fa-exclamation-triangle"></i> No se pudieron cargar los establecimientos. Intenta de nuevo.' +
                    '</td></tr>'
                );
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
            est_Codigo: $("#est_Codigo").val(),
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
        $("#ICAWeb_Presentar").addClass("active");
    }


    cargarAniosActividades(){

        let anioActual = new Date().getFullYear();

        for(let i = anioActual; i <= 2030; i++){
            $("#ace_Anio").append(`<option value="${i}">${i}</option>`);
        }
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
        swal("Esa actividad ya fue agregada para ese año");
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

            <td>
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
    $("#ace_Anio").val('');

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


    
    /**
     * Actividades del CONTRIBUYENTE para la declaracion (agregadas de
     * todos sus establecimientos, sin repetir CIIU). Pinta la misma
     * #tbodyActividades que antes llenaba cargarActividades(), asi que el
     * resto del formulario (calculo de totales, guardado) no cambia.
     */
    cargarActividadesContribuyente(idContribuyente){

        $.ajax({

            url:'../business/controller/class.declaracionesIca.php',
            type:'POST',
            dataType:'json',

            data:{
                funcion:12,
                dec_IdContribuyente:idContribuyente
            },

            success:function(arr){

                $("#tbodyActividades").empty();

                if(arr.ok != 1){
                    if (arr.mensaje) {
                        swal({
                            type: 'warning',
                            title: 'Sin actividades',
                            text: arr.mensaje
                        });
                    }
                    return;
                }

                arr.datos.forEach(function(a){

                    // n_establecimientos es informativo: en cuantos locales
                    // del contribuyente aplica esta actividad.
                    var etiquetaLocales = a.n_establecimientos > 1
                        ? ' <small class="text-muted">(' + a.n_establecimientos + ' establecimientos)</small>'
                        : '';

                    $("#tbodyActividades").append(`

                        <tr>

                            <td>
                                ${a.acc_Codigo} - ${a.acc_Nombre}${etiquetaLocales}
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
    // Delega en core/numeros.js (NumerosCOP), que es la unica definicion
    // real. Estas cuatro funciones estaban COPIADAS en tres archivos y eso
    // ya costo dos bugs: se arreglaba una copia y las otras seguian rotas
    // (ver la cabecera de core/numeros.js). Se conservan como metodos para
    // no tocar las ~200 llamadas existentes.
    return NumerosCOP.aEntero(valor);
}

limpiarEntero(valor){
    // OJO: historicamente quitaba las COMAS (no los puntos), porque recibia
    // valores crudos de la BD con punto decimal. Ese es exactamente el caso
    // de NumerosCOP.deBaseDeDatos().
    return Math.floor(NumerosCOP.deBaseDeDatos(valor));
}

formatearCOP(numero){
    return NumerosCOP.formatear(numero);
}

numero(valor){
    // Valor tal como lo ve el usuario en pantalla (formato es-CO).
    // Si el dato viene CRUDO de la base de datos hay que usar
    // NumerosCOP.deBaseDeDatos(); confundirlos multiplica por 100 el valor
    // (fue el bug de los "00000" al corregir una declaracion).
    return NumerosCOP.aCifra(valor);
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

// La pantalla abre directo en el listado de declaraciones del
// contribuyente. Ya no se pasa por la tabla de establecimientos.
establecimientos.consultarDeclaraciones(null, idContribuyente);
establecimientos.UsuarioActivo();

// Crear declaracion dejo de depender de que NO exista ninguna: antes el
// boton solo aparecia cuando el contribuyente no tenia declaracion, asi
// que no habia forma de crear la del periodo siguiente desde aqui.
$(document).on('click', '#btnNuevaDeclaracion', function () {
    establecimientos.crearDeclaracion(null, idContribuyente);
});

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

    // Los 9 campos de "Totales" (ingresos, arriba del formulario) comparten
    // la clase .campo-total con los de "Liquidación Privada" (renglones
    // 20+, abajo) pero NO tienen numeroCampo -no mapean a una columna
    // dec_ValorConceptoN via el SP de liquidacion-. Sin este guard, cambiar
    // cualquiera de esos 9 campos disparaba un UPDATE a la columna literal
    // "dec_ValorConcepto" (sin numero), que no existe, y tronaba con "No se
    // pudieron guardar las actividades". Esos totales SI se guardan bien:
    // junto con las actividades, al presionar "Finalizar Declaración".
    if (!numeroCampo) { return; }

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

/*
 * Guarda la liquidacion completa (totales + actividades) y ejecuta el SP
 * de calculo.
 *
 * Antes este boton ("Finalizar Declaración") no guardaba nada: solo
 * mostraba un mensaje de exito falso y cerraba el modal. La logica real
 * -construir totales/actividades y llamar a funcion 6- estaba escrita mas
 * abajo pero atada a "#btnValidarDeclaracion", un boton que ya no existe
 * en el formulario (quedo huerfano en un rediseño anterior del encabezado
 * del modal). El usuario podia liquidar una declaracion completa, cerrar
 * el modal creyendo que quedo guardada, y perder todo lo que escribio.
 */
$("#btnGenerarOficial").off("click").on("click", function () {

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
        dec_BaseGravable: establecimientos.numero($('[data-campo="ingresos_gravables"]').val()),

        dec_CapacidadInstalada: establecimientos.numero($('[data-campo="capacidad_instalada"]').val()),
        dec_ValorImpuesto: establecimientos.numero($('[data-campo="valor_impuesto"]').val())

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



        },
        // Ver nota en icaWebRit.js: sin este error() el boton "Liquidar"
        // quedaba mudo si el backend no devolvia JSON valido.
        error: function (xhr) {
            $('#loading').hide();
            $('#wrapper').removeClass('body-load');
            console.error('Liquidar - respuesta del servidor:', xhr.responseText);
            swal("Error",
                 "No se pudo liquidar. Intente de nuevo; si persiste, avise a soporte.",
                 "error");
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

// ==========================================
// FUNCIONES DE FIRMA DIGITAL Y DECLARACIONES
// ==========================================

// Abre el mismo formulario de "Crear Declaración" pero pre-cargado con la
// declaracion existente, lista para modificarla. Antes esto era un aviso
// de "disponible próximamente": no habia ninguna forma real de editar.
establecimientos.editarDeclaracion = function(dec_Id) {
    EditarDeclaracion.abrir(dec_Id);
};

// Borra (inactiva) una declaracion en borrador. Solo aparece este boton
// mientras esta en borrador -ver declaraciones.ui.js htmlAcciones()-, nunca
// sobre una ya firmada o presentada.
establecimientos.borrarDeclaracion = function(dec_Id) {
    swal({
        type: 'warning',
        title: '¿Borrar este borrador?',
        text: 'Esta acción no se puede deshacer.',
        showCancelButton: true,
        confirmButtonText: 'Sí, borrar',
        cancelButtonText: 'Cancelar'
    }).then(function (result) {
        if (!result.value) { return; }

        $.ajax({
            url: '../business/controller/class.declaracionesICA.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 4, dec_Id: dec_Id },
            success: function (arr) {
                if (arr.ok == 1) {
                    swal({ type: 'success', title: 'Borrado', text: 'El borrador se eliminó correctamente.' });
                    establecimientos.pintarAccionesDeclaracionContribuyente();
                } else {
                    swal({ type: 'error', title: 'No se pudo borrar', text: arr.mensaje || 'Intente nuevamente.' });
                }
            },
            error: function () {
                swal({ type: 'error', title: 'Error de conexión', text: 'No se pudo borrar el borrador.' });
            }
        });
    });
};

establecimientos.abrirFirmaDigital = function(dec_Id, idEstablecimiento) {
    // Todo el flujo OTP (envio, reenvio, cuenta regresiva, validacion y
    // firma) vive en FirmaOTP, dentro de core/declaraciones.ui.js, y lo
    // comparten esta pantalla y Consultar Declaraciones.
    FirmaOTP.abrir(dec_Id, idEstablecimiento, function () {
        establecimientos.pintarAccionesDeclaracionContribuyente();
    });
};

/**
 * Firma del CONTADOR o REVISOR FISCAL. Obligatoria para presentar cuando el
 * contribuyente tiene uno registrado (ver requiere_contador en el backend).
 * El codigo viaja al correo del contador/revisor del contribuyente, no al
 * del usuario en pantalla.
 *
 * Faltaba en esta pantalla: DeclaracionesUI.htmlAcciones() (compartido con
 * Consultar Declaraciones) genera un boton que llama a esta funcion, pero
 * solo estaba implementada en icaWebConsultar.js. Aqui no hacia nada.
 */
establecimientos.firmaContador = function(dec_Id, idEstablecimiento) {
    FirmaOTP.abrir(dec_Id, idEstablecimiento, function () {
        establecimientos.pintarAccionesDeclaracionContribuyente();
    }, 'contador');
};

/**
 * "Editar borrador" sobre una declaracion YA FIRMADA: borra la firma (y la
 * del contador, si existia) y vuelve a borrador, porque acreditaban un
 * contenido que esta por cambiar.
 */
establecimientos.editarFirmada = function(dec_Id) {
    swal({
        title: '¿Editar esta declaración?',
        text: 'Está firmada. Al editarla se eliminará la firma y volverá al estado Borrador. '
            + 'Deberá firmarla de nuevo antes de presentarla.',
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1fa49d',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, editar y eliminar la firma',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (!result.value) { return; }

        $.ajax({
            url: '../business/controller/class.declaracionesICA.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 10, dec_Id: dec_Id },
            success: function (resp) {
                if (resp.ok != 1) {
                    swal('Error', resp.mensaje || 'No se pudo devolver a borrador', 'error');
                    return;
                }
                swal('Volvió a borrador', resp.mensaje, 'success').then(() => {
                    establecimientos.editarDeclaracion(dec_Id);
                });
            }
        });
    });
};

/**
 * Genera una DECLARACION DE CORRECCION de una ya presentada. No modifica
 * la original: crea una nueva en borrador con todos los datos copiados,
 * enlazada por dec_DeclaracionCorrige.
 */
establecimientos.corregirDeclaracion = function(dec_Id) {
    swal({
        title: '¿Generar declaración de corrección?',
        text: 'Se creará una nueva declaración con los mismos datos, enlazada a esta. '
            + 'La declaración presentada no se modifica.',
        type: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1fa49d',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, generar corrección',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (!result.value) { return; }

        $.ajax({
            url: '../business/controller/class.declaracionesICA.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 11, dec_Id: dec_Id },
            success: function (resp) {
                if (resp.ok != 1) {
                    swal('Error', resp.mensaje || 'No se pudo generar la corrección', 'error');
                    return;
                }
                swal('Corrección generada', resp.mensaje, 'success').then(() => {
                    if (resp.datos && resp.datos.dec_Id) {
                        establecimientos.editarDeclaracion(resp.datos.dec_Id);
                    }
                });
            }
        });
    });
};

establecimientos.presentarDeclaracion = function(dec_Id, idEstablecimiento) {
    swal({
        title: '¿Presentar Declaración?',
        text: "Al presentarla ya no podrá editarla.",
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1fa49d',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, presentar'
    }).then((result) => {
        if (!result.value) { return; }
        establecimientos._intentarPresentar(dec_Id, idEstablecimiento);
    });
};

/**
 * Intenta presentar. Si falta la firma del contador/revisor (el backend
 * responde datos.codigo === 'FALTA_CONTADOR'), en vez de mostrar eso como
 * un error suelto, abre aqui mismo su flujo de OTP; en cuanto firma, se
 * reintenta presentar solo. Antes eran dos acciones separadas ("Enviar
 * código al contador" y luego, aparte, "Presentar") y habia que notar que
 * el boton habia cambiado. Ahora "Presentar" resuelve todo en un click.
 */
establecimientos._intentarPresentar = function(dec_Id, idEstablecimiento) {

    $.ajax({
        url: '../business/controller/class.declaracionesICA.php',
        type: 'POST',
        dataType: 'json',
        data: { funcion: 9, dec_Id: dec_Id },
        success: function(resp) {

            if (resp.ok != 1) {
                if (resp.datos && resp.datos.codigo === 'FALTA_CONTADOR') {
                    FirmaOTP.abrir(dec_Id, idEstablecimiento, function () {
                        establecimientos._intentarPresentar(dec_Id, idEstablecimiento);
                    }, 'contador');
                    return;
                }
                swal('Error', resp.mensaje || 'No se pudo presentar la declaración', 'error');
                return;
            }
            swal('¡Presentada!', 'La declaración ha sido presentada con éxito.', 'success').then(() => {
                // Refresca los botones de la tabla para reflejar el nuevo estado,
                // en vez de dejarlos desactualizados hasta que se recargue la pagina.
                establecimientos.pintarAccionesDeclaracionContribuyente();
            });
        }
    });
};
