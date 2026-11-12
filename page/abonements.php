<?php
// Планы из БД
$stmt = $database->query('
    SELECT *
    FROM membership_plans
    WHERE is_active = 1
    ORDER BY price ASC
');
$plans = $stmt->fetchAll();

// Активный абонемент юзера 
$activeMembership = null;
if (!empty($USER)) {
    $stmt = $database->prepare('
        SELECT um.*, mp.title
        FROM user_memberships um
        JOIN membership_plans mp ON mp.id = um.plan_id
        WHERE um.user_id = ?
          AND um.status = "active"
          AND um.expires_at >= CURDATE()
        ORDER BY um.expires_at DESC
        LIMIT 1
    ');
    $stmt->execute([(int) $USER['id']]);
    $activeMembership = $stmt->fetch() ?: null;
}

// Обработка "оплаты"
$success = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'buy_membership') {

    if (empty($USER)) {
        $error = 'Войдите в аккаунт, чтобы оформить абонемент';
    } else {

        $planId = (int) ($_POST['plan_id'] ?? 0);
        $clientName = trim($_POST['client_name'] ?? '');
        $clientEmail = trim($_POST['client_email'] ?? '');

        $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
        $cardExpiry = trim($_POST['card_expiry'] ?? '');
        $cardCvc = trim($_POST['card_cvc'] ?? '');

        if ($planId <= 0) {
            $error = 'Ошибка: не выбран абонемент.';
        } elseif ($clientName === '') {
            $error = 'Ошибка: введите имя.';
        } elseif ($clientEmail === '' || !filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ошибка: введите корректный email.';
        } elseif (strlen($cardNumber) !== 16) {
            $error = 'Ошибка: номер карты должен состоять из 16 цифр.';
        } elseif (strlen($cardExpiry) !== 5) {
            $error = 'Ошибка: введите срок действия (ММ/ГГ).';
        } elseif (strlen($cardCvc) < 3 || strlen($cardCvc) > 4) {
            $error = 'Ошибка: CVC должен состоять из 3 или 4 цифр.';
        }


        if (!$error) {
            $stmt = $database->prepare('SELECT * FROM membership_plans WHERE id = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$planId]);
            $plan = $stmt->fetch();

            if (!$plan) {
                $error = 'Абонемент не найден или недоступен.';
            } else {

                if ($activeMembership) {
                    $stmt = $database->prepare('UPDATE user_memberships SET status = "expired" WHERE id = ?');
                    $stmt->execute([(int) $activeMembership['id']]);
                }

                $expiresAt = date('Y-m-d', strtotime('+' . (int) $plan['duration_days'] . ' days'));

                $stmt = $database->prepare('
                    INSERT INTO user_memberships
                        (user_id, plan_id, purchase_date, expires_at, status, price_paid)
                    VALUES (?, ?, CURDATE(), ?, "active", ?)
                ');
                $stmt->execute([
                    (int) $USER['id'],
                    (int) $plan['id'],
                    $expiresAt,
                    $plan['price'],
                ]);

                $success = true;


                $stmt = $database->prepare('
                    SELECT um.*, mp.title
                    FROM user_memberships um
                    JOIN membership_plans mp ON mp.id = um.plan_id
                    WHERE um.user_id = ?
                      AND um.status = "active"
                      AND um.expires_at >= CURDATE()
                    ORDER BY um.expires_at DESC
                    LIMIT 1
                ');
                $stmt->execute([(int) $USER['id']]);
                $activeMembership = $stmt->fetch() ?: null;
            }
        }
    }
}

?>
<section class="pricing-section container">

    <?php if ($success): ?>
        <div class="alert-success">
            Абонемент оформлен! Действует до
            <?= date('d.m.Y', strtotime($activeMembership['expires_at'])) ?>.
            <a href="index.php?page=profile&tab=memberships" class="link-accent">Посмотреть в профиле →</a>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Заголовок секции -->
    <div class="pricing-header">
        <h1 class="pricing-title">
            ВЫБЕРИ<br>
            <span>СВОЙ</span><br>
            ФОРМАТ
        </h1>

        <p class="pricing-description">
            Каждый абонемент — это доступ к тренажёрному
            залу, групповым занятиям и персональной
            поддержке. Без скрытых платежей.
        </p>
    </div>

    <!-- Карточки тарифов -->
    <div class="pricing-cards">
        <?php foreach ($plans as $index => $plan): ?>
            <?php
            $isFeatured = !empty($plan['is_hit']);
            $isCurrent = $activeMembership && (int) $activeMembership['plan_id'] === (int) $plan['id'];
            ?>
            <div class="pricing-card <?= $isFeatured ? 'featured' : '' ?>">

                <div class="card-top">
                    <div class="card-name"><?= htmlspecialchars($plan['title']) ?></div>
                    <?php if ($isFeatured): ?>
                        <span class="hit-label">ХИТ</span>
                    <?php endif; ?>
                </div>

                <div class="card-price">
                    <?= number_format((float) $plan['price'], 0, '.', ' ') ?>
                    <span>₽ / мес</span>
                </div>

                <p class="card-description">
                    <?= htmlspecialchars($plan['description'] ?? '') ?>
                </p>

                <ul class="card-features">
                    <?php
                    $features = json_decode($plan['features'] ?? '[]', true);
                    if (!is_array($features))
                        $features = [];
                    ?>
                    <?php if ($features): ?>
                        <?php foreach ($features as $feature): ?>
                            <li><?= htmlspecialchars($feature) ?></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>Информация уточняется</li>
                    <?php endif; ?>
                </ul>

                <?php if ($isCurrent): ?>
                    <div class="card-select" style="cursor:default;">
                        <span>ВАШ АБОНЕМЕНТ</span>
                    </div>
                <?php elseif (!empty($USER)): ?>
                    <button class="card-select" type="button" data-plan-id="<?= (int) $plan['id'] ?>"
                        data-plan-title="<?= htmlspecialchars($plan['title']) ?>" data-price="<?= (int) $plan['price'] ?>">
                        <span>ВЫБРАТЬ</span>
                        <span class="arrow">→</span>
                    </button>
                <?php else: ?>
                    <a class="card-select" href="index.php?page=auth">
                        <span>ВОЙТИ И ВЫБРАТЬ</span>
                        <span class="arrow">→</span>
                    </a>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- МОДАЛЬНОЕ ОКНО ОФОРМЛЕНИЯ -->
<div class="modal-overlay" id="orderModal">
    <div class="payment-modal container">

        <button class="modal-close" id="closeModal" type="button" aria-label="Закрыть">
            &times;
        </button>

        <h2>Оформление абонемента</h2>
        <p class="modal-subtitle">
            Заполните поля — это займёт минуту
        </p>

        <form id="paymentForm" method="POST" action="index.php?page=abonements">
            <input type="hidden" name="action" value="buy_membership">
            <input type="hidden" name="plan_id" id="planIdInput" value="">

            <div class="modal-input-group">
                <label for="clientName">Ваше имя</label>
                <input type="text" id="clientName" name="client_name" placeholder="Имя"
                    value="<?= htmlspecialchars($USER['name'] ?? '') ?>">
            </div>

            <div class="modal-input-group">
                <label for="clientEmail">Email для чека</label>
                <input type="email" id="clientEmail" name="client_email" placeholder="example@mail.ru"
                    value="<?= htmlspecialchars($USER['email'] ?? '') ?>">
            </div>

            <div class="modal-input-group">
                <label for="cardNumber">Номер карты</label>
                <input type="text" id="cardNumber" name="card_number" placeholder="0000 0000 0000 0000"
                    inputmode="numeric" maxlength="19">
            </div>

            <div class="card-details">
                <div class="modal-input-group">
                    <label for="cardExpiry">Срок</label>
                    <input type="text" id="cardExpiry" name="card_expiry" placeholder="MM/YY" inputmode="numeric"
                        maxlength="5">
                </div>

                <div class="modal-input-group">
                    <label for="cardCvc">CVC</label>
                    <input type="password" id="cardCvc" name="card_cvc" placeholder="•••" inputmode="numeric"
                        maxlength="4">
                </div>
            </div>

            <div class="payment-total">
                <span>К оплате:</span>
                <strong id="totalPrice">— ₽</strong>
            </div>

            <button type="submit" class="pay-button">Оплатить</button>
        </form>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById('orderModal');
        const closeModal = document.getElementById('closeModal');
        const totalPrice = document.getElementById('totalPrice');
        const planIdInput = document.getElementById('planIdInput');
        const form = document.getElementById('paymentForm');
        const modalTitle = modal.querySelector('h2');

        // Открытие
        document.querySelectorAll('.card-select[data-plan-id]').forEach(btn => {
            btn.addEventListener('click', () => {
                const price = Number(btn.dataset.price);
                const title = btn.dataset.planTitle;

                planIdInput.value = btn.dataset.planId;
                totalPrice.textContent = price.toLocaleString('ru-RU') + ' ₽';
                modalTitle.textContent = 'Оформление: ' + title;

                modal.classList.add('active');
                document.body.classList.add('modal-open');
            });
        });

        function hideModal() {
            modal.classList.remove('active');
            document.body.classList.remove('modal-open');
            form.reset();
            document.getElementById('clientName').value = <?= json_encode($USER['name'] ?? '') ?>;
            document.getElementById('clientEmail').value = <?= json_encode($USER['email'] ?? '') ?>;
        }

        closeModal.addEventListener('click', hideModal);

        modal.addEventListener('click', e => {
            if (e.target === modal) hideModal();
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && modal.classList.contains('active')) hideModal();
        });

        // Маска карты
        const cardNumber = document.getElementById('cardNumber');
        cardNumber.addEventListener('input', () => {
            let value = cardNumber.value.replace(/\D/g, '').slice(0, 19);
            cardNumber.value = value.match(/.{1,4}/g)?.join(' ') || '';
        });

        // Маска срока
        const cardExpiry = document.getElementById('cardExpiry');
        cardExpiry.addEventListener('input', () => {
            let value = cardExpiry.value.replace(/\D/g, '').slice(0, 4);
            if (value.length >= 3) value = value.slice(0, 2) + '/' + value.slice(2);
            cardExpiry.value = value;
        });

        // CVC — только цифры
        const cardCvc = document.getElementById('cardCvc');
        cardCvc.addEventListener('input', () => {
            cardCvc.value = cardCvc.value.replace(/\D/g, '').slice(0, 4);
        });

        // Форма — на сервер уходят только plan_id + action, карта игнорируется
        // (client_name / client_email тоже уходят, но PHP их не использует для оплаты)
    })();
</script>