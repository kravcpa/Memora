<?php
// SRC/blocked.php
session_start();
require_once __DIR__ . "/../Config/config.php";

// Mostriamo la pagina anche se session scaduta — ma richiediamo logout per sicurezza.
$email = $_SESSION['email'] ?? null;
$username = '';
if ($email) {
    $r = $conn->prepare("SELECT Username FROM user WHERE Email = ?");
    $r->bind_param("s", $email);
    $r->execute();
    $ud = $r->get_result()->fetch_assoc();
    if ($ud) $username = $ud['Username'];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Account bloccato</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm mx-auto" style="max-width:600px">
        <div class="card-body text-center">
            <h3>Account bloccato</h3>
            <?php if ($username): ?>
                <p class="text-muted">Ciao <strong><?php echo htmlspecialchars($username); ?></strong>, il tuo account è stato bloccato.</p>
            <?php else: ?>
                <p class="text-muted">Il tuo account è stato bloccato.</p>
            <?php endif; ?>

            <p>Se pensi ci sia un errore contatta l'amministrazione.</p>

            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
    </div>
</div>
</body>
</html>
