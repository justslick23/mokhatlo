
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Welcome') | Mokhatlo</title>

    <script>
        (function () {
            const key = 'mokhatlo.theme';
    
            let savedTheme = null;
    
            try {
                savedTheme = localStorage.getItem(key);
            } catch (error) {
                // Continue using system preference.
            }
    
            const systemDark = window.matchMedia(
                '(prefers-color-scheme: dark)'
            ).matches;
    
            const theme = ['light', 'dark'].includes(savedTheme)
                ? savedTheme
                : (systemDark ? 'dark' : 'light');
    
            document.documentElement.setAttribute(
                'data-bs-theme',
                theme
            );
        })();
    </script>
    
<!-- Bootstrap 5 CSS -->
<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<!-- Bootstrap Icons -->
<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    rel="stylesheet"
>

<!-- Custom Mokhatlo CSS -->
<link rel="stylesheet" href="{{ asset('css/app.css') }}">

</head>

<body>
    <main class="auth-screen">
        <div class="row g-0 min-vh-100">

            <div class="col-lg-6 d-none d-lg-flex
                        auth-intro align-items-center">
                <div class="auth-intro-content">

                    <div class="d-flex align-items-center gap-3 mb-5">
                        <span class="brand-mark bg-white text-primary">
                            M
                        </span>
                        <span class="fs-4 fw-bold">Mokhatlo</span>
                    </div>

                    <div class="eyebrow text-warning mb-3">
                        Society Financial Management
                    </div>

                    <h1>
                        Your society.<br>
                        Your savings.<br>
                        Your future.
                    </h1>

                    <p class="mt-4">
                        One modern platform to manage savings,
                        contributions, loans and member finances
                        with confidence and transparency.
                    </p>

                    <div class="mt-5 pt-4 border-top border-light
                                border-opacity-25">
                        <span class="small text-white-50">
                            Built for modern savings societies.
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 d-flex align-items-center
                        justify-content-center">
                <div class="auth-content">

                    <div class="d-lg-none d-flex
                                align-items-center gap-2 mb-5">
                        <span class="brand-mark">M</span>
                        <strong class="fs-4">Mokhatlo</strong>
                    </div>

                    @if(session('status'))
                        <div class="alert alert-success alert-dismissible fade show"
                             role="alert" data-auto-dismiss>
                            {{ session('status') }}

                            <button type="button"
                                    class="btn-close"
                                    data-bs-dismiss="alert"
                                    aria-label="Close"></button>
                        </div>
                    @endif

                    @if(session('warning'))
                        <div class="alert alert-warning" role="alert">
                            {{ session('warning') }}
                        </div>
                    @endif

                    @yield('content')

                    <div class="text-center mt-5">
                        <small class="muted">
                            &copy; {{ date('Y') }} Mokhatlo
                        </small>
                    </div>

                </div>
            </div>

        </div>
    </main>
    
<!-- Bootstrap JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom Mokhatlo JavaScript -->
<script src="{{ asset('js/app.js') }}"></script>

</body>
</html>
