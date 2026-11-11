<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - CVSU Naic</title>
    <link rel="icon" type="image/png" href="{{ asset('img/cvsu-logo.png') }}">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="{{ asset('css/cvsu-auth.css') }}?v=1.8.6">
</head>

<body class="auth-page">
    <div class="login-container">
        <div class="logo-container">
            <img src="{{ asset('img/cvsu-logo.png') }}" alt="CVSU Naic Logo" class="img-logo">
        </div>

        <div class="login-header">
            <h3>Reset Your Password</h3>
            @if ($validToken && $user)
                <p>Hello {{ $user->name }}, create a new password for your account.</p>
            @else
                <p>Set a new password for your account</p>
            @endif
        </div>

        @if ($error)
            <div class="alert alert-error">
                <i class='bx bx-error-circle'></i>
                {{ $error }}
            </div>
        @endif

        @if (!empty($success))
            <div class="alert alert-success">
                <i class='bx bx-check-circle'></i>
                {{ $success }}
            </div>
        @endif

        @if ($validToken)
            <form method="POST" action="{{ route('password.update', $token) }}" id="resetForm">
                @csrf

                <div class="form-group">
                    <label for="password" class="required">New Password</label>
                    <i class='bx bx-lock-alt'></i>
                    <input type="password" id="password" name="password" class="form-input"
                        placeholder="Enter your new password" required>
                    <button type="button" class="password-toggle" onclick="togglePassword('password')">
                        <i class='bx bx-hide' id="password-icon"></i>
                    </button>
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <ul class="password-requirements">
                            <li id="req-length"><i class='bx bx-x'></i> At least 8 characters</li>
                            <li id="req-upper"><i class='bx bx-x'></i> Uppercase letter</li>
                            <li id="req-lower"><i class='bx bx-x'></i> Lowercase letter</li>
                            <li id="req-number"><i class='bx bx-x'></i> Number</li>
                        </ul>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="required">Confirm New Password</label>
                    <i class='bx bx-lock-alt'></i>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input"
                        placeholder="Confirm your new password" required>
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                        <i class='bx bx-hide' id="confirm_password-icon"></i>
                    </button>
                    <div class="field-error" id="confirmError"></div>
                </div>

                <button type="submit" class="login-button" id="resetBtn">
                    <span class="spinner"></span>
                    Reset Password
                </button>
            </form>
        @endif

        <div class="divider">
            <span>Remember your password?</span>
        </div>

        <div class="register-link">
            <a href="{{ route('login') }}">Back to Sign In</a>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-icon');

            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('bx-hide');
                icon.classList.add('bx-show');
            } else {
                field.type = 'password';
                icon.classList.remove('bx-show');
                icon.classList.add('bx-hide');
            }
        }

        // Password strength checker
        document.getElementById('password').addEventListener('input', function () {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            const strengthFill = document.getElementById('strengthFill');

            // Check requirements
            const requirements = {
                length: password.length >= 8,
                upper: /[A-Z]/.test(password),
                lower: /[a-z]/.test(password),
                number: /\d/.test(password)
            };

            // Update requirement indicators
            Object.keys(requirements).forEach(req => {
                const element = document.getElementById(`req-${req}`);
                const icon = element.querySelector('i');

                if (requirements[req]) {
                    element.classList.add('valid');
                    icon.className = 'bx bx-check';
                } else {
                    element.classList.remove('valid');
                    icon.className = 'bx bx-x';
                }
            });

            // Calculate strength
            const validCount = Object.values(requirements).filter(Boolean).length;
            const strength = validCount / 4;

            // Update strength bar
            strengthFill.style.width = (strength * 100) + '%';

            // Update strength class
            strengthBar.className = 'password-strength';
            if (strength < 0.5) {
                strengthBar.classList.add('strength-weak');
            } else if (strength < 1) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        });

        // Confirm password validation
        document.getElementById('confirm_password').addEventListener('input', function () {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const errorDiv = document.getElementById('confirmError');

            if (confirmPassword && password !== confirmPassword) {
                errorDiv.textContent = 'Passwords do not match';
                errorDiv.classList.add('show');
            } else {
                errorDiv.classList.remove('show');
            }
        });

        document.getElementById('resetForm').addEventListener('submit', function (e) {
            const btn = document.getElementById('resetBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function () {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });
    </script>
</body>

</html>