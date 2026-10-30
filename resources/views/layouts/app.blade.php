
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Dashboard') | Mokhatlo</title>


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

@php
    $currentSociety = request()->attributes->get('currentSociety');

    $availableSocieties = auth()->user()
        ->memberships()
        ->with('society')
        ->where('status', 'active')
        ->whereHas('society', function ($query) {
            $query->where('status', 'active');
        })
        ->get();
@endphp

<div class="app-shell">

    <!-- Desktop sidebar -->
    <aside class="app-sidebar d-none d-lg-block">
        @include('layouts.partials.navigation')
    </aside>

    <!-- Mobile sidebar -->
    <div class="offcanvas offcanvas-start"
         tabindex="-1"
         id="mobileSidebar"
         aria-labelledby="mobileSidebarLabel">

        <div class="offcanvas-header">
            <h2 class="offcanvas-title h5" id="mobileSidebarLabel">
                Menu
            </h2>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="offcanvas"
                    aria-label="Close"></button>
        </div>

        <div class="offcanvas-body">
            @include('layouts.partials.navigation')
        </div>
    </div>

    <div class="app-main">

        <!-- Top Navigation -->
        <header class="app-topbar">

            <div class="d-flex align-items-center gap-3">

                <button
                    class="btn btn-outline-secondary d-lg-none"
                    type="button"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#mobileSidebar"
                    aria-controls="mobileSidebar"
                    aria-label="Open menu">
                    ☰
                </button>

                <div class="d-none d-sm-block">
                    <div class="small muted">Current society</div>

                    <strong>
                        {{ $currentSociety->name }}
                    </strong>
                </div>

            </div>

            <div class="d-flex align-items-center gap-3">

<!-- Theme Toggle -->
<button
    type="button"
    class="btn btn-outline-secondary mk-theme-toggle"
    data-theme-toggle
    aria-label="Dark mode"
    aria-pressed="false"
    title="Switch theme"
>
    <i class="bi bi-moon-stars-fill"
       data-theme-icon
       aria-hidden="true"></i>

    <span class="d-none d-md-inline ms-2"
          data-theme-label>
        Dark mode
    </span>
</button>

                <!-- Society Switcher -->
                <div class="dropdown">

                    <button class="btn btn-outline-secondary
                                   dropdown-toggle"
                            type="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">
                        Switch society
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end">

                        @foreach($availableSocieties as $membership)

                            <li>
                                <form method="POST"
                                      action="{{ route('societies.switch', $membership->society) }}">
                                    @csrf

                                    <button type="submit"
                                            class="dropdown-item
                                            {{ $currentSociety->id === $membership->society_id ? 'active' : '' }}">
                                        {{ $membership->society->name }}
                                    </button>
                                </form>
                            </li>

                        @endforeach

                        <li><hr class="dropdown-divider"></li>

                        <li>
                            <a class="dropdown-item"
                               href="{{ route('societies.create') }}">
                                + Create society
                            </a>
                        </li>

                    </ul>
                </div>

                <div class="avatar-circle"
                     title="{{ auth()->user()->name }}">
                    {{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                </div>

            </div>
        </header>

        <!-- Page Content -->
        <main class="app-content">

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

            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    Please correct the highlighted information.
                </div>
            @endif

            @yield('content')

        </main>
    </div>
</div>

<!-- Bootstrap JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom Mokhatlo JavaScript -->
<script src="{{ asset('js/app.js') }}"></script>

</body>
</html>
