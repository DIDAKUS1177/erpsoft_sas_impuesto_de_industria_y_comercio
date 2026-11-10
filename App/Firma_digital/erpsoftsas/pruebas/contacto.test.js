/*
 * Pruebas de los avisos con el contacto del municipio (core/contacto.js).
 *
 * Se ejecutan con:   node pruebas/contacto.test.js
 * Sin dependencias: no hay framework, solo Node.
 *
 * Lo que más importa: con el contacto de Paipa los dos avisos quedan IDÉNTICOS a
 * como estaban escritos (letra por letra, errata incluida), y en cualquier otro
 * caso no sale nada de Paipa.
 */
const C = require('../core/contacto.js');

let pasadas = 0;
const fallidas = [];

function comprobar(descripcion, obtenido, esperado) {
    if (Object.is(obtenido, esperado)) {
        pasadas++;
    } else {
        fallidas.push(`  ✗ ${descripcion}\n      esperado: ${esperado}\n      obtenido: ${obtenido}`);
    }
}

// Los textos TAL COMO estaban escritos en los seis sitios antes de la 054.
const ANTES_NUEVO_ESTABLECIMIENTO =
    'Para la creación de un nuevo establecimiento diríjase de manera presencial a la secretaria de Hacienda del municipio de Paipa o realice el trámite a través del correo impuestos@paipa-boyaca.gov.co.';
const ANTES_PRESENTAR_Y_PAGAR =
    'Para la PRESENTACIÓN y PAGO de la Declaración diríjase a las entidades financieras o realice transferecia bancaria y envíe los respectivos soportes al correo: impuestos@paipa-boyaca.gov.co.';

const PAIPA = { dependencia: 'Secretaría de Hacienda', telefono: '318 530 9285', correo: 'impuestos@paipa-boyaca.gov.co', ciudad: 'Paipa' };
const GUATEQUE = { dependencia: 'Tesorería', telefono: '311 000 1111', correo: 'hacienda@guateque.gov.co', ciudad: 'Guateque' };

/* ---------- Paipa: el texto no cambia ---------- */
comprobar('Paipa: creación de establecimiento idéntico al de siempre', C.textoNuevoEstablecimiento(PAIPA), ANTES_NUEVO_ESTABLECIMIENTO);
comprobar('Paipa: presentación y pago idéntico al de siempre (con la errata)', C.textoPresentarYPagar(PAIPA), ANTES_PRESENTAR_Y_PAGAR);

/* ---------- Otro municipio con sus datos ---------- */
comprobar('Guateque: establecimiento',
    C.textoNuevoEstablecimiento(GUATEQUE),
    'Para la creación de un nuevo establecimiento diríjase de manera presencial a la secretaria de Hacienda del municipio de Guateque o realice el trámite a través del correo hacienda@guateque.gov.co.');
comprobar('Guateque: presentación y pago',
    C.textoPresentarYPagar(GUATEQUE),
    'Para la PRESENTACIÓN y PAGO de la Declaración diríjase a las entidades financieras o realice transferecia bancaria y envíe los respectivos soportes al correo: hacienda@guateque.gov.co.');
comprobar('Guateque: ningún aviso nombra a Paipa',
    /paipa/i.test(C.textoNuevoEstablecimiento(GUATEQUE) + C.textoPresentarYPagar(GUATEQUE)), false);

/* ---------- Sin correo: no se menciona ningún correo ni otro municipio ---------- */
const SIN_CORREO = { ciudad: 'Guateque', correo: null, telefono: '311 000 1111' };
comprobar('sin correo: establecimiento',
    C.textoNuevoEstablecimiento(SIN_CORREO),
    'Para la creación de un nuevo establecimiento diríjase de manera presencial a la secretaria de Hacienda del municipio de Guateque.');
comprobar('sin correo: presentación y pago',
    C.textoPresentarYPagar(SIN_CORREO),
    'Para la PRESENTACIÓN y PAGO de la Declaración diríjase a las entidades financieras o realice transferecia bancaria y entregue los respectivos soportes en la secretaria de Hacienda del municipio de Guateque.');
comprobar('sin correo: no sale "correo" ni arroba',
    /correo|@/i.test(C.textoNuevoEstablecimiento(SIN_CORREO) + C.textoPresentarYPagar(SIN_CORREO)), false);

/* ---------- Sin ciudad ---------- */
comprobar('sin ciudad, con correo: establecimiento',
    C.textoNuevoEstablecimiento({ correo: 'hacienda@guateque.gov.co' }),
    'Para la creación de un nuevo establecimiento diríjase de manera presencial a la secretaria de Hacienda de su municipio o realice el trámite a través del correo hacienda@guateque.gov.co.');
comprobar('sin ciudad, con correo: presentación y pago',
    C.textoPresentarYPagar({ correo: 'hacienda@guateque.gov.co' }),
    'Para la PRESENTACIÓN y PAGO de la Declaración diríjase a las entidades financieras o realice transferecia bancaria y envíe los respectivos soportes al correo: hacienda@guateque.gov.co.');

/* ---------- Sin nada de contacto ---------- */
const NADA = [null, undefined, {}, { dependencia: null, telefono: null, correo: null, ciudad: null }, { correo: '   ', ciudad: '' }];
NADA.forEach(function (c, i) {
    comprobar('sin contacto #' + i + ': establecimiento',
        C.textoNuevoEstablecimiento(c),
        'Para la creación de un nuevo establecimiento diríjase de manera presencial a la secretaria de Hacienda de su municipio.');
    comprobar('sin contacto #' + i + ': presentación y pago',
        C.textoPresentarYPagar(c),
        'Para la PRESENTACIÓN y PAGO de la Declaración diríjase a las entidades financieras o realice transferecia bancaria y entregue los respectivos soportes en la secretaria de Hacienda de su municipio.');
    comprobar('sin contacto #' + i + ': nada de Paipa ni de correos',
        /paipa|@|correo/i.test(C.textoNuevoEstablecimiento(c) + C.textoPresentarYPagar(c)), false);
});

/* ---------- datos(): limpia y lee la página ---------- */
comprobar('datos(): recorta espacios', C.datos({ correo: '  a@b.co  ', ciudad: ' X ' }).correo, 'a@b.co');
comprobar('datos(): recorta la ciudad', C.datos({ correo: '  a@b.co  ', ciudad: ' X ' }).ciudad, 'X');
comprobar('datos(): null es cadena vacía', C.datos({ correo: null }).correo, '');
comprobar('datos(): sin nada devuelve cadenas vacías', JSON.stringify(C.datos()), JSON.stringify({ dependencia: '', telefono: '', correo: '', ciudad: '' }));

// Sin argumento usa window.ERP_CONTACTO (lo que deja dist/menu.php).
global.window = { ERP_CONTACTO: GUATEQUE };
comprobar('sin argumento lee window.ERP_CONTACTO', C.textoPresentarYPagar(), C.textoPresentarYPagar(GUATEQUE));
global.window = { ERP_CONTACTO: PAIPA };
comprobar('con el window de Paipa sale el texto de siempre', C.textoNuevoEstablecimiento(), ANTES_NUEVO_ESTABLECIMIENTO);
delete global.window;

/* ---------- Los valores van como texto: ni se escapan ni se interpretan ---------- */
// swal({ text }) los pone con textContent; aquí solo se comprueba que el aviso los
// conserva tal cual y no revienta con comillas ni signos raros.
const RARO = { correo: 'a"b\'c@d.co', ciudad: 'San <José> & Cía' };
comprobar('valores raros: el texto los conserva',
    C.textoNuevoEstablecimiento(RARO),
    'Para la creación de un nuevo establecimiento diríjase de manera presencial a la secretaria de Hacienda del municipio de San <José> & Cía o realice el trámite a través del correo a"b\'c@d.co.');

/* ---------- Resultado ---------- */
const total = pasadas + fallidas.length;
if (fallidas.length) {
    console.log(`FALLARON ${fallidas.length} de ${total}:\n` + fallidas.join('\n'));
    process.exit(1);
}
console.log(`contacto.test.js: ${pasadas} de ${total} casos pasan.`);
