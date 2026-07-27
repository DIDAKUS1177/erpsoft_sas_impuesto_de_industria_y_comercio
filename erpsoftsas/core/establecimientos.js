/*    METDOS DEL MODULO DE DEPENDENCIA    */

var enable = true;
var idRol = localStorage.getItem('id_Rol');

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
            $("#est_IdContribuyente").empty();
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
                $("#est_IdContribuyente").empty();
                var option = new Option(
                        d.strDocumentoContribuyente + " - " + d.strNombreContribuyente,
                        d.est_IdContribuyente,
                        true,
                        true
                    );

                $("#est_IdContribuyente").append(option).trigger('change');

                $("#est_Nombre").val(d.est_Nombre);
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
        est_IdContribuyente: $("#est_IdContribuyente").val(), // CORREGIDO
        est_Nombre: $("#est_Nombre").val(),
        est_Direccion: $("#est_Direccion").val(),
        est_Pais: $("#est_Pais").val(),
        est_Departamento: $("#est_Departamento").val(),
        est_Ciudad: $("#est_Ciudad").val(),
        est_Barrio: $("#est_Barrio").val(),
        est_Correo: $("#est_Correo").val(),

        // est_Telefono: $("#est_Telefono").val(),
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

        $("#MICAWeb").addClass("active show");
        $("#SubICAWeb").css("display", "block");
        $("#ICAWeb_RIT").addClass("active");
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


}

const establecimientos = new Establecimientos();

establecimientos.getEstablecimientos();
establecimientos.UsuarioActivo();
//establecimientos.cargarContribuyentes();


$('#est_IdContribuyente').select2({
    language: {
    inputTooShort: () => "Ingrese mínimo 2 caracteres",
    searching: () => "Buscando...",
    noResults: () => "No se encontraron resultados"
},
    dropdownParent: $('#modal-Establecimientos'),
    placeholder: "Buscar contribuyente por documento o nombre",
    minimumInputLength: 2,
    
    ajax: {
        url: '../business/controller/class.contribuyentes.php',
        type: 'POST',
        dataType: 'json',
        delay: 250,

        data: function (params) {
            return {
                funcion: 5,
                buscar: params.term
            };
        },

        processResults: function (data) {

            console.log(data);
            let resultados = [];

            if(data.ok == 1){

                data.datos.forEach(function(c){

                    resultados.push({
                        id: c.ind_Id,
                        text: c.ind_NumeroIdentificacion + " - " +
                              c.ind_PrimerNombre + " " +
                              c.ind_PrimerApellido
                    });

                });

            }

            return {
                results: resultados
            };

        }
    }
});


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
