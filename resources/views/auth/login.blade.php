<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · CargoTrace</title>
    <style>
        :root { color-scheme: dark; --navy:#071329; --panel:#0b1a34; --blue:#2874ff; --line:#263b60; --muted:#91a5c7; --text:#eef4ff; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:grid; grid-template-columns:minmax(350px, .82fr) minmax(0,1.18fr); background:var(--navy); color:var(--text); font:14px/1.5 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif; }
        .login-side { position:relative; z-index:1; display:flex; flex-direction:column; justify-content:space-between; padding:34px clamp(25px,6vw,88px); border-right:1px solid #ffffff0d; background:#08152d; }
        .brand { display:flex; align-items:center; gap:11px; }
        .brand-mark { display:grid; width:39px; height:39px; place-items:center; border:1px solid #347bff; border-radius:12px; background:linear-gradient(145deg,#1d64f0,#12274e); color:white; }
        .brand-mark svg { width:27px; height:27px; fill:none; stroke:currentColor; stroke-linecap:round; stroke-linejoin:round; stroke-width:1.35; }
        .brand strong,.brand small { display:block; }
        .brand strong { font-size:15px; }
        .brand small { color:var(--muted); font-size:10px; }
        .login-content { width:100%; max-width:365px; margin:auto; padding:45px 0; }
        .eyebrow { color:#74b8ff; font-size:10px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; }
        h1 { margin:10px 0 5px; font-size:30px; letter-spacing:-.7px; }
        .sub { margin:0 0 27px; color:var(--muted); font-size:12px; }
        label { display:block; margin:15px 0 6px; color:#c0cee3; font-size:11px; font-weight:600; }
        input[type=email],input[type=password] { width:100%; min-height:42px; padding:10px 12px; border:1px solid var(--line); border-radius:8px; outline:0; background:#091831; color:var(--text); font-size:12px; }
        input:focus { border-color:#4389ff; box-shadow:0 0 0 3px #2874ff24; }
        .remember { display:flex; align-items:center; gap:8px; margin-top:14px; color:#aabbd5; font-size:11px; font-weight:400; }
        .remember input { accent-color:var(--blue); }
        button { width:100%; min-height:41px; margin-top:21px; border:1px solid var(--blue); border-radius:8px; background:linear-gradient(100deg,#2472ff,#1d59d0); color:#fff; cursor:pointer; font-size:12px; font-weight:700; box-shadow:0 8px 26px #1764f533; }
        button:hover { filter:brightness(1.1); }
        .error { margin-top:6px; color:#ff9eaa; font-size:10px; }
        .hint { margin-top:20px; padding:10px 11px; border:1px solid #4d70a033; border-radius:8px; background:#102242; color:#a4b8d7; font-size:10px; }
        .hint b { color:#dce8f9; }
        .footer { color:#7086a8; font-size:9px; }
        .visual { position:relative; display:flex; min-height:100vh; flex-direction:column; justify-content:space-between; overflow:hidden; padding:30px clamp(25px,5vw,70px); background:radial-gradient(ellipse at 53% 45%,#102d5c 0,#091a36 45%,#071329 78%); }
        .visual::after { position:absolute; inset:0; background:linear-gradient(90deg,#07132955,transparent 35%),linear-gradient(0deg,#071329,transparent 36%); content:""; pointer-events:none; }
        .visual-top,.visual-copy { position:relative; z-index:1; }
        .visual-top { display:flex; justify-content:flex-end; color:#9cb0d0; font-size:10px; }
        .world { position:absolute; inset:12% 1% 15%; width:98%; height:72%; }
        .visual-copy { max-width:540px; margin:0 0 2vh; }
        .visual-copy h2 { margin:0 0 9px; font-size:clamp(25px,3vw,42px); letter-spacing:-1px; line-height:1.12; }
        .visual-copy p { margin:0; color:#a8bddf; font-size:13px; }
        .mini-stats { display:flex; flex-wrap:wrap; gap:23px; margin-top:20px; }
        .mini-stats strong,.mini-stats small { display:block; }
        .mini-stats strong { font-size:17px; }
        .mini-stats small { margin-top:2px; color:#8ca3c7; font-size:9px; }
        @media(max-width:850px) { body { grid-template-columns:1fr; } .login-side { min-height:100vh; padding:22px max(22px,calc((100vw - 380px)/2)); border:0; } .visual { display:none; } .login-content { padding:55px 0; } }
    </style>
</head>
<body>
<section class="login-side">
    <a class="brand" href="{{ route('login') }}" style="color:inherit;text-decoration:none">
        <span class="brand-mark"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2.8 8.2 4.5v9.4L12 21.2l-8.2-4.5V7.3L12 2.8Z"/><path d="m3.8 7.3 8.2 4.6 8.2-4.6M12 11.9v9.3M7.8 5.1l8.4 4.7v4.4l-4.2 2.3-4.2-2.3V5.1Z"/></svg></span><span><strong>CargoTrace</strong><small>Global Shipment Visibility</small></span>
    </a>
    <form class="login-content" method="POST" action="{{ url('/login') }}">
        @csrf
        <span class="eyebrow">Logistics Control Center</span>
        <h1>Selamat datang</h1>
        <p class="sub">Masuk untuk melihat dan mengelola perjalanan shipment Anda.</p>
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        @error('email') <div class="error">{{ $message }}</div> @enderror
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required>
        @error('password') <div class="error">{{ $message }}</div> @enderror
        <label class="remember"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
        <button type="submit">Masuk ke Dashboard &nbsp; →</button>
        @if (app()->environment('local'))
            <div class="hint">Akun demo lokal: <b>admin@cargotrace.test</b> · password: <b>password</b></div>
        @endif
    </form>
    <footer class="footer">© {{ now()->year }} CargoTrace · Global Shipment Visibility</footer>
</section>
<section class="visual" aria-label="Informasi CargoTrace">
    <div class="visual-top"><span>● &nbsp; Sistem CargoTrace</span></div>
    <svg class="world" viewBox="0 0 1000 550" role="img" aria-label="Peta visual jaringan logistik global">
        <defs>
            <radialGradient id="ocean"><stop stop-color="#1b4e89" stop-opacity=".85"/><stop offset="1" stop-color="#0b1b38" stop-opacity=".2"/></radialGradient>
            <filter id="soft-glow"><feGaussianBlur stdDeviation="9"/></filter>
        </defs>
        <ellipse cx="510" cy="275" rx="475" ry="235" fill="url(#ocean)" opacity=".55"/>
        <g fill="#3569a4" fill-opacity=".52" stroke="#77b6ed" stroke-opacity=".23" stroke-width="1.5">
            <path d="m105 155 50-42 71 8 44 24 31-4 30 30-28 32-35 5-23 32-29 5-24 39-30-13-19-40-29-18-17-32-30-17Z"/>
            <path d="m278 290 33 4 25 32-5 46-25 34-15 54-23-12-9-44-18-31 6-43Z"/>
            <path d="m443 151 37-30 55 3 35 28-19 24-36-3-13 25-29-7-13-25-27 3Z"/>
            <path d="m458 219 45-18 46 11 28 40-23 57-39 40-26-16-5-50-31-20-15-33Z"/>
            <path d="m543 159 50-45 87 5 47 23 71-7 64 31-24 30-70 2-47 30-49-15-39 25-34-16-30-37-44-5Z"/>
            <path d="m733 286 44-21 58 19 36 34-20 24-59-7-42-21Z"/>
        </g>
        <g fill="none" stroke="#4aaeff" stroke-width="2" opacity=".8">
            <path d="M202 197 Q364 57 506 218 T790 202"/>
            <path d="M314 334 Q468 160 641 257 T768 333"/>
            <path d="M202 197 Q475 440 790 202"/>
        </g>
        <g fill="#a5efff">
            <circle cx="202" cy="197" r="5"/><circle cx="506" cy="218" r="5"/><circle cx="790" cy="202" r="5"/><circle cx="314" cy="334" r="5"/><circle cx="641" cy="257" r="5"/><circle cx="768" cy="333" r="5"/>
        </g>
        <g fill="#49baff" opacity=".45" filter="url(#soft-glow)">
            <circle cx="202" cy="197" r="17"/><circle cx="506" cy="218" r="17"/><circle cx="790" cy="202" r="17"/>
        </g>
    </svg>
    <div class="visual-copy">
        <h2>Global Shipment<br>Visibility &amp; Tracking</h2>
        <p>Pantau pergerakan kargo, lihat status terbaru, dan jaga operasional tetap terhubung di seluruh dunia.</p>
        <div class="mini-stats"><span><strong>Real-time</strong><small>Pembaruan tracking</small></span><span><strong>Global</strong><small>Rute terhubung</small></span><span><strong>Terpercaya</strong><small>Visibilitas shipment</small></span></div>
    </div>
</section>
</body>
</html>
