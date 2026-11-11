{{-- System activities report table (superadmin reports module) --}}
<div class="overflow-hidden rounded-card border border-line bg-white shadow-card">
    <div class="flex items-center justify-between border-b border-line px-5 py-4">
        <h3 class="flex items-center gap-2 text-base font-bold text-ink">
            <i class='bx bxs-list-ul text-cvsu-green-600'></i> System Activities
        </h3>
        <span class="rounded-full bg-cvsu-green-50 px-3 py-1 text-xs font-semibold text-cvsu-green-700">{{ count($data['activities']) }} recorded</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[820px] text-left text-sm">
            <thead class="border-b border-line bg-muted/5 text-xs font-bold uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-5 py-3">Date / Time</th>
                    <th class="px-5 py-3">User</th>
                    <th class="px-5 py-3">Action</th>
                    <th class="px-5 py-3">Resource Type</th>
                    <th class="px-5 py-3">Description</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($data['activities'] as $act)
                    <tr class="transition hover:bg-cvsu-green-50/50">
                        <td class="px-5 py-3.5 whitespace-nowrap text-muted">{{ \Carbon\Carbon::parse($act->created_at)->format('M d, Y h:i A') }}</td>
                        <td class="px-5 py-3.5 font-semibold text-ink">{{ $act->full_name }}</td>
                        <td class="px-5 py-3.5">
                            <span class="rounded-full bg-cvsu-green-100 px-2.5 py-0.5 text-xs font-bold text-cvsu-green-700">{{ ucwords(str_replace('_', ' ', $act->action)) }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-muted">
                            <span class="inline-flex items-center gap-1.5">
                                <i class='bx bx-{{ match ($act->resource_type) {
                                    'file' => 'file', 'folder' => 'folder', 'user' => 'user',
                                    'announcement' => 'megaphone', 'department' => 'building', default => 'cog',
                                } }} text-cvsu-green-600'></i>
                                {{ ucwords((string) $act->resource_type) }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 max-w-xs truncate text-muted">{{ $act->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center">
                        <i class='bx bxs-inbox text-4xl text-muted'></i>
                        <p class="mt-2 text-sm font-medium text-muted">No activities recorded in this range.</p>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>