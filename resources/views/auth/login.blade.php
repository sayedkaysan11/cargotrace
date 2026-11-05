<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - CargoTrace</title>
    <style>
        :root { --navy:#14213D; --cream:#F4EDE0; --coral:#FF6B5B; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
               background:var(--navy); font-family:system-ui,-apple-system,"Segoe UI",sans-serif; }
        .card { background:var(--cream); width:100%; max-width:380px; padding:32px; border-radius:16px; margin:16px; }
        h1 { margin:0 0 4px; color:var(--navy); font-size:24px; }
        p.sub { margin:0 0 24px; color:#4b5568; font-size:14px; }
        label { display:block; margin:14px 0 6px; font-size:14px; color:var(--navy); font-weight:600; }
        input[type=email], input[type=password] { width:100%; padding:10px 12px; border:1px solid #c9c2b4;
               border-radius:8px; font-size:15px; background:#fff; }
        .remember { display:flex; gap:8px; align-items:center; margin-top:14px; font-size:14px; color:var(--navy); }
        button { width:100%; margin-top:20px; padding:12px; border:0; border-radius:8px; cursor:pointer;
                 background:var(--coral); color:var(--navy); font-size:16px; font-weight:700; }
        .error { margin-top:6px; color:#b42318; font-size:13px; }
        .hint { margin-top:18px; font-size:12px; color:#4b5568; }
    </style>
</head>
<body>
<form class="card" method="POST" action="{{ url('/login') }}">
    @csrf
    <h1>CargoTrace</h1>
    <p class="sub">Global Shipment Visibility &amp; Tracking</p>

    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
    @error('email') <div class="error">{{ $message }}</div> @enderror

    <label for="password">Password</label>
    <input id="password" type="password" name="password" required>
    @error('password') <div class="error">{{ $message }}</div> @enderror

    <label class="remember"><input type="checkbox" name="remember" value="1"> Ingat saya</label>

    <button type="submit">Masuk</button>

    @if (app()->environment('local'))
        <div class="hint">Demo (lokal): admin@cargotrace.test / operator1@cargotrace.test / customer1@example.test &mdash; password: <b>password</b></div>
    @endif
</form>
</body>
</html>
