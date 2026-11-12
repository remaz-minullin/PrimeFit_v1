<?php
/** @var PDO $database */

$stmt = $database->query('SELECT * FROM trainers ORDER BY id ASC');
$trainers = $stmt->fetchAll();

// Все занятые тренировки
$stmt = $database->query('
    SELECT ts.trainer_id, ts.session_date, ts.max_slots, COUNT(b.id) as booked_count
    FROM trainer_sessions ts
    LEFT JOIN bookings b ON b.session_id = ts.id AND b.status = "booked"
    WHERE ts.session_date >= NOW()
    GROUP BY ts.id
    HAVING booked_count >= ts.max_slots
');
$fullSessions = $stmt->fetchAll();

//  массив для JS
$busySlots = [];
foreach ($fullSessions as $fs) {
    // Формат даты для JS: YYYY-MM-DD
    $dateStr = date('Y-m-d', strtotime($fs['session_date']));
    // Формат времени для JS: HH:MM
    $timeStr = date('H:i', strtotime($fs['session_date']));

    $busySlots[$fs['trainer_id']][$dateStr][] = $timeStr;
}

//  Проверка на премиум
$hasPremium = false;
if (!empty($USER)) {
    $stmt = $database->prepare('
        SELECT um.id
        FROM user_memberships um
        JOIN membership_plans mp ON mp.id = um.plan_id
        WHERE um.user_id = ?
          AND um.status = "active"
          AND um.expires_at >= CURDATE()
          AND mp.is_premium = 1
        LIMIT 1
    ');
    $stmt->execute([(int) $USER['id']]);
    $hasPremium = (bool) $stmt->fetch();
}

// Обработка записи
$success = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book_trainer') {
    if (empty($USER)) {
        $error = 'Войдите в аккаунт, чтобы записаться к тренеру.';
    } elseif (!$hasPremium) {
        $error = 'Для записи к тренеру нужен активный ПРЕМИУМ абонемент.';
    } else {
        $trainerId = (int) ($_POST['trainer_id'] ?? 0);
        $date = $_POST['booking_date'] ?? '';
        $time = $_POST['booking_time'] ?? '';
        $phone = trim($_POST['phone'] ?? '');

        if (!$trainerId || !$date || !$time) {
            $error = 'Заполните все поля.';
        } else {
            // сбор даты и времени 
            $sessionDate = $date . ' ' . $time . ':00';

            if (strtotime($sessionDate) < time()) {
                $error = 'Нельзя записаться на прошедшее время.';
            }
            // 2. проверка времени
            else {
                $hours = (int) date('H', strtotime($sessionDate));
                $minutes = (int) date('i', strtotime($sessionDate));
                $totalMinutes = ($hours * 60) + $minutes;


                if ($totalMinutes < 480 || $totalMinutes > 1200 || ($totalMinutes - 480) % 90 !== 0) {
                    $error = 'Выберите корректное время из доступных слотов (с 08:00 до 20:00 с шагом 1.5 часа).';
                }
            }


            if (!$error) {
                // проверка на занятость
                $stmt = $database->prepare('SELECT * FROM trainer_sessions WHERE trainer_id = ? AND session_date = ? LIMIT 1');
                $stmt->execute([$trainerId, $sessionDate]);
                $session = $stmt->fetch();

                $sessionId = null;

                if ($session) {
                    $stmt = $database->prepare('SELECT COUNT(*) FROM bookings WHERE session_id = ? AND status = "booked"');
                    $stmt->execute([$session['id']]);
                    $bookedCount = (int) $stmt->fetchColumn();

                    // 3. Если записавшихся больше или столько же, сколько мест — ошибка
                    if ($bookedCount >= (int) $session['max_slots']) {
                        $error = 'На это время у тренера уже нет свободных мест. Выберите другое время.';
                    } else {
                        $sessionId = $session['id'];
                    }
                } else {

                    $stmt = $database->prepare('INSERT INTO trainer_sessions (trainer_id, session_date, max_slots) VALUES (?, ?, 1)');
                    $stmt->execute([$trainerId, $sessionDate]);
                    $sessionId = $database->lastInsertId();
                }

                if (!$error && $sessionId) {
                    $stmt = $database->prepare('SELECT id FROM user_memberships WHERE user_id = ? AND status = "active" AND expires_at >= CURDATE() LIMIT 1');
                    $stmt->execute([(int) $USER['id']]);
                    $membershipId = $stmt->fetchColumn();

                    try {
                        $stmt = $database->prepare('
                            INSERT INTO bookings (user_id, session_id, user_membership_id, status)
                            VALUES (?, ?, ?, "booked")
                        ');
                        $stmt->execute([(int) $USER['id'], $sessionId, $membershipId]);
                        $success = true;
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            $error = 'Вы уже записаны на это время.';
                        } else {
                            $error = 'Ошибка БД: ' . $e->getMessage();
                        }
                    }
                }
            }
        }
    }
}
?>

<section class="trainers-section container">

    <?php if ($success): ?>
        <div class="alert-success">
            Вы успешно записаны! Посмотреть запись можно в
            <a href="index.php?page=profile&tab=bookings" class="link-accent">личном кабинете</a>.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Заголовок -->
    <div class="trainers-header">
        <h2>НАШИ <span>ТРЕНЕРЫ</span></h2>
        <p>
            Сертифицированные специалисты, которые помогут достичь результата:
            <br>
            от первого шага в зале до подготовки к соревнованиям.
        </p>
    </div>

    <!-- Карточки тренеров -->
    <div class="trainers-grid">
        <?php if (empty($trainers)): ?>
            <p style="color:#777;">Тренеры пока не добавлены.</p>
        <?php endif; ?>

        <?php foreach ($trainers as $trainer): ?>
            <article class="trainer-card">
                <div class="trainer-image">
                    <?php if (!empty($trainer['photo'])): ?>
                        <img src="<?= htmlspecialchars($trainer['photo']) ?>" alt="<?= htmlspecialchars($trainer['name']) ?>">
                    <?php endif; ?>
                </div>

                <div class="trainer-content">
                    <h3><?= htmlspecialchars($trainer['name']) ?></h3>
                    <span class="trainer-position"><?= htmlspecialchars($trainer['direction']) ?></span>
                    <p class="trainer-description">
                        <?= htmlspecialchars($trainer['description']) ?>
                    </p>

                    <?php if (!empty($USER)): ?>
                        <!-- Передаем ID тренера и его имя в кнопку -->
                        <button class="trainer-button" type="button" data-trainer-id="<?= (int) $trainer['id'] ?>"
                            data-trainer-name="<?= htmlspecialchars($trainer['name']) ?>">
                            <span>ЗАПИСАТЬСЯ</span>
                            <span class="trainer-arrow">→</span>
                        </button>
                    <?php else: ?>
                        <a href="index.php?page=auth" class="trainer-button" style="text-decoration:none;">
                            <span>ВОЙТИ ДЛЯ ЗАПИСИ</span>
                            <span class="trainer-arrow">→</span>
                        </a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- МОДАЛЬНОЕ ОКНО ОНЛАЙН-ЗАПИСИ -->
<div class="modal-overlay" id="trainerModal">
    <div class="trainer-modal">
        <button class="modal-close" id="closeTrainerModal" type="button" aria-label="Закрыть">&times;</button>

        <h2>Онлайн-запись</h2>
        <p class="trainer-modal-subtitle">Заполните поля — это займёт минуту</p>

        <div class="selected-trainer">
            Тренер: <span id="selectedTrainer">Не выбран</span>
        </div>

        <form id="trainerForm" method="POST" action="index.php?page=trainers">
            <input type="hidden" name="action" value="book_trainer">
            <input type="hidden" name="trainer_id" id="trainerIdInput" value="">

            <div class="booking-input-group">
                <label for="bookingName">Ваше имя</label>
                <input type="text" id="bookingName" name="client_name"
                    value="<?= htmlspecialchars($USER['name'] ?? '') ?>">
            </div>

            <div class="booking-input-group">
                <label for="bookingPhone">Телефон</label>
                <input type="tel" id="bookingPhone" name="phone" value="<?= htmlspecialchars($USER['phone'] ?? '') ?>"
                    placeholder="+7 (999) 123-45-67">
            </div>

            <div class="booking-details">
                <div class="booking-input-group">
                    <label for="bookingDate">День</label>
                    <input type="date" id="bookingDate" name="booking_date">
                </div>

                <div class="booking-input-group">
                    <label for="bookingTime">Время</label>
                    <input type="time" id="bookingTime" name="booking_time" min="08:00" max="20:00" step="5400">
                </div>
            </div>

            <button type="submit" class="booking-submit">Записаться</button>
        </form>
    </div>
</div>

<script>
    // Передача массива из PHP в JS
    const busySlots = <?= json_encode($busySlots ?? []) ?>;

    (function () {
        const trainerModal = document.getElementById('trainerModal');
        const closeTrainerModal = document.getElementById('closeTrainerModal');
        const selectedTrainer = document.getElementById('selectedTrainer');
        const trainerIdInput = document.getElementById('trainerIdInput');
        const trainerForm = document.getElementById('trainerForm');

        // Переменная для хранения ID текущего тренера
        let currentTrainerId = null;

        // Открытие модалки
        document.querySelectorAll('.trainer-button[data-trainer-id]').forEach(button => {
            button.addEventListener('click', () => {
                selectedTrainer.textContent = button.dataset.trainerName;
                trainerIdInput.value = button.dataset.trainerId;

                // Запоминаем ID тренера для проверки занятости
                currentTrainerId = button.dataset.trainerId;

                // Сбрасываем время при открытии нового тренера
                bookingTime.value = '';
                updateTimeLimits();

                trainerModal.classList.add('active');
                document.body.classList.add('modal-open');
            });
        });

        function hideTrainerModal() {
            trainerModal.classList.remove('active');
            document.body.classList.remove('modal-open');
        }

        closeTrainerModal.addEventListener('click', hideTrainerModal);
        trainerModal.addEventListener('click', (e) => {
            if (e.target === trainerModal) hideTrainerModal();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && trainerModal.classList.contains('active')) hideTrainerModal();
        });

        // Маска телефона
        const bookingPhone = document.getElementById('bookingPhone');
        bookingPhone.addEventListener('input', () => {
            let digits = bookingPhone.value.replace(/\D/g, '');
            if (digits.startsWith('8')) digits = '7' + digits.slice(1);
            if (digits.startsWith('7')) digits = digits.slice(1);
            digits = digits.slice(0, 10);
            let formatted = '+7';
            if (digits.length > 0) formatted += ' (' + digits.slice(0, 3);
            if (digits.length >= 4) formatted += ') ' + digits.slice(3, 6);
            if (digits.length >= 7) formatted += '-' + digits.slice(6, 8);
            if (digits.length >= 9) formatted += '-' + digits.slice(8, 10);
            bookingPhone.value = formatted;
        });

        // Запрет на прошедшие даты
        const bookingDate = document.getElementById('bookingDate');
        const bookingTime = document.getElementById('bookingTime');

        const today = new Date();
        const localToday = [today.getFullYear(), String(today.getMonth() + 1).padStart(2, '0'), String(today.getDate()).padStart(2, '0')].join('-');
        bookingDate.min = localToday;

        // Функция для обновления доступного времени
        function updateTimeLimits() {
            const selectedDate = bookingDate.value;
            const datalist = document.getElementById('timeSlots');

            if (datalist) datalist.innerHTML = '';

            if (!selectedDate) return;

            let currentDatalist = datalist;
            if (!currentDatalist) {
                currentDatalist = document.createElement('datalist');
                currentDatalist.id = 'timeSlots';
                document.body.appendChild(currentDatalist);
                bookingTime.setAttribute('list', 'timeSlots');
            }

            let currentMinutes = 8 * 60; // 08:00
            const endMinutes = 20 * 60;  // 20:00
            const step = 90;             // 1.5 часа

            const currentHours = today.getHours();
            const currentMinutesNow = today.getMinutes();

            let hasAvailableSlots = false;

            while (currentMinutes <= endMinutes) {
                const hours = String(Math.floor(currentMinutes / 60)).padStart(2, '0');
                const mins = String(currentMinutes % 60).padStart(2, '0');
                const timeStr = `${hours}:${mins}`;

                let isPast = false;
                if (selectedDate === localToday) {
                    const slotH = Math.floor(currentMinutes / 60);
                    const slotM = currentMinutes % 60;
                    if (slotH < currentHours || (slotH === currentHours && slotM <= currentMinutesNow)) {
                        isPast = true;
                    }
                }

                let isBusy = false;
                if (currentTrainerId && busySlots[currentTrainerId] && busySlots[currentTrainerId][selectedDate]) {
                    if (busySlots[currentTrainerId][selectedDate].includes(timeStr)) {
                        isBusy = true;
                    }
                }

                if (!isPast && !isBusy) {
                    const option = document.createElement('option');
                    option.value = timeStr;
                    currentDatalist.appendChild(option);
                    hasAvailableSlots = true;
                }

                currentMinutes += step;
            }

           
            if (bookingTime.value && !Array.from(currentDatalist.options).some(opt => opt.value === bookingTime.value)) {
                bookingTime.value = '';
            }

            if (selectedDate === localToday && !hasAvailableSlots) {
                alert('На сегодня мест больше нет. Выберите завтрашний день.');
                bookingTime.value = '';
            }
        }

        bookingDate.addEventListener('change', updateTimeLimits);
        updateTimeLimits();
    })();
</script>