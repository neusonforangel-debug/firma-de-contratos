<?php
require_once __DIR__ . '/../app/helpers.php';

$to = "contratos@redwm.org"; // pon un correo tuyo
$ok = send_mail($to, "Prueba mail() REDWM", "<b>Hola</b> prueba de mail()");
var_dump($ok);
echo "<br>Último error: ";
var_dump(error_get_last());