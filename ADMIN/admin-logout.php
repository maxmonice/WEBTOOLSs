<?php
session_start();
session_destroy();
header("Location: http://localhost/LUKES/WEBTOOLS/index.php");
exit();
?>