<?php
require __DIR__ . '/controllers/auth.php';
require __DIR__ . '/controllers/users.php';
require __DIR__ . '/controllers/media.php';
require __DIR__ . '/controllers/bookmarks.php';

function route(string $route, string $method, PDO $pdo): void {
  // Auth
  if ($route === '/' && $method==='GET') { require __DIR__.'/../views/home.php'; return; }
  if ($route === '/register' && $method==='POST') { register($pdo); return; }
  if ($route === '/login' && $method==='POST') { login($pdo); return; }
  if ($route === '/logout' && $method==='POST') { logout(); return; }

  // Users
  if (preg_match('#^/users/([^/]+)$#',$route,$m) && $method==='GET') { userProfile($pdo, urldecode($m[1])); return; }
  if ($route === '/users/username' && $method==='POST') { changeUsername($pdo); return; }
  if ($route === '/users/password' && $method==='POST') { changePassword($pdo); return; }

  // Media
  if ($route === '/search' && $method==='GET') { searchMedia($pdo); return; }
  if (preg_match('#^/media/(\d+)$#',$route,$m) && $method==='GET') { mediaDetail($pdo,(int)$m[1]); return; }

  // Bookmarks
  if ($route === '/bookmarks' && $method==='POST') { upsertBookmark($pdo); return; }
  if ($route === '/bookmarks' && $method==='DELETE') { deleteBookmark($pdo); return; }

  http_response_code(404);
  echo 'Not found';
}
