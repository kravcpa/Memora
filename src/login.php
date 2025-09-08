<?php
session_start();
require_once "../Config/config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_input = trim($_POST['user_input']);
    $password = $_POST['password'];

    // Controllo campi vuoti
    if (empty($user_input) || empty($password)) {
        header("Location: error.php?msg=" . urlencode("Devi compilare tutti i campi."));
        exit();
    }

    // Query: login sia per email che per username
    $stmt = $conn->prepare("
        SELECT Email, Username, Password, ImageId
        FROM user
        WHERE Email = ? OR Username = ?
    ");
    $stmt->bind_param("ss", $user_input, $user_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // Verifica password
        if (password_verify($password, $row['Password'])) {
            $_SESSION['email'] = $row['Email'];
            header("Location: home.php");
            exit();
        } else {
            header("Location: error.php?msg=" . urlencode("Password errata."));
            exit();
        }
    } else {
        header("Location: error.php?msg=" . urlencode("Utente non trovato."));
        exit();
    }
} else {
    header("Location: error.php?msg=" . urlencode("Accesso non valido."));
    exit();
}
