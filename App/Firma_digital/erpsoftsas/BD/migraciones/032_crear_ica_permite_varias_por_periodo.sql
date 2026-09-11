/* ============================================================================
   032 — Crear declaracion de ICA permite VARIAS por periodo (de verdad)
   ----------------------------------------------------------------------------
   La migracion 027 debia quitar el freno que impide dos declaraciones
   ORIGINALES del mismo contribuyente y periodo, por instruccion del cliente
   -se le advirtio que eso permite dos originales del mismo periodo y lo
   confirmo; lo reafirmo el 2026-09-11-. Pero 027 apuntaba al nombre VIEJO del
   indice (UQ_declaracion_periodo_nuevas), que la migracion 020 ya habia
   renombrado a UQ_declaracion_contribuyente_periodo.

   Resultado: 027 quedo registrada como aplicada pero no quito nada, y el indice
   siguio bloqueando. Como el codigo ya NO reabre el borrador existente -crear
   crea siempre-, "Crear Declaracion" chocaba contra el indice con un error 2601
   ("Cannot insert duplicate key ... UQ_declaracion_contribuyente_periodo") y la
   pantalla contestaba "No se pudo crear la declaracion". Ese es el
   "cuando le doy crear en industria y comercio no me permite".

   Esta migracion quita el indice que REALMENTE existe hoy.

   LO QUE NO SE TOCA
   UQ_declaracion_numero se conserva. El numero identifica el recibo ante el
   banco -viaja en el codigo de barras de recaudo- y dos declaraciones con el
   mismo numero serian dos recibos indistinguibles. Aqui solo se permite repetir
   (contribuyente, año, periodo); el NUMERO sigue siendo unico.

   RIESGO
   Cambia una regla sobre datos tributarios: a partir de aqui un mismo
   contribuyente puede tener dos declaraciones ORIGINALES del mismo periodo. Es
   decision del cliente, tomada en agosto y reafirmada el 2026-09-11. Reversible:
   se recrea el indice mientras no existan duplicados.
   ============================================================================ */

SET NOCOUNT ON;
GO
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

IF EXISTS (SELECT 1 FROM sys.indexes
            WHERE name = 'UQ_declaracion_contribuyente_periodo'
              AND object_id = OBJECT_ID('dbo.ind_declaraciones_ica'))
BEGIN
    DROP INDEX UQ_declaracion_contribuyente_periodo ON dbo.ind_declaraciones_ica;
    PRINT '  - UQ_declaracion_contribuyente_periodo (crear ya no bloquea por periodo)';
END
ELSE
    PRINT '  = UQ_declaracion_contribuyente_periodo no existia';
GO

/* Por si la 027 quedo a medias en alguna base: si aun existiera el nombre
   viejo, tambien se retira. Inofensivo donde ya no este. */
IF EXISTS (SELECT 1 FROM sys.indexes
            WHERE name = 'UQ_declaracion_periodo_nuevas'
              AND object_id = OBJECT_ID('dbo.ind_declaraciones_ica'))
    DROP INDEX UQ_declaracion_periodo_nuevas ON dbo.ind_declaraciones_ica;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.conf_migraciones WHERE mig_Nombre = '032_crear_ica_permite_varias_por_periodo')
    INSERT INTO dbo.conf_migraciones (mig_Nombre, mig_Nota)
    VALUES ('032_crear_ica_permite_varias_por_periodo',
            'Quita UQ_declaracion_contribuyente_periodo, el indice que la 027 no alcanzo por nombre. Crear ICA permite varias originales por periodo, por decision del cliente reafirmada el 2026-09-11. UQ_declaracion_numero se conserva.');
GO
