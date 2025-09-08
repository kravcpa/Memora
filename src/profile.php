<?php
// SRC/profile.php
session_start();
require_once __DIR__ . "/../Config/config.php";
if (!isset($_SESSION['email'])) { header("Location: index.php"); exit(); }
include "navbar.php";

$loggedEmail = $_SESSION['email'];
$viewEmail = isset($_GET['u']) ? $_GET['u'] : $loggedEmail;

/* Recupera dati utente (anche IsAdmin/IsBlocked) */
$stmt = $conn->prepare("SELECT Email, Username, ImageId, IsAdmin, IsBlocked FROM user WHERE Email = ?");
$stmt->bind_param("s", $viewEmail);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
if (!$userData) { header("Location: error.php?msg=" . urlencode("Utente non trovato.")); exit(); }

$avatar = $conn->query("SELECT URL FROM image WHERE Id = " . intval($userData['ImageId']))->fetch_assoc()['URL'] ?? '';
$username = $userData['Username'];
$viewIsAdmin = intval($userData['IsAdmin']) === 1;
$viewIsBlocked = intval($userData['IsBlocked']) === 1;

$isOwn = ($viewEmail === $loggedEmail);

/* Recupera se l'utente loggante è admin (per mostrare pulsante blocco) */
$me = $conn->prepare("SELECT IsAdmin FROM user WHERE Email = ?");
$me->bind_param("s", $loggedEmail);
$me->execute();
$meRow = $me->get_result()->fetch_assoc();
$meIsAdmin = intval($meRow['IsAdmin']) === 1;

/* Gestione follow/unfollow e block/unblock */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['follow'])) {
        $ins = $conn->prepare("INSERT IGNORE INTO follow (FollowerEmail, FollowedEmail) VALUES (?, ?)");
        $ins->bind_param("ss", $loggedEmail, $viewEmail); $ins->execute();
    } elseif (isset($_POST['unfollow'])) {
        $del = $conn->prepare("DELETE FROM follow WHERE FollowerEmail = ? AND FollowedEmail = ?");
        $del->bind_param("ss", $loggedEmail, $viewEmail); $del->execute();
    } elseif (isset($_POST['toggle_block']) && $meIsAdmin && !$viewIsAdmin) {
        // toggle IsBlocked
        $newBlock = $viewIsBlocked ? 0 : 1;
        $upd = $conn->prepare("UPDATE user SET IsBlocked = ? WHERE Email = ?");
        $upd->bind_param("is", $newBlock, $viewEmail); $upd->execute();
        // Aggiorna variabile per riflettere cambiamento nella UI
        $viewIsBlocked = (bool)$newBlock;
    }
    header("Location: profile.php?u=" . urlencode($viewEmail));
    exit();
}

/* Verifica se già segui (per mostrare il bottone corretto) */
$isFollowing = false;
if (!$isOwn) {
    $chk = $conn->prepare("SELECT 1 FROM follow WHERE FollowerEmail=? AND FollowedEmail=?");
    $chk->bind_param("ss", $loggedEmail, $viewEmail);
    $chk->execute();
    $isFollowing = $chk->get_result()->num_rows > 0;
}

/* STATISTICHE e bookmarks (mantengo lo schema che hai già) */
function countQuery($conn, $sql, $param) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $param);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['cnt'];
}
$followers = countQuery($conn, "SELECT COUNT(*) AS cnt FROM follow WHERE FollowedEmail = ?", $viewEmail);
$following = countQuery($conn, "SELECT COUNT(*) AS cnt FROM follow WHERE FollowerEmail = ?", $viewEmail);
$bookmarks = countQuery($conn, "SELECT COUNT(*) AS cnt FROM bookmark WHERE UserEmail = ?", $viewEmail);

/* Episodi e capitoli */
$q = $conn->prepare("
    SELECT COALESCE(SUM(b.Progression),0) AS cnt
    FROM bookmark b
    JOIN media m ON b.MediaId = m.Id
    JOIN mediatype mt ON m.MediaTypeId = mt.Id
    WHERE b.UserEmail = ? AND mt.Type = 'Anime'
");
$q->bind_param("s", $viewEmail); $q->execute();
$episodes = $q->get_result()->fetch_assoc()['cnt'];

$q = $conn->prepare("
    SELECT COALESCE(SUM(b.Progression),0) AS cnt
    FROM bookmark b
    JOIN media m ON b.MediaId = m.Id
    JOIN mediatype mt ON m.MediaTypeId = mt.Id
    WHERE b.UserEmail = ? AND mt.Type = 'Manga'
");
$q->bind_param("s", $viewEmail); $q->execute();
$chapters = $q->get_result()->fetch_assoc()['cnt'];

/* Bookmarks per status (con voto/progress/rewatch) */
$statusRes = $conn->query("SELECT Id, Type FROM status ORDER BY Id ASC");
$statuses = $statusRes->fetch_all(MYSQLI_ASSOC);
$bookmarksByStatus = [];
foreach ($statuses as $s) {
    $stmt = $conn->prepare("
        SELECT m.Id, m.Title, mt.Type AS MediaType,
               b.Progression AS Progression, b.Rating AS Rating, b.Rewatch AS Rewatch
        FROM bookmark b
        JOIN media m ON b.MediaId = m.Id
        JOIN mediatype mt ON m.MediaTypeId = mt.Id
        WHERE b.UserEmail = ? AND b.StatusId = ?
        ORDER BY mt.Type, m.Title
    ");
    $stmt->bind_param("si", $viewEmail, $s['Id']);
    $stmt->execute();
    $bookmarksByStatus[$s['Type']] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Profilo - <?php echo htmlspecialchars($username); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo CSS_PATH; ?>">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="card mb-4 text-center">
        <div class="card-body">
            <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle mb-3" width="120" height="120">
            <h2><?php echo htmlspecialchars($username); ?></h2>
            <p class="text-muted"><?php echo htmlspecialchars($viewEmail); ?></p>

            <?php if ($isOwn): ?>
                <a href="settings.php" class="btn btn-outline-primary">Settings</a>
            <?php else: ?>
                <form method="post" class="d-inline">
                    <?php if ($isFollowing): ?>
                        <button name="unfollow" class="btn btn-danger">Unfollow</button>
                    <?php else: ?>
                        <button name="follow" class="btn btn-primary">Follow</button>
                    <?php endif; ?>
                </form>

                <!-- Se io sono admin e il profilo visto non è admin mostro block/unblock -->
                <?php if ($meIsAdmin && !$viewIsAdmin): ?>
                    <form method="post" class="d-inline ms-2">
                        <button name="toggle_block" class="btn <?php echo $viewIsBlocked ? 'btn-success' : 'btn-outline-danger'; ?>">
                            <?php echo $viewIsBlocked ? 'Sblocca' : 'Blocca'; ?>
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats -->
    <div class="row text-center mb-4">
        <div class="col-md-2"><div class="card shadow-sm"><div class="card-body"><h5><?php echo $followers; ?></h5><p>Follower</p></div></div></div>
        <div class="col-md-2"><div class="card shadow-sm"><div class="card-body"><h5><?php echo $following; ?></h5><p>Seguiti</p></div></div></div>
        <div class="col-md-2"><div class="card shadow-sm"><div class="card-body"><h5><?php echo $bookmarks; ?></h5><p>Bookmark</p></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><h5><?php echo $episodes; ?></h5><p>Episodi visti</p></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><h5><?php echo $chapters; ?></h5><p>Capitoli letti</p></div></div></div>
    </div>

    <!-- Bookmarks per status con colonne -->
    <?php foreach ($statuses as $s): ?>
        <div class="mb-4">
            <h4><?php echo htmlspecialchars($s['Type']); ?></h4>
            <?php $list = $bookmarksByStatus[$s['Type']] ?? []; ?>
            <?php if (count($list) > 0): ?>
                <div class="list-group">
                    <div class="list-group-item d-none d-md-flex fw-bold">
                        <div class="col-md-6">Titolo</div>
                        <div class="col-md-2">Voto</div>
                        <div class="col-md-2">Episodio/Capitolo</div>
                        <div class="col-md-2">Rewatches</div>
                    </div>
                    <?php foreach ($list as $bk): ?>
                        <div class="list-group-item">
                            <div class="row align-items-center">
                                <div class="col-12 col-md-6">
                                    <a href="media.php?id=<?php echo intval($bk['Id']); ?>"><?php echo htmlspecialchars($bk['Title']); ?></a>
                                </div>
                                <div class="col-4 col-md-2"><?php echo ($bk['Rating'] !== null) ? htmlspecialchars($bk['Rating']) : 'N/A'; ?></div>
                                <div class="col-4 col-md-2"><?php echo ($bk['Progression'] !== null) ? htmlspecialchars($bk['Progression']) : 'N/A'; ?></div>
                                <div class="col-4 col-md-2"><?php echo ($bk['Rewatch'] !== null) ? htmlspecialchars($bk['Rewatch']) : 'N/A'; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">Nessun media in questa categoria</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
