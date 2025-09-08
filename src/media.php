<?php
// SRC/media.php (versione corretta)
session_start();
require_once __DIR__ . "/../Config/config.php";
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}
include "navbar.php";

$email = $_SESSION['email'];
$mediaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$mediaId) {
    header("Location: error.php?msg=" . urlencode("Media non valido."));
    exit();
}

/* -------------------------
   1) Carico dati media (prepared + bind_result)
   ------------------------- */
$mstmt = $conn->prepare("
    SELECT m.Id, m.Title, m.Description, m.PublishingDate,
           mt.Type AS MediaType, c.name AS CreatorName,
           p.Name AS PlatformName, ps.Type AS PubStatus,
           m.CreatorId, m.PlatformId, m.MediaTypeId, m.PubStatusId
    FROM media m
    JOIN mediatype mt ON m.MediaTypeId = mt.Id
    JOIN creator c ON m.CreatorId = c.id
    JOIN platform p ON m.PlatformId = p.Id
    LEFT JOIN publishingstatus ps ON m.PubStatusId = ps.Id
    WHERE m.Id = ?
");
$mstmt->bind_param("i", $mediaId);
$mstmt->execute();
$mstmt->bind_result($m_Id, $m_Title, $m_Description, $m_PublishingDate, $m_MediaType, $m_CreatorName, $m_PlatformName, $m_PubStatus, $m_CreatorId, $m_PlatformId, $m_MediaTypeId, $m_PubStatusId);
if (!$mstmt->fetch()) {
    $mstmt->close();
    header("Location: error.php?msg=" . urlencode("Media non trovato."));
    exit();
}
$mstmt->close();

/* -------------------------
   2) Aggregati: count bookmarks, avg rating, total rewatches
      uso prepared + bind_result per evitare get_result()
   ------------------------- */
$aggStmt = $conn->prepare("
    SELECT COUNT(b.UserEmail) AS cntBookmarks,
           AVG(b.Rating) AS avgRating,
           SUM(b.Rewatch) AS totalRewatches
    FROM bookmark b
    WHERE b.MediaId = ?
");
$aggStmt->bind_param("i", $mediaId);
$aggStmt->execute();
$aggStmt->bind_result($agg_cntBookmarks, $agg_avgRating, $agg_totalRewatches);
$aggStmt->fetch();
$aggStmt->close();

$bookmarkCount = intval($agg_cntBookmarks ?? 0);
$avgRating = ($bookmarkCount > 0 && $agg_avgRating !== null) ? number_format((float)$agg_avgRating, 2) : 'N/A';
$totalRewatches = intval($agg_totalRewatches ?? 0);

/* -------------------------
   3) Bookmark utente (se esiste)
   ------------------------- */
$bkStmt = $conn->prepare("SELECT Progression, Rating, Note, Rewatch, StatusId FROM bookmark WHERE UserEmail = ? AND MediaId = ?");
$bkStmt->bind_param("si", $email, $mediaId);
$bkStmt->execute();
$bkStmt->bind_result($bk_Progression, $bk_Rating, $bk_Note, $bk_Rewatch, $bk_StatusId);
$userBookmark = null;
if ($bkStmt->fetch()) {
    $userBookmark = [
        'Progression' => $bk_Progression,
        'Rating' => $bk_Rating,
        'Note' => $bk_Note,
        'Rewatch' => $bk_Rewatch,
        'StatusId' => $bk_StatusId
    ];
}
$bkStmt->close();

/* -------------------------
   4) Lista status (si usa query semplice)
   ------------------------- */
$statusRes = $conn->query("SELECT Id, Type FROM status ORDER BY Id");
$statuses = $statusRes ? $statusRes->fetch_all(MYSQLI_ASSOC) : [];

/* -------------------------
   5) Gestione POST (inserimento/aggiornamento/cancellazione bookmark + logging)
   ------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ricarica stato precedente (uscito sopra), ma rileggo per sicurezza
    $prev = null;
    $check = $conn->prepare("SELECT Progression, Rating, Rewatch, StatusId FROM bookmark WHERE UserEmail = ? AND MediaId = ?");
    $check->bind_param("si", $email, $mediaId);
    $check->execute();
    $check->bind_result($p_Progression, $p_Rating, $p_Rewatch, $p_StatusId);
    if ($check->fetch()) {
        $prev = ['Progression' => $p_Progression, 'Rating' => $p_Rating, 'Rewatch' => $p_Rewatch, 'StatusId' => $p_StatusId];
    }
    $check->close();

    // cancellazione bookmark
    if (isset($_POST['delete_bookmark'])) {
        $del = $conn->prepare("DELETE FROM bookmark WHERE UserEmail = ? AND MediaId = ?");
        $del->bind_param("si", $email, $mediaId);
        $del->execute();
        $del->close();
        header("Location: media.php?id={$mediaId}&deleted=1");
        exit();
    }

    // valori dal form (sanitizzazione minima)
    $progress = isset($_POST['progression']) && $_POST['progression'] !== '' ? intval($_POST['progression']) : 0;
    $rating   = (isset($_POST['rating']) && $_POST['rating'] !== '') ? intval($_POST['rating']) : null;
    $note     = isset($_POST['note']) ? trim($_POST['note']) : null;
    $rewatch  = isset($_POST['rewatch']) && $_POST['rewatch'] !== '' ? intval($_POST['rewatch']) : 0;
    $statusId = isset($_POST['status_id']) ? intval($_POST['status_id']) : null;

    // verifica status
    $chk = $conn->prepare("SELECT Type FROM status WHERE Id = ?");
    $chk->bind_param("i", $statusId);
    $chk->execute();
    $chk->bind_result($chk_Type);
    if (!$chk->fetch()) {
        $chk->close();
        header("Location: error.php?msg=" . urlencode("Status selezionato non valido."));
        exit();
    }
    $newStatusType = $chk_Type;
    $chk->close();

    // decide insert or update
    if ($prev) {
        $u = $conn->prepare("
            UPDATE bookmark
            SET Progression = ?, Rating = ?, Note = ?, Rewatch = ?, StatusId = ?
            WHERE UserEmail = ? AND MediaId = ?
        ");
        $u->bind_param("iisiisi", $progress, $rating, $note, $rewatch, $statusId, $email, $mediaId);
        $u->execute();
        $u->close();
    } else {
        $i = $conn->prepare("
            INSERT INTO bookmark (UserEmail, MediaId, Progression, Rating, Note, Rewatch, StatusId)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $i->bind_param("siiisii", $email, $mediaId, $progress, $rating, $note, $rewatch, $statusId);
        $i->execute();
        $i->close();
    }

    // LOGGING ATTIVITA' (regole)
    $oldStatusType = null;
    $oldProg = 0;
    $oldRating = null;
    if ($prev) {
        $oldProg = intval($prev['Progression'] ?? 0);
        $oldRating = isset($prev['Rating']) ? $prev['Rating'] : null;
        if (!empty($prev['StatusId'])) {
            $s = $conn->prepare("SELECT Type FROM status WHERE Id = ?");
            $s->bind_param("i", $prev['StatusId']);
            $s->execute();
            $s->bind_result($tmpType);
            if ($s->fetch()) $oldStatusType = $tmpType;
            $s->close();
        }
    }

    $toInsert = [];
    if ($rating !== null && ($oldRating === null || intval($oldRating) !== intval($rating))) $toInsert[] = 'Voted a Media';
    if (intval($progress) > intval($oldProg)) $toInsert[] = 'Progressed a Media';
    if (strtolower($newStatusType) === 'completed' && strtolower((string)$oldStatusType) !== 'completed') $toInsert[] = 'Completed a Media';

    foreach ($toInsert as $actType) {
        $ins = $conn->prepare("INSERT INTO activity (UserEmail, MediaId, Type) VALUES (?, ?, ?)");
        $ins->bind_param("sis", $email, $mediaId, $actType);
        $ins->execute();
        $ins->close();
    }

    // redirect PRG
    header("Location: media.php?id={$mediaId}&saved=1");
    exit();
}

/* -------------------------
   6) Relazioni (prepared + bind_result)
   ------------------------- */
$relations = [];
$rstmt = $conn->prepare("
    SELECT r.SourceId, r.TargetId, r.Type,
           ms.Title AS SourceTitle, mt.Title AS TargetTitle
    FROM relation r
    LEFT JOIN media ms ON r.SourceId = ms.Id
    LEFT JOIN media mt ON r.TargetId = mt.Id
    WHERE r.SourceId = ? OR r.TargetId = ?
");
$rstmt->bind_param("ii", $mediaId, $mediaId);
$rstmt->execute();
$rstmt->bind_result($rel_SourceId, $rel_TargetId, $rel_Type, $rel_SourceTitle, $rel_TargetTitle);
while ($rstmt->fetch()) {
    $relations[] = [
        'SourceId' => $rel_SourceId,
        'TargetId' => $rel_TargetId,
        'Type' => $rel_Type,
        'SourceTitle' => $rel_SourceTitle,
        'TargetTitle' => $rel_TargetTitle
    ];
}
$rstmt->close();

/* -------------------------
   7) Aggiorno dati di pagina dopo possibile POST (rileggerli)
   ------------------------- */
/* rileggo bookmark utente */
$bkStmt2 = $conn->prepare("SELECT Progression, Rating, Note, Rewatch, StatusId FROM bookmark WHERE UserEmail = ? AND MediaId = ?");
$bkStmt2->bind_param("si", $email, $mediaId);
$bkStmt2->execute();
$bkStmt2->bind_result($bk_Progression, $bk_Rating, $bk_Note, $bk_Rewatch, $bk_StatusId);
$userBookmark = null;
if ($bkStmt2->fetch()) {
    $userBookmark = [
        'Progression' => $bk_Progression,
        'Rating' => $bk_Rating,
        'Note' => $bk_Note,
        'Rewatch' => $bk_Rewatch,
        'StatusId' => $bk_StatusId
    ];
}
$bkStmt2->close();

/* rileggo aggregati */
$aggStmt2 = $conn->prepare("
    SELECT COUNT(b.UserEmail) AS cntBookmarks,
           AVG(b.Rating) AS avgRating,
           SUM(b.Rewatch) AS totalRewatches
    FROM bookmark b
    WHERE b.MediaId = ?
");
$aggStmt2->bind_param("i", $mediaId);
$aggStmt2->execute();
$aggStmt2->bind_result($agg_cntBookmarks, $agg_avgRating, $agg_totalRewatches);
$aggStmt2->fetch();
$aggStmt2->close();

$bookmarkCount = intval($agg_cntBookmarks ?? 0);
$avgRating = ($bookmarkCount > 0 && $agg_avgRating !== null) ? number_format((float)$agg_avgRating, 2) : 'N/A';
$totalRewatches = intval($agg_totalRewatches ?? 0);

/* -------------------------
   HTML output
   ------------------------- */
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($m_Title); ?> — Memora</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo CSS_PATH; ?>">
</head>
<body class="bg-light">
<div class="container py-4">
    <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success">Bookmark salvato.</div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-warning">Bookmark cancellato.</div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h2><?php echo htmlspecialchars($m_Title); ?></h2>
            <p class="mb-1"><strong>Tipo:</strong> <?php echo htmlspecialchars($m_MediaType); ?></p>
            <p class="mb-1"><strong>Creatore:</strong> <?php echo htmlspecialchars($m_CreatorName); ?></p>
            <p class="mb-1"><strong>Piattaforma:</strong> <?php echo htmlspecialchars($m_PlatformName); ?></p>
            <p class="mb-1"><strong>Publishing status:</strong> <?php echo htmlspecialchars($m_PubStatus ?? '—'); ?></p>
            <p class="mb-1"><strong>Publishing date:</strong> <?php echo htmlspecialchars($m_PublishingDate); ?></p>
            <p class="mb-2"><?php echo nl2br(htmlspecialchars($m_Description)); ?></p>

            <div class="row">
                <div class="col-md-4"><strong>Average score:</strong> <?php echo $avgRating; ?></div>
                <div class="col-md-4"><strong>Total Rewatches:</strong> <?php echo $totalRewatches; ?></div>
                <div class="col-md-4"><strong>Total Bookmarks:</strong> <?php echo $bookmarkCount; ?></div>
            </div>
        </div>
    </div>

    <!-- Form bookmark utente -->
    <div class="card mb-4">
        <div class="card-body">
            <h4>Il tuo bookmark</h4>
            <form method="post" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status_id" class="form-select" required>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?php echo $s['Id']; ?>" <?php if($userBookmark && intval($userBookmark['StatusId'])===intval($s['Id'])) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($s['Type']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Progressione (episodi/capitoli)</label>
                    <input type="number" name="progression" min="0" class="form-control" value="<?php echo $userBookmark ? intval($userBookmark['Progression']) : 0; ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Voto (0-10)</label>
                    <input type="number" name="rating" class="form-control" min="0" max="10" value="<?php echo ($userBookmark && $userBookmark['Rating'] !== null) ? intval($userBookmark['Rating']) : ''; ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Rewatches</label>
                    <input type="number" name="rewatch" class="form-control" min="0" value="<?php echo $userBookmark ? intval($userBookmark['Rewatch']) : 0; ?>">
                </div>

                <div class="col-md-12">
                    <label class="form-label">Note</label>
                    <textarea name="note" class="form-control" rows="3"><?php echo $userBookmark ? htmlspecialchars($userBookmark['Note']) : ''; ?></textarea>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary">Salva</button>
                    <?php if ($userBookmark): ?>
                        <button type="submit" name="delete_bookmark" class="btn btn-danger" onclick="return confirm('Sei sicuro di voler cancellare il bookmark?')">Elimina Bookmark</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Relazioni -->
    <div class="card mb-4">
        <div class="card-body">
            <h4>Relazioni</h4>
            <?php if (empty($relations)): ?>
                <p class="text-muted">Nessuna relazione trovata per questo media.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($relations as $r):
                        if (intval($r['SourceId']) === $mediaId) {
                            $label = htmlspecialchars($r['Type']) . " → ";
                            $otherTitle = $r['TargetTitle'];
                            $otherId = $r['TargetId'];
                        } else {
                            $label = " ← " . htmlspecialchars($r['Type']);
                            $otherTitle = $r['SourceTitle'];
                            $otherId = $r['SourceId'];
                        }
                        ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <?php echo $label; ?>
                                <a href="media.php?id=<?php echo intval($otherId); ?>"><?php echo htmlspecialchars($otherTitle); ?></a>
                            </div>
                            <small class="text-muted">Type: <?php echo htmlspecialchars($r['Type']); ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
