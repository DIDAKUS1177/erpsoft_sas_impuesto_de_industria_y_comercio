<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionUsuarios.php';
require_once('./tcpdf/tcpdf.php');

class LiquidacionICAComercioPdf extends TCPDF {
    public function Header() {}
    public function Footer() {}
}

/* ============================================================
   VARIABLES – ENCABEZADO / GENERALES
   ============================================================ */
$municipio          = "PAIPA";
$departamento       = "BOYACÁ";
$nit_municipio      = "891801240";
$direccion_mpio     = "Carrera 22 No 25-14";
$ciudad_mpio        = "PAIPA - BOYACÁ";

$fecha_max_presentacion = " "; // si quieres, compleméntalo
$anio_gravable      = "2023";
$solo_bogota        = "SI"; // solo para texto, no checkbox real

$periodo_texto      = "SAMANENTE PARA BOGOTA, marque el bimestre o periodo anual";

// checkboxes meses/bimestres (true/false)
$chk_ene_feb  = false;
$chk_mar_abr  = false;
$chk_may_jun  = false;
$chk_jul_ago  = false;
$chk_sep_oct  = false;
$chk_nov_dic  = false;
$chk_anual    = true;

// Opción de uso
$chk_declaracion_inicial = true;
$chk_solo_pago           = false;
$chk_correccion          = false;
$no_declaracion_corrige  = "";  // número
$fecha_declaracion       = "19/03/2024";

/* ============================================================
   VARIABLES – DATOS CONTRIBUYENTE (BLOQUE 1–6)
   ============================================================ */
$nombre_razon_social = "SISTEMA ERPSOFT SOCIEDAD POR ACCIONES SIMPLIFICADA";

$tipo_doc_CC = false;
$tipo_doc_NIT = true;
$tipo_doc_XT = false;
$tipo_doc_TI = false;
$tipo_doc_CE = false;
$tipo_doc_No = false;

$numero_documento = "901632322";
$digito_verif     = "2";

$es_consorcio_un_tv  = false;
$realiza_act_traves_patrimonio = false;

$direccion_notificacion = "CALLE 6A NO 8-23";
$municipio_contrib      = "DUITAMA";
$departamento_contrib   = "BOYACÁ";

$telefono_contrib   = "3103063035";
$correo_contrib     = "erpsoftsas@gmail.com";
$no_establecimientos = "1";
$clasificacion       = ""; // puedes parametrizar

/* ============================================================
   VARIABLES – BASE GRAVABLE / LIQ. IMPUESTO
   ============================================================ */
// Base gravable – ingresos (renglones 8–16)
$vlr_8_total_ingresos_pais         = 65279327.00;
$vlr_9_menos_fuera_municipio       = 193439497.00;
$vlr_10_total_ingresos_municipio   = -128160170.00;
$vlr_11_menos_devoluciones         = 0;
$vlr_12_menos_exportaciones        = 0;
$vlr_13_menos_venta_activos        = 0;
$vlr_14_menos_excluidos_no_grav    = 0;
$vlr_15_menos_otras_actividades    = 0;
$vlr_16_total_ingresos_gravables   = -128160170.00;

// Actividad gravada
$actividad_codigo       = "303";
$actividad_descripcion  = "ACTIVIDADES GRAVADAS";
$actividad_ingresos     = 65279327.00;
$actividad_tarifa_mil   = 7.0;     // por mil
$actividad_impuesto     = 456955.29;
$total_impuesto_renglon = 456955.29;

// Otros campos liquidación (renglon 20 en adelante)
$vlr_20_total_impto_ic       = 457000.00;
$vlr_21_impto_avisos_tableros= 0;
$vlr_22_pago_unidades_adic   = 0;
$vlr_23_sobretasa_bomberos   = 23000.00;
$vlr_24_sobretasa_seguridad  = 0;
$vlr_25_total_impto_cargo    = 480000.00;
$vlr_26_menos_valores_exencion = 0;
$vlr_27_menos_retenciones     = 1000.00;
$vlr_28_menos_anticipo_anterior= 0;
$vlr_29_anticipo_anio_sgte    = 0;
$vlr_31_sanciones             = 0;
$vlr_32_menos_saldo_favor_ant = 0;
$vlr_33_total_saldo_cargo     = 1000.00;
$vlr_35_valor_a_pagar         = 1000.00;
$vlr_37_intereses_mora        = 0;
$vlr_38_total_a_pagar         = 1000.00;

// Pago voluntario
$vlr_39_aporte_voluntario = 0;
$vlr_40_total_con_aporte  = 1000.00;

/* ============================================================
   VARIABLES – FIRMAS
   ============================================================ */
$firmante_nombre     = "SISTEMA ERPSOFT SOCIEDAD POR ACCIONES SIMPLIFICADA";
$firmante_tipo_doc_CC= false;
$firmante_tipo_doc_CE= false;
$firmante_tipo_doc_TI= false;
$firmante_tipo_doc_NIT= true;
$firmante_num_doc    = "901632322";

$contador_nombre      = "";
$contador_tipo_doc_CC = false;
$contador_tipo_doc_CE = false;
$contador_tipo_doc_TI = false;
$contador_num_doc     = "";
$contador_tp          = "";

$revisor_nombre      = "";
$revisor_tipo_doc_CC = false;
$revisor_tipo_doc_CE = false;
$revisor_tipo_doc_TI = false;
$revisor_num_doc     = "";
$revisor_tp          = "";

$codigo_barras       = "";
$referencia_recaudo  = "2024000797";

/* ============================================================
   FUNCIONES AUXILIARES
   ============================================================ */
function moneyCol($v) {
    return '$' . number_format((float)$v, 2, ',', '.');
}

function drawCheckBoxX($pdf, $x, $y, $size = 4, $checked = false) {
    $pdf->Rect($x, $y, $size, $size);
    if ($checked) {
        $pdf->SetFont('helvetica','B', $size-1);
        $pdf->SetXY($x, $y-0.5);
        $pdf->Cell($size, $size+1, 'X', 0, 0, 'C');
    }
}

/* ============================================================
   CREACIÓN PDF
   ============================================================ */
$pdf = new LiquidacionICAComercioPdf('P', 'mm', array(215.9, 330.2), true, 'UTF-8', false);
$pdf->SetMargins(7,8,7);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage();
$pdf->SetFont('helvetica','',7);

// Marco general
$pdf->Rect(7,8,202,341); // ajusta altura si lo necesitas

/* ------------------------------------------------------------
   ENCABEZADO
   ------------------------------------------------------------ */
$y = 9;

// logos izquierda (ajusta rutas)
$pdf->Rect(8,$y,24,22);
$pdf->Image('tcpdf/pdf/img/escudo_izq.png', 8, $y, 24, 22, '', '', '', false, 300);

// texto cabecera
$pdf->SetFont('helvetica','B',9);
$pdf->SetXY(40,$y);
$pdf->Cell(120,5,"MUNICIPIO DE $municipio",0,2,'L');
$pdf->SetFont('helvetica','',7);
$pdf->SetX(40);
$pdf->Cell(120,4,$nit_municipio,0,2,'L');
$pdf->SetX(40);
$pdf->Cell(120,4,$direccion_mpio,0,2,'L');
$pdf->SetX(40);
$pdf->Cell(120,4,$ciudad_mpio,0,2,'L');

// título principal
$pdf->SetFont('helvetica','B',10);
$pdf->SetXY(7, $y+20);
$pdf->Cell(202,5,'FORMULARIO ÚNICO NACIONAL DE DECLARACIÓN Y PAGO DEL',0,2,'C');
$pdf->Cell(202,5,'IMPUESTO DE INDUSTRIA Y COMERCIO',0,2,'C');

// fecha máxima presentación (cuadro superior derecho)
$pdf->SetFont('helvetica','',7);
$pdf->SetXY(150,9);
$pdf->MultiCell(58,8,"Fecha máxima de presentación\n$fecha_max_presentacion",1,'C');

/* ------------------------------------------------------------
   BLOQUE: MUNICIPIO / DEPTO / AÑO / PERIODO
   ------------------------------------------------------------ */
$y = 32;

$pdf->SetFont('helvetica','',7);

// fila municipio / depto
$pdf->SetXY(7,$y);
$pdf->Cell(100,6,"MUNICIPIO O DISTRITO:  $municipio_contrib",1,0,'L');
$pdf->Cell(102,6,"DEPARTAMENTO:  $departamento_contrib",1,1,'L');
$y += 6;

// fila año gravable + bimestres
$pdf->SetXY(7,$y);
$pdf->Cell(50,6,"AÑO GRAVABLE:",1,0,'L');
$pdf->SetFont('helvetica','B',8);
$pdf->Cell(15,6,$anio_gravable,1,0,'C');
$pdf->SetFont('helvetica','',6);
$pdf->Cell(137,6,"SOLAMENTE PARA BOGOTÁ, marque el bimestre o periodo anual",1,1,'L');
$y += 6;

// fila meses / anual
$pdf->SetXY(7,$y);
$w_mes = 22;
$pdf->Cell($w_mes,8,"ene-feb",1,0,'C');
$pdf->Cell($w_mes,8,"mar-abr",1,0,'C');
$pdf->Cell($w_mes,8,"may-jun",1,0,'C');
$pdf->Cell($w_mes,8,"jul-ago",1,0,'C');
$pdf->Cell($w_mes,8,"sep-oct",1,0,'C');
$pdf->Cell($w_mes,8,"nov-dic",1,0,'C');
$pdf->Cell(202-6*$w_mes,8,"anual",1,1,'C');

// dibujar checks
$chkY = $y+2;
drawCheckBoxX($pdf, 7 + $w_mes/2 -2,   $chkY, 4, $chk_ene_feb);
drawCheckBoxX($pdf, 7 + $w_mes + $w_mes/2 -2, $chkY, 4, $chk_mar_abr);
drawCheckBoxX($pdf, 7 + 2*$w_mes + $w_mes/2 -2, $chkY, 4, $chk_may_jun);
drawCheckBoxX($pdf, 7 + 3*$w_mes + $w_mes/2 -2, $chkY, 4, $chk_jul_ago);
drawCheckBoxX($pdf, 7 + 4*$w_mes + $w_mes/2 -2, $chkY, 4, $chk_sep_oct);
drawCheckBoxX($pdf, 7 + 5*$w_mes + $w_mes/2 -2, $chkY, 4, $chk_nov_dic);
drawCheckBoxX($pdf, 7 + 6*$w_mes + (202-6*$w_mes)/2 -2, $chkY, 4, $chk_anual);

$y += 8;

/* ------------------------------------------------------------
   BLOQUE: OPCIÓN DE USO
   ------------------------------------------------------------ */
$pdf->SetXY(7,$y);
$pdf->Cell(202,6,"OPCIÓN DE USO:",1,1,'L');
$y += 6;

$pdf->SetXY(7,$y);
$pdf->Cell(60,6,"DECLARACIÓN INICIAL",1,0,'L');
$pdf->Cell(40,6,"SOLO PAGO",1,0,'L');
$pdf->Cell(40,6,"CORRECCIÓN",1,0,'L');
$pdf->Cell(32,6,"Declaración que corrige No.",1,0,'L');
$pdf->Cell(30,6,"Fecha:  $fecha_declaracion",1,1,'L');

// checkboxes opción uso
$cbY = $y+1.5;
drawCheckBoxX($pdf, 7+45, $cbY, 4, $chk_declaracion_inicial);
drawCheckBoxX($pdf, 7+60+30, $cbY, 4, $chk_solo_pago);
drawCheckBoxX($pdf, 7+100+30, $cbY, 4, $chk_correccion);

$y += 6;

/* ------------------------------------------------------------
   BLOQUE: DATOS BÁSICOS DEL CONTRIBUYENTE
   ------------------------------------------------------------ */

// Fila 1 – Nombres / razón social
$pdf->SetFont('helvetica','',7);
$pdf->SetXY(7,$y);
$pdf->Cell(7,6,"1",1,0,'C');
$pdf->Cell(135,6,"NOMBRES Y APELLIDOS O RAZÓN SOCIAL",1,0,'L');
$pdf->Cell(60,6,$nombre_razon_social,1,1,'L');
$y += 6;

// fila tipo doc + número + DV + casillas consorcio/autónomo
$pdf->SetXY(7,$y);
$pdf->Cell(7,8,"2",1,0,'C');
$pdf->Cell(135,8,"CC   NIT   XT   TI   CE   No.   DV   Es consorcio o unión temporal    Realiza actividades a través de patrimonio autónomo",1,0,'L');
$pdf->Cell(60,8,"",1,1,'L');

// casillas tipo doc
$baseX = 14; $ty = $y+2;
$pdf->SetFont('helvetica','',6);
$pdf->SetXY($baseX,$y);
$pdf->Cell(6,8,"CC",0,0,'L');   drawCheckBoxX($pdf, $baseX+8, $ty, 3.5, $tipo_doc_CC);
$pdf->SetXY($baseX+16,$y);
$pdf->Cell(7,8,"NIT",0,0,'L');  drawCheckBoxX($pdf, $baseX+24, $ty, 3.5, $tipo_doc_NIT);
$pdf->SetXY($baseX+32,$y);
$pdf->Cell(7,8,"XT",0,0,'L');   drawCheckBoxX($pdf, $baseX+39, $ty, 3.5, $tipo_doc_XT);
$pdf->SetXY($baseX+46,$y);
$pdf->Cell(7,8,"TI",0,0,'L');   drawCheckBoxX($pdf, $baseX+53, $ty, 3.5, $tipo_doc_TI);
$pdf->SetXY($baseX+60,$y);
$pdf->Cell(7,8,"CE",0,0,'L');   drawCheckBoxX($pdf, $baseX+67, $ty, 3.5, $tipo_doc_CE);

// número doc + DV
$pdf->SetXY($baseX+76,$y);
$pdf->Cell(24,8,"No: $numero_documento",0,0,'L');
$pdf->SetXY($baseX+100,$y);
$pdf->Cell(10,8,"DV $digito_verif",0,0,'L');

// consorcio / patrimonio
drawCheckBoxX($pdf, $baseX+124, $ty, 3.5, $es_consorcio_un_tv);
drawCheckBoxX($pdf, $baseX+159, $ty, 3.5, $realiza_act_traves_patrimonio);

$y += 8;

// fila 3 – Dirección
$pdf->SetXY(7,$y);
$pdf->Cell(7,6,"3",1,0,'C');
$pdf->Cell(195,6,"DIRECCIÓN DE NOTIFICACIÓN  $direccion_notificacion",1,1,'L');
$y += 6;

// fila 4 – Municipio / Depto / Establecimientos / Clasificación
$pdf->SetXY(7,$y);
$pdf->Cell(7,6,"4",1,0,'C');
$pdf->Cell(70,6,"MUNICIPIO O DISTRITO DE LA DIRECCIÓN:  $municipio_contrib",1,0,'L');
$pdf->Cell(60,6,"DEPARTAMENTO:  $departamento_contrib",1,0,'L');
$pdf->Cell(35,6,"6. No.ESTABLECIMIENTOS  $no_establecimientos",1,0,'L');
$pdf->Cell(30,6,"7. CLASIFICACIÓN  $clasificacion",1,1,'L');
$y += 6;

// fila 5 – Teléfono / correo
$pdf->SetXY(7,$y);
$pdf->Cell(7,6,"5",1,0,'C');
$pdf->Cell(60,6,"TELÉFONO  $telefono_contrib",1,0,'L');
$pdf->Cell(135,6,"5. CORREO ELECTRÓNICO  $correo_contrib",1,1,'L');
$y += 6;

/* ------------------------------------------------------------
   BLOQUE: BASE GRAVABLE / INGRESOS (renglones 8–16)
   ------------------------------------------------------------ */

$pdf->SetFont('helvetica','B',7);
$pdf->SetXY(7,$y);
$pdf->Cell(7,6,"",1,0,'C');
$pdf->Cell(135,6,"BASE GRAVABLE",1,0,'L');
$pdf->Cell(60,6,"VALOR",1,1,'C');
$y += 6;
$pdf->SetFont('helvetica','',7);

function filaBG($pdf,&$y,$num,$texto,$valor){
    $pdf->SetXY(7,$y);
    $pdf->Cell(7,6,$num,1,0,'C');
    $pdf->Cell(135,6,$texto,1,0,'L');
    $pdf->Cell(60,6, moneyCol($valor),1,1,'R');
    $y += 6;
}

filaBG($pdf,$y,"8","TOTAL INGRESOS ORDINARIOS Y EXTRAORDINARIOS DEL PERÍODO EN TODO EL PAÍS",$vlr_8_total_ingresos_pais);
filaBG($pdf,$y,"9","MENOS INGRESOS FUERA DE ESTE MUNICIPIO O DISTRITO",$vlr_9_menos_fuera_municipio);
filaBG($pdf,$y,"10","TOTAL INGRESOS ORDINARIOS Y EXTRAORDINARIOS EN ESTE MUNICIPIO (renglón 8 menos 9)",$vlr_10_total_ingresos_municipio);
filaBG($pdf,$y,"11","MENOS INGRESOS POR DEVOLUCIONES, REBAJAS, DESCUENTOS",$vlr_11_menos_devoluciones);
filaBG($pdf,$y,"12","MENOS INGRESOS POR EXPORTACIONES",$vlr_12_menos_exportaciones);
filaBG($pdf,$y,"13","MENOS INGRESOS POR VENTA DE ACTIVOS FIJOS",$vlr_13_menos_venta_activos);
filaBG($pdf,$y,"14","MENOS INGRESOS POR ACTIVIDADES EXCLUIDAS O NO SUJETAS Y OTROS INGRESOS NO GRAVADOS",$vlr_14_menos_excluidos_no_grav);
filaBG($pdf,$y,"15","MENOS INGRESOS POR OTRAS ACTIVIDADES EXENTAS EN ESTE MUNICIPIO O DISTRITO (POR ACUERDO)",$vlr_15_menos_otras_actividades);
filaBG($pdf,$y,"16","TOTAL INGRESOS GRAVABLES (renglón 10 menos 11, 12, 13, 14, 15)",$vlr_16_total_ingresos_gravables);

/* ------------------------------------------------------------
   BLOQUE: ACTIVIDAD GRAVADA (renglón 17–19)
   ------------------------------------------------------------ */

$pdf->SetFont('helvetica','B',7);
$pdf->SetXY(7,$y);
$pdf->Cell(60,6,"ACTIVIDADES GRAVADAS",1,0,'C');
$pdf->Cell(25,6,"CÓDIGO",1,0,'C');
$pdf->Cell(55,6,"INGRESOS GRAVADOS",1,0,'C');
$pdf->Cell(30,6,"TARIFA (por mil)",1,0,'C');
$pdf->Cell(39,6,"IMPUESTO",1,1,'C');
$y += 6;

$pdf->SetFont('helvetica','',7);
$pdf->SetXY(7,$y);
$pdf->Cell(60,6,$actividad_descripcion,1,0,'L');
$pdf->Cell(25,6,$actividad_codigo,1,0,'C');
$pdf->Cell(55,6,moneyCol($actividad_ingresos),1,0,'R');
$pdf->Cell(30,6,number_format($actividad_tarifa_mil,3,',','.')." ‰",1,0,'C');
$pdf->Cell(39,6,moneyCol($actividad_impuesto),1,1,'R');
$y += 6;

// Total ingresos/ impuesto
$pdf->SetXY(7,$y);
$pdf->Cell(85,6,"17. TOTAL INGRESOS GRAVADOS",1,0,'L');
$pdf->Cell(55,6,moneyCol($actividad_ingresos),1,0,'R');
$pdf->Cell(30,6,"17. TOTAL IMPUESTO",1,0,'L');
$pdf->Cell(39,6,moneyCol($total_impuesto_renglon),1,1,'R');
$y += 6;

/* ------------------------------------------------------------
   BLOQUE: LIQUIDACIÓN DEL IMPUESTO (20–33)
   ------------------------------------------------------------ */

filaBG($pdf,$y,"20","TOTAL IMPUESTO DE INDUSTRIA Y COMERCIO (renglón 17 + 19)",$vlr_20_total_impto_ic);
filaBG($pdf,$y,"21","IMPUESTO DE AVISOS Y TABLEROS (15% de renglón 20)",$vlr_21_impto_avisos_tableros);
filaBG($pdf,$y,"22","PAGO POR UNIDADES COMERCIALES ADICIONALES DEL SECTOR FINANCIERO",$vlr_22_pago_unidades_adic);
filaBG($pdf,$y,"23","SOBRETASA BOMBERIL (Ley 1575 de 2012) si la hay, líquidela según el acuerdo municipal o distrital",$vlr_23_sobretasa_bomberos);
filaBG($pdf,$y,"24","SOBRETASA DE SEGURIDAD (Ley 1421 de 2010) si la hay, líquidela según el acuerdo municipal o distrital",$vlr_24_sobretasa_seguridad);
filaBG($pdf,$y,"25","TOTAL IMPUESTO A CARGO (Renglón 20+21+22+23+24)",$vlr_25_total_impto_cargo);
filaBG($pdf,$y,"26","MENOS VALOR DE EXENCIÓN O EXONERACIÓN SOBRE EL IMPUESTO Y NO SOBRE LOS INGRESOS",$vlr_26_menos_valores_exencion);
filaBG($pdf,$y,"27","MENOS RETENCIONES QUE LE PRACTICARON A FAVOR DE ESTE MUNICIPIO O DISTRITO EN ESTE PERÍODO",$vlr_27_menos_retenciones);
filaBG($pdf,$y,"28","MENOS ANTICIPO LIQUIDADO EN EL AÑO ANTERIOR",$vlr_28_menos_anticipo_anterior);
filaBG($pdf,$y,"29","ANTICIPO DEL AÑO SIGUIENTE (si existe, líqüidelo según el acuerdo municipal o distrital)",$vlr_29_anticipo_anio_sgte);

// Sanciones (30/31) – dejamos línea especial
$pdf->SetXY(7,$y);
$pdf->Cell(7,6,"31",1,0,'C');
$pdf->Cell(135,6,"SANCIONES         Extemporaneidad    Corrección    Inexactitud    Otra   Cual:",1,0,'L');
$pdf->Cell(60,6,moneyCol($vlr_31_sanciones),1,1,'R');
$y += 6;

// Menos saldo favor período anterior
filaBG($pdf,$y,"32","MENOS SALDO A FAVOR DEL PERÍODO ANTERIOR SIN SOLICITUD DE DEVOLUCIÓN O COMPENSACIÓN",$vlr_32_menos_saldo_favor_ant);
filaBG($pdf,$y,"33","TOTAL SALDO A CARGO (Renglón 25-26-27-28-29+30+31-32)",$vlr_33_total_saldo_cargo);
filaBG($pdf,$y,"35","VALOR A PAGAR",$vlr_35_valor_a_pagar);
filaBG($pdf,$y,"37","INTERESES DE MORA",$vlr_37_intereses_mora);
filaBG($pdf,$y,"38","TOTAL A PAGAR (renglón 35+36+37)",$vlr_38_total_a_pagar);

/* ------------------------------------------------------------
   BLOQUE: PAGO VOLUNTARIO
   ------------------------------------------------------------ */
$pdf->SetXY(7,$y);
$pdf->Cell(107,6,"SECCIÓN PAGO VOLUNTARIO (Solamente donde exista esta opción)",1,0,'L');
$pdf->Cell(50,6,"39 LIQUÍDE EL VALOR DE PAGO VOLUNTARIO",1,0,'L');
$pdf->Cell(45,6,moneyCol($vlr_39_aporte_voluntario),1,1,'R');
$y += 6;

$pdf->SetXY(7,$y);
$pdf->Cell(157,6,"40 TOTAL A PAGAR CON PAGO VOLUNTARIO (renglón 38+39)",1,0,'L');
$pdf->Cell(45,6,moneyCol($vlr_40_total_con_aporte),1,1,'R');
$y += 6;

/* ------------------------------------------------------------
   BLOQUE: FIRMAS
   ------------------------------------------------------------ */
$pdf->SetFont('helvetica','B',7);
$pdf->SetXY(7,$y);
$pdf->Cell(202,6,"E. FIRMAS",1,1,'L');
$y += 6;
$pdf->SetFont('helvetica','',7);

// fila firmas grande
$pdf->SetXY(7,$y);
$pdf->Cell(67,6,"FIRMA DEL DECLARANTE",1,0,'C');
$pdf->Cell(67,6,"FIRMA DEL CONTADOR",1,0,'C');
$pdf->Cell(68,6,"REVISOR FISCAL",1,1,'C');
$y += 18; // espacio para firmas manuscritas

// nombres
$pdf->SetXY(7,$y);
$pdf->Cell(67,6,"NOMBRE  $firmante_nombre",1,0,'L');
$pdf->Cell(67,6,"NOMBRE  $contador_nombre",1,0,'L');
$pdf->Cell(68,6,"NOMBRE  $revisor_nombre",1,1,'L');
$y += 6;

// documentos línea 1
$pdf->SetXY(7,$y);
$pdf->Cell(67,6,"C.C.  C.E.  T.I.  NIT",1,0,'L');
$pdf->Cell(67,6,"C.C.  C.E.  T.I.  T.P.",1,0,'L');
$pdf->Cell(68,6,"C.C.  C.E.  T.I.  T.P.",1,1,'L');
$y += 6;

// documentos línea 2 (números)
$pdf->SetXY(7,$y);
$pdf->Cell(67,6,"No.  $firmante_num_doc",1,0,'L');
$pdf->Cell(67,6,"No.  $contador_num_doc",1,0,'L');
$pdf->Cell(68,6,"No.  $revisor_num_doc",1,1,'L');
$y += 6;

// código barras / referencia recaudo
$pdf->SetXY(7,$y);
$pdf->Cell(101,6,"CÓDIGO DE BARRAS",1,0,'L');
$pdf->Cell(101,6,"REFERENCIA DE RECAUDO FORMULARIO No.  $referencia_recaudo",1,1,'L');
$y += 6;

/* ============================================================
   SALIDA PDF
   ============================================================ */
$pdf->Output('Liquidacion_ICA_Comercio.pdf','I');
