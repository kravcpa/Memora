<?php
function register(PDO $pdo): void {
  $email = filter_input(INPUT_POST,'email',FILTER_VALIDATE_EMAIL);
  $username = trim((string)($_POST['username'] ?? ''));
  $password = (string)($_POST['password'] ?? '');
  if (!$email || $username==='' || strlen($password)<8) { http_response_code(400); echo 'Invalid input'; return; }
  $hash = password_hash($password, PASSWORD_DEFAULT);
  $stmt = $pdo->prepare('INSERT INTO `User` (Email, Username, Password, IsBlocked, IsAdmin, ImageId) VALUES (?,?,?,?,?,NULL)');
  try {
    $stmt->execute([$email,$username,$hash,0,0]);
  } catch (PDOException $e) { http_response_code(409); echo 'Email/username exists'; return; }
  $_SESSION['email']=$email;
  header('Location: /memora-php/'); // adjust base
}

function login(PDO $pdo): void {
  $login = trim((string)($_POST['login'] ?? ''));
  $password = (string)($_POST['password'] ?? '');
  $stmt=$pdo->prepare('SELECT Email,Username,Password,IsBlocked FROM `User` WHERE Email=? OR Username=?');
  $stmt->execute([$login,$login]);
  $u=$stmt->fetch();
  if(!$u || $u['IsBlocked'] || !password_verify($password,$u['Password'])) { http_response_code(401); echo 'Invalid creds'; return; }
  $_SESSION['email']=$u['Email'];
  header('Location: /memora-php/');
}
function logout(): void { session_destroy(); header('Location: /memora-php/'); }
