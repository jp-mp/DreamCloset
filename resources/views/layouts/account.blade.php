<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · DreamCloset</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f8f8f5; color: #263e48; font: 1rem/1.5 system-ui, sans-serif; }
        main { width: min(100% - 2rem, 30rem); margin: 4rem auto; }
        .panel { padding: 2rem; background: white; border: 1px solid #ddded8; border-radius: 1rem; }
        h1 { font-family: Georgia, serif; font-weight: normal; }
        label { display: block; margin-top: 1rem; }
        input { width: 100%; padding: .75rem; border: 1px solid #b4bdbd; border-radius: .5rem; font: inherit; }
        button { margin-top: 1.5rem; padding: .8rem 1.2rem; border: 0; border-radius: .5rem; background: #263e48; color: white; font: inherit; cursor: pointer; }
        a { color: #263e48; }
        .errors { color: #972e21; }
        input:focus-visible, button:focus-visible, a:focus-visible { outline: 3px solid #d77d58; outline-offset: 3px; }
    </style>
</head>
<body>
<main>
    <a href="{{ url('/') }}">DreamCloset</a>
    <section class="panel">
        <h1>@yield('title')</h1>
        @if ($errors->any())
            <div class="errors" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </section>
</main>
</body>
</html>
