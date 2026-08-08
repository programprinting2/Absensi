@php
    $selectedKeys = $selectedRole
        ? $selectedRole->menus->pluck('menu_key')->all()
        : [];
    $roleNames = $roles->pluck('name', 'slug');
    $staffRoles = $staffRoles ?? $roles->where('slug', '!=', 'employee')->values();
    $editingUserId = session('editing_user_id') ?: old('_editing_user_id');
    $editingUser = $editingUserId
        ? $accessUsers->firstWhere('id', (int) $editingUserId)
        : null;
    $showCreateUserModal = $errors->any() && ! $editingUserId;
    $showEditUserModal = filled($editingUserId) && ($errors->any() || session()->has('editing_user_id'));
@endphp


<div class="mb-5">
    <h3 class="text-base font-semibold text-gray-900">Hak Akses</h3>
    <p class="text-sm text-gray-500 mt-0.5">
        Buat akun Admin/staf di sini. Akun Karyawan dibuat dari <strong>Karyawan → Edit → Akses Portal</strong>.
    </p>
</div>

{{-- Kelola user --}}
<div
    class="mb-8 border border-gray-200 rounded-lg overflow-hidden"
    x-data="{
        editing: {
            id: {{ $editingUser?->id ?? 'null' }},
            name: {{ \Illuminate\Support\Js::from(old('name', $editingUser?->name ?? '')) }},
            email: {{ \Illuminate\Support\Js::from(old('email', $editingUser?->email ?? '')) }},
            role: {{ \Illuminate\Support\Js::from(old('role', $editingUser?->role ?? 'admin')) }},
            employeeId: {{ \Illuminate\Support\Js::from(old('employee_id', $editingUser?->employee_id ?? '')) }},
            isEmployee: {{ ($editingUser?->role === 'employee' || old('role') === 'employee') ? 'true' : 'false' }}
        },
        openEdit(user) {
            this.editing = user;
            $dispatch('open-modal', 'edit-system-user');
        }
    }"
>
    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h4 class="text-sm font-semibold text-gray-800">Pengguna sistem</h4>
            <p class="text-xs text-gray-500 mt-0.5">Kelola akun Admin, staf, dan karyawan portal.</p>
        </div>
        <x-primary-button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'create-system-user')">
            Buat User
        </x-primary-button>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm divide-y divide-gray-100">
            <thead class="bg-white text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-2.5">Nama</th>
                    <th class="px-4 py-2.5">Role</th>
                    <th class="px-4 py-2.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($accessUsers as $accessUser)
                    <tr wire:key="access-user-{{ $accessUser->id }}">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="font-medium text-gray-900">
                                {{ $accessUser->name }}
                                @if ($accessUser->id === auth()->id())
                                    <span class="text-xs font-normal text-gray-400">(anda)</span>
                                @endif
                            </div>
                            <div class="mt-0.5 text-xs text-gray-500">{{ $accessUser->email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                {{ $roleNames[$accessUser->role] ?? $accessUser->role }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center p-1.5 rounded-md text-indigo-600 hover:bg-indigo-50 hover:text-indigo-700 transition"
                                    title="Update"
                                    @click="openEdit({
                                        id: {{ $accessUser->id }},
                                        name: {{ \Illuminate\Support\Js::from($accessUser->name) }},
                                        email: {{ \Illuminate\Support\Js::from($accessUser->email) }},
                                        role: {{ \Illuminate\Support\Js::from($accessUser->role) }},
                                        employeeId: {{ \Illuminate\Support\Js::from($accessUser->employee_id ?? '') }},
                                        isEmployee: {{ $accessUser->role === 'employee' ? 'true' : 'false' }}
                                    })"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                </button>

                                @if ($accessUser->id !== auth()->id())
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center p-1.5 rounded-md text-red-600 hover:bg-red-50 hover:text-red-700 transition"
                                        title="Hapus"
                                        x-on:click.prevent="$dispatch('open-modal', 'delete-system-user-{{ $accessUser->id }}')"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-gray-500">Belum ada user.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Dialog: Buat User --}}
    <x-modal name="create-system-user" :show="$showCreateUserModal" focusable maxWidth="lg">
        <form method="POST" action="{{ route('settings.users.store') }}" class="p-6 space-y-4">
            @csrf
            <h2 class="text-lg font-medium text-gray-900">Buat User</h2>
            <p class="text-sm text-gray-500">Untuk akun Admin atau staf. Akun Karyawan buat lewat menu Edit Karyawan.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <x-input-label for="create_user_name" value="Nama" />
                    <x-text-input id="create_user_name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name') }}" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="create_user_email" value="Email login" />
                    <x-text-input id="create_user_email" name="email" type="email" class="mt-1 block w-full" value="{{ old('email') }}" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="create_user_role" value="Role" />
                    <select id="create_user_role" name="role" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                        @foreach ($staffRoles as $role)
                            <option value="{{ $role->slug }}" @selected(old('role', 'admin') === $role->slug)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('role')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="create_user_password" value="Password" />
                    <x-text-input id="create_user_password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="create_user_password_confirmation" value="Konfirmasi password" />
                    <x-text-input id="create_user_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">Batal</x-secondary-button>
                <x-primary-button type="submit">Buat User</x-primary-button>
            </div>
        </form>
    </x-modal>

    {{-- Dialog: Update User --}}
    <x-modal name="edit-system-user" :show="$showEditUserModal" focusable maxWidth="lg">
        <form method="POST" class="p-6 space-y-4" x-bind:action="'{{ url('/settings/users') }}/' + editing.id">
            @csrf
            @method('PUT')
            <input type="hidden" name="_editing_user_id" x-bind:value="editing.id">

            <h2 class="text-lg font-medium text-gray-900">Update User</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <x-input-label for="edit_user_name" value="Nama" />
                    <input id="edit_user_name" name="name" type="text" x-model="editing.name" required
                           class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="edit_user_email" value="Email" />
                    <input id="edit_user_email" name="email" type="email" x-model="editing.email" required
                           class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="edit_user_role" value="Role" />
                    <template x-if="editing.isEmployee">
                        <div>
                            <input type="hidden" name="role" value="employee">
                            <input type="hidden" name="employee_id" x-bind:value="editing.employeeId">
                            <p class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Karyawan</p>
                            <p class="mt-1 text-xs text-gray-500">Akun terhubung ke data karyawan. Ubah email/password di sini; buat/hapus portal lewat Edit Karyawan.</p>
                        </div>
                    </template>
                    <template x-if="!editing.isEmployee">
                        <select id="edit_user_role" name="role" x-model="editing.role"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                            @foreach ($roles as $role)
                                <option value="{{ $role->slug }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </template>
                    <x-input-error :messages="$errors->get('role')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="edit_user_password" value="Password baru (opsional)" />
                    <x-text-input id="edit_user_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="edit_user_password_confirmation" value="Konfirmasi password" />
                    <x-text-input id="edit_user_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">Batal</x-secondary-button>
                <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
            </div>
        </form>
    </x-modal>

    @foreach ($accessUsers as $accessUser)
        @continue($accessUser->id === auth()->id())
        <x-modal name="delete-system-user-{{ $accessUser->id }}" focusable>
            <form method="POST" action="{{ route('settings.users.destroy', $accessUser) }}" class="p-6">
                @csrf
                @method('DELETE')
                <h2 class="text-lg font-medium text-gray-900">Hapus {{ $accessUser->name }}?</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Akun <span class="font-medium">{{ $accessUser->email }}</span> akan dihapus. Tindakan ini tidak bisa dibatalkan.
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">Batal</x-secondary-button>
                    <x-danger-button>Hapus</x-danger-button>
                </div>
            </form>
        </x-modal>
    @endforeach
</div>

<div class="mb-3">
    <h4 class="text-sm font-semibold text-gray-800">Menu per role</h4>
    <p class="text-xs text-gray-500 mt-0.5">
        Menu baru di <code class="bg-gray-100 px-1 rounded">config/menus.php</code> otomatis muncul di checklist.
    </p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    {{-- Daftar role --}}
    <div class="lg:col-span-4 space-y-3">
        <div class="border border-gray-200 rounded-lg divide-y divide-gray-100 overflow-hidden">
            @foreach ($roles as $role)
                <a href="{{ route('settings.index', ['tab' => 'hak-akses', 'role' => $role->id]) }}"
                   @click="activeTab = 'hak-akses'"
                   class="flex items-center justify-between gap-2 px-4 py-3 text-sm hover:bg-gray-50 {{ $selectedRole?->id === $role->id ? 'bg-orange-50 text-[#f7340d] font-medium' : 'text-gray-700' }}">
                    <span class="truncate">{{ $role->name }}</span>
                    @if ($role->is_system)
                        <span class="text-[10px] uppercase tracking-wide text-gray-400 shrink-0">sistem</span>
                    @endif
                </a>
            @endforeach
        </div>

        <form method="POST" action="{{ route('settings.roles.store') }}" class="border border-dashed border-gray-300 rounded-lg p-4 space-y-3">
            @csrf
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Role baru</p>
            <div>
                <x-input-label for="new_role_name" value="Nama role" />
                <x-text-input id="new_role_name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name') }}" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="new_home_menu_key" value="Menu beranda" />
                <select id="new_home_menu_key" name="home_menu_key" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">— pilih —</option>
                    @foreach ($menuItems as $item)
                        <option value="{{ $item['key'] }}" @selected(old('home_menu_key') === $item['key'])>{{ $item['label'] }} ({{ $item['key'] }})</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-2 max-h-40 overflow-y-auto border border-gray-100 rounded-md p-2">
                @foreach ($menuItems as $item)
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="menus[]" value="{{ $item['key'] }}" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                               @checked(in_array($item['key'], old('menus', []), true))>
                        <span>{{ $item['label'] }}</span>
                    </label>
                @endforeach
            </div>
            <x-primary-button type="submit">Tambah Role</x-primary-button>
        </form>
    </div>

    {{-- Editor role terpilih --}}
    <div class="lg:col-span-8">
        @if ($selectedRole)
            <form method="POST" action="{{ route('settings.roles.update', $selectedRole) }}" class="border border-gray-200 rounded-lg p-5 space-y-5">
                @csrf
                @method('PUT')

                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1 space-y-3">
                        <div>
                            <x-input-label for="role_name" value="Nama role" />
                            <x-text-input id="role_name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name', $selectedRole->name) }}" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="home_menu_key" value="Menu beranda (setelah login)" />
                            <select id="home_menu_key" name="home_menu_key" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">— otomatis (menu pertama) —</option>
                                @foreach ($menuItems as $item)
                                    <option value="{{ $item['key'] }}" @selected(old('home_menu_key', $selectedRole->home_menu_key) === $item['key'])>
                                        {{ $item['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <p class="text-xs text-gray-400">Slug: <code>{{ $selectedRole->slug }}</code></p>
                    </div>
                </div>

                <div>
                    <p class="text-sm font-medium text-gray-800 mb-2">Menu yang diizinkan</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach ($menuItems as $item)
                            <label class="flex items-center gap-2 rounded-md border border-gray-100 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <input type="checkbox" name="menus[]" value="{{ $item['key'] }}"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                       @checked(in_array($item['key'], old('menus', $selectedKeys), true))
                                       @disabled($selectedRole->slug === 'admin' && in_array($item['key'], ['settings', 'tools.database'], true))>
                                <span>
                                    {{ $item['label'] }}
                                    <span class="text-xs text-gray-400">{{ $item['key'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @if ($selectedRole->slug === 'admin')
                        <p class="mt-2 text-xs text-gray-500">Menu Settings dan Database &amp; Tools wajib aktif untuk role Admin.</p>
                        <input type="hidden" name="menus[]" value="settings">
                        <input type="hidden" name="menus[]" value="tools.database">
                    @endif
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-gray-100">
                    <div>
                        @unless ($selectedRole->is_system)
                            <x-danger-button
                                form="delete-role-{{ $selectedRole->id }}"
                                onclick="return confirmSubmit(event, 'Hapus role ini?')"
                            >Hapus Role</x-danger-button>
                        @endunless
                    </div>
                    <x-primary-button>Simpan Menu Role</x-primary-button>
                </div>
            </form>

            @unless ($selectedRole->is_system)
                <form id="delete-role-{{ $selectedRole->id }}" method="POST" action="{{ route('settings.roles.destroy', $selectedRole) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endunless
        @else
            <div class="rounded-lg border border-dashed border-gray-300 px-6 py-12 text-center text-sm text-gray-500">
                Belum ada role.
            </div>
        @endif
    </div>
</div>
