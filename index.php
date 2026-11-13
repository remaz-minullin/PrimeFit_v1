<?php
    include('database/connection.php');
    include('php/session.php');
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prime Fit</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include('php/header.php') ?>
    <?php
   if (isset($_GET['page'])) {
    $page = $_GET['page'];
    
    if ($page == 'reg') { include('page/reg.php'); }
    if ($page == 'auth') { include('page/auth.php'); }
    if ($page == 'profile') { include('page/profile.php'); }
    if ($page == 'abonements') { include('page/abonements.php'); }
    if ($page == 'trainers') { include('page/trainers.php'); }
    
    // Админка
    if ($page == 'admin') { include('page/admin.php'); }
    if ($page == 'admin_plan_add') { include('page/admin_plan_add.php'); }
    if ($page == 'admin_plan_edit') { include('page/admin_plan_edit.php'); }
    if ($page == 'admin_trainer_add') { include('page/admin_trainer_add.php'); }
    if ($page == 'admin_trainer_edit') { include('page/admin_trainer_edit.php'); }
    if ($page == 'trainer_profile') { include('page/trainer_profile.php'); }
    
} else {
    include('php/start.php');
}
    ?>
    <?php include('php/footer.php') ?>
</body>
</html>