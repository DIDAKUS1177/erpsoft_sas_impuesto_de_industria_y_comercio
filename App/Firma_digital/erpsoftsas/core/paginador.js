/*
 * Paginador de los listados que pagina el SERVIDOR (Contribuyentes y
 * Establecimientos del municipio; ver business/class.paginacion.php).
 *
 * Pedido del cliente (2026-09-29): elegir de a 5, 10, 20, 50 o 100 por página,
 * pasar de página y ver cuántos hay en total. Pinta, dentro del contenedor que
 * se le dé, el selector de tamaño, el "Mostrando X a Y de N" y los botones de
 * página; cuando la persona cambia algo, llama a alCambiar(pagina, porPagina)
 * para que la pantalla vuelva a pedir al servidor.
 *
 *   var pag = Paginador.crear('#paginacionContribuyentes', {
 *       nombre: ['contribuyente', 'contribuyentes'],
 *       alCambiar: function (pagina, porPagina) { ...pedir... }
 *   });
 *   pag.pintar(respuesta.datos, textoBuscado);   // total, pagina, paginas, porPagina, totalGeneral
 */
var Paginador = (function () {

    var TAMANOS = [5, 10, 20, 50, 100];

    function miles(n) {
        return Number(n || 0).toLocaleString('es-CO');
    }

    /** Páginas a mostrar: la primera, la última y dos a cada lado de la actual. */
    function numeros(actual, total) {
        var lista = [];
        for (var p = 1; p <= total; p++) {
            if (p === 1 || p === total || Math.abs(p - actual) <= 2) {
                lista.push(p);
            } else if (lista[lista.length - 1] !== '…') {
                lista.push('…');
            }
        }
        return lista;
    }

    function crear(selector, opciones) {
        var estado = { pagina: 1, porPagina: 10 };
        var $caja = $(selector);
        var singular = (opciones.nombre || [])[0] || 'registro';
        var plural = (opciones.nombre || [])[1] || 'registros';

        function cambiar(pagina, porPagina) {
            estado.pagina = pagina;
            estado.porPagina = porPagina;
            if (typeof opciones.alCambiar === 'function') { opciones.alCambiar(pagina, porPagina); }
        }

        $caja.on('change', '.js-pag-tamano', function () {
            cambiar(1, parseInt($(this).val(), 10) || 10);   // otro tamaño: vuelve a la primera
        });
        $caja.on('click', '.js-pag-ir', function (e) {
            e.preventDefault();
            if ($(this).closest('li').hasClass('disabled')) { return; }
            cambiar(parseInt($(this).data('pagina'), 10) || 1, estado.porPagina);
        });

        return {
            /** Página y tamaño a pedir (la búsqueda nueva vuelve a la página 1). */
            pedido: function (reiniciar) {
                if (reiniciar) { estado.pagina = 1; }
                return { pagina: estado.pagina, porPagina: estado.porPagina };
            },

            pintar: function (datos, consulta) {
                datos = datos || {};
                var total = parseInt(datos.total, 10) || 0;
                var general = parseInt(datos.totalGeneral, 10) || total;
                var pagina = parseInt(datos.pagina, 10) || 1;
                var paginas = parseInt(datos.paginas, 10) || 1;
                var porPagina = parseInt(datos.porPagina, 10) || estado.porPagina;
                estado.pagina = pagina;
                estado.porPagina = porPagina;

                var desde = total ? (pagina - 1) * porPagina + 1 : 0;
                var hasta = Math.min(total, pagina * porPagina);

                var info = total
                    ? 'Mostrando ' + miles(desde) + ' a ' + miles(hasta) + ' de ' + miles(total)
                        + (consulta ? ' resultado' + (total === 1 ? '' : 's') : ' ' + (total === 1 ? singular : plural))
                    : (consulta ? 'Sin resultados' : 'Aún no hay ' + plural + ' registrados');

                var selectorTamano = '<select class="form-control form-control-sm js-pag-tamano" '
                    + 'style="width:auto;display:inline-block;" aria-label="Registros por página">'
                    + TAMANOS.map(function (t) {
                        return '<option value="' + t + '"' + (t === porPagina ? ' selected' : '') + '>' + t + '</option>';
                    }).join('') + '</select>';

                var botones = '';
                if (paginas > 1) {
                    botones += '<li class="page-item' + (pagina <= 1 ? ' disabled' : '') + '">'
                        + '<a class="page-link js-pag-ir" href="#" data-pagina="' + (pagina - 1) + '">Anterior</a></li>';
                    numeros(pagina, paginas).forEach(function (p) {
                        botones += p === '…'
                            ? '<li class="page-item disabled"><span class="page-link">…</span></li>'
                            : '<li class="page-item' + (p === pagina ? ' active' : '') + '">'
                                + '<a class="page-link js-pag-ir" href="#" data-pagina="' + p + '">' + p + '</a></li>';
                    });
                    botones += '<li class="page-item' + (pagina >= paginas ? ' disabled' : '') + '">'
                        + '<a class="page-link js-pag-ir" href="#" data-pagina="' + (pagina + 1) + '">Siguiente</a></li>';
                }

                $caja.html(
                    '<div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:10px;">'
                    + '<div style="font-size:13px;">Mostrar ' + selectorTamano + ' por página</div>'
                    + '<div style="font-size:13px;" class="text-muted">' + info + '</div>'
                    + (botones ? '<ul class="pagination pagination-sm mb-0">' + botones + '</ul>' : '')
                    + '</div>'
                    // El total de registrados, siempre a la vista (lo pidió el cliente
                    // para que el administrador sepa cuántos hay).
                    + '<div style="font-size:13px;margin-top:6px;"><b>Total de ' + plural + ' registrados: '
                    + miles(general) + '</b></div>'
                );
            }
        };
    }

    return { crear: crear, TAMANOS: TAMANOS };
})();
