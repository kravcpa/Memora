<?php
// SRC/navbar.php
if (!isset($_SESSION)) session_start();
require_once "../Config/config.php";

// Se non loggato non mostriamo la navbar (ma tu usi navbar solo in pagine protette)
if (!isset($_SESSION['email'])) return;

$email = $_SESSION['email'];
$stmt = $conn->prepare("
    SELECT u.Username, u.IsAdmin, i.URL AS Avatar
    FROM user u
    JOIN image i ON u.ImageId = i.Id
    WHERE u.Email = ?
");
$stmt->bind_param("s", $email);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="home.php">Memora</a>
        <div class="d-flex align-items-center">
            <img src="<?php echo htmlspecialchars($userData['Avatar']); ?>" 
                 alt="Avatar" class="rounded-circle me-2" width="40" height="40">
            <span class="text-white me-3"><?php echo htmlspecialchars($userData['Username']); ?></span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="bg-light border-bottom">
    <div class="container d-flex justify-content-between py-2">
        <div>
            <a href="home.php" class="btn btn-link">Home</a>
            <a href="profile.php" class="btn btn-link">Profile</a>
            <a href="browse.php" class="btn btn-link">Browse</a>
            <?php if ($userData['IsAdmin']): ?>
                <a href="admin.php" class="btn btn-outline-warning me-2">Admin</a>
            <?php endif; ?>
        </div>
        <form class="d-flex" method="get" action="search.php">
            <input class="form-control me-2" type="text" name="q" placeholder="Cerca utenti o media...">
            <button class="btn btn-outline-primary" type="submit">Invia</button>
        </form>
    </div>
</div>
