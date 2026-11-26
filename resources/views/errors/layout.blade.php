{{--
    Self-contained on purpose: no database, session, signed-in user, Livewire or built assets,
    so it still renders when one of those is what failed.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ $code }} {{ $title }} - {{ config('app.name') }}</title>
        <style>
            :root { --bg: #fafafa; --card: #ffffff; --border: #e5e5e5; --text: #171717; --muted: #525252; --button: #262626; --button-text: #ffffff; }
            @media (prefers-color-scheme: dark) {
                :root { --bg: #171717; --card: #262626; --border: #404040; --text: #fafafa; --muted: #a3a3a3; --button: #ffffff; --button-text: #171717; }
            }
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; background: var(--bg); color: var(--text); font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
            main { width: 100%; max-width: 28rem; padding: 32px; text-align: center; background: var(--card); border: 1px solid var(--border); border-radius: 12px; }
            .code { margin: 0; font-size: 3rem; font-weight: 700; line-height: 1; }
            h1 { margin: 12px 0 8px; font-size: 1.25rem; }
            p { margin: 0; line-height: 1.5; color: var(--muted); }
            a { display: inline-block; margin-top: 24px; padding: 10px 20px; border-radius: 8px; background: var(--button); color: var(--button-text); font-weight: 600; text-decoration: none; }
        </style>
    </head>
    <body>
        <main>
            <p class="code">{{ $code }}</p>
            <h1>{{ $title }}</h1>
            <p>{{ $message }}</p>
            @if ($showHomeLink ?? true)
                <a href="{{ url('/') }}">{{ __('Go to the start page') }}</a>
            @endif
        </main>
    </body>
</html>
