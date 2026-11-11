<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    <link rel="icon" type="image/png" href="{{ asset('img/cvsu-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/cvsu-auth.css') }}?v=1.8.6">
</head>

<body class="auth-page">
    <div class="login-container">
        {{-- Logo Section --}}
        <div class="logo-section">
            <img src="{{ asset('img/cvsu-logo.png') }}" alt="CVSU Naic Logo" class="logo">
        </div>

        {{-- Header Section --}}
        <div class="header-section">
            <h1 class="main-title">Office of the Director</h1>
            <h2 class="main-title">for Curriculum and Instruction</h2>
            <p class="system-name">Document Management System</p>
        </div>

        {{-- Alert Messages --}}
        @if ($errors->any())
            <div class="alert alert-error">
                <i class='bx bx-error-circle alert-icon'></i>
                <span class="alert-text">{{ $errors->first() }}</span>
                <button type="button" class="alert-close">
                    <i class='bx bx-x'></i>
                </button>
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success">
                <i class='bx bx-check-circle alert-icon'></i>
                <span class="alert-text">{{ session('success') }}</span>
                <button type="button" class="alert-close">
                    <i class='bx bx-x'></i>
                </button>
            </div>
        @endif

        @if ($errors->any() && count($errors) > 1)
            {{-- Surface every field error, matching the legacy alert block --}}
            @foreach ($errors->all() as $message)
                @if ($message !== $errors->first())
                    <div class="alert alert-error">
                        <i class='bx bx-error-circle alert-icon'></i>
                        <span class="alert-text">{{ $message }}</span>
                    </div>
                @endif
            @endforeach
        @endif

        {{-- Login Form --}}
        <form method="POST" action="{{ route('login.attempt') }}" class="login-form" id="loginForm">
            @csrf

            <div class="form-group">
                <label for="username" class="form-label">Username or Email</label>
                <div class="input-container">
                    <i class='bx bx-user input-icon'></i>
                    <input type="text" id="username" name="username" class="form-input @error('username') error @enderror"
                        placeholder="Enter your username or email"
                        value="{{ old('username') }}" required autocomplete="username">
                </div>
                <div class="field-error @error('username') show @enderror" id="username-error">
                    {{ $errors->first('username') ?: 'Please enter a valid username or email' }}
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="input-container">
                    <i class='bx bx-lock-alt input-icon'></i>
                    <input type="password" id="password" name="password"
                        class="form-input @error('password') error @enderror"
                        placeholder="Enter your password" required autocomplete="current-password">
                </div>
                <div class="field-error @error('password') show @enderror" id="password-error">
                    {{ $errors->first('password') ?: 'Password is required' }}
                </div>
            </div>

            {{-- Form Options --}}
            <div class="form-options">
                <label class="checkbox-container">
                    <input type="checkbox" id="remember" name="remember" value="1" @checked(old('remember'))>
                    <span class="checkmark"></span>
                    <span class="checkbox-text">Remember me</span>
                </label>
                <a href="{{ route('password.request') }}" class="forgot-link">Forgot Password?</a>
            </div>

            {{-- Submit Button --}}
            <button type="submit" class="submit-btn" id="loginBtn">
                <span class="btn-text">Sign In</span>
                <div class="btn-loader" style="display: none;">
                    <div class="spinner"></div>
                </div>
            </button>

            {{-- Google OAuth Button (only when configured).
                 The legacy gate was GoogleOAuth::isConfigured(); here it is simply
                 the presence of a client id, which we deliberately did not copy over. --}}
            @if (config('services.google.client_id'))
                <div class="divider">
                    <span class="divider-text">or</span>
                </div>

                <a href="{{ url('/login/google') }}" class="google-btn">
                    <svg class="google-icon" viewBox="0 0 24 24" width="20" height="20">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                    </svg>
                    <span>Continue with Google</span>
                </a>
            @endif
        </form>

        {{-- Divider --}}
        <div class="divider">
            <span class="divider-text">Don't have an account?</span>
        </div>

        {{-- Register Link --}}
        <a href="{{ route('register') }}" class="register-link">
            <i class='bx bx-user-plus'></i>
            <span>Create New Account</span>
        </a>
    </div>

    <script src="{{ asset('js/auth-script.js') }}"></script>
</body>

</html>