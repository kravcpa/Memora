<?php
// SRC/browse.php
session_start();
require_once "../Config/config.php";
if (!isset($_SESSION['email'])) { header("Location: index.php"); exit(); }
include "navbar.php";

$mediaTypes = $conn->query("SELECT Id, Type FROM mediatype ORDER BY Type")->fetch_all(MYSQLI_ASSOC);
$genres     = $conn->query("SELECT Id, Name FROM genre ORDER BY Name")->fetch_all(MYSQLI_ASSOC);
$creators   = $conn->query("SELECT id, name FROM creator ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$platforms  = $conn->query("SELECT Id, Name FROM platform ORDER BY Name")->fetch_all(MYSQLI_ASSOC);
$pubStatus  = $conn->query("SELECT Id, Type FROM publishingstatus ORDER BY Id")->fetch_all(MYSQLI_ASSOC);

/* Top-10 per mediatype (senza filtri) */
$topListByType = [];
foreach ($mediaTypes as $mt) {
    $stmt = $conn->prepare("
        SELECT m.Id, m.Title, COALESCE(AVG(b.Rating),0) AS avg_rating
        FROM media m
        LEFT JOIN bookmark b ON b.MediaId = m.Id AND b.Rating IS NOT NULL
        WHERE m.MediaTypeId = ?
        GROUP BY m.Id
        ORDER BY avg_rating DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $mt['Id']);
    $stmt->execute();
    $topListByType[$mt['Type']] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Browse - Memora</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo CSS_PATH; ?>">
</head>
<body class="bg-light">

<div class="container py-4">
    <!-- Search + Filters (inviano a search.php) -->
    <form method="get" action="search.php" class="mb-3">
        <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="Cerca media o utenti...">
            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filters">Filtri</button>
            <button class="btn btn-primary" type="submit">Cerca</button>
        </div>

        <div class="collapse mt-3" id="filters">
            <div class="card card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Tipo di media</label>
                        <select name="mediatype[]" multiple class="form-select">
                            <?php foreach ($mediaTypes as $mt): ?>
                                <option value="<?php echo $mt['Id']; ?>"><?php echo htmlspecialchars($mt['Type']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Tieni Ctrl/Cmd per selezionare più tipi</small>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Generi</label>
                        <select name="genre[]" multiple class="form-select">
                            <?php foreach ($genres as $g): ?>
                                <option value="<?php echo $g['Id']; ?>"><?php echo htmlspecialchars($g['Name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Creatore</label>
                        <select name="creator" class="form-select">
                            <option value="">— Any —</option>
                            <?php foreach ($creators as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Piattaforma</label>
                        <select name="platform" class="form-select">
                            <option value="">— Any —</option>
                            <?php foreach ($platforms as $p): ?>
                                <option value="<?php echo $p['Id']; ?>"><?php echo htmlspecialchars($p['Name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Data da</label>
                        <input type="date" name="year_from" class="form-control" value="<?php echo htmlspecialchars($yearFrom ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data a</label>
                        <input type="date" name="year_to" class="form-control" value="<?php echo htmlspecialchars($yearTo ?? ''); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Publishing status</label>
                        <select name="pubstatus" class="form-select">
                            <option value="">— Any —</option>
                            <?php foreach ($pubStatus as $ps): ?>
                                <option value="<?php echo $ps['Id']; ?>"><?php echo htmlspecialchars($ps['Type']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="Any">— Any —</option>
                            <option value="User">User</option>
                            <option value="Media">Media</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Top 10 per tipo -->
    <?php foreach ($topListByType as $typeName => $list): ?>
        <div class="card mb-3 shadow-sm">
            <div class="card-body">
                <h5 class="card-title"><?php echo htmlspecialchars($typeName); ?> — Top 10</h5>
                <?php if (empty($list)): ?>
                    <p class="text-muted">Nessun media trovato.</p>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($list as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="media.php?id=<?php echo $item['Id']; ?>"><?php echo htmlspecialchars($item['Title']); ?></a>
                                <span class="badge bg-primary rounded-pill"><?php echo number_format((float)$item['avg_rating'], 2); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
