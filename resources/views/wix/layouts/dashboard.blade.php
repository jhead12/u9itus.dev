<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'U9itus – Political Loyalty Ads')</title>

    {{-- Wix Design System styles --}}
    <style>
        :root {
            --wix-color-primary: #3899EC;
            --wix-color-secondary: #4EB7F5;
            --wix-color-success: #60BC57;
            --wix-color-danger: #EE5951;
            --wix-color-warning: #FAC249;
            --wix-color-bg: #F0F4F7;
            --wix-color-card: #FFFFFF;
            --wix-color-text: #162D3D;
            --wix-color-text-light: #577083;
            --wix-font-family: 'HelveticaNeueW01-45Ligh', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            --wix-radius: 6px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--wix-font-family);
            background: var(--wix-color-bg);
            color: var(--wix-color-text);
            padding: 24px;
            line-height: 1.5;
        }
        .wix-card {
            background: var(--wix-color-card);
            border-radius: var(--wix-radius);
            box-shadow: 0 1px 3px rgba(22,45,61,0.08);
            padding: 24px;
            margin-bottom: 24px;
        }
        .wix-card h2 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--wix-color-text);
        }
        .wix-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        .wix-stat {
            text-align: center;
            padding: 16px;
        }
        .wix-stat .value {
            font-size: 28px;
            font-weight: 700;
            color: var(--wix-color-primary);
        }
        .wix-stat .label {
            font-size: 13px;
            color: var(--wix-color-text-light);
            margin-top: 4px;
        }
        .wix-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 20px;
            border-radius: var(--wix-radius);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: background 0.15s;
        }
        .wix-btn-primary {
            background: var(--wix-color-primary);
            color: #fff;
        }
        .wix-btn-primary:hover { background: #2B81D6; }
        .wix-btn-success {
            background: var(--wix-color-success);
            color: #fff;
        }
        .wix-btn-danger {
            background: var(--wix-color-danger);
            color: #fff;
        }
        .wix-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .wix-badge-active { background: #E5F6E3; color: #3E8236; }
        .wix-badge-pending { background: #FEF4DC; color: #C48A0A; }
        .wix-badge-danger { background: #FDECEB; color: #C73733; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #E5E5E5; }
        th { font-size: 12px; color: var(--wix-color-text-light); text-transform: uppercase; letter-spacing: 0.5px; }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .page-header h1 {
            font-size: 24px;
            font-weight: 700;
        }
        .page-header .subtitle {
            font-size: 14px;
            color: var(--wix-color-text-light);
            margin-top: 4px;
        }
    </style>

    @stack('styles')
</head>
<body>
    @yield('content')

    <script>
        // Base API URL for AJAX calls
        window.D4D = {
            apiBase: '{{ url("/api/v1") }}',
            csrfToken: '{{ csrf_token() }}',
        };
    </script>
    @stack('scripts')
</body>
</html>
