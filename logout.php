  <?php
  require_once 'freshfold_config.php';
  User::logout();
  header('Location: login_page.php');
  exit();