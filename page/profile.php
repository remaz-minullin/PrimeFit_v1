<?php

if (empty($USER)) {
    header('Location: index.php?page=auth');
    exit;
}

$userId = (int) $USER['id'];
$errors = [];
$success = false;

/* Удаление прошедших записей */
$stmt = $database->prepare('
    DELETE b FROM bookings b
    JOIN trainer_sessions ts ON ts.id = b.session_id
    WHERE b.user_id = ? AND ts.session_date < NOW()
');
$stmt->execute([$userId]);

/* обновление профиля */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');

    // Валидация
    if ($name === '') {
        $errors['name'] = 'Введите имя';
    }

    if ($email === '') {
        $errors['email'] = 'Введите почту';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный email';
    } else {

        $stmt = $database->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Этот email уже занят';
        }
    }

    if ($phone === '') {
        $errors['phone'] = 'Введите телефон';
    }

    if ($birth_date === '') {
        $errors['birth_date'] = 'Укажите дату рождения';
    } elseif (!DateTime::createFromFormat('Y-m-d', $birth_date)) {
        $errors['birth_date'] = 'Некорректная дата';
    }

    if (!$errors) {
        $stmt = $database->prepare('
            UPDATE users
            SET name = ?, email = ?, phone = ?, birth_date = ?
            WHERE id = ?
        ');
        $stmt->execute([$name, $email, $phone, $birth_date, $userId]);
        $success = true;

        // Обновляем $USER
        $stmt = $database->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $USER = $stmt->fetch();
    }
}

/* отмена записи */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_booking') {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);

    if ($bookingId > 0) {
        // Обновляем статус только если это запись текущего юзера
        $stmt = $database->prepare('
            UPDATE bookings 
            SET status = "cancelled" 
            WHERE id = ? AND user_id = ? AND status = "booked"
        ');
        $stmt->execute([$bookingId, $userId]);

        if ($stmt->rowCount() > 0) {
            // Перезапрашиваем записи, чтобы обновить таблицу
            $stmt = $database->prepare('
                SELECT b.id, b.status, ts.session_date, t.name AS trainer_name, t.direction
                FROM bookings b
                JOIN trainer_sessions ts ON ts.id = b.session_id
                JOIN trainers t          ON t.id  = ts.trainer_id
                WHERE b.user_id = ?
                ORDER BY ts.session_date DESC
            ');
            $stmt->execute([$userId]);
            $bookings = $stmt->fetchAll();
        }
    }
}

/* абонемент */
$stmt = $database->prepare('
    SELECT um.*, mp.title, mp.price, mp.description, mp.is_premium, mp.features, mp.is_hit
    FROM user_memberships um
    JOIN membership_plans mp ON mp.id = um.plan_id
    WHERE um.user_id = ?
      AND um.status = "active"
      AND um.expires_at >= CURDATE()
    ORDER BY um.expires_at DESC
    LIMIT 1
');
$stmt->execute([$userId]);
$activeMembership = $stmt->fetch() ?: null;

/* записи */
$stmt = $database->prepare('
    SELECT
        b.id, b.status, ts.session_date,
        t.name AS trainer_name, t.direction
    FROM bookings b
    JOIN trainer_sessions ts ON ts.id = b.session_id
    JOIN trainers t          ON t.id  = ts.trainer_id
    WHERE b.user_id = ?
    ORDER BY ts.session_date DESC
');
$stmt->execute([$userId]);
$bookings = $stmt->fetchAll();

/* таб */
$tab = $_GET['tab'] ?? 'data';
if (!in_array($tab, ['data', 'memberships', 'bookings'], true)) {
    $tab = 'data';
}

$form = [
    'name' => $USER['name'] ?? '',
    'email' => $USER['email'] ?? '',
    'phone' => $USER['phone'] ?? '',
    'birth_date' => $USER['birth_date'] ?? '',
];

$displayName = !empty($USER['name']) && $USER['name'] !== $USER['email']
    ? $USER['name']
    : 'Заполните профиль';
?>
<main class="container">
    <h1 class="profile-h1">ЛИЧНЫЙ <span>КАБИНЕТ</span></h1>

    <section class="container user-info__section">
        <div class="left-box">
            <div class="top">
                <div class="user-info__top">
                    <div class="icon"><img src="images/icon.svg" alt=""></div>
                    <div class="name"><?= htmlspecialchars($displayName) ?></div>
                </div>
                <div class="user-info__bottom">
                    <p class="phone"><?= htmlspecialchars($USER['phone'] ?? '— телефон не указан —') ?></p>
                    <p class="email"><?= htmlspecialchars($USER['email']) ?></p>
                </div>
            </div>

            <div class="bottom">
                <a href="index.php?page=profile&tab=data" class="but-1 <?= $tab === 'data' ? 'is-active' : '' ?>">Мои
                    данные</a>
                <a href="index.php?page=profile&tab=memberships"
                    class="but-1 <?= $tab === 'memberships' ? 'is-active' : '' ?>">Мои абонементы</a>
                <a href="index.php?page=profile&tab=bookings"
                    class="but-1 <?= $tab === 'bookings' ? 'is-active' : '' ?>">Мои записи</a>
            </div>
        </div>

        <div class="right-box">

            <?php if ($tab === 'data'): ?>

                <?php if ($success): ?>
                    <div class="alert-success">Данные сохранены</div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert-error">Проверьте поля формы</div>
                <?php endif; ?>

                <form method="POST" action="index.php?page=profile&tab=data" class="user-form" novalidate>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form__block">
                        <div class="input-group">
                            <label for="name">Имя</label>
                            <input type="text" id="name" name="name" value="<?= htmlspecialchars($form['name']) ?>">
                            <?php if (!empty($errors['name'])): ?>
                                <small class="error"><?= htmlspecialchars($errors['name']) ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="input-group">
                            <label for="email">Почта</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($form['email']) ?>">
                            <?php if (!empty($errors['email'])): ?>
                                <small class="error"><?= htmlspecialchars($errors['email']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form__block">
                        <div class="input-group">
                            <label for="phone">Телефон</label>
                            <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($form['phone']) ?>">
                            <?php if (!empty($errors['phone'])): ?>
                                <small class="error"><?= htmlspecialchars($errors['phone']) ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="input-group">
                            <label for="birth_date">Дата рождения</label>
                            <input type="date" id="birth_date" name="birth_date"
                                value="<?= htmlspecialchars($form['birth_date']) ?>">
                            <?php if (!empty($errors['birth_date'])): ?>
                                <small class="error"><?= htmlspecialchars($errors['birth_date']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" class="register-btn">Сохранить изменения</button>
                </form>

            <?php elseif ($tab === 'memberships'): ?>

                <?php if ($activeMembership): ?>
                    <section class="subscription-section" style="padding:0;">
                        <!-- Используем те же классы, что и в каталоге -->
                        <div class="pricing-card <?= !empty($activeMembership['is_premium']) || !empty($activeMembership['is_hit']) ? 'featured' : '' ?>"
                            style="width: 100%; max-width: 370px; margin: 0 auto;">

                            <div class="card-top">
                                <div class="card-name"><?= htmlspecialchars($activeMembership['title']) ?></div>
                                <?php if (!empty($activeMembership['is_premium']) || !empty($activeMembership['is_hit'])): ?>
                                    <span class="hit-label">ХИТ</span>
                                <?php endif; ?>
                            </div>

                            <div class="card-price">
                                <?= number_format((float) $activeMembership['price'], 0, '.', ' ') ?>
                                <span>₽ / мес</span>
                            </div>

                            <p class="card-description">
                                <?= htmlspecialchars($activeMembership['description'] ?? '') ?>
                            </p>

                            <!-- Выводим преимущества из БД -->
                            <ul class="card-features">
                                <?php
                                $features = json_decode($activeMembership['features'] ?? '[]', true);
                                if (!is_array($features))
                                    $features = [];
                                ?>
                                <?php if ($features): ?>
                                    <?php foreach ($features as $feature): ?>
                                        <li><?= htmlspecialchars($feature) ?></li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>

                            <!-- Дата окончания -->
                            <div class="expiration"
                                style="margin-top: auto; padding-top: 22px; border-top: 1px solid rgba(0, 0, 0, 0.1);">
                                <span>Активен до</span>
                                <strong><?= date('d.m.y', strtotime($activeMembership['expires_at'])) ?></strong>
                            </div>

                            <!-- Кнопка смены тарифа -->
                            <?php if (empty($activeMembership['is_premium'])): ?>
                                <a href="index.php?page=abonements" class="btn-upgrade"
                                    style="margin-top: 15px; text-align: center; display: block;">
                                    Перейти на ПРЕМИУМ →
                                </a>
                            <?php endif; ?>

                        </div>
                    </section>
                <?php else: ?>
                    <p class="profile-empty">
                        У вас нет активного абонемента.
                        <a href="index.php?page=abonements" class="link-accent">Выбрать абонемент →</a>
                    </p>
                <?php endif; ?>

            <?php elseif ($tab === 'bookings'): ?>

                <?php if (empty($bookings)): ?>
                    <p class="profile-empty">Записей пока нет.</p>
                <?php else: ?>
                    <section class="records-section" style="padding:0;">
                        <div class="records-table-wrapper">
                            <table class="records-table">
                                <thead>
                                    <tr>
                                        <th>№ записи</th>
                                        <th>Тренер</th>
                                        <th>Направление</th>
                                        <th>Дата занятия</th>
                                        <th>Статус</th>
                                        <th>Действие</th> <!-- НОВАЯ КОЛОНКА -->
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $b): ?>
                                        <tr>
                                            <td><?= (int) $b['id'] ?></td>
                                            <td><?= htmlspecialchars($b['trainer_name']) ?></td>
                                            <td><?= htmlspecialchars($b['direction']) ?></td>
                                            <td><?= date('d.m.Y H:i', strtotime($b['session_date'])) ?></td>
                                            <td>
                                                <?php
                                                $statusMap = [
                                                    'booked' => ['label' => 'Подтверждено', 'class' => 'confirmed'],
                                                    'cancelled' => ['label' => 'Отменено', 'class' => 'waiting'],
                                                    'visited' => ['label' => 'Посещено', 'class' => 'confirmed'],
                                                ];
                                                $s = $statusMap[$b['status']] ?? ['label' => $b['status'], 'class' => ''];
                                                ?>
                                                <span class="status <?= $s['class'] ?>">
                                                    <?= htmlspecialchars($s['label']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($b['status'] === 'booked'): ?>
                                                    <form method="POST" action="index.php?page=profile&tab=bookings" style="margin:0;">
                                                        <input type="hidden" name="action" value="cancel_booking">
                                                        <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                                                        <button type="submit" class="btn-cancel"
                                                            onclick="return confirm('Вы уверены, что хотите отменить запись?');">Отменить</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="color:#555;">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </section>
</main>

<script>
    // Маска для телефона
    const phoneInput = document.getElementById('phone');
    
    if (phoneInput) {
        phoneInput.addEventListener('input', () => {
        
            let digits = phoneInput.value.replace(/\D/g, '');
          
            if (digits.startsWith('8')) {
                digits = '7' + digits.slice(1);
            }
            
            if (digits.length > 0 && !digits.startsWith('7')) {
                digits = '7' + digits;
            }
        
            digits = digits.slice(1, 11);
            
            let formatted = '+7';
            if (digits.length > 0) formatted += ' (' + digits.slice(0, 3);
            if (digits.length >= 4) formatted += ') ' + digits.slice(3, 6);
            if (digits.length >= 7) formatted += '-' + digits.slice(6, 8);
            if (digits.length >= 9) formatted += '-' + digits.slice(8, 10);
            
            phoneInput.value = formatted;
        });
    }
</script>