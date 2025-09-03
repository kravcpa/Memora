<?php require __DIR__.'/layout.php'; ?>
<?php startDB('content'); ?>
  <h1>Memora (plain PHP)</h1>
  <form method="POST" action="/memora-php/register">
    <input name="email" placeholder="email">
    <input name="username" placeholder="username">
    <input type="password" name="password" placeholder="password">
    <button>Register</button>
  </form>
  <form method="POST" action="/memora-php/login">
    <input name="login" placeholder="email or username">
    <input type="password" name="password" placeholder="password">
    <button>Login</button>
  </form>
<?php endDB(); ?>
