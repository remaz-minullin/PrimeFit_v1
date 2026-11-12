<!--начало шапки сайта -->
<div class="header-block">
    <header class="container">
        <a href="index.php" class="logo">
            <img src="images/logo.svg" alt="PrimeFit">
        </a>

        <div class="header-menu" id="headerMenu">
            <nav class="nav-header">
                <a href="index.php">ГЛАВНАЯ</a>
                <a href="index.php?page=abonements">АБОНЕМЕНТЫ</a>
                <a href="index.php?page=trainers">ТРЕНЕРЫ</a>
                <a href="index.php?page=contacts">КОНТАКТЫ</a>
                
                <!-- Ссылка видна только админу -->
                <?php if (!empty($USER) && ($USER['role'] ?? '') === 'admin'): ?>
                    <a href="index.php?page=admin">АДМИН ПАНЕЛЬ</a>
                <?php endif; ?>
            </nav>

            <div class="header-auth">
                <?php if (!empty($USER)): ?>
                    <a href="index.php?page=profile" class="but-1">ПРОФИЛЬ</a>
                    <a href="index.php?exit=1" class="but-2">ВЫЙТИ</a>
                <?php else: ?>
                    <a href="index.php?page=auth" class="but-1">ВХОД</a>
                    <a href="index.php?page=reg" class="but-2">РЕГИСТРАЦИЯ</a>
                <?php endif; ?>
            </div>
        </div>

        <button class="burger" id="burger" aria-label="Меню" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </header>
</div>
<!-- конец шапки сайта -->

<script>
    // Бургер-меню
    (function () {
        const burger = document.getElementById('burger');
        const menu = document.getElementById('headerMenu');
        if (!burger || !menu) return;

        burger.addEventListener('click', () => {
            const open = menu.classList.toggle('is-open');
            burger.classList.toggle('is-active', open);
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        // Закрыть меню при клике на ссылку (удобно на мобилке)
        menu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                menu.classList.remove('is-open');
                burger.classList.remove('is-active');
                burger.setAttribute('aria-expanded', 'false');
            });
        });
    })();
</script>