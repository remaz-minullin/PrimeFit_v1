<?php
/** @var PDO $database */

// Проверка на админа
if (empty($USER) || ($USER['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

$error = null;

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $duration_days = (int)($_POST['duration_days'] ?? 30);
    $features_raw = trim($_POST['features'] ?? '');

    // Валидация
    if ($title === '' || $price <= 0) {
        $error = 'Заполните название и цену абонемента.';
    } else {

        $featuresArray = [];
        if ($features_raw !== '') {
            /* разбиение на строки */
            $lines = explode("\n", $features_raw);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $featuresArray[] = $line;
                }
            }
        }
        $featuresJson = json_encode($featuresArray, JSON_UNESCAPED_UNICODE);

        $stmt = $database->prepare('
            INSERT INTO membership_plans 
                (title, price, description, duration_days, features, is_active) 
            VALUES (?, ?, ?, ?, ?, 1)
        ');
        $stmt->execute([$title, $price, $description, $duration_days, $featuresJson]);

        header('Location: index.php?page=admin&success=добавлен');
        exit;
    }
}
?>

<main class="add-trainer-page container">
    <div class="add-trainer-layout" style="grid-template-columns: 1fr; max-width: 800px;">
        
        <?php if ($error): ?>
            <div class="alert-error" style="margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form id="addPlanForm" method="POST">
            <div class="trainer-form-section">
                
                <div class="trainer-form-group">
                    <label for="planTitle">Название абонемента</label>
                    <input type="text" id="planTitle" name="title" placeholder="Например: ПРО" >
                </div>

                <div class="trainer-form-group">
                    <label for="planPrice">Цена (₽)</label>
                    <input type="number" id="planPrice" name="price" placeholder="3990" min="0" step="1" >
                </div>

                <div class="trainer-form-group">
                    <label for="planDuration">Длительность (дней)</label>
                    <input type="number" id="planDuration" name="duration_days" value="30" min="1" >
                </div>

                <div class="trainer-form-group">
                    <label for="planDesc">Краткое описание</label>
                    <input type="text" id="planDesc" name="description" placeholder="Максимум возможностей для результата" >
                </div>

                <div class="trainer-form-group">
                    <label for="planFeatures">Преимущества </label>
                    <textarea id="planFeatures" name="features" placeholder="Всё из «Старта»&#10;Групповые занятия&#10;Бассейн и сауна" style="height: 120px;"></textarea>
                </div>

                <button type="submit" class="add-trainer-submit">Добавить абонемент</button>
                
                <a href="index.php?page=admin" style="text-align: center; display: block; margin-top: 15px; color: #888; text-decoration: none;">Отмена</a>
            </div>
        </form>
    </div>
</main>