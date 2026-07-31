/*    MÉTODOS DEL MÓDULO DE PAZ Y SALVO    */

var enable = true;
var idRol = localStorage.getItem('id_Rol');

class PazYSalvo {

    constructor() {}

    /**
     * Método: buscarPredios
     * Busca predios por nombre, dirección o código
     */
    buscarPredios() {
        let dato = $("#txtBusquedaPredio").val().trim();

        if (dato.length < 3) {
            $("#listadoPredios").hide().empty();
            return;
        }

  //      $('#loading').show();
//        $('#wrapper').addClass('body-load');

        $.ajax({
            url: '../business/controller/class.pazysalvo.php',
            data: { funcion: 1, dato: dato },
            dataType: "json",
            type: "POST",
            beforeSend: function() {
                $("#loadingOverlay").fadeIn(150);
            },
            success: function(resp) {
  //              $('#loading').hide();
//                $('#wrapper').removeClass('body-load');
                $("#loadingOverlay").fadeOut(150);

                console.log('Respuesta predios:', resp);

                //if (resp.ok == 1 && resp.data.length > 0) {
                if (resp.ok === 1 && Array.isArray(resp.datos)) {
                    pazysalvo.mostrarPredios(resp.datos);
                } else {
                    $("#listadoPredios").hide().empty();
                    swal({
                        type: 'info',
                        title: 'Sin resultados',
                        text: 'No se encontraron predios con ese criterio.',
                    });
                }
            },
            error: function(xhr, textStatus, errorThrown) {
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                console.log('Error AJAX:', xhr, textStatus, errorThrown);
            }
        });
    }

    /**
     * Método: mostrarPredios
     * Renderiza la lista de resultados debajo del input
     */
    mostrarPredios(predios) {
        const lista = $("#listadoPredios").empty();
        predios.forEach(p => {
            let item = `
                <a href="javascript:void(0)" class="list-group-item list-group-item-action"
                   onclick="pazysalvo.seleccionarPredio('${p.codigo_predio}','${p.nombre}','${p.direccion}',${p.tiene_deuda})">
                    <strong>${p.codigo_predio}</strong> - ${p.nombre}<br>
                    <small>${p.direccion}</small>
                </a>`;
            lista.append(item);
        });
        lista.show();
    }

    /**
     * Método: seleccionarPredio
     * Guarda la selección y muestra mensaje
     */
    seleccionarPredio(codigo, nombre, direccion, tieneDeuda) {
        //$("#txtBusquedaPredio").val(`${codigo} - ${nombre}`);
        $("#txtBusquedaPredio").val(`${codigo}`);
        $("#listadoPredios").hide();

        $("#btnGenerarPazYSalvo").prop("disabled", false);
        $("#btnGenerarPazYSalvo").attr("onclick", `pazysalvo.generarPazYSalvo('${codigo}', ${tieneDeuda})`);

        if (tieneDeuda === 1) {
            pazysalvo.mostrarMensaje("El predio tiene deuda pendiente. No se puede generar Paz y Salvo.", "danger");
        } else {
            pazysalvo.mostrarMensaje("El predio está Paz y Salvo. Puede generar el documento.", "success");
        }
    }

    /**
     * Método: generarPazYSalvo
     * Genera y descarga el PDF si está al día
     */
    generarPazYSalvo(codigo_predio, tieneDeuda) {
        if (tieneDeuda === 1) {
            swal({
                type: 'error',
                title: 'No se puede generar',
                text: 'El predio tiene deudas pendientes.',
            });
            return;
        }

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        // Abrir PDF directamente (descarga)
        window.open(`../business/controller/class.pazysalvo.php?funcion=2&codigo_predio=${codigo_predio}`, "_blank");
        //window.open(`../extensiones/certificadoPazySalvo.php?codigo=${codigo_predio}`, '_blank');

        /* Descargar PDF mediante enlace invisible

        const url = `../business/controller/class.pazysalvo.php?funcion=2&codigo_predio=${codigo_predio}`;
        const a = document.createElement('a');
        a.href = url;
        a.download = ''; 
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        */

        $('#loading').hide();
        $('#wrapper').removeClass('body-load');
    }

    /**
     * Método: mostrarMensaje
     * Muestra alertas dinámicas dentro de la vista
     */
    mostrarMensaje(texto, tipo) {
        const alerta = $("#mensajePredio");
        alerta.removeClass("d-none alert-success alert-danger")
            .addClass(`alert alert-${tipo}`)
            .html(texto);
    }

    /**
     * Método: UsuarioActivo
     * Marca el menú correspondiente como activo
     */
    UsuarioActivo() {

        $("#accordion-menu li").removeClass("active show");
        $("#accordion-menu .submenu").css("display", "none");


        $("#MConsultasExternas").addClass("active show");
        $("#SubConsultasExternas").css("display", "block");
        $("#ConsultasPazYSalvo").addClass("active");
    }
}

const pazysalvo = new PazYSalvo();

// Eventos iniciales
$("#txtBusquedaPredio").on("keyup", function() {
    pazysalvo.buscarPredios();
});

pazysalvo.UsuarioActivo();
