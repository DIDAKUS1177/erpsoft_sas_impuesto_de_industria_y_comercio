/*    METDOS DEL MODULO DE DEPENDENCIA    */

var enable = true;
var idRol = localStorage.getItem('id_Rol');

class Establecimientos {

    constructor() {}

    /**
     * crearUsuario: Método para abrir modal de creación de ActividadesComercio.
     */
    async crearEstablecimientos() {

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
            var soporteRit =
                '<a href="../extensiones/soporterit.php?codigo=' + dep.est_Id + '" ' +
                'target="_blank" ' +
                'class="btn btn-info btn-sm mr-1" ' +
                'data-toggle="tooltip" title="Descargar RIT">' +
                '<i class="fa fa-download"></i>' +
                '</a>';


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
                                        
                    soporteRit +
                        '<button type="button" class="btn btn-primary btn-sm mr-1" ' +
                            'data-toggle="tooltip" title="Crear Declaración" ' +
                            'onclick="establecimientos.crearDeclaracion(' + dep.est_Id + ')">' +
                            '<i class="fa fa-file-text-o"></i>' +
                        '</button>' +

                        '<button type="button" class="btn btn-success btn-sm" ' +
                            'data-toggle="tooltip" title="Consultar Declaraciones" ' +
                            'onclick="establecimientos.consultarDeclaraciones(' + dep.est_Id + ')">' +
                            '<i class="fa fa-search"></i>' +
                        '</button>' +

                    '</td>'+

                    '</tr>'
                );
            
        }
    
        establecimientos.init_table();
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
            $("#fechaLimiteInteres").val(fechaActual);

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
                    $("#bodyConceptosRegistrados").empty();
                    establecimientos.draw_table_documents(arr.datos);
                } else {
                    $("#conceptosRegistrados").DataTable().destroy();
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

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        let formData = {
            funcion: 1,
            con_Codigo: $("#con_Codigo").val(),
            con_Nombre: $("#con_Nombre").val(),
            con_Direccion: $("#con_Direccion").val(),
            con_Pais: $("#con_Pais").val(),
            con_Departamento: $("#con_Departamento").val(),
            con_Ciudad: $("#con_Ciudad").val(),
            con_Barrio: $("#con_Barrio").val(),
            con_Correo: $("#con_Correo").val(),
            con_Telefono: $("#con_Telefono").val(),
            con_Activos: $("#con_Activos").val(),
            con_Area: $("#con_Area").val(),
            con_Persona: $("#con_Persona").val(),
            con_OpcionUso: $("#con_OpcionUso").val(),
            con_Causal: $("#con_Causal").val(),
            con_CedulaRLegal: $("#con_CedulaRLegal").val(),
            con_NombreRLegal: $("#con_NombreRLegal").val(),
            con_CorreoRLegal: $("#con_CorreoRLegal").val(),
            con_EstadoRegistro: $("#con_EstadoRegistro").val(),
            con_Matricula: $("#con_Matricula").val(),

            con_FechaMatricula: $("#con_FechaMatricula").val(),
            con_FechaInscripcion: $("#con_FechaInscripcion").val(),
            con_FechaInicio: $("#con_FechaInicio").val(),

            con_Excluido: $("#con_Excluido").is(":checked") ? 1 : 0,
            con_ExcentoAvisos: $("#con_ExcentoAvisos").is(":checked") ? 1 : 0,
            con_LocalMunicipio: $("#con_LocalMunicipio").is(":checked") ? 1 : 0,
            con_CamaraComercio: $("#con_CamaraComercio").is(":checked") ? 1 : 0,
            con_Activo: $("#con_Activo").is(":checked") ? 1 : 0,

            con_CodigoCatastral: $("#con_CodigoCatastral").val(),
            con_Observacion: $("#con_Observacion").val(),
            con_FechaCierre: $("#con_FechaCierre").val(),
            con_NoResolucion: $("#con_NoResolucion").val(),

            con_PrincipalRut: $("#con_PrincipalRut").val(),
            con_Actividad2: $("#con_Actividad2").val(),
            con_Actividad3: $("#con_Actividad3").val(),
            con_FechaActividad: $("#con_FechaActividad").val(),

            con_CedulaContador: $("#con_CedulaContador").val(),
            con_NombreContador: $("#con_NombreContador").val(),
            con_TarjetaContador: $("#con_TarjetaContador").val(),

            con_CedulaRevisor: $("#con_CedulaRevisor").val(),
            con_NombreRevisor: $("#con_NombreRevisor").val(),
            con_TarjetaRevisor: $("#con_TarjetaRevisor").val()
        };

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

        $("#MICAAlcaldia").addClass("active show");
        $("#SubICAAlcaldia").css("display", "block");

        $("#MICA_Procesos").addClass("active show");
        $("#SubICA_Procesos").css("display", "block");

        $("#ICA_Establecimientos").addClass("active");
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


}

const establecimientos = new Establecimientos();

establecimientos.getEstablecimientos();
establecimientos.UsuarioActivo();

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
