<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Maintenance — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #f9fafb;
            color: #111827;
            font-family: system-ui, -apple-system, sans-serif;
        }

        .card {
            width: 100%;
            max-width: 480px;
            padding: 40px 32px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 8px 30px rgba(0, 0, 0, .04);
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 32px;
            font-size: 24px;
            font-weight: 700;
        }

        .brand img {
            width: 48px;
            height: 48px;
        }

        .icon {
            width: 36px;
            height: 36px;
            margin-bottom: 16px;
            color: #6b7280;
        }

        h1 {
            margin: 0 0 12px;
            font-size: 26px;
            line-height: 1.3;
        }

        p {
            margin: 0;
            color: #6b7280;
            line-height: 1.7;
        }

        .logout-form {
            margin-top: 24px;
        }

        .logout-button {
            display: flex;
            width: 100%;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border: 0;
            border-radius: 8px;
            background: #111827;
            color: #fff;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            line-height: 20px;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .05);
        }

        .logout-button:hover {
            background: #1f2937;
        }

        footer {
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #9ca3af;
            font-size: 13px;
        }
    </style>
</head>
<body>
<main class="card">
    <div class="brand">
        <img src="{{ asset('images/logo.png') }}" alt="">
        <span>{{ config('app.name') }}</span>
    </div>

    <svg class="icon" xmlns="http://www.w3.org/2000/svg"
         viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="1.5"
         stroke-linecap="round" stroke-linejoin="round"
         aria-hidden="true">
        <path d="M14.7 6.3a4 4 0 0 0-5.4 5.4l-6.6 6.6a2 2 0 0 0 2.8 2.8l6.6-6.6a4 4 0 0 0 5.4-5.4L15 11l-3-3z"/>
    </svg>

    <h1>We'll be right back!</h1>

    <p>
        MyMilestones is undergoing scheduled maintenance.
        Please check back in a few minutes.
    </p>

    @auth
        <form method="POST" action="{{ route('logout') }}" class="logout-form">
            @csrf

            <button type="submit" class="logout-button">
                Log out
            </button>
        </form>
    @endauth

    <footer>
        &copy; {{ config('app.name') }}
    </footer>
</main>
</body>
</html>
