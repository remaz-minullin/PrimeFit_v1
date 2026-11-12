<?php

if (isset($_SESSION['user_id'])) {
    header('Location: index.php?page=profile');
    exit;
}

$errors = [];
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $oldEmail = $email;

    // Валидация
    if ($email === '') {
        $errors['email'] = 'Введите email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный email';
    }

    if ($password === '') {
        $errors['password'] = 'Введите пароль';
    } elseif (mb_strlen($password) < 6) {
        $errors['password'] = 'Пароль минимум 6 символов';
    }

    if ($confirmPassword === '') {
        $errors['confirm_password'] = 'Повторите пароль';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Пароли не совпадают';
    }

    if (empty($errors['email'])) {
        $stmt = $database->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Этот email уже зарегистрирован';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $database->prepare('
            INSERT INTO users (email, password_hash)
            VALUES (?, ?)
        ');
        $stmt->execute([$email, $hash]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $database->lastInsertId();

        header('Location: index.php?page=profile');
        exit;
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
                    <a href="index.php?page=auth" class="tab">Вход</a>
                    <a href="index.php?page=reg" class="tab active">Регистрация</a>
                </div>

                <div class="line"></div>

                <?php if (!empty($errors)): ?>
                    <div class="alert-error">Проверьте поля формы</div>
                <?php endif; ?>

                <form method="POST" action="index.php?page=reg" novalidate>
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

                    <div class="input-group">
                        <label for="confirm_password">Повторите пароль</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                            placeholder="Повторите пароль" required>
                        <?php if (!empty($errors['confirm_password'])): ?>
                            <small class="error">
                                <?= htmlspecialchars($errors['confirm_password']) ?>
                            </small>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="register-btn">Зарегистрироваться</button>
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