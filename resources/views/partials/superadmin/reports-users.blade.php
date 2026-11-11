{{-- Top active users report table (superadmin reports module) --}}
<div class="overflow-hidden rounded-card border border-line bg-white shadow-card">
    <div class="flex items-center justify-between border-b border-line px-5 py-4">
        <h3 class="flex items-center gap-2 text-base font-bold text-ink">
            <i class='bx bxs-group text-cvsu-green-600'></i> Top Active Users
        </h3>
        <span class="rounded-full bg-cvsu-green-50 px-3 py-1 text-xs font-semibold text-cvsu-green-700">Ranked by activity score</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="border-b border-line bg-muted/5 text-xs font-bold uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-5 py-3">#</th>
                    <th class="px-5 py-3">User</th>
                    <th class="px-5 py-3">Department</th>
                    <th class="px-5 py-3 text-center">Files Uploaded</th>
                    <th class="px-5 py-3 text-right">Total Size</th>
                    <th class="px-5 py-3 text-center">Activities</th>
                    <th class="px-5 py-3 text-center">Score</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($data['topUsers'] as $i => $user)
                    <tr class="transition hover:bg-cvsu-green-50/50">
                        <td class="px-5 py-3.5">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold
                                {{ $i < 3 ? 'bg-cvsu-gold-400 text-cvsu-green-900' : 'bg-cvsu-green-100 text-cvsu-green-700' }}">
                                {{ $i + 1 }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <p class="font-semibold text-ink">{{ $user->full_name }}</p>
                            <p class="text-xs text-muted">{{ $user->username }}</p>
                        </td>
                        <td class="px-5 py-3.5 text-muted">{{ $user->department_name ?: '—' }}</td>
                        <td class="px-5 py-3.5 text-center font-semibold text-ink">{{ number_format($user->files_uploaded) }}</td>
                        <td class="px-5 py-3.5 text-right text-muted">{{ number_format($user->total_uploaded_size) }} B</td>
                        <td class="px-5 py-3.5 text-center text-muted">{{ number_format($user->activities) }}</td>
                        <td class="px-5 py-3.5 text-center">
                            <span class="rounded-full bg-cvsu-green-100 px-2.5 py-0.5 text-xs font-bold text-cvsu-green-700">
                                {{ ($user->files_uploaded * 2) + $user->activities }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center">
                        <i class='bx bxs-inbox text-4xl text-muted'></i>
                        <p class="mt-2 text-sm font-medium text-muted">No user activity in this range.</p>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>