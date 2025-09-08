<?php
session_start();
require_once "../Config/config.php";
include "navbar.php";

/* --- Cancellazione account: posizionare subito dopo session_start() e require config --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    // proteggi: conferma via JS client, qui procediamo a cancellare dati collegati e l'utente
    $email = $_SESSION['email'];

    // eliminare bookmark, follow, activity prima di eliminare user per evitare FK errors
    $stmt = $conn->prepare("DELETE FROM bookmark WHERE UserEmail = ?");
    $stmt->bind_param("s", $email); $stmt->execute(); $stmt->close();

    $stmt = $conn->prepare("DELETE FROM follow WHERE FollowerEmail = ? OR FollowedEmail = ?");
    $stmt->bind_param("ss", $email, $email); $stmt->execute(); $stmt->close();

    $stmt = $conn->prepare("DELETE FROM activity WHERE UserEmail = ?");
    $stmt->bind_param("s", $email); $stmt->execute(); $stmt->close();

    // se vuoi rimuovere relazioni media->user ecc, aggiungile qui (io non tocco image table)
    // infine cancelliamo l'utente
    $stmt = $conn->prepare("DELETE FROM user WHERE Email = ?");
    $stmt->bind_param("s", $email); $stmt->execute(); $stmt->close();

    // logout e redirect alla index
    session_unset();
    session_destroy();
    header("Location: index.php?msg=" . urlencode("Account cancellato."));
    exit();
}

if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}
$email = $_SESSION['email'];

// Cambia Username
if (isset($_POST['new_username'])) {
    $newUser = trim($_POST['new_username']);
    if ($newUser !== "") {
        $stmt = $conn->prepare("UPDATE user SET Username = ? WHERE Email = ?");
        $stmt->bind_param("ss", $newUser, $email);
        $stmt->execute();

        // Inseriamo attività Changed Username
        $ins = $conn->prepare("INSERT INTO activity (UserEmail, MediaId, Type) VALUES (?, NULL, ?)");
        $type = 'Changed Username';
        $ins->bind_param("ss", $email, $type);
        $ins->execute();
    }
}

// Cambia Password
if (isset($_POST['current_password'], $_POST['new_password'])) {
    $curr = $_POST['current_password'];
    $new  = $_POST['new_password'];

    $stmt = $conn->prepare("SELECT Password FROM user WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $hash = $stmt->get_result()->fetch_assoc()['Password'];

    if (password_verify($curr, $hash)) {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE user SET Password = ? WHERE Email = ?");
        $stmt->bind_param("ss", $newHash, $email);
        $stmt->execute();
    } else {
        $error = "Password attuale non corretta";
    }
}

// Cambia Avatar
if (isset($_POST['avatar_id'])) {
    $avatarId = intval($_POST['avatar_id']);
    $stmt = $conn->prepare("UPDATE user SET ImageId = ? WHERE Email = ?");
    $stmt->bind_param("is", $avatarId, $email);
    $stmt->execute();
}

// Recupera info utente
$stmt = $conn->prepare("SELECT Username, Email, ImageId FROM user WHERE Email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Tutti gli avatar disponibili
$avatars = $conn->query("SELECT Id, URL, Name FROM image ORDER BY Name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Settings - Memora</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo CSS_PATH; ?>">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2>Impostazioni Profilo</h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card p-4 mb-3">
        <h4>Cambia Username</h4>
        <form method="post">
            <input type="text" name="new_username" class="form-control mb-2" placeholder="Nuovo username">
            <button class="btn btn-primary">Aggiorna</button>
        </form>
    </div>

    <div class="card p-4 mb-3">
        <h4>Cambia Password</h4>
        <form method="post">
            <input type="password" name="current_password" class="form-control mb-2" placeholder="Password attuale">
            <input type="password" name="new_password" class="form-control mb-2" placeholder="Nuova password">
            <button class="btn btn-warning">Cambia Password</button>
        </form>
    </div>

    <div class="card p-4 mb-3">
        <h4>Cambia Avatar</h4>
        <form method="post">
            <select name="avatar_id" class="form-select mb-2">
                <?php foreach ($avatars as $a): ?>
                    <option value="<?php echo $a['Id']; ?>" <?php if ($a['Id'] == $user['ImageId']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($a['Name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-info">Aggiorna Avatar</button>
        </form>
    </div>

    <hr>
    <div class="card mt-4">
        <div class="card-body">
            <h5>Elimina account</h5>
            <p class="text-muted">Questa azione rimuoverà il tuo account e i tuoi dati (bookmark, follow, activity). Non cancelliamo le immagini automaticamente.</p>
            <form method="post" onsubmit="return confirm('Sei sicuro di voler cancellare definitivamente il tuo account? Questa operazione è irreversibile.');">
                <button type="submit" name="delete_account" class="btn btn-danger">Elimina account</button>
            </form>
        </div>
    </div>

</div>
</body>
</html>
