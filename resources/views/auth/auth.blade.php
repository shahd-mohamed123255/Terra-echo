<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Terra Echo | Authentication</title>

    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>

<body data-mode="{{ $mode ?? 'login' }}">

    <div class="background"></div>
    <div class="overlay"></div>

    <a href="{{ route('home') }}" class="logo">Terra Echo</a>

    <main class="auth-container">

        <div class="auth-card">

            <div class="brand">
                <span class="brand-icon">✦</span>
                <span>Terra Echo</span>
            </div>

            <div class="auth-heading">
                <h1 id="form-title">
                    {{ ($mode ?? 'login') === 'login' ? 'Welcome Back' : 'Create Account' }}
                </h1>

                <p id="form-subtitle">
                    {{ ($mode ?? 'login') === 'login'
                        ? 'Sign in to continue your journey'
                        : 'Join us and explore your farm' }}
                </p>
            </div>

            @if (($mode ?? 'login') === 'login')

                <form id="login-form" class="auth-form" method="POST" action="{{ route('login.submit') }}">
                    @csrf

                    <div class="input-group">
                        <label for="login-email">Email Address</label>
                        <input
                            type="email"
                            id="login-email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="Enter your email"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="input-group">
                        <label for="login-password">Password</label>
                        <input
                            type="password"
                            id="login-password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >
                    </div>

                    <button type="submit" class="submit-btn">
                        Login <span>→</span>
                    </button>
                </form>

            @else

                <form id="register-form" class="auth-form" method="POST" action="{{ route('register.submit') }}">
                    @csrf

                    <div class="input-group">
                        <label for="register-name">Full Name</label>
                        <input
                            type="text"
                            id="register-name"
                            name="name"
                            value="{{ old('name') }}"
                            placeholder="Enter your full name"
                            autocomplete="name"
                            required
                        >
                    </div>

                    <div class="input-group">
                        <label for="register-email">Email Address</label>
                        <input
                            type="email"
                            id="register-email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="Enter your email"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="input-group">
                        <label for="register-password">Password</label>
                        <input
                            type="password"
                            id="register-password"
                            name="password"
                            placeholder="Create a password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >
                    </div>

                    <div class="input-group">
                        <label for="confirm-password">Confirm Password</label>
                        <input
                            type="password"
                            id="confirm-password"
                            name="password_confirmation"
                            placeholder="Confirm your password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >
                    </div>

                    <button type="submit" class="submit-btn">
                        Create Account <span>→</span>
                    </button>
                </form>

            @endif

            <p class="switch-text" id="switch-text">
                @if (($mode ?? 'login') === 'login')
                    Don't have an account?
                    <a href="{{ route('register') }}" id="switch-btn">Sign up</a>
                @else
                    Already have an account?
                    <a href="{{ route('login') }}" id="switch-btn">Login</a>
                @endif
            </p>

            <p class="form-message" id="form-message">
                @if ($errors->any()) {{ $errors->first() }} @endif
            </p>

            <a href="{{ route('home') }}" class="back-home">
                ← Back to Home
            </a>

        </div>

    </main>

</body>
</html>