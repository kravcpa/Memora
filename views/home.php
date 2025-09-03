<?php require __DIR__.'/layout.php'; ?>
<?php startDB('content'); echo $_SESSION['email']; ?>
  <h1>Memora (plain PHP)</h1>
  <form method="POST" action="/public/register">
    <input name="email" placeholder="email">
    <input name="username" placeholder="username">
    <input type="password" name="password" placeholder="password">
    <button>Register</button>
  </form>
  <form method="POST" action="/public/login">
    <input name="login" placeholder="email or username">
    <input type="password" name="password" placeholder="password">
    <button>Login</button>
  </form>
<?php endDB(); ?>
