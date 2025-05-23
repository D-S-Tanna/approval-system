<?php
require_once __DIR__ . '/config/database.php';

if (function_exists('testDbConnection')) {
    if (testDbConnection()) {
        echo "Database connection successful!";
    } else {
        echo "Database connection failed. Check your credentials and server.";
    }
} else {
    echo "testDbConnection() function not found.";
}
?>