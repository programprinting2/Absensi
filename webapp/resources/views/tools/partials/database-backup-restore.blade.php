<div class="br-wrap">

  {{-- Header --}}
  <div class="br-header">
    <div class="d-flex align-items-center gap-3">
      <div class="br-header-icon">
        <i data-feather="hard-drive" style="width:22px;height:22px"></i>
      </div>
      <div>
        <h4 class="br-header-title">Backup &amp; Restore</h4>
        <p class="br-header-sub">Kelola backup dan restore database dengan mudah dan aman</p>
      </div>
    </div>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#br-help-panel">
      <i data-feather="help-circle" style="width:13px;height:13px"></i> Help
    </button>
  </div>

  <div class="collapse mb-3" id="br-help-panel">
    <div class="br-section" style="background:#f8f9fa;font-size:.8rem;">
      <strong>Tips:</strong> Selalu buat backup terbaru sebelum restore. Proses bisa memakan waktu beberapa menit — jangan tutup tab browser saat berjalan.
    </div>
  </div>

  {{-- Stat cards --}}
  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
      <div class="br-stat-card">
        <div class="br-stat-icon blue"><i data-feather="database" style="width:16px;height:16px"></i></div>
        <div>
          <div class="br-stat-label">Driver</div>
          <div class="br-stat-value">{{ $connection['driver'] }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="br-stat-card">
        <div class="br-stat-icon green"><i data-feather="grid" style="width:16px;height:16px"></i></div>
        <div>
          <div class="br-stat-label">Jumlah Tabel</div>
          <div class="br-stat-value">{{ $tableData->count() }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="br-stat-card">
        <div class="br-stat-icon orange"><i data-feather="server" style="width:16px;height:16px"></i></div>
        <div>
          <div class="br-stat-label">Total Ukuran DB</div>
          <div class="br-stat-value">{{ $totalSize->total ?? '-' }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="br-stat-card">
        <div class="br-stat-icon purple"><i data-feather="archive" style="width:16px;height:16px"></i></div>
        <div>
          <div class="br-stat-label">Total Backup</div>
          <div class="br-stat-value">{{ $backupCount }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Informasi server --}}
  <div class="br-section mb-3">
    <div class="br-section-title"><i data-feather="server" style="width:14px;height:14px"></i> Informasi Koneksi</div>
    <div class="row g-3">
      <div class="col-6 col-md-3">
        <div class="connection-item">
          <span class="label">Driver</span>
          <span class="value">{{ $connection['driver'] }}</span>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="connection-item">
          <span class="label">Region</span>
          <span class="value">{{ $connection['region'] }}</span>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="connection-item">
          <span class="label">Database</span>
          <span class="value">{{ $connection['database'] }}</span>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="connection-item">
          <span class="label">Username</span>
          <span class="value">{{ $connection['username'] }}</span>
        </div>
      </div>
      <div class="col-12 col-md-6">
        <div class="connection-item">
          <span class="label">Host</span>
          <span class="value" style="font-size:.82rem">{{ $connection['host'] }}</span>
        </div>
      </div>
      <div class="col-12">
        <div class="connection-item">
          <span class="label">Supabase URL</span>
          <span class="value" style="font-size:.82rem">
            @if($connection['url'] !== '-')
              <a href="{{ $connection['url'] }}" target="_blank">{{ $connection['url'] }}</a>
            @else
              -
            @endif
          </span>
        </div>
      </div>
    </div>
  </div>

  {{-- Backup & Restore panels --}}
  <div class="row g-3 mb-3">
    <div class="col-12 col-xl-6">
      <div class="br-action-card backup h-100">
        <div class="br-action-header">
          <div class="br-action-icon backup"><i data-feather="download-cloud" style="width:18px;height:18px"></i></div>
          <div>
            <div class="br-action-title">Backup Database</div>
            <div class="br-action-desc">Buat salinan database untuk keamanan data</div>
          </div>
        </div>

        <div class="br-field-label">Backup Scope</div>
        <div class="row g-2 mb-3">
          <div class="col-12 col-md-6">
            <label class="br-option backup-option d-flex align-items-start gap-2 h-100">
              <input class="form-check-input mt-1" type="radio" name="backup-scope" value="full" checked>
              <div>
                <div class="br-option-title">Full Backup</div>
                <div class="br-option-desc">Seluruh data + file CSV per tabel</div>
              </div>
            </label>
          </div>
          <div class="col-12 col-md-6">
            <label class="br-option backup-option d-flex align-items-start gap-2 h-100">
              <input class="form-check-input mt-1" type="radio" name="backup-scope" value="structure">
              <div>
                <div class="br-option-title">Database Structure</div>
                <div class="br-option-desc">Hanya struktur database (schema)</div>
              </div>
            </label>
          </div>
        </div>

        <div class="br-field-label">Storage Type</div>
        <div class="row g-2 mb-3">
          <div class="col-12 col-md-6">
            <label class="br-option backup-option d-flex align-items-start gap-2 h-100">
              <input class="form-check-input mt-1" type="radio" name="backup-storage-type" value="local" checked>
              <div>
                <div class="br-option-title">Local Storage</div>
                <div class="br-option-desc">Simpan di server, dapat diunduh</div>
                <code class="d-block small text-break mt-1">{{ ($backupSchedule['local_backup_dir'] ?? storage_path('app')) }}\backup_*.tar.gz</code>
              </div>
            </label>
          </div>
          <div class="col-12 col-md-6">
            <label class="br-option backup-option d-flex align-items-start gap-2 h-100">
              <input class="form-check-input mt-1" type="radio" name="backup-storage-type" value="cloud">
              <div>
                <div class="br-option-title">Cloud Storage</div>
                <div class="br-option-desc">Upload langsung ke Google Drive</div>
              </div>
            </label>
          </div>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-3">
          <div>
            <div class="br-field-label mb-1">Jalankan di background</div>
            <div class="br-option-desc">Proses berjalan tanpa memblokir halaman</div>
          </div>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="backup-background-toggle" checked disabled>
          </div>
        </div>

        <button id="btn-backup" type="button" class="btn btn-primary w-100">
          <i data-feather="upload" style="width:14px;height:14px"></i> Mulai Backup
        </button>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="br-action-card restore h-100">
        <div class="br-action-header">
          <div class="br-action-icon restore"><i data-feather="upload-cloud" style="width:18px;height:18px"></i></div>
          <div>
            <div class="br-action-title">Restore Database</div>
            <div class="br-action-desc">Kembalikan database dari file backup</div>
          </div>
        </div>

        <div class="br-field-label">Restore Mode</div>
        <div class="row g-2 mb-3">
          <div class="col-12 col-md-6">
            <label class="br-option restore-option d-flex align-items-start gap-2 h-100">
              <input class="form-check-input mt-1" type="radio" name="restore-mode" value="full" checked>
              <div>
                <div class="br-option-title">Full Restore</div>
                <div class="br-option-desc">Timpa seluruh database dari backup</div>
              </div>
            </label>
          </div>
          <div class="col-12 col-md-6">
            <label class="br-option restore-option d-flex align-items-start gap-2 h-100">
              <input class="form-check-input mt-1" type="radio" name="restore-mode" value="table">
              <div>
                <div class="br-option-title">Per Tabel</div>
                <div class="br-option-desc">Pilih tabel tertentu untuk di-restore</div>
              </div>
            </label>
          </div>
        </div>

        <div class="br-field-label">Restore Source</div>
        <div class="row g-2 mb-3">
          <div class="col-12 col-md-6">
            <label class="br-option restore-option d-flex align-items-start gap-2 h-100">
              <input class="form-check-input mt-1" type="radio" name="restore-source-type" value="local" checked>
              <div>
                <div class="br-option-title">Local File</div>
                <div class="br-option-desc">Upload file backup dari komputer</div>
              </div>
            </label>
          </div>
          <div class="col-12 col-md-6">
            <label class="br-option restore-option d-flex align-items-start gap-2 h-100">
              <input class="form-check-input mt-1" type="radio" name="restore-source-type" value="cloud">
              <div>
                <div class="br-option-title">Google Drive</div>
                <div class="br-option-desc">Pilih file dari Google Drive</div>
              </div>
            </label>
          </div>
        </div>

        <div id="restore-local-source">
          <div class="br-dropzone" id="restore-dropzone">
            <i data-feather="upload" style="width:24px;height:24px;color:#198754"></i>
            <div class="br-dropzone-title">Klik atau drag file backup ke sini</div>
            <div class="br-dropzone-desc">Format: .tar.gz, .sql, .backup (Max 2GB)</div>
            <div id="restore-selected-file" class="br-dropzone-file d-none"></div>
          </div>
          <input type="file" class="d-none" accept=".gz,.tar,.sql,.backup" id="restore-file-input">
          <button type="button" class="btn btn-success w-100 mt-3" id="btn-restore" disabled>
            <i data-feather="upload" style="width:14px;height:14px"></i> Mulai Restore
          </button>
        </div>

        <div class="d-none" id="restore-drive-source">
          <div class="d-flex flex-column gap-2">
            <button type="button" class="btn btn-outline-success btn-sm" id="btn-select-drive-file">
              <i data-feather="folder" style="width:13px;height:13px"></i>
              Pilih File dari Google Drive
            </button>
            <div id="selected-drive-file" class="small text-muted d-none"></div>
            <button type="button" class="btn btn-success btn-sm" id="btn-restore-drive" disabled>
              <i data-feather="upload" style="width:13px;height:13px"></i> Mulai Restore dari Google Drive
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Scheduled Backup --}}
  <div class="br-section mb-3" id="br-scheduled-backup">
    <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap mb-3">
      <div>
        <div class="br-section-title mb-1"><i data-feather="clock" style="width:14px;height:14px"></i> Scheduled Backup</div>
        <div class="br-option-desc">
          Otomatis backup database —
          <span id="schedule-os-label">{{ $backupSchedule['os_label'] ?? 'Deteksi OS...' }}</span>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span id="schedule-installed-badge" class="badge {{ ($backupSchedule['installed'] ?? false) ? 'bg-success' : 'bg-secondary' }} br-schedule-badge">
          {{ ($backupSchedule['installed'] ?? false) ? 'Aktif' : 'Nonaktif' }}
        </span>
        <div class="form-check form-switch mb-0">
          <input class="form-check-input" type="checkbox" role="switch" id="schedule-enabled"
            {{ ($backupSchedule['installed'] ?? false) ? 'checked' : '' }}
            aria-checked="{{ ($backupSchedule['installed'] ?? false) ? 'true' : 'false' }}">
        </div>
      </div>
    </div>

    <div id="schedule-settings-panel">
      <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
          <label class="br-field-label" for="schedule-frequency">Frekuensi</label>
          <select id="schedule-frequency" class="form-select form-select-sm">
            <option value="hourly" {{ ($backupSchedule['config']['frequency'] ?? '') === 'hourly' ? 'selected' : '' }}>Setiap Jam</option>
            <option value="daily" {{ ($backupSchedule['config']['frequency'] ?? 'daily') === 'daily' ? 'selected' : '' }}>Harian</option>
            <option value="weekly" {{ ($backupSchedule['config']['frequency'] ?? '') === 'weekly' ? 'selected' : '' }}>Mingguan</option>
          </select>
        </div>
        <div class="col-12 col-md-4">
          <label class="br-field-label" for="schedule-time">Waktu</label>
          <input type="time" id="schedule-time" class="form-control form-control-sm"
            value="{{ $backupSchedule['config']['time'] ?? '02:00' }}">
        </div>
        <div class="col-12 col-md-4" id="schedule-weekday-wrap">
          <label class="br-field-label" for="schedule-weekday">Hari (mingguan)</label>
          <select id="schedule-weekday" class="form-select form-select-sm">
            @foreach(['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'] as $i => $day)
            <option value="{{ $i }}" {{ (int)($backupSchedule['config']['weekday'] ?? 1) === $i ? 'selected' : '' }}>{{ $day }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12 col-md-6">
          <label class="br-field-label" for="schedule-storage">Storage</label>
          <select id="schedule-storage" class="form-select form-select-sm">
            <option value="local" {{ ($backupSchedule['config']['storage_type'] ?? 'local') === 'local' ? 'selected' : '' }}>Local Storage</option>
            <option value="cloud" {{ ($backupSchedule['config']['storage_type'] ?? '') === 'cloud' ? 'selected' : '' }}>Google Drive</option>
          </select>
          <div class="br-option-desc mt-1" id="schedule-storage-path-wrap">
            <span class="text-muted">Lokasi simpan:</span>
            <code id="schedule-storage-path" class="d-block text-break small mt-1">{{ ($backupSchedule['local_backup_dir'] ?? storage_path('app')) }}\backup_YYYYMMDD_HHMMSS.tar.gz</code>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <label class="br-field-label" for="schedule-scope">Scope</label>
          <select id="schedule-scope" class="form-select form-select-sm">
            <option value="full" {{ ($backupSchedule['config']['backup_scope'] ?? 'full') === 'full' ? 'selected' : '' }}>Full Backup</option>
            <option value="structure" {{ ($backupSchedule['config']['backup_scope'] ?? '') === 'structure' ? 'selected' : '' }}>Structure Only</option>
          </select>
        </div>
      </div>

      <div class="mb-3">
        <div class="br-field-label mb-1">Preview perintah scheduler</div>
        <div class="br-schedule-preview" id="schedule-preview-text">
          @if(($backupSchedule['os'] ?? '') === 'windows')
            Task: {{ $backupSchedule['preview']['task_name'] ?? 'Absensi_DatabaseBackup' }}
            — {{ $backupSchedule['preview']['schedule_label'] ?? '-' }}
          @elseif(($backupSchedule['os'] ?? '') === 'linux')
            {{ $backupSchedule['preview']['cron_line'] ?? 'Cron belum dikonfigurasi' }}
          @else
            OS tidak didukung untuk auto-install scheduler.
          @endif
        </div>
      </div>

      <div class="row g-2 small text-muted mb-3" id="schedule-last-run">
        <div class="col-md-4">Terakhir jalan: <strong id="schedule-last-run-at">{{ $backupSchedule['config']['last_run_at'] ? \Carbon\Carbon::parse($backupSchedule['config']['last_run_at'])->format('d/m/Y H:i') : '-' }}</strong></div>
        <div class="col-md-4">Status: <strong id="schedule-last-status">{{ $backupSchedule['config']['last_status'] ?? '-' }}</strong></div>
        <div class="col-md-4">File: <strong id="schedule-last-file" class="text-break">{{ $backupSchedule['config']['last_file'] ?? '-' }}</strong></div>
      </div>

      <div id="schedule-status-alert" class="alert py-2 px-3 d-none mb-3" style="font-size:.8rem;"></div>

      <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-primary btn-sm" id="btn-schedule-apply">
          <i data-feather="check-circle" style="width:13px;height:13px"></i> Simpan &amp; Terapkan
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-schedule-disable">
          <i data-feather="x-circle" style="width:13px;height:13px"></i> Nonaktifkan
        </button>
      </div>
    </div>
  </div>

  {{-- Tables & History --}}
  <div class="row g-3">
    <div class="col-12 col-xl-7">
      <div class="br-section h-100">
        <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
          <div class="br-section-title mb-0"><i data-feather="layers" style="width:14px;height:14px"></i> Daftar Tabel</div>
          <div class="d-flex align-items-center gap-2">
            <input type="search" id="br-table-search" class="form-control form-control-sm" placeholder="Cari tabel..." style="width:160px;font-size:.78rem">
            <button id="btn-clear-tables" type="button" class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1" disabled>
              <i data-feather="trash-2" style="width:13px;height:13px"></i>
              Clear (<span id="clear-tables-count">0</span>)
            </button>
          </div>
        </div>
        <div style="max-height:380px;overflow-y:auto;">
          <table class="table table-sm table-hover mb-0" id="br-tables-table">
            <thead class="table-light sticky-top">
              <tr>
                <th style="width:34px;" class="text-center">
                  <input type="checkbox" class="form-check-input" id="clear-tables-select-all" title="Pilih semua">
                </th>
                <th style="width:40px">#</th>
                <th>Nama Tabel</th>
                <th class="text-end">Record</th>
                <th class="text-end">Ukuran</th>
              </tr>
            </thead>
            <tbody>
              @foreach($tableData as $i => $t)
              <tr data-table-name="{{ strtolower($t['name']) }}">
                <td class="text-center">
                  <input type="checkbox" class="form-check-input tbl-check" value="{{ $t['name'] }}" data-rows="{{ $t['row_count'] }}">
                </td>
                <td class="text-muted">{{ $i + 1 }}</td>
                <td><code style="font-size:.8rem">{{ $t['name'] }}</code></td>
                <td class="text-end">{{ $t['row_count'] }}</td>
                <td class="text-end text-muted">{{ $t['size'] }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-5">
      <div class="br-section h-100">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="br-section-title mb-0"><i data-feather="clock" style="width:14px;height:14px"></i> Riwayat Backup &amp; Restore</div>
          <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.location.reload()">
            <i data-feather="refresh-cw" style="width:13px;height:13px"></i> Refresh
          </button>
        </div>
        <div style="max-height:380px;overflow-y:auto;">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light sticky-top">
              <tr>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>File / Detail</th>
                <th class="text-end">Ukuran</th>
                <th class="text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              @forelse($backupHistory as $item)
              <tr>
                <td style="font-size:.75rem;white-space:nowrap">{{ $item['date'] }}</td>
                <td><span class="badge bg-primary" style="font-size:.68rem">{{ $item['type_label'] }}</span></td>
                <td style="font-size:.75rem;word-break:break-all">{{ $item['file'] }}</td>
                <td class="text-end" style="font-size:.75rem">{{ $item['size'] }}</td>
                <td class="text-center"><span class="badge bg-success" style="font-size:.68rem">{{ $item['status'] }}</span></td>
              </tr>
              @empty
              <tr>
                <td colspan="5" class="text-center text-muted py-3" style="font-size:.8rem">Belum ada riwayat backup.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>
