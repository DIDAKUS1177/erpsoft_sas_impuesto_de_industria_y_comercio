/* ============================================================================
   059 — Nombres completos de las actividades (Paipa, Acuerdo 019 de 2022)
   ----------------------------------------------------------------------------
   Pedido de la Alcaldía de Paipa (documento "CAMBIOS ICA WEB", punto 5,
   2026-11-19): "corregir el nombre de las actividades, que los nombres estén
   completos". El 2026-11-20 Jennyfer envió el Excel "TARIFAS 2023_ACUERDO 019
   DE 2022.xlsx" con las 68 actividades. Juan confirmó que el nombre nuevo debe
   verse también en lo ya emitido (los documentos leen el nombre del catálogo).

   QUÉ HACE
     Cambia acc_Nombre, por CÓDIGO y en todos los años del catálogo, al nombre
     completo del Excel (65 actividades). SOLO si la tarifa que tiene hoy el
     código es la misma del Excel: si en producción ese código vale otra cosa,
     no se toca y se avisa. Antes de cambiar guarda el nombre anterior en
     dbo.ind_actividadescomercio_respaldo_059.

   QUÉ NO HACE
     - No cambia NINGUNA tarifa (las 68 del Excel ya coinciden con el sistema).
     - No toca 316, 319 ni 324: en el sistema describen OTRA actividad que en el
       Excel (consultoría / contratistas / demás servicios contra acabado de
       edificios / almacenamiento / casa de empeño). Falta que la Alcaldía diga
       cuál vale; cambiarlos le cambiaría la actividad a quien ya los tiene.
     - No toca el 218 ("Demás actividades comerciales"), que no viene en el Excel.
     - Solo corre en la base de Paipa (y en copias locales de prueba): el Acuerdo
       019 de 2022 es de Paipa y cada municipio tiene su propio catálogo.

   RIESGO: bajo. Solo texto; re-ejecutable (la segunda vez no cambia nada).
   ============================================================================ */

SET NOCOUNT ON;
GO

IF DB_NAME() LIKE 'erpsofts[_]ind[_]comercio[_]%' AND DB_NAME() <> 'erpsofts_ind_comercio_paip'
BEGIN
    PRINT '  (esta base no es la de Paipa: el catalogo de actividades no se toca)';
    SET NOEXEC ON;
END
GO

IF OBJECT_ID('dbo.ind_actividadescomercio_respaldo_059', 'U') IS NULL
    CREATE TABLE dbo.ind_actividadescomercio_respaldo_059 (
        acc_Id          INT           NOT NULL,
        acc_Codigo      NVARCHAR(50)  NULL,
        acc_NombreAntes NVARCHAR(500) NULL,
        res_Fecha       DATETIME      NOT NULL DEFAULT GETDATE()
    );
GO

DECLARE @nuevos TABLE (codigo NVARCHAR(50) NOT NULL, tarifa DECIMAL(9,4) NOT NULL, nombre NVARCHAR(500) NOT NULL);
INSERT INTO @nuevos (codigo, tarifa, nombre) VALUES
        (N'101', 0.0040, N'Producción, procesamiento, transformación y conservación de carnes y pescados y sus derivados.'),
        (N'102', 0.0040, N'Elaboración de productos alimenticios a base de frutas, legumbres, hortalizas, leche, almidones, tubérculos, productos de molinería, panadería y otros productos alimenticios.'),
        (N'103', 0.0040, N'Productos de calzado, partes de calzado, prendas de vestir, prendas y tejidos en otros materiales.'),
        (N'104', 0.0050, N'Fabricación de artículos confeccionados en cuero natural o recuperado, o combinaciones de estos con otros materiales, siempre que la materia constitutiva básica sea el cuero, artículos de talabartería y guarnicionería.'),
        (N'105', 0.0060, N'Elaboración de comidas y platos preparados por vendedores informales o puestos de venta móviles.'),
        (N'106', 0.0060, N'Fabricación de artículos de vidrio, cerámica, porcelana, hormigón, cemento, yeso y similares.'),
        (N'107', 0.0060, N'Fabricación de joyas, bisutería y artículos conexos'),
        (N'108', 0.0070, N'Fabricación de productos de hierro, acero, aluminio, de materiales de construcción; carrocerías, fabricación de partes de piezas y accesorios de motores y automotores. Fundición de hierro y acero.'),
        (N'109', 0.0070, N'Fabricación de maquinaria y equipo de uso especial'),
        (N'110', 0.0070, N'Reciclaje de materiales metálicos y no metálicos.'),
        (N'111', 0.0070, N'Fabricación de bebidas alcohólicas, jugos, derivados de frutas, fabricación de cerveza tipo artesanal, aguas tratadas.'),
        (N'112', 0.0070, N'Fabricación de muebles y otros elementos de madera y/o metálicos.'),
        (N'113', 0.0070, N'Construcción de edificaciones, carreteras y vías.'),
        (N'114', 0.0070, N'Demás actividades industriales.'),
        (N'201', 0.0040, N'Tiendas, comercio de frutas, verduras, leche, productos lácteos y huevos.'),
        (N'202', 0.0050, N'Comercio de materias primas, productos agrícolas en bruto, en establecimientos especializados. Animales vivos.'),
        (N'203', 0.0060, N'Comercio de productos farmacéuticos, medicinales y veterinarios.'),
        (N'204', 0.0050, N'Comercio de papelería, servicio de fotocopiado, misceláneas y productos de belleza y artículos de tocador.'),
        (N'205', 0.0070, N'Comercio al por menor y al por mayor de carnes (incluye aves de corral, productos cárnicos, pescados, productos de mar).'),
        (N'206', 0.0070, N'Comercio de artesanías, joyas, prendas de vestir, accesorios, calzado y textiles.'),
        (N'207', 0.0080, N'Comercio de muebles, electrodomésticos, artículos de uso doméstico, maquinaria y equipo de oficina, equipo fotográfico, equipo médico y de cómputo.'),
        (N'208', 0.0080, N'Comercio en supermercados, mini mercados y droguerías.'),
        (N'209', 0.0080, N'Comercio de partes, piezas (autopartes) y accesorios (lujos) para vehículos automotores.'),
        (N'210', 0.0090, N'Comercio de vehículos automotores, motocicletas y bicicletas.'),
        (N'211', 0.0080, N'Comercio de artículos de ferretería, cerrajería, vidrio, pinturas y tubería.'),
        (N'212', 0.0090, N'Comercio de combustibles derivados del petróleo, lubricantes, aditivos y productos de limpieza para automotores.'),
        (N'213', 0.0100, N'Comercio de bebidas alcohólicas, gaseosas y cigarrillos.'),
        (N'214', 0.0100, N'Comercio de gas natural y conexiones para el gas natural.'),
        (N'215', 0.0100, N'Comercio de líneas telefónicas y telefonía celular y sus accesorios.'),
        (N'216', 0.0100, N'Comercio en carbones, materiales de construcción, puzolana, y otros materiales de extracción.'),
        (N'217', 0.0100, N'Comercio de energía eléctrica, transmisión y conexión de energía eléctrica.'),
        (N'219', 0.0100, N'Demás actividades comerciales.'),
        (N'301', 0.0070, N'Servicio de transporte automotor terrestre, urbano, intermunicipal, escolar, especial y de carga. Transporte y tubería.'),
        (N'302', 0.0070, N'Restaurantes, heladerías, cafeterías, fuentes de soda y similares.'),
        (N'303', 0.0070, N'Consultores de informática, suministros de programas de informática, equipos de informática, desarrollo de programas informáticos. Actividades de radio.'),
        (N'304', 0.0070, N'Parqueaderos, lavaderos de vehículos, servicio de montallantas, talleres de mecánica, latonería, pintura, y otros servicios conexos.'),
        (N'305', 0.0070, N'Hoteles, aparta hoteles, centros vacacionales, centros de atracciones, residencias, moteles, amoblados, alojamiento rural, zonas de camping, y demás servicios de hospedaje.'),
        (N'306', 0.0070, N'Diferentes tipos de educación, básica primaria, secundaria y media, académica, educación inicial, preescolar. Educación técnica, tecnológica, universitaria, formación para el trabajo y demás tipos de enseñanza formal y no formal. Guarderías'),
        (N'307', 0.0070, N'Bares, discotecas, licoreras, cantinas y similares.'),
        (N'308', 0.0070, N'Actividades de apoyo a la agricultura, ganadería, silvicultura, extracción y similares'),
        (N'309', 0.0080, N'Servicio de veterinaria.'),
        (N'310', 0.0080, N'Servicios de mensajería, vigilancia y aseo, lavanderías y notarías. Limpieza de edificaciones e instalaciones industriales.'),
        (N'311', 0.0080, N'Servicios de empleo temporal.'),
        (N'312', 0.0080, N'Servicios de salud prestados por IPS, EPS, EPS régimen subsidiado y demás entidades prestadoras de salud.'),
        (N'313', 0.0080, N'Publicidad, fotografía, floristerías, actividades inmobiliarias, alquileres y arrendamientos en general.'),
        (N'314', 0.0090, N'Servicio de funeraria, seguros, fondos de pensiones y cesantías, servicios de administración financiera.'),
        (N'315', 0.0090, N'Instalaciones eléctricas, fontanería, calefacción y aire acondicionado de la construcción y similares'),
        (N'317', 0.0090, N'Peluquerías, salas de belleza y demás tratamientos de belleza'),
        (N'318', 0.0100, N'Mantenimiento y reparación especializado de diferentes tipos de maquinaria y equipo'),
        (N'320', 0.0100, N'Catering para eventos, organización de convenciones y eventos'),
        (N'321', 0.0100, N'Actividades de agencias de viajes y operadores turísticos'),
        (N'322', 0.0100, N'Otra clase de transporte (aéreo y ferrovial).'),
        (N'323', 0.0100, N'Restaurantes flotantes, bares flotantes, cafeterías flotantes sobre cuerpos de agua, alquiler de equipo de deportes acuáticos y transporte acuático.'),
        (N'325', 0.0100, N'Distribución de energía eléctrica, telefonía móvil, televisión por cable, televisión satelital y telecomunicaciones en general.'),
        (N'326', 0.0100, N'Actividades de consultoría profesional y de ejercicio profesional, sociedades de contratistas y demás servicios profesionales. Contratos de régimen subsidiado y demás servicios conexos. Actividades de arquitectura e ingeniería.'),
        (N'327', 0.0100, N'Servicios médicos, odontológicos, servicios de oftalmología, laboratorios clínicos y estéticos y demás profesionales de la salud.'),
        (N'328', 0.0100, N'Servicios de acueducto, alcantarillado y aseo, medición (empresas de servicios públicos).'),
        (N'329', 0.0080, N'Servicios prestados por contratista de construcción de obras civiles, urbanizadoras y demás actividades de construcción.'),
        (N'330', 0.0080, N'Casas de juego con ventas de licor y canchas de tejo.'),
        (N'331', 0.0100, N'Instalación especializada de maquinaria y equipo industrial'),
        (N'332', 0.0100, N'Servicio de sacrificio y degüello de ganado.'),
        (N'333', 0.0100, N'Servicios de clubes sociales, clubes residenciales, servicios de piscina diferentes al ITP.'),
        (N'334', 0.0100, N'Demás actividades de servicio.'),
        (N'401', 0.0030, N'Corporaciones de ahorro y vivienda'),
        (N'402', 0.0050, N'Demás entidades financieras.');

-- Códigos del Excel cuya tarifa en esta base NO es la del Excel: no se tocan.
DECLARE @distintos INT = (
    SELECT COUNT(*) FROM dbo.ind_actividadescomercio a
      JOIN @nuevos n ON n.codigo = LTRIM(RTRIM(CAST(a.acc_Codigo AS NVARCHAR(50))))
     WHERE ABS(CAST(a.acc_Tarifa AS DECIMAL(9,4)) - n.tarifa) > 0.00001);
IF @distintos > 0
    PRINT '  AVISO: ' + CAST(@distintos AS VARCHAR(10)) + ' fila(s) con tarifa distinta a la del Excel: se dejan como estan (revisar a mano).';

BEGIN TRANSACTION;

INSERT INTO dbo.ind_actividadescomercio_respaldo_059 (acc_Id, acc_Codigo, acc_NombreAntes)
SELECT a.acc_Id, CAST(a.acc_Codigo AS NVARCHAR(50)), a.acc_Nombre
  FROM dbo.ind_actividadescomercio a
  JOIN @nuevos n ON n.codigo = LTRIM(RTRIM(CAST(a.acc_Codigo AS NVARCHAR(50))))
 WHERE ABS(CAST(a.acc_Tarifa AS DECIMAL(9,4)) - n.tarifa) <= 0.00001
   AND ISNULL(a.acc_Nombre, N'') <> n.nombre;

UPDATE a
   SET a.acc_Nombre = n.nombre,
       a.acc_FechaActualizacion = GETDATE()
  FROM dbo.ind_actividadescomercio a
  JOIN @nuevos n ON n.codigo = LTRIM(RTRIM(CAST(a.acc_Codigo AS NVARCHAR(50))))
 WHERE ABS(CAST(a.acc_Tarifa AS DECIMAL(9,4)) - n.tarifa) <= 0.00001
   AND ISNULL(a.acc_Nombre, N'') <> n.nombre;

PRINT '  Nombres de actividades actualizados: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

COMMIT TRANSACTION;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '059_nombres_completos_actividades_paipa')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('059_nombres_completos_actividades_paipa',
            N'Nombres completos de las actividades segun el Excel de la Alcaldia (Acuerdo 019 de 2022). Sin cambiar tarifas; 316, 319, 324 y 218 quedan como estaban.');
GO

SET NOEXEC OFF;
GO

/* ----------------------------------------------------------------------------
   VUELTA ATRÁS
       UPDATE a SET a.acc_Nombre = r.acc_NombreAntes
         FROM dbo.ind_actividadescomercio a
         JOIN dbo.ind_actividadescomercio_respaldo_059 r ON r.acc_Id = a.acc_Id;
       DELETE FROM dbo.conf_migraciones WHERE mig_Nombre = '059_nombres_completos_actividades_paipa';
       DROP TABLE dbo.ind_actividadescomercio_respaldo_059;
   ---------------------------------------------------------------------------- */
