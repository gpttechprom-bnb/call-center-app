<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel | Русский шаблон</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">

        <style>
            :root {
                --bg: #07111f;
                --bg-soft: rgba(10, 22, 39, 0.72);
                --panel: rgba(14, 27, 49, 0.82);
                --panel-border: rgba(255, 255, 255, 0.12);
                --text: #f5f7fb;
                --muted: #9ab0cb;
                --accent: #ff7a59;
                --accent-strong: #ffb84d;
                --cool: #66d9ef;
                --success: #77f2b5;
                --shadow: 0 30px 80px rgba(0, 0, 0, 0.35);
            }

            * {
                box-sizing: border-box;
            }

            html {
                line-height: 1.15;
                -webkit-text-size-adjust: 100%;
            }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: 'Manrope', sans-serif;
                color: var(--text);
                background:
                    radial-gradient(circle at top left, rgba(102, 217, 239, 0.18), transparent 28%),
                    radial-gradient(circle at 85% 18%, rgba(255, 122, 89, 0.28), transparent 24%),
                    radial-gradient(circle at 50% 100%, rgba(255, 184, 77, 0.14), transparent 26%),
                    linear-gradient(135deg, #07111f 0%, #0b1730 55%, #09111d 100%);
                overflow-x: hidden;
            }

            body::before,
            body::after {
                content: '';
                position: fixed;
                inset: auto;
                border-radius: 999px;
                filter: blur(70px);
                opacity: 0.6;
                pointer-events: none;
            }

            body::before {
                width: 18rem;
                height: 18rem;
                top: 8%;
                left: -4rem;
                background: rgba(255, 122, 89, 0.15);
            }

            body::after {
                width: 22rem;
                height: 22rem;
                right: -6rem;
                bottom: 5%;
                background: rgba(102, 217, 239, 0.14);
            }

            a {
                color: inherit;
                text-decoration: none;
            }

            .page {
                position: relative;
                width: min(1180px, calc(100% - 32px));
                margin: 0 auto;
                padding: 28px 0 56px;
            }

            .nav {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 20px;
                margin-bottom: 40px;
            }

            .brand {
                display: inline-flex;
                align-items: center;
                gap: 14px;
            }

            .brand-mark {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 52px;
                height: 52px;
                border-radius: 16px;
                background: linear-gradient(135deg, var(--accent), var(--accent-strong));
                color: #08111d;
                font-family: 'Space Grotesk', sans-serif;
                font-size: 1.25rem;
                font-weight: 700;
                box-shadow: 0 16px 32px rgba(255, 122, 89, 0.28);
            }

            .brand-copy {
                display: grid;
                gap: 3px;
            }

            .brand-title {
                font-family: 'Space Grotesk', sans-serif;
                font-size: 1rem;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            .brand-subtitle {
                color: var(--muted);
                font-size: 0.92rem;
            }

            .nav-links {
                display: flex;
                align-items: center;
                gap: 12px;
                flex-wrap: wrap;
                justify-content: flex-end;
            }

            .nav-link,
            .button,
            .button-secondary {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 999px;
                padding: 12px 18px;
                font-weight: 700;
                transition: transform 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
            }

            .nav-link {
                color: var(--text);
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.08);
            }

            .button {
                color: #08111d;
                background: linear-gradient(135deg, var(--accent-strong), var(--accent));
                box-shadow: 0 18px 32px rgba(255, 122, 89, 0.24);
            }

            .button-secondary {
                color: var(--text);
                background: rgba(255, 255, 255, 0.06);
                border: 1px solid rgba(255, 255, 255, 0.12);
                backdrop-filter: blur(14px);
            }

            .nav-link:hover,
            .button:hover,
            .button-secondary:hover {
                transform: translateY(-1px);
            }

            .hero {
                display: grid;
                grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
                gap: 28px;
                align-items: stretch;
            }

            .hero-panel,
            .status-card,
            .feature-card,
            .info-card {
                position: relative;
                background: var(--panel);
                border: 1px solid var(--panel-border);
                border-radius: 28px;
                box-shadow: var(--shadow);
                overflow: hidden;
                backdrop-filter: blur(18px);
            }

            .hero-panel {
                padding: 40px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                min-height: 520px;
            }

            .hero-panel::before,
            .status-card::before {
                content: '';
                position: absolute;
                inset: 0;
                background: linear-gradient(140deg, rgba(255, 255, 255, 0.08), transparent 38%, transparent);
                pointer-events: none;
            }

            .eyebrow {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                width: fit-content;
                padding: 8px 14px;
                border-radius: 999px;
                background: rgba(119, 242, 181, 0.1);
                color: var(--success);
                font-size: 0.84rem;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            .eyebrow::before {
                content: '';
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: currentColor;
                box-shadow: 0 0 16px currentColor;
            }

            .hero-title {
                margin: 22px 0 18px;
                max-width: 10ch;
                font-family: 'Space Grotesk', sans-serif;
                font-size: clamp(3rem, 6vw, 5.5rem);
                line-height: 0.96;
                letter-spacing: -0.05em;
            }

            .hero-title span {
                display: block;
                color: var(--cool);
            }

            .hero-text {
                max-width: 680px;
                margin: 0;
                color: var(--muted);
                font-size: 1.08rem;
                line-height: 1.8;
            }

            .hero-actions {
                display: flex;
                gap: 14px;
                flex-wrap: wrap;
                margin-top: 28px;
            }

            .hero-footer {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 14px;
                margin-top: 34px;
            }

            .metric {
                padding: 18px 18px 20px;
                border-radius: 22px;
                background: rgba(255, 255, 255, 0.04);
                border: 1px solid rgba(255, 255, 255, 0.06);
            }

            .metric-value {
                display: block;
                margin-bottom: 8px;
                font-family: 'Space Grotesk', sans-serif;
                font-size: 1.8rem;
                font-weight: 700;
            }

            .metric-label {
                color: var(--muted);
                font-size: 0.95rem;
                line-height: 1.5;
            }

            .status-card {
                padding: 30px;
                display: grid;
                gap: 18px;
                min-height: 520px;
            }

            .status-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
            }

            .status-title {
                margin: 0;
                font-size: 1.15rem;
                font-weight: 800;
            }

            .status-badge {
                padding: 8px 12px;
                border-radius: 999px;
                background: rgba(102, 217, 239, 0.12);
                color: var(--cool);
                font-size: 0.82rem;
                font-weight: 800;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            .status-stack {
                display: grid;
                gap: 12px;
            }

            .stack-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 16px 18px;
                border-radius: 20px;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.07);
            }

            .stack-name {
                display: grid;
                gap: 4px;
            }

            .stack-name strong {
                font-size: 1rem;
            }

            .stack-name span {
                color: var(--muted);
                font-size: 0.9rem;
            }

            .stack-state {
                padding: 8px 12px;
                border-radius: 999px;
                font-size: 0.82rem;
                font-weight: 800;
                white-space: nowrap;
            }

            .stack-state.ready {
                background: rgba(119, 242, 181, 0.12);
                color: var(--success);
            }

            .stack-state.pending {
                background: rgba(255, 184, 77, 0.14);
                color: var(--accent-strong);
            }

            .mini-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 14px;
            }

            .mini-card {
                padding: 18px;
                border-radius: 22px;
                background: linear-gradient(180deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.03));
                border: 1px solid rgba(255, 255, 255, 0.08);
            }

            .mini-card strong {
                display: block;
                margin-bottom: 8px;
                font-size: 1rem;
            }

            .mini-card span {
                color: var(--muted);
                font-size: 0.92rem;
                line-height: 1.6;
            }

            .section {
                margin-top: 28px;
            }

            .section-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 20px;
            }

            .feature-card,
            .info-card {
                padding: 26px;
            }

            .card-kicker {
                display: inline-block;
                margin-bottom: 14px;
                color: var(--cool);
                font-size: 0.8rem;
                font-weight: 800;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            .card-title {
                margin: 0 0 12px;
                font-size: 1.3rem;
                font-weight: 800;
            }

            .card-text {
                margin: 0;
                color: var(--muted);
                line-height: 1.75;
            }

            .info-strip {
                display: grid;
                grid-template-columns: 1.2fr 0.8fr;
                gap: 20px;
                margin-top: 20px;
            }

            .bullets {
                display: grid;
                gap: 12px;
                margin-top: 18px;
            }

            .bullet {
                display: flex;
                gap: 12px;
                align-items: flex-start;
                color: var(--muted);
                line-height: 1.65;
            }

            .bullet::before {
                content: '';
                width: 10px;
                height: 10px;
                margin-top: 8px;
                flex: 0 0 auto;
                border-radius: 50%;
                background: linear-gradient(135deg, var(--accent-strong), var(--accent));
                box-shadow: 0 0 18px rgba(255, 122, 89, 0.35);
            }

            .footer {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                margin-top: 26px;
                color: var(--muted);
                font-size: 0.92rem;
            }

            .footer-links {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
            }

            .footer-links a {
                padding: 10px 14px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.08);
            }

            @media (max-width: 1024px) {
                .hero,
                .info-strip,
                .section-grid {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 720px) {
                .page {
                    width: min(100% - 20px, 1180px);
                    padding-top: 18px;
                    padding-bottom: 30px;
                }

                .nav,
                .footer,
                .status-head {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .hero-panel,
                .status-card,
                .feature-card,
                .info-card {
                    border-radius: 24px;
                }

                .hero-panel,
                .status-card {
                    min-height: auto;
                    padding: 24px;
                }

                .hero-footer,
                .mini-grid {
                    grid-template-columns: 1fr;
                }

                .hero-title {
                    max-width: none;
                    font-size: clamp(2.4rem, 12vw, 4rem);
                }

                .nav-links,
                .hero-actions {
                    width: 100%;
                }

                .nav-link,
                .button,
                .button-secondary {
                    width: 100%;
                }
            }
        </style>
    </head>
    <body>
        <main class="page">
            <nav class="nav">
                <div class="brand">
                    <div class="brand-mark">L</div>
                    <div class="brand-copy">
                        <div class="brand-title">Laravel Space</div>
                        <div class="brand-subtitle">Русский стартовый шаблон с аккуратной подачей</div>
                    </div>
                </div>

                <div class="nav-links">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/home') }}" class="nav-link">Главная</a>
                        @else
                            <a href="{{ route('login') }}" class="nav-link">Войти</a>

                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="button">Регистрация</a>
                            @endif
                        @endauth
                    @endif
                </div>
            </nav>

            <section class="hero">
                <article class="hero-panel">
                    <div>
                        <div class="eyebrow">Laravel 8 + PHP {{ PHP_VERSION }}</div>
                        <h1 class="hero-title">
                            Шаблон, который
                            <span>выглядит как продукт</span>
                        </h1>
                        <p class="hero-text">
                            Эта страница больше не похожа на стандартную заглушку. Теперь это чистый стартовый экран
                            для Laravel-проекта с русским интерфейсом, выразительной типографикой и приятной глубиной.
                        </p>

                        <div class="hero-actions">
                            <a href="https://laravel.com/docs" class="button">Открыть документацию</a>
                            <a href="https://laracasts.com" class="button-secondary">Смотреть Laracasts</a>
                        </div>
                    </div>

                    <div class="hero-footer">
                        <div class="metric">
                            <span class="metric-value">01</span>
                            <div class="metric-label">Один Blade-файл без лишней сложности и сборки интерфейса.</div>
                        </div>
                        <div class="metric">
                            <span class="metric-value">RU</span>
                            <div class="metric-label">Русский текст и понятная структура вместо англоязычного шаблона.</div>
                        </div>
                        <div class="metric">
                            <span class="metric-value">UI</span>
                            <div class="metric-label">Атмосферный визуальный стиль, который не выглядит случайным.</div>
                        </div>
                    </div>
                </article>

                <aside class="status-card">
                    <div class="status-head">
                        <div>
                            <p class="card-kicker">Стек проекта</p>
                            <h2 class="status-title">Текущее состояние шаблона</h2>
                        </div>
                        <div class="status-badge">Активно</div>
                    </div>

                    <div class="status-stack">
                        <div class="stack-item">
                            <div class="stack-name">
                                <strong>Laravel</strong>
                                <span>Фреймворк готов для старта и локальной доработки.</span>
                            </div>
                            <div class="stack-state ready">Готово</div>
                        </div>

                        <div class="stack-item">
                            <div class="stack-name">
                                <strong>Blade UI</strong>
                                <span>Шаблон обновлён и больше не выглядит как стандартная страница.</span>
                            </div>
                            <div class="stack-state ready">Обновлено</div>
                        </div>

                        <div class="stack-item">
                            <div class="stack-name">
                                <strong>Docker / Python</strong>
                                <span>Можно продолжить проверку библиотек и окружения следующим шагом.</span>
                            </div>
                            <div class="stack-state pending">Дальше</div>
                        </div>
                    </div>

                    <div class="mini-grid">
                        <div class="mini-card">
                            <strong>Русская локализация</strong>
                            <span>Навигация, подписи и смысловые блоки уже приведены к читаемому русскому интерфейсу.</span>
                        </div>
                        <div class="mini-card">
                            <strong>Адаптивная вёрстка</strong>
                            <span>Композиция спокойно сжимается до мобильного экрана без поломки сетки.</span>
                        </div>
                    </div>
                </aside>
            </section>

            <section class="section">
                <div class="section-grid">
                    <article class="feature-card">
                        <p class="card-kicker">Документация</p>
                        <h3 class="card-title">Быстрый вход в проект</h3>
                        <p class="card-text">
                            Официальная документация Laravel остаётся лучшей точкой входа, когда нужно быстро понять
                            роутинг, сервис-контейнер, очереди, события или Blade-шаблоны.
                        </p>
                    </article>

                    <article class="feature-card">
                        <p class="card-kicker">Практика</p>
                        <h3 class="card-title">Видео и реальные сценарии</h3>
                        <p class="card-text">
                            Laracasts помогает двигаться от теории к реальной разработке: от основ PHP и Laravel до
                            продвинутой структуры приложений и сервисного кода.
                        </p>
                    </article>

                    <article class="feature-card">
                        <p class="card-kicker">Экосистема</p>
                        <h3 class="card-title">Инструменты для роста</h3>
                        <p class="card-text">
                            Forge, Vapor, Nova, Horizon, Sanctum и другие инструменты дают удобную дорожку от шаблона
                            до production-уровня, не ломая привычный Laravel-поток.
                        </p>
                    </article>
                </div>

                <div class="info-strip">
                    <article class="info-card">
                        <p class="card-kicker">Что уже сделано</p>
                        <h3 class="card-title">Страница ощущается как старт продукта</h3>
                        <p class="card-text">
                            Вместо стандартного экрана теперь есть визуальная иерархия, читаемый ритм блоков,
                            атмосферный фон и понятный акцент на следующем шаге для разработки.
                        </p>
                        <div class="bullets">
                            <div class="bullet">Выразительный hero-блок с крупной типографикой и кнопками действия.</div>
                            <div class="bullet">Информационные карточки с более чистой и современной подачей.</div>
                            <div class="bullet">Минимум файлов и максимум понятности для дальнейшей кастомизации.</div>
                        </div>
                    </article>

                    <article class="info-card">
                        <p class="card-kicker">Следующий шаг</p>
                        <h3 class="card-title">Можно продолжать уже по задаче проекта</h3>
                        <p class="card-text">
                            Если хочешь, следующим сообщением я продолжу не дизайн, а уже техническую часть:
                            проверку Docker-сборки, Python-библиотек или интеграцию нужных компонентов в Laravel.
                        </p>
                    </article>
                </div>
            </section>

            <footer class="footer">
                <div>Laravel v{{ Illuminate\Foundation\Application::VERSION }} • PHP v{{ PHP_VERSION }}</div>
                <div class="footer-links">
                    <a href="https://laravel-news.com/">Новости Laravel</a>
                    <a href="https://laravel.bigcartel.com">Магазин</a>
                    <a href="https://github.com/sponsors/taylorotwell">Поддержать</a>
                </div>
            </footer>
        </main>
    </body>
</html>
