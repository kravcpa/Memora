<?php
// File: Config/config.php
// Impostazioni database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'memora');

// Percorso al foglio di stile
define('CSS_PATH', '../Config/style.css');

// Connessione al database MySQLi
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}
?>
