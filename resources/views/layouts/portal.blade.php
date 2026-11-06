<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CargoTrace') · CargoTrace</title>
    <link rel="stylesheet" href="{{ asset('css/cargotrace.css') }}?v={{ filemtime(public_path('css/cargotrace.css')) }}">
    @stack('styles')
</head>
<body>
<div class="portal">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-mark"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2.8 8.2 4.5v9.4L12 21.2l-8.2-4.5V7.3L12 2.8Z"/><path d="m3.8 7.3 8.2 4.6 8.2-4.6M12 11.9v9.3M7.8 5.1l8.4 4.7v4.4l-4.2 2.3-4.2-2.3V5.1Z"/></svg></span>
            <span><strong>CargoTrace</strong><small>Global Shipment Visibility</small></span>
        </a>
        <div class="nav-label">Workspace</div>
        <nav class="nav" aria-label="Navigasi utama">
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="nav-icon">⌂</span>Dashboard</a>
            <a class="{{ request()->routeIs('shipments.*') ? 'active' : '' }}" href="{{ route('shipments.index') }}"><span class="nav-icon">▣</span>Shipments</a>
            <a class="{{ request()->routeIs('tracking.*') ? 'active' : '' }}" href="{{ route('shipments.index') }}"><span class="nav-icon">⌕</span>Tracking</a>
            <a class="{{ request()->routeIs('map') ? 'active' : '' }}" href="{{ route('map') }}"><span class="nav-icon">◈</span>Map View</a>
            <a class="{{ request()->routeIs('exceptions.*') ? 'active' : '' }}" href="{{ route('exceptions.index') }}"><span class="nav-icon">⚠</span>Exceptions</a>
            <a class="{{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><span class="nav-icon">▤</span>Reports</a>
            <a class="{{ request()->routeIs('analytics.*') ? 'active' : '' }}" href="{{ route('analytics.index') }}"><span class="nav-icon">▥</span>Analytics</a>
            @if (auth()->user()->isAdmin())
                <a class="{{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><span class="nav-icon">♙</span>Users</a>
            @endif
            <a class="{{ request()->routeIs('news.*') ? 'active' : '' }}" href="{{ route('news.index') }}"><span class="nav-icon">▧</span>News</a>
            <a class="{{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}"><span class="nav-icon">⚙</span>Settings</a>
        </nav>
        <div class="quick">
            <div class="nav-label">Quick access</div>
            <nav class="nav" aria-label="Akses cepat">
                @if (auth()->user()->isAdmin() || auth()->user()->isOperator())
                    <a href="{{ route('shipments.create') }}"><span class="nav-icon">＋</span>New Shipment</a>
                @endif
                <a href="{{ route('shipments.index') }}"><span class="nav-icon">⌕</span>Track Shipment</a>
                <a href="{{ route('reports.index') }}"><span class="nav-icon">▤</span>View Reports</a>
            </nav>
        </div>
        <div class="sidebar-spacer"></div>
        <a class="profile" href="{{ route('settings.index') }}">
            <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
            <span class="profile-name"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role->value }}</small></span>
            <span aria-hidden="true">⌄</span>
        </a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="logout" type="submit">↪ &nbsp; Keluar</button></form>
    </aside>

    <main class="main">
        <header class="topbar">
            @if (request()->routeIs('dashboard'))
                <form class="top-search dashboard-search" method="GET" action="{{ route('dashboard') }}">
                    <span aria-hidden="true">⌕</span>
                    <input id="dashboard-search-input" type="search" name="search" value="{{ request('search') }}" placeholder="Search shipment, tracking number..." aria-label="Search shipment">
                    <kbd>Ctrl + K</kbd>
                    <button type="submit" aria-label="Search">↵</button>
                </form>
                <div class="top-meta dashboard-top-meta">
                    <span class="live dashboard-live">Live</span>
                    <span class="dashboard-clock"><time id="dashboard-clock"></time><small>UTC</small></span>
                    <a class="dashboard-notifications" href="{{ route('exceptions.index') }}" aria-label="{{ $openExceptionCount }} shipment exceptions">
                        <span aria-hidden="true">♧</span>
                        @if ($openExceptionCount > 0)<b>{{ $openExceptionCount > 99 ? '99+' : $openExceptionCount }}</b>@endif
                    </a>
                    <label class="dashboard-language" aria-label="Dashboard language">
                        <span aria-hidden="true">◎</span>
                        <select id="dashboard-language"><option value="en">EN</option><option value="id">ID</option></select>
                        <span aria-hidden="true">⌄</span>
                    </label>
                    <span class="avatar dashboard-header-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                </div>
            @else
                <form class="top-search" method="GET" action="{{ route('shipments.index') }}">
                    <span aria-hidden="true">⌕</span>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari shipment, tracking number..." aria-label="Cari shipment">
                    <button type="submit">Cari</button>
                </form>
                <div class="top-meta"><span class="live">Aktif</span><span class="timestamp">{{ now()->format('d M Y · H:i') }}</span><span>◎ &nbsp; ID</span><span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span></div>
            @endif
        </header>
        <div class="content">
            @include('portal.partials.flash')
            @yield('content')
        </div>
    </main>
</div>
<nav class="mobile-nav" aria-label="Navigasi seluler">
    <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span>⌂</span>Home</a>
    <a class="{{ request()->routeIs('shipments.*', 'tracking.*') ? 'active' : '' }}" href="{{ route('shipments.index') }}"><span>▣</span>Shipments</a>
    <a class="{{ request()->routeIs('map') ? 'active' : '' }}" href="{{ route('map') }}"><span>◈</span>Map</a>
    <a class="{{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}"><span>⚙</span>Settings</a>
</nav>
@stack('scripts')
</body>
</html>
