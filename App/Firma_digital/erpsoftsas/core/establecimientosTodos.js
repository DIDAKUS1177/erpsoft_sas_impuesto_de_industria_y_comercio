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

        var gestionar = tieneDueno
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

        // Sin buscador, paginación ni contador de DataTables: busca el servidor y
        // nunca llegan más de 20 filas. order [] respeta el orden del servidor.
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

    /** Texto bajo el buscador: cuántos resultados hay y si conviene afinar. */
    function textoEstado(consulta, cantidad, hayMas) {
        if (!consulta) {
            if (hayMas) {
                return 'Estos son los 20 registrados más recientemente. Escribe para buscar entre todos.';
            }
            if (!cantidad) { return 'Aún no hay establecimientos registrados.'; }
            return cantidad === 1 ? '1 establecimiento registrado.' : cantidad + ' establecimientos registrados.';
        }
        if (!cantidad) { return 'Ningún establecimiento coincide con «' + consulta + '».'; }
        if (hayMas) {
            return 'Se muestran los primeros 20 resultados para «' + consulta + '». Escribe más datos para afinar.';
        }
        return (cantidad === 1 ? '1 resultado' : cantidad + ' resultados') + ' para «' + consulta + '».';
    }

    function buscar() {
        var consulta = ($('#buscarEstablecimiento').val() || '').trim();
        var miTurno = ++turno;

        $('#estadoBusqueda').text('Buscando…');

        $.ajax({
            url: '../business/controller/class.establecimientos.php',
            data: { funcion: 22, buscar: consulta },
            dataType: 'json',
            type: 'POST',
            success: function (arr) {
                // Si la persona siguió escribiendo, esta respuesta ya no vale.
                if (miTurno !== turno) { return; }

                if (arr.ok != 1 || !arr.datos || !arr.datos.filas) {
                    pintar([]);
                    $('#estadoBusqueda').text(arr.mensaje || 'No se pudo buscar. Intenta de nuevo.');
                    return;
                }
                pintar(arr.datos.filas);
                $('#estadoBusqueda').text(textoEstado(consulta, arr.datos.filas.length, arr.datos.hayMas));
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
        espera = setTimeout(buscar, 300);
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
        ContribActivo.fijar({
            id: String($(this).data('id')),
            doc: String($(this).data('doc') || ''),
            nombre: $(this).data('nombre') || ''
        });
        window.location = 'establecimientos.php';
    });

    $(function () {
        marcarMenu();
        buscar();
    });

    return { buscar: buscar };
})();
