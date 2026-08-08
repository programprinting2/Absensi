import './echo';

// Daftarkan Alpine.data via alpine:init SEBELUM Livewire.start().
import './parameter-autocomplete';
import './master-type-autocomplete';
import './currency';
import './time-input';
import './toast';
import './dialog';
import { installLivewireConfirm } from './dialog';
import './payroll-settings';
import './attendance-calendar';

import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

// Override wire:confirm agar pakai dialog app (bukan window.confirm).
installLivewireConfirm(Livewire);

// Wajib: dengan inject_assets=false + @livewireScriptConfig,
// Livewire TIDAK AUTO-start — harus dipanggil manual.
Livewire.start();

