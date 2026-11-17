<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Elite · Conectar Office</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
               font-family: "Segoe UI", system-ui, -apple-system, sans-serif; background: #f1f5f9; color: #334155; }
        .card { width: 100%; max-width: 380px; background: #fff; border: 1px solid #e2e8f0; border-radius: 24px; overflow: hidden;
                box-shadow: 0 10px 40px rgba(0, 0, 0, .06); }
        .head { background: linear-gradient(90deg, #004C6C, #005981, #003850); color: #fff; padding: 22px 24px; }
        .head h1 { margin: 0; font-size: 19px; font-weight: 800; letter-spacing: -.01em; }
        .head p { margin: 4px 0 0; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; color: rgba(255,255,255,.65); }
        form, .body { padding: 22px 24px 24px; }
        label { display: block; font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: .12em; margin: 0 0 6px 4px; }
        input { width: 100%; padding: 12px 14px; border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 14px; font-size: 14px; font-weight: 600; color: #1e293b; margin-bottom: 16px; outline: none; }
        input:focus { border-color: #004C6C; background: #fff; }
        button { width: 100%; padding: 13px; border: 0; border-radius: 14px; background: #EE9D4C; color: #fff; font-size: 14px; font-weight: 800; cursor: pointer; }
        button:hover { background: #e08a35; }
        .hint { font-size: 11px; color: #94a3b8; font-weight: 600; margin: -8px 0 18px 4px; line-height: 1.5; }
        .error { background: #fff1f2; border: 1px solid #ffe4e6; color: #e11d48; border-radius: 12px; padding: 10px 12px; font-size: 12px; font-weight: 700; margin-bottom: 16px; }
        .ok { text-align: center; }
        .ok .check { width: 54px; height: 54px; border-radius: 50%; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; margin: 4px auto 14px; font-size: 28px; font-weight: 800; }
        .ok p { font-size: 14px; line-height: 1.5; margin: 0; }
    </style>
</head>
<body>
<div class="card">
    <div class="head">
        <h1>Gestión Documental</h1>
        <p>Conectar Office con Elite</p>
    </div>
    @if (!empty($done))
        <div class="body ok">
            <div class="check">✓</div>
            <p><strong>Listo, Office quedó conectado.</strong><br>Ya puedes cerrar esta ventana si no se cierra sola.</p>
        </div>
    @else
        <form method="post" action="{{ \App\Http\Modules\Documental\Controller\DavLoginController::loginUrl() }}" autocomplete="on">
            @if ($error)
                <div class="error">{{ $error }}</div>
            @endif
            <label for="email">Correo</label>
            <input id="email" name="email" type="email" value="{{ $email }}" required autofocus autocomplete="username">
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">
            <p class="hint">Tu contraseña de Elite o una clave de escritorio. Office recordará la sesión por {{ \App\Http\Modules\Documental\Controller\DavLoginController::COOKIE_DAYS }} días.</p>
            <button type="submit">Iniciar sesión</button>
        </form>
    @endif
</div>
</body>
</html>
