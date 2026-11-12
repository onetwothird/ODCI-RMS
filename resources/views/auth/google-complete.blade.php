<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finish Registration - CVSU Naic</title>
    <link rel="icon" type="image/png" href="{{ asset('img/cvsu-logo.png') }}">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="{{ asset('css/register.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cvsu-auth.css') }}?v=1.8.6">
</head>

<body class="auth-page">
    <div class="login-container">
        <div class="container-header">

            <div class="logo-section">
                <img src="{{ asset('img/cvsu-logo.png') }}" alt="CVSU Naic Logo" class="logo">
            </div>

            <div class="header-section">
                <h1 class="main-title">ODCI Document Management System</h1>
                <p class="sub-title">Finish your registration - {{ $user->email }}</p>
            </div>

            <div class="wizard-progress">
                <div class="progress-step active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-label">Personal</div>
                </div>
                <div class="progress-step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-label">Employment</div>
                </div>
            </div>

            @if ($errors->any())
                <div class="alert alert-error">
                    <i class='bx bx-error-circle alert-icon'></i>
                    <span class="alert-text">Please correct the errors below.</span>
                    <button type="button" class="alert-close">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-error">
                    <i class='bx bx-error-circle alert-icon'></i>
                    <span class="alert-text">{{ session('error') }}</span>
                    <button type="button" class="alert-close">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
            @endif
        </div>

        <div class="container-content">

            <form method="POST" action="{{ route('oauth.google.complete.store') }}" class="login-form" id="registerForm">
                @csrf

                <div class="wizard-step active" data-step="1">
                    <div class="form-group">
                        <label for="name" class="form-label">First Name</label>
                        <div class="input-container">
                            <i class='bx bx-user input-icon'></i>
                            <input type="text" id="name" name="name" class="form-input" placeholder="First name"
                                value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div class="field-error @error('name') show @enderror">
                            {{ $errors->first('name') ?: 'First name is required' }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="mi" class="form-label">Middle Initial</label>
                        <div class="input-container">
                            <i class='bx bx-user input-icon'></i>
                            <input type="text" id="mi" name="mi" class="form-input" maxlength="5" placeholder="M.I."
                                value="{{ old('mi', $user->mi) }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="surname" class="form-label">Last Name</label>
                        <div class="input-container">
                            <i class='bx bx-user input-icon'></i>
                            <input type="text" id="surname" name="surname" class="form-input" placeholder="Last name"
                                value="{{ old('surname', $user->surname) }}" required>
                        </div>
                        <div class="field-error @error('surname') show @enderror">
                            {{ $errors->first('surname') ?: 'Last name is required' }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                        <div class="input-container">
                            <i class='bx bx-calendar input-icon'></i>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-input"
                                value="{{ old('date_of_birth', optional($user->date_of_birth)->format('Y-m-d') ?? $user->date_of_birth) }}">
                        </div>
                        <div class="field-error @error('date_of_birth') show @enderror">
                            {{ $errors->first('date_of_birth') }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="phone" class="form-label">Phone Number</label>
                        <div class="input-container">
                            <i class='bx bx-phone input-icon'></i>
                            <input type="tel" id="phone" name="phone" class="form-input" placeholder="+63 xxx xxx xxxx"
                                value="{{ old('phone', $user->phone) }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address" class="form-label">Address</label>
                        <div class="input-container">
                            <i class='bx bx-map input-icon'></i>
                            <input type="text" id="address" name="address" class="form-input"
                                placeholder="Complete address"
                                value="{{ old('address', $user->address) }}">
                        </div>
                    </div>
                </div>

                <div class="wizard-step" data-step="2">
                    <div class="form-group">
                        <label for="department_id" class="form-label">Department</label>
                        <div class="input-container">
                            <i class='bx bx-buildings input-icon'></i>
                            <select id="department_id" name="department_id" class="form-input" required>
                                <option value="">Select your department</option>
                                @foreach ($departments as $dept)
                                    <option value="{{ $dept->id }}" @selected(old('department_id', $user->department_id) == $dept->id)>
                                        {{ $dept->department_code . ' - ' . $dept->department_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field-error @error('department_id') show @enderror">
                            {{ $errors->first('department_id') ?: 'Please select a department' }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="position" class="form-label">Position</label>
                        <div class="input-container">
                            <i class='bx bx-briefcase input-icon'></i>
                            <input type="text" id="position" name="position" class="form-input" placeholder="Position"
                                value="{{ old('position', $user->position) }}" required>
                        </div>
                        <div class="field-error @error('position') show @enderror">
                            {{ $errors->first('position') ?: 'Position is required' }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="employee_id" class="form-label">Employee ID</label>
                        <div class="input-container">
                            <i class='bx bx-id-card input-icon'></i>
                            <input type="text" id="employee_id" name="employee_id" class="form-input"
                                placeholder="Employee ID"
                                value="{{ old('employee_id', $user->employee_id) }}" required>
                        </div>
                        <div class="field-error @error('employee_id') show @enderror">
                            {{ $errors->first('employee_id') ?: 'Employee ID is required' }}
                        </div>
                    </div>
                </div>

                <div class="wizard-navigation">
                    <button type="button" class="wizard-btn prev-btn" id="prevBtn" style="display: none;">
                        <i class='bx bx-chevron-left'></i>
                        <span>Previous</span>
                    </button>

                    <button type="button" class="wizard-btn next-btn" id="nextBtn">
                        <span>Next</span>
                        <i class='bx bx-chevron-right'></i>
                    </button>

                    <button type="submit" class="wizard-btn next-btn" id="submitBtn" style="display: none;">
                        <span class="btn-text">Finish Registration</span>
                        <div class="btn-loader" style="display: none;">
                            <div class="spinner"></div>
                        </div>
                    </button>
                </div>
            </form>

            <div class="divider">
                <span class="divider-text">Wrong account?</span>
            </div>

            <a href="{{ route('login') }}" class="register-link">
                <i class='bx bx-log-in'></i>
                <span>Sign In</span>
            </a>
        </div>
    </div>

    <script src="{{ asset('js/auth-script.js') }}"></script>
</body>

</html>
