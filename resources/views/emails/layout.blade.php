<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #374151;
            line-height: 1.6;
            background-color: #EDE3CC;
            margin: 0;
            padding: 0;
        }
        .email-wrapper {
            padding: 24px 16px;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .email-header {
            background: #FAF5E9;
            color: #2D2318;
            padding: 28px 24px;
            text-align: center;
            border-bottom: 4px solid #C8861F;
        }
        .email-header img {
            max-height: 46px;
            width: auto;
        }
        .email-header h1 {
            color: #2D2318;
            margin: 0;
            font-size: 22px;
            font-weight: 700;
        }
        .email-body {
            padding: 24px;
        }
        h1 {
            color: #2D2318;
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 16px 0;
        }
        h2 {
            color: #374151;
            font-size: 16px;
            font-weight: 600;
            margin: 24px 0 12px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        p {
            margin: 12px 0;
        }
        ul {
            background: #f9fafb;
            border-radius: 6px;
            padding: 16px 16px 16px 36px;
            margin: 16px 0;
        }
        ul li {
            margin-bottom: 8px;
            color: #4b5563;
        }
        ul li:last-child {
            margin-bottom: 0;
        }
        a {
            color: #C8861F;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .btn {
            display: inline-block;
            background: #C8861F;
            color: #ffffff !important;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none!important;
            font-weight: 500;
            margin: 8px 0;
        }
        .btn:hover {
            background: #a86f19;
            text-decoration: none;
        }
        .btn-secondary {
            background: #6b7280;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .highlight-box {
            background: #FAF5E9;
            border-left: 4px solid #C8861F;
            padding: 16px;
            border-radius: 0 6px 6px 0;
            margin: 16px 0;
        }
        .warning-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px;
            border-radius: 0 6px 6px 0;
            margin: 16px 0;
        }
        .signature {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }
        .signature-name {
            font-weight: 600;
            color: #1f2937;
        }
        .signature-details {
            color: #6b7280;
            font-size: 14px;
        }
        .footer {
            background: #f9fafb;
            padding: 16px 24px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
        }
        em {
            font-style: normal;
            font-weight: 600;
            color: #1f2937;
        }
        strong {
            color: #1f2937;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <div class="email-header">
                {{-- Logo Pépite transparent, servi depuis le site public (fiable en email). --}}
                <img src="https://pepite-lausanne.ch/assets/logo-email.png" alt="{{ $owner->contact->display_name() }}">
            </div>

            <div class="email-body">
                @yield('content')

                <div class="signature">
                    <p class="signature-name">{{ $owner->contact->display_name() }}</p>
                    <p class="signature-details">
                        {!! nl2br($owner->contact->street) !!}<br>
                        {{ $owner->contact->zip }} {{ $owner->contact->city }}
                    </p>
                </div>
            </div>

            <div class="footer">
                {{ config('app.name', __('Reservation system')) }}
            </div>
        </div>
    </div>
</body>
</html>
