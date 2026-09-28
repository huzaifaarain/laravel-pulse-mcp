<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Authorize {{ $client->name }} · {{ config('app.name') }}</title>
    <style>
        :root { --bg: #f6f7f9; --card: #fff; --text: #111827; --muted: #6b7280; --border: #e5e7eb; --accent: #4f46e5; --accent-text: #fff; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0b0f17; --card: #111827; --text: #f3f4f6; --muted: #9ca3af; --border: #1f2937; --accent: #818cf8; --accent-text: #0b0f17; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; background: var(--bg); color: var(--text); font: 15px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { width: 100%; max-width: 420px; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 28px; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        p { margin: 0 0 16px; color: var(--muted); }
        ul { margin: 0 0 24px; padding-left: 20px; }
        li { margin-bottom: 4px; }
        .actions { display: flex; gap: 12px; }
        form { flex: 1; margin: 0; }
        button { width: 100%; height: 40px; border-radius: 8px; font: inherit; font-weight: 600; cursor: pointer; border: 1px solid var(--border); background: transparent; color: var(--text); }
        button.primary { background: var(--accent); border-color: var(--accent); color: var(--accent-text); }
    </style>
</head>
<body>
    <main>
        <h1>Authorize {{ $client->name }}</h1>
        <p>{{ $client->name }} is requesting read-only access to the Laravel Pulse monitoring data of {{ config('app.name') }} on behalf of {{ $user->name ?? $user->email ?? 'your account' }}.</p>

        @if (count($scopes) > 0)
            <p>This application will be able to:</p>
            <ul>
                @foreach ($scopes as $scope)
                    <li>{{ $scope->description }}</li>
                @endforeach
            </ul>
        @endif

        <div class="actions">
            <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit">Cancel</button>
            </form>

            <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="primary">Authorize</button>
            </form>
        </div>
    </main>
</body>
</html>
