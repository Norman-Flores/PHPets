<?php
session_start();
session_unset();
session_destroy();

// Clear remember me cookie
setcookie("remember_token", "", time() - 3600, "/");

header("Location: index.php");
exit();