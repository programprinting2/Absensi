import './echo';

// Daftarkan Alpine.data via alpine:init SEBELUM Livewire.start().
import './parameter-autocomplete';
import './master-type-autocomplete';
import './currency';
import './time-input';
import './toast';
import './payroll-settings';
import './attendance-calendar';

import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

// Wajib: dengan inject_assets=false + @livewireScriptConfig,
// Livewire TIDAK AUTO-start — harus dipanggil manual.
Livewire.start();

