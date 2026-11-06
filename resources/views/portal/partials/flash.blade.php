@if (session('status'))
    <div class="flash success" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="flash error" role="alert">
        <strong>Periksa kembali data yang dimasukkan.</strong>
        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
