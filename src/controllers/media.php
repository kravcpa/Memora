<?php
function searchMedia(PDO $pdo): void {
  $q = trim((string)($_GET['q'] ?? ''));
  $genre = isset($_GET['genre_id']) ? (int)$_GET['genre_id'] : null;
  $year  = isset($_GET['year']) ? (int)$_GET['year'] : null;
  $sql = 'SELECT DISTINCT m.MediaId,m.Title,m.PublishingDate,mt.Type AS MediaType
          FROM `Media` m
          LEFT JOIN `MediaType` mt ON mt.MediaTypeId=m.MediaTypeId
          LEFT JOIN `IsOfGenre` ig ON ig.MediaId=m.MediaId
          LEFT JOIN `Genre` g ON g.GenreId=ig.GenreId
          WHERE 1=1';
  $params=[];
  if ($q!==''){ $sql.=' AND (m.Title LIKE ? )'; $params[]='%'.$q.'%'; }
  if ($genre){ $sql.=' AND g.GenreId = ?'; $params[]=$genre; }
  if ($year){ $sql.=' AND YEAR(m.PublishingDate) = ?'; $params[]=$year; }
  $sql.=' ORDER BY m.Title LIMIT 50';
  $stmt=$pdo->prepare($sql); $stmt->execute($params);
  header('Content-Type: application/json'); echo json_encode($stmt->fetchAll());
}

function mediaDetail(PDO $pdo, int $id): void {
  $d=$pdo->prepare('SELECT m.*, mt.Type AS MediaType, c.Name AS Creator, p.Name AS Platform, ps.Type AS PubStatus
                    FROM `Media` m
                    LEFT JOIN `MediaType` mt ON mt.MediaTypeId=m.MediaTypeId
                    LEFT JOIN `Creator` c ON c.CreatorId=m.CreatorId
                    LEFT JOIN `Platform` p ON p.PlatformId=m.PlatformId
                    LEFT JOIN `PublishingStatus` ps ON ps.PubStatusId=m.PubStatusId
                    WHERE m.MediaId=?');
  $d->execute([$id]); $media=$d->fetch(); if(!$media){ http_response_code(404); echo 'Not found'; return; }

  $g=$pdo->prepare('SELECT g.GenreId,g.Name FROM `IsOfGenre` ig JOIN `Genre` g ON g.GenreId=ig.GenreId WHERE ig.MediaId=? ORDER BY g.Name');
  $g->execute([$id]);

  header('Content-Type: application/json');
  echo json_encode(['media'=>$media,'genres'=>$g->fetchAll()]);
}
