/*
 * Pruebas de las funciones puras de la pantalla «Informe general» (core/informeGeneral.js).
 *
 *   node pruebas/informeGeneral.test.js
 *
 * Sin dependencias ni navegador. Comprueba lo que la pantalla le manda al servidor
 * (la configuración v1) y cómo pinta lo que el servidor le contesta.
 */
const path = require('path');
const L = require(path.join(__dirname, '..', 'core', 'informeGeneral.js'));

let fallas = 0, total = 0;
function igual(nombre, real, esperado) {
    total++;
    const a = JSON.stringify(real), b = JSON.stringify(esperado);
    if (a !== b) { fallas++; console.log('FALLA: ' + nombre + '\n   real:     ' + a + '\n   esperado: ' + b); }
}

const anio = { c: 'anio', etiqueta: 'Año', tipo: 'entero', ops: ['in', 'between'], min: 2000, max: 2100 };
const valor = { c: 'valorDeclarado', etiqueta: 'Total a pagar', tipo: 'dinero', ops: ['between', '>=', '<='] };
const fecha = { c: 'fechaPresentacion', etiqueta: 'Fecha', tipo: 'fecha', ops: ['between', '>=', '<='] };
const estado = { c: 'estado', etiqueta: 'Estado', tipo: 'lista', ops: ['in'], opciones: [{ v: 'pagada', t: 'Pagada' }] };
const banco = { c: 'banco', etiqueta: 'Banco', tipo: 'lista', ops: ['in'], opciones: [{ v: '16', t: 'X' }] };
const texto = { c: 'contribuyente', etiqueta: 'Contribuyente', tipo: 'texto', ops: ['contiene'] };
const ciiu = { c: 'ciiu', etiqueta: 'CIIU', tipo: 'texto', ops: ['igual'] };
const bool = { c: 'corregida', etiqueta: 'Corregida', tipo: 'booleano', ops: ['='] };

// --- Año
igual('año rango', L.filtroDesdePantalla(anio, { d: '2025', h: '2026' }), { filtro: { c: 'anio', op: 'between', v: [2025, 2026] } });
igual('año igual', L.filtroDesdePantalla(anio, { d: '2026', h: '2026' }), { filtro: { c: 'anio', op: 'in', v: [2026] } });
igual('año solo desde', L.filtroDesdePantalla(anio, { d: '2025', h: '' }), { filtro: { c: 'anio', op: 'between', v: [2025, 2100] } });
igual('año solo hasta', L.filtroDesdePantalla(anio, { d: '', h: '2026' }), { filtro: { c: 'anio', op: 'between', v: [2000, 2026] } });
igual('año vacío', L.filtroDesdePantalla(anio, { d: '', h: '' }), null);
igual('año invertido', L.filtroDesdePantalla(anio, { d: '2027', h: '2026' }).error !== undefined, true);
igual('año letras', L.filtroDesdePantalla(anio, { d: 'abc', h: '' }).error !== undefined, true);
// --- Dinero (el usuario escribe con puntos de miles)
igual('dinero entre', L.filtroDesdePantalla(valor, { d: '200.000', h: '500.000' }), { filtro: { c: 'valorDeclarado', op: 'between', v: [200000, 500000] } });
igual('dinero >=', L.filtroDesdePantalla(valor, { d: '700.000', h: '' }), { filtro: { c: 'valorDeclarado', op: '>=', v: 700000 } });
igual('dinero <=', L.filtroDesdePantalla(valor, { d: '', h: '100000' }), { filtro: { c: 'valorDeclarado', op: '<=', v: 100000 } });
// --- Fecha
igual('fecha entre', L.filtroDesdePantalla(fecha, { d: '2026-01-01', h: '2026-03-31' }), { filtro: { c: 'fechaPresentacion', op: 'between', v: ['2026-01-01', '2026-03-31'] } });
igual('fecha desde', L.filtroDesdePantalla(fecha, { d: '2026-01-01', h: '' }), { filtro: { c: 'fechaPresentacion', op: '>=', v: '2026-01-01' } });
igual('fecha a medias', L.filtroDesdePantalla(fecha, { d: '2026-01', h: '' }).error !== undefined, true);
// --- Lista, texto, booleano
igual('lista códigos', L.filtroDesdePantalla(estado, ['pagada', 'sinpagar']), { filtro: { c: 'estado', op: 'in', v: ['pagada', 'sinpagar'] } });
igual('lista bancos como enteros', L.filtroDesdePantalla(banco, ['11', '3']), { filtro: { c: 'banco', op: 'in', v: [11, 3] } });
igual('lista vacía', L.filtroDesdePantalla(estado, []), null);
igual('texto', L.filtroDesdePantalla(texto, '  maria  '), { filtro: { c: 'contribuyente', op: 'contiene', v: 'maria' } });
igual('texto vacío', L.filtroDesdePantalla(texto, '   '), null);
igual('ciiu igual con dígitos', L.filtroDesdePantalla(ciiu, '47 11'), { filtro: { c: 'ciiu', op: 'igual', v: '4711' } });
igual('ciiu sin dígitos', L.filtroDesdePantalla(ciiu, 'abc').error !== undefined, true);
igual('booleano sí', L.filtroDesdePantalla(bool, 'true'), { filtro: { c: 'corregida', op: '=', v: true } });
igual('booleano no', L.filtroDesdePantalla(bool, 'false'), { filtro: { c: 'corregida', op: '=', v: false } });
igual('booleano todos', L.filtroDesdePantalla(bool, ''), null);

// --- Ida y vuelta (plantilla -> pantalla -> configuración)
[[anio, { c: 'anio', op: 'between', v: [2025, 2026] }],
 [valor, { c: 'valorDeclarado', op: '>=', v: 700000 }],
 [valor, { c: 'valorDeclarado', op: 'between', v: [200000, 1500000] }],
 [fecha, { c: 'fechaPresentacion', op: '<=', v: '2026-03-31' }],
 [estado, { c: 'estado', op: 'in', v: ['pagada'] }],
 [banco, { c: 'banco', op: 'in', v: [11, 3] }],
 [texto, { c: 'contribuyente', op: 'contiene', v: 'prueba' }],
 [bool, { c: 'corregida', op: '=', v: false }]].forEach(function (par) {
    const raw = L.pantallaDesdeFiltro(par[0], par[1]);
    igual('ida y vuelta ' + par[0].c, L.filtroDesdePantalla(par[0], raw), { filtro: par[1] });
});
igual('año en lista de uno', L.pantallaDesdeFiltro(anio, { c: 'anio', op: 'in', v: [2026] }), { d: '2026', h: '2026' });

// --- Configuración completa
const fuente = {
    columnas: [{ c: 'documento', etiqueta: 'NIT / Documento', ordenable: true }, { c: 'contribuyente', etiqueta: 'Contribuyente', ordenable: true },
               { c: 'correo', etiqueta: 'Correo', ordenable: false }, { c: 'periodo', etiqueta: 'Mes', ordenable: true }],
    filtros: [anio, estado]
};
let r = L.construirConfig({
    modo: 'declaracion', impuesto: 'ica',
    filtros: { anio: { d: '2026', h: '2026' }, estado: ['pagada'], fantasma: 'x' },
    columnas: [{ c: 'documento', t: '' }, { c: 'contribuyente', t: ' Nombre ' }, { c: 'correo', t: 'Correo' }, { c: 'noExiste', t: '' }],
    orden: [{ c: 'documento', d: 'desc' }, { c: 'noExiste', d: 'asc' }]
}, fuente);
igual('config sin errores', r.errores, []);
igual('config', r.config, { v: 1, modo: 'declaracion', impuesto: 'ica',
    filtros: [{ c: 'anio', op: 'in', v: [2026] }, { c: 'estado', op: 'in', v: ['pagada'] }],
    columnas: [{ c: 'documento' }, { c: 'contribuyente', t: 'Nombre' }, { c: 'correo' }],
    orden: [{ c: 'documento', d: 'desc' }] });
r = L.construirConfig({ modo: 'contribuyente', impuesto: 'ica', filtros: {}, columnas: [], orden: [] }, fuente);
igual('sin columnas', r.errores, ['Elija al menos una columna para el informe.']);
igual('contribuyente sin impuesto', 'impuesto' in r.config, false);
r = L.construirConfig({ modo: 'declaracion', impuesto: 'ica', filtros: { anio: { d: '2027', h: '2026' } }, columnas: [{ c: 'documento' }], orden: [] }, fuente);
igual('error de filtro llega', r.errores.length, 1);

r = L.construirConfig({ modo: 'declaracion', impuesto: 'ica', filtros: {}, columnas: [{ c: 'documento', t: 'a<b' }], orden: [] }, fuente);
igual('título con < se rechaza antes de enviar', r.errores.length, 1);
r = L.construirConfig({ modo: 'declaracion', impuesto: 'ica', filtros: {}, columnas: [{ c: 'documento', t: 'x'.repeat(61) }], orden: [] }, fuente);
igual('título de más de 60', r.errores.length, 1);
r = L.construirConfig({ modo: 'declaracion', impuesto: 'ica', filtros: {}, columnas: [{ c: 'documento', t: 'Año "Ñandú" & Cía' }], orden: [] }, fuente);
igual('título con tildes y comillas pasa', r.errores.length, 0);

// --- Adaptar al cambiar de impuesto
const e = { columnas: [{ c: 'documento', t: '' }, { c: 'periodo', t: '' }], filtros: { anio: { d: '1', h: '' }, bimestre: ['1'] }, orden: [{ c: 'periodo', d: 'asc' }] };
const f2 = { columnas: [{ c: 'documento', ordenable: true }], filtros: [anio] };
igual('adaptar cuenta', L.adaptarAFuente(e, f2), { columnas: 1, filtros: 1, orden: 1 });
igual('adaptar deja', [e.columnas.length, Object.keys(e.filtros), e.orden.length], [1, ['anio'], 0]);

// --- Lista de columnas
let cols = [{ c: 'a', t: '' }, { c: 'b', t: '' }, { c: 'c', t: '' }];
igual('agregar repetida', L.agregarColumna(cols, 'a', 60), false);
igual('agregar nueva', L.agregarColumna(cols, 'd', 60), true);
igual('agregar con tope', L.agregarColumna(cols, 'e', 4), false);
igual('subir', [L.moverColumna(cols, 2, -1), cols.map(x => x.c).join('')], [1, 'acbd']);
igual('subir la primera', L.moverColumna(cols, 0, -1), -1);
igual('bajar la última', L.moverColumna(cols, 3, 1), -1);
igual('quitar', [L.quitarColumna(cols, 'c'), cols.map(x => x.c).join('')], [true, 'abd']);
igual('quitar inexistente', L.quitarColumna(cols, 'z'), false);

// --- Celdas
igual('dinero', L.formatearCelda(1361000, 'dinero'), '$ 1.361.000');
igual('dinero 0', L.formatearCelda(0, 'dinero'), '$ 0');
igual('entero', L.formatearCelda(1234567, 'entero'), '1.234.567');
igual('año sin separador', L.formatearCelda(2026, 'anio'), '2026');
igual('código intacto', L.formatearCelda('0012345', 'codigo'), '0012345');
igual('fecha', L.formatearCelda('2026-08-13', 'fecha'), '13/08/2026');
igual('nulo', L.formatearCelda(null, 'dinero'), '');
igual('texto con HTML queda como texto', L.formatearCelda('<b>x</b>', 'texto'), '<b>x</b>');
igual('conteo plural', L.textoConteo(3, { singular: 'declaración', plural: 'declaraciones' }), '3 declaraciones');
igual('conteo singular', L.textoConteo(1, { singular: 'declaración', plural: 'declaraciones' }), '1 declaración');
igual('conteo cero', L.textoConteo(0, { singular: 'declaración', plural: 'declaraciones' }), 'No hay declaraciones con estos filtros.');

// --- Errores del Excel
igual('excel 400 texto', L.mensajeErrorExcel(400, 'text/plain; charset=utf-8', 'El informe tiene 60.000 filas.'), 'El informe tiene 60.000 filas.');
igual('excel 403 html', L.mensajeErrorExcel(403, 'text/html', '<html>x</html>'), 'Su rol no tiene permiso para descargar este informe.');
igual('excel 401', L.mensajeErrorExcel(401, 'text/html', ''), 'Su sesión terminó. Inicie sesión de nuevo.');
igual('excel 500 con etiquetas', L.mensajeErrorExcel(500, 'text/plain', '<b>boom</b>'), 'No se pudo generar el informe. Intente de nuevo.');
igual('nombre seguro', L.nombreSeguro('  <script>Mi plantilla\n ', 100), 'scriptMi plantilla');

// --- Modo «una fila por contribuyente (declaraciones agrupadas)»: es de un impuesto, como el modo por declaración
r = L.construirConfig({ modo: 'agrupado', impuesto: 'reteica', filtros: { anio: { d: '2026', h: '2026' } }, columnas: [{ c: 'documento' }], orden: [] }, fuente);
igual('agrupado lleva el impuesto', [r.config.modo, r.config.impuesto, r.errores], ['agrupado', 'reteica', []]);

// --- Al pasar a otra fuente, las opciones de un filtro de lista que ya no existen se quitan (Corregida no es un estado de las vigentes)
const estadoVig = { c: 'estado', etiqueta: 'Estado', tipo: 'lista', ops: ['in'], opciones: [{ v: 'pagada', t: 'Pagada' }, { v: 'sinpagar', t: 'Sin pagar' }, { v: 'sinvalor', t: 'Sin valor' }] };
const eEst = { columnas: [{ c: 'documento', t: '' }], filtros: { estado: ['pagada', 'corregida', 'sinpagar'] }, orden: [] };
igual('adaptar quita solo la opción que sobra', [L.adaptarAFuente(eEst, { columnas: [{ c: 'documento', ordenable: true }], filtros: [estadoVig] }), eEst.filtros.estado],
      [{ columnas: 0, filtros: 0, orden: 0 }, ['pagada', 'sinpagar']]);
const eEst2 = { columnas: [{ c: 'documento', t: '' }], filtros: { estado: ['corregida'] }, orden: [] };
igual('adaptar quita el filtro si no queda ninguna opción', [L.adaptarAFuente(eEst2, { columnas: [{ c: 'documento', ordenable: true }], filtros: [estadoVig] }), Object.keys(eEst2.filtros)],
      [{ columnas: 0, filtros: 1, orden: 0 }, []]);
const bancoSinOpc = { c: 'banco', etiqueta: 'Banco', tipo: 'lista', ops: ['in'], opciones: null };
const eBan = { columnas: [{ c: 'documento', t: '' }], filtros: { banco: ['16'] }, orden: [] };
L.adaptarAFuente(eBan, { columnas: [{ c: 'documento', ordenable: true }], filtros: [bancoSinOpc] });
igual('adaptar no toca las listas sin opciones fijas', eBan.filtros.banco, ['16']);

// --- Orden de la vista previa: ascendente, descendente, sin orden
igual('orden: primer clic', L.siguienteOrden(null, 'valorDeclarado'), { c: 'valorDeclarado', d: 'asc' });
igual('orden: segundo clic', L.siguienteOrden({ c: 'valorDeclarado', d: 'asc' }, 'valorDeclarado'), { c: 'valorDeclarado', d: 'desc' });
igual('orden: tercer clic quita el orden', L.siguienteOrden({ c: 'valorDeclarado', d: 'desc' }, 'valorDeclarado'), null);
igual('orden: otra columna empieza ascendente', L.siguienteOrden({ c: 'valorDeclarado', d: 'desc' }, 'documento'), { c: 'documento', d: 'asc' });
igual('orden vigente si la columna sigue', L.ordenVigente({ c: 'a', d: 'asc' }, [{ c: 'a', t: '' }]), { c: 'a', d: 'asc' });
igual('orden se descarta si la columna salió del informe', L.ordenVigente({ c: 'z', d: 'asc' }, [{ c: 'a', t: '' }]), null);
igual('sin orden', L.ordenVigente(null, [{ c: 'a', t: '' }]), null);

// --- Fila de totales de la vista previa: el rótulo no tapa una suma
const unidad = { singular: 'declaración', plural: 'declaraciones' };
let ft = L.filaTotales([{ c: 'documento', tipo: 'codigo', total: false }, { c: 'valor', tipo: 'dinero', total: true }], { valor: 1020000 }, 12, unidad);
igual('totales: rótulo en la primera columna y la suma en su sitio', ft, { celdas: ['Total de las 12 declaraciones', '$ 1.020.000'], rotuloAparte: null });
ft = L.filaTotales([{ c: 'valor', tipo: 'dinero', total: true }, { c: 'documento', tipo: 'codigo', total: false }, { c: 'pagado', tipo: 'dinero', total: true }], { valor: 1020000, pagado: 0 }, 1, unidad);
igual('totales: si la primera columna suma, el rótulo pasa a la primera que no suma', ft, { celdas: ['$ 1.020.000', 'Total de la 1 declaración', '$ 0'], rotuloAparte: null });
ft = L.filaTotales([{ c: 'valor', tipo: 'dinero', total: true }, { c: 'pagado', tipo: 'dinero', total: true }], { valor: 5, pagado: 6 }, 2, unidad);
igual('totales: si todas suman, el rótulo va aparte', ft, { celdas: ['$ 5', '$ 6'], rotuloAparte: 'Total de las 2 declaraciones' });
igual('totales: sin columnas de dinero no hay fila', L.filaTotales([{ c: 'documento', tipo: 'codigo', total: false }], {}, 3, unidad), null);
igual('totales: una columna de dinero sin suma del servidor no cuenta', L.filaTotales([{ c: 'valor', tipo: 'dinero', total: true }], {}, 3, unidad), null);

console.log(fallas ? ('FALLARON ' + fallas + ' de ' + total) : ('Todo correcto (' + total + ' pruebas)'));
process.exit(fallas ? 1 : 0);
