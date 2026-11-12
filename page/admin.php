<?php
/** @var PDO $database */

// Проверка на админа
if (empty($USER) || ($USER['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

$success = null;
$error = null;

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($id > 0) {
        // --- СКРЫТИЕ АБОНЕМЕНТА ---
        if ($action === 'hide_plan') {
            // Вместо DELETE делаем UPDATE (скрываем)
            $stmt = $database->prepare('UPDATE membership_plans SET is_active = 0 WHERE id = ?');
            $stmt->execute([$id]);
            $success = 'Абонемент скрыт из продажи';
        }

        // --- УДАЛЕНИЕ ТРЕНЕРА (пока оставим физическое, если на тренера не записаны) ---
        elseif ($action === 'delete_trainer') {
            try {
                $stmt = $database->prepare('DELETE FROM trainers WHERE id = ?');
                $stmt->execute([$id]);
                $success = 'Тренер успешно удален';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'Нельзя удалить тренера: к нему уже записаны клиенты.';
                } else {
                    $error = 'Ошибка БД: ' . $e->getMessage();
                }
            }
        }
    }
}

// Получаем данные. 
// ВАЖНО: для абонементов ставим WHERE is_active = 1, чтобы скрытые не висели в админке.
$plans = $database->query('SELECT * FROM membership_plans WHERE is_active = 1 ORDER BY id DESC')->fetchAll();
$trainers = $database->query('SELECT * FROM trainers ORDER BY id DESC')->fetchAll();

// Флеш-сообщения из URL (например, после редактирования)
if (isset($_GET['success'])) {
    $success = 'Запись успешно ' . htmlspecialchars($_GET['success']);
}
?>

<section class="admin-panel container">

    <?php if ($success): ?>
        <div class="alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- УПРАВЛЕНИЕ АБОНЕМЕНТАМИ -->
    <div class="admin-section">
        <div class="admin-section-header">
            <h2>Управление абонементами</h2>
            <a href="index.php?page=admin_plan_add" class="admin-add-btn"
                style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">
                + Добавить
            </a>
        </div>

        <div class="admin-table-wrapper">
            <?php if (empty($plans)): ?>
                <p style="color: #888; padding: 20px 0;">Активных абонементов пока нет.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Цена</th>
                            <th>Дней</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plans as $plan): ?>
                            <tr>
                                <td><?= $plan['id'] ?></td>
                                <td><?= htmlspecialchars($plan['title']) ?></td>
                                <td><?= number_format((float) $plan['price'], 0, '.', ' ') ?> ₽</td>
                                <td><?= $plan['duration_days'] ?></td>
                                <td>
                                    <div class="admin-actions">
                                        <!-- Редактирование -->
                                        <a href="index.php?page=admin_plan_edit&id=<?= $plan['id'] ?>" class="edit-btn"
                                            style="text-decoration:none;">
                                            <span class="edit-icon">✎</span>
                                        </a>
                                        <!-- Скрытие (мягкое удаление) -->
                                        <form method="POST" style="margin:0;"
                                            onsubmit="return confirm('Скрыть этот абонемент? Он пропадет из каталога, но останется у тех, кто его купил.');">
                                            <input type="hidden" name="action" value="hide_plan">
                                            <input type="hidden" name="id" value="<?= $plan['id'] ?>">
                                            <button type="submit" class="delete-btn"
                                                title="Скрыть из продажи"><span>▤</span></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- УПРАВЛЕНИЕ ТРЕНЕРАМИ -->
    <div class="admin-section">
        <div class="admin-section-header">
            <h2>Управление тренерами</h2>
            <a href="index.php?page=admin_trainer_add" class="admin-add-btn"
                style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">
                + Добавить
            </a>
        </div>

        <div class="admin-table-wrapper">
            <?php if (empty($trainers)): ?>
                <p style="color: #888; padding: 20px 0;">Тренеров пока нет.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Имя</th>
                            <th>Направление</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trainers as $trainer): ?>
                            <tr>
                                <td><?= $trainer['id'] ?></td>
                                <td><?= htmlspecialchars($trainer['name']) ?></td>
                                <td><?= htmlspecialchars($trainer['direction']) ?></td>
                                <td>
                                    <div class="admin-actions">
                                        <!-- Редактирование -->
                                        <a href="index.php?page=admin_trainer_edit&id=<?= $trainer['id'] ?>" class="edit-btn"
                                            style="text-decoration:none;">
                                            <span class="edit-icon">✎</span>
                                        </a>
                                        <!-- Удаление -->
                                        <form method="POST" style="margin:0;" onsubmit="return confirm('Удалить тренера?');">
                                            <input type="hidden" name="action" value="delete_trainer">
                                            <input type="hidden" name="id" value="<?= $trainer['id'] ?>">
                                            <button type="submit" class="delete-btn"><span>▤</span></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</section>