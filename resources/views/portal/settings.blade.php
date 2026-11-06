@extends('layouts.portal')

@section('title', 'Settings')

@section('content')
    <div class="page-heading">
        <div><h1>Settings</h1><p>Kelola informasi akun yang digunakan di CargoTrace.</p></div>
    </div>
    <div class="settings-layout">
        <nav class="panel settings-menu" aria-label="Settings sections">
            <div class="panel-head"><h2>Settings</h2></div>
            <a class="active" href="#profile">General Settings<span>Profile information</span></a>
            <a href="#security">Security<span>Password and sign-in</span></a>
            <a href="#access">Account Access<span>Role and account status</span></a>
        </nav>
        <div class="settings-content">
        <section class="panel" id="profile">
            <div class="panel-head"><h2>Profil Akun</h2><span class="text-link">Informasi dasar</span></div>
            <form class="panel-body" method="POST" action="{{ route('settings.update') }}">
                @csrf @method('PUT')
                <div class="form-grid">
                    <div class="form-group full"><label for="name">Nama</label><input class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="255" required></div>
                    <div class="form-group full"><label for="email">Email</label><input class="form-control" id="email" type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="255" required></div>
                    <div class="form-group"><label for="company_name">Perusahaan</label><input class="form-control" id="company_name" name="company_name" value="{{ old('company_name', $user->company_name) }}" maxlength="255"></div>
                    <div class="form-group"><label for="phone">Telepon</label><input class="form-control" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30"></div>
                </div>
                <div class="toolbar" style="justify-content:flex-end;margin-top:16px"><button class="btn primary" type="submit">Simpan Profil</button></div>
            </form>
        </section>
        <section class="panel" id="security">
            <div class="panel-head"><h2>Keamanan Akun</h2><span class="text-link">Ganti password</span></div>
            <form class="panel-body" method="POST" action="{{ route('settings.update') }}">
                @csrf @method('PUT')
                <input type="hidden" name="name" value="{{ $user->name }}">
                <input type="hidden" name="email" value="{{ $user->email }}">
                <input type="hidden" name="company_name" value="{{ $user->company_name }}">
                <input type="hidden" name="phone" value="{{ $user->phone }}">
                <div class="form-grid">
                    <div class="form-group full"><label for="current_password">Password saat ini</label><input class="form-control" id="current_password" type="password" name="current_password" autocomplete="current-password"></div>
                    <div class="form-group"><label for="password">Password baru</label><input class="form-control" id="password" type="password" name="password" autocomplete="new-password" minlength="8"></div>
                    <div class="form-group"><label for="password_confirmation">Konfirmasi password baru</label><input class="form-control" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8"></div>
                </div>
                <div class="form-help" style="margin-top:8px">Kosongkan kedua field password untuk mempertahankan password saat ini.</div>
                <div class="toolbar" style="justify-content:flex-end;margin-top:16px"><button class="btn primary" type="submit">Perbarui Password</button></div>
            </form>
        </section>
    <section class="panel" id="access">
        <div class="panel-head"><h2>Informasi Akses</h2><span class="text-link">Role: {{ str($user->role->value)->title() }}</span></div>
        <div class="panel-body grid-three">
            <div><span class="form-help">AKUN</span><div class="primary-text">{{ $user->name }}</div><span class="secondary-text">{{ $user->email }}</span></div>
            <div><span class="form-help">HAK AKSES</span><div class="primary-text">{{ str($user->role->value)->title() }}</div><span class="secondary-text">{{ $user->isAdmin() ? 'Akses administrator' : ($user->isOperator() ? 'Akses operasional' : 'Akses customer') }}</span></div>
            <div><span class="form-help">STATUS</span><div><span class="status {{ $user->is_active ? 'active' : 'cancelled' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span></div><span class="secondary-text">Akun dibuat {{ $user->created_at->format('d M Y') }}</span></div>
        </div>
    </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const menu = document.querySelector('.settings-menu');
            if (!menu) return;

            const links = Array.from(menu.querySelectorAll('a[href^="#"]'));
            const updateActiveLink = () => {
                const activeHash = window.location.hash || '#profile';
                links.forEach(link => link.classList.toggle('active', link.hash === activeHash));
            };

            menu.addEventListener('click', event => {
                if (event.target.closest('a[href^="#"]')) updateActiveLink();
            });
            window.addEventListener('hashchange', updateActiveLink);
            updateActiveLink();
        })();
    </script>
@endpush
