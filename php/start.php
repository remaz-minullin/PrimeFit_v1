<div class="abonementi container">
    <?php
    // Получаем 3 самых дешевых активных абонемента из БД
    $stmt = $database->query('
            SELECT * 
            FROM membership_plans 
            WHERE is_active = 1 
            ORDER BY price ASC 
            LIMIT 3
        ');
    $home_plans = $stmt->fetchAll();
    ?>

    <?php if (empty($home_plans)): ?>
        <p style="color: #8A8A8A; font-family: ir; grid-column: 1 / -1;">Абонементы скоро появятся...</p>
    <?php else: ?>
        <?php foreach ($home_plans as $plan): ?>
            <div class="abonement">
                <h1 class="t-9"><?= htmlspecialchars($plan['title']) ?></h1>
                <div class="cena">
                    <h1 class="t-10">
                        <?= number_format((float) $plan['price'], 0, '.', ' ') ?>
                        <span class="t-11">₽/мес</span>
                    </h1>
                    <p class="t-11"><?= htmlspecialchars($plan['description'] ?? '') ?></p>
                </div>

                <div class="plus">
                    <?php
                    // Достаем преимущества из JSON
                    $features = json_decode($plan['features'] ?? '[]', true);
                    if (!is_array($features))
                        $features = [];
                    ?>

                    <?php if ($features): ?>
                        <?php foreach ($features as $feature): ?>
                            <p class="t-13"><img src="images/gal.svg" alt=""><?= htmlspecialchars($feature) ?></p>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="t-13"><img src="images/gal.svg" alt="">Информация уточняется</p>
                    <?php endif; ?>
                </div>

                <!-- Ссылка ведет в каталог, где можно купить -->
                <a href="index.php?page=abonements" class="vibor" style="text-decoration: none;">
                    <h1 class="t-12">ВЫБРАТЬ</h1>
                    <img src="images/ykaz.svg" alt="">
                </a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>