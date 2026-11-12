{{--
    Shared account-settings page (port of roles/user/settings.php and roles/admin/profile.php).
    Expects: $currentUser, $profileImage, $hasProfileImage, $initials, $departments,
             $securityScore, $forSuperAdmin, $forRole ('user'|'admin')
--}}
@php
    $fullName = $currentUser->full_name ?: $currentUser->username;
    $roleLabel = $forSuperAdmin ? 'Super Admin' : ucfirst($currentUser->role);
    $dept = $currentUser->department;
    $fs = function ($bytes) {
        $bytes = (int) $bytes;
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2).' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2).' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2).' KB';
        return $bytes.' B';
    };
@endphp

@if (session('success_message'))
    <x-ui.alert type="success">{{ session('success_message') }}</x-ui.alert>
@endif

@if (session('error_message'))
    <x-ui.alert type="error">{{ session('error_message') }}</x-ui.alert>
@endif

<div class="ui-card overflow-hidden">
    {{-- Settings header --}}
    <div class="flex flex-col gap-5 border-b border-line bg-gradient-to-r from-cvsu-green-700 to-cvsu-green-800 px-4 py-5 text-white sm:px-6 sm:py-6 sm:flex-row sm:items-center">
        <a href="{{ route($forRole === 'admin' ? 'admin.profile' : 'user.settings') }}" class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white/15 text-white ring-2 ring-white/30 backdrop-blur">
            @if ($hasProfileImage)
                <img src="{{ $profileImage }}" alt="Profile" class="h-full w-full object-cover">
            @else
                <span class="text-3xl font-bold text-white">{{ $initials }}</span>
            @endif
        </a>
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold sm:text-2xl">{{ $fullName }}</h1>
                <span class="inline-flex items-center gap-1 rounded-full bg-cvsu-gold-400 px-2.5 py-0.5 text-xs font-bold text-cvsu-green-900">
                    @if ($forSuperAdmin)
                        <i class='bx bxs-crown'></i>
                    @else
                        <i class='bx bxs-shield-alt-2'></i>
                    @endif
                    {{ $roleLabel }}
                </span>
            </div>
            <p class="mt-1 text-sm text-cvsu-green-100">{{ $currentUser->email }}</p>
            <p class="mt-0.5 text-sm text-cvsu-green-100">
                <i class='bx bx-building mr-1'></i>{{ $dept?->department_name ?? 'No Department Assigned' }} &middot; {{ $currentUser->position ?? 'No position set' }}
            </p>
        </div>
        <div class="sm:ml-auto">
            <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium text-white">
                <i class='bx {{ $securityScore >= 70 ? 'bxs-shield-alt-2' : 'bx-shield-alt-2' }} {{ $securityScore >= 70 ? 'text-cvsu-gold-300' : '' }}'></i>
                Security Score: {{ $securityScore }}/100
            </span>
        </div>
    </div>

    <div class="grid gap-5 p-4 sm:gap-6 sm:p-6 lg:grid-cols-[300px_1fr]">
        {{-- Sidebar column --}}
        <div class="space-y-6">
            {{-- Profile image card --}}
            <div class="ui-card p-4 sm:p-5">
                <h3 class="mb-4 flex items-center gap-2 text-sm font-bold text-ink">
                    <i class='bx bxs-image-alt text-cvsu-green-600'></i> Profile Photo
                </h3>
                <div class="mx-auto flex h-28 w-28 items-center justify-center overflow-hidden rounded-full border-4 border-cvsu-green-100 bg-cvsu-green-50">
                    @if ($hasProfileImage)
                        <img src="{{ $profileImage }}" alt="Profile" class="h-full w-full rounded-full object-cover">
                    @else
                        <span class="text-3xl font-bold text-cvsu-green-600">{{ $initials }}</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-5 space-y-3">
                    @csrf
                    <input type="hidden" name="action" value="upload_image">
                    <label class="block cursor-pointer rounded-lg border border-line bg-white px-4 py-2.5 text-center text-sm font-semibold text-ink transition hover:border-cvsu-green-400 hover:bg-cvsu-green-50">
                        <i class='bx bx-cloud-upload mr-1 text-cvsu-green-600'></i> Choose Image
                        <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,.gif,.webp" class="hidden" onchange="this.form.submit()">
                    </label>
                    <p class="text-center text-xs text-muted">JPG, PNG, GIF or WEBP. Max 5MB.</p>
                </form>
                @if ($hasProfileImage)
                    <form method="POST" action="{{ route('profile.update') }}" class="mt-2">
                        @csrf
                        <input type="hidden" name="action" value="remove_image">
                        <button type="submit" class="w-full rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-danger transition hover:bg-red-50">
                            <i class='bx bx-trash mr-1'></i> Remove Photo
                        </button>
                    </form>
                @endif
            </div>

            {{-- Security score card --}}
            <div class="ui-card p-4 sm:p-5">
                <h3 class="mb-4 flex items-center gap-2 text-sm font-bold text-ink">
                    <i class='bx bxs-shield-alt-2 text-cvsu-gold-500'></i> Account Security
                </h3>
                <div class="mb-2 flex items-center justify-between text-sm">
                    <span class="font-semibold text-ink">Profile Completion</span>
                    <span class="font-bold text-cvsu-green-600">{{ $securityScore }}%</span>
                </div>
                <div class="h-2.5 w-full overflow-hidden rounded-full bg-muted/15">
                    <div class="h-full rounded-full bg-gradient-to-r from-cvsu-green-500 to-cvsu-gold-400 transition-all" style="width: {{ $securityScore }}%"></div>
                </div>
                <ul class="mt-4 space-y-2 text-sm text-muted">
                    <li class="flex items-center gap-2">
                        <i class='bx {{ $currentUser->phone ? 'bxs-check-circle text-cvsu-green-600' : 'bx-circle text-muted' }}'></i> Contact number
                    </li>
                    <li class="flex items-center gap-2">
                        <i class='bx {{ $currentUser->position ? 'bxs-check-circle text-cvsu-green-600' : 'bx-circle text-muted' }}'></i> Position / title
                    </li>
                    <li class="flex items-center gap-2">
                        <i class='bx {{ $currentUser->employee_id ? 'bxs-check-circle text-cvsu-green-600' : 'bx-circle text-muted' }}'></i> Employee ID
                    </li>
                    <li class="flex items-center gap-2">
                        <i class='bx {{ $currentUser->date_of_birth ? 'bxs-check-circle text-cvsu-green-600' : 'bx-circle text-muted' }}'></i> Date of birth
                    </li>
                    @if ($forRole === 'admin')
                        <li class="flex items-center gap-2">
                            <i class='bx {{ $currentUser->hire_date ? 'bxs-check-circle text-cvsu-green-600' : 'bx-circle text-muted' }}'></i> Hire date
                        </li>
                    @endif
                    <li class="flex items-center gap-2">
                        <i class='bx {{ strlen((string) $currentUser->password) >= 12 ? 'bxs-check-circle text-cvsu-green-600' : 'bx-circle text-muted' }}'></i> Strong password
                    </li>
                    @if (! $currentUser->email_verified)
                        <li class="flex items-center gap-2 text-cvsu-gold-600">
                            <i class='bx bxs-info-circle'></i> Email not verified
                        </li>
                    @endif
                </ul>
            </div>

            @if ($forRole === 'admin')
                {{-- Account info --}}
                <div class="ui-card p-4 sm:p-5">
                    <h3 class="mb-4 flex items-center gap-2 text-sm font-bold text-ink">
                        <i class='bx bxs-user-detail text-cvsu-green-600'></i> Account Info
                    </h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Username</dt>
                            <dd class="font-semibold text-ink">{{ $currentUser->username }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Last Login</dt>
                            <dd class="font-semibold text-ink">{{ $currentUser->last_login ? \Carbon\Carbon::parse($currentUser->last_login)->format('M d, Y h:i A') : 'Never' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Member Since</dt>
                            <dd class="font-semibold text-ink">{{ $currentUser->created_at?->format('M d, Y') }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Status</dt>
                            <dd>
                                <span class="rounded-full bg-cvsu-green-100 px-2 py-0.5 text-xs font-bold text-cvsu-green-700">Active</span>
                            </dd>
                        </div>
                    </dl>
                </div>
            @endif
        </div>

        {{-- Main column --}}
        <div class="space-y-6">
            {{-- Profile form --}}
            <form method="POST" action="{{ route('profile.update') }}" class="rounded-card border border-line bg-white p-6 shadow-card">
                @csrf
                <input type="hidden" name="action" value="update_profile">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-line pb-4">
                    <h3 class="flex items-center gap-2 text-base font-bold text-ink">
                        <i class='bx bxs-user-pin text-cvsu-green-600'></i> Profile Information
                    </h3>
                    <button type="submit" class="ui-btn-primary">
                        <i class='bx bx-save'></i> Save Changes
                    </button>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="field_name" class="mb-1.5 block text-sm font-semibold text-ink">First Name <span class="text-danger">*</span></label>
                        <input type="text" id="field_name" name="name" value="{{ old('name', $currentUser->name) }}" required
                               class="ui-input">
                    </div>
                    <div>
                        <label for="field_mi" class="mb-1.5 block text-sm font-semibold text-ink">Middle Initial</label>
                        <input type="text" id="field_mi" name="mi" value="{{ old('mi', $currentUser->mi) }}" maxlength="5"
                               class="ui-input">
                    </div>
                    <div>
                        <label for="field_surname" class="mb-1.5 block text-sm font-semibold text-ink">Surname <span class="text-danger">*</span></label>
                        <input type="text" id="field_surname" name="surname" value="{{ old('surname', $currentUser->surname) }}" required
                               class="ui-input">
                    </div>
                    <div>
                        <label for="field_employee" class="mb-1.5 block text-sm font-semibold text-ink">Employee ID</label>
                        <input type="text" id="field_employee" name="employee_id" value="{{ old('employee_id', $currentUser->employee_id) }}"
                               class="ui-input">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="field_email" class="mb-1.5 block text-sm font-semibold text-ink">Email Address <span class="text-danger">*</span></label>
                        <input type="email" id="field_email" name="email" value="{{ old('email', $currentUser->email) }}" required
                               class="ui-input">
                    </div>
                    <div>
                        <label for="field_phone" class="mb-1.5 block text-sm font-semibold text-ink">Phone Number</label>
                        <input type="text" id="field_phone" name="phone" value="{{ old('phone', $currentUser->phone) }}"
                               class="ui-input">
                    </div>
                    <div>
                        <label for="field_position" class="mb-1.5 block text-sm font-semibold text-ink">Position / Job Title</label>
                        <input type="text" id="field_position" name="position" value="{{ old('position', $currentUser->position) }}"
                               class="ui-input">
                    </div>
                    <div>
                        <label for="field_department" class="mb-1.5 block text-sm font-semibold text-ink">Department</label>
                        <select id="field_department" name="department_id"
                                class="ui-input">
                            <option value="">— No department —</option>
                            @foreach ($departments as $deptOption)
                                <option value="{{ $deptOption->id }}" @selected($currentUser->department_id == $deptOption->id)>
                                    {{ $deptOption->department_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="field_address" class="mb-1.5 block text-sm font-semibold text-ink">Address</label>
                        <input type="text" id="field_address" name="address" value="{{ old('address', $currentUser->address) }}"
                               class="ui-input">
                    </div>
                    <div>
                        <label for="field_dob" class="mb-1.5 block text-sm font-semibold text-ink">Date of Birth</label>
                        <input type="date" id="field_dob" name="date_of_birth"
                               value="{{ old('date_of_birth', $currentUser->date_of_birth ? \Carbon\Carbon::parse($currentUser->date_of_birth)->format('Y-m-d') : '') }}"
                               class="ui-input">
                    </div>
                    @if ($forRole === 'admin')
                        <div>
                            <label for="field_hired" class="mb-1.5 block text-sm font-semibold text-ink">Hire Date</label>
                            <input type="date" id="field_hired" name="hire_date"
                                   value="{{ old('hire_date', $currentUser->hire_date ? \Carbon\Carbon::parse($currentUser->hire_date)->format('Y-m-d') : '') }}"
                                   class="ui-input">
                        </div>
                    @endif
                </div>
                <input type="hidden" name="submit_profile" value="1">
            </form>

            {{-- Password form --}}
            <form method="POST" action="{{ route('profile.update') }}" class="rounded-card border border-line bg-white p-6 shadow-card" id="passwordForm">
                @csrf
                <input type="hidden" name="action" value="change_password">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-line pb-4">
                    <h3 class="flex items-center gap-2 text-base font-bold text-ink">
                        <i class='bx bxs-lock-alt text-cvsu-gold-500'></i> Change Password
                    </h3>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-cvsu-gold-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cvsu-gold-600">
                        <i class='bx bxs-key'></i> Update Password
                    </button>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="field_current" class="mb-1.5 block text-sm font-semibold text-ink">Current Password</label>
                        <div class="relative">
                            <input type="password" id="field_current" name="current_password" required
                                   class="ui-input ui-input-gold pr-10">
                            <button type="button" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-muted hover:text-ink" onclick="togglePw('field_current', this)">
                                <i class='bx bx-show'></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label for="field_new" class="mb-1.5 block text-sm font-semibold text-ink">New Password</label>
                        <div class="relative">
                            <input type="password" id="field_new" name="new_password" required minlength="8"
                                   class="ui-input ui-input-gold pr-10">
                            <button type="button" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-muted hover:text-ink" onclick="togglePw('field_new', this)">
                                <i class='bx bx-show'></i>
                            </button>
                        </div>
                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-muted/15">
                            <div id="strengthBar" class="h-full rounded-full bg-muted/20 transition-all" style="width: 0%"></div>
                        </div>
                        <p id="strengthLabel" class="mt-1 text-xs text-muted">Use at least 8 characters.</p>
                    </div>
                    <div>
                        <label for="field_confirm" class="mb-1.5 block text-sm font-semibold text-ink">Confirm New Password</label>
                        <div class="relative">
                            <input type="password" id="field_confirm" name="confirm_password" required minlength="8"
                                   class="ui-input ui-input-gold pr-10">
                            <button type="button" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-muted hover:text-ink" onclick="togglePw('field_confirm', this)">
                                <i class='bx bx-show'></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function togglePw(id, btn) {
        const input = document.getElementById(id);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.querySelector('i').className = show ? 'bx bx-hide' : 'bx bx-show';
    }

    (function () {
        const pw = document.getElementById('field_new');
        if (! pw) return;
        const bar = document.getElementById('strengthBar');
        const label = document.getElementById('strengthLabel');

        pw.addEventListener('input', function () {
            let score = 0;
            const v = this.value;
            if (v.length >= 8) score += 25;
            if (v.length >= 12) score += 25;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score += 20;
            if (/\d/.test(v)) score += 20;
            if (/[^A-Za-z0-9]/.test(v)) score += 10;

            bar.style.width = score + '%';
            if (score <= 25) {
                bar.className = 'h-full rounded-full bg-red-500 transition-all';
                label.textContent = 'Weak password';
            } else if (score <= 65) {
                bar.className = 'h-full rounded-full bg-cvsu-gold-400 transition-all';
                label.textContent = 'Decent password';
            } else {
                bar.className = 'h-full rounded-full bg-cvsu-green-600 transition-all';
                label.textContent = 'Strong password';
            }
        });
    })();
</script>