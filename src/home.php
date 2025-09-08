<?php
session_start();
require_once "../Config/config.php";

$email = $_SESSION['email'] ?? null;
if ($email) {
    $chk = $conn->prepare("SELECT IsBlocked FROM user WHERE Email = ?");
    $chk->bind_param("s", $email);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    if ($row && intval($row['IsBlocked']) === 1) {
        header("Location: blocked.php");
        exit();
    }
}

include "navbar.php";

if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

$email = $_SESSION['email'];

$stmt = $conn->prepare("
    SELECT u.Username, i.URL AS Avatar
    FROM user u
    JOIN image i ON u.ImageId = i.Id
    WHERE u.Email = ?
");
$stmt->bind_param("s", $email);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$username = $userData['Username'];
$avatar   = $userData['Avatar'];

$sql = "
    SELECT a.ActivityId, u.Username, i.URL AS Avatar, a.Type, 
           m.Title AS MediaTitle, mt.Type AS MediaType
    FROM activity a
    JOIN user u ON a.UserEmail = u.Email
    JOIN image i ON u.ImageId = i.Id
    LEFT JOIN media m ON a.MediaId = m.Id
    LEFT JOIN mediatype mt ON m.MediaTypeId = mt.Id
    WHERE a.UserEmail = ?
       OR a.UserEmail IN (SELECT FollowedEmail FROM follow WHERE FollowerEmail = ?)
    ORDER BY a.ActivityId DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $email, $email);
$stmt->execute();
$activities = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Home - Memora</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo CSS_PATH; ?>">
</head>
<body class="bg-light">

<!-- Feed attività -->
<div class="container my-4">
    <h3>Attività recenti</h3>
    <?php while ($row = $activities->fetch_assoc()): ?>
        <div class="card mb-2 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <img src="<?php echo htmlspecialchars($row['Avatar']); ?>" alt="Avatar" class="rounded-circle me-2" width="40" height="40">
                <strong class="me-2"><?php echo htmlspecialchars($row['Username']); ?></strong>
                <span>
                    <?php
                        if ($row['MediaTitle']) {
                            if ($row['Type'] === "Progressed a Media") {
                                echo ($row['MediaType'] === "Anime") ? "ha visto un episodio di " : (($row['MediaType'] === "Manga") ? "ha letto un capitolo di " : "ha progredito in ");
                            } elseif ($row['Type'] === "Completed a Media") {
                                echo "ha completato ";
                            } elseif ($row['Type'] === "Voted a Media") {
                                echo "ha votato ";
                            } elseif ($row['Type'] === "Changed Username") {
                                echo "ha cambiato username";
                            } else {
                                echo htmlspecialchars($row['Type']);
                            }
                            echo "<em>" . htmlspecialchars($row['MediaTitle']) . "</em>";
                        } else {
                            echo htmlspecialchars($row['Type']);
                        }
                    ?>
                </span>
            </div>
        </div>
    <?php endwhile; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
