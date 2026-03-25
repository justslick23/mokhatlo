<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'SocietyManager') }} — Cooperative Finance Made Simple</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --ink:       #0f1623;
            --ink-soft:  #3d4a5c;
            --ink-muted: #7c8798;
            --paper:     #f5f3ef;
            --paper-warm:#ede9e2;
            --accent:    #5f76e8;
            --accent-2:  #764ba2;
            --gold:      #c9a84c;
            --gold-light:#f5e6c0;
            --success:   #1dd1a1;
            --danger:    #ff4f70;
            --white:     #ffffff;
            --shadow-sm: 0 2px 8px rgba(15,22,35,.08);
            --shadow-md: 0 8px 32px rgba(15,22,35,.12);
            --shadow-lg: 0 24px 64px rgba(15,22,35,.16);
            --radius:    16px;
            --radius-sm: 10px;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--paper);
            color: var(--ink);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── NOISE TEXTURE OVERLAY ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
        }

        /* ── NAV ── */
        .nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            padding: 0 5%;
            height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(245,243,239,.88);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(15,22,35,.06);
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .nav-logo-mark {
            width: 36px; height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 1rem;
        }

        .nav-logo-text {
            font-family: 'DM Serif Display', serif;
            font-size: 1.25rem;
            color: var(--ink);
            letter-spacing: -.02em;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-ghost {
            padding: 9px 20px;
            border-radius: var(--radius-sm);
            background: transparent;
            border: 1.5px solid rgba(15,22,35,.12);
            color: var(--ink-soft);
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all .2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-ghost:hover {
            background: white;
            border-color: var(--accent);
            color: var(--accent);
            box-shadow: var(--shadow-sm);
        }

        .btn-primary {
            padding: 9px 22px;
            border-radius: var(--radius-sm);
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            border: none;
            color: white;
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all .25s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 14px rgba(95,118,232,.35);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(95,118,232,.45);
            color: white;
        }

        /* ── HERO ── */
        .hero {
            min-height: 100vh;
            padding-top: 72px;
            display: flex;
            align-items: center;
            position: relative;
        }

        /* Decorative blobs */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: .18;
            pointer-events: none;
        }
        .blob-1 {
            width: 560px; height: 560px;
            background: var(--accent);
            top: -100px; right: -150px;
        }
        .blob-2 {
            width: 380px; height: 380px;
            background: var(--gold);
            bottom: 0; left: -100px;
        }
        .blob-3 {
            width: 260px; height: 260px;
            background: var(--success);
            top: 40%; right: 20%;
        }

        .hero-inner {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 80px 5%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: center;
        }

        /* ── EYEBROW ── */
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--gold-light);
            color: #8a6400;
            font-size: .8rem;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            padding: 6px 14px;
            border-radius: 100px;
            margin-bottom: 24px;
            border: 1px solid rgba(201,168,76,.3);
        }
        .eyebrow::before {
            content: '';
            display: block;
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--gold);
        }

        .hero-title {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(2.8rem, 5vw, 4.4rem);
            line-height: 1.08;
            letter-spacing: -.03em;
            color: var(--ink);
            margin-bottom: 24px;
        }

        .hero-title em {
            font-style: italic;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-2) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-desc {
            font-size: 1.1rem;
            line-height: 1.75;
            color: var(--ink-soft);
            margin-bottom: 40px;
            font-weight: 300;
        }

        .hero-ctas {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-hero-primary {
            padding: 16px 32px;
            border-radius: var(--radius);
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            border: none;
            color: white;
            font-family: 'DM Sans', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all .3s cubic-bezier(.4,0,.2,1);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 24px rgba(95,118,232,.4);
        }
        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 40px rgba(95,118,232,.5);
            color: white;
        }

        .btn-hero-secondary {
            padding: 16px 32px;
            border-radius: var(--radius);
            background: white;
            border: 1.5px solid rgba(15,22,35,.12);
            color: var(--ink);
            font-family: 'DM Sans', sans-serif;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all .25s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: var(--shadow-sm);
        }
        .btn-hero-secondary:hover {
            border-color: var(--accent);
            color: var(--accent);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Trust badges */
        .trust-row {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-top: 40px;
            padding-top: 32px;
            border-top: 1px solid rgba(15,22,35,.08);
        }

        .trust-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .85rem;
            color: var(--ink-muted);
            font-weight: 500;
        }

        .trust-badge i {
            color: var(--success);
        }

        /* ── DASHBOARD PREVIEW ── */
        .hero-visual {
            position: relative;
        }

        .preview-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg), 0 0 0 1px rgba(15,22,35,.05);
            overflow: hidden;
            transform: perspective(1200px) rotateY(-6deg) rotateX(2deg);
            transition: transform .5s cubic-bezier(.4,0,.2,1);
        }
        .preview-card:hover {
            transform: perspective(1200px) rotateY(-2deg) rotateX(1deg);
        }

        .preview-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-2) 100%);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            background: rgba(255,255,255,.4);
        }

        .preview-title-bar {
            flex: 1;
            height: 10px;
            background: rgba(255,255,255,.2);
            border-radius: 100px;
            margin-left: 8px;
        }

        .preview-body {
            padding: 20px;
        }

        /* Mini stats in preview */
        .preview-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }

        .preview-stat {
            background: var(--paper);
            border-radius: 12px;
            padding: 14px;
        }

        .preview-stat-label {
            font-size: .7rem;
            color: var(--ink-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 6px;
        }

        .preview-stat-value {
            font-family: 'DM Serif Display', serif;
            font-size: 1.4rem;
            color: var(--ink);
        }

        .preview-stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: .65rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 100px;
            margin-top: 4px;
        }
        .preview-stat-badge.up {
            background: rgba(29,209,161,.12);
            color: #10ac84;
        }
        .preview-stat-badge.warn {
            background: rgba(255,79,112,.12);
            color: var(--danger);
        }

        /* Mini chart bar */
        .preview-chart {
            background: var(--paper);
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 14px;
        }

        .preview-chart-label {
            font-size: .7rem;
            color: var(--ink-muted);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .mini-bars {
            display: flex;
            align-items: flex-end;
            gap: 5px;
            height: 48px;
        }

        .mini-bar {
            flex: 1;
            border-radius: 4px 4px 0 0;
            background: linear-gradient(180deg, var(--accent) 0%, var(--accent-2) 100%);
            opacity: .6;
            transition: opacity .2s;
        }
        .mini-bar:last-child { opacity: 1; }

        /* Preview transactions */
        .preview-rows {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .preview-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: var(--paper);
            border-radius: 10px;
        }

        .preview-avatar {
            width: 28px; height: 28px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            flex-shrink: 0;
        }

        .preview-row-lines {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .preview-line {
            height: 7px;
            background: rgba(15,22,35,.1);
            border-radius: 100px;
        }

        .preview-line.short { width: 60%; }

        .preview-amount {
            font-size: .75rem;
            font-weight: 700;
            color: var(--success);
        }

        /* Floating badge */
        .float-badge {
            position: absolute;
            background: white;
            border-radius: 14px;
            box-shadow: var(--shadow-md);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: float 3s ease-in-out infinite;
        }

        .float-badge-1 {
            top: -20px; right: -30px;
            animation-delay: 0s;
        }

        .float-badge-2 {
            bottom: 40px; left: -40px;
            animation-delay: 1.5s;
        }

        .float-badge-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: .9rem;
            color: white;
        }
        .float-badge-icon.green { background: linear-gradient(135deg, #1dd1a1, #10ac84); }
        .float-badge-icon.blue { background: linear-gradient(135deg, #54a0ff, var(--accent)); }

        .float-badge-text { font-size: .8rem; }
        .float-badge-title { font-weight: 700; color: var(--ink); display: block; }
        .float-badge-sub { color: var(--ink-muted); font-size: .72rem; }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }

        /* ── HOW IT WORKS ── */
        .section {
            padding: 100px 5%;
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .section-label {
            font-size: .8rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 12px;
        }

        .section-title {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(2rem, 4vw, 3rem);
            line-height: 1.1;
            letter-spacing: -.025em;
            color: var(--ink);
            margin-bottom: 16px;
        }

        .section-subtitle {
            font-size: 1.05rem;
            color: var(--ink-soft);
            font-weight: 300;
            line-height: 1.7;
            max-width: 560px;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 60px;
        }

        .step-card {
            background: white;
            border-radius: 20px;
            padding: 36px 28px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(15,22,35,.05);
            position: relative;
            transition: all .3s ease;
        }

        .step-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-md);
        }

        .step-number {
            font-family: 'DM Serif Display', serif;
            font-size: 4rem;
            color: rgba(15,22,35,.06);
            position: absolute;
            top: 16px; right: 20px;
            line-height: 1;
        }

        .step-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 20px;
        }
        .step-icon.purple { background: rgba(95,118,232,.12); color: var(--accent); }
        .step-icon.teal   { background: rgba(29,209,161,.12); color: var(--success); }
        .step-icon.gold   { background: rgba(201,168,76,.12);  color: var(--gold); }

        .step-title {
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--ink);
            margin-bottom: 8px;
        }

        .step-desc {
            font-size: .9rem;
            color: var(--ink-muted);
            line-height: 1.65;
        }

        /* ── FEATURES ── */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 60px;
        }

        .feature-item {
            display: flex;
            gap: 16px;
            padding: 24px;
            background: white;
            border-radius: 16px;
            border: 1px solid rgba(15,22,35,.05);
            box-shadow: var(--shadow-sm);
            transition: all .25s ease;
        }
        .feature-item:hover {
            border-color: rgba(95,118,232,.2);
            box-shadow: 0 4px 16px rgba(95,118,232,.08);
        }

        .feature-icon-wrap {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .feature-text h4 {
            font-size: .95rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 4px;
        }
        .feature-text p {
            font-size: .875rem;
            color: var(--ink-muted);
            line-height: 1.6;
        }

        /* ── CTA SECTION ── */
        .cta-section {
            margin: 0 5% 80px;
            border-radius: 28px;
            background: linear-gradient(135deg, var(--ink) 0%, #1e2d4a 100%);
            padding: 80px 60px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            width: 400px; height: 400px;
            border-radius: 50%;
            background: var(--accent);
            filter: blur(100px);
            opacity: .15;
            top: -100px; right: -100px;
        }

        .cta-section::after {
            content: '';
            position: absolute;
            width: 300px; height: 300px;
            border-radius: 50%;
            background: var(--gold);
            filter: blur(80px);
            opacity: .1;
            bottom: -80px; left: -60px;
        }

        .cta-inner {
            position: relative;
            z-index: 1;
        }

        .cta-section .eyebrow {
            background: rgba(201,168,76,.15);
            color: var(--gold);
            border-color: rgba(201,168,76,.2);
        }

        .cta-title {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(2rem, 4vw, 3.2rem);
            color: white;
            letter-spacing: -.025em;
            line-height: 1.1;
            margin-bottom: 16px;
        }

        .cta-desc {
            color: rgba(255,255,255,.6);
            font-size: 1.05rem;
            font-weight: 300;
            margin-bottom: 40px;
            max-width: 480px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-cta-primary {
            padding: 16px 36px;
            border-radius: var(--radius);
            background: white;
            border: none;
            color: var(--ink);
            font-family: 'DM Sans', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all .25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,.3);
        }
        .btn-cta-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(0,0,0,.4);
            color: var(--accent);
        }

        .btn-cta-secondary {
            padding: 16px 36px;
            border-radius: var(--radius);
            background: rgba(255,255,255,.1);
            border: 1.5px solid rgba(255,255,255,.2);
            color: white;
            font-family: 'DM Sans', sans-serif;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all .25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(8px);
        }
        .btn-cta-secondary:hover {
            background: rgba(255,255,255,.15);
            border-color: rgba(255,255,255,.35);
            color: white;
            transform: translateY(-2px);
        }

        /* ── FOOTER ── */
        .footer {
            padding: 32px 5%;
            border-top: 1px solid rgba(15,22,35,.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .footer-brand {
            font-family: 'DM Serif Display', serif;
            font-size: 1.1rem;
            color: var(--ink);
        }

        .footer-links {
            display: flex;
            gap: 24px;
        }

        .footer-links a {
            font-size: .875rem;
            color: var(--ink-muted);
            text-decoration: none;
            transition: color .2s;
        }
        .footer-links a:hover { color: var(--accent); }

        .footer-copy {
            font-size: .8rem;
            color: var(--ink-muted);
        }

        /* ── DIVIDER ── */
        .section-divider {
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(15,22,35,.08), transparent);
            margin: 0 5%;
            width: 90%;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 992px) {
            .hero-inner {
                grid-template-columns: 1fr;
                gap: 60px;
                text-align: center;
            }
            .hero-ctas { justify-content: center; }
            .trust-row { justify-content: center; }
            .hero-visual { order: -1; }
            .preview-card { transform: perspective(1200px) rotateY(0deg) rotateX(2deg); }
            .float-badge-1 { top: -10px; right: 0; }
            .float-badge-2 { bottom: 20px; left: 0; }
            .steps-grid { grid-template-columns: 1fr; }
            .features-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 640px) {
            .nav-logo-text { display: none; }
            .cta-section { padding: 60px 28px; }
            .footer { flex-direction: column; gap: 16px; text-align: center; }
            .footer-links { flex-wrap: wrap; justify-content: center; }
            .hero-ctas { flex-direction: column; }
            .btn-hero-primary, .btn-hero-secondary { width: 100%; justify-content: center; }
            .trust-row { flex-direction: column; gap: 12px; }
        }

        /* Stagger animations */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .hero-left > * {
            opacity: 0;
            animation: fadeUp .6s cubic-bezier(.4,0,.2,1) forwards;
        }
        .hero-left .eyebrow       { animation-delay: .1s; }
        .hero-left .hero-title    { animation-delay: .2s; }
        .hero-left .hero-desc     { animation-delay: .3s; }
        .hero-left .hero-ctas     { animation-delay: .4s; }
        .hero-left .trust-row     { animation-delay: .5s; }

        .hero-visual {
            opacity: 0;
            animation: fadeUp .8s .4s cubic-bezier(.4,0,.2,1) forwards;
        }
    </style>
</head>
<body>

    <!-- ── NAVIGATION ── -->
    <nav class="nav">
        <a href="{{ url('/') }}" class="nav-logo">
            <div class="nav-logo-mark">
                <i class="fas fa-users"></i>
            </div>
            <span class="nav-logo-text">SocietyManager</span>
        </a>

        <div class="nav-links">
            @if (Route::has('login'))
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn-ghost">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn-ghost">
                        Sign in
                    </a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn-primary">
                            Get started
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    @endif
                @endauth
            @endif
        </div>
    </nav>

    <!-- ── HERO ── -->
    <section class="hero">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>

        <div class="hero-inner">
            <div class="hero-left">
                <span class="eyebrow">Cooperative Finance Platform</span>

                <h1 class="hero-title">
                    Manage your<br>
                    savings society<br>
                    <em>effortlessly</em>
                </h1>

                <p class="hero-desc">
                    Track contributions, issue loans, and manage member accounts — all in one
                    beautifully simple platform built for cooperatives and savings groups.
                </p>

                <div class="hero-ctas">
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn-hero-primary">
                            <i class="fas fa-rocket"></i>
                            Create your society
                        </a>
                    @endif
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="btn-hero-secondary">
                            <i class="fas fa-sign-in-alt"></i>
                            Sign in to account
                        </a>
                    @endif
                </div>

                <div class="trust-row">
                    <div class="trust-badge">
                        <i class="fas fa-check-circle"></i>
                        No setup fees
                    </div>
                    <div class="trust-badge">
                        <i class="fas fa-check-circle"></i>
                        Secure & encrypted
                    </div>
                    <div class="trust-badge">
                        <i class="fas fa-check-circle"></i>
                        Free to start
                    </div>
                </div>
            </div>

            <!-- Dashboard Preview -->
            <div class="hero-visual">
                <div class="float-badge float-badge-1">
                    <div class="float-badge-icon green">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="float-badge-text">
                        <span class="float-badge-title">M 12,450</span>
                        <span class="float-badge-sub">collected this month</span>
                    </div>
                </div>

                <div class="preview-card">
                    <div class="preview-header">
                        <div class="preview-dot"></div>
                        <div class="preview-dot"></div>
                        <div class="preview-dot"></div>
                        <div class="preview-title-bar"></div>
                    </div>
                    <div class="preview-body">
                        <div class="preview-stats">
                            <div class="preview-stat">
                                <div class="preview-stat-label">Members</div>
                                <div class="preview-stat-value">248</div>
                                <span class="preview-stat-badge up"><i class="fas fa-arrow-up"></i> +12%</span>
                            </div>
                            <div class="preview-stat">
                                <div class="preview-stat-label">Overdue</div>
                                <div class="preview-stat-value">3</div>
                                <span class="preview-stat-badge warn"><i class="fas fa-clock"></i> action</span>
                            </div>
                        </div>

                        <div class="preview-chart">
                            <div class="preview-chart-label">Monthly Contributions</div>
                            <div class="mini-bars">
                                <div class="mini-bar" style="height:38%"></div>
                                <div class="mini-bar" style="height:55%"></div>
                                <div class="mini-bar" style="height:45%"></div>
                                <div class="mini-bar" style="height:72%"></div>
                                <div class="mini-bar" style="height:60%"></div>
                                <div class="mini-bar" style="height:88%"></div>
                                <div class="mini-bar" style="height:100%"></div>
                            </div>
                        </div>

                        <div class="preview-rows">
                            <div class="preview-row">
                                <div class="preview-avatar" style="background:linear-gradient(135deg,#1dd1a1,#10ac84)"></div>
                                <div class="preview-row-lines">
                                    <div class="preview-line"></div>
                                    <div class="preview-line short"></div>
                                </div>
                                <span class="preview-amount">+M 500</span>
                            </div>
                            <div class="preview-row">
                                <div class="preview-avatar" style="background:linear-gradient(135deg,#feca57,#f8b500)"></div>
                                <div class="preview-row-lines">
                                    <div class="preview-line"></div>
                                    <div class="preview-line short"></div>
                                </div>
                                <span class="preview-amount">+M 750</span>
                            </div>
                            <div class="preview-row">
                                <div class="preview-avatar" style="background:linear-gradient(135deg,#54a0ff,#5f76e8)"></div>
                                <div class="preview-row-lines">
                                    <div class="preview-line"></div>
                                    <div class="preview-line short"></div>
                                </div>
                                <span class="preview-amount">+M 300</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="float-badge float-badge-2">
                    <div class="float-badge-icon blue">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="float-badge-text">
                        <span class="float-badge-title">Bank-grade security</span>
                        <span class="float-badge-sub">256-bit encryption</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="section-divider"></div>

    <!-- ── HOW IT WORKS ── -->
    <section class="section">
        <div class="section-label">How it works</div>
        <h2 class="section-title">Up and running<br>in minutes</h2>
        <p class="section-subtitle">
            No complicated setup. Create your society, add members, and start tracking everything that matters.
        </p>

        <div class="steps-grid">
            <div class="step-card">
                <span class="step-number">01</span>
                <div class="step-icon purple">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="step-title">Create your society</h3>
                <p class="step-desc">Register your cooperative or savings group and invite your members with a simple email link.</p>
            </div>

            <div class="step-card">
                <span class="step-number">02</span>
                <div class="step-icon teal">
                    <i class="fas fa-coins"></i>
                </div>
                <h3 class="step-title">Track contributions</h3>
                <p class="step-desc">Log monthly contributions, view member balances and generate statements in a single click.</p>
            </div>

            <div class="step-card">
                <span class="step-number">03</span>
                <div class="step-icon gold">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <h3 class="step-title">Manage loans</h3>
                <p class="step-desc">Issue loans, set repayment schedules, and track overdue payments — all with automatic calculations.</p>
            </div>
        </div>
    </section>

    <div class="section-divider"></div>

    <!-- ── FEATURES ── -->
    <section class="section">
        <div class="section-label">Features</div>
        <h2 class="section-title">Everything your<br>society needs</h2>

        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon-wrap" style="background:rgba(95,118,232,.1);color:var(--accent)">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="feature-text">
                    <h4>Real-time reporting</h4>
                    <p>Charts and dashboards that give you instant visibility into your society's financial health.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon-wrap" style="background:rgba(29,209,161,.1);color:var(--success)">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="feature-text">
                    <h4>Role-based access</h4>
                    <p>Chairperson, treasurer, and member roles — each with the right level of access and visibility.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon-wrap" style="background:rgba(201,168,76,.1);color:var(--gold)">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="feature-text">
                    <h4>Statement generation</h4>
                    <p>Generate professional PDF statements for any member or time period in seconds.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon-wrap" style="background:rgba(255,79,112,.1);color:var(--danger)">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="feature-text">
                    <h4>Overdue alerts</h4>
                    <p>Automatic notifications when contributions are late or loan repayments are overdue.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon-wrap" style="background:rgba(84,160,255,.1);color:#54a0ff">
                    <i class="fas fa-sync-alt"></i>
                </div>
                <div class="feature-text">
                    <h4>Multiple cycles</h4>
                    <p>Run several savings cycles simultaneously and switch between them effortlessly.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon-wrap" style="background:rgba(118,75,162,.1);color:var(--accent-2)">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <div class="feature-text">
                    <h4>Mobile-friendly</h4>
                    <p>Works perfectly on phones and tablets — manage your society from anywhere, anytime.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ── CTA SECTION ── -->
    <div class="cta-section">
        <div class="cta-inner">
            <span class="eyebrow">Ready to start?</span>
            <h2 class="cta-title">Join hundreds of<br>societies already growing</h2>
            <p class="cta-desc">
                Start managing your cooperative's finances with clarity and confidence — completely free.
            </p>
            <div class="cta-buttons">
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="btn-cta-primary">
                        <i class="fas fa-rocket"></i>
                        Create free account
                    </a>
                @endif
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="btn-cta-secondary">
                        <i class="fas fa-sign-in-alt"></i>
                        Already have an account
                    </a>
                @endif
            </div>
        </div>
    </div>

    <!-- ── FOOTER ── -->
    <footer class="footer">
        <div class="footer-brand">SocietyManager</div>
        <div class="footer-links">
            <a href="#">Privacy</a>
            <a href="#">Terms</a>
            <a href="#">Support</a>
            <a href="#">Contact</a>
        </div>
        <div class="footer-copy">&copy; {{ date('Y') }} {{ config('app.name', 'SocietyManager') }}. All rights reserved.</div>
    </footer>

</body>
</html>