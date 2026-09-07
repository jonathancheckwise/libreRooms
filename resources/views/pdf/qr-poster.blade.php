<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        html, body { margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; color: #2D2318; }
        .sheet {
            width: 100%;
            text-align: center;
            padding: 110px 60px 40px;
            box-sizing: border-box;
        }
        .bar { height: 12px; background: #C8861F; width: 100%; }
        .kicker {
            text-transform: uppercase; letter-spacing: 6px;
            color: #C8861F; font-size: 16px; font-weight: bold;
            margin: 0 0 10px;
        }
        .title { font-size: 46px; font-weight: bold; margin: 0 0 6px; }
        .sub { font-size: 18px; color: #6b7280; margin: 0 0 30px; }
        .qr-box {
            display: inline-block;
            border: 3px solid #EDE3CC;
            border-radius: 24px;
            padding: 24px;
        }
        .qr-box img { width: 360px; height: 360px; }
        .hint { font-size: 22px; margin: 36px auto 0; max-width: 460px; line-height: 1.4; }
        .footer { margin-top: 40px; font-size: 14px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="bar"></div>
    <div class="sheet">
        <p class="kicker">{{ $subtitle }}</p>
        <h1 class="title">{{ $title }}</h1>
        <p class="sub">{{ __('Rooms planning') }}</p>

        <div class="qr-box">
            <img src="{{ $qr }}" alt="QR">
        </div>

        <p class="hint">{{ $hint }}</p>
        <p class="footer">reservations.pepite-lausanne.ch</p>
    </div>
</body>
</html>
