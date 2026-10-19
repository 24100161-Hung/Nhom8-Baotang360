<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} – BaoTang360</title>
    <style>
        :root { --bg:#F5F4EE; --surface:#FFFFFF; --text:#17201D; --muted:#4F5D57; --primary:#1D5C4B; --border:#D5D6CB; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0E1413; --surface:#161E1C; --text:#E6EEEA; --muted:#A8B7B0; --primary:#63C7A6; --border:#2E3A36; } }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:24px; padding:16px;
               background:var(--bg); color:var(--text); font-family:"Be Vietnam Pro", system-ui, sans-serif; }
        main { max-width:520px; width:100%; background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:32px; text-align:center; }
        .code { font-family:"Playfair Display", Georgia, serif; font-size:64px; font-weight:700; color:var(--primary); margin:0; line-height:1; }
        p { line-height:1.6; }
        .muted { color:var(--muted); font-size:14px; }
        a { display:inline-block; margin-top:16px; padding:10px 20px; border-radius:999px; background:var(--primary); color:#fff; text-decoration:none; font-weight:600; }
        a:focus-visible { outline:3px solid var(--text); outline-offset:2px; }
        @media (prefers-color-scheme: dark) { a { color:#0B1210; } }
        .bt360-academic-footer { max-width:640px; text-align:center; color:var(--muted); font-size:12px; line-height:1.6; }
        .bt360-academic-footer p { margin:2px 0; }
    </style>
</head>
<body>
    <main>
        <p class="code">{{ $code }}</p>
        @if (app()->getLocale() === 'en')
            <p>{{ $messageEn }}</p>
            <p class="muted">{{ $messageVi }}</p>
            <a href="/en">Back to home</a>
        @else
            <p>{{ $messageVi }}</p>
            <p class="muted">{{ $messageEn }}</p>
            <a href="/vi">Về trang chủ</a>
        @endif
    </main>
    @include('partials.academic-footer')
</body>
</html>
