<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <style>
        :root {
            --page: #f0f2f5;
            --card: #ffffff;
            --text: #1d2436;
            --muted: #6d768c;
            --line: #d7e2f2;
            --accent: #1877f2;
            --accent-dark: #0f66dc;
            --accent-soft: #eef5ff;
            --sidebar: #24262c;
            --sidebar-dark: #1f2126;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 18px;
            background:
                radial-gradient(circle at top left, rgba(24, 119, 242, 0.08), transparent 32%),
                linear-gradient(180deg, #f8fbff 0%, var(--page) 100%);
            color: var(--text);
            font-family: Inter, Arial, sans-serif;
        }

        .auth-shell {
            width: min(1040px, 100%);
            display: grid;
            grid-template-columns: minmax(280px, 380px) minmax(320px, 1fr);
            border: 1px solid #dbe4f4;
            border-radius: 28px;
            overflow: hidden;
            background: var(--card);
            box-shadow: 0 28px 80px rgba(31, 40, 62, 0.14);
        }

        .auth-brand {
            padding: 42px 34px;
            background:
                radial-gradient(circle at top left, rgba(95, 186, 255, 0.28), transparent 38%),
                linear-gradient(180deg, var(--sidebar) 0%, var(--sidebar-dark) 100%);
            color: #ffffff;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 70px;
            height: 70px;
            border-radius: 24px;
            background: linear-gradient(180deg, #d3ebff 0%, #9ec8ff 100%);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.72);
        }

        .brand-badge svg {
            width: 42px;
            height: 42px;
            display: block;
        }

        .brand-title {
            margin: 28px 0 12px;
            font-size: 34px;
            line-height: 1.1;
        }

        .brand-text {
            margin: 0;
            color: rgba(255, 255, 255, 0.76);
            font-size: 16px;
            line-height: 1.7;
        }

        .brand-points {
            margin: 30px 0 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 14px;
        }

        .brand-points li {
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255, 255, 255, 0.88);
            font-size: 14px;
        }

        .brand-points li::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #66b3ff;
            flex: 0 0 auto;
        }

        .auth-card {
            padding: 42px 38px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        }

        .auth-kicker {
            margin-bottom: 12px;
            color: #7f879d;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.16em;
        }

        .auth-card h1 {
            margin: 0;
            font-size: 34px;
            line-height: 1.1;
        }

        .auth-card p {
            margin: 14px 0 0;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.7;
        }

        .auth-form {
            margin-top: 28px;
            display: grid;
            gap: 18px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-field label {
            color: #58627b;
            font-size: 14px;
            font-weight: 600;
        }

        .form-field input {
            width: 100%;
            height: 48px;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 0 14px;
            background: #ffffff;
            color: var(--text);
            font-size: 15px;
        }

        .form-field input:focus {
            outline: 2px solid rgba(24, 119, 242, 0.2);
            border-color: #7aaef5;
        }

        .auth-button,
        .auth-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 50px;
            border-radius: 16px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        .auth-button {
            border: 0;
            background: linear-gradient(180deg, #5eaaff, #1877f2);
            color: #ffffff;
        }

        .auth-secondary {
            border: 1px solid var(--line);
            background: #ffffff;
            color: #4e5a78;
        }

        .auth-row {
            display: grid;
            gap: 12px;
        }

        .auth-note {
            margin-top: 18px;
            color: #7d879d;
            font-size: 13px;
            line-height: 1.6;
        }

        .auth-switch {
            margin-top: 18px;
            color: var(--muted);
            font-size: 14px;
        }

        .auth-switch a {
            color: var(--accent);
            font-weight: 700;
            text-decoration: none;
        }

        @media (max-width: 900px) {
            .auth-shell {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .auth-brand,
            .auth-card {
                padding: 30px 24px;
            }
        }
    </style>
</head>
<body>
<div class="auth-shell">
    <aside class="auth-brand">
        <div class="brand-badge" aria-hidden="true">
            <svg viewBox="0 0 64 64" role="img" aria-hidden="true">
                <defs>
                    <linearGradient id="authMicFill" x1="0%" x2="100%" y1="0%" y2="100%">
                        <stop offset="0%" stop-color="#58acff"></stop>
                        <stop offset="100%" stop-color="#1877f2"></stop>
                    </linearGradient>
                </defs>
                <rect x="20" y="8" width="24" height="30" rx="12" fill="url(#authMicFill)"></rect>
                <path d="M14 29c0 10 8 18 18 18s18-8 18-18" fill="none" stroke="url(#authMicFill)" stroke-linecap="round" stroke-width="6"></path>
                <path d="M32 47v8" fill="none" stroke="url(#authMicFill)" stroke-linecap="round" stroke-width="6"></path>
                <rect x="19" y="54" width="26" height="7" rx="3.5" fill="url(#authMicFill)"></rect>
            </svg>
        </div>
        <h2 class="brand-title">Call Center QA ALT</h2>
        <p class="brand-text">Окремий тестовий контур для перевірки альтернативного варіанту сервісу без втручання в основний інтерфейс.</p>
        <ul class="brand-points">
            <li>Повна копія інтерфейсу для тестування</li>
            <li>Ті самі дані, чек-листи та AI-налаштування</li>
            <li>Безпечний окремий маршрут для експериментів</li>
        </ul>
    </aside>

    <main class="auth-card">
        @yield('content')
    </main>
</div>
</body>
</html>
