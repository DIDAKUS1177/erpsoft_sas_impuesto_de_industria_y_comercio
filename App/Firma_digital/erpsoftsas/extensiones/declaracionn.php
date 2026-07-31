<!DOCTYPE html>
<html>
<head>

<title>Declaración ICA</title>

<style>

body{
font-family: Arial;
font-size:12px;
}

table{
border-collapse:collapse;
width:100%;
}

td{
border:1px solid #2f7d32;
padding:4px;
}

.section{
background:#9ec3a7;
font-weight:bold;
}

.input{
width:100%;
border:none;
text-align:right;
}

.label{
background:#e9e4c9;
}

</style>

</head>

<body>

<h2>FORMULARIO ICA</h2>

<form method="post" action="generar_pdf.php">

<table>

<tr class="section">
<td colspan="3">A. INFORMACIÓN DEL CONTRIBUYENTE</td>
</tr>

<tr>
<td>1</td>
<td>RAZÓN SOCIAL</td>
<td>
<input name="razon_social">
</td>
</tr>

<tr>
<td>2</td>
<td>NIT</td>
<td>
<input name="nit">
</td>
</tr>

<tr>
<td>3</td>
<td>DIRECCIÓN</td>
<td>
<input name="direccion">
</td>
</tr>

<tr>
<td>4</td>
<td>TELÉFONO</td>
<td>
<input name="telefono">
</td>
</tr>

<tr class="section">
<td colspan="3">B. BASE GRAVABLE</td>
</tr>

<tr>
<td>8</td>
<td>TOTAL INGRESOS</td>
<td>
<input id="r8" class="input" name="r8">
</td>
</tr>

<tr>
<td>9</td>
<td>INGRESOS FUERA DEL MUNICIPIO</td>
<td>
<input id="r9" class="input" name="r9">
</td>
</tr>

<tr>
<td>10</td>
<td>TOTAL INGRESOS MUNICIPIO</td>
<td>
<input id="r10" class="input" name="r10" readonly>
</td>
</tr>

<tr>
<td>11</td>
<td>DEVOLUCIONES</td>
<td>
<input id="r11" class="input" name="r11">
</td>
</tr>

<tr>
<td>16</td>
<td>TOTAL INGRESOS GRAVABLES</td>
<td>
<input id="r16" class="input" name="r16" readonly>
</td>
</tr>

<tr class="section">
<td colspan="3">C. ACTIVIDADES GRAVADAS</td>
</tr>

<tr>
<td>CODIGO</td>
<td>INGRESOS</td>
<td>TARIFA</td>
</tr>

<tr>
<td><input name="codigo1"></td>
<td><input id="ingreso1" name="ingreso1"></td>
<td><input id="tarifa1" name="tarifa1"></td>
</tr>

<tr>
<td colspan="2">IMPUESTO</td>
<td>
<input id="impuesto1" name="impuesto1" readonly>
</td>
</tr>

<tr class="section">
<td colspan="3">D. LIQUIDACIÓN</td>
</tr>

<tr>
<td>20</td>
<td>ICA</td>
<td>
<input id="r20" name="r20" readonly>
</td>
</tr>

<tr>
<td>21</td>
<td>AVISOS 15%</td>
<td>
<input id="r21" name="r21" readonly>
</td>
</tr>

<tr>
<td>23</td>
<td>SOBRETASA BOMBERIL</td>
<td>
<input id="r23" name="r23">
</td>
</tr>

<tr>
<td>25</td>
<td>TOTAL IMPUESTO</td>
<td>
<input id="r25" name="r25" readonly>
</td>
</tr>

</table>

<br>

<button type="submit">Generar PDF</button>

</form>

<script>

function num(v){
return parseFloat(v)||0;
}

function calcular(){

let r8=num(document.getElementById("r8").value);
let r9=num(document.getElementById("r9").value);

let r10=r8-r9;
document.getElementById("r10").value=r10;

let r11=num(document.getElementById("r11").value);

let r16=r10-r11;
document.getElementById("r16").value=r16;

let ingreso=num(document.getElementById("ingreso1").value);
let tarifa=num(document.getElementById("tarifa1").value);

let impuesto=(ingreso*tarifa)/1000;

document.getElementById("impuesto1").value=impuesto;

document.getElementById("r20").value=impuesto;

let avisos=impuesto*0.15;

document.getElementById("r21").value=avisos;

let bomberil=num(document.getElementById("r23").value);

document.getElementById("r25").value=impuesto+avisos+bomberil;

}

document.querySelectorAll("input").forEach(function(el){
el.addEventListener("keyup",calcular);
});

</script>

</body>
</html>