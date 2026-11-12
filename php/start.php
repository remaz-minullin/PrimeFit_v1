<?php

$stmt = $database->query('
            SELECT * 
            FROM membership_plans 
            WHERE is_active = 1 
            ORDER BY price ASC 
            LIMIT 3
        ');
$home_plans = $stmt->fetchAll();
?>


<div class=" main-page">
    <div class="container start-block">
        <span class="bu container"><img src="images/Background.svg" alt="" class="img-1"><span class="t">НОВЫЙ
                СЕЗОН</span>
            · 2026</span>
    </div>
    <main class="container main-block">
        <div>
            <h1 class="t-4">
                ТВОЙ<br>ПРЕДЕЛ
            </h1>
            <h1 class="t-5">
                НЕ<br>ЗДЕСЬ.
            </h1>
        </div>
        <img src="images/bun.svg" alt="">
    </main>
    <section class="container section-block">
        <p class="t-1">Премиальное оборудование, тренеры-эксперты и атмосфера,которая вытаскивает из зоны комфорта.
            Выбери
            свой абонемент в «Прайм Фитнес».</p>
        <div>
            <a href="index.php?page=abonements" class="but-2">ВЫБРАТЬ АБОНЕМЕНТ <img src="images/strel.svg" alt=""></a>
            <a href="index.php?page=trainers" class="but-1">НАШИ ТРЕНЕРЫ</a>
        </div>
    </section>

    <div class="marquee">
        <div class="marquee-track">
            <div class="marquee-item">Сила <span class="star">✦</span> <span class=" marquee-item">Дисциплина</span>
                <span class="star">✦</span> Результат <span class="star">✦</span> <span
                    class="marquee-item">Энергия</span>
                <span class="star">✦</span>
            </div>
            <div class="marquee-item">Сила <span class="star">✦</span> <span class="marquee-item">Дисциплина</span>
                <span class="star">✦</span> Результат <span class="star">✦</span> <span
                    class="marquee-item">Энергия</span>
                <span class="star">✦</span>
            </div>
        </div>
    </div>

    <div class="stat container">
        <div class="stat-1">
            <span class="t-6">15<span class="t-7">+</span></span>
            <p class="t-8">ЛЕТ НА РЫНКЕ</p>
        </div>
        <div class="stat-1">
            <span class="t-6">40<span class="t-7">+</span></span>
            <p class="t-8">ТРЕНЕРОВ</p>
        </div>
        <div class="stat-1">
            <span class="t-6">24<span class="t-7">/7</span></span>
            <p class="t-8">РЕЖИМ РАБОТЫ</p>
        </div>
        <div class="stat-1">
            <span class="t-6">98<span class="t-7">%</span></span>
            <p class="t-8">ДОВОЛЬНЫ РЕЗУЛЬТАТОМ</p>
        </div>
    </div>



    <div class="container ab-zag">
        <span class="t-6" style="line-height: 1;">ВЫБЕРИ<br><span class="t-7">СВОЙ</span><br>ФОРМАТ</span>
        <p class="t-1">Каждый абонемент — это доступ к тренажёрному
            залу, групповым занятиям и персональной
            поддержке. Без скрытых платежей.</p>
    </div>
    <div class="abonementi container">
        <?php if (empty($home_plans)): ?>
            <p style="color: #8A8A8A; font-family: ir; grid-column: 1 / -1;">Абонементы скоро появятся...</p>
        <?php else: ?>
            <?php foreach ($home_plans as $plan): ?>
                <div class="abonement container">
                    <h1 class="t-9">
                        <?= htmlspecialchars($plan['title']) ?>
                    </h1>
                    <div class="cena">
                        <h1 class="t-10">
                            <?= number_format((float) $plan['price'], 0, '.', ' ') ?>
                            <span class="t-11">₽/мес</span>
                        </h1>
                        <p class="t-11">
                            <?= htmlspecialchars($plan['description'] ?? '') ?>
                        </p>
                    </div>

                    <div class="plus">
                        <?php

                        $features = json_decode($plan['features'] ?? '[]', true);
                        if (!is_array($features))
                            $features = [];
                        ?>

                        <?php if ($features): ?>
                            <?php foreach ($features as $feature): ?>
                                <p class="t-13"><img src="images/gal.svg" alt="">
                                    <?= htmlspecialchars($feature) ?>
                                </p>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="t-13"><img src="images/gal.svg" alt="">Информация уточняется</p>
                        <?php endif; ?>
                    </div>


                    <a href="index.php?page=abonements" class="vibor" style="text-decoration: none;">
                        <h1 class="t-12">ВЫБРАТЬ</h1>
                        <img src="images/ykaz.svg" alt="">
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>



<div class="container ab-zag">
    <span class="t-6" style="line-height: 1;">ПОЧЕМУ<br><span class="t-7">PRIME</span></span>
    <p class="t-1">Не просто спортзал — экосистема для тела и головы.
        Вот что делает нас другими.</p>
</div>

<div class="container premka">
    <div>
        <div class="preim-1">
            <h1 class="t-14">01</h1>
            <p class="t-15">Оборудование премиум-класса</p>
            <p class="t-13">Техника Technogym и Hammer Strength последнего поколения. Всё, чтобы тренировка была
                эффективной и
                безопасной.</p>
        </div>
        <div class="preim-2">
            <div class="preim-1">
                <h1 class="t-14">03</h1>
                <p class="t-15">24/7 доступ</p>
                <p class="t-13">Тренируйтесь когда удобно. Клуб открыт
                    круглосуточно.</p>
            </div>
            <div class="preim-1">
                <h1 class="t-14">04</h1>
                <p class="t-15">Бассейн и спа</p>
                <p class="t-13">Восстановление после тренировок — важная часть результата.</p>
            </div>
        </div>
    </div>
    <div class="preim-1">
        <h1 class="t-14">02</h1>
        <p class="t-15" style="margin-top: 280px;">Тренеры-эксперты</p>
        <p class="t-13">40+ сертифицированных специалистов.
            Индивидуальная программа, контроль техники и
            поддержка на каждом этапе.</p>
    </div>
</div>

<div class="container zal">
    <img src="images/zal.svg" alt="">
    <div class="preim-1">
        <h1 class="t-14">05</h1>
        <p class="t-15">Поддержка 24/7</p>
        <p class="t-13">Вопросы по абонементу — решаем по звонку за минуты.</p>
    </div>
</div>

<div class="cta">
    <h1 class="t-16">ГОТОВ НАЧАТЬ?</h1>
    <div class="tren">
        <button class="but-4">ЗАПИСАТЬСЯ НА ТРЕНИРОВКУ <img src="images/zelt.svg" alt=""></button>
    </div>
</div>
</div>