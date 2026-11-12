<?php
/** @var PDO $database */

// Проверка на админа
if (empty($USER) || ($USER['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) {
    header('Location: index.php?page=admin');
    exit;
}

$error = null;

$stmt = $database->prepare('SELECT * FROM trainers WHERE id = ?');
$stmt->execute([$id]);
$trainer = $stmt->fetch();

if (!$trainer) {
    header('Location: index.php?page=admin');
    exit;
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $direction = trim($_POST['direction'] ?? '');
    $description = trim($_POST['description'] ?? '');

   
    $photoPath = $trainer['photo'];

    // Валидация 
    if ($name === '' || $direction === '') {
        $error = 'Заполните имя и направление тренера.';
    }

    elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];

        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file['type'], $allowedTypes)) {
            $error = 'Можно загружать только изображения (JPEG, PNG, WEBP, GIF).';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = 'Размер фотографии не должен превышать 5 МБ.';
        } else {
            $uploadDir = __DIR__ . '/../uploads/trainers/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('trainer_') . '.' . $ext;
            $destination = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Удаление старого фото 
                if (!empty($trainer['photo']) && file_exists(__DIR__ . '/../' . $trainer['photo'])) {
                    unlink(__DIR__ . '/../' . $trainer['photo']);
                }
                $photoPath = 'uploads/trainers/' . $filename;
            } else {
                $error = 'Ошибка при сохранении файла на сервер.';
            }
        }
    }

    if (!$error) {
        $stmt = $database->prepare('UPDATE trainers SET name = ?, direction = ?, description = ?, photo = ? WHERE id = ?');
        $stmt->execute([$name, $direction, $description, $photoPath, $id]);

        header('Location: index.php?page=admin&success=обновлен');
        exit;
    }
}
?>

<main class="add-trainer-page container">
    <div class="add-trainer-layout">

        <?php if ($error): ?>
            <div class="alert-error" style="grid-column: 1 / -1; margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form id="addTrainerForm" method="POST" enctype="multipart/form-data" style="display: contents;">

            <div class="trainer-photo-section">
                <label for="trainerPhoto" class="trainer-photo-upload">
                  
                    <img src="<?= !empty($trainer['photo']) ? htmlspecialchars($trainer['photo']) : '' ?>" 
                         alt="Фото тренера" id="trainerPhotoPreview" 
                         style="<?= !empty($trainer['photo']) ? 'display: block;' : 'display: none;' ?>">
                    
                    <!-- Плейсхолдер скрываем, если фото уже есть -->
                    <div class="photo-placeholder" id="photoPlaceholder" 
                         style="<?= !empty($trainer['photo']) ? 'display: none;' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M4 7H7L9 4H15L17 7H20C21.1 7 22 7.9 22 9V19C22 20.1 21.1 21 20 21H4C2.9 21 2 20.1 2 19V9C2 7.9 2.9 7 4 7Z" fill="currentColor" />
                            <circle cx="12" cy="14" r="4" stroke="#EEEEEE" stroke-width="2" />
                        </svg>
                        <span>Добавьте изображение</span>
                    </div>
                    
                    <input type="file" id="trainerPhoto" name="photo" accept="image/*" hidden>
                </label>
            </div>

            <div class="trainer-form-section">
                <div class="trainer-form-group">
                    <label for="trainerName">Введите имя и фамилию тренера</label>
                    <input type="text" id="trainerName" name="name" placeholder="Иван Иванов" 
                           value="<?= htmlspecialchars($trainer['name']) ?>" >
                </div>

                <div class="trainer-form-group">
                    <label for="trainerAbout">Введите описание</label>
                    <textarea id="trainerAbout" name="description" placeholder="Опыт работы, достижения..." required><?= htmlspecialchars($trainer['description']) ?></textarea>
                </div>

                <div class="trainer-form-group">
                    <label for="trainerDirection">Введите направление тренера</label>
                    <input type="text" id="trainerDirection" name="direction" placeholder="Кроссфит" 
                           value="<?= htmlspecialchars($trainer['direction']) ?>" >
                </div>

                <button type="submit" class="add-trainer-submit">Сохранить изменения</button>
                
                <a href="index.php?page=admin" style="text-align: center; display: block; margin-top: 15px; color: #888; text-decoration: none;">Отмена</a>
            </div>

        </form>
    </div>
</main>

<script>
    // Предпросмотр фото 
    const trainerPhoto = document.getElementById('trainerPhoto');
    const trainerPhotoPreview = document.getElementById('trainerPhotoPreview');
    const photoPlaceholder = document.getElementById('photoPlaceholder');
    let currentPhotoUrl = null;

    trainerPhoto.addEventListener('change', () => {
        const file = trainerPhoto.files[0];
        if (!file) return;

        if (!file.type.startsWith('image/')) {
            alert('Выберите изображение');
            trainerPhoto.value = '';
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert('Размер фотографии не должен превышать 5 МБ');
            trainerPhoto.value = '';
            return;
        }

        if (currentPhotoUrl) {
            URL.revokeObjectURL(currentPhotoUrl);
        }

        currentPhotoUrl = URL.createObjectURL(file);
        trainerPhotoPreview.src = currentPhotoUrl;
        trainerPhotoPreview.style.display = 'block';
        photoPlaceholder.style.display = 'none';
    });
</script>