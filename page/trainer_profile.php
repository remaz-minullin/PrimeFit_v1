<?php
/** @var PDO $database */

// Проверка, что зашел именно тренер
if (empty($USER)) {
    header('Location: index.php?page=auth');
    exit;
}

if (($USER['role'] ?? '') !== 'trainer') {
    header('Location: index.php?page=profile'); // Обычных юзеров выкидываем в их профиль
    exit;
}

$userId = (int) $USER['id'];
$errors = [];
$success = false;

// Получаем ID тренера из таблицы trainers по ID юзера
$stmt = $database->prepare('SELECT id, name, direction FROM trainers WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$trainerData = $stmt->fetch();

if (!$trainerData) {
    // Если админ создал юзера с ролью trainer, но не привязал его к карточке тренера
    die('Ошибка: ваш аккаунт не привязан к карточке тренера. Обратитесь к администратору.');
}

$trainerId = (int) $trainerData['id'];

/* Отмена записи тренером */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_booking_by_trainer') {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);

    if ($bookingId > 0) {
        //  Получаем информацию о записи 
        $stmt = $database->prepare('
            SELECT b.user_id, ts.session_date, t.name AS trainer_name
            FROM bookings b
            JOIN trainer_sessions ts ON ts.id = b.session_id
            JOIN trainers t ON t.id = ts.trainer_id
            WHERE b.id = ? AND ts.trainer_id = ? AND b.status = "booked"
        ');
        $stmt->execute([$bookingId, $trainerId]);
        $bookingInfo = $stmt->fetch();

        if ($bookingInfo) {
            
            $stmt = $database->prepare('UPDATE bookings SET status = "cancelled" WHERE id = ?');
            $stmt->execute([$bookingId]);

            //уведомление для юзера
            $dateStr = date('d.m.Y H:i', strtotime($bookingInfo['session_date']));
            $message = "Тренер {$bookingInfo['trainer_name']} отменил вашу запись на {$dateStr}.";

            $stmt = $database->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
            $stmt->execute([$bookingInfo['user_id'], $message]);
        }
    }
   
    header('Location: index.php?page=trainer_profile&tab=bookings');
    exit;
}

/* Обновление профиля тренера (Имя и Направление) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_trainer_profile') {
    $name = trim($_POST['name'] ?? '');
    $direction = trim($_POST['direction'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '')
        $errors['name'] = 'Введите имя';
    if ($direction === '')
        $errors['direction'] = 'Введите направление';
    if ($phone === '')
        $errors['phone'] = 'Введите телефон';

    if (!$errors) {
        // Обновляем карточку тренера
        $stmt = $database->prepare('UPDATE trainers SET name = ?, direction = ? WHERE id = ?');
        $stmt->execute([$name, $direction, $trainerId]);

        // Обновляем данные юзера (телефон)
        $stmt = $database->prepare('UPDATE users SET phone = ? WHERE id = ?');
        $stmt->execute([$phone, $userId]);

        $success = true;

        // Обновляем $USER и $trainerData
        $stmt = $database->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $USER = $stmt->fetch();

        $stmt = $database->prepare('SELECT id, name, direction FROM trainers WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $trainerData = $stmt->fetch();
    }
}

/* Получение записей клиентов к  тренеру */
$stmt = $database->prepare('
    SELECT 
        b.id AS booking_id, 
        b.status, 
        ts.session_date,
        u.name AS client_name, 
        u.phone AS client_phone, 
        u.email AS client_email
    FROM bookings b
    JOIN trainer_sessions ts ON ts.id = b.session_id
    JOIN users u ON u.id = b.user_id
    WHERE ts.trainer_id = ? AND ts.session_date >= NOW()
    ORDER BY ts.session_date ASC
');

$stmt->execute([$trainerId]);
$bookings = $stmt->fetchAll();

$tab = $_GET['tab'] ?? 'bookings';
if (!in_array($tab, ['data', 'bookings'], true)) {
    $tab = 'bookings';
}

$form = [
    'name' => $trainerData['name'] ?? '',
    'direction' => $trainerData['direction'] ?? '',
    'phone' => $USER['phone'] ?? '',
];

$displayName = $trainerData['name'] ?: $USER['email'];
?>

<main class="container">
    <h1 class="profile-h1">КАБИНЕТ <span>ТРЕНЕРА</span></h1>

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
                <a href="index.php?page=trainer_profile&tab=bookings"
                    class="but-1 <?= $tab === 'bookings' ? 'is-active' : '' ?>">Мои клиенты</a>
                <a href="index.php?page=trainer_profile&tab=data"
                    class="but-1 <?= $tab === 'data' ? 'is-active' : '' ?>">Мои данные</a>
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

                <form method="POST" action="index.php?page=trainer_profile&tab=data" class="user-form" novalidate>
                    <input type="hidden" name="action" value="update_trainer_profile">

                    <div class="form__block">
                        <div class="input-group">
                            <label for="name">Имя и Фамилия</label>
                            <input type="text" id="name" name="name" value="<?= htmlspecialchars($form['name']) ?>">
                            <?php if (!empty($errors['name'])): ?><small
                                    class="error"><?= $errors['name'] ?></small><?php endif; ?>
                        </div>
                        <div class="input-group">
                            <label for="direction">Направление</label>
                            <input type="text" id="direction" name="direction"
                                value="<?= htmlspecialchars($form['direction']) ?>">
                            <?php if (!empty($errors['direction'])): ?><small
                                    class="error"><?= $errors['direction'] ?></small><?php endif; ?>
                        </div>
                    </div>

                    <div class="form__block">
                        <div class="input-group">
                            <label for="phone">Телефон</label>
                            <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($form['phone']) ?>">
                            <?php if (!empty($errors['phone'])): ?><small
                                    class="error"><?= $errors['phone'] ?></small><?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" class="register-btn">Сохранить изменения</button>
                </form>

            <?php elseif ($tab === 'bookings'): ?>

                <?php if (empty($bookings)): ?>
                    <p class="profile-empty">У вас пока нет записей на ближайшее время.</p>
                <?php else: ?>
                    <section class="records-section" style="padding:0;">
                        <div class="records-table-wrapper">
                            <table class="records-table">
                                <thead>
                                    <tr>
                                        <th>Дата и время</th>
                                        <th>Клиент</th>
                                        <th>Телефон</th>
                                        <th>Статус</th>
                                        <th>Действие</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $b): ?>
                                        <tr>
                                            <td><strong><?= date('d.m.Y H:i', strtotime($b['session_date'])) ?></strong></td>
                                            <td><?= htmlspecialchars($b['client_name'] ?? 'Без имени') ?></td>
                                            <td><?= htmlspecialchars($b['client_phone'] ?? '—') ?></td>
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
                                                    <form method="POST" action="index.php?page=trainer_profile&tab=bookings"
                                                        style="margin:0;">
                                                        <input type="hidden" name="action" value="cancel_booking_by_trainer">
                                                        <input type="hidden" name="booking_id" value="<?= (int) $b['booking_id'] ?>">
                                                        <button type="submit" class="btn-cancel"
                                                            onclick="return confirm('Вы уверены, что хотите отменить запись клиента?');">Отменить</button>
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
            if (digits.startsWith('8')) digits = '7' + digits.slice(1);
            if (digits.length > 0 && !digits.startsWith('7')) digits = '7' + digits;
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