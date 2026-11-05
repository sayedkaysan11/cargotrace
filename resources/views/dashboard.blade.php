<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - CargoTrace</title>
    <style>
        :root { --navy:#14213D; --cream:#F4EDE0; --coral:#FF6B5B; }
        body { margin:0; background:var(--cream); color:var(--navy); font-family:system-ui,-apple-system,"Segoe UI",sans-serif; }
        header { background:var(--navy); color:#fff; padding:16px 24px; display:flex; justify-content:space-between; align-items:center; }
        main { padding:32px 24px; max-width:720px; }
        .badge { display:inline-block; background:var(--coral); color:var(--navy); font-weight:700;
                 padding:2px 10px; border-radius:999px; font-size:13px; text-transform:uppercase; }
        .box { background:#fff; border-radius:12px; padding:20px; margin-top:20px; }
        button { background:transparent; border:1px solid #fff; color:#fff; padding:6px 14px; border-radius:8px; cursor:pointer; }
    </style>
</head>
<body>
<header>
    <strong>CargoTrace</strong>
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Keluar</button></form>
</header>
<main>
    <h1>Halo, {{ $user->name }}</h1>
    <p>Role: <span class="badge">{{ $user->role->value }}</span></p>
    <div class="box">
        Shipment yang bisa kamu lihat: <strong>{{ $shipmentCount }}</strong>
        <p style="margin:8px 0 0;font-size:14px;color:#4b5568;">
            Admin dan operator melihat semua shipment. Customer hanya melihat miliknya sendiri.
        </p>
    </div>
    <p style="margin-top:24px;font-size:14px;color:#4b5568;">Halaman placeholder. Dashboard sungguhan dibuat di milestone UI.</p>
</main>
</body>
</html>
