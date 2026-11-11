{{-- Departments report table (superadmin reports module) --}}
<div class="overflow-hidden rounded-card border border-line bg-white shadow-card">
    <div class="flex items-center justify-between border-b border-line px-5 py-4">
        <h3 class="flex items-center gap-2 text-base font-bold text-ink">
            <i class='bx bxs-building text-cvsu-green-600'></i> Department Statistics
        </h3>
        <span class="rounded-full bg-cvsu-green-50 px-3 py-1 text-xs font-semibold text-cvsu-green-700">{{ count($data['departments']) }} department(s)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="border-b border-line bg-muted/5 text-xs font-bold uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-5 py-3">Department</th>
                    <th class="px-5 py-3 text-center">Users</th>
                    <th class="px-5 py-3 text-center">Folders</th>
                    <th class="px-5 py-3 text-center">Files</th>
                    <th class="px-5 py-3 text-right">Storage Used</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($data['departments'] as $dept)
                    <tr class="transition hover:bg-cvsu-green-50/50">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-cvsu-green-100 text-cvsu-green-700">
                                    <i class='bx bxs-building-house'></i>
                                </span>
                                <div>
                                    <p class="font-semibold text-ink">{{ $dept->department_name }}</p>
                                    <p class="text-xs text-muted">{{ $dept->department_code }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-center font-semibold text-ink">{{ number_format($dept->user_count) }}</td>
                        <td class="px-5 py-3.5 text-center text-muted">{{ number_format($dept->folder_count) }}</td>
                        <td class="px-5 py-3.5 text-center text-muted">{{ number_format($dept->file_count) }}</td>
                        <td class="px-5 py-3.5 text-right font-semibold text-ink">{{ $fs($dept->total_size) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center">
                        <i class='bx bxs-inbox text-4xl text-muted'></i>
                        <p class="mt-2 text-sm font-medium text-muted">No departments found.</p>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>