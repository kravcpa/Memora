<?php
// SRC/search.php (corretto: non ci sono duplicati di "q")
session_start();
require_once "../Config/config.php";
if (!isset($_SESSION['email'])) { header("Location: index.php"); exit(); }
include "navbar.php";

/* --- Input e filtri --- */
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$typeFilter = isset($_GET['type']) ? $_GET['type'] : 'Any'; // Any|User|Media

$typesFilter   = isset($_GET['mediatype']) ? (array)$_GET['mediatype'] : [];
$genreFilter   = isset($_GET['genre']) ? (array)$_GET['genre'] : [];
$creatorFilter = isset($_GET['creator']) && $_GET['creator'] !== '' ? intval($_GET['creator']) : null;
$platformFilter= isset($_GET['platform']) && $_GET['platform'] !== '' ? intval($_GET['platform']) : null;
$yearFrom      = isset($_GET['year_from']) && $_GET['year_from'] !== '' ? $_GET['year_from'] : null; // date string YYYY-MM-DD
$yearTo        = isset($_GET['year_to']) && $_GET['year_to'] !== '' ? $_GET['year_to'] : null;
$pubFilter     = isset($_GET['pubstatus']) && $_GET['pubstatus'] !== '' ? intval($_GET['pubstatus']) : null;

/* --- Dati per popolare filtri (stesso ordine di browse) --- */
$mediaTypes = $conn->query("SELECT Id, Type FROM mediatype ORDER BY Type")->fetch_all(MYSQLI_ASSOC);
$genresList = $conn->query("SELECT Id, Name FROM genre ORDER BY Name")->fetch_all(MYSQLI_ASSOC);
$creators   = $conn->query("SELECT id, name FROM creator ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$platforms  = $conn->query("SELECT Id, Name FROM platform ORDER BY Name")->fetch_all(MYSQLI_ASSOC);
$pubStatus  = $conn->query("SELECT Id, Type FROM publishingstatus ORDER BY Id")->fetch_all(MYSQLI_ASSOC);

/* --- Risultati --- */
$users = [];
$media = [];

/* === Cerca utenti (prepared) === */
if ($typeFilter === 'Any' || $typeFilter === 'User') {
    if ($q !== '') {
        $like = '%' . $q . '%';
        $uStmt = $conn->prepare("SELECT Username, Email, ImageId FROM user WHERE Username LIKE ? OR Email LIKE ? ORDER BY Username LIMIT 200");
        $uStmt->bind_param("ss", $like, $like);
        $uStmt->execute();
        $users = $uStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $users = [];
    }
}

/* === Cerca media (con filtri) === */
if ($typeFilter === 'Any' || $typeFilter === 'Media') {
    $where = [];
    $joinGenre = "";

    if ($q !== '') {
        // escape per LIKE
        $likeEsc = '%' . $conn->real_escape_string($q) . '%';
        $where[] = "m.Title LIKE '{$likeEsc}'";
    }

    if (!empty($typesFilter)) {
        $ids = array_map('intval', $typesFilter);
        $where[] = "m.MediaTypeId IN (" . implode(",", $ids) . ")";
    }

    if (!empty($genreFilter)) {
        $gids = array_map('intval', $genreFilter);
        $joinGenre = " JOIN isofgenre ig ON ig.MediaId = m.Id ";
        $where[] = "ig.GenreId IN (" . implode(",", $gids) . ")";
    }

    if ($creatorFilter) $where[] = "m.CreatorId = " . intval($creatorFilter);
    if ($platformFilter) $where[] = "m.PlatformId = " . intval($platformFilter);
    if ($yearFrom) {
        $yFrom = intval(substr($yearFrom,0,4));
        $where[] = "YEAR(m.PublishingDate) >= " . $yFrom;
    }
    if ($yearTo) {
        $yTo = intval(substr($yearTo,0,4));
        $where[] = "YEAR(m.PublishingDate) <= " . $yTo;
    }
    if ($pubFilter) $where[] = "m.PubStatusId = " . intval($pubFilter);

    $whereSQL = count($where) ? "WHERE " . implode(" AND ", $where) : "";

    $sql = "
        SELECT DISTINCT m.Id, m.Title, mt.Type AS MediaType
        FROM media m
        JOIN mediatype mt ON m.MediaTypeId = mt.Id
        {$joinGenre}
        {$whereSQL}
        ORDER BY mt.Type, m.Title
        LIMIT 500
    ";
    $media = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Ricerca - Memora</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo CSS_PATH; ?>">
</head>
<body class="bg-light">
<div class="container py-4">

    <!-- Form ricerca + filtri (stesso layout dell'area filtri in browse.php) -->
    <form class="mb-3" method="get" action="search.php">
        <div class="input-group">
            <!-- unica casella q: l'utente digita qui ed è il valore inviato -->
            <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Cerca...">
            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filters">Filtri</button>
            <button class="btn btn-primary" type="submit">Cerca</button>
        </div>

        <div class="collapse mt-3" id="filters">
            <div class="card card-body">
                <div class="row g-3">

                    <!-- Tipo di media -->
                    <div class="col-md-3">
                        <label class="form-label">Tipo di media</label>
                        <select name="mediatype[]" multiple class="form-select">
                            <?php foreach ($mediaTypes as $mt): ?>
                                <option value="<?php echo $mt['Id']; ?>" <?php if(in_array($mt['Id'], $typesFilter)) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($mt['Type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Generi -->
                    <div class="col-md-3">
                        <label class="form-label">Generi</label>
                        <select name="genre[]" multiple class="form-select">
                            <?php foreach ($genresList as $g): ?>
                                <option value="<?php echo $g['Id']; ?>" <?php if(in_array($g['Id'], $genreFilter)) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($g['Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Creatore -->
                    <div class="col-md-3">
                        <label class="form-label">Creatore</label>
                        <select name="creator" class="form-select">
                            <option value="">— Any —</option>
                            <?php foreach ($creators as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php if($creatorFilter == $c['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Piattaforma -->
                    <div class="col-md-3">
                        <label class="form-label">Piattaforma</label>
                        <select name="platform" class="form-select">
                            <option value="">— Any —</option>
                            <?php foreach ($platforms as $p): ?>
                                <option value="<?php echo $p['Id']; ?>" <?php if($platformFilter == $p['Id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($p['Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date (uso date inputs, poi estraggo YEAR lato server) -->
                    <div class="col-md-3">
                        <label class="form-label">Data da</label>
                        <input type="date" name="year_from" class="form-control" value="<?php echo htmlspecialchars($yearFrom ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data a</label>
                        <input type="date" name="year_to" class="form-control" value="<?php echo htmlspecialchars($yearTo ?? ''); ?>">
                    </div>

                    <!-- Publishing status -->
                    <div class="col-md-3">
                        <label class="form-label">Publishing status</label>
                        <select name="pubstatus" class="form-select">
                            <option value="">— Any —</option>
                            <?php foreach ($pubStatus as $ps): ?>
                                <option value="<?php echo $ps['Id']; ?>" <?php if($pubFilter == $ps['Id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($ps['Type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Type -->
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="Any" <?php if($typeFilter==='Any') echo 'selected'; ?>>— Any —</option>
                            <option value="User" <?php if($typeFilter==='User') echo 'selected'; ?>>User</option>
                            <option value="Media" <?php if($typeFilter==='Media') echo 'selected'; ?>>Media</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <button class="btn btn-primary">Applica filtri</button>
                        <a href="search.php" class="btn btn-outline-secondary ms-2">Reset</a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Risultati Utenti -->
    <?php if ($typeFilter === 'Any' || $typeFilter === 'User'): ?>
        <div class="mb-4">
            <h5>Utenti</h5>
            <?php if (empty($users)): ?>
                <p class="text-muted">Nessun utente trovato.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($users as $u):
                        $imgUrl = $conn->query("SELECT URL FROM image WHERE Id=" . intval($u['ImageId']))->fetch_assoc()['URL'] ?? '';
                        ?>
                        <li class="list-group-item d-flex align-items-center">
                            <img src="<?php echo htmlspecialchars($imgUrl); ?>" class="rounded-circle me-2" width="36" height="36">
                            <div>
                                <strong><?php echo htmlspecialchars($u['Username']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($u['Email']); ?></small>
                            </div>
                            <a href="profile.php?u=<?php echo urlencode($u['Email']); ?>" class="btn btn-sm btn-outline-primary ms-auto">Vedi profilo</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Risultati Media -->
    <?php if ($typeFilter === 'Any' || $typeFilter === 'Media'): ?>
        <div>
            <h5>Media</h5>
            <?php if (empty($media)): ?>
                <p class="text-muted">Nessun media trovato.</p>
            <?php else:
                $byType = [];
                foreach ($media as $m) $byType[$m['MediaType']][] = $m;
                foreach ($byType as $type => $items): ?>
                    <h6 class="mt-3"><?php echo htmlspecialchars($type); ?></h6>
                    <ul class="list-group mb-3">
                        <?php foreach ($items as $it): ?>
                            <li class="list-group-item">
                                <a href="media.php?id=<?php echo $it['Id']; ?>"><?php echo htmlspecialchars($it['Title']); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach;
            endif; ?>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
