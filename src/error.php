<?php
require_once "../Config/config.php";
$msg = isset($_GET['msg']) ? $_GET['msg'] : "Errore sconosciuto.";
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Errore - Memora</title>
    <link rel="stylesheet" href="<?php echo CSS_PATH; ?>">
</head>
<body>
    <div class="error-box">
        <h2>Si è verificato un errore</h2>
        <p><?php echo htmlspecialchars($msg); ?></p>
        <form action="index.php">
            <button type="submit">Torna alla Home</button>
        </form>
    </div>
</body>
</html>
