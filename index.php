<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once "Shoxruz.php";
    echo "OK";
} catch (Throwable $e) {
    echo "ERROR: ";
    echo $e->getMessage();
}
