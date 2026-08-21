/*
 * Casillas que pueden no estar en la pantalla.
 *
 * Trampa que ya costo un bug real: $("#loQueSea").is(":checked") sobre un
 * elemento que NO existe devuelve false, no undefined. Escrito como
 *     campo: $("#x").is(":checked") ? 1 : 0
 * eso manda un 0 solido al servidor y APAGA la columna en silencio cada vez
 * que se guarda. Paso exactamente asi con est_Exento / est_Excento_avisos:
 * se quitaron las casillas del formulario y se olvido quitar el envio, de modo
 * que cada guardado borraba las dos exenciones sin que nadie lo notara.
 *
 * Los campos de texto no tienen el problema: .val() sobre un elemento que no
 * existe da undefined, jQuery lo omite del POST, y el controlador -que recorre
 * $_POST con foreach- ni lo toca. Por eso la guarda solo hace falta aqui.
 *
 * Con esta funcion, si la casilla no esta en la pantalla el campo no viaja, y
 * el valor guardado en la base se queda como estaba.
 */
function flagCasilla(id) {
    const $c = $("#" + id);
    if ($c.length === 0) return undefined;   // no esta en pantalla: no se manda
    return $c.is(":checked") ? 1 : 0;
}

/*    METDOS DEL MODULO DE DEPENDENCIA    */

var enable = true;
var idRol = localStorage.getItem('id_Rol');

class Establecimientos {

    constructor() {}

    /**
     * Fecha de SQL Server -> valor para un <input type="date">.
     *
     * El driver devuelve las fechas como {date: "AAAA-MM-DD hh:mm:ss...", ...}.
     * 1900-01-01 es el centinela de "nunca se lleno" que usa esta base: se
     * muestra vacio, igual que ya hace el certificado en PDF, para no dar a
     * entender que un establecimiento cerro en 1900.
     */
    fechaParaInput(valor) {
        if (!valor) { return ''; }
        var texto = typeof valor === 'string' ? valor : (valor.date || '');
        if (!texto) { return ''; }
        var soloFecha = texto.substring(0, 10);
        return soloFecha === '1900-01-01' ? '' : soloFecha;
    }

    /**
     * Punto 15: el contribuyente ve el cese, pero no lo edita. Solo la
     * Alcaldia (rol 1) puede tocarlo.
     *
     * Esto es presentacion, no seguridad: el control real esta en
     * _filtrarCese() del controlador, porque un readonly se quita desde la
     * consola del navegador. Aqui solo se evita que el usuario escriba algo
     * que despues el servidor le va a descartar sin avisar.
     */
    aplicarPermisosCese() {
        var esAdmin = String(idRol) === '1';

        $('#est_Fecha_cierre, #est_Resolucion_cierre, #est_Observacion_cierre')
            .prop('readonly', !esAdmin);
        $('#est_Causal').prop('disabled', !esAdmin);
        $('#avisoCese').toggle(!esAdmin);
    }

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
            // El nuevo local cuelga del contribuyente en sesion. Para la
            // Alcaldia (que no tiene uno propio) el servidor respeta lo que
            // llegue; para el contribuyente lo fija el, sin poder elegir otro.
            $("#est_IdContribuyente").val(localStorage.getItem('id_Contribuyente') || '');
            $("#btnCrearEstablecimientos").empty();
            $("#btnCrearEstablecimientos").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearEstablecimientos").attr('action', 'javascript:establecimientos.postEstablecimientos()');
                /*
                 * Ya NO se llama a Geografia.poblar aqui.
                 *
                 * Esa funcion llena dos <select> en cascada, y desde que la
                 * ubicacion quedo fija en el municipio estos campos son
                 * <input readonly>. Meterles <option> no hacia nada visible...
                 * salvo dejar el de Departamento en blanco, que es justo lo
                 * que se veia en pantalla.
                 *
                 * Los valores los escribe PHP desde config.municipio.php al
                 * generar la vista, asi que aqui no hay nada que hacer.
                 */
            $('#modal-Establecimientos').modal({backdrop: 'static', keyboard: false})
            $("#modal-Establecimientos").modal('show');
        }
    }

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

                // Ver nota en icaWebRit.js: se carga el catalogo completo y se
                // preselecciona la ciudad actual del contribuyente.
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
    /**
     * Retira un establecimiento: baja LOGICA, no borrado.
     *
     * El establecimiento nunca se elimina de la base porque de el cuelgan
     * declaraciones y anexos de años anteriores; borrarlo dejaria huerfano el
     * historico tributario. La funcion 4 del controlador pone est_Activo = 0.
     */
    retirarEstablecimiento(id) {
        swal({
            title: '¿Retirar este establecimiento?',
            text: 'Dejará de contarse como activo y no entrará en nuevas ' +
                  'declaraciones. Sus declaraciones y archivos anteriores se conservan.',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, retirar',
            cancelButtonText: 'Cancelar'
        }).then(function (res) {
            if (!res.value) { return; }
            establecimientos._cambiarEstadoEstablecimiento(id, 4, 'Establecimiento retirado');
        });
    }

    /** Vuelve a poner activo un establecimiento retirado. */
    reactivarEstablecimiento(id) {
        swal({
            title: '¿Reactivar el establecimiento?',
            text: 'Volverá a contarse como activo y a entrar en las declaraciones.',
            type: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, reactivar',
            cancelButtonText: 'Cancelar'
        }).then(function (res) {
            if (!res.value) { return; }
            // No hay funcion propia para reactivar: se usa la de editar
            // mandando solo est_Activo, que es lo unico que cambia.
            establecimientos._cambiarEstadoEstablecimiento(id, 2, 'Establecimiento reactivado', 1);
        });
    }

    _cambiarEstadoEstablecimiento(id, funcion, mensajeOk, activo) {
        var datos = { funcion: funcion, est_Id: id };
        if (activo !== undefined) { datos.est_Activo = activo; }

        $.ajax({
            url: '../business/controller/class.establecimientos.php',
            type: 'POST',
            dataType: 'json',
            data: datos,
            success: function (resp) {
                if (resp.ok != 1) {
                    swal({ type: 'error', title: 'No se pudo', text: resp.mensaje || '' });
                    return;
                }
                swal({ type: 'success', title: mensajeOk, timer: 1800 });
                establecimientos.getEstablecimientos();
            },
            error: function () {
                swal({ type: 'error', title: 'Error de conexión',
                       text: 'No se pudo cambiar el estado del establecimiento.' });
            }
        });
    }

    draw_table_documents(arrFilter) {

        $("#establecimientosRegistrados").DataTable().destroy();
        $("#bodyEstablecimientosRegistrados").empty();
        for (let dep of arrFilter) {
            // OJO: son DOS columnas distintas con nombres casi iguales.
            //   est_Activo  (int)   1/0  -> activo o inactivo. Es la que
            //                             escribe _inactivarEstablecimientos.
            //   est_Activos (float)      -> el monto de ACTIVOS (patrimonio)
            //                             que se captura en el formulario.
            // Este boton venia mirando est_Activos, o sea el patrimonio: como
            // los 12 establecimientos lo tienen en 0, todos se pintaban como
            // "inactivos" (rojo, "Activar") aunque los 12 estaban activos, y
            // el boton nunca reflejaba lo que hacia.
            if (dep.est_Activo == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Establecimiento";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Establecimiento";
            }

            // Punto 5: los establecimientos ya no descargan el RIT. El RIT es
            // del contribuyente, no de cada local, asi que un boton por fila
            // sugeria que cada establecimiento tenia el suyo. La descarga vive
            // ahora en la pantalla del RIT.
            var soporteRit = '';

            // Punto 13: distintivo de estado. El color nunca va solo, lleva
            // texto -mismo criterio que los estados de las declaraciones-,
            // para que se entienda tambien en blanco y negro o con daltonismo.
            // "Cesado" manda sobre "Inactivo": si tiene fecha de cese, eso es
            // lo que le importa al funcionario que mira la lista.
            var fechaCese = establecimientos.fechaParaInput(dep.est_Fecha_cierre);
            var estadoTexto, estadoFondo, estadoColor;

            if (fechaCese) {
                estadoTexto = 'Cesado';
                estadoFondo = '#FEF3C7';
                estadoColor = '#92400E';
            } else if (dep.est_Activo == 1) {
                estadoTexto = 'Activo';
                estadoFondo = '#D1FAE5';
                estadoColor = '#065F46';
            } else {
                estadoTexto = 'Cerrado';
                estadoFondo = '#FEE2E2';
                estadoColor = '#991B1B';
            }

            var chipEstado =
                '<span style="display:inline-block; padding:2px 10px; border-radius:999px;' +
                ' font-size:12px; font-weight:600; white-space:nowrap;' +
                ' background:' + estadoFondo + '; color:' + estadoColor + ';">' +
                estadoTexto + '</span>' +
                (fechaCese ? '<br><span style="font-size:11px; color:#6B7280;">' + fechaCese + '</span>' : '');


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
                    '<td align="center">' +
                    chipEstado +
                    '</td>' +
                    '<td align="center" style="white-space:nowrap;">' +
                    
                    /*
                     * Solo Editar y Retirar.
                     *
                     * Aqui habia ademas "Crear Declaración" y "Consultar
                     * Declaraciones", y los dos sobraban: la declaracion de ICA
                     * es UNA por contribuyente y año, no por establecimiento
                     * -regla de negocio confirmada por el cliente-. Un boton de
                     * declarar en la fila de un local sugeria lo contrario, que
                     * cada local declara por su cuenta. Declarar y consultar
                     * viven en su propio modulo, que es donde se buscan.
                     *
                     * "Retirar" es baja LOGICA (est_Activo = 0, funcion 4): el
                     * establecimiento no se borra nunca, porque de el cuelgan
                     * declaraciones y anexos de años anteriores.
                     */
                    '<button type="button" class="btn btn-warning btn-sm mr-1" ' +
                        'data-toggle="tooltip" title="Editar establecimiento" ' +
                        'onclick="establecimientos.editarEstablecimiento(' + dep.est_Id + ')">' +
                        '<i class="fa fa-pencil"></i>' +
                    '</button>' +

                    soporteRit +

                    (dep.est_Activo == 1
                        ? '<button type="button" class="btn btn-danger btn-sm" ' +
                              'data-toggle="tooltip" title="Retirar establecimiento" ' +
                              'onclick="establecimientos.retirarEstablecimiento(' + dep.est_Id + ')">' +
                              '<i class="fa fa-trash"></i>' +
                          '</button>'
                        : '<button type="button" class="btn btn-success btn-sm" ' +
                              'data-toggle="tooltip" title="Reactivar establecimiento" ' +
                              'onclick="establecimientos.reactivarEstablecimiento(' + dep.est_Id + ')">' +
                              '<i class="fa fa-undo"></i>' +
                          '</button>') +
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

                // Se deja el id a mano para los anexos, y se listan los que ya
                // tenga cargados (punto 17).
                $('#est_Id').val(id);
                establecimientos.listarAnexos(id);

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
                // est_IdContribuyente ya no es un select (punto 5): el
                // establecimiento pertenece al RIT desde el que se entra, asi
                // que solo se conserva el id en un campo oculto.
                $("#est_IdContribuyente").val(d.est_IdContribuyente);

                $("#est_Nombre").val(d.est_Nombre);
                $("#est_Direccion").val(d.est_Direccion);
                // Unico pais del catalogo. Se fuerza a 'Colombia' en vez de respetar
                // lo guardado porque los registros viejos tienen "1" (el value del
                // <option> fijo anterior), que no matchearia ninguna opcion y dejaria
                // el select en blanco; al guardar, el dato viejo queda saneado.
                $("#est_Pais").val('Colombia');
                // Ver nota en icaWebRit.js: catalogo completo + preseleccion.
            // La ubicacion es fija: no hay cascada que poblar.
                $("#est_Barrio").val(d.est_Barrio);
                $("#est_Correo").val(d.est_Correo);

                // $("#est_Telefono").val(d.est_Telefono);
                $("#est_Activos").val(d.est_Activos);
                $("#est_Area").val(d.est_Area);
                // $("#est_Persona").val(d.est_Persona);

                $("#est_OpcionUso").val(d.est_Opcion_uso);

                // Cese de actividades (puntos 14/15/16). Las fechas centinela
                // 1900-01-01 significan "nunca se lleno": se muestran vacias,
                // igual que hace el certificado en PDF.
                $("#est_Causal").val(d.est_Causal || '');
                $("#est_Resolucion_cierre").val(d.est_Resolucion_cierre || '');
                $("#est_Observacion_cierre").val(d.est_Observacion_cierre || '');
                $("#est_Fecha_cierre").val(establecimientos.fechaParaInput(d.est_Fecha_cierre));
                establecimientos.aplicarPermisosCese();

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

    // La autorizacion de notificacion electronica se pide ahora solo en el
    // RIT (punto 8), asi que aqui ya no se exige: el checkbox no existe en
    // este formulario y la validacion bloqueaba el guardado para siempre.

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
        est_IdContribuyente: $("#est_IdContribuyente").val(), // CORREGIDO
        est_Nombre: $("#est_Nombre").val(),
        est_Direccion: $("#est_Direccion").val(),
        // est_Pais / est_Departamento / est_Ciudad ya no se envian: son
        // VARCHAR(5) y no aguantan un nombre de pais o departamento. El
        // servidor los descarta de todos modos. Ver class.establecimientos.php.

        est_Barrio: $("#est_Barrio").val(),
        est_Correo: $("#est_Correo").val(),

        // est_Telefono: $("#est_Telefono").val(),
        est_Activos: $("#est_Activos").val(),
        est_Area: $("#est_Area").val(),
        //est_Persona: $("#est_Persona").val(),

        est_Opcion_uso: $("#est_OpcionUso").val(),

        // Cese de actividades. Se mandan siempre; si quien envia no es la
        // Alcaldia, el servidor los descarta (_filtrarCese). No se confia en
        // el readonly de la pantalla, que se quita desde la consola.
        est_Fecha_cierre: $("#est_Fecha_cierre").val(),
        est_Causal: $("#est_Causal").val(),
        est_Resolucion_cierre: $("#est_Resolucion_cierre").val(),
        est_Observacion_cierre: $("#est_Observacion_cierre").val(),
        
        est_Cedula_representante: $("#est_Cedula_representante").val(),
        est_Nombre_representante: $("#est_Nombre_representante").val(),
        est_Email_representante: $("#est_Email_representante").val(),

        //est_EstadoRegistro: $("#est_EstadoRegistro").val(),
        est_Matricula: $("#est_Matricula").val(),
        est_Fecha_matricula: $("#est_Fecha_matricula").val(),
        est_Fecha_inscripcion: $("#est_Fecha_inscripcion").val(),
        est_Fecha_inicio: $("#est_Fecha_inicio").val(),
        
        est_Excento_avisos: flagCasilla("est_Excento_avisos"),
        est_Exento: flagCasilla("est_Exento"),
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



        crearDeclaracion($idEstablecimiento) {

            const numeroGenerado = "2024000" + Math.floor(Math.random() * 900 + 100);
  

            // Estado inicial de botones
            $("#btnValidarDeclaracion").prop("disabled", false);
            $("#btnDescargarPDF").prop("disabled", true);
            $("#btnGenerarOficial").prop("disabled", true);



        //Parametro: 27 (2= Modulo Usuario, 7:Permiso Crear Usuario)
        /*var permiso = await _permisos.getPermisos(idRol, 311);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            */

            // --- FECHA ACTUAL ---
            const hoy = new Date();

            // Formatos correctos
            const yyyy = hoy.getFullYear();
            const mm = ("0" + (hoy.getMonth() + 1)).slice(-2);
            const dd = ("0" + hoy.getDate()).slice(-2);

            const fechaActual = `${yyyy}-${mm}-${dd}`;

            // --- HORA ACTUAL ---
            const hh = ("0" + hoy.getHours()).slice(-2);
            const min = ("0" + hoy.getMinutes()).slice(-2);
            const horaActual = `${hh}:${min}`;

            $("#formDeclaracion").trigger("reset");
            // --- LLENAR CAMPOS ---
            //$("#numDeclaracion").val( generarConsecutivo() ); // si quieres otro método, me dices
            //$("#numDeclaracion").val(numeroGenerado);
            $("#anioDeclaracion").val(yyyy);
            $("#periodoDeclaracion").val(12);

            $("#fechaDeclaracion").val(fechaActual);
            // El campo "Fecha Limite Para Calculo de Intereses" se retiro (punto 10
            // de la revision del 2026-08-21). .val() sobre un elemento inexistente
            // no rompe nada, pero se quita para no dejar codigo que apunta al vacio.

            $("#horaDeclaracion").val(horaActual);

            $("#declaracionCorrige").val("");   // vacío por defecto
            $("#opcionUso").val("");            // vacío por defecto

            // switches:
            $("#switchPagada").prop("checked", false);
            $("#switchSinPago").prop("checked", true);



            
            $("#btnGuardarDeclaracion").empty();
            $("#btnGuardarDeclaracion").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formDeclaracion").attr('action', 'javascript:establecimientos.postCrearDeclaracion()');
            $('#modal-CrearDeclaracion').modal({backdrop: 'static', keyboard: false})
            $("#modal-CrearDeclaracion").modal('show');
            establecimientos.cargarActividades($idEstablecimiento); // Aquí pasas el idEstablecimiento
        //}
    }


        cargarActividades = function(idEstablecimiento) {

        // Aquí normalmente llamas a AJAX para traer los datos reales
        // Ejemplo temporal:
        var actividades = [
            { id: 1, nombre: "303", tarifa: 0.007, base: 0 }
        ];

        $("#tbodyActividades").empty();

        actividades.forEach(function (act) {
            $("#tbodyActividades").append(`
                <tr data-id="${act.id}" data-tarifa="${act.tarifa}">
                    <td>${act.nombre}</td>

                    <td>
                        <input type="number"
                            class="form-control base-gravable"
                            value="${act.base}"
                            min="0"
                            step="1">
                    </td>

                    <td>${(act.tarifa * 100).toFixed(2)}%</td>

                    <td class="impuesto">$0</td>

                    <td class="text-center">
                        <input type="checkbox" class="chkSeleccion" checked>
                    </td>
                </tr>
            `);

        });

    };




    consultarDeclaraciones(idEstablecimiento) {

    $("#tbodyDeclaraciones").empty();

        // ⚠️ Temporal: datos simulados mientras construyes backend
        const declaraciones = [
            {
                anio: 2023,
                mes: "Diciembre",
                numero: "2024000797",
                fecha_pago: "30/04/2024",
                banco: "Davivienda",
                valor: 1000000
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
                        <button class="btn btn-outline-primary btn-sm"
                            title="Descargar Declaración"
                            onclick="establecimientos.descargarDeclaracion('${d.numero}')">
                            <i class="fa fa-download"></i>
                        </button>
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
            data: { funcion: 3 },
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

        // Ver nota en postEditarEstablecimiento: la autorizacion se pide
        // solo en el RIT (punto 8).

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
            est_IdContribuyente: $("#est_IdContribuyente").val(),
            est_Nombre: $("#est_Nombre").val(),
            est_Direccion: $("#est_Direccion").val(),
            // ver nota arriba: la ubicacion no viaja

            est_Barrio: $("#est_Barrio").val(),
            est_Correo: $("#est_Correo").val(),

            //est_Telefono: $("#est_Telefono").val(),
            est_Activos: $("#est_Activos").val(),
            est_Area: $("#est_Area").val(),
            //est_Persona: $("#est_Persona").val(),

            est_Opcion_uso: $("#est_OpcionUso").val(),

            // Cese de actividades: van tambien al crear, por si la Alcaldia
            // registra un establecimiento que ya viene cesado. Si quien envia
            // no es administrador el servidor los descarta (_filtrarCese).
            est_Fecha_cierre: $("#est_Fecha_cierre").val(),
            est_Causal: $("#est_Causal").val(),
            est_Resolucion_cierre: $("#est_Resolucion_cierre").val(),
            est_Observacion_cierre: $("#est_Observacion_cierre").val(),

            est_Cedula_representante: $("#est_Cedula_representante").val(),
            est_Nombre_representante: $("#est_Nombre_representante").val(),
            est_Email_representante: $("#est_Email_representante").val(),

            //est_EstadoRegistro: $("#est_EstadoRegistro").val(),
            
            est_Matricula: $("#est_Matricula").val(),
            est_Fecha_matricula: $("#est_Fecha_matricula").val(),
            est_Fecha_inscripcion: $("#est_Fecha_inscripcion").val(),
            est_Fecha_inicio: $("#est_Fecha_inicio").val(),

            //est_LocalMunicipio: $("#est_LocalMunicipio").is(":checked") ? 1 : 0,
            est_Exento: flagCasilla("_est_Exento"),
            est_ExcentoAvisos: flagCasilla("est_ExcentoAvisos"),
            
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


    recalcularTotales() {

        let totalBase = 0;

        $("#tbodyActividades tr").each(function () {

            const seleccionado = $(this).find(".chkSeleccion").is(":checked");

            if (seleccionado) {
                const base = parseFloat($(this).find(".base-gravable").val()) || 0;
                totalBase += base;
            }
        });

        // 🔹 Actualiza los campos solicitados
        $('[data-campo="ingresos_total_pais"]').val(
            totalBase.toLocaleString("es-CO", { minimumFractionDigits: 2 })
        );

        $('[data-campo="ingresos_municipio"]').val(
            totalBase.toLocaleString("es-CO", { minimumFractionDigits: 2 })
        );

        $('[data-campo="ingresos_gravables"]').val(
            totalBase.toLocaleString("es-CO", { minimumFractionDigits: 2 })
        );
    }
    

    /**
     * UsuarioActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    UsuarioActivo() {
        $("#accordion-menu li").removeClass("active show");
        $("#accordion-menu .submenu").css("display", "none");

        // Antes marcaba #MICAWeb (Industria y Comercio) y #ICAWeb_RIT: un
        // resto de cuando el RIT vivia dentro de ese submenu. Desde que el
        // RIT paso a ser un item de primer nivel aparte, esto marcaba el
        // enlace equivocado como activo. Establecimientos ahora tambien es
        // un item de primer nivel propio (#MEstablecimientos).
        // Establecimientos volvio a ser submodulo de Industria y Comercio
        // (reunion 2026-08-18), asi que se marca el padre y se despliega su
        // submenu, en vez del item de primer nivel que ya no existe.
        $("#MICAWeb").addClass("active show");
        $("#SubICAWeb").css("display", "block");
        $("#ICAWeb_Establecimientos").addClass("active");
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


liquidarDeclaracion() {

    const redondearMiles = v => Math.round(v / 1000) * 1000;

    // ===============================
    // 1. INDUSTRIA Y COMERCIO (ICA)
    // ===============================
    let ica = 0;

    $("#tbodyActividades tr").each(function () {

        if (!$(this).find(".chkSeleccion").is(":checked")) return;

        const base   = parseFloat($(this).find(".base-gravable").val()) || 0;
        const tarifa = parseFloat($(this).data("tarifa")) || 0;

        ica += base * tarifa;
    });

    ica = redondearMiles(ica); // 🔥 CLAVE

    // ===============================
    // 2. AVISOS Y TABLEROS (15 % ICA)
    // ===============================
    let avisosTableros = redondearMiles(ica * 0.15);

    // ===============================
    // 3. SOBRETASA BOMBERIL (5 % ICA)
    // ===============================
    let sobretasaBomberil = redondearMiles(ica * 0.05);

    // ===============================
    // 4. TOTAL IMPUESTO A CARGO
    // ===============================
    let totalImpuestoCargo =
        ica +
        avisosTableros +
        sobretasaBomberil;

    totalImpuestoCargo = redondearMiles(totalImpuestoCargo);

    // ===============================
    // 5. OTROS CONCEPTOS (0 por ahora)
    // ===============================
    const exencion        = 0;
    const retenciones     = 0;
    const autoreten       = 0;
    const anticipoAnt     = 0;
    const anticipoSig     = 0;
    const sanciones       = 0;
    const saldoFavorAnt   = 0;

    // ===============================
    // 6. VALOR A PAGAR
    // ===============================
    let valorAPagar =
        totalImpuestoCargo -
        exencion -
        retenciones -
        autoreten -
        anticipoAnt +
        anticipoSig +
        sanciones -
        saldoFavorAnt;

    valorAPagar = redondearMiles(Math.max(valorAPagar, 0));

    // ===============================
    // 7. DESCUENTO PRONTO PAGO
    // ===============================
    const descuentoProntoPago = redondearMiles(
        parseFloat($('[data-campo="descuento_pronto_pago"]').val()) || 0
    );

    // ===============================
    // 8. INTERÉS DE MORA
    // ===============================
    const interesMora = redondearMiles(
        parseFloat($('[data-campo="interes_mora"]').val()) || 0
    );

    // ===============================
    // 9. TOTAL A PAGAR
    // ===============================
    let totalAPagar =
        valorAPagar -
        descuentoProntoPago +
        interesMora;

    totalAPagar = redondearMiles(Math.max(totalAPagar, 0));

    // ===============================
    // 10. PINTAR RESULTADOS
    // ===============================
    const fmt = v => v.toLocaleString("es-CO");

    $('[data-campo="industria_comercio"]').val(fmt(ica));
    $('[data-campo="avisos_tableros"]').val(fmt(avisosTableros));
    $('[data-campo="sobretasa_bomberil"]').val(fmt(sobretasaBomberil));
    $('[data-campo="total_impuesto_cargo"]').val(fmt(totalImpuestoCargo));

    $('[data-campo="valor_a_pagar"]').val(fmt(valorAPagar));
    $('[data-campo="total_a_pagar"]').val(fmt(totalAPagar));

    return totalAPagar;
}



    /* ======================================================================
       Anexos del establecimiento (puntos 17 y 18)
       ----------------------------------------------------------------------
       Los archivos NO viajan con $.ajax normal: hay que usar FormData y
       apagar processData/contentType, o jQuery los convierte en la cadena
       "[object File]". Esa es la razon de que la carga nunca funcionara: el
       formulario mandaba los campos como objeto plano.
       ====================================================================== */

    subirAnexos() {

        var idEstablecimiento = $('#est_Id').val();
        if (!idEstablecimiento) {
            swal({ type: 'info', title: 'Primero guarde el establecimiento',
                   text: 'Los archivos se cargan una vez el establecimiento existe.' });
            return;
        }

        // Sin el input en pantalla, $(...)[0] es undefined y .files lanzaria
        // TypeError. No deberia llamarse nunca -el boton ya no existe- pero
        // dejarlo sin guarda es dejar una mina.
        var $inputArchivo = $('#anexoArchivo');
        if ($inputArchivo.length === 0) { return; }
        var archivos = $inputArchivo[0].files;
        if (!archivos.length) {
            swal({ type: 'info', title: 'No eligió ningún archivo' });
            return;
        }

        var datos = new FormData();
        datos.append('funcion', 1);
        datos.append('est_Id', idEstablecimiento);
        datos.append('tipo', $('#anexoTipo').val());
        for (var i = 0; i < archivos.length; i++) {
            datos.append('anexos[]', archivos[i]);
        }

        var $boton = $('#btnSubirAnexo');
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
                $('#anexoArchivo').val('');

                swal({
                    type: resp.ok == 1 ? 'success' : 'error',
                    title: resp.ok == 1 ? 'Archivos cargados' : 'No se pudo cargar',
                    text: resp.mensaje
                });

                establecimientos.listarAnexos(idEstablecimiento);
            },
            error: function () {
                $boton.prop('disabled', false);
                swal({ type: 'error', title: 'Error de conexión',
                       text: 'No se pudieron cargar los archivos.' });
            }
        });
    }

    /**
     * El nombre del archivo lo escribe quien sube (class.anexos.php solo le
     * quita la ruta con basename(), no etiquetas HTML). Confirmado en vivo:
     * un archivo subido con nombre "<svg onload=alert(document.cookie)>.pdf"
     * se ejecutaba de verdad al listarlo -robo de la cookie de sesion real de
     * quien lo viera, incluido personal interno revisando anexos ajenos-,
     * porque listarAnexos() insertaba el nombre crudo con $().html().
     */
    escapeHtml(texto) {
        return String(texto == null ? '' : texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /** Punto 17: ver lo que ya esta cargado, no solo poder cargar. */
    listarAnexos(idEstablecimiento) {
        // Punto 14 de la revision del 2026-08-21: el bloque de documentos se
        // retiro del formulario de establecimiento. Si la tabla no esta en la
        // pantalla no hay nada que pintar, y pedirlo seria una peticion al vacio.
        if ($('#tbodyAnexos').length === 0) { return; }


        if (!idEstablecimiento) {
            $('#tbodyAnexos').html(
                '<tr><td colspan="5" class="text-center text-muted py-3">' +
                    'Guarde el establecimiento para poder adjuntar archivos.' +
                '</td></tr>'
            );
            return;
        }

        $.ajax({
            url: '../business/controller/class.anexos.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 2, est_Id: idEstablecimiento },
            success: function (resp) {

                if (resp.ok != 1 || !resp.datos || !resp.datos.length) {
                    $('#tbodyAnexos').html(
                        '<tr><td colspan="5" class="text-center text-muted py-3">' +
                            'Todavía no hay archivos cargados.' +
                        '</td></tr>'
                    );
                    return;
                }

                var etiquetas = {
                    rut: 'RUT', camara: 'Cámara de Comercio', cedula: 'Cédula',
                    usosuelo: 'Uso de Suelos', cese: 'Cese', otro: 'Otro'
                };

                var filas = '';
                resp.datos.forEach(function (a) {
                    var kb = Math.max(1, Math.round((a.anx_Tamano || 0) / 1024));
                    filas +=
                        '<tr>' +
                            '<td>' + establecimientos.escapeHtml(etiquetas[a.anx_Tipo] || a.anx_Tipo || '') + '</td>' +
                            '<td>' + establecimientos.escapeHtml(a.anx_NombreOriginal) + '</td>' +
                            '<td>' + kb + ' KB</td>' +
                            '<td>' + establecimientos.escapeHtml(a.anx_FechaCarga) + '</td>' +
                            '<td class="text-center" style="white-space:nowrap;">' +
                                '<a class="btn btn-info btn-sm mr-1" target="_blank" ' +
                                   'href="../extensiones/anexo.php?id=' + a.anx_Id + '" ' +
                                   'title="Ver archivo"><i class="fa fa-eye"></i></a>' +
                                '<button type="button" class="btn btn-danger btn-sm" ' +
                                   'title="Quitar" ' +
                                   'onclick="establecimientos.quitarAnexo(' + a.anx_Id + ')">' +
                                   '<i class="fa fa-trash"></i></button>' +
                            '</td>' +
                        '</tr>';
                });

                $('#tbodyAnexos').html(filas);
            },
            error: function () {
                $('#tbodyAnexos').html(
                    '<tr><td colspan="5" class="text-center text-danger py-3">' +
                        'No se pudo consultar los archivos.' +
                    '</td></tr>'
                );
            }
        });
    }

    quitarAnexo(idAnexo) {

        swal({
            title: '¿Quitar este archivo?',
            text: 'Dejará de aparecer en el establecimiento.',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, quitar',
            cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (!r.value) { return; }

            $.ajax({
                url: '../business/controller/class.anexos.php',
                type: 'POST',
                dataType: 'json',
                data: { funcion: 3, anx_Id: idAnexo },
                success: function () {
                    establecimientos.listarAnexos($('#est_Id').val());
                },
                error: function () {
                    swal({ type: 'error', title: 'No se pudo quitar el archivo' });
                }
            });
        });
    }
}

const establecimientos = new Establecimientos();

establecimientos.getEstablecimientos();
establecimientos.UsuarioActivo();
//establecimientos.cargarContribuyentes();


// Aqui se inicializaba select2 sobre #est_IdContribuyente con un buscador de
// contribuyentes por documento o nombre. Se retira: desde el punto 5 de la
// reunion del 2026-08-18 el establecimiento pertenece al RIT desde el que se
// entra, asi que no hay a quien buscar. El campo quedo oculto, pero select2
// dibujaba su propia caja de busqueda encima igual y seguia preguntando.
//
// El valor lo fija el servidor para todo rol que no sea Alcaldia
// (_agregarEstablecimientos / _editarEstablecimientos), asi que quitarlo de la
// pantalla no abre ningun hueco.


$(document).ready(function(){

    establecimientos.cargarAniosActividades();

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



$(document).ready(function () {

    // Ocultar al iniciar
    establecimientos.validarOpcionUso();

    // Detectar cambio
    $("#opcionUso").on("change", function () {
        establecimientos.validarOpcionUso();
    });

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


$(document).on("input", ".base-gravable", function () {

    const fila = $(this).closest("tr");
    const base = parseFloat($(this).val()) || 0;
    const tarifa = parseFloat(fila.data("tarifa")) || 0;
    const seleccionado = fila.find(".chkSeleccion").is(":checked");

    let impuesto = 0;

    if (seleccionado) {
        impuesto = base * tarifa;
    }

    fila.find(".impuesto").text(
        "$ " + impuesto.toLocaleString("es-CO", { minimumFractionDigits: 2 })
    );

    establecimientos.recalcularTotales();

});


$(document).on("change", ".chkSeleccion", function () {
    establecimientos.recalcularTotales();
});


$(document).on("change", ".chkSeleccion", function () {

    const fila = $(this).closest("tr");
    const base = parseFloat(fila.find(".base-gravable").val()) || 0;
    const tarifa = parseFloat(fila.data("tarifa")) || 0;

    let impuesto = $(this).is(":checked") ? base * tarifa : 0;

    fila.find(".impuesto").text(
        "$ " + impuesto.toLocaleString("es-CO", { minimumFractionDigits: 2 })
    );

});


$("#btnGenerarOficial").off("click").on("click", function () {

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

});



$("#btnValidarDeclaracion").on("click", function () {

    const total = establecimientos.liquidarDeclaracion();

    if (total <= 0) {
        swal({
            type: 'warning',
            title: 'Sin valores',
            text: 'Debe ingresar valores para poder liquidar'
        });
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

// Anexos: la carga va aparte del guardado del formulario porque necesita que
// el establecimiento ya exista para colgarle los archivos.
$(document).on('click', '#btnSubirAnexo', function () {
    establecimientos.subirAnexos();
});
