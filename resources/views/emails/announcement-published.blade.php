<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comunicado CoSphere</title>
</head>
<body style="margin:0;padding:24px;background:#f4f7fb;font-family:Arial,sans-serif;color:#111827;">
    <main style="max-width:600px;margin:0 auto;padding:28px;background:#ffffff;border-radius:12px;">
        <p style="color:#174b91;font-weight:bold;">CoSphere · Gestão Condominial</p>
        <h1 style="font-size:22px;">{{ $announcement->title }}</h1>
        <div style="line-height:1.6;white-space:pre-wrap;">{{ $announcement->content }}</div>
        <p style="margin-top:24px;"><a href="{{ route('morador.announcements.show', $announcement->id) }}" style="display:inline-block;padding:12px 18px;border-radius:8px;background:#f3701e;color:#ffffff;text-decoration:none;">Ver comunicado no CoSphere</a></p>
    </main>
</body>
</html>
