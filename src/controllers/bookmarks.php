<?php
function upsertBookmark(PDO $pdo): void {
  if (!isset($_SESSION['email'])) { http_response_code(401); echo 'Login required'; return; }
  $mediaId = (int)($_POST['media_id'] ?? 0);
  $progress = (int)($_POST['progression'] ?? 0);
  $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : null;
  $note = (string)($_POST['note'] ?? '');
  $rewatch = (int)($_POST['rewatch'] ?? 0);
  $statusId = (int)($_POST['status_id'] ?? 0);
  $sql='INSERT INTO `Bookmark` (UserEmail,MediaId,Progression,Rating,Note,Rewatch,StatusId)
        VALUES (?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE Progression=VALUES(Progression), Rating=VALUES(Rating),
        Note=VALUES(Note), Rewatch=VALUES(Rewatch), StatusId=VALUES(StatusId)';
  $stmt=$pdo->prepare($sql);
  $stmt->execute([$_SESSION['email'],$mediaId,$progress,$rating,$note,$rewatch,$statusId]);
  echo 'OK';
}
function deleteBookmark(PDO $pdo): void {
  if (!isset($_SESSION['email'])) { http_response_code(401); echo 'Login required'; return; }
  parse_str(file_get_contents('php://input'), $del);
  $mediaId = (int)($del['media_id'] ?? 0);
  $stmt=$pdo->prepare('DELETE FROM `Bookmark` WHERE UserEmail=? AND MediaId=?');
  $stmt->execute([$_SESSION['email'],$mediaId]);
  echo 'OK';
}
