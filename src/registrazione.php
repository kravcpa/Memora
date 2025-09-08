<?php
require_once "../Config/config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($username) || empty($email) || empty($password)) {
        header("Location: error.php?msg=" . urlencode("Tutti i campi sono obbligatori."));
        exit();
    }

    // Controlla se esiste già utente con stessa email o username
    $stmt = $conn->prepare("SELECT Email FROM user WHERE Email = ? OR Username = ?");
    $stmt->bind_param("ss", $email, $username);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        header("Location: error.php?msg=" . urlencode("Email o Username già in uso."));
        exit();
    }

    // Crea hash della password
    $hashedPwd = password_hash($password, PASSWORD_DEFAULT);

    // Inserisce l'utente (immagine di default = 1)
    $stmt = $conn->prepare("INSERT INTO user (Email, Username, Password, ImageId) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("sss", $email, $username, $hashedPwd);
    if ($stmt->execute()) {
        header("Location: index.php?msg=" . urlencode("Registrazione completata, ora puoi accedere."));
        exit();
    } else {
        header("Location: error.php?msg=" . urlencode("Errore durante la registrazione."));
        exit();
    }
} else {
    header("Location: error.php?msg=" . urlencode("Accesso non valido."));
    exit();
}
