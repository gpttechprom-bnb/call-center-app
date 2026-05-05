<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Yaprofi AI Call Center</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --page: #eef3f7;
            --ink: #162133;
            --muted: #64748b;
            --muted-strong: #4f5f7a;
            --card: rgba(255, 255, 255, 0.86);
            --card-border: rgba(150, 168, 196, 0.25);
            --accent: #0f7b6c;
            --accent-strong: #12a087;
            --accent-soft: #dff7f0;
            --accent-ink: #0e5f54;
            --signal: #f47c48;
            --signal-soft: #fff0e8;
            --shadow: 0 24px 70px rgba(22, 33, 51, 0.12);
            --hero-shadow: 0 40px 90px rgba(20, 49, 84, 0.16);
            --radius-xl: 34px;
            --radius-lg: 24px;
            --radius-md: 18px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            line-height: 1.15;
            -webkit-text-size-adjust: 100%;
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Manrope", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(18, 160, 135, 0.16), transparent 24%),
                radial-gradient(circle at 92% 15%, rgba(244, 124, 72, 0.16), transparent 18%),
                linear-gradient(180deg, #f7fafc 0%, #eef3f7 42%, #e9eef5 100%);
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            border-radius: 999px;
            filter: blur(70px);
            opacity: 0.65;
            pointer-events: none;
            z-index: 0;
        }

        body::before {
            width: 280px;
            height: 280px;
            top: 5%;
            left: -90px;
            background: rgba(18, 160, 135, 0.18);
        }

        body::after {
            width: 320px;
            height: 320px;
            right: -110px;
            bottom: 8%;
            background: rgba(244, 124, 72, 0.18);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .site {
            position: relative;
            z-index: 1;
            width: min(1240px, calc(100% - 32px));
            margin: 0 auto;
            padding: 22px 0 46px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 28px;
            padding: 10px 0;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .brand-mark {
            width: 52px;
            height: 52px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--accent), var(--accent-strong));
            color: #ffffff;
            font-family: "Space Grotesk", sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            box-shadow: 0 16px 34px rgba(18, 160, 135, 0.28);
        }

        .brand-copy {
            display: grid;
            gap: 3px;
        }

        .brand-title {
            font-family: "Space Grotesk", sans-serif;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .brand-subtitle {
            color: var(--muted);
            font-size: 0.93rem;
        }

        .topbar-nav {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .nav-link,
        .button,
        .button-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid transparent;
            font-weight: 700;
            transition: transform 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
        }

        .nav-link {
            color: var(--muted-strong);
            background: rgba(255, 255, 255, 0.56);
            border-color: rgba(158, 178, 205, 0.24);
            backdrop-filter: blur(10px);
        }

        .button {
            color: #ffffff;
            background: linear-gradient(135deg, var(--accent), var(--accent-strong));
            box-shadow: 0 16px 30px rgba(18, 160, 135, 0.24);
        }

        .button-secondary {
            color: var(--accent-ink);
            background: var(--accent-soft);
            border-color: rgba(18, 160, 135, 0.14);
        }

        .nav-link:hover,
        .button:hover,
        .button-secondary:hover {
            transform: translateY(-1px);
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.12fr) minmax(320px, 0.88fr);
            gap: 24px;
            align-items: stretch;
        }

        .hero-main,
        .hero-side,
        .feature-card,
        .story-card,
        .cta-card {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
            backdrop-filter: blur(18px);
        }

        .hero-main {
            position: relative;
            overflow: hidden;
            padding: 42px;
            min-height: 580px;
            box-shadow: var(--hero-shadow);
        }

        .hero-main::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 78% 14%, rgba(18, 160, 135, 0.18), transparent 24%),
                radial-gradient(circle at 12% 88%, rgba(244, 124, 72, 0.18), transparent 20%);
            pointer-events: none;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: fit-content;
            padding: 8px 14px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent-ink);
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 14px currentColor;
        }

        .hero-title {
            position: relative;
            margin: 24px 0 18px;
            max-width: 11ch;
            font-family: "Space Grotesk", sans-serif;
            font-size: clamp(3rem, 6vw, 5.5rem);
            line-height: 0.94;
            letter-spacing: -0.055em;
        }

        .hero-title span {
            display: block;
            color: var(--accent);
        }

        .hero-text {
            position: relative;
            max-width: 690px;
            margin: 0;
            color: var(--muted-strong);
            font-size: 1.08rem;
            line-height: 1.82;
        }

        .hero-actions {
            position: relative;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 28px;
        }

        .hero-visual {
            position: relative;
            margin-top: 28px;
            display: grid;
            grid-template-columns: 1fr 220px;
            gap: 14px;
            align-items: end;
        }

        .hero-screen,
        .hero-float {
            position: relative;
            overflow: hidden;
            border-radius: 26px;
            border: 1px solid rgba(145, 170, 196, 0.18);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.82), rgba(242, 247, 251, 0.92));
            box-shadow: 0 20px 42px rgba(19, 42, 71, 0.12);
        }

        .hero-screen {
            min-height: 240px;
            padding: 18px;
        }

        .hero-float {
            min-height: 198px;
            padding: 16px;
            transform: translateY(18px);
        }

        .screen-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .screen-dots {
            display: inline-flex;
            gap: 6px;
        }

        .screen-dots span {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #bfd2e4;
        }

        .screen-pill {
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(15, 123, 108, 0.1);
            color: var(--accent-ink);
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .screen-layout {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 14px;
            min-height: 170px;
        }

        .screen-column,
        .screen-card,
        .screen-log {
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.84);
            border: 1px solid rgba(173, 189, 208, 0.18);
        }

        .screen-column {
            padding: 16px;
            display: grid;
            gap: 12px;
        }

        .screen-card {
            padding: 14px;
        }

        .screen-card strong,
        .screen-float-title {
            display: block;
            margin-bottom: 8px;
            font-size: 0.96rem;
            font-weight: 800;
        }

        .screen-bars {
            display: grid;
            gap: 8px;
        }

        .screen-bar {
            height: 10px;
            border-radius: 999px;
            background: #e5edf4;
            overflow: hidden;
        }

        .screen-bar::after {
            content: "";
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--accent), var(--accent-strong));
        }

        .screen-bar.wide::after {
            width: 82%;
        }

        .screen-bar.mid::after {
            width: 58%;
        }

        .screen-bar.small::after {
            width: 36%;
        }

        .screen-log {
            padding: 16px;
            display: grid;
            gap: 12px;
        }

        .screen-log-item {
            padding: 12px;
            border-radius: 16px;
            background: #f5f9fc;
            border: 1px solid rgba(176, 190, 207, 0.16);
        }

        .screen-log-item strong {
            display: block;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }

        .screen-log-item span,
        .screen-card span,
        .screen-float-copy {
            color: var(--muted-strong);
            font-size: 0.88rem;
            line-height: 1.55;
        }

        .screen-float-badge {
            display: inline-flex;
            margin-bottom: 12px;
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--signal-soft);
            color: #b85b31;
            font-size: 0.74rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .screen-float-metric {
            display: grid;
            gap: 6px;
            margin-top: 14px;
        }

        .screen-float-metric strong {
            font-family: "Space Grotesk", sans-serif;
            font-size: 2rem;
            line-height: 1;
        }

        .screen-float-metric span {
            color: var(--muted-strong);
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .hero-grid {
            position: relative;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: 34px;
        }

        .hero-metric {
            padding: 18px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.58);
            border: 1px solid rgba(164, 183, 208, 0.18);
        }

        .hero-metric strong {
            display: block;
            margin-bottom: 10px;
            font-family: "Space Grotesk", sans-serif;
            font-size: 1.6rem;
            line-height: 1;
        }

        .hero-metric span {
            display: block;
            color: var(--muted-strong);
            font-size: 0.95rem;
            line-height: 1.55;
        }

        .hero-side {
            padding: 28px;
            display: grid;
            gap: 16px;
            min-height: 580px;
        }

        .panel-kicker {
            color: var(--accent);
            font-size: 0.8rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .panel-title {
            margin: 6px 0 0;
            font-size: 1.25rem;
            font-weight: 800;
        }

        .stack {
            display: grid;
            gap: 12px;
        }

        .stack-card {
            display: grid;
            gap: 8px;
            padding: 18px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.62);
            border: 1px solid rgba(160, 178, 203, 0.2);
        }

        .stack-card strong {
            font-size: 1rem;
        }

        .stack-card p {
            margin: 0;
            color: var(--muted-strong);
            font-size: 0.94rem;
            line-height: 1.65;
        }

        .stack-badge {
            width: fit-content;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .stack-badge.local {
            background: var(--accent-soft);
            color: var(--accent-ink);
        }

        .stack-badge.hybrid {
            background: #e9eefb;
            color: #4964a8;
        }

        .stack-badge.api {
            background: var(--signal-soft);
            color: #c15a2d;
        }

        .qa-preview {
            position: relative;
            overflow: hidden;
            padding: 18px;
            border-radius: 26px;
            background:
                radial-gradient(circle at top right, rgba(20, 229, 143, 0.14), transparent 24%),
                linear-gradient(180deg, #121723 0%, #0e1420 100%);
            border: 1px solid rgba(77, 96, 128, 0.42);
            box-shadow: 0 28px 58px rgba(8, 14, 27, 0.34);
            color: #eff5ff;
        }

        .qa-preview::after {
            content: "";
            position: absolute;
            inset: auto -80px -110px auto;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(20, 229, 143, 0.18), transparent 68%);
            pointer-events: none;
        }

        .qa-preview-head {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(73, 89, 117, 0.42);
        }

        .qa-preview-kicker {
            color: #93a5c4;
            font-size: 0.77rem;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .qa-preview-live {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #31e38a;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .qa-preview-live::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 14px currentColor;
        }

        .qa-preview-list {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 14px;
        }

        .qa-preview-card {
            display: grid;
            gap: 12px;
            padding: 16px 18px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.045);
            border: 1px solid rgba(106, 125, 154, 0.24);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        .qa-preview-top {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 14px;
        }

        .qa-preview-name {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 800;
            color: #f5f8ff;
        }

        .qa-preview-meta {
            margin-top: 6px;
            color: #8d9bb5;
            font-size: 0.83rem;
            line-height: 1.5;
        }

        .qa-preview-score {
            min-width: 48px;
            padding: 7px 10px;
            border-radius: 12px;
            border: 1px solid rgba(73, 212, 141, 0.28);
            background: rgba(16, 198, 119, 0.08);
            color: #43e892;
            text-align: center;
            font-family: "Space Grotesk", sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1;
        }

        .qa-preview-score.is-warm {
            border-color: rgba(255, 188, 84, 0.24);
            background: rgba(255, 188, 84, 0.08);
            color: #ffc153;
        }

        .qa-preview-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .qa-preview-tag {
            padding: 6px 9px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(112, 130, 158, 0.16);
            color: #a4b2ca;
            font-size: 0.73rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .qa-preview-metrics {
            display: grid;
            gap: 10px;
        }

        .qa-preview-metric {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 8px 12px;
        }

        .qa-preview-metric-label,
        .qa-preview-metric-value {
            font-size: 0.83rem;
        }

        .qa-preview-metric-label {
            color: #b8c4da;
        }

        .qa-preview-metric-value {
            color: #f3f6ff;
            font-variant-numeric: tabular-nums;
        }

        .qa-preview-track {
            grid-column: 1 / -1;
            height: 6px;
            border-radius: 999px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.06);
        }

        .qa-preview-fill {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #20e18c, #1fc66f);
        }

        .qa-preview-fill.is-amber {
            background: linear-gradient(90deg, #ffb22e, #ffd25c);
        }

        .qa-preview-fill.is-rose {
            background: linear-gradient(90deg, #ff707e, #ff8f7d);
        }

        .section {
            margin-top: 24px;
        }

        .section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
        }

        .section-title {
            margin: 0;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .section-note {
            max-width: 640px;
            margin: 8px 0 0;
            color: var(--muted-strong);
            line-height: 1.7;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .feature-card {
            padding: 26px;
        }

        .feature-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: var(--accent-soft);
            color: var(--accent-ink);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
            margin-bottom: 16px;
        }

        .feature-icon svg {
            width: 24px;
            height: 24px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .feature-title {
            margin: 0 0 10px;
            font-size: 1.22rem;
            font-weight: 800;
        }

        .feature-text {
            margin: 0;
            color: var(--muted-strong);
            line-height: 1.72;
        }

        .story-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 20px;
        }

        .story-card,
        .cta-card {
            padding: 30px;
        }

        .story-list {
            display: grid;
            gap: 14px;
            margin-top: 20px;
        }

        .story-item {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            color: var(--muted-strong);
            line-height: 1.68;
        }

        .story-item::before {
            content: "";
            width: 10px;
            height: 10px;
            margin-top: 8px;
            flex: 0 0 auto;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--signal));
            box-shadow: 0 0 16px rgba(18, 160, 135, 0.28);
        }

        .cta-card {
            display: grid;
            gap: 16px;
            align-content: start;
            background: linear-gradient(180deg, rgba(15, 123, 108, 0.08), rgba(255, 255, 255, 0.88));
        }

        .cta-points {
            display: grid;
            gap: 10px;
        }

        .cta-point {
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.62);
            border: 1px solid rgba(153, 172, 196, 0.18);
            color: var(--muted-strong);
            line-height: 1.6;
        }

        .footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-top: 24px;
            padding: 18px 4px 4px;
            color: var(--muted);
            font-size: 0.93rem;
        }

        .footer-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .footer-links a {
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.56);
            border: 1px solid rgba(158, 178, 205, 0.18);
        }

        @media (max-width: 1080px) {
            .hero,
            .feature-grid,
            .story-grid {
                grid-template-columns: 1fr;
            }

            .hero-main,
            .hero-side {
                min-height: auto;
            }

            .hero-visual {
                grid-template-columns: 1fr;
            }

            .hero-float {
                transform: none;
            }
        }

        @media (max-width: 760px) {
            .site {
                width: min(100% - 20px, 1240px);
                padding-top: 16px;
                padding-bottom: 28px;
            }

            .topbar,
            .footer,
            .section-head {
                flex-direction: column;
                align-items: flex-start;
            }

            .hero-main,
            .hero-side,
            .feature-card,
            .story-card,
            .cta-card {
                border-radius: 26px;
            }

            .hero-main,
            .hero-side,
            .story-card,
            .cta-card {
                padding: 24px;
            }

            .hero-grid {
                grid-template-columns: 1fr;
            }

            .screen-layout {
                grid-template-columns: 1fr;
            }

            .hero-title {
                max-width: none;
                font-size: clamp(2.5rem, 12vw, 4.2rem);
            }

            .topbar-nav,
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
    <main class="site">
        <header class="topbar">
            <div class="brand">
                <div class="brand-mark">YA</div>
                <div class="brand-copy">
                    <div class="brand-title">Yaprofi AI Call Center</div>
                    <div class="brand-subtitle">Розшифровка, оцінювання та контроль якості дзвінків в одному середовищі</div>
                </div>
            </div>

            <nav class="topbar-nav">
                <a href="#capabilities" class="nav-link">Можливості</a>
                <a href="#formats" class="nav-link">Формати роботи</a>
                <a href="#value" class="nav-link">Цінність</a>
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="button-secondary">Увійти</a>
                @endif
                <a href="{{ url('/alt/call-center') }}" class="button">Відкрити сервіс</a>
            </nav>
        </header>

        <section class="hero">
            <article class="hero-main">
                <div class="eyebrow">Локально, гібридно або через API</div>
                <h1 class="hero-title">
                    Сервіс, який
                    <span>перетворює дзвінки на керовану якість</span>
                </h1>
                <p class="hero-text">
                    Yaprofi AI Call Center допомагає бізнесу швидко отримувати транскрибацію, оцінювати розмови за чек-листами,
                    бачити слабкі місця менеджерів і запускати автоматичний контроль якості без важких інтеграцій. Платформа
                    може працювати локально без обов’язкових витрат на API-токени, а за потреби легко підключає платні моделі
                    та зовнішні сервіси для вищої точності або масштабування.
                </p>

                <div class="hero-actions">
                    <a href="{{ url('/alt/call-center') }}" class="button">Спробувати в роботі</a>
                    <a href="#formats" class="button-secondary">Подивитися формати запуску</a>
                </div>

                <div class="hero-visual" aria-hidden="true">
                    <div class="hero-screen">
                        <div class="screen-toolbar">
                            <div class="screen-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                            <div class="screen-pill">AI QA Dashboard</div>
                        </div>
                        <div class="screen-layout">
                            <div class="screen-column">
                                <div class="screen-card">
                                    <strong>Автоматична обробка дзвінків</strong>
                                    <div class="screen-bars">
                                        <div class="screen-bar wide"></div>
                                        <div class="screen-bar mid"></div>
                                        <div class="screen-bar small"></div>
                                    </div>
                                </div>
                                <div class="screen-card">
                                    <strong>Оцінювання за чек-листом</strong>
                                    <span>Послідовні сценарії, контроль структури розмови, рекомендації та прозорий бал по менеджеру.</span>
                                </div>
                            </div>
                            <div class="screen-log">
                                <div class="screen-log-item">
                                    <strong>Whisper / локальна транскрибація</strong>
                                    <span>Отримання тексту розмови без обов’язкових витрат на зовнішні API.</span>
                                </div>
                                <div class="screen-log-item">
                                    <strong>CRM та фільтрація сценаріїв</strong>
                                    <span>Відсів знайомих номерів, календар контролю та пріоритезація черги.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="hero-float">
                        <div class="screen-float-badge">Гібридний стек</div>
                        <strong class="screen-float-title">Локально, але без обмежень</strong>
                        <div class="screen-float-copy">
                            Починайте без токенів, а коли потрібна вища точність або масштабування — підключайте платні моделі точково.
                        </div>
                        <div class="screen-float-metric">
                            <strong>3 режими</strong>
                            <span>Локальний, гібридний та API-first для різних команд, обсягів і вимог до якості.</span>
                        </div>
                    </div>
                </div>

                <div class="hero-grid">
                    <div class="hero-metric">
                        <strong>Локально</strong>
                        <span>Працюйте на власному сервері або робочій машині, коли не хочете залежати від постійних витрат на API.</span>
                    </div>
                    <div class="hero-metric">
                        <strong>Гібридно</strong>
                        <span>Комбінуйте локальний Whisper, Ollama чи Qwen із платними моделями там, де потрібна додаткова якість.</span>
                    </div>
                    <div class="hero-metric">
                        <strong>Автоматично</strong>
                        <span>Запускайте обробку дзвінків за графіком, сценаріями, правилами CRM і маршрутизацією під різні чек-листи.</span>
                    </div>
                </div>
            </article>

            <aside class="hero-side" id="formats">
                <div>
                    <div class="panel-kicker">Як це працює</div>
                    <h2 class="panel-title">Одна платформа для різних моделей і різних бюджетів</h2>
                </div>

                <div class="stack">
                    <div class="stack-card">
                        <div class="stack-badge local">Локальний режим</div>
                        <strong>Без обов’язкової оплати за API-токени</strong>
                        <p>Підійде, якщо потрібно контролювати витрати, тримати дані ближче до себе та будувати стабільний внутрішній контур QA.</p>
                    </div>

                    <div class="stack-card">
                        <div class="stack-badge hybrid">Гібридний режим</div>
                        <strong>Розумний баланс між ціною та якістю</strong>
                        <p>Базові етапи можна виконувати локально, а окремі задачі передавати сильнішим платним моделям тільки там, де це дає реальну користь.</p>
                    </div>

                    <div class="stack-card">
                        <div class="stack-badge api">API-режим</div>
                        <strong>Масштабування під великі навантаження</strong>
                        <p>Коли потрібна швидкість, додаткові моделі або зовнішні сервіси, платформа спокійно вбудовується в дорожчий, але потужніший стек.</p>
                    </div>
                </div>

                <div class="qa-preview" aria-hidden="true">
                    <div class="qa-preview-head">
                        <div class="qa-preview-kicker">AI QA Dashboard</div>
                        <div class="qa-preview-live">Live</div>
                    </div>

                    <div class="qa-preview-list">
                        <div class="qa-preview-card">
                            <div class="qa-preview-top">
                                <div>
                                    <p class="qa-preview-name">Менеджер: Олена К.</p>
                                    <div class="qa-preview-meta">14:32 · 8хв 14с · CRM: #4821</div>
                                </div>
                                <div class="qa-preview-score">84</div>
                            </div>
                            <div class="qa-preview-tags">
                                <span class="qa-preview-tag">Whisper</span>
                                <span class="qa-preview-tag">Чек-лист B2B</span>
                            </div>
                            <div class="qa-preview-metrics">
                                <div class="qa-preview-metric">
                                    <div class="qa-preview-metric-label">Виявлення потреб</div>
                                    <div class="qa-preview-metric-value">90%</div>
                                    <div class="qa-preview-track"><span class="qa-preview-fill" style="width: 90%"></span></div>
                                </div>
                                <div class="qa-preview-metric">
                                    <div class="qa-preview-metric-label">Заперечення</div>
                                    <div class="qa-preview-metric-value">65%</div>
                                    <div class="qa-preview-track"><span class="qa-preview-fill is-amber" style="width: 65%"></span></div>
                                </div>
                                <div class="qa-preview-metric">
                                    <div class="qa-preview-metric-label">Закриття</div>
                                    <div class="qa-preview-metric-value">82%</div>
                                    <div class="qa-preview-track"><span class="qa-preview-fill" style="width: 82%"></span></div>
                                </div>
                            </div>
                        </div>

                        <div class="qa-preview-card">
                            <div class="qa-preview-top">
                                <div>
                                    <p class="qa-preview-name">Менеджер: Дмитро В.</p>
                                    <div class="qa-preview-meta">13:18 · 5хв 40с · CRM: #4819</div>
                                </div>
                                <div class="qa-preview-score is-warm">58</div>
                            </div>
                            <div class="qa-preview-tags">
                                <span class="qa-preview-tag">Whisper</span>
                                <span class="qa-preview-tag">Чек-лист Retail</span>
                            </div>
                            <div class="qa-preview-metrics">
                                <div class="qa-preview-metric">
                                    <div class="qa-preview-metric-label">Виявлення потреб</div>
                                    <div class="qa-preview-metric-value">55%</div>
                                    <div class="qa-preview-track"><span class="qa-preview-fill is-amber" style="width: 55%"></span></div>
                                </div>
                                <div class="qa-preview-metric">
                                    <div class="qa-preview-metric-label">Заперечення</div>
                                    <div class="qa-preview-metric-value">40%</div>
                                    <div class="qa-preview-track"><span class="qa-preview-fill is-rose" style="width: 40%"></span></div>
                                </div>
                            </div>
                        </div>

                        <div class="qa-preview-card">
                            <div class="qa-preview-top">
                                <div>
                                    <p class="qa-preview-name">Менеджер: Іван Т.</p>
                                    <div class="qa-preview-meta">12:05 · 11хв 02с · CRM: #4815</div>
                                </div>
                                <div class="qa-preview-score">91</div>
                            </div>
                            <div class="qa-preview-tags">
                                <span class="qa-preview-tag">GPT-4o</span>
                                <span class="qa-preview-tag">Чек-лист B2B</span>
                            </div>
                            <div class="qa-preview-metrics">
                                <div class="qa-preview-metric">
                                    <div class="qa-preview-metric-label">Виявлення потреб</div>
                                    <div class="qa-preview-metric-value">95%</div>
                                    <div class="qa-preview-track"><span class="qa-preview-fill" style="width: 95%"></span></div>
                                </div>
                                <div class="qa-preview-metric">
                                    <div class="qa-preview-metric-label">Закриття</div>
                                    <div class="qa-preview-metric-value">88%</div>
                                    <div class="qa-preview-track"><span class="qa-preview-fill" style="width: 88%"></span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

        <section class="section" id="capabilities">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Що саме дає сервіс команді продажів і контролю якості</h2>
                    <p class="section-note">
                        Це не просто сторінка з розшифровкою аудіо. Це робочий інструмент, який допомагає менеджерам, керівникам
                        відділів продажів і власникам бізнесу бачити картину по дзвінках швидко, регулярно й без ручного хаосу.
                    </p>
                </div>
            </div>

            <div class="feature-grid">
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 3v12"></path>
                            <path d="M8.5 7.5a3.5 3.5 0 0 1 7 0v4a3.5 3.5 0 0 1-7 0z"></path>
                            <path d="M5 11.5a7 7 0 0 0 14 0"></path>
                            <path d="M9 21h6"></path>
                        </svg>
                    </div>
                    <h3 class="feature-title">Транскрибація дзвінків у зручному робочому циклі</h3>
                    <p class="feature-text">
                        Завантажуйте аудіо, працюйте з посиланнями на записи, запускайте локальну або зовнішню транскрибацію й
                        отримуйте текст розмови без зайвих ручних дій.
                    </p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M4 6h16"></path>
                            <path d="M4 12h10"></path>
                            <path d="M4 18h7"></path>
                            <path d="M17 11l2 2 4-4"></path>
                        </svg>
                    </div>
                    <h3 class="feature-title">Оцінювання за чек-листами та сценаріями</h3>
                    <p class="feature-text">
                        Налаштовуйте власні критерії, промпти та логіку оцінки. Сервіс допомагає не просто зберігати бали, а
                        будувати повторюваний стандарт якості для всієї команди.
                    </p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M4 19h16"></path>
                            <path d="M7 16V9"></path>
                            <path d="M12 16V5"></path>
                            <path d="M17 16v-3"></path>
                        </svg>
                    </div>
                    <h3 class="feature-title">Автообробка, календар контролю та CRM-фільтрація</h3>
                    <p class="feature-text">
                        Слідкуйте, скільки дзвінків уже оброблено, які потрапляють під сценарій, що відсіяно CRM, і як рухається
                        черга впродовж дня чи місяця.
                    </p>
                </article>
            </div>
        </section>

        <section class="section" id="value">
            <div class="story-grid">
                <article class="story-card">
                    <div class="panel-kicker">Для кого це</div>
                    <h2 class="section-title">Інструмент для бізнесу, який хоче контроль без перевантаження процесами</h2>
                    <div class="story-list">
                        <div class="story-item">Для керівника відділу продажів, який хоче бачити якість дзвінків не вибірково, а системно.</div>
                        <div class="story-item">Для власника бізнесу, який не хоче переплачувати за токени там, де задачі можна вирішити локально.</div>
                        <div class="story-item">Для QA або операційної команди, якій потрібні чек-листи, порівняння моделей, контроль сценаріїв і швидкий доступ до розмов.</div>
                        <div class="story-item">Для компаній, що хочуть почати з локального контуру, а потім безболісно додати платні сервіси та масштабування.</div>
                    </div>
                </article>

                <aside class="cta-card">
                    <div class="panel-kicker">Чому це вигідно</div>
                    <h2 class="panel-title">Менше ручної рутини, більше керованих рішень</h2>
                    <div class="cta-points">
                        <div class="cta-point">Не потрібно одразу будувати дорогу інфраструктуру на зовнішніх API, якщо для ваших задач достатньо локального контуру.</div>
                        <div class="cta-point">Не потрібно жертвувати гнучкістю: якщо потрібна сильніша модель, її можна підключити точково, а не платити за все підряд.</div>
                        <div class="cta-point">Не потрібно збирати аналітику вручну: одна система дає тексти, оцінки, рекомендації, календарі обробки та контроль по менеджерах.</div>
                    </div>

                    <div class="hero-actions">
                        <a href="{{ url('/alt/call-center') }}" class="button">Перейти до робочого кабінету</a>
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="button-secondary">Увійти в систему</a>
                        @endif
                    </div>
                </aside>
            </div>
        </section>

        <footer class="footer">
            <div>
                Yaprofi AI Call Center — стартова сторінка багатосторінкового сервісу для транскрибації, оцінювання та автоматизації контролю якості дзвінків.
            </div>
            <div class="footer-links">
                <a href="#capabilities">Можливості</a>
                <a href="#formats">Формати роботи</a>
                <a href="#value">Цінність</a>
                <a href="{{ url('/alt/call-center') }}">Сервіс</a>
            </div>
        </footer>
    </main>
</body>
</html>
