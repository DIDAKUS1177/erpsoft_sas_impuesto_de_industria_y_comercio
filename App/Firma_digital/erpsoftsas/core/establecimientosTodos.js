/*
 * Todos los establecimientos del municipio: el ítem "Establecimientos" que el
 * administrador tiene bajo Contribuyentes (retro cliente 2026-09-24).
 *
 * Es un DIRECTORIO, no un segundo editor. Se busca en el servidor
 * (class.establecimientos.php, función 22: máximo 20 filas, sin tildes, por
 * nombre, dirección, código o por el documento/nombre del dueño) y para editar
 * un establecimiento se "Gestiona" a su contribuyente, que abre
 * establecimientos.php con todas sus herramientas. Así el formulario de edición
 * sigue tomando el dueño del contribuyente activo y nunca guarda un local a
 * nombre de otro.
 */
var EstablecimientosTodos = (function () {

    var turno = 0;      // número de la última búsqueda pedida
    var espera = null;  // pausa mientras la persona sigue escribiendo

    /** Texto para meterlo en HTML sin romper el marcado. */
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
            .replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function nombreDe(f) {
        return ((f.ind_PrimerNombre || '') + ' ' + (f.ind_PrimerApellido || '')).trim();
    }

    function fila(f) {
        var nombre = nombreDe(f);
        var activo = f.est_Activo == 1;
        var tieneDueno = !!f.est_IdContribuyente;

        // "Gestionar a un contribuyente" del panel de Roles: sin el, el
        // directorio es solo de consulta.
        var puedeGestionar = (typeof erpPuede !== 'function' || erpPuede('alcaldia.contribuyentes.gestionar'));
        var gestionar = !puedeGestionar ? ''
            : tieneDueno
            ? '<button type="button" class="acc-card acc-primary js-gestionar-dueno" title="Trabajar como el dueño de este establecimiento" '
                + 'data-id="' + esc(f.est_IdContribuyente) + '" '
                + 'data-doc="' + esc(f.ind_NumeroIdentificacion) + '" '
                + 'data-nombre="' + esc(nombre) + '">'
                + '<i class="fa fa-briefcase"></i><span class="acc-lbl">Gestionar</span></button>'
            : '<span class="acc-card acc-off" title="El establecimiento no tiene contribuyente">'
                + '<i class="fa fa-briefcase"></i><span class="acc-lbl">Gestionar</span></span>';

        return '<tr>'
            + '<td>' + esc(f.est_Nombre)
                + '<div class="text-muted" style="font-size:12px;">Código ' + esc(f.est_Codigo) + '</div></td>'
            + '<td>' + esc(f.est_Direccion) + '</td>'
            + '<td>' + (tieneDueno ? esc(nombre || 'Contribuyente')
                + '<div class="text-muted" style="font-size:12px;">' + esc(f.ind_NumeroIdentificacion) + '</div>'
                : '<span class="text-muted">Sin contribuyente</span>') + '</td>'
            + '<td><span class="chip-estado ' + (activo ? 'est-activo">Activo' : 'est-cerrado">Cerrado') + '</span></td>'
            + '<td align="center"><div class="acc-cards">' + gestionar + '</div></td>'
            + '</tr>';
    }

    function pintar(filas) {
        $('#tablaEstablecimientos').DataTable().destroy();
        $('#bodyEstablecimientos').html(filas.map(fila).join(''));

        // Sin buscador, paginación ni contador de DataTables: busca y pagina el
        // servidor (core/paginador.js). order [] respeta el orden del servidor.
        $('#tablaEstablecimientos').DataTable({
            scrollCollapse: true,
            autoWidth: false,
            responsive: true,
            searching: false,
            paging: false,
            info: false,
            order: [],
            columnDefs: [{ targets: 'datatable-nosort', orderable: false }],
            language: { emptyTable: 'Sin resultados' }
        });
    }

    /** Texto bajo el buscador (el detalle de páginas lo da el paginador). */
    function textoEstado(consulta, total) {
        total = parseInt(total, 10) || 0;
        if (!consulta) { return 'Registrados más recientemente primero. Escribe para buscar por nombre, dirección o documento.'; }
        if (!total) { return 'Ningún establecimiento coincide con «' + consulta + '».'; }
        return (total === 1 ? '1 resultado' : total.toLocaleString('es-CO') + ' resultados') + ' para «' + consulta + '».';
    }

    // Paginado en el servidor: cambiar de página o de tamaño vuelve a pedir con
    // el mismo texto buscado.
    var paginador = Paginador.crear('#paginacionEstablecimientos', {
        nombre: ['establecimiento', 'establecimientos'],
        alCambiar: function () { buscar(true); }
    });

    function buscar(mismaPagina) {
        var consulta = ($('#buscarEstablecimiento').val() || '').trim();
        var miTurno = ++turno;
        // Una búsqueda nueva empieza en la página 1; cambiar de página no.
        var pedido = paginador.pedido(mismaPagina !== true);

        $('#estadoBusqueda').text('Buscando…');

        $.ajax({
            url: '../business/controller/class.establecimientos.php',
            data: { funcion: 22, buscar: consulta, pagina: pedido.pagina, porPagina: pedido.porPagina },
            dataType: 'json',
            type: 'POST',
            success: function (arr) {
                // Si la persona siguió escribiendo, esta respuesta ya no vale.
                if (miTurno !== turno) { return; }

                if (arr.ok != 1 || !arr.datos || !arr.datos.filas) {
                    pintar([]);
                    paginador.pintar({}, consulta);
                    $('#estadoBusqueda').text(arr.mensaje || 'No se pudo buscar. Intenta de nuevo.');
                    return;
                }
                pintar(arr.datos.filas);
                paginador.pintar(arr.datos, consulta);
                $('#estadoBusqueda').text(textoEstado(consulta, arr.datos.total));
            },
            error: function () {
                if (miTurno !== turno) { return; }
                $('#estadoBusqueda').text('No se pudo buscar. Revisa la conexión e intenta de nuevo.');
            }
        });
    }

    function marcarMenu() {
        $('#accordion-menu li').removeClass('active show');
        $('#accordion-menu .submenu').css('display', 'none');
        $('#MEstablecimientosTodos').addClass('active');
    }

    // Busca mientras se escribe, con una pausa corta; Enter busca de una.
    $(document).on('input', '#buscarEstablecimiento', function () {
        clearTimeout(espera);
        espera = setTimeout(function () { buscar(); }, 300);
    });
    $(document).on('keydown', '#buscarEstablecimiento', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(espera);
            buscar();
        }
    });

    // Gestionar: el dueño queda como contribuyente activo y se abren SUS
    // establecimientos (los datos van por data-* por si el nombre trae comillas).
    $(document).on('click', '.js-gestionar-dueno', function () {
        // Sus establecimientos, o la primera pantalla del contribuyente que el
        // rol puede usar (interruptores del panel de Roles).
        var destino = (typeof menu !== 'undefined' && menu.pantallaDelContribuyente)
            ? menu.pantallaDelContribuyente('establecimientos.php') : 'establecimientos.php';
        if (!destino) {
            swal({
                type: 'warning',
                title: 'Sin secciones del contribuyente',
                text: 'Su rol puede gestionar contribuyentes, pero no tiene permiso en ninguna de sus secciones '
                    + '(RIT, establecimientos o declaraciones). Pídaselo al administrador.'
            });
            return;
        }
        ContribActivo.fijar({
            id: String($(this).data('id')),
            doc: String($(this).data('doc') || ''),
            nombre: $(this).data('nombre') || ''
        });
        window.location = destino;
    });

    $(function () {
        marcarMenu();
        buscar();
    });

    return { buscar: buscar };
})();
