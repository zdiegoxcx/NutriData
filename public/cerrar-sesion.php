<?php
session_start();
session_destroy();

header('Location: login.html');
exit;
// No debe haber nada más en este archivo.
?>