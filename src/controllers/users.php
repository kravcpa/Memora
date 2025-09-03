<?php
function userProfile(PDO $pdo, string $email): void {
  $stmt=$pdo->prepare('SELECT u.Email,u.Username,u.IsBlocked,u.IsAdmin,u.ImageId,i.URL AS AvatarURL
                       FROM `User` u LEFT JOIN `Image` i ON i.ImageId=u.ImageId WHERE u.Email=?');
  $stmt->execute([$email]);
  $user=$stmt->fetch(); if(!$user){ http_response_code(404); echo 'User not found'; return; }

  $followers = $pdo->prepare('SELECT COUNT(*) c FROM `Follow` WHERE FollowingId=?'); $followers->execute([$email]);
  $following = $pdo->prepare('SELECT COUNT(*) c FROM `Follow` WHERE FollowerId=?');  $following->execute([$email]);

  header('Content-Type: application/json');
  echo json_encode(['user'=>$user,'followers'=>$followers->fetch()['c'],'following'=>$following->fetch()['c']]);
}

function changeUsername(PDO $pdo): void {
  if (!isset($_SESSION['email'])) { http_response_code(401); echo 'Login required'; return; }
  $new = trim((string)($_POST['username'] ?? ''));
  if ($new==='') { http_response_code(400); echo 'Bad username'; return; }
  $stmt=$pdo->prepare('UPDATE `User` SET Username=? WHERE Email=?');
  $stmt->execute([$new, $_SESSION['email']]);
  echo 'OK';
}

function changePassword(PDO $pdo): void {
  if (!isset($_SESSION['email'])) { http_response_code(401); echo 'Login required'; return; }
  $pwd = (string)($_POST['password'] ?? '');
  if (strlen($pwd)<8) { http_response_code(400); echo 'Weak password'; return; }
  $hash = password_hash($pwd, PASSWORD_DEFAULT);
  $stmt=$pdo->prepare('UPDATE `User` SET Password=? WHERE Email=?');
  $stmt->execute([$hash, $_SESSION['email']]);
  echo 'OK';
}
