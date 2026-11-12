<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ODCI Document - CVSU Naic</title>
    <meta name="description" content="One place for CVSU Naic faculty and departments to submit, organize and track curriculum and instruction documents.">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" type="image/png" href="{{ asset('img/cvsu-logo.png') }}">
    <script>document.documentElement.classList.add('js');</script>
    @vite('resources/css/app.css')
</head>
<body class="antialiased" style="margin:0">
<div class="bg-white text-ink">

@php
    $features = [
        ['bxs-folder-open', 'Department folders', 'Every department gets the same document categories, split by semester, so nobody has to guess where a file belongs.'],
        ['bx-check-square', 'Document tracker', 'See which faculty have submitted which requirements, and who is still missing something.'],
        ['bx-cloud-upload', 'Upload and download', 'Upload with the right category and semester, then preview or download whenever you need the file again.'],
        ['bx-news', 'Social feed', 'Share announcements and updates with your department and the whole campus in one place.'],
        ['bx-bar-chart-alt-2', 'Reports', 'Submission and storage reports per department or per person, without exporting spreadsheets by hand.'],
        ['bx-history', 'Activity logs', 'Every sign in, upload and change is recorded, so you can answer who did what and when.'],
    ];
    $steps = [
        ['Create an account', 'Sign up with your CVSU email or Google, and pick your department.'],
        ['Get approved', 'A super admin reviews your registration. You can sign in once it is approved.'],
        ['Start submitting', 'Open your department folders, choose the category and semester, and upload.'],
    ];
@endphp

{{-- Top bar --}}
<header id="topbar" class="fixed inset-x-0 top-0 z-50 transition duration-300 data-[solid=true]:bg-white/95 data-[solid=true]:shadow-card data-[solid=true]:backdrop-blur" data-solid="false">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
        <a href="{{ url('/') }}" class="flex items-center gap-3">
            <img src="{{ asset('img/cvsu-logo.png') }}" alt="CVSU Naic logo" class="h-10 w-10 rounded-full">
            <span class="leading-tight">
                <span id="brandName" class="block whitespace-nowrap text-base font-bold text-white transition duration-300 [#topbar[data-solid=true]_&]:text-cvsu-green-800">ODCI Document</span>
                <span class="hidden text-xs text-white/60 transition duration-300 sm:block [#topbar[data-solid=true]_&]:text-muted">CVSU Naic</span>
            </span>
        </a>
        <nav class="hidden items-center gap-7 text-sm font-semibold md:flex">
            @foreach ([['#explore', 'Explore folders'], ['#roles', 'Who it is for'], ['#how', 'How it works']] as [$href, $label])
                <a href="{{ $href }}" class="text-white/80 transition hover:text-white [#topbar[data-solid=true]_&]:text-gray-700 [#topbar[data-solid=true]_&]:hover:text-cvsu-green-700">{{ $label }}</a>
            @endforeach
        </nav>
        <div class="flex items-center gap-2">
            @auth
                <a href="{{ $dashboardUrl }}" class="ui-btn-primary !text-white">Open dashboard</a>
            @else
                <a href="{{ route('login') }}" class="ui-btn-ghost !bg-white/10 !text-white hover:!bg-white/20 [#topbar[data-solid=true]_&]:!bg-gray-100 [#topbar[data-solid=true]_&]:!text-gray-800">Log in</a>
                <a href="{{ route('register') }}" class="ui-btn-primary !bg-cvsu-gold-400 !text-ink hover:!bg-cvsu-gold-500">Sign up</a>
            @endauth
        </div>
    </div>
</header>

{{-- Hero: the tracker fills itself in, then you can play with it --}}
<section id="hero" class="relative isolate overflow-hidden bg-cvsu-green-900 pt-16 text-white">
    <img src="{{ asset('img/cvsu-naic-bg.jpg') }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover opacity-60">
    <div class="absolute inset-0 -z-10 bg-[radial-gradient(520px_circle_at_var(--mx,70%)_var(--my,30%),rgba(240,192,0,.16),transparent_60%)]"></div>
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-cvsu-green-900/95 via-cvsu-green-900/70 to-cvsu-green-900/25"></div>
    <div class="absolute inset-x-0 bottom-0 -z-10 h-40 bg-gradient-to-t from-cvsu-green-900 to-transparent"></div>

    <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-[1fr_1.02fr]">
        <div>
            <p class="mb-5 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-sm font-medium text-cvsu-gold-300">
                <i class='bx bxs-graduation'></i> Cavite State University, Naic Campus
            </p>
            <h1 class="!text-4xl !font-extrabold leading-[1.06] tracking-tight text-white sm:!text-5xl lg:!text-[3.6rem]">
                Every curriculum document, accounted for.
            </h1>
            <p class="mt-5 max-w-lg text-lg leading-relaxed text-white/75">
                The Office of the Director for Curriculum and Instruction collects syllabi, class records, exams and reports from every department. This is where they live, and where you can see what is still missing.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                @auth
                    <a href="{{ $dashboardUrl }}" class="ui-btn-primary !h-12 !bg-cvsu-gold-400 !px-7 !text-ink hover:!bg-cvsu-gold-500">Open your dashboard</a>
                @else
                    <a href="{{ route('register') }}" class="ui-btn-primary !h-12 !bg-cvsu-gold-400 !px-7 !text-ink transition hover:!-translate-y-0.5 hover:!bg-cvsu-gold-500">Create an account</a>
                    <a href="{{ route('login') }}" class="ui-btn-ghost !h-12 !bg-white/10 !px-7 !text-white hover:!bg-white/20">Log in</a>
                @endauth
            </div>
            @guest
            <p class="mt-4 text-sm text-white/50">New accounts are reviewed by an administrator before first sign in.</p>
            @endguest
        </div>

        {{-- Interactive tracker --}}
        <div class="rounded-3xl bg-white/[.06] p-4 shadow-2xl ring-1 ring-white/10 backdrop-blur sm:p-6" aria-label="Sample document tracker">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <div class="text-sm font-semibold text-white">Document tracker</div>
                    <div class="text-xs text-white/50">Sample data. Tap a cell to mark it.</div>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-extrabold leading-none tabular-nums"><span id="trkDone">0</span><span class="text-lg font-semibold text-white/40">/<span id="trkTotal">25</span></span></div>
                    <div class="mt-1 text-xs text-white/50">submitted</div>
                </div>
            </div>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/10">
                <div id="trkBar" class="h-full w-0 rounded-full bg-cvsu-gold-400 transition-[width] duration-500 ease-out motion-reduce:transition-none"></div>
            </div>

            <div id="trkGrid" class="mt-5 grid grid-cols-[minmax(0,1fr)_repeat(5,auto)] items-center gap-x-2 gap-y-2 sm:gap-x-3">
                <span></span>
                @foreach (['Syllabus', 'Class record', 'Exam', 'TOS', 'Workload'] as $col)
                    <span class="w-9 text-center text-[10px] font-medium leading-tight text-white/50 sm:w-11 sm:text-[11px]">{{ $col }}</span>
                @endforeach
                @foreach (['Instructor I', 'Instructor II', 'Asst. Professor', 'Assoc. Professor', 'Professor'] as $r => $row)
                    <span class="truncate pr-2 text-sm font-medium text-white/85">{{ $row }}</span>
                    @foreach (range(0, 4) as $c)
                        <button type="button" data-cell data-r="{{ $r }}" data-c="{{ $c }}" data-on="false" aria-pressed="false" aria-label="{{ $row }}, {{ ['Syllabus', 'Class record', 'Exam', 'TOS', 'Workload'][$c] }}"
                                class="grid h-9 w-9 scale-90 place-items-center rounded-lg bg-white/10 text-transparent transition duration-300 ease-[cubic-bezier(.34,1.56,.64,1)] hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cvsu-gold-300 motion-reduce:transition-none data-[on=true]:scale-100 data-[on=true]:bg-cvsu-gold-400 data-[on=true]:text-ink sm:h-11 sm:w-11">
                            <i class='bx bx-check text-xl'></i>
                        </button>
                    @endforeach
                @endforeach
            </div>
            <p id="trkNote" class="mt-4 text-xs text-white/50">Filling in...</p>
        </div>
    </div>
</section>

{{-- Features --}}
<section class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
    <div data-reveal class="max-w-2xl">
        <h2 class="!text-3xl !font-extrabold tracking-tight text-cvsu-green-900 sm:!text-4xl">Built around how the office already works</h2>
        <p class="mt-3 text-lg text-gray-600">Paper trails and shared drives get lost. Every requirement gets a fixed home and a visible status.</p>
    </div>
    <div class="mt-12 grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($features as $i => [$icon, $title, $text])
            <article data-reveal style="--d: {{ $i * 70 }}ms" class="group">
                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-cvsu-green-100 text-2xl text-cvsu-green-700 transition duration-300 group-hover:-rotate-6 group-hover:scale-110 group-hover:bg-cvsu-green-600 group-hover:text-white"><i class='bx {{ $icon }}'></i></span>
                <h3 class="mt-4 !text-lg !font-bold">{{ $title }}</h3>
                <p class="mt-1.5 leading-relaxed text-gray-600">{{ $text }}</p>
            </article>
        @endforeach
    </div>
</section>

{{-- Folder explorer --}}
<section id="explore" class="scroll-mt-16 bg-[#f6f9f6]">
    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
        <div data-reveal class="max-w-2xl">
            <h2 class="!text-3xl !font-extrabold tracking-tight text-cvsu-green-900 sm:!text-4xl">Open a folder, see what belongs in it</h2>
            <p class="mt-3 text-lg text-gray-600">Each department folder is split into document categories and semesters. Try it with sample files.</p>
        </div>

        <div data-reveal class="mt-10 grid overflow-hidden rounded-3xl bg-white shadow-card-md lg:grid-cols-[320px_1fr]">
            <div class="bg-white p-3 sm:p-4">
                <div id="semTabs" class="mb-3 grid grid-cols-2 gap-1 rounded-xl bg-[#f1f5f2] p-1 text-sm font-semibold" role="tablist">
                    <button type="button" role="tab" data-sem="First Semester" aria-selected="true" class="rounded-lg px-3 py-2 text-gray-600 transition aria-selected:bg-cvsu-green-600 aria-selected:text-white">First sem</button>
                    <button type="button" role="tab" data-sem="Second Semester" aria-selected="false" class="rounded-lg px-3 py-2 text-gray-600 transition aria-selected:bg-cvsu-green-600 aria-selected:text-white">Second sem</button>
                </div>
                <ul id="catList" class="m-0 grid list-none gap-1 p-0"></ul>
            </div>
            <div class="min-h-[22rem] bg-[#f6f9f6] p-5 sm:p-8">
                <div class="flex items-center gap-3">
                    <span id="catIcon" class="grid h-11 w-11 place-items-center rounded-xl text-xl"></span>
                    <div>
                        <h3 id="catTitle" class="!text-xl !font-bold"></h3>
                        <p id="catMeta" class="text-sm text-muted"></p>
                    </div>
                </div>
                <ul id="fileList" class="m-0 mt-6 grid list-none gap-2 p-0"></ul>
                <p class="mt-6 text-xs text-muted">These files are examples. Your own folders show what you and your department have uploaded.</p>
            </div>
        </div>
    </div>
</section>

{{-- Roles --}}
<section id="roles" class="mx-auto max-w-6xl scroll-mt-16 px-4 py-20 sm:px-6 sm:py-28">
    <div data-reveal class="max-w-2xl">
        <h2 class="!text-3xl !font-extrabold tracking-tight text-cvsu-green-900 sm:!text-4xl">One system, three views of it</h2>
        <p class="mt-3 text-lg text-gray-600">What you see after signing in depends on your role. Pick one to see its menu.</p>
    </div>

    <div data-reveal class="mt-10 grid gap-6 lg:grid-cols-[1fr_1.1fr] lg:items-center">
        <div id="roleTabs" class="grid gap-2" role="tablist">
            @foreach ([
                ['faculty', 'bx-user', 'Faculty and staff', 'Upload your own documents, browse your department folders and follow the feed.'],
                ['admin', 'bx-user-check', 'Department admins', 'Track every faculty member in your department and keep submissions complete.'],
                ['super', 'bx-shield-quarter', 'Super admin', 'Approve accounts, manage users and departments, and watch campus-wide activity.'],
            ] as $i => [$key, $icon, $title, $text])
                <button type="button" role="tab" data-role="{{ $key }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}"
                        class="flex items-start gap-4 rounded-2xl bg-[#f6f9f6] p-4 text-left transition duration-300 hover:bg-cvsu-green-50 aria-selected:bg-cvsu-green-600 aria-selected:text-white aria-selected:shadow-card-md sm:p-5">
                    <i class='bx {{ $icon }} mt-0.5 text-3xl text-cvsu-green-600 [[aria-selected=true]_&]:text-cvsu-gold-300'></i>
                    <span>
                        <span class="block text-lg font-bold">{{ $title }}</span>
                        <span class="mt-0.5 block text-sm leading-relaxed text-gray-600 [[aria-selected=true]_&]:text-white/80">{{ $text }}</span>
                    </span>
                </button>
            @endforeach
        </div>

        {{-- Mini sidebar, rebuilt for the selected role --}}
        <div class="mx-auto w-full max-w-md rounded-3xl bg-cvsu-green-900 p-4 text-white shadow-2xl sm:p-6">
            <div class="flex items-center gap-3 pb-4">
                <img src="{{ asset('img/cvsu-logo.png') }}" alt="" class="h-9 w-9 rounded-full">
                <div class="leading-tight"><div class="text-sm font-bold">ODCI Document</div><div id="roleName" class="text-xs text-white/60">Faculty and staff</div></div>
            </div>
            <ul id="roleMenu" class="m-0 grid list-none gap-1 p-0"></ul>
        </div>
    </div>
</section>

{{-- How it works --}}
<section id="how" class="scroll-mt-16 bg-cvsu-green-900 text-white">
    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
        <h2 data-reveal class="max-w-2xl !text-3xl !font-extrabold tracking-tight text-white sm:!text-4xl">Getting started takes three steps</h2>
        <div id="steps" class="relative mt-14">
            <div class="absolute left-5 top-5 hidden h-[calc(100%-2.5rem)] w-0.5 bg-white/15 md:left-[calc(16.66%)] md:top-5 md:block md:h-0.5 md:w-[66.66%]">
                <div id="stepsFill" class="h-full w-full origin-top scale-y-0 bg-cvsu-gold-400 md:origin-left md:scale-x-0 md:scale-y-100"></div>
            </div>
            <ol class="m-0 grid list-none gap-10 p-0 md:grid-cols-3">
                @foreach ($steps as $i => [$title, $text])
                    <li data-step class="relative flex gap-4 md:flex-col md:items-center md:text-center">
                        <span data-dot class="relative z-10 grid h-10 w-10 flex-none place-items-center rounded-full bg-cvsu-green-800 font-bold text-white/60 ring-4 ring-cvsu-green-900 transition duration-500 data-[on=true]:scale-110 data-[on=true]:bg-cvsu-gold-400 data-[on=true]:text-ink" data-on="false">{{ $i + 1 }}</span>
                        <div class="max-w-xs">
                            <h3 class="!text-lg !font-bold text-white">{{ $title }}</h3>
                            <p class="mt-1 leading-relaxed text-white/70">{{ $text }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</section>

{{-- Closing panel --}}
<section class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
    <div data-reveal class="relative isolate overflow-hidden rounded-3xl bg-cvsu-green-900 px-6 py-14 text-white sm:px-12 sm:py-16">
        <img src="{{ asset('img/cvsu-naic-bg.jpg') }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover opacity-40">
        <div class="absolute inset-0 -z-10 bg-gradient-to-br from-cvsu-green-900/95 via-cvsu-green-900/80 to-cvsu-green-800/50"></div>
        <div class="absolute -right-24 -top-24 -z-10 h-72 w-72 rounded-full bg-cvsu-gold-400/20 blur-3xl"></div>

        @auth
            <h2 class="max-w-2xl !text-3xl !font-extrabold tracking-tight text-white sm:!text-4xl">Welcome back, {{ auth()->user()->name }}</h2>
            <p class="mt-3 max-w-xl text-lg text-white/75">Your folders, files and department feed are one click away.</p>
            <a href="{{ $dashboardUrl }}" class="ui-btn-primary mt-8 !h-12 !bg-cvsu-gold-400 !px-7 !text-ink hover:!bg-cvsu-gold-500">Open your dashboard</a>
        @else
            <h2 class="max-w-2xl !text-3xl !font-extrabold tracking-tight text-white sm:!text-4xl">Ready to submit your documents?</h2>
            <p class="mt-3 max-w-xl text-lg leading-relaxed text-white/75">Registering takes a couple of minutes. Here is what to have ready.</p>

            <ul class="m-0 mt-10 grid list-none gap-4 p-0 sm:grid-cols-3">
                @foreach ([
                    ['bx-id-card', 'Your CVSU details', 'Name, email and employee ID.'],
                    ['bxs-buildings', 'Your department', 'So your files land in the right folders.'],
                    ['bx-time-five', 'A short wait', 'An administrator approves new accounts.'],
                ] as $i => [$icon, $title, $text])
                    <li data-reveal style="--d: {{ 150 + $i * 120 }}ms" class="group rounded-2xl bg-white/10 p-5 backdrop-blur transition duration-300 hover:-translate-y-1 hover:bg-white/15">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-cvsu-gold-400 text-2xl text-ink transition duration-300 group-hover:rotate-6 group-hover:scale-110"><i class='bx {{ $icon }}'></i></span>
                        <h3 class="mt-4 !text-lg !font-bold text-white">{{ $title }}</h3>
                        <p class="mt-1 text-sm leading-relaxed text-white/70">{{ $text }}</p>
                    </li>
                @endforeach
            </ul>
        @endauth
    </div>
</section>

<footer class="bg-cvsu-green-900 text-sm text-white/70">
    <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <div class="flex items-center gap-3">
            <img src="{{ asset('img/cvsu-logo.png') }}" alt="" class="h-9 w-9 rounded-full">
            <span>Office of the Director for Curriculum and Instruction<br>Cavite State University, Naic Campus</span>
        </div>
        <div class="flex gap-5">
            <a href="{{ route('login') }}" class="text-white/70 hover:text-white">Log in</a>
            <a href="{{ route('register') }}" class="text-white/70 hover:text-white">Sign up</a>
            <a href="{{ route('password.request') }}" class="text-white/70 hover:text-white">Forgot password</a>
        </div>
    </div>
</footer>

</div>

<style>
    /* Scroll reveal: content stays visible without JS. */
    .js [data-reveal] { opacity: 0; transform: translateY(18px); transition: opacity .7s ease, transform .7s cubic-bezier(.2,.7,.2,1); transition-delay: var(--d, 0ms); }
    .js [data-reveal][data-in] { opacity: 1; transform: none; }
    @media (prefers-reduced-motion: reduce) { .js [data-reveal] { opacity: 1; transform: none; transition: none; } }
</style>

<script>
(function () {
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

    /* header turns solid after the hero */
    var bar = $('#topbar');
    var onScroll = function () { bar.dataset.solid = window.scrollY > 24 ? 'true' : 'false'; };
    onScroll(); window.addEventListener('scroll', onScroll, { passive: true });

    /* soft light that follows the cursor in the hero */
    var hero = $('#hero');
    if (!reduce) {
        hero.addEventListener('pointermove', function (e) {
            var b = hero.getBoundingClientRect();
            hero.style.setProperty('--mx', (e.clientX - b.left) + 'px');
            hero.style.setProperty('--my', (e.clientY - b.top) + 'px');
        });
    }

    /* scroll reveal */
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { if (en.isIntersecting) { en.target.setAttribute('data-in', ''); io.unobserve(en.target); } });
    }, { threshold: 0.15 });
    $$('[data-reveal]').forEach(function (el) { io.observe(el); });

    /* ---- hero tracker ---- */
    var cells = $$('[data-cell]');
    var total = cells.length;
    var done = $('#trkDone'), bar2 = $('#trkBar'), note = $('#trkNote');
    $('#trkTotal').textContent = total;
    function paint() {
        var n = cells.filter(function (c) { return c.dataset.on === 'true'; }).length;
        done.textContent = n;
        bar2.style.width = (n / total * 100) + '%';
        return n;
    }
    function setCell(c, on) { c.dataset.on = on ? 'true' : 'false'; c.setAttribute('aria-pressed', on ? 'true' : 'false'); }
    // which cells start submitted (leaves a few gaps so it reads as real work in progress)
    var pattern = [1,1,1,1,0, 1,1,0,1,1, 1,0,1,1,0, 1,1,1,0,1, 1,1,1,1,1];
    var order = cells.map(function (c, i) { return i; });
    var started = false;
    function fillIn() {
        if (started) return; started = true;
        order.forEach(function (i, step) {
            var delay = reduce ? 0 : 350 + step * 55;
            setTimeout(function () {
                if (pattern[i]) setCell(cells[i], true);
                paint();
                if (step === order.length - 1) note.textContent = 'Two faculty are still missing documents. Tap a cell to change it.';
            }, delay);
        });
    }
    fillIn();
    cells.forEach(function (c) {
        c.addEventListener('click', function () {
            setCell(c, c.dataset.on !== 'true');
            var n = paint();
            note.textContent = n === total ? 'Everyone has submitted. That is what the tracker is for.' : (total - n) + ' still to submit.';
        });
    });

    /* ---- folder explorer ---- */
    var cats = [
        ['IPCR Accomplishment', 'bxs-trophy', 'bg-amber-100 text-amber-600', ['IPCR_Accomplishment_2026.pdf', 'Supporting_Documents.zip']],
        ['IPCR Target', 'bx-target-lock', 'bg-red-100 text-red-600', ['IPCR_Target_2026.pdf']],
        ['Workload', 'bxs-briefcase', 'bg-violet-100 text-violet-600', ['Faculty_Workload.xlsx', 'Workload_Approval.pdf']],
        ['Course Syllabus', 'bxs-book-content', 'bg-cyan-100 text-cyan-600', ['ITEC_101_Syllabus.docx', 'ITEC_102_Syllabus.docx', 'ITEC_103_Syllabus.pdf']],
        ['Course Syllabus Acceptance Form', 'bxs-check-circle', 'bg-emerald-100 text-emerald-600', ['Acceptance_Form_ITEC_101.pdf']],
        ['Exam', 'bxs-file-doc', 'bg-rose-100 text-rose-600', ['Midterm_Exam.pdf', 'Final_Exam.pdf']],
        ['TOS', 'bxs-spreadsheet', 'bg-teal-100 text-teal-600', ['TOS_Midterm.xlsx', 'TOS_Final.xlsx']],
        ['Class Record', 'bxs-data', 'bg-indigo-100 text-indigo-600', ['Class_Record_ITEC_101.xlsx']],
        ['Attendance Sheet', 'bxs-user-check', 'bg-sky-100 text-sky-600', ['Attendance_Week1-8.pdf']],
        ['Consultation', 'bxs-chat', 'bg-orange-100 text-orange-600', ['Consultation_Log.pdf', 'Consultation_Schedule.docx']]
    ];
    var sem = 'First Semester', cur = 0;
    var catList = $('#catList'), fileList = $('#fileList');
    function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
    function renderCats() {
        catList.innerHTML = '';
        cats.forEach(function (c, i) {
            var b = el('button', 'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-semibold transition hover:bg-cvsu-green-50 aria-[current=true]:bg-cvsu-green-100 aria-[current=true]:text-cvsu-green-800');
            b.type = 'button'; b.setAttribute('aria-current', i === cur ? 'true' : 'false');
            b.innerHTML = '<span class="grid h-8 w-8 flex-none place-items-center rounded-lg ' + c[2] + '"><i class="bx ' + c[1] + '"></i></span><span class="min-w-0 flex-1 truncate">' + c[0] + '</span><span class="text-xs font-medium text-muted">' + c[3].length + '</span>';
            b.addEventListener('click', function () { cur = i; renderCats(); renderFiles(); });
            var li = el('li'); li.appendChild(b); catList.appendChild(li);
        });
    }
    function renderFiles() {
        var c = cats[cur];
        $('#catIcon').className = 'grid h-11 w-11 place-items-center rounded-xl text-xl ' + c[2];
        $('#catIcon').innerHTML = '<i class="bx ' + c[1] + '"></i>';
        $('#catTitle').textContent = c[0];
        $('#catMeta').textContent = sem + ' - ' + c[3].length + (c[3].length === 1 ? ' file' : ' files');
        fileList.innerHTML = '';
        c[3].forEach(function (f, i) {
            var ext = f.split('.').pop().toUpperCase();
            var li = el('li', 'flex items-center gap-3 rounded-xl bg-white p-3 shadow-card transition duration-500 ease-out');
            li.innerHTML = '<span class="grid h-10 w-10 flex-none place-items-center rounded-lg bg-cvsu-green-100 text-xs font-bold text-cvsu-green-700">' + ext + '</span><span class="min-w-0 flex-1 truncate text-sm font-semibold">' + f + '</span><i class="bx bx-download text-xl text-cvsu-green-600"></i>';
            if (!reduce) { li.style.opacity = '0'; li.style.transform = 'translateX(14px)'; setTimeout(function () { li.style.opacity = '1'; li.style.transform = 'none'; }, 40 + i * 90); }
            fileList.appendChild(li);
        });
    }
    $$('#semTabs [data-sem]').forEach(function (t) {
        t.addEventListener('click', function () {
            sem = t.dataset.sem;
            $$('#semTabs [data-sem]').forEach(function (x) { x.setAttribute('aria-selected', x === t ? 'true' : 'false'); });
            renderFiles();
        });
    });
    renderCats(); renderFiles();

    /* ---- roles ---- */
    var menus = {
        faculty: ['Faculty and staff', [['bxs-dashboard', 'Dashboard'], ['bxs-news', 'Social feed'], ['bxs-user-detail', 'Colleagues'], ['bxs-folder', 'Department folders'], ['bxs-report', 'My reports']]],
        admin: ['Department admin', [['bxs-dashboard', 'Dashboard'], ['bxs-news', 'Social feed'], ['bxs-user-detail', 'Faculty and staff'], ['bxs-folder', 'Folders'], ['bxs-file', 'Files'], ['bx-check-square', 'Document tracker']]],
        super: ['Super admin', [['bxs-dashboard', 'Dashboard'], ['bxs-group', 'Users'], ['bxs-buildings', 'Departments'], ['bx-check-square', 'Document tracker'], ['bxs-folder', 'Folders and files'], ['bxs-report', 'Reports'], ['bxs-cog', 'System settings'], ['bxs-time', 'Activity logs']]]
    };
    var roleMenu = $('#roleMenu');
    function showRole(key) {
        $('#roleName').textContent = menus[key][0];
        roleMenu.innerHTML = '';
        menus[key][1].forEach(function (m, i) {
            var li = el('li', 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition duration-300 ' + (i === 0 ? 'bg-white/15' : 'text-white/80'));
            li.innerHTML = '<i class="bx ' + m[0] + ' text-lg"></i>' + m[1];
            if (!reduce) { li.style.opacity = '0'; li.style.transform = 'translateX(-10px)'; setTimeout(function () { li.style.opacity = '1'; li.style.transform = 'none'; }, 30 + i * 55); }
            roleMenu.appendChild(li);
        });
    }
    $$('#roleTabs [data-role]').forEach(function (b) {
        b.addEventListener('click', function () {
            $$('#roleTabs [data-role]').forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
            showRole(b.dataset.role);
        });
    });
    showRole('faculty');

    /* ---- steps line fills as you scroll ---- */
    var steps = $('#steps'), fill = $('#stepsFill'), dots = $$('[data-dot]');
    function stepsProgress() {
        var r = steps.getBoundingClientRect(), vh = window.innerHeight;
        var p = Math.min(1, Math.max(0, (vh * 0.75 - r.top) / (r.height + vh * 0.1)));
        fill.style.transform = window.innerWidth >= 768 ? 'scaleX(' + p + ')' : 'scaleY(' + p + ')';
        dots.forEach(function (d, i) { d.dataset.on = p >= (i / (dots.length - 0.4)) + 0.02 ? 'true' : 'false'; });
    }
    stepsProgress(); window.addEventListener('scroll', stepsProgress, { passive: true }); window.addEventListener('resize', stepsProgress);
})();
</script>
</body>
</html>
