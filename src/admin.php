<?php
// SRC/admin.php (versione aggiornata: fix creator/platform/layout/relation enum/isofgenre + enabled edits)
// Usa Bootstrap; sovrascrivi il file esistente in \Memora\SRC\admin.php
session_start();
require_once __DIR__ . "/../Config/config.php";

// *** controllo admin
if (!isset($_SESSION['email'])) { header("Location: index.php"); exit(); }
$me = $conn->prepare("SELECT IsAdmin, Username FROM user WHERE Email = ?");
$me->bind_param("s", $_SESSION['email']); $me->execute();
$meRes = $me->get_result()->fetch_assoc(); $me->close();
if (!$meRes || intval($meRes['IsAdmin']) !== 1) {
    header("Location: error.php?msg=" . urlencode("Accesso riservato agli admin.")); exit();
}
$adminName = $meRes['Username'];

// entità gestibili (aggiunto isofgenre)
$allowedEntities = ['media','genre','creator','platform','status','publishingstatus','mediatype','relation','isofgenre'];
$entity = isset($_GET['entity']) && in_array($_GET['entity'],$allowedEntities) ? $_GET['entity'] : 'media';

/* -----------------------------
   HELP: estrai enum values per relation.Type
   ----------------------------- */
$relationTypes = [];
$col = $conn->query("SHOW COLUMNS FROM relation LIKE 'Type'")->fetch_assoc();
if ($col && isset($col['Type'])) {
    // Type: enum('Sequel','Prequel',...)
    if (preg_match("/^enum\\((.*)\\)$/", $col['Type'], $m)) {
        $vals = str_getcsv($m[1], ",", "'"); // parse naif
        foreach ($vals as $v) $relationTypes[] = trim($v, "'");
    }
}

/* -----------------------------
   HANDLER POST (save / delete) — prepared statements
   ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postEntity = $_POST['entity'] ?? '';
    $action = $_POST['action'] ?? '';

    // ----- MEDIA save / delete -----
    if ($postEntity === 'media' && $action === 'save') {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null;
        $title = trim($_POST['title'] ?? '');
        $mediatype = intval($_POST['mediatype'] ?? 0);
        $creator = intval($_POST['creator'] ?? 0);
        $platform = intval($_POST['platform'] ?? 0);
        $pubstatus = intval($_POST['pubstatus'] ?? 0);
        $publishingdate = $_POST['publishingdate'] ?? null;
        $description = trim($_POST['description'] ?? '');
        if ($id) {
            $u = $conn->prepare("UPDATE media SET Title=?, MediaTypeId=?, CreatorId=?, PlatformId=?, PubStatusId=?, PublishingDate=?, Description=? WHERE Id=?");
            $u->bind_param("siiiissi", $title, $mediatype, $creator, $platform, $pubstatus, $publishingdate, $description, $id);
            $u->execute(); $u->close();
        } else {
            $i = $conn->prepare("INSERT INTO media (Title, MediaTypeId, CreatorId, PlatformId, PubStatusId, PublishingDate, Description) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $i->bind_param("siiiiss", $title, $mediatype, $creator, $platform, $pubstatus, $publishingdate, $description);
            $i->execute(); $i->close();
        }
        header("Location: admin.php?entity=media"); exit();
    }
    if ($postEntity === 'media' && $action === 'delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $d = $conn->prepare("DELETE FROM media WHERE Id = ?");
        $d->bind_param("i", $id); $d->execute(); $d->close();
        header("Location: admin.php?entity=media"); exit();
    }

    // ----- GENRE -----
    if ($postEntity === 'genre' && $action === 'save') {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null;
        $name = trim($_POST['name'] ?? '');
        if ($id) {
            $u = $conn->prepare("UPDATE genre SET Name=? WHERE Id=?"); $u->bind_param("si",$name,$id); $u->execute(); $u->close();
        } else {
            $i = $conn->prepare("INSERT INTO genre (Name) VALUES (?)"); $i->bind_param("s",$name); $i->execute(); $i->close();
        }
        header("Location: admin.php?entity=genre"); exit();
    }
    if ($postEntity === 'genre' && $action === 'delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']); $d = $conn->prepare("DELETE FROM genre WHERE Id = ?"); $d->bind_param("i",$id); $d->execute(); $d->close();
        header("Location: admin.php?entity=genre"); exit();
    }

    // ----- CREATOR (ATTENZIONE: creator ha solo id,name,Description) -----
    if ($postEntity === 'creator' && $action === 'save') {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($id) {
            $u = $conn->prepare("UPDATE creator SET name=?, Description=? WHERE id=?"); $u->bind_param("ssi",$name,$description,$id); $u->execute(); $u->close();
        } else {
            $i = $conn->prepare("INSERT INTO creator (name, Description) VALUES (?, ?)"); $i->bind_param("ss",$name,$description); $i->execute(); $i->close();
        }
        header("Location: admin.php?entity=creator"); exit();
    }
    if ($postEntity === 'creator' && $action === 'delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']); $d = $conn->prepare("DELETE FROM creator WHERE id = ?"); $d->bind_param("i",$id); $d->execute(); $d->close();
        header("Location: admin.php?entity=creator"); exit();
    }

    // ----- PLATFORM (NAME, Description, WebsiteURL) -----
    if ($postEntity === 'platform' && $action === 'save') {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $website = trim($_POST['website'] ?? '');
        if ($id) {
            $u = $conn->prepare("UPDATE platform SET Name=?, Description=?, WebsiteURL=? WHERE Id=?");
            $u->bind_param("sssi",$name,$description,$website,$id); $u->execute(); $u->close();
        } else {
            $i = $conn->prepare("INSERT INTO platform (Name, Description, WebsiteURL) VALUES (?, ?, ?)");
            $i->bind_param("sss",$name,$description,$website); $i->execute(); $i->close();
        }
        header("Location: admin.php?entity=platform"); exit();
    }
    if ($postEntity === 'platform' && $action === 'delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']); $d = $conn->prepare("DELETE FROM platform WHERE Id = ?"); $d->bind_param("i",$id); $d->execute(); $d->close();
        header("Location: admin.php?entity=platform"); exit();
    }

    // ----- STATUS -----
    if ($postEntity === 'status' && $action === 'save') {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null;
        $type = trim($_POST['type'] ?? '');
        if ($id) { $u = $conn->prepare("UPDATE status SET Type=? WHERE Id=?"); $u->bind_param("si",$type,$id); $u->execute(); $u->close(); }
        else { $i = $conn->prepare("INSERT INTO status (Type) VALUES (?)"); $i->bind_param("s",$type); $i->execute(); $i->close(); }
        header("Location: admin.php?entity=status"); exit();
    }
    if ($postEntity === 'status' && $action === 'delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']); $d = $conn->prepare("DELETE FROM status WHERE Id = ?"); $d->bind_param("i",$id); $d->execute(); $d->close();
        header("Location: admin.php?entity=status"); exit();
    }

    // ----- PUBLISHINGSTATUS -----
    if ($postEntity === 'publishingstatus' && $action === 'save') {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null;
        $type = trim($_POST['type'] ?? '');
        if ($id) { $u = $conn->prepare("UPDATE publishingstatus SET Type=? WHERE Id=?"); $u->bind_param("si",$type,$id); $u->execute(); $u->close(); }
        else { $i = $conn->prepare("INSERT INTO publishingstatus (Type) VALUES (?)"); $i->bind_param("s",$type); $i->execute(); $i->close(); }
        header("Location: admin.php?entity=publishingstatus"); exit();
    }
    if ($postEntity === 'publishingstatus' && $action === 'delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']); $d = $conn->prepare("DELETE FROM publishingstatus WHERE Id = ?"); $d->bind_param("i",$id); $d->execute(); $d->close();
        header("Location: admin.php?entity=publishingstatus"); exit();
    }

    // ----- MEDIATYPE -----
    if ($postEntity === 'mediatype' && $action === 'save') {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null;
        $typeName = trim($_POST['type'] ?? '');
        if ($id) { $u = $conn->prepare("UPDATE mediatype SET Type=? WHERE Id=?"); $u->bind_param("si",$typeName,$id); $u->execute(); $u->close(); }
        else { $i = $conn->prepare("INSERT INTO mediatype (Type) VALUES (?)"); $i->bind_param("s",$typeName); $i->execute(); $i->close(); }
        header("Location: admin.php?entity=mediatype"); exit();
    }
    if ($postEntity === 'mediatype' && $action === 'delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']); $d = $conn->prepare("DELETE FROM mediatype WHERE Id = ?"); $d->bind_param("i",$id); $d->execute(); $d->close();
        header("Location: admin.php?entity=mediatype"); exit();
    }

    // ----- RELATION (Type è enum: usiamo dropdown) -----
    if ($postEntity === 'relation' && $action === 'save') {
        $orig_source = isset($_POST['orig_source']) && $_POST['orig_source'] !== '' ? intval($_POST['orig_source']) : null;
        $orig_target = isset($_POST['orig_target']) && $_POST['orig_target'] !== '' ? intval($_POST['orig_target']) : null;
        $orig_type = $_POST['orig_type'] ?? null;

        $source = intval($_POST['source_id']);
        $target = intval($_POST['target_id']);
        $rtype = trim($_POST['rtype']);

        if ($orig_source !== null && $orig_target !== null && $orig_type !== null) {
            if ($orig_source !== $source || $orig_target !== $target || $orig_type !== $rtype) {
                $del = $conn->prepare("DELETE FROM relation WHERE SourceId=? AND TargetId=? AND Type=?");
                $del->bind_param("iis",$orig_source,$orig_target,$orig_type); $del->execute(); $del->close();
            }
        }
        $ins = $conn->prepare("INSERT IGNORE INTO relation (SourceId, TargetId, Type) VALUES (?, ?, ?)");
        $ins->bind_param("iis",$source,$target,$rtype); $ins->execute(); $ins->close();
        header("Location: admin.php?entity=relation"); exit();
    }
    if ($postEntity === 'relation' && $action === 'delete') {
        $src = intval($_POST['source']); $tgt = intval($_POST['target']); $type = $_POST['type'] ?? '';
        $d = $conn->prepare("DELETE FROM relation WHERE SourceId=? AND TargetId=? AND Type=?"); $d->bind_param("iis",$src,$tgt,$type); $d->execute(); $d->close();
        header("Location: admin.php?entity=relation"); exit();
    }

    // ----- ISOFGENRE management (new section) -----
    if ($postEntity === 'isofgenre' && $action === 'add') {
        $mid = intval($_POST['media_id']); $gid = intval($_POST['genre_id']);
        $ins = $conn->prepare("INSERT IGNORE INTO isofgenre (MediaId, GenreId) VALUES (?, ?)");
        $ins->bind_param("ii", $mid, $gid); $ins->execute(); $ins->close();
        header("Location: admin.php?entity=isofgenre"); exit();
    }
    if ($postEntity === 'isofgenre' && $action === 'delete') {
        $mid = intval($_POST['media_id']); $gid = intval($_POST['genre_id']);
        $d = $conn->prepare("DELETE FROM isofgenre WHERE MediaId=? AND GenreId=?"); $d->bind_param("ii",$mid,$gid); $d->execute(); $d->close();
        header("Location: admin.php?entity=isofgenre"); exit();
    }
}

/* -----------------------------
   Recupero dati per UI (senza usare get_result multiplo problematico)
   ----------------------------- */
// Media (con join per mostrare i nomi)
$mediaList = $conn->query("
    SELECT m.Id, m.Title, m.Description, m.PublishingDate, m.MediaTypeId, m.CreatorId, m.PlatformId, m.PubStatusId,
           mt.Type AS MediaType, c.name AS CreatorName, p.Name AS PlatformName, ps.Type AS PubStatus
    FROM media m
    LEFT JOIN mediatype mt ON m.MediaTypeId = mt.Id
    LEFT JOIN creator c ON m.CreatorId = c.id
    LEFT JOIN platform p ON m.PlatformId = p.Id
    LEFT JOIN publishingstatus ps ON m.PubStatusId = ps.Id
    ORDER BY m.Title
")->fetch_all(MYSQLI_ASSOC);

// altri: genres, creators, platforms (con descrizioni), status, pubStatus, mediatypes
$genres = $conn->query("SELECT Id, Name FROM genre ORDER BY Name")->fetch_all(MYSQLI_ASSOC);
$creators = $conn->query("SELECT id, name, Description FROM creator ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$platforms = $conn->query("SELECT Id, Name, Description, WebsiteURL FROM platform ORDER BY Name")->fetch_all(MYSQLI_ASSOC);
$statuses = $conn->query("SELECT Id, Type FROM status ORDER BY Id")->fetch_all(MYSQLI_ASSOC);
$pubStatus = $conn->query("SELECT Id, Type FROM publishingstatus ORDER BY Id")->fetch_all(MYSQLI_ASSOC);
$mediatypes = $conn->query("SELECT Id, Type FROM mediatype ORDER BY Id")->fetch_all(MYSQLI_ASSOC);

// relations (mostriamo titolo + mediatype di source/target)
$relations = $conn->query("
    SELECT r.SourceId, r.TargetId, r.Type,
           s.Title AS SourceTitle, smt.Type AS SourceMediaType,
           t.Title AS TargetTitle, tmt.Type AS TargetMediaType
    FROM relation r
    LEFT JOIN media s ON r.SourceId = s.Id
    LEFT JOIN mediatype smt ON s.MediaTypeId = smt.Id
    LEFT JOIN media t ON r.TargetId = t.Id
    LEFT JOIN mediatype tmt ON t.MediaTypeId = tmt.Id
    ORDER BY r.SourceId
")->fetch_all(MYSQLI_ASSOC);

// isofgenre list
$isofgenre = $conn->query("
    SELECT ig.MediaId, ig.GenreId, m.Title AS MediaTitle, mt.Type AS MediaType, g.Name AS GenreName
    FROM isofgenre ig
    JOIN media m ON ig.MediaId = m.Id
    JOIN mediatype mt ON m.MediaTypeId = mt.Id
    JOIN genre g ON ig.GenreId = g.Id
    ORDER BY m.Title, g.Name
")->fetch_all(MYSQLI_ASSOC);

/* -----------------------------
   HTML
   ----------------------------- */
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Admin — Memora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f7f7f9; }
    .row-form { gap:8px; margin-bottom:6px; align-items:center; display:flex; flex-wrap:wrap; }
    .delete-x { width:36px; text-align:center; }
    .small-input { max-width:160px; }
    .textarea-small { min-height:40px; max-height:120px; resize:vertical; }
    .list-group-item { display:flex; flex-direction:row; gap:8px; align-items:center; }
    .col-flex { flex:1 1 auto; min-width:120px; }
    .col-fixed { flex: 0 0 auto; }
    .entity-actions { display:flex; gap:6px; align-items:center; }
    .form-row-inline { display:flex; gap:8px; width:100%; align-items:center; }
    .muted-small { font-size:0.85rem; color:#666; }
    .section-title { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
  </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Admin panel — <?php echo htmlspecialchars($adminName); ?></h3>
        <a href="home.php" class="btn btn-outline-secondary">Torna</a>
    </div>

    <form id="chooseEntity" method="get" class="mb-3">
        <label class="form-label">Gestisci:</label>
        <select name="entity" onchange="document.getElementById('chooseEntity').submit()" class="form-select" style="max-width:420px">
            <?php foreach ($allowedEntities as $e): ?>
                <option value="<?php echo $e; ?>" <?php if($entity === $e) echo 'selected'; ?>><?php echo ucfirst($e); ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <!-- MEDIA -->
    <div <?php if($entity !== 'media') echo 'style="display:none"'; ?>>
        <div class="section-title">
            <h5>Media (<?php echo count($mediaList); ?>)</h5>
            <button class="btn btn-sm btn-success" onclick="toggleNew('media')">Aggiungi nuovo media</button>
        </div>

        <div id="new-media" class="card card-body mb-3" style="display:none">
            <form method="post" class="form-row-inline">
                <input type="hidden" name="entity" value="media">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="">
                <input name="title" class="form-control col-flex" placeholder="Titolo" required>
                <select name="mediatype" class="form-select small-input">
                    <?php foreach($mediatypes as $mt): ?><option value="<?php echo $mt['Id']; ?>"><?php echo htmlspecialchars($mt['Type']); ?></option><?php endforeach; ?>
                </select>
                <select name="creator" class="form-select small-input">
                    <?php foreach($creators as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?>
                </select>
                <select name="platform" class="form-select small-input">
                    <?php foreach($platforms as $p): ?><option value="<?php echo $p['Id']; ?>"><?php echo htmlspecialchars($p['Name']); ?></option><?php endforeach; ?>
                </select>
                <select name="pubstatus" class="form-select small-input">
                    <?php foreach($pubStatus as $ps): ?><option value="<?php echo $ps['Id']; ?>"><?php echo htmlspecialchars($ps['Type']); ?></option><?php endforeach; ?>
                </select>
                <input type="date" name="publishingdate" class="form-control small-input">
                <button class="btn btn-primary">Salva</button>
            </form>
            <div class="mt-2">
                <textarea name="description" class="form-control textarea-small" placeholder="Description"></textarea>
            </div>
        </div>

        <div class="list-group">
            <?php foreach($mediaList as $m): ?>
                <form method="post" class="list-group-item" oninput="enableSave(this)">
                    <input type="hidden" name="entity" value="media">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?php echo intval($m['Id']); ?>">

                    <div class="delete-x col-fixed">
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="doDelete('media', <?php echo intval($m['Id']); ?>)">X</button>
                    </div>

                    <input name="title" class="form-control col-flex" value="<?php echo htmlspecialchars($m['Title']); ?>">

                    <select name="mediatype" class="form-select small-input">
                        <?php foreach($mediatypes as $mt): ?>
                            <option value="<?php echo $mt['Id']; ?>" <?php if(intval($mt['Id'])===intval($m['MediaTypeId'])) echo 'selected'; ?>><?php echo htmlspecialchars($mt['Type']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="creator" class="form-select small-input">
                        <?php foreach($creators as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php if(intval($c['id'])===intval($m['CreatorId'])) echo 'selected'; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="platform" class="form-select small-input">
                        <?php foreach($platforms as $p): ?>
                            <option value="<?php echo $p['Id']; ?>" <?php if(intval($p['Id'])===intval($m['PlatformId'])) echo 'selected'; ?>><?php echo htmlspecialchars($p['Name']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="pubstatus" class="form-select small-input">
                        <?php foreach($pubStatus as $ps): ?>
                            <option value="<?php echo $ps['Id']; ?>" <?php if(intval($ps['Id'])===intval($m['PubStatusId'])) echo 'selected'; ?>><?php echo htmlspecialchars($ps['Type']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <input type="date" name="publishingdate" class="form-control small-input" value="<?php echo htmlspecialchars($m['PublishingDate']); ?>">

                    <textarea name="description" class="form-control textarea-small col-flex"><?php echo htmlspecialchars($m['Description']); ?></textarea>

                    <div class="entity-actions col-fixed">
                        <button type="submit" class="btn btn-primary btn-save" disabled>Modifica</button>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- GENRE -->
    <div <?php if($entity !== 'genre') echo 'style="display:none"'; ?>>
        <div class="section-title">
            <h5>Generi (<?php echo count($genres); ?>)</h5>
            <button class="btn btn-sm btn-success" onclick="toggleNew('genre')">Aggiungi</button>
        </div>
        <div id="new-genre" class="card card-body mb-2" style="display:none">
            <form method="post" class="form-row-inline">
                <input type="hidden" name="entity" value="genre"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
                <input name="name" class="form-control col-flex" placeholder="Nome genere" required>
                <button class="btn btn-primary">Aggiungi</button>
            </form>
        </div>
        <div class="list-group">
            <?php foreach($genres as $g): ?>
                <form method="post" class="list-group-item" oninput="enableSave(this)">
                    <input type="hidden" name="entity" value="genre"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo intval($g['Id']); ?>">
                    <div class="delete-x col-fixed"><button type="button" class="btn btn-sm btn-outline-danger" onclick="doDelete('genre', <?php echo intval($g['Id']); ?>)">X</button></div>
                    <input name="name" class="form-control col-flex" value="<?php echo htmlspecialchars($g['Name']); ?>">
                    <div class="entity-actions col-fixed"><button type="submit" class="btn btn-primary btn-save" disabled>Modifica</button></div>
                </form>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- CREATOR -->
    <div <?php if($entity !== 'creator') echo 'style="display:none"'; ?>>
        <div class="section-title">
            <h5>Creatori (<?php echo count($creators); ?>)</h5>
            <button class="btn btn-sm btn-success" onclick="toggleNew('creator')">Aggiungi</button>
        </div>
        <div id="new-creator" class="card card-body mb-2" style="display:none">
            <form method="post" class="form-row-inline">
                <input type="hidden" name="entity" value="creator"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
                <input name="name" class="form-control" placeholder="Nome" required>
                <input name="description" class="form-control col-flex" placeholder="Descrizione">
                <button class="btn btn-primary">Aggiungi</button>
            </form>
        </div>
        <div class="list-group">
            <?php foreach($creators as $c): ?>
                <form method="post" class="list-group-item" oninput="enableSave(this)">
                    <input type="hidden" name="entity" value="creator"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo intval($c['id']); ?>">
                    <div class="delete-x col-fixed"><button type="button" class="btn btn-sm btn-outline-danger" onclick="doDelete('creator', <?php echo intval($c['id']); ?>)">X</button></div>
                    <input name="name" class="form-control small-input" value="<?php echo htmlspecialchars($c['name']); ?>">
                    <input name="description" class="form-control col-flex" value="<?php echo htmlspecialchars($c['Description']); ?>">
                    <div class="entity-actions col-fixed"><button type="submit" class="btn btn-primary btn-save" disabled>Modifica</button></div>
                </form>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- PLATFORM -->
    <div <?php if($entity !== 'platform') echo 'style="display:none"'; ?>>
        <div class="section-title">
            <h5>Piattaforme (<?php echo count($platforms); ?>)</h5>
            <button class="btn btn-sm btn-success" onclick="toggleNew('platform')">Aggiungi</button>
        </div>
        <div id="new-platform" class="card card-body mb-2" style="display:none">
            <form method="post" class="form-row-inline">
                <input type="hidden" name="entity" value="platform"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
                <input name="name" class="form-control small-input" placeholder="Nome" required>
                <input name="description" class="form-control col-flex" placeholder="Description">
                <input name="website" class="form-control small-input" placeholder="WebsiteURL">
                <button class="btn btn-primary">Aggiungi</button>
            </form>
        </div>
        <div class="list-group">
            <?php foreach($platforms as $p): ?>
                <form method="post" class="list-group-item" oninput="enableSave(this)">
                    <input type="hidden" name="entity" value="platform"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo intval($p['Id']); ?>">
                    <div class="delete-x col-fixed"><button type="button" class="btn btn-sm btn-outline-danger" onclick="doDelete('platform', <?php echo intval($p['Id']); ?>)">X</button></div>
                    <input name="name" class="form-control small-input" value="<?php echo htmlspecialchars($p['Name']); ?>">
                    <input name="description" class="form-control col-flex" value="<?php echo htmlspecialchars($p['Description']); ?>">
                    <input name="website" class="form-control small-input" value="<?php echo htmlspecialchars($p['WebsiteURL']); ?>">
                    <div class="entity-actions col-fixed"><button type="submit" class="btn btn-primary btn-save" disabled>Modifica</button></div>
                </form>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- STATUS -->
    <div <?php if($entity !== 'status') echo 'style="display:none"'; ?>>
        <div class="section-title"><h5>Status (<?php echo count($statuses); ?>)</h5><button class="btn btn-sm btn-success" onclick="toggleNew('status')">Aggiungi</button></div>
        <div id="new-status" class="card card-body mb-2" style="display:none">
            <form method="post" class="form-row-inline"><input type="hidden" name="entity" value="status"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value=""><input name="type" class="form-control col-flex" placeholder="Type" required><button class="btn btn-primary">Aggiungi</button></form>
        </div>
        <div class="list-group">
            <?php foreach($statuses as $s): ?>
                <form method="post" class="list-group-item" oninput="enableSave(this)"><input type="hidden" name="entity" value="status"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo intval($s['Id']); ?>"><div class="delete-x col-fixed"><button type="button" class="btn btn-sm btn-outline-danger" onclick="doDelete('status', <?php echo intval($s['Id']); ?>)">X</button></div><input name="type" class="form-control col-flex" value="<?php echo htmlspecialchars($s['Type']); ?>"><div class="entity-actions col-fixed"><button type="submit" class="btn btn-primary btn-save" disabled>Modifica</button></div></form>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- PUBLISHINGSTATUS -->
    <div <?php if($entity !== 'publishingstatus') echo 'style="display:none"'; ?>>
        <div class="section-title"><h5>Publishing status (<?php echo count($pubStatus); ?>)</h5><button class="btn btn-sm btn-success" onclick="toggleNew('publishingstatus')">Aggiungi</button></div>
        <div id="new-publishingstatus" class="card card-body mb-2" style="display:none">
            <form method="post" class="form-row-inline"><input type="hidden" name="entity" value="publishingstatus"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value=""><input name="type" class="form-control col-flex" placeholder="Type" required><button class="btn btn-primary">Aggiungi</button></form>
        </div>
        <div class="list-group">
            <?php foreach($pubStatus as $p): ?>
                <form method="post" class="list-group-item" oninput="enableSave(this)"><input type="hidden" name="entity" value="publishingstatus"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo intval($p['Id']); ?>"><div class="delete-x col-fixed"><button type="button" class="btn btn-sm btn-outline-danger" onclick="doDelete('publishingstatus', <?php echo intval($p['Id']); ?>)">X</button></div><input name="type" class="form-control col-flex" value="<?php echo htmlspecialchars($p['Type']); ?>"><div class="entity-actions col-fixed"><button type="submit" class="btn btn-primary btn-save" disabled>Modifica</button></div></form>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MEDIATYPE -->
    <div <?php if($entity !== 'mediatype') echo 'style="display:none"'; ?>>
        <div class="section-title"><h5>MediaType (<?php echo count($mediatypes); ?>)</h5><button class="btn btn-sm btn-success" onclick="toggleNew('mediatype')">Aggiungi</button></div>
        <div id="new-mediatype" class="card card-body mb-2" style="display:none">
            <form method="post" class="form-row-inline"><input type="hidden" name="entity" value="mediatype"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value=""><input name="type" class="form-control col-flex" placeholder="Tipo media" required><button class="btn btn-primary">Aggiungi</button></form>
        </div>
        <div class="list-group">
            <?php foreach($mediatypes as $mt): ?>
                <form method="post" class="list-group-item" oninput="enableSave(this)"><input type="hidden" name="entity" value="mediatype"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo intval($mt['Id']); ?>"><div class="delete-x col-fixed"><button type="button" class="btn btn-sm btn-outline-danger" onclick="doDelete('mediatype', <?php echo intval($mt['Id']); ?>)">X</button></div><input name="type" class="form-control col-flex" value="<?php echo htmlspecialchars($mt['Type']); ?>"><div class="entity-actions col-fixed"><button type="submit" class="btn btn-primary btn-save" disabled>Modifica</button></div></form>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- RELATION (Type = enum dropdown) -->
    <div <?php if($entity !== 'relation') echo 'style="display:none"'; ?>>
        <div class="section-title">
            <h5>Relazioni (<?php echo count($relations); ?>)</h5>
            <button class="btn btn-sm btn-success" onclick="toggleNew('relation')">Aggiungi relazione</button>
        </div>

        <div id="new-relation" class="card card-body mb-2" style="display:none">
            <form method="post" class="form-row-inline">
                <input type="hidden" name="entity" value="relation"><input type="hidden" name="action" value="save">
                <select name="source_id" class="form-select small-input">
                    <?php foreach($mediaList as $m): ?><option value="<?php echo $m['Id']; ?>"><?php echo htmlspecialchars($m['Title']) . " (" . htmlspecialchars($m['MediaType']) . ")"; ?></option><?php endforeach; ?>
                </select>
                <select name="rtype" class="form-select small-input">
                    <?php foreach($relationTypes as $rt): ?><option><?php echo htmlspecialchars($rt); ?></option><?php endforeach; ?>
                </select>
                <select name="target_id" class="form-select small-input">
                    <?php foreach($mediaList as $m): ?><option value="<?php echo $m['Id']; ?>"><?php echo htmlspecialchars($m['Title']) . " (" . htmlspecialchars($m['MediaType']) . ")"; ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn-primary">Aggiungi</button>
            </form>
        </div>

        <div class="list-group">
            <?php foreach($relations as $r): ?>
                <form method="post" class="list-group-item" oninput="enableSave(this)">
                    <input type="hidden" name="entity" value="relation"><input type="hidden" name="action" value="save">
                    <input type="hidden" name="orig_source" value="<?php echo intval($r['SourceId']); ?>">
                    <input type="hidden" name="orig_target" value="<?php echo intval($r['TargetId']); ?>">
                    <input type="hidden" name="orig_type" value="<?php echo htmlspecialchars($r['Type']); ?>">

                    <div class="delete-x col-fixed"><button type="button" class="btn btn-sm btn-outline-danger" onclick="doDeleteRelation(<?php echo intval($r['SourceId']); ?>, <?php echo intval($r['TargetId']); ?>, '<?php echo addslashes($r['Type']); ?>')">X</button></div>

                    <select name="source_id" class="form-select small-input">
                        <?php foreach($mediaList as $m): ?><option value="<?php echo $m['Id']; ?>" <?php if(intval($m['Id'])===intval($r['SourceId'])) echo 'selected'; ?>><?php echo htmlspecialchars($m['Title']) . " (" . htmlspecialchars($m['MediaType']) . ")"; ?></option><?php endforeach; ?>
                    </select>

                    <select name="rtype" class="form-select small-input">
                        <?php foreach($relationTypes as $rt): ?><option <?php if($rt=== $r['Type']) echo 'selected'; ?>><?php echo htmlspecialchars($rt); ?></option><?php endforeach; ?>
                    </select>

                    <select name="target_id" class="form-select small-input">
                        <?php foreach($mediaList as $m): ?><option value="<?php echo $m['Id']; ?>" <?php if(intval($m['Id'])===intval($r['TargetId'])) echo 'selected'; ?>><?php echo htmlspecialchars($m['Title']) . " (" . htmlspecialchars($m['MediaType']) . ")"; ?></option><?php endforeach; ?>
                    </select>

                    <div class="entity-actions col-fixed"><button type="submit" class="btn btn-primary btn-save" disabled>Modifica</button></div>
                </form>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ISOFGENRE (gestione mapping media <-> genre) -->
<div <?php if($entity !== 'isofgenre') echo 'style="display:none"'; ?>>
    <div class="section-title">
        <h5>Media ↔ Genre (<?php echo count($isofgenre); ?>)</h5>
        <div>
            <input id="isg-search" class="form-control form-control-sm" placeholder="Filtra media..." style="max-width:320px" oninput="filterMedia()">
        </div>
    </div>

    <?php
    // costruisco una mappatura MediaId => [genres]
    $mediaGenres = [];
    foreach ($isofgenre as $row) {
        $mid = intval($row['MediaId']);
        if (!isset($mediaGenres[$mid])) $mediaGenres[$mid] = [];
        $mediaGenres[$mid][] = ['GenreId'=>intval($row['GenreId']), 'GenreName'=>$row['GenreName']];
    }
    // genero una mappa dei generi per id (utile per select)
    $genreById = [];
    foreach ($genres as $g) $genreById[intval($g['Id'])] = $g['Name'];
    ?>

    <div class="row g-3">
        <?php foreach ($mediaList as $m): 
            $mid = intval($m['Id']);
            $currentGenres = $mediaGenres[$mid] ?? [];
            // calcolo generi ancora disponibili per l'add-select
            $used = array_column($currentGenres, 'GenreId');
            $availableGenres = array_filter($genres, function($gg) use ($used) {
                return !in_array(intval($gg['Id']), $used);
            });
        ?>
            <div class="col-12 col-md-6 col-lg-4 media-card" data-title="<?php echo strtolower($m['Title']); ?>">
                <div class="card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-0"><?php echo htmlspecialchars($m['Title']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($m['MediaType']); ?></small>
                            </div>
                            <div class="muted-small text-end">
                                <span class="text-muted">ID <?php echo intval($m['Id']); ?></span>
                            </div>
                        </div>

                        <!-- badges dei generi attuali -->
                        <div class="mb-2">
                            <?php if (count($currentGenres) === 0): ?>
                                <span class="text-muted small">Nessun genere assegnato</span>
                            <?php else: ?>
                                <?php foreach ($currentGenres as $cg): ?>
                                    <form method="post" class="d-inline-block me-1 mb-1" onsubmit="return confirm('Rimuovere il genere <?php echo addslashes($cg['GenreName']); ?> da <?php echo addslashes($m['Title']); ?>?')">
                                        <input type="hidden" name="entity" value="isofgenre">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="media_id" value="<?php echo $mid; ?>">
                                        <input type="hidden" name="genre_id" value="<?php echo intval($cg['GenreId']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Rimuovi genere">
                                            <span style="padding:4px 8px;"><?php echo htmlspecialchars($cg['GenreName']); ?> ×</span>
                                        </button>
                                    </form>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="mt-auto">
                            <!-- add genre form -->
                            <form method="post" class="d-flex gap-2 align-items-center">
                                <input type="hidden" name="entity" value="isofgenre">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="media_id" value="<?php echo $mid; ?>">
                                <select name="genre_id" class="form-select form-select-sm" required <?php if(empty($availableGenres)) echo 'disabled'; ?>>
                                    <option value=""><?php echo empty($availableGenres) ? 'Tutti i generi già assegnati' : 'Aggiungi genere...'; ?></option>
                                    <?php foreach ($availableGenres as $ag): ?>
                                        <option value="<?php echo intval($ag['Id']); ?>"><?php echo htmlspecialchars($ag['Name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm btn-primary" <?php if(empty($availableGenres)) echo 'disabled'; ?>>Aggiungi</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <style>
        .media-card .card { min-height:150px; }
        .media-card .btn-outline-danger { border-radius:14px; padding-left:8px; padding-right:8px; font-size:0.85rem; }
        #isg-search { width:100%; }
    </style>

    <script>
        // semplice filtro client-side della lista media per titolo
        function filterMedia() {
            const q = document.getElementById('isg-search').value.trim().toLowerCase();
            const cards = document.querySelectorAll('.media-card');
            cards.forEach(c => {
                const t = c.getAttribute('data-title') || '';
                c.style.display = (q === '' || t.indexOf(q) !== -1) ? 'block' : 'none';
            });
        }
    </script>
</div>

</div>

<script>
  // abilita il Save quando un campo cambia nella form (collega gli eventi)
  function enableSave(form) {
    let btn = form.querySelector('.btn-save');
    if (!btn) return;
    btn.disabled = false;
  }
  // toggle new rows
  function toggleNew(kind) {
    const el = document.getElementById('new-' + kind);
    if (!el) return;
    el.style.display = el.style.display === 'none' || el.style.display === '' ? 'block' : 'none';
    if (el.style.display === 'block') el.scrollIntoView({behavior:'smooth'});
  }

  // creazione form POST per delete generico
  function doDelete(entity, id) {
    if (!confirm('Eliminare elemento?')) return;
    const f = document.createElement('form');
    f.method = 'post'; f.style.display='none';
    const e = document.createElement('input'); e.name='entity'; e.value=entity; f.appendChild(e);
    const a = document.createElement('input'); a.name='action'; a.value='delete'; f.appendChild(a);
    const hid = document.createElement('input'); hid.name='id'; hid.value=id; f.appendChild(hid);
    document.body.appendChild(f); f.submit();
  }

  function doDeleteRelation(src, tgt, type) {
    if (!confirm('Eliminare relazione?')) return;
    const f = document.createElement('form');
    f.method='post'; f.style.display='none';
    const en = document.createElement('input'); en.name='entity'; en.value='relation'; f.appendChild(en);
    const ac = document.createElement('input'); ac.name='action'; ac.value='delete'; f.appendChild(ac);
    const s = document.createElement('input'); s.name='source'; s.value=src; f.appendChild(s);
    const t = document.createElement('input'); t.name='target'; t.value=tgt; f.appendChild(t);
    const ty = document.createElement('input'); ty.name='type'; ty.value=type; f.appendChild(ty);
    document.body.appendChild(f); f.submit();
  }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
