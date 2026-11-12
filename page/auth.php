<?php

$errors = [];
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $oldEmail = $email;

    // 1. Валидация
    if ($email === '') {
        $errors['email'] = 'Введите email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный email';
    }

    if ($password === '') {
        $errors['password'] = 'Введите пароль';
    }

    // 2. Проверка учётки
    if (!$errors) {
        $stmt = $database->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors['form'] = 'Неверный email или пароль';
        } else {
         
            session_regenerate_id(true);   // защита от session fixation
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['name'];

            header('Location: index.php?page=profile');
            exit;
        }
    }
}
?>

<body>
    <div class="container">
        <div class="auth-container">
            <div class="auth-box">

                <h1>Добро пожаловать</h1>
                <p class="subtitle">Войдите или создайте аккаунт</p>

                <div class="tabs">
                    <a href="index.php?page=auth" class="tab active">Вход</a>
                    <a href="index.php?page=reg" class="tab">Регистрация</a>
                </div>

                <div class="line"></div>

                <?php if (!empty($errors['form'])): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars($errors['form']) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="index.php?page=auth" novalidate>
                    <div class="input-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="Email"
                            value="<?= htmlspecialchars($oldEmail) ?>" required>
                        <?php if (!empty($errors['email'])): ?>
                            <small class="error">
                                <?= htmlspecialchars($errors['email']) ?>
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="input-group">
                        <label for="password">Введите пароль</label>
                        <input type="password" id="password" name="password" placeholder="Введите пароль" required>
                        <?php if (!empty($errors['password'])): ?>
                            <small class="error">
                                <?= htmlspecialchars($errors['password']) ?>
                            </small>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="register-btn">Войти</button>
                </form>

                <p class="agreement">
                    Нажимая на кнопку, вы соглашаетесь с
                    <a href="#">политикой конфиденциальности</a>
                    и обработкой персональных данных (152-ФЗ)
                </p>

            </div>
        </div>
    </div>

</body>