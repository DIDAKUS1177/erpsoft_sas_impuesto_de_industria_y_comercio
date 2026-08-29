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

                // Se carga el catalogo completo de ciudades y se preselecciona
                // la actual (d.ind_IdCiudad) DESPUES de tenerla, para que el
                // select2 ya arranque mostrando el valor correcto en vez de
                // vacio.
                establecimientos.cargarCiudadesInfoContribuyente(d.ind_IdCiudad);

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
     * cargarCiudadesInfoContribuyente: llena el select2 "Municipio de
     * Registro" del modal de Informacion del Contribuyente con el catalogo
     * completo (conf_ciudades), y preselecciona idActual si se pasa.
     */
    cargarCiudadesInfoContribuyente(idActual) {

        if ($.fn.select2 && $('#infoContrib_ind_IdCiudad').hasClass("select2-hidden-accessible")) {
            $('#infoContrib_ind_IdCiudad').select2('destroy');
        }

        $.ajax({
            url: '../business/controller/class.ciudades.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 1 },
            success: function (arr) {
                if (arr.ok != 1) { return; }

                $('#infoContrib_ind_IdCiudad').empty();
                $('#infoContrib_ind_IdCiudad').append('<option value=""></option>');

                $.each(arr.datos, function (i, ciudad) {
                    $('#infoContrib_ind_IdCiudad').append(
                        `<option value="${ciudad.ciu_Id}">${ciudad.ciu_Nombre} - ${ciudad.ciu_Departamento}</option>`
                    );
                });

                if (idActual) {
                    $('#infoContrib_ind_IdCiudad').val(idActual);
                }

                $('#infoContrib_ind_IdCiudad').select2({
                    dropdownParent: $('#modal-InfoContribuyente'),
                    width: '100%'
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
            // Ver la nota en core/establecimientos.js: se marca el modo, y
            // quien envia es el manejador instalado al cargar la pantalla.
            establecimientos._modo = { accion: 'crear' };
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


                // Cambio 13 del cliente: distinguir de un vistazo los
                // establecimientos activos de los cerrados. est_Activo ya
                // existia en la tabla; lo unico que faltaba era mostrarlo.
                var estaActivo = Number(dep.est_Activo) === 1;
                var chipEstado = estaActivo
                    ? '<span style="background:#e6f2ea;color:#2c6b45;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;">Activo</span>'
                    : '<span style="background:#fbeceb;color:#a32a20;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;">Cerrado</span>';

                $('#bodyEstablecimientosRegistrados').append(
                    '<tr' + (estaActivo ? '' : ' style="opacity:.72;"') + '>' +
                    '<td>' +
                    dep.est_Nombre + 
                    '</td>' +
                    '<td>' + chipEstado + '</td>' +
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

                    // Cambio 12: el establecimiento se cierra, no se borra -su
                    // historial de declaraciones tiene que seguir existiendo-.
                    // Solo tiene sentido en los que aun estan activos.
                    (estaActivo
                        ? '<button type="button" class="btn btn-danger btn-sm" ' +
                              'data-toggle="tooltip" title="Cerrar Establecimiento" ' +
                              'onclick="establecimientos.cerrarEstablecimiento(' + dep.est_Id + ')">' +
                              '<i class="fa fa-ban"></i>' +
                          '</button>'
                        : '') +

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
                // Unico pais del catalogo. Se fuerza a 'Colombia' en vez de respetar
                // lo guardado porque los registros viejos tienen "1" (el value del
                // <option> fijo anterior), que no matchearia ninguna opcion y dejaria
                // el select en blanco; al guardar, el dato viejo queda saneado.
                $("#est_Pais").val('Colombia');
                // Departamento/Ciudad ya no son un unico <option> fijo: se
                // llenan con el catalogo completo y se preselecciona lo
                // guardado. Establecimientos creados ANTES de este cambio
                // tienen "1" guardado como texto (el value del option viejo),
                // que no matchea ningun departamento real -el select
                // simplemente queda en blanco para esos, lo cual es correcto:
                // no hay forma de adivinar cual era su departamento real.
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

                establecimientos._modo = { accion: 'editar', id: id };

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
        // est_Exento y est_Excento_avisos ya no se envian: subieron al
        // contribuyente en la migracion 016 y la casilla no existe aqui.
        // `.is(":checked")` sobre un elemento ausente devuelve FALSE, no
        // undefined, asi que dejarlo escribia un 0 solido en cada guardado.
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
                swal("Error","No se pudo crear la declaración","error");
                return;
            }

            const d = arr.datos;

            /*
             * En blanco ANTES de rellenar.
             *
             * Este camino no escribia ninguno de los 29 campos de cifras, asi
             * que abrir una declaracion, cerrarla y pulsar "Crear" dejaba en
             * pantalla los ingresos, las retenciones y la sancion de la
             * anterior — y "Guardar" los escribia en la nueva.
             */
            if (typeof limpiarFormularioDeclaracion === 'function') {
                limpiarFormularioDeclaracion();
            }

            establecimientos.cargarActividades(idEstablecimiento);

            /*
             * Se muestra el NUMERO de la declaracion, no el id de la fila.
             *
             * Se ponia d.dec_Id, y por eso el cliente veia "183" al crear: es el
             * identificador interno, no el numero del formulario. Desde la
             * migracion 012 el numero es un consecutivo por año (2026000001) y
             * es el que ve el contribuyente, va impreso en el PDF y viaja en el
             * codigo de barras del banco. El id no le dice nada a nadie.
             *
             * Se cae al id si la declaracion es anterior a esa migracion y no
             * tiene numero propio.
             */
            $("#numDeclaracion").val(d.dec_NumeroDeclaracion || d.dec_Id);

            // Y si el servidor reabrio una que ya existia, se dice. Un formulario
            // que aparece con cifras que uno no escribio, sin explicacion, se lee
            // como que el sistema calcula mal — que es justo lo que paso.
            if (Number(d._reabierta) === 1 && arr.mensaje) {
                swal({ type: 'info', title: 'Se abrió su declaración en curso',
                       text: arr.mensaje });
            }
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
    /**
     * Cierra un establecimiento (cambio 12 del cliente).
     *
     * NO lo borra: marca est_Activo = 0 usando el endpoint que ya existia
     * (funcion 4). Un establecimiento con declaraciones presentadas no puede
     * desaparecer -su historial tributario tiene que seguir consultable-, por
     * eso se cierra en vez de eliminarse.
     */
    cerrarEstablecimiento(id) {
        swal({
            type: 'warning',
            title: '¿Cerrar este establecimiento?',
            text: 'Dejará de estar activo, pero se conserva junto con sus declaraciones. Podrá seguir consultándolo.',
            showCancelButton: true,
            confirmButtonColor: '#a32a20',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, cerrar',
            cancelButtonText: 'Cancelar'
        }).then(function (resultado) {
            if (!resultado.value) { return; }

            $.ajax({
                url: '../business/controller/class.establecimientos.php',
                type: 'POST',
                dataType: 'json',
                data: { funcion: 4, est_Id: id },
                success: function (arr) {
                    if (arr.ok == 1) {
                        swal({
                            type: 'success',
                            title: 'Establecimiento cerrado',
                            text: 'Quedó marcado como cerrado.'
                        });
                        establecimientos.getEstablecimientos();
                    } else {
                        swal({
                            type: 'error',
                            title: 'No se pudo cerrar',
                            text: arr.mensaje || 'Intente nuevamente.'
                        });
                    }
                }
                // El error de red lo cubre la red de seguridad global de
                // dist/menu.php (ajaxError), que ya avisa al usuario.
            });
        });
    }

    getEstablecimientos() {

        // La tabla de establecimientos salio de esta pantalla (punto 5).
        // Quedan llamadas a esta funcion en flujos del modal; sin esta guarda
        // reventarian contra un elemento que ya no existe.
        if ($("#bodyEstablecimientosRegistrados").length === 0) { return; }
        
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

        // El RIT dejo de ser un item dentro de "Industria y Comercio" y pasó
        // a ser un modulo propio de primer nivel (#MRIT), porque el Registro
        // de Informacion Tributaria aplica a todos los modulos. Antes esto
        // marcaba #MICAWeb y abria #SubICAWeb, que ya no corresponde.
        $("#MRIT").addClass("active");
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

            /*
             * Punto 16 de la revision del 2026-08-21: "Que no salga este aviso cada
             * que se modifica una casilla".
             *
             * Esto se dispara al salir de CADA renglon de la liquidacion, asi que
             * llenar el formulario significaba cerrar la ventanita quince veces. El
             * usuario ya ve el resultado: los totales de la pantalla se actualizan
             * solos delante de el.
             *
             * No se reemplaza por nada silencioso a proposito -no hace falta avisar
             * de algo que se ve-. Lo que SI se conserva es el aviso de error: si el
             * recalculo falla hay que decirlo, y de eso se encarga el manejador de
             * error del propio .ajax().
             */



        }
    })

};








    /* ======================================================================
       Formulario del RIT (puntos 4, 8, 9, 10, 14, 15)
       ----------------------------------------------------------------------
       El RIT es el contribuyente, no el establecimiento. Estas dos funciones
       son las que llenan el formulario de la pantalla y lo graban.
       ====================================================================== */

    cargarRIT() {

        if (!idContribuyente) {
            $('#ritEstadoCarga').text('No se pudo identificar al contribuyente.');
            return;
        }

        $('#ritEstadoCarga').text('Cargando…');

        $.ajax({
            url: '../business/controller/class.contribuyentes.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 6, ind_Id: idContribuyente },
            success: function (resp) {

                if (resp.ok != 1 || !resp.datos) {
                    $('#ritEstadoCarga').text(resp.mensaje || 'No se pudo cargar el RIT.');
                    return;
                }

                var d = resp.datos;
                establecimientos._rit = d;

                $('#rit_ind_Id').val(d.ind_Id);

                // Los de solo lectura se pintan como texto.
                $('#rit_ind_NumeroIdentificacion').val(d.ind_NumeroIdentificacion || '');
                $('#rit_ind_DV').val(d.ind_DV != null ? d.ind_DV : '');
                $('#rit_TipoDocumento').val(establecimientos.nombreTipoDocumento(d.ind_IdTipoDocumento));

                var campos = [
                    'ind_Persona', 'ind_PrimerNombre', 'ind_SegundoNombre',
                    'ind_PrimerApellido', 'ind_SegundoApellido', 'ind_Direccion',
                    'ind_Telefono', 'ind_Email', 'ind_Matricula',
                    'ind_Fecha_matricula', 'ind_Fecha_inicio', 'ind_Ind_camara_comercio',
                    'ind_Cedula_representante', 'ind_Nombre_representante', 'ind_Email_representante',
                    'ind_Cedula_contador', 'ind_Nombre_contador', 'ind_Tarjeta_profesional',
                    'ind_Cedula_revisor', 'ind_Nombre_revisor', 'ind_Tarjeta_profesional_revisor',
                    'ind_EmailContador', 'ind_EmailRevisor',
                    // Codigos del RUT: subieron al contribuyente en la 005.
                    'ind_Rut', 'ind_Rut_segundo', 'ind_Rut_tercero'
                ];
                campos.forEach(function (campo) {
                    var v = d[campo];
                    $('#rit_' + campo).val(v == null ? '' : v);
                });

                // Geografia.poblar() es la cascada departamento -> ciudad y
                // necesita DOS selects; aqui solo hay uno, asi que se usa el
                // mismo camino del select unico que ya usa el modal de
                // Informacion del Contribuyente.
                establecimientos.cargarCiudadesRIT(d.ind_IdCiudad);

                // Una persona juridica no tiene segundo nombre ni apellidos:
                // su razon social entra entera en la primera casilla. Pedido
                // el 2026-08-25.
                establecimientos.ajustarNombresPorTipoPersona();
                establecimientos.pintarSeleccionMultiple(d.ind_RegimenTributario, d.ind_Responsabilidades);
                establecimientos.pintarExenciones(d.ind_NoSujetas, d.ind_SinAvisosTableros);
                establecimientos.pintarCese(d);
                establecimientos.listarAnexosRIT();

                // Autorizacion de notificacion electronica: sin ella el
                // servidor rechaza el guardado (ver _guardarRIT).
                $('#rit_ind_Autorizacion').prop('checked', String(d.ind_Autorizacion) === '1');

                establecimientos.pintarActividadesRIT(d.actividades || []);
                establecimientos.prepararEditorActividades();

                // La descarga del certificado vive ahora aqui y va por
                // contribuyente, no por establecimiento (punto 5).
                $('#btnDescargarRIT').attr(
                    'href', '../extensiones/ritActualizado.php?contribuyente=' + d.ind_Id
                );
                establecimientos.aplicarPermisosRIT();

                // aplicarPermisosRIT() suelta los campos .campo-solo-admin, asi que
                // hay que volver a aplicar el modo: si el RIT esta firmado, la
                // pantalla tiene que quedar bloqueada tambien despues de recargar.
                // Si todavia no se ha consultado la firma, consultarFirmaRIT() lo
                // hara al llegar la respuesta.
                if (establecimientos._firmaRIT) {
                    establecimientos.modoRIT(
                        establecimientos._firmaRIT.firmado == 1,
                        establecimientos._firmaRIT
                    );
                }

                // Punto 10: el RIT queda inicializado en el primer ingreso, sin
                // que nadie tenga que crearlo.
                $('#ritEstadoCarga').text(
                    d.rit_recien_inicializado == 1
                        ? 'Registro creado en este ingreso. Complete los datos y guarde.'
                        : 'Última actualización: ' + (d.ind_FechaActualizacion || 'sin registro')
                );
            },
            error: function () {
                $('#ritEstadoCarga').text('No se pudo cargar el RIT. Intenta de nuevo.');
            }
        });
    }

    /** Llena el municipio del RIT con el catalogo completo (conf_ciudades). */
    /**
     * Punto 1 (reunion 2026-08-18): departamento y municipio en dos selects
     * encadenados.
     *
     * Antes era un unico select con los 1.120 municipios del pais listados
     * como "Municipio - Departamento"; encontrar el propio obligaba a recorrer
     * la lista entera.
     *
     * No se usa Geografia.poblar() -la cascada que ya existe para la direccion
     * del establecimiento- porque aquella trabaja sobre columnas de TEXTO
     * libre (est_Departamento / est_Ciudad) y aqui lo que se guarda es
     * ind_IdCiudad, la clave de conf_ciudades. El departamento no se guarda:
     * sale del propio catalogo y solo sirve para acotar la lista.
     */
    /**
     * Con persona JURIDICA, el segundo nombre y los dos apellidos no aplican:
     * la razon social va completa en "Primer nombre o razon social". Se
     * bloquean y se vacian para que no quede un apellido colgado de una
     * empresa; con persona natural se vuelven a habilitar.
     *
     * Se vacian ADEMAS de bloquear a proposito: un campo deshabilitado con
     * texto dentro sigue mostrando un dato que ya no significa nada, y en un
     * certificado tributario eso confunde.
     */
    /**
     * Vuelca las casillas de regimen y responsabilidades a sus campos ocultos.
     *
     * Son <input type=checkbox> sin atributo name a proposito: si lo tuvieran,
     * serialize() mandaria un parametro repetido por cada una marcada, y el
     * controlador -que recorre $_POST campo a campo- solo veria la ultima. Con
     * un campo oculto viaja la lista completa en un solo valor.
     */
    /**
     * Vuelca las dos exenciones a sus campos ocultos.
     *
     * Un checkbox sin marcar NO se envia con serialize(), y el controlador
     * recorre $_POST con array_key_exists: sin el campo oculto, desmarcar una
     * exencion nunca llegaria al servidor y no habria manera de apagarla.
     */
    /**
     * Los tres documentos que el cliente marco como obligatorios. El uso de
     * suelo queda fuera a proposito: lo pidio como opcional.
     */
    static get DOCUMENTOS_OBLIGATORIOS() {
        return {
            rut:    'RUT',
            camara: 'Cámara de comercio o acta de constitución',
            cedula: 'Documento de identificación del representante legal'
        };
    }

    /**
     * Sube un documento del RIT.
     *
     * Va contra el mismo controlador que los anexos de establecimiento, pero
     * mandando ind_Id en vez de est_Id: desde la migracion 017 un anexo puede
     * colgar del contribuyente. Toda la validacion -extension, tipo real del
     * contenido, tamaño, tope de archivos- vive alli y no se repite aqui: lo
     * que se comprueba en el navegador se salta desde la consola.
     */
    subirAnexoRIT() {
        const idContribuyente = $('#rit_ind_Id').val();
        if (!idContribuyente) {
            swal({ type: 'info', title: 'Primero guarde el RIT',
                   text: 'Los documentos se cargan una vez el registro existe.' });
            return;
        }

        const $archivo = $('#ritAnexoArchivo');
        if ($archivo.length === 0 || !$archivo[0].files.length) {
            swal({ type: 'info', title: 'No eligió ningún archivo' });
            return;
        }

        const datos = new FormData();
        datos.append('funcion', 1);
        datos.append('ind_Id', idContribuyente);
        datos.append('tipo', $('#ritAnexoTipo').val());
        datos.append('anexos[]', $archivo[0].files[0]);

        const $boton = $('#btnSubirAnexoRIT');
        $boton.prop('disabled', true);

        $.ajax({
            url: '../business/controller/class.anexos.php',
            type: 'POST',
            data: datos,
            processData: false,   // sin esto jQuery serializa y pierde el archivo
            contentType: false,   // el navegador pone el boundary del multipart
            dataType: 'json',
            success: function (resp) {
                $boton.prop('disabled', false);
                $archivo.val('');

                swal({
                    type: resp.ok == 1 ? 'success' : 'error',
                    title: resp.ok == 1 ? 'Documento cargado' : 'No se pudo cargar',
                    text: resp.mensaje || ''
                });

                establecimientos.listarAnexosRIT();
            },
            error: function (xhr) {
                $boton.prop('disabled', false);
                console.log('Error al subir el anexo del RIT:', xhr.responseText);
                swal({ type: 'error', title: 'Error en el servidor',
                       text: 'No se recibió respuesta válida.' });
            }
        });
    }

    /** Lista los documentos ya cargados y avisa de los que falten. */
    listarAnexosRIT() {
        const idContribuyente = $('#rit_ind_Id').val();
        if ($('#tbodyAnexosRIT').length === 0) { return; }

        if (!idContribuyente) {
            $('#tbodyAnexosRIT').html(
                '<tr><td colspan="5" class="text-center text-muted py-3">' +
                'Guarde el RIT para poder adjuntar documentos.</td></tr>');
            return;
        }

        $.ajax({
            url: '../business/controller/class.anexos.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 2, ind_Id: idContribuyente },
            success: function (resp) {
                const etiquetas = {
                    rut: 'RUT', camara: 'Cámara de comercio', cedula: 'Documento de identificación',
                    usosuelo: 'Uso de suelo', cese: 'Cese', otro: 'Otro'
                };

                const lista = (resp.ok == 1 && resp.datos) ? resp.datos : [];

                if (!lista.length) {
                    $('#tbodyAnexosRIT').html(
                        '<tr><td colspan="5" class="text-center text-muted py-3">' +
                        'Todavía no hay documentos cargados.</td></tr>');
                } else {
                    let filas = '';
                    lista.forEach(function (a) {
                        const kb = Math.max(1, Math.round((a.anx_Tamano || 0) / 1024));
                        filas +=
                            '<tr>' +
                            '<td>' + establecimientos.escapeHtml(etiquetas[a.anx_Tipo] || a.anx_Tipo || '') + '</td>' +
                            // El nombre lo escribe quien sube: se escapa. Ver la nota de
                            // escapeHtml, que existe por un XSS real encontrado aqui.
                            '<td>' + establecimientos.escapeHtml(a.anx_NombreOriginal) + '</td>' +
                            '<td>' + kb + ' KB</td>' +
                            '<td>' + establecimientos.escapeHtml(a.anx_FechaCarga) + '</td>' +
                            '<td>' +
                              '<a class="btn btn-sm btn-outline-info" target="_blank" ' +
                                 'href="../extensiones/anexo.php?id=' + encodeURIComponent(a.anx_Id) + '">Ver</a> ' +
                              '<button type="button" class="btn btn-sm btn-outline-danger" ' +
                                 'onclick="establecimientos.eliminarAnexoRIT(' + Number(a.anx_Id) + ')">Quitar</button>' +
                            '</td>' +
                            '</tr>';
                    });
                    $('#tbodyAnexosRIT').html(filas);
                }

                establecimientos.avisarDocumentosFaltantes(lista);
            },
            error: function () {
                $('#tbodyAnexosRIT').html(
                    '<tr><td colspan="5" class="text-center text-muted py-3">' +
                    'No se pudieron cargar los documentos.</td></tr>');
            }
        });
    }

    /** Retira un documento del RIT. El borrado es logico: el archivo sigue en disco. */
    eliminarAnexoRIT(idAnexo) {
        swal({
            title: '¿Retirar este documento?',
            text: 'Dejará de aparecer en el RIT. El archivo no se borra del servidor.',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, retirar',
            cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (!r.value) { return; }

            $.ajax({
                url: '../business/controller/class.anexos.php',
                type: 'POST',
                dataType: 'json',
                data: { funcion: 3, anx_Id: idAnexo },
                success: function (resp) {
                    swal({
                        type: resp.ok == 1 ? 'success' : 'error',
                        title: resp.ok == 1 ? 'Documento retirado' : 'No se pudo retirar',
                        text: resp.mensaje || ''
                    });
                    establecimientos.listarAnexosRIT();
                },
                error: function () {
                    swal({ type: 'error', title: 'Error en el servidor',
                           text: 'No se recibió respuesta válida.' });
                }
            });
        });
    }

    /**
     * Dice cuales de los obligatorios faltan.
     *
     * Es un aviso, no un bloqueo: el RIT se diligencia en varias sesiones y
     * negar el guardado por un documento pendiente dejaria al contribuyente
     * sin poder guardar ni lo que ya tiene escrito.
     */
    avisarDocumentosFaltantes(lista) {
        const $aviso = $('#ritAvisoDocumentos');
        if ($aviso.length === 0) { return; }

        const cargados = (lista || []).map(a => a.anx_Tipo);
        const obligatorios = Establecimientos.DOCUMENTOS_OBLIGATORIOS;
        const faltan = Object.keys(obligatorios).filter(t => cargados.indexOf(t) === -1);

        if (!faltan.length) {
            $aviso.hide();
            return;
        }

        $aviso.html(
            '<b><i class="fa fa-exclamation-triangle"></i> Faltan documentos obligatorios:</b> ' +
            faltan.map(t => obligatorios[t]).join(', ') + '.'
        ).show();
    }

    recogerExenciones() {
        $('#rit_ind_NoSujetas').val($('#rit_chk_NoSujetas').is(':checked') ? 1 : 0);
        $('#rit_ind_SinAvisosTableros').val($('#rit_chk_SinAvisos').is(':checked') ? 1 : 0);
    }

    /**
     * Marca las dos exenciones con lo que hay guardado.
     */
    pintarExenciones(noSujetas, sinAvisos) {
        $('#rit_chk_NoSujetas').prop('checked', String(noSujetas) === '1');
        $('#rit_chk_SinAvisos').prop('checked', String(sinAvisos) === '1');
    }

    recogerSeleccionMultiple() {
        const juntar = (sel) => $(sel + ':checked').map(function () { return this.value; }).get().join(',');
        $('#rit_ind_RegimenTributario').val(juntar('.rit-regimen'));
        $('#rit_ind_Responsabilidades').val(juntar('.rit-responsabilidad'));
    }

    /**
     * Marca las casillas a partir de lo que hay guardado.
     */
    pintarSeleccionMultiple(regimen, responsabilidades) {
        const marcar = (sel, valor) => {
            const puestos = String(valor || '').split(',').map(v => v.trim()).filter(Boolean);
            $(sel).each(function () { this.checked = puestos.indexOf(this.value) !== -1; });
        };
        marcar('.rit-regimen', regimen);
        marcar('.rit-responsabilidad', responsabilidades);
    }

    ajustarNombresPorTipoPersona() {
        const esJuridica = String($('#rit_ind_Persona').val()) === '2';
        const dependientes = ['rit_ind_SegundoNombre', 'rit_ind_PrimerApellido', 'rit_ind_SegundoApellido'];

        dependientes.forEach(function (id) {
            const $c = $('#' + id);
            if (esJuridica) { $c.val(''); }
            $c.prop('readonly', esJuridica)
              .toggleClass('campo-bloqueado', esJuridica)
              .attr('title', esJuridica ? 'No aplica para persona jurídica' : '');
        });

        $('#rit_ind_PrimerNombre').attr('placeholder', esJuridica ? 'Razón social' : '');
    }

    cargarCiudadesRIT(idActual) {

        $.ajax({
            url: '../business/controller/class.ciudades.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 1 },
            success: function (arr) {
                if (arr.ok != 1) { return; }

                establecimientos._ciudades = arr.datos || [];

                var esc = establecimientos.escaparHtml;
                var departamentos = [];
                $.each(establecimientos._ciudades, function (i, c) {
                    if (c.ciu_Departamento && departamentos.indexOf(c.ciu_Departamento) === -1) {
                        departamentos.push(c.ciu_Departamento);
                    }
                });
                departamentos.sort();

                var opts = '<option value="">Seleccione departamento…</option>';
                $.each(departamentos, function (i, d) {
                    opts += '<option value="' + esc(d) + '">' + esc(d) + '</option>';
                });
                $('#rit_DepartamentoResidencia').html(opts);

                // Con un municipio ya guardado, se preselecciona SU departamento
                // y despues el municipio; si no, la lista arranca vacia.
                var actual = null;
                if (idActual) {
                    $.each(establecimientos._ciudades, function (i, c) {
                        if (String(c.ciu_Id) === String(idActual)) { actual = c; return false; }
                    });
                }

                if (actual) {
                    $('#rit_DepartamentoResidencia').val(actual.ciu_Departamento);
                    establecimientos.pintarMunicipiosRIT(actual.ciu_Departamento, idActual);
                } else {
                    establecimientos.pintarMunicipiosRIT('', null);
                }
            },
            error: function () {
                // Sin catalogo se conserva el municipio actual en vez de dejar
                // el select vacio y que al guardar se borre.
                $('#rit_ind_IdCiudad').html(
                    '<option value="' + (idActual || '') + '" selected>Municipio actual</option>'
                );
            }
        });
    }

    /** Municipios de un departamento, opcionalmente preseleccionando uno. */
    pintarMunicipiosRIT(departamento, idSeleccionado) {

        var esc = establecimientos.escaparHtml;
        var lista = (establecimientos._ciudades || []).filter(function (c) {
            return !departamento || c.ciu_Departamento === departamento;
        });

        lista.sort(function (a, b) {
            return String(a.ciu_Nombre).localeCompare(String(b.ciu_Nombre), 'es');
        });

        var opts = '<option value="">' +
                   (departamento ? 'Seleccione municipio…' : 'Seleccione primero el departamento') +
                   '</option>';
        $.each(lista, function (i, c) {
            opts += '<option value="' + esc(c.ciu_Id) + '">' + esc(c.ciu_Nombre) + '</option>';
        });

        $('#rit_ind_IdCiudad').html(opts);
        if (idSeleccionado) { $('#rit_ind_IdCiudad').val(idSeleccionado); }
    }

    nombreTipoDocumento(id) {
        var tipos = { 1: 'Cédula de Ciudadanía', 3: 'Cédula de Extranjería', 4: 'Pasaporte', 5: 'NIT' };
        return tipos[id] || '';
    }

    /** Punto 9: las actividades se muestran, pero se editan en su establecimiento. */
    pintarActividadesRIT(actividades) {

        if (!actividades.length) {
            $('#tbodyActividadesRIT').html(
                '<tr><td colspan="4" class="text-center text-muted py-3">' +
                    'No hay actividades económicas registradas.' +
                '</td></tr>'
            );
            return;
        }

        var esc = establecimientos.escaparHtml;
        var filas = '';
        actividades.forEach(function (a) {
            // La columna "Establecimiento" desaparecio: desde la migracion 005
            // la actividad es del contribuyente, no de un local concreto.
            filas +=
                '<tr data-actividad="' + esc(a.atc_IdCodigoActividad) + '">' +
                    '<td>' + esc(a.acc_Codigo) + '</td>' +
                    '<td>' + esc(a.acc_Nombre) + '</td>' +
                    '<td>' + esc(a.acc_Tarifa) + '</td>' +
                    '<td class="text-center">' +
                        '<button type="button" class="btn btn-sm btn-outline-danger btn-quitar-actividad" ' +
                                'title="Quitar esta actividad">&times;</button>' +
                    '</td>' +
                '</tr>';
        });
        $('#tbodyActividadesRIT').html(filas);
    }

    /**
     * Punto 11: el contribuyente registra sus propias actividades desde el RIT.
     *
     * El catalogo se pide una sola vez y se cachea: son ~1.900 filas y volver a
     * bajarlas en cada apertura del RIT no aporta nada.
     */
    prepararEditorActividades() {

        // Sin año (migracion 007): estas son las actividades VIGENTES del
        // contribuyente. El historico por periodo lo guarda cada declaracion.
        //
        // El catalogo se pide una sola vez y se cachea: son ~1.900 filas y
        // volver a bajarlas en cada apertura del RIT no aporta nada.
        if (establecimientos._catalogoActividades) {
            establecimientos._pintarCatalogo();
            return;
        }

        $.ajax({
            url: '../business/controller/class.actividadesComercio.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 3 },
            success: function (resp) {
                establecimientos._catalogoActividades = (resp && resp.ok == 1 && resp.datos) ? resp.datos : [];
                establecimientos._pintarCatalogo();
            },
            error: function () {
                // Sin catalogo no se puede agregar, pero lo ya registrado se
                // sigue viendo.
                establecimientos._catalogoActividades = [];
            }
        });
    }

    _pintarCatalogo() {
        var esc = establecimientos.escaparHtml;
        var html = '<option value="">Agregar actividad…</option>';
        (establecimientos._catalogoActividades || []).forEach(function (a) {
            html += '<option value="' + esc(a.acc_Id) + '" data-codigo="' + esc(a.acc_Codigo) +
                    '" data-tarifa="' + esc(a.acc_Tarifa) + '">' +
                    esc(a.acc_Codigo) + ' — ' + esc(a.acc_Nombre) + '</option>';
        });
        $('#ritCatalogoActividades').html(html);
    }

    /** Ids de las actividades que hay ahora mismo en la tabla. */
    _actividadesEnPantalla() {
        var ids = [];
        $('#tbodyActividadesRIT tr[data-actividad]').each(function () {
            ids.push($(this).data('actividad'));
        });
        return ids;
    }

    guardarActividadesRIT() {
        var ids = establecimientos._actividadesEnPantalla();
        $.ajax({
            url: '../business/controller/class.contribuyentes.php',
            type: 'POST',
            dataType: 'json',
            data: {
                funcion: 8,
                ind_Id: idContribuyente,
                actividades: ids
            },
            success: function (resp) {
                swal({
                    type: (resp && resp.ok == 1) ? 'success' : 'warning',
                    title: (resp && resp.ok == 1) ? 'Listo' : 'No se guardó',
                    text: (resp && resp.mensaje) ? resp.mensaje : ''
                });
                if (resp && resp.ok == 1) { establecimientos.cargarRIT(); }
            },
            error: function () {
                swal({ type: 'error', title: 'Error',
                       text: 'No se pudieron guardar las actividades. Intenta de nuevo.' });
            }
        });
    }

    /**
     * El nombre y la direccion de un establecimiento los escribe el propio
     * contribuyente, asi que no pueden concatenarse crudos dentro de .html():
     * es la misma via por la que se colo un XSS almacenado en el listado de
     * anexos (commit 16d98f8). No hay utilidad de escape en el proyecto, asi
     * que va aqui.
     */
    escaparHtml(valor) {
        if (valor === null || valor === undefined) { return ''; }
        return String(valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }


    /**
     * Puntos 14 y 15: contador y revisor solo los edita el administrador.
     * Esto es la parte visible; quien de verdad decide es el servidor
     * (_camposSoloAdministrador en class.contribuyentes.php), porque un
     * readonly del navegador se quita con la consola.
     */
    aplicarPermisosRIT() {
        // Reunion 2026-08-18: contador y revisor fiscal los registra el propio
        // contribuyente, no la Alcaldia. Antes iban de solo lectura para todo
        // el que no fuera administrador (puntos 14 y 15 de la lista anterior);
        // esa regla quedo derogada, asi que se sueltan los campos y se retira
        // el aviso del candado.
        $('.campo-solo-admin').prop('readonly', false);
        $('#ritAvisoContador').hide();
    }


    /* ================================================================
       FIRMA DEL RIT
       ================================================================
       Pedido del cliente el 2026-08-19: "se guarde, se firme, y ya cuando
       quede firmado pues genere el PDF".

       El backend (microservicios/firmas/api.php, funciones 9 y 10) valida el
       codigo OTP dentro de la MISMA llamada que registra la firma, asi que
       aqui no hay un paso intermedio de "verificar" que se pueda saltar.
       ================================================================ */

    /**
     * Estado de la pantalla del RIT.
     *
     * Reunion 2026-08-19. El RIT tiene dos modos y esta funcion decide cual:
     *
     *   FIRMADO  -> pantalla BLOQUEADA. Los campos no se diligencian, abajo se
     *               ven las firmas y arriba quedan solo "Actualizar" y
     *               "Descargar".
     *   EDITANDO -> se puede diligenciar; salen "Guardar", "Firmar" y
     *               "Cancelar", y el aviso de que actualizar el RIT es
     *               obligatorio.
     *
     * "Actualizar" no guarda nada: desbloquea. Y desbloquear es empezar una
     * novedad, que al guardarse invalida la firma anterior (cambia el hash) y
     * obliga a firmar de nuevo. Por eso el boton dice "Actualizar" y no
     * "Editar": es el nombre que le da el formulario oficial.
     */
    /**
     * Opcion de uso, con la MISMA regla que el formulario impreso: la primera
     * vez es una inscripcion, y desde que el RIT se formalizo una vez, toda
     * novedad posterior es una actualizacion.
     *
     * "firmado" o "desactualizada" sirven igual como señal de que ya se
     * formalizo alguna vez: desactualizada significa que hubo firma y luego
     * cambios, o sea que la inscripcion ya ocurrio.
     */
    pintarOpcionUso(estadoFirma) {
        const yaFormalizado = estadoFirma &&
            (String(estadoFirma.firmado) === '1' || !!estadoFirma.desactualizada);

        $('#rit_OpcionUso').val(yaFormalizado ? 'Actualización' : 'Inscripción');
    }

    consultarFirmaRIT() {
        var self = this;
        $.ajax({
            url: '../microservicios/firmas/api.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 10 },
            success: function (r) {
                if (r.ok != 1) { self.modoRIT(false, null); return; }
                self._firmaRIT = r;
                self.modoRIT(r.firmado == 1, r);
                self.pintarOpcionUso(r);
            },
            error: function () { $('#ritEstadoFirma').html(''); }
        });
    }

    /**
     * Aplica el modo. `firmado` manda; `datos` es la respuesta de la funcion 10.
     * Se puede forzar el modo edicion con el boton "Actualizar" aunque haya
     * firma: en ese caso `this._editando` queda en true hasta que se recargue.
     */
    modoRIT(firmado, datos) {
        var bloqueado = firmado && !this._editando;

        // --- campos ---
        // Se excluyen los del cese, que tienen su propia regla (solo Alcaldia)
        // y su propio boton: bloquearlos aqui los dejaria abiertos al
        // desbloquear el resto, que no es lo que se quiere.
        var $campos = $('#formRIT').find('input, select, textarea')
                          .not('.cese-solo-admin')
                          ;

        $campos.filter('input, textarea').prop('readonly', bloqueado);
        $campos.filter('select').prop('disabled', bloqueado);
        // Un checkbox readonly se sigue pudiendo marcar: hay que deshabilitarlo.
        $campos.filter('input[type=checkbox]').prop('disabled', bloqueado);

        // Botones de las tablas internas (actividades) y del cese.
        $('#btnAgregarActividadRIT, #btnGuardarActividadesRIT').prop('disabled', bloqueado);

        // --- barra superior ---
        $('#btnActualizarRIT').toggle(!!bloqueado);
        $('#btnGuardarRIT, #btnFirmarRIT, #btnCancelarRIT').toggle(!bloqueado);

        // --- aviso de obligatoriedad: solo si NO hay firma vigente ---
        $('#ritAvisoObligatorio').toggle(!firmado);

        // --- distintivo de estado ---
        var $e = $('#ritEstadoFirma');
        if (firmado) {
            $e.html('<span class="badge badge-success" style="font-size:12px;">' +
                    '<i class="fa fa-check"></i> Firmado</span>');
        } else if (datos && datos.desactualizada) {
            // No es lo mismo "nunca se firmo" que "se firmo y despues cambio".
            // Sin distinguirlo, el usuario cree que se perdio su firma.
            $e.html('<span class="badge badge-warning" style="font-size:12px;" ' +
                    'title="Firmado el ' + datos.desactualizada.fecha +
                    ', pero el RIT cambió después. Debe firmarlo de nuevo.">' +
                    '<i class="fa fa-exclamation-triangle"></i> Firma desactualizada</span>');
        } else {
            $e.html('<span class="badge badge-secondary" style="font-size:12px;">Sin firmar</span>');
        }

        // --- bloque de firmas al pie ---
        if (firmado && datos && datos.firma) {
            $('#ritFirmaContribuyente').html(
                '<img src="../extensiones/Sello_Firma.png" style="height:46px;"><br>' +
                '<small class="text-muted">' + $('<div>').text(datos.firma.nombre).html() +
                '<br>' + $('<div>').text(datos.firma.fecha).html() + '</small>'
            );
            $('#ritBloqueFirmas').show();
        } else {
            $('#ritBloqueFirmas').hide();
        }

        $('#btnDescargarRIT').attr('title', firmado
            ? 'Descargar el RIT firmado'
            : 'Se puede descargar, pero saldrá marcado SIN FIRMAR');
    }

    /** "Actualizar": desbloquea para registrar una novedad. */
    actualizarRIT() {
        var self = this;
        swal({
            title: '¿Actualizar el RIT?',
            text: 'Podrá modificar la información. Al guardarla, la firma actual dejará ' +
                  'de tener validez y deberá firmar de nuevo.',
            type: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, actualizar',
            cancelButtonText: 'Cancelar'
        }).then(function (res) {
            if (!res.value) { return; }
            self._editando = true;
            self.modoRIT(true, self._firmaRIT);
        });
    }

    /**
     * Firma el RIT.
     *
     * Usa el MISMO modal que las declaraciones (FirmaOTP, en
     * declaraciones.ui.js). Antes esto abria una ventana de swal distinta y
     * el cliente pidio que fuera la misma: menos que aprender, y el
     * contribuyente reconoce la ventana de firmar.
     */
    firmarRIT() {
        if (typeof FirmaOTP === 'undefined' || typeof FirmaOTP.abrirRit !== 'function') {
            swal({ type: 'error', title: 'No disponible',
                   text: 'No se pudo abrir la ventana de firma. Recargue la página.' });
            return;
        }

        /*
         * Sin los soportes obligatorios no se firma (cliente, 2026-08-26).
         *
         * Esto es cortesia, no seguridad: avisa antes de gastar un codigo. La
         * regla de verdad esta en la funcion 9 de la API, que la vuelve a
         * comprobar y no se puede saltar desde la consola.
         *
         * Se consulta al servidor en vez de mirar el aviso amarillo de la
         * pantalla: ese aviso solo se refresca al listar, y quien acaba de
         * quitar un documento en otra pestaña lo veria desactualizado.
         */
        var self = this;
        var idContribuyente = $('#rit_ind_Id').val();

        $.ajax({
            url: '../business/controller/class.anexos.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 2, ind_Id: idContribuyente },
            success: function (resp) {
                const cargados = (resp.ok == 1 && resp.datos ? resp.datos : []).map(a => a.anx_Tipo);
                const obligatorios = Establecimientos.DOCUMENTOS_OBLIGATORIOS;
                const faltan = Object.keys(obligatorios).filter(t => cargados.indexOf(t) === -1);

                if (faltan.length) {
                    swal({
                        type: 'warning',
                        title: 'Faltan documentos obligatorios',
                        text: 'Para firmar el RIT debe cargar: ' +
                              faltan.map(t => obligatorios[t]).join(', ') + '.'
                    });
                    self.listarAnexosRIT();   // deja el aviso amarillo al dia
                    return;
                }

                self._abrirVentanaDeFirmaRIT();
            },
            // Si no se puede comprobar, se deja pasar: el servidor lo vuelve a
            // mirar antes de registrar nada. Bloquear aqui por una peticion
            // caida dejaria al contribuyente sin poder firmar.
            error: function () { self._abrirVentanaDeFirmaRIT(); }
        });
    }

    /** Abre el modal de firma. Separado de firmarRIT() solo para no anidar. */
    _abrirVentanaDeFirmaRIT() {
        var self = this;
        FirmaOTP.abrirRit(function () {
            // Firmar cierra la novedad: la pantalla vuelve a bloquearse.
            self._editando = false;
            self.consultarFirmaRIT();
        });
    }

    /* ================================================================
       CESE DE ACTIVIDADES (reunion 2026-08-19)
       ================================================================
       El cliente lo quiso en el RIT, debajo de la fecha de inicio de
       actividades, y sin numero de resolucion.

       El dato NO cambio de tabla: sigue en ind_establecimientos, porque lo que
       cesa es un LOCAL y no la persona. Por eso hay un selector: hay que decir
       cual de los establecimientos es el que cierra.

       Guarda por la funcion 21 de class.establecimientos.php, que valida
       permiso y causal del lado del servidor. Lo de aqui es comodidad, no
       seguridad.
       ================================================================ */

    /**
     * Vuelca el cese del CONTRIBUYENTE en el formulario.
     *
     * Antes habia un selector de establecimiento y una consulta aparte para
     * llenarlo: el cese vivia en el local. Con la migracion 019 subio a la
     * persona, asi que el dato llega en la misma respuesta del RIT y no hace
     * falta pedir nada mas. Cerrar un local suelto y seguir con los otros se
     * hace desde el estado del registro del establecimiento.
     */
    pintarCese(d) {
        // sqlsrv devuelve las fechas como objeto; el input date quiere
        // AAAA-MM-DD. Y hay filas con la centinela 1900-01-01, que es en lo que
        // SQL Server convierte una cadena vacia y significa "sin cese".
        var f = d.ind_FechaCese;
        var texto = '';
        if (f) {
            texto = (typeof f === 'string') ? f.substring(0, 10)
                  : (f.date ? String(f.date).substring(0, 10) : '');
            if (texto.indexOf('1900-01-01') === 0) { texto = ''; }
        }

        $('#rit_est_Fecha_cierre').val(texto);
        $('#rit_est_Causal').val(d.ind_CausalCese || '');
        $('#rit_est_Observacion_cierre').val(d.ind_ObservacionCese || '');

        this.aplicarPermisoCese();
    }

    /** Quien no es Alcaldia ve el cese pero no lo toca. */
    aplicarPermisoCese() {
        var esAlcaldia = (idRol == 1 || idRol == 2);

        $('input.cese-solo-admin').prop('readonly', !esAlcaldia);
        $('select.cese-solo-admin').prop('disabled', !esAlcaldia);
        $('#btnGuardarCeseRIT').prop('disabled', !esAlcaldia).toggle(esAlcaldia);
        $('#ritAvisoCese').toggle(!esAlcaldia);
    }

    guardarCeseRIT() {
        var id = $('#rit_ind_Id').val();

        if (!id) {
            swal({ type: 'warning', title: 'Primero guarde el RIT',
                   text: 'El cese se registra sobre un registro ya guardado.' });
            return;
        }

        var self = this;
        $.ajax({
            url: '../business/controller/class.establecimientos.php',
            type: 'POST',
            dataType: 'json',
            data: {
                funcion: 21,
                ind_Id: id,
                est_Fecha_cierre: $('#rit_est_Fecha_cierre').val(),
                est_Causal: $('#rit_est_Causal').val(),
                est_Observacion_cierre: $('#rit_est_Observacion_cierre').val()
            },
            success: function (resp) {
                if (resp.ok != 1) {
                    swal({ type: 'error', title: 'No se pudo guardar', text: resp.mensaje || '' });
                    return;
                }
                swal({ type: 'success', title: 'Listo', text: resp.mensaje, timer: 2000 });
                // El cese entra en el hash del RIT: cambiarlo tumba la firma.
                self.consultarFirmaRIT();
            },
            error: function () {
                swal({ type: 'error', title: 'Error de conexión', text: 'No se pudo guardar el cese.' });
            }
        });
    }

    /**
     * Los tres codigos CIIU del RUT: obligatorio el principal, y los tres de
     * cuatro digitos numericos.
     *
     * El servidor lo valida igual (_validarCodigosCiiu); esto es para avisar
     * antes de enviar y, sobre todo, para explicar el 3-contra-4. Quien mete
     * un codigo de tres no se equivoco de tecla: confundio la numeracion del
     * acuerdo municipal con la CIIU de la DIAN.
     *
     * Devuelve null si todo bien, o el mensaje.
     */
    validarCodigosCiiu() {
        var campos = [
            ['rit_ind_Rut',         'Código de actividad principal', true],
            ['rit_ind_Rut_segundo', 'Código de actividad secundaria', false],
            ['rit_ind_Rut_tercero', 'Código de otra actividad',       false]
        ];

        for (var i = 0; i < campos.length; i++) {
            var id = campos[i][0], rotulo = campos[i][1], obligatorio = campos[i][2];
            var v = ($('#' + id).val() || '').trim();

            if (v === '') {
                if (obligatorio) {
                    return rotulo + ' es obligatorio. Son los cuatro dígitos del código CIIU de la DIAN.';
                }
                continue;
            }
            if (!/^[0-9]+$/.test(v)) {
                return rotulo + ' solo admite números.';
            }
            if (v.length !== 4) {
                return rotulo + ' debe tener exactamente 4 dígitos. ' +
                       'Recuerde: el CIIU de la DIAN es de 4 dígitos. Los códigos del acuerdo ' +
                       'municipal son de 3 y se eligen en la tabla de actividades económicas.';
            }
        }
        return null;
    }

    guardarRIT() {

        var errorCiiu = this.validarCodigosCiiu();
        if (errorCiiu) {
            swal({ type: 'warning', title: 'Revise los códigos del RUT', text: errorCiiu });
            $('#rit_ind_Rut').focus();
            return;
        }

        // Las dos casillas de seleccion multiple no viajan solas: son
        // <input type=checkbox> sin name, y lo que se envia es el campo oculto
        // con sus codigos separados por coma.
        establecimientos.recogerSeleccionMultiple();
        establecimientos.recogerExenciones();

        var $boton = $('#btnGuardarRIT');
        $boton.prop('disabled', true);

        $.ajax({
            url: '../business/controller/class.contribuyentes.php',
            type: 'POST',
            dataType: 'json',
            data: $('#formRIT').serialize() + '&funcion=7',
            success: function (resp) {
                $boton.prop('disabled', false);

                if (resp.ok != 1) {
                    swal({ type: 'error', title: 'No se pudo guardar', text: resp.mensaje || 'Intenta de nuevo.' });
                    return;
                }

                establecimientos.cargarRIT();

                /*
                 * Guardar es solo la mitad. Cualquier cambio invalida la firma
                 * anterior -el hash deja de coincidir-, asi que el RIT queda
                 * sin firmar hasta que se firme de nuevo. Se ofrece en el acto
                 * en vez de dejar al usuario con un RIT guardado que el cree
                 * completo y que sale marcado SIN FIRMAR al imprimirlo.
                 */
                swal({
                    type: 'success',
                    title: 'RIT actualizado',
                    text: (resp.mensaje || '') + ' Para que quede en firme debe firmarlo.',
                    showCancelButton: true,
                    confirmButtonText: 'Firmar ahora',
                    cancelButtonText: 'Más tarde'
                }).then(function (res) {
                    establecimientos.consultarFirmaRIT();
                    if (res.value) { establecimientos.firmarRIT(); }
                });
            },
            error: function () {
                $boton.prop('disabled', false);
                swal({ type: 'error', title: 'Error de conexión', text: 'No se pudo guardar el RIT.' });
            }
        });
    }
}

const establecimientos = new Establecimientos();

// Punto 4: la pantalla abre con el formulario del RIT, no con la
// tabla de establecimientos (que paso a su propio modulo).
establecimientos.cargarRIT();

$(document).on('click', '#btnCancelarRIT', function () {
    establecimientos._editando = false;   // se abandona la novedad
    establecimientos.cargarRIT();         // descarta lo escrito y recarga
    establecimientos.consultarFirmaRIT();
});

$(document).on('click', '#btnFirmarRIT', function () {
    establecimientos.firmarRIT();
});

$(document).on('click', '#btnActualizarRIT', function () {
    establecimientos.actualizarRIT();
});


$(document).on('click', '#btnGuardarCeseRIT', function () {
    establecimientos.guardarCeseRIT();
});


// Estado de firma al abrir la pantalla.
establecimientos.consultarFirmaRIT();
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

    // Mismo guard que en las otras dos pantallas: los campos de ingresos no
    // tienen numeroCampo y no deben disparar el guardado por renglon.
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



        },
        // Sin este error() el boton quedaba MUDO ante cualquier fallo: si el
        // backend devolvia algo que no fuera JSON valido (p.ej. un warning de
        // PHP impreso antes del JSON), success() no corria y no se avisaba
        // nada. Al usuario le parecia que "Liquidar" simplemente no servia.
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

/* ============================================================================
   Editor de actividades economicas del RIT (punto 11, reunion 2026-08-18)
   ----------------------------------------------------------------------------
   Los tres manejadores van delegados sobre document: la tabla se repinta
   entera en cada carga del RIT, asi que atarlos a las filas directamente los
   dejaria muertos tras el primer repintado.
   ============================================================================ */

$(document).on('click', '#btnAgregarActividadRIT', function () {

    var $sel = $('#ritCatalogoActividades');
    var id   = $sel.val();
    if (!id) { return; }

    // No repetir: la tabla tiene indice UNICO por (contribuyente, actividad,
    // año) y un duplicado abortaria el guardado entero.
    if ($('#tbodyActividadesRIT tr[data-actividad="' + id + '"]').length) {
        swal({ type: 'info', title: 'Ya está en la lista',
               text: 'Esa actividad ya está registrada para el año seleccionado.' });
        return;
    }

    var $op    = $sel.find('option:selected');
    var esc    = establecimientos.escaparHtml;
    var texto  = $op.text();
    var nombre = texto.indexOf(' — ') >= 0 ? texto.split(' — ').slice(1).join(' — ') : texto;

    $('#tbodyActividadesRIT').append(
        '<tr data-actividad="' + esc(id) + '">' +
            '<td>' + esc($op.data('codigo')) + '</td>' +
            '<td>' + esc(nombre) + '</td>' +
            '<td>' + esc($op.data('tarifa')) + '</td>' +
            '<td class="text-center">' +
                '<button type="button" class="btn btn-sm btn-outline-danger btn-quitar-actividad" ' +
                        'title="Quitar esta actividad">&times;</button>' +
            '</td>' +
        '</tr>'
    );

    $sel.val('');
});

$(document).on('click', '.btn-quitar-actividad', function () {
    $(this).closest('tr').remove();
});

$(document).on('click', '#btnGuardarActividadesRIT', function () {
    establecimientos.guardarActividadesRIT();
});

/* Cambiar de departamento repinta los municipios y limpia el que hubiera. */
$(document).on('change', '#rit_DepartamentoResidencia', function () {
    establecimientos.pintarMunicipiosRIT($(this).val(), null);
});


/*
 * Envio del formulario de establecimiento. Ver la nota extensa en
 * core/establecimientos.js: el boton "Crear" dependia de un atributo action
 * que se escribia despues de esperar la consulta de permisos, y si esa espera
 * no terminaba bien el boton no hacia absolutamente nada.
 *
 * Las cuatro pantallas comparten el mismo HTML del modal, asi que el arreglo
 * va en las cuatro.
 */
$(function () {
    $("#formCrearEstablecimientos").off("submit.erp").on("submit.erp", function (e) {
        e.preventDefault();

        const modo = establecimientos._modo;

        if (!modo || !modo.accion) {
            swal({
                type: 'warning',
                title: 'No se pudo continuar',
                text: 'Vuelva a abrir el formulario e intente de nuevo.',
            });
            return;
        }

        if (modo.accion === 'editar') {
            establecimientos.postEditarEstablecimiento(modo.id);
        } else {
            establecimientos.postEstablecimientos();
        }
    });
});
