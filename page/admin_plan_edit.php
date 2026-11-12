<?php
/** @var PDO $database */

if (empty($USER) || ($USER['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) {
    header('Location: index.php?page=admin'); exit;
}

$error = null;

$stmt = $database->prepare('SELECT * FROM membership_plans WHERE id = ?');
$stmt->execute([$id]);
$plan = $stmt->fetch();

if (!$plan) {
    header('Location: index.php?page=admin'); exit;
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $duration_days = (int)($_POST['duration_days'] ?? 30);
    $features_raw = trim($_POST['features'] ?? '');

    if ($title === '' || $price <= 0) {
        $error = 'Заполните название и цену абонемента.';
    } else {
        $featuresArray = [];
        if ($features_raw !== '') {
            $lines = explode("\n", $features_raw);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') $featuresArray[] = $line;
            }
        }
        $featuresJson = json_encode($featuresArray, JSON_UNESCAPED_UNICODE);

        $stmt = $database->prepare('
            UPDATE membership_plans 
            SET title = ?, price = ?, description = ?, duration_days = ?, features = ? 
            WHERE id = ?
        ');
        $stmt->execute([$title, $price, $description, $duration_days, $featuresJson, $id]);

        header('Location: index.php?page=admin&success=обновлен'); exit;
    }
}

$featuresText = '';
if (!empty($plan['features'])) {
    $featuresArray = json_decode($plan['features'], true);
    if (is_array($featuresArray)) {
        $featuresText = implode("\n", $featuresArray);
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
                    <input type="text" id="planTitle" name="title" value="<?= htmlspecialchars($plan['title']) ?>" >
                </div>

                <div class="trainer-form-group">
                    <label for="planPrice">Цена (₽)</label>
                    <input type="number" id="planPrice" name="price" value="<?= (int)$plan['price'] ?>" min="0" step="1" >
                </div>

                <div class="trainer-form-group">
                    <label for="planDuration">Длительность (дней)</label>
                    <input type="number" id="planDuration" name="duration_days" value="<?= (int)$plan['duration_days'] ?>" min="1" >
                </div>

                <div class="trainer-form-group">
                    <label for="planDesc">Краткое описание</label>
                    <input type="text" id="planDesc" name="description" value="<?= htmlspecialchars($plan['description']) ?>" >
                </div>

                <div class="trainer-form-group">
                    <label for="planFeatures">Преимущества </label>
                    <textarea id="planFeatures" name="features" style="height: 120px;"><?= htmlspecialchars($featuresText) ?></textarea>
                </div>

                <button type="submit" class="add-trainer-submit">Сохранить изменения</button>
                
                <a href="index.php?page=admin" style="text-align: center; display: block; margin-top: 15px; color: #888; text-decoration: none;">Отмена</a>
            </div>
        </form>
    </div>
</main>