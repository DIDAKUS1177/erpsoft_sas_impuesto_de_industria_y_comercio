<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'erpsoft_sas_impuesto_de_industria_y_comercio');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$result = $conn->query("SELECT usu_Id, usu_Nombre, usu_Usuario FROM conf_usuario");
while($row = $result->fetch_assoc()) {
    echo $row['usu_Id'] . " | " . $row['usu_Nombre'] . " | " . $row['usu_Usuario'] . "\n";
}
$conn->close();
