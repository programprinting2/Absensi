<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Settings</h2>
    </x-slot>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      /* Bootstrap reboot menambahkan underline pada semua <a> — netralisasi ke style Absensi. */
      a,
      a:hover,
      a:focus,
      a:active {
        text-decoration: none !important;
      }

      .db-tools-scope { font-size: 0.925rem; color: #111827; }
      .db-tools-scope .card { border-color: #e5e7eb; box-shadow: none; }
      .db-tools-scope .btn-primary { background-color: #111827; border-color: #111827; }
      .db-tools-scope .btn-primary:hover { background-color: #1f2937; border-color: #1f2937; }
      .db-tools-scope .nav-tabs .nav-link.active { color: #f7340d; border-color: #e5e7eb #e5e7eb #fff; font-weight: 600; }
      .db-tools-scope .nav-tabs .nav-link { color: #6b7280; text-decoration: none !important; }
      .db-tools-scope .page-breadcrumb { display: none; }
.db-stat-card {
    border-left: 4px solid;
    border-radius: 6px;
  }
  .db-stat-card.blue  { border-color: #0d6efd; }
  .db-stat-card.green { border-color: #198754; }
  .db-stat-card.orange{ border-color: #fd7e14; }
  .db-stat-card.purple{ border-color: #6f42c1; }
  .table-sm td, .table-sm th { font-size: .82rem; }
  .badge-batch { font-size: .7rem; }
  .section-title { font-size: .85rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; margin-bottom: 12px; }
  .connection-item { display: flex; flex-direction: column; }
  .connection-item .label { font-size: .72rem; color: #6c757d; }
  .connection-item .value { font-size: .9rem; font-weight: 600; word-break: break-all; }
  .backup-card {
    border: 1px solid #dee2e6;
    border-radius: 10px;
    background: #fff;
    transition: border-color .2s, box-shadow .2s;
  }
  .backup-card:hover {
    border-color: #cfd4da;
    box-shadow: 0 2px 8px rgba(33, 37, 41, .05);
  }
  .storage-option {
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 12px;
    background: #fff;
    transition: border-color .2s, background .2s;
  }
  .storage-option:has(input:checked) {
    border-color: #0d6efd;
    background: #f8f9fa;
  }
  .storage-option .form-check-input { margin-top: .2rem; }
  .drive-picker-row { cursor: pointer; }
  .drive-picker-row.active { background: #f8fbff; }
  .migration-step {
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 14px;
    background: #fff;
  }
  .migration-step .step-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #0d6efd;
    margin-bottom: 8px;
  }
  .migration-step .step-number {
    width: 22px;
    height: 22px;
    border-radius: 999px;
    background: #0d6efd;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .7rem;
  }
  .migration-option-card {
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 12px;
    height: 100%;
  }
  .migration-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
  }
  .migration-stat {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 10px;
    background: #f8f9fa;
  }
  .migration-stat .label {
    display: block;
    color: #6c757d;
    font-size: .72rem;
    margin-bottom: 2px;
  }
  .migration-stat .value {
    display: block;
    font-size: .9rem;
    font-weight: 700;
  }
  .migration-field-label {
    display: block;
    font-size: .74rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 4px;
  }

    </style>

    <div class="h-[calc(100vh-8rem)] flex flex-col">
        <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-3">
            <div class="bg-white shadow-sm rounded-lg overflow-hidden flex-1 flex flex-col min-h-0">
                @include('settings._settings-tabs')
                <div class="db-tools-scope flex-1 min-h-0 overflow-y-auto p-4 sm:p-6">
<nav class="page-breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('tools.database') }}">Tools</a></li>
      <li class="breadcrumb-item active">Database</li>
    </ol>
  </nav>

  <ul class="nav nav-tabs mb-3" id="databaseToolTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="backup-restore-tab" data-bs-toggle="tab" data-bs-target="#backup-restore-pane" type="button" role="tab" aria-controls="backup-restore-pane" aria-selected="true">
        Backup / Restore
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="migration-server-tab" data-bs-toggle="tab" data-bs-target="#migration-server-pane" type="button" role="tab" aria-controls="migration-server-pane" aria-selected="false">
        Migration Server
      </button>
    </li>
  </ul>

  <div class="tab-content" id="databaseToolTabContent">
    <div class="tab-pane fade show active" id="backup-restore-pane" role="tabpanel" aria-labelledby="backup-restore-tab" tabindex="0">
      <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
          <div class="card db-stat-card blue h-100">
            <div class="card-body py-3">
              <p class="text-muted mb-1" style="font-size:.78rem">Driver</p>
              <h5 class="mb-0">{{ $connection['driver'] }}</h5>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card db-stat-card green h-100">
            <div class="card-body py-3">
              <p class="text-muted mb-1" style="font-size:.78rem">Jumlah Tabel</p>
              <h5 class="mb-0">{{ $tableData->count() }}</h5>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card db-stat-card orange h-100">
            <div class="card-body py-3">
              <p class="text-muted mb-1" style="font-size:.78rem">Total Ukuran DB</p>
              <h5 class="mb-0">{{ $totalSize->total ?? '-' }}</h5>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card db-stat-card purple h-100">
            <div class="card-body py-3">
              <p class="text-muted mb-1" style="font-size:.78rem">Total Migrasi</p>
              <h5 class="mb-0">{{ count($migrations) }}</h5>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <p class="section-title"><i data-feather="server" style="width:13px;height:13px"></i> Informasi Koneksi</p>
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
                <div class="col-12 col-md-6">
                  <div class="connection-item">
                    <span class="label">Host (Pooler)</span>
                    <span class="value" style="font-size:.82rem">{{ $connection['host'] }}</span>
                  </div>
                </div>
                <div class="col-12">
                  <div class="connection-item">
                    <span class="label">Supabase URL</span>
                    <span class="value" style="font-size:.82rem">
                      <a href="{{ $connection['url'] }}" target="_blank">{{ $connection['url'] }}</a>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-12 col-xl-7">
          <div class="card h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
                <p class="section-title mb-0"><i data-feather="layers" style="width:13px;height:13px"></i> Tabel & Jumlah Record</p>
                <button id="btn-clear-tables" type="button" class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1" disabled>
                  <i data-feather="trash-2" style="width:13px;height:13px;"></i>
                  Clear Table (<span id="clear-tables-count">0</span>)
                </button>
              </div>
              <div style="max-height:420px; overflow-y:auto;">
                <table class="table table-sm table-hover mb-0">
                  <thead class="table-light sticky-top">
                    <tr>
                      <th style="width:34px;" class="text-center">
                        <input type="checkbox" class="form-check-input" id="clear-tables-select-all" title="Pilih semua">
                      </th>
                      <th>#</th>
                      <th>Nama Tabel</th>
                      <th class="text-end">Record</th>
                      <th class="text-end">Ukuran</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($tableData as $i => $t)
                    <tr>
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
        </div>

        <div class="col-12 col-xl-5">
          <div class="card h-100">
            <div class="card-body">
              <p class="section-title"><i data-feather="git-commit" style="width:13px;height:13px"></i> Riwayat Migrasi</p>
              <div style="max-height:420px; overflow-y:auto;">
                <table class="table table-sm table-hover mb-0">
                  <thead class="table-light sticky-top">
                    <tr>
                      <th>Nama Migrasi</th>
                      <th class="text-center">Batch</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($migrations as $mig)
                    <tr>
                      <td style="font-size:.75rem; word-break:break-all;">{{ $mig->migration }}</td>
                      <td class="text-center">
                        <span class="badge bg-secondary badge-batch">{{ $mig->batch }}</span>
                      </td>
                    </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <p class="section-title"><i data-feather="database" style="width:13px;height:13px"></i> Backup & Restore</p>

              <div class="row g-3">
                <div class="col-12 col-lg-6">
                  <div class="backup-card p-3 d-flex flex-column gap-2 h-100">
                    <div>
                      <div class="text-muted mb-2" style="font-size:.8rem;font-weight:600;">Backup Scope</div>
                      <div class="row g-2 mb-3">
                        <div class="col-12 col-md-6">
                          <label class="storage-option d-flex align-items-start gap-3 h-100">
                            <input class="form-check-input" type="radio" name="backup-scope" value="full" checked>
                            <div>
                              <div style="font-size:.95rem;font-weight:600;">Full Backup</div>
                              <div class="text-muted" style="font-size:.78rem;">SQL data + CSV per tabel</div>
                            </div>
                          </label>
                        </div>
                        <div class="col-12 col-md-6">
                          <label class="storage-option d-flex align-items-start gap-3 h-100">
                            <input class="form-check-input" type="radio" name="backup-scope" value="structure">
                            <div>
                              <div style="font-size:.95rem;font-weight:600;">Database Structure</div>
                              <div class="text-muted" style="font-size:.78rem;">Schema/struktur database saja</div>
                            </div>
                          </label>
                        </div>
                      </div>

                      <div class="text-muted mb-2" style="font-size:.8rem;font-weight:600;">Storage Type</div>
                      <div class="row g-2 mb-3">
                        <div class="col-12 col-md-6">
                          <label class="storage-option d-flex align-items-start gap-3 h-100">
                            <input class="form-check-input" type="radio" name="backup-storage-type" value="local" checked>
                            <div>
                              <div style="font-size:.95rem;font-weight:600;">Local Storage</div>
                              <div class="text-muted" style="font-size:.78rem;">Store backup di server lalu diunduh ke browser</div>
                            </div>
                          </label>
                        </div>
                        <div class="col-12 col-md-6">
                          <label class="storage-option d-flex align-items-start gap-3 h-100">
                            <input class="form-check-input" type="radio" name="backup-storage-type" value="cloud">
                            <div>
                              <div style="font-size:.95rem;font-weight:600;">Cloud Storage</div>
                              <div class="text-muted" style="font-size:.78rem;">Upload hasil backup langsung ke Google Drive</div>
                            </div>
                          </label>
                        </div>
                      </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                      <div style="width:36px;height:36px;background:rgba(13,110,253,.1);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i data-feather="download-cloud" style="width:16px;height:16px;color:#0d6efd;"></i>
                      </div>
                      <div>
                        <div style="font-size:.88rem;font-weight:600;">Download Backup</div>
                        <div style="font-size:.76rem;color:#6c757d;">Diproses di background, link download muncul saat file siap</div>
                      </div>
                    </div>
                    <p style="font-size:.78rem;color:#6c757d;margin-bottom:4px;">
                      Mengunduh seluruh data sebagai <code>.sql</code> dan <code>.csv</code> per tabel, dikemas dalam satu file <code>backup_YYYYMMDD_HHiiss.tar.gz</code>.
                    </p>
                    <button id="btn-backup" type="button" class="btn btn-primary btn-sm d-flex align-items-center justify-content-center gap-1 mt-auto">
                      <i data-feather="download" style="width:14px;height:14px;"></i>
                      <span>Download Backup</span>
                    </button>
                  </div>
                </div>

                <div class="col-12 col-lg-6">
                  <div class="backup-card p-3 d-flex flex-column gap-2 h-100" style="border-color:#dc3545;">
                    <div>
                      <div class="text-muted mb-2" style="font-size:.8rem;font-weight:600;">Restore Mode</div>
                      <div class="row g-2 mb-3">
                        <div class="col-12 col-md-6">
                          <label class="storage-option d-flex align-items-start gap-3 h-100">
                            <input class="form-check-input" type="radio" name="restore-mode" value="full" checked>
                            <div>
                              <div style="font-size:.95rem;font-weight:600;">Full Restore</div>
                              <div class="text-muted" style="font-size:.78rem;">Seluruh database ditimpa dari backup</div>
                            </div>
                          </label>
                        </div>
                        <div class="col-12 col-md-6">
                          <label class="storage-option d-flex align-items-start gap-3 h-100">
                            <input class="form-check-input" type="radio" name="restore-mode" value="table">
                            <div>
                              <div style="font-size:.95rem;font-weight:600;">Per Tabel</div>
                              <div class="text-muted" style="font-size:.78rem;">Pilih tabel tertentu untuk ditimpa</div>
                            </div>
                          </label>
                        </div>
                      </div>

                      <div class="text-muted mb-2" style="font-size:.8rem;font-weight:600;">Restore Source</div>
                      <div class="row g-2 mb-3">
                        <div class="col-12 col-md-6">
                          <label class="storage-option d-flex align-items-start gap-3 h-100">
                            <input class="form-check-input" type="radio" name="restore-source-type" value="local" checked>
                            <div>
                              <div style="font-size:.95rem;font-weight:600;">Local Drive</div>
                              <div class="text-muted" style="font-size:.78rem;">Pilih file backup dari komputer/server lokal</div>
                            </div>
                          </label>
                        </div>
                        <div class="col-12 col-md-6">
                          <label class="storage-option d-flex align-items-start gap-3 h-100">
                            <input class="form-check-input" type="radio" name="restore-source-type" value="cloud">
                            <div>
                              <div style="font-size:.95rem;font-weight:600;">Google Drive</div>
                              <div class="text-muted" style="font-size:.78rem;">Pilih file backup dari folder Google Drive</div>
                            </div>
                          </label>
                        </div>
                      </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                      <div style="width:36px;height:36px;background:rgba(220,53,69,.1);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i data-feather="upload-cloud" style="width:16px;height:16px;color:#dc3545;"></i>
                      </div>
                      <div>
                        <div style="font-size:.88rem;font-weight:600;">Restore dari Backup</div>
                        <div style="font-size:.76rem;color:#dc3545;font-weight:500;">Upload dulu, lalu restore diproses di background queue</div>
                      </div>
                    </div>
                    <p style="font-size:.78rem;color:#6c757d;margin-bottom:4px;">
                      Upload file <code>.tar.gz</code> hasil backup. Setelah file selesai diupload, proses restore berjalan di background dan progress tetap dipantau dari modal ini.
                    </p>
                    <div class="mt-auto" id="restore-local-source">
                      <div class="input-group input-group-sm">
                        <input type="file" class="form-control form-control-sm" accept=".gz,.tar" id="restore-file-input">
                        <button type="button" class="btn btn-danger btn-sm" id="btn-restore" disabled>
                          <i data-feather="upload" style="width:13px;height:13px;"></i> Restore
                        </button>
                      </div>
                    </div>
                    <div class="mt-auto d-none" id="restore-drive-source">
                      <div class="d-flex flex-column gap-2">
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btn-select-drive-file">
                          <i data-feather="folder" style="width:13px;height:13px;"></i>
                          Pilih File dari Google Drive
                        </button>
                        <div id="selected-drive-file" class="small text-muted d-none"></div>
                        <button type="button" class="btn btn-danger btn-sm" id="btn-restore-drive" disabled>
                          <i data-feather="upload" style="width:13px;height:13px;"></i> Restore dari Google Drive
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="alert alert-warning mb-0 mt-3 py-2 px-3" style="font-size:.78rem;">
                <i data-feather="alert-triangle" style="width:13px;height:13px;"></i>
                <strong>Penting:</strong> Proses backup/restore bisa memakan waktu beberapa menit. Jangan tutup tab browser saat proses berlangsung.
                Selalu buat backup terbaru sebelum melakukan restore.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="migration-server-pane" role="tabpanel" aria-labelledby="migration-server-tab" tabindex="0">
      <div class="row g-3 mb-3">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <p class="section-title"><i data-feather="shuffle" style="width:13px;height:13px"></i> Server Migration Wizard</p>
              <p class="text-muted mb-3" style="font-size:.88rem;">Migrasi dari server lama ke server baru, mencakup database, file upload, storage, dan konfigurasi aplikasi.</p>

              <div class="row g-2 mb-2">
                <div class="col-12 col-md-6">
                  <div class="migration-option-card">
                    <div class="d-flex align-items-center gap-2 mb-1">
                      <i data-feather="database" style="width:14px;height:14px;color:#0d6efd"></i>
                      <strong style="font-size:.88rem;">Opsi 1 - Database Only</strong>
                    </div>
                    <p class="text-muted mb-0" style="font-size:.8rem;">Migrasi struktur dan data database menggunakan koneksi source dan destination.</p>
                  </div>
                </div>
                <div class="col-12 col-md-6">
                  <div class="migration-option-card">
                    <div class="d-flex align-items-center gap-2 mb-1">
                      <i data-feather="package" style="width:14px;height:14px;color:#198754"></i>
                      <strong style="font-size:.88rem;">Opsi 2 - Full Migration</strong>
                    </div>
                    <p class="text-muted mb-0" style="font-size:.8rem;">Database + file upload + storage + konfigurasi aplikasi (arsitektur package).</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-12 col-xl-6">
          <div class="migration-step h-100">
            <div class="step-kicker"><span class="step-number">1</span> Source Server</div>
            <div class="row g-2">
              <div class="col-12 col-md-4">
                <label class="migration-field-label" for="migration-source-driver">Driver</label>
                <select id="migration-source-driver" class="form-select form-select-sm">
                  <option value="mysql" selected>MySQL</option>
                  <option value="pgsql">PostgreSQL</option>
                </select>
                <div class="form-text" style="font-size:.74rem;">Contoh: PostgreSQL untuk Supabase, MySQL untuk server lokal/VPS.</div>
              </div>
              <div class="col-12 col-md-4">
                <label class="migration-field-label" for="migration-source-host">Host</label>
                <input id="migration-source-host" class="form-control form-control-sm" placeholder="Host source">
                <div class="form-text" style="font-size:.74rem;">Contoh: 127.0.0.1, localhost, db.example.com.</div>
              </div>
              <div class="col-12 col-md-4">
                <label class="migration-field-label" for="migration-source-port">Port</label>
                <input id="migration-source-port" class="form-control form-control-sm" placeholder="Port (opsional)">
                <div class="form-text" style="font-size:.74rem;">Contoh: 3306 (MySQL), 5432 (PostgreSQL).</div>
              </div>
              <div class="col-12 col-md-6">
                <label class="migration-field-label" for="migration-source-database">Database</label>
                <input id="migration-source-database" class="form-control form-control-sm" placeholder="Database source">
                <div class="form-text" style="font-size:.74rem;">Contoh: erp_printing.</div>
              </div>
              <div class="col-12 col-md-6">
                <label class="migration-field-label" for="migration-source-username">Username</label>
                <input id="migration-source-username" class="form-control form-control-sm" placeholder="Username source">
                <div class="form-text" style="font-size:.74rem;">Contoh: root, postgres, erp_user.</div>
              </div>
              <div class="col-12">
                <label class="migration-field-label" for="migration-source-password">Password</label>
                <input id="migration-source-password" type="password" class="form-control form-control-sm" placeholder="Password source">
                <div class="form-text" style="font-size:.74rem;">Isi password user database source.</div>
              </div>
            </div>
            <div class="d-flex align-items-center justify-content-between mt-3">
              <small class="text-muted">Tes koneksi server lama</small>
              <div class="d-flex gap-2">
                <button id="btn-save-source-config" type="button" class="btn btn-outline-secondary btn-sm">Save</button>
                <button id="btn-load-source-config" type="button" class="btn btn-outline-secondary btn-sm">Load</button>
                <button id="btn-test-source" type="button" class="btn btn-outline-primary btn-sm">Test Source</button>
              </div>
            </div>
            <div id="migration-source-test-result" class="alert py-2 px-3 mb-0 mt-2 d-none" style="font-size:.8rem;"></div>
            <div id="analysis-source-panel" class="border rounded p-2 mt-2 bg-light d-none">
              <div class="fw-semibold mb-2" style="font-size:.82rem;color:#0d6efd;">Source Analysis</div>
              <div class="migration-stats-grid">
                <div class="migration-stat"><span class="label">Total Tabel</span><span class="value" id="analysis-source-tables">-</span></div>
                <div class="migration-stat"><span class="label">Total Record</span><span class="value" id="analysis-source-records">-</span></div>
                <div class="migration-stat"><span class="label">Ukuran DB</span><span class="value" id="analysis-source-size">-</span></div>
                <div class="migration-stat"><span class="label">Status</span><span class="value" id="analysis-source-status">Belum dites</span></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-xl-6">
          <div class="migration-step h-100">
            <div class="step-kicker"><span class="step-number">2</span> Destination Server</div>
            <div class="row g-2">
              <div class="col-12 col-md-4">
                <label class="migration-field-label" for="migration-destination-driver">Driver</label>
                <select id="migration-destination-driver" class="form-select form-select-sm">
                  <option value="mysql" selected>MySQL</option>
                  <option value="pgsql">PostgreSQL</option>
                </select>
                <div class="form-text" style="font-size:.74rem;">Contoh: pilih sesuai database target.</div>
              </div>
              <div class="col-12 col-md-4">
                <label class="migration-field-label" for="migration-destination-host">Host</label>
                <input id="migration-destination-host" class="form-control form-control-sm" placeholder="Host destination">
                <div class="form-text" style="font-size:.74rem;">Contoh: 10.10.10.5, mysql.internal, db-new.example.com.</div>
              </div>
              <div class="col-12 col-md-4">
                <label class="migration-field-label" for="migration-destination-port">Port</label>
                <input id="migration-destination-port" class="form-control form-control-sm" placeholder="Port (opsional)">
                <div class="form-text" style="font-size:.74rem;">Contoh: 3306 (MySQL), 5432 (PostgreSQL).</div>
              </div>
              <div class="col-12 col-md-6">
                <label class="migration-field-label" for="migration-destination-database">Database</label>
                <input id="migration-destination-database" class="form-control form-control-sm" placeholder="Database destination">
                <div class="form-text" style="font-size:.74rem;">Contoh: erp_printing_new.</div>
              </div>
              <div class="col-12 col-md-6">
                <label class="migration-field-label" for="migration-destination-username">Username</label>
                <input id="migration-destination-username" class="form-control form-control-sm" placeholder="Username destination">
                <div class="form-text" style="font-size:.74rem;">Contoh: migrator_user.</div>
              </div>
              <div class="col-12 col-md-6">
                <label class="migration-field-label" for="migration-destination-password">Password</label>
                <input id="migration-destination-password" type="password" class="form-control form-control-sm" placeholder="Password destination">
                <div class="form-text" style="font-size:.74rem;">Isi password user database destination.</div>
              </div>
            </div>
            <div class="d-flex align-items-center justify-content-between mt-3">
              <small class="text-muted">Tes koneksi server baru</small>
              <div class="d-flex gap-2">
                <button id="btn-save-destination-config" type="button" class="btn btn-outline-secondary btn-sm">Save</button>
                <button id="btn-load-destination-config" type="button" class="btn btn-outline-secondary btn-sm">Load</button>
                <button id="btn-test-destination" type="button" class="btn btn-outline-primary btn-sm">Test Destination</button>
              </div>
            </div>
            <div id="migration-destination-test-result" class="alert py-2 px-3 mb-0 mt-2 d-none" style="font-size:.8rem;"></div>
            <div id="analysis-destination-panel" class="border rounded p-2 mt-2 bg-light d-none">
              <div class="fw-semibold mb-2" style="font-size:.82rem;color:#198754;">Destination Analysis</div>
              <div class="migration-stats-grid">
                <div class="migration-stat"><span class="label">Total Tabel</span><span class="value" id="analysis-destination-tables">-</span></div>
                <div class="migration-stat"><span class="label">Total Record</span><span class="value" id="analysis-destination-records">-</span></div>
                <div class="migration-stat"><span class="label">Ukuran DB</span><span class="value" id="analysis-destination-size">-</span></div>
                <div class="migration-stat"><span class="label">Status</span><span class="value" id="analysis-destination-status">Belum dites</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-12">
          <div class="migration-step h-100">
            <div class="step-kicker"><span class="step-number">3</span> Migration Execution</div>
            <div class="mb-2" style="font-size:.82rem;">
              Strategi untuk data besar: gunakan batch chunkById, nonaktifkan foreign key check saat import, dan set ulang auto increment setelah selesai.
            </div>
            <div class="row g-2 mb-3">
              <div class="col-12 col-md-6">
                <label class="migration-field-label" for="migration-mode">Mode Migration</label>
                <select id="migration-mode" class="form-select form-select-sm">
                  <option value="full" selected>Full Migration (Structure + data)</option>
                  <option value="structure">DB Structure Only</option>
                </select>
              </div>
              <div class="col-12 col-md-6 d-flex align-items-end">
                <div class="small text-muted" style="font-size:.76rem;">
                  Pilih mode sebelum proses dijalankan. Full Migration akan membawa struktur dan isi data, sedangkan Structure Only hanya menyalin skema.
                </div>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-sm mb-2 align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Tabel</th>
                    <th style="width:100px;">Progress</th>
                    <th style="width:120px;">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td>customers</td><td>80%</td><td><span class="badge bg-warning">Running</span></td></tr>
                  <tr><td>orders</td><td>30%</td><td><span class="badge bg-warning">Running</span></td></tr>
                  <tr><td>invoices</td><td>100%</td><td><span class="badge bg-success">Done</span></td></tr>
                </tbody>
              </table>
            </div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-primary btn-sm" id="btn-start-migration">Start Migration</button>
              <button type="button" class="btn btn-success btn-sm" id="btn-switch-server">Switch ke Destination</button>
              <button type="button" class="btn btn-outline-danger btn-sm" id="btn-rollback-server">Rollback Switch</button>
              <button type="button" class="btn btn-outline-secondary btn-sm">Pause</button>
            </div>
          <div id="migration-execution-status" class="alert alert-info py-2 px-3 mt-3 mb-0 d-none" style="font-size:.8rem;"></div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-12 col-xl-6">
          <div class="migration-step h-100">
            <div class="step-kicker"><span class="step-number">5</span> Verification</div>
            <ul class="mb-2" style="font-size:.82rem; padding-left: 18px;">
              <li>Bandingkan COUNT(*) source vs destination per tabel</li>
              <li>Validasi tabel kritikal: customer, order, invoice</li>
              <li>Tandai status OK atau mismatch</li>
            </ul>
            <div class="alert alert-success py-2 px-3 mb-0" style="font-size:.8rem;">
              Contoh hasil: customer 12.500 vs 12.500 - OK
            </div>
          </div>
        </div>
        <div class="col-12 col-xl-6">
          <div class="migration-step h-100">
            <div class="step-kicker"><span class="step-number">!</span> Cakupan Migrasi Tabel</div>
            <div class="alert alert-success py-2 px-3 mb-0" style="font-size:.8rem;">
              Semua tabel dipindahkan tanpa pengecualian, termasuk tabel migrations.
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-12">
          <div class="card border-success">
            <div class="card-body">
              <p class="section-title text-success"><i data-feather="archive" style="width:13px;height:13px"></i> Rekomendasi Arsitektur: Export Package - Import Package</p>
              <p class="text-muted mb-2" style="font-size:.84rem;">Lebih stabil untuk shared hosting, VPS, maupun cloud, dan aman untuk database serta file berukuran besar.</p>
              <div class="row g-2" style="font-size:.8rem;">
                <div class="col-12 col-md-8">
                  <div class="border rounded p-2 bg-light">
                    migration.zip
                    <br>- database.sql
                    <br>- uploads.zip
                    <br>- config.json
                    <br>- manifest.json
                  </div>
                </div>
                <div class="col-12 col-md-4">
                  <div class="border rounded p-2 h-100">
                    <strong>Keuntungan</strong>
                    <br>- Bisa offline
                    <br>- Tidak perlu koneksi antar server
                    <br>- Bisa jadi backup jangka panjang
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modal-progress" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
      <div class="modal-content">
        <div class="modal-header border-0 pb-1">
          <h6 class="modal-title fw-bold d-flex align-items-center gap-2">
            <i id="progress-modal-icon" data-feather="download-cloud" style="width:16px;height:16px;color:#0d6efd;"></i>
            <span id="progress-modal-title">Backup Database</span>
          </h6>
        </div>
        <div class="modal-body py-2">
          <div class="progress mb-2" style="height:24px;border-radius:8px;background:#e9ecef;">
            <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%;font-size:.75rem;font-weight:700;transition:width .4s ease;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
          </div>
          <p id="progress-message" class="text-muted mb-0" style="font-size:.8rem;">Memulai...</p>
          <div class="d-flex justify-content-between align-items-center mt-1" style="font-size:.74rem;">
            <span id="progress-live-status" class="text-muted">Menunggu proses dimulai...</span>
            <span id="progress-elapsed" class="text-muted">00:00</span>
          </div>
          <div id="progress-detail" class="mt-2 d-none">
            <div class="border rounded p-2 bg-light" style="font-size:.78rem;">
              <div id="progress-detail-content" class="d-flex flex-column gap-1"></div>
            </div>
          </div>
          <div id="progress-modal-error" class="alert alert-danger py-2 px-3 mt-2 mb-0 d-none" style="font-size:.8rem;"></div>
        </div>
        <div class="modal-footer border-0 pt-1">
          <a id="progress-modal-download" href="#" class="btn btn-primary btn-sm d-none">
            <i data-feather="download" style="width:13px;height:13px;"></i>
            Download File
          </a>
          <button id="progress-modal-close" type="button" class="btn btn-secondary btn-sm d-none" data-bs-dismiss="modal">Tutup</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modal-confirm-restore" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" id="rc-dialog" style="max-width:480px;">
      <div class="modal-content border border-danger">
        <div class="modal-header border-0 pb-1">
          <h6 class="modal-title fw-bold text-danger d-flex align-items-center gap-2">
            <i data-feather="alert-triangle" style="width:16px;height:16px;"></i>
            <span id="rc-title">Konfirmasi Restore Database</span>
          </h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body py-2">
          <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:.82rem;">
            <strong>PERINGATAN!</strong> <span id="rc-warning">Tindakan ini akan <strong>menghapus semua data yang ada</strong>
            dan menggantinya dengan data dari file backup.</span> Tindakan ini <strong>tidak bisa dibatalkan</strong>.
          </div>
          <div class="d-flex flex-column gap-1" style="font-size:.84rem;">
            <div><span class="text-muted">File :</span> <strong id="rc-filename" class="text-break"></strong></div>
            <div><span class="text-muted">Ukuran :</span> <strong id="rc-filesize"></strong></div>
          </div>

          <div id="rc-full-section" class="mt-3">
            <div class="text-muted mb-1" style="font-size:.8rem;font-weight:600;">
              <i data-feather="file-text" style="width:13px;height:13px;"></i> Isi log.txt (data yang akan direstore)
            </div>
            <div id="rc-log-wrap" class="d-none">
              <pre id="rc-log" class="border rounded bg-light px-2 py-2 mb-0" style="max-height:220px;overflow:auto;font-size:.72rem;white-space:pre-wrap;word-break:break-word;"></pre>
            </div>
            <div id="rc-log-empty" class="alert alert-secondary py-2 px-3 mb-0 d-none" style="font-size:.78rem;">
              File backup ini tidak memuat <code>log.txt</code> (kemungkinan dibuat sebelum fitur log ditambahkan).
            </div>
          </div>

          <div id="rc-table-section" class="mt-3 d-none">
            <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
              <span class="text-muted" style="font-size:.8rem;font-weight:600;">Pilih tabel yang akan ditimpa (<span id="rc-selected-count">0</span> dipilih):</span>
              <label class="d-flex align-items-center gap-1 mb-0" style="font-size:.78rem;cursor:pointer;">
                <input type="checkbox" class="form-check-input mt-0" id="rc-select-all-tables"> Pilih semua
              </label>
            </div>
            <div style="max-height:300px;overflow-y:auto;" class="border rounded">
              <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light sticky-top">
                  <tr>
                    <th style="width:34px;" class="text-center"></th>
                    <th>Nama Tabel</th>
                    <th class="text-end">Record Sekarang</th>
                    <th class="text-end">Record di Backup</th>
                  </tr>
                </thead>
                <tbody id="rc-tables-body"></tbody>
              </table>
            </div>
            <div id="rc-tables-empty" class="alert alert-secondary py-2 px-3 mb-0 mt-2 d-none" style="font-size:.78rem;">
              Backup ini tidak memuat data tabel (kemungkinan backup <strong>structure only</strong>), sehingga restore per tabel tidak tersedia.
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-1">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-danger btn-sm" id="btn-confirm-restore">
            <i data-feather="upload-cloud" style="width:13px;height:13px;"></i>
            Ya, Lakukan Restore
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modal-clear-tables" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
      <div class="modal-content border border-danger">
        <div class="modal-header border-0 pb-1">
          <h6 class="modal-title fw-bold text-danger d-flex align-items-center gap-2">
            <i data-feather="trash-2" style="width:16px;height:16px;"></i>
            Bersihkan Isi Tabel
          </h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body py-2">
          <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:.82rem;">
            <strong>PERINGATAN!</strong> Seluruh <strong>isi/record</strong> dari tabel terpilih akan
            <strong>dihapus permanen</strong> (struktur tabel tetap ada). Tindakan ini <strong>tidak bisa dibatalkan</strong>.
          </div>
          <div class="mb-2" style="font-size:.82rem;">
            <span class="text-muted">Tabel terpilih (<span id="ct-count">0</span>):</span>
            <div id="ct-table-list" class="border rounded px-2 py-1 mt-1 bg-light" style="max-height:140px;overflow-y:auto;font-size:.78rem;"></div>
          </div>
          <label for="ct-confirm-input" class="form-label mb-1" style="font-size:.8rem;">Ketik <strong>CLEAR</strong> untuk konfirmasi:</label>
          <input id="ct-confirm-input" type="text" class="form-control form-control-sm" placeholder="CLEAR" autocomplete="off">
          <div id="ct-error" class="alert alert-danger py-2 px-3 mt-2 mb-0 d-none" style="font-size:.8rem;"></div>
        </div>
        <div class="modal-footer border-0 pt-1">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-danger btn-sm" id="btn-confirm-clear-tables">
            <i data-feather="trash-2" style="width:13px;height:13px;"></i>
            Ya, Bersihkan Isi Tabel
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modal-select-drive-file" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header border-0 pb-1">
          <h6 class="modal-title fw-bold d-flex align-items-center gap-2">
            <i data-feather="folder" style="width:16px;height:16px;"></i>
            Pilih File Backup dari Google Drive
          </h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body py-2">
          <div id="drive-picker-loading" class="text-muted py-3">Memuat file Google Drive...</div>
          <div id="drive-picker-error" class="alert alert-danger py-2 px-3 d-none mb-2"></div>
          <div class="table-responsive d-none" id="drive-picker-table-wrap">
            <table class="table table-sm table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>Nama File</th>
                  <th style="width:110px">Ukuran</th>
                  <th style="width:140px">Tanggal</th>
                  <th style="width:90px" class="text-center">Pilih</th>
                </tr>
              </thead>
              <tbody id="drive-picker-body"></tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer border-0 pt-1">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-refresh-drive-picker">Refresh</button>
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modal-confirm-migration" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
      <div class="modal-content border border-warning">
        <div class="modal-header border-0 pb-1">
          <h6 class="modal-title fw-bold text-warning d-flex align-items-center gap-2">
            <i data-feather="shuffle" style="width:16px;height:16px;"></i>
            Konfirmasi Migration Execution
          </h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body py-2">
          <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:.82rem;">
            <strong>PERINGATAN!</strong> Proses migration dapat mengubah data pada destination server.
            Pastikan source dan destination sudah benar sebelum melanjutkan.
          </div>
          <div class="d-flex flex-column gap-2" style="font-size:.84rem;">
            <div><span class="text-muted">Mode :</span> <strong id="migration-confirm-mode">-</strong></div>
            <div><span class="text-muted">Ketikan :</span> <strong>MIGRATION</strong> untuk melanjutkan.</div>
            <div>
              <label for="migration-confirm-input" class="migration-field-label">Konfirmasi</label>
              <input id="migration-confirm-input" type="text" class="form-control form-control-sm" placeholder="Ketik MIGRATION">
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-1">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-warning btn-sm" id="btn-confirm-migration">
            <i data-feather="play" style="width:13px;height:13px;"></i>
            Lanjutkan Migration
          </button>
        </div>
      </div>
    </div>
  </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<script>
if (window.feather) { feather.replace(); }

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const URL_BACKUP_START   = "{{ route('tools.database.backup.start') }}";
const URL_BACKUP_RUN     = "{{ route('tools.database.backup.run') }}";
const URL_BACKUP_DL      = "{{ url('tools/database/backup/download') }}";
const URL_PROGRESS       = "{{ url('tools/database/progress') }}";
const URL_RESTORE_PREPARE = "{{ route('tools.database.restore.prepare') }}";
const URL_RESTORE_RUN     = "{{ route('tools.database.restore.run') }}";
const URL_CLEAR_TABLES   = "{{ route('tools.database.tables.clear') }}";
const URL_MIGRATION_SOURCE_TEST = "{{ route('tools.database.migration.source.test') }}";
const URL_MIGRATION_START = "{{ route('tools.database.migration.start') }}";
const URL_MIGRATION_RUN = "{{ route('tools.database.migration.run') }}";
const URL_MIGRATION_SWITCH_PREVIEW = "{{ route('tools.database.migration.switch.preview') }}";
const URL_MIGRATION_SWITCH_EXECUTE = "{{ route('tools.database.migration.switch.execute') }}";
const URL_MIGRATION_SWITCH_ROLLBACK_PREVIEW = "{{ route('tools.database.migration.switch.rollback.preview') }}";
const URL_MIGRATION_SWITCH_ROLLBACK_EXECUTE = "{{ route('tools.database.migration.switch.rollback.execute') }}";
const URL_MIGRATION_SOURCE_SAVE_CONFIG = "{{ route('tools.database.migration.source.save-config') }}";
const URL_MIGRATION_SOURCE_LOAD_CONFIG = "{{ route('tools.database.migration.source.load-config') }}";
const URL_MIGRATION_DESTINATION_TEST = "{{ route('tools.database.migration.destination.test') }}";
const URL_MIGRATION_DESTINATION_SAVE_CONFIG = "{{ route('tools.database.migration.destination.save-config') }}";
const URL_MIGRATION_DESTINATION_LOAD_CONFIG = "{{ route('tools.database.migration.destination.load-config') }}";
const URL_MIGRATION_DESTINATION_CLEAR = "{{ route('tools.database.migration.destination.clear') }}";
const URL_GOOGLE_DRIVE   = "{{ route('tools.google-drive.index') }}";
const URL_GOOGLE_DRIVE_FILES = "{{ route('tools.google-drive.files') }}";

let pollTimer   = null;
let bsProgress  = null;
let bsConfirm   = null;
let bsDrivePicker = null;
let bsMigrationConfirm = null;
let bsClearTables = null;
let activeProgressToken = null;
let selectedDriveRestoreFile = null;
let progressStartAt = null;
let progressElapsedTimer = null;
let progressDotsTimer = null;
let progressDots = 0;
let lastMigrationMeta = {};

// ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ Init modals ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
document.addEventListener('DOMContentLoaded', () => {
  bsProgress = new bootstrap.Modal(document.getElementById('modal-progress'), { backdrop: 'static', keyboard: false });
  bsConfirm  = new bootstrap.Modal(document.getElementById('modal-confirm-restore'));
  bsDrivePicker = new bootstrap.Modal(document.getElementById('modal-select-drive-file'));
  bsMigrationConfirm = new bootstrap.Modal(document.getElementById('modal-confirm-migration'));
  bsClearTables = new bootstrap.Modal(document.getElementById('modal-clear-tables'));

  initClearTables();

  syncRestoreSourceUI();

  document.querySelectorAll('input[name="restore-source-type"]').forEach(input => {
    input.addEventListener('change', syncRestoreSourceUI);
  });

  // Restore file input: enable button when file chosen
  document.getElementById('restore-file-input').addEventListener('change', function() {
    document.getElementById('btn-restore').disabled = !this.files.length;
  });

  document.getElementById('btn-select-drive-file').addEventListener('click', () => {
    bsDrivePicker.show();
    loadGoogleDriveBackupFiles();
  });

  document.getElementById('btn-refresh-drive-picker').addEventListener('click', loadGoogleDriveBackupFiles);

  const testSourceButton = document.getElementById('btn-test-source');
  if (testSourceButton) {
    testSourceButton.addEventListener('click', testSourceConnection);
  }

  const saveSourceConfigButton = document.getElementById('btn-save-source-config');
  if (saveSourceConfigButton) {
    saveSourceConfigButton.addEventListener('click', saveSourceConfig);
  }

  const loadSourceConfigButton = document.getElementById('btn-load-source-config');
  if (loadSourceConfigButton) {
    loadSourceConfigButton.addEventListener('click', loadSourceConfig);
  }

  const testDestinationButton = document.getElementById('btn-test-destination');
  if (testDestinationButton) {
    testDestinationButton.addEventListener('click', testDestinationConnection);
  }

  const saveDestinationConfigButton = document.getElementById('btn-save-destination-config');
  if (saveDestinationConfigButton) {
    saveDestinationConfigButton.addEventListener('click', saveDestinationConfig);
  }

  const loadDestinationConfigButton = document.getElementById('btn-load-destination-config');
  if (loadDestinationConfigButton) {
    loadDestinationConfigButton.addEventListener('click', loadDestinationConfig);
  }

  const startMigrationButton = document.getElementById('btn-start-migration');
  if (startMigrationButton) {
    startMigrationButton.addEventListener('click', openMigrationConfirmModal);
  }

  const switchServerButton = document.getElementById('btn-switch-server');
  if (switchServerButton) {
    switchServerButton.addEventListener('click', runSwitchServer);
  }

  const rollbackServerButton = document.getElementById('btn-rollback-server');
  if (rollbackServerButton) {
    rollbackServerButton.addEventListener('click', runRollbackSwitchServer);
  }

  const confirmMigrationButton = document.getElementById('btn-confirm-migration');
  if (confirmMigrationButton) {
    confirmMigrationButton.addEventListener('click', runMigrationExecution);
  }

  // Restore dari Google Drive ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ siapkan & periksa log dulu
  document.getElementById('btn-restore-drive').addEventListener('click', prepareRestoreCloud);

  // Backup button
  document.getElementById('btn-backup').addEventListener('click', startBackup);

  // Restore lokal ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ upload, periksa log, lalu tampilkan konfirmasi
  document.getElementById('btn-restore').addEventListener('click', prepareRestoreLocal);

  // Confirm restore button ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ jalankan restore yang sudah disiapkan
  document.getElementById('btn-confirm-restore').addEventListener('click', function() {
    runPreparedRestore();
  });
});

// ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ CLEAR TABLES ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
function initClearTables() {
  const selectAll = document.getElementById('clear-tables-select-all');
  const checks = document.querySelectorAll('.tbl-check');
  const clearBtn = document.getElementById('btn-clear-tables');

  if (!clearBtn) return;

  checks.forEach(cb => cb.addEventListener('change', updateClearTablesState));

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('.tbl-check').forEach(cb => { cb.checked = this.checked; });
      updateClearTablesState();
    });
  }

  clearBtn.addEventListener('click', openClearTablesModal);

  const confirmBtn = document.getElementById('btn-confirm-clear-tables');
  if (confirmBtn) confirmBtn.addEventListener('click', submitClearTables);

  updateClearTablesState();
}

function getSelectedClearTables() {
  return Array.from(document.querySelectorAll('.tbl-check:checked')).map(cb => cb.value);
}

function updateClearTablesState() {
  const selected = getSelectedClearTables();
  const clearBtn = document.getElementById('btn-clear-tables');
  const countEl = document.getElementById('clear-tables-count');
  const selectAll = document.getElementById('clear-tables-select-all');
  const total = document.querySelectorAll('.tbl-check').length;

  if (countEl) countEl.textContent = selected.length;
  if (clearBtn) clearBtn.disabled = selected.length === 0;

  if (selectAll) {
    selectAll.checked = total > 0 && selected.length === total;
    selectAll.indeterminate = selected.length > 0 && selected.length < total;
  }
}

function openClearTablesModal() {
  const selected = getSelectedClearTables();
  if (!selected.length) return;

  document.getElementById('ct-count').textContent = selected.length;
  document.getElementById('ct-table-list').innerHTML = selected
    .map(name => '<div><code style="font-size:.78rem;">' + escapeHtml(name) + '</code></div>')
    .join('');

  const input = document.getElementById('ct-confirm-input');
  input.value = '';
  const errEl = document.getElementById('ct-error');
  errEl.classList.add('d-none');
  errEl.textContent = '';

  bsClearTables.show();
  setTimeout(() => input.focus(), 150);
}

async function submitClearTables() {
  const selected = getSelectedClearTables();
  const input = document.getElementById('ct-confirm-input');
  const errEl = document.getElementById('ct-error');
  const confirmBtn = document.getElementById('btn-confirm-clear-tables');

  if (!selected.length) return;

  if ((input.value || '').trim() !== 'CLEAR') {
    errEl.textContent = 'Konfirmasi tidak valid. Ketik CLEAR (huruf besar) untuk melanjutkan.';
    errEl.classList.remove('d-none');
    input.focus();
    return;
  }

  errEl.classList.add('d-none');
  const originalHtml = confirmBtn.innerHTML;
  confirmBtn.disabled = true;
  confirmBtn.innerHTML = 'Membersihkan...';

  try {
    const res = await fetch(URL_CLEAR_TABLES, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ tables: selected })
    });
    const data = await res.json();

    if (!res.ok || data.success === false || data.error) {
      errEl.textContent = data.error || 'Gagal membersihkan tabel.';
      errEl.classList.remove('d-none');
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = originalHtml;
      if (window.feather) { feather.replace(); }
      return;
    }

    confirmBtn.innerHTML = 'Berhasil! Memuat ulang...';
    setTimeout(() => window.location.reload(), 1200);
  } catch (e) {
    errEl.textContent = 'Tidak dapat menghubungi server.';
    errEl.classList.remove('d-none');
    confirmBtn.disabled = false;
    confirmBtn.innerHTML = originalHtml;
    if (window.feather) { feather.replace(); }
  }
}

// ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ BACKUP ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
async function startBackup() {
  const storageType = getSelectedBackupStorageType();
  const backupScope = getSelectedBackupScope();
  openProgressModal('Backup Database', 'download-cloud', '#0d6efd');

  // Tampilkan box detail sejak awal dengan informasi yang sudah diketahui di klien,
  // lalu diperkaya/diperbarui oleh polling progress.
  renderProgressMeta({
    operation: 'backup',
    stage_label: 'Menyiapkan proses',
    storage_label: storageType === 'cloud' ? 'Google Drive' : 'Local Storage',
    backup_scope_label: backupScope === 'structure' ? 'Database Structure' : 'Full Backup',
  });

  try {
    const res  = await fetch(URL_BACKUP_START, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        storage_type: storageType,
        backup_scope: backupScope
      })
    });
    const data = await res.json();
    if (data.error) { showProgressError(data.error); return; }

    const isStructure = (data.backup_scope || backupScope) === 'structure';
    const isCloud = (data.storage_type || storageType) === 'cloud';

    // Jalankan proses backup pada request terpisah agar progress tetap realtime
    // dan tidak bergantung pada worker queue yang berjalan.
    fetch(URL_BACKUP_RUN, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        token: data.token,
        storage_type: storageType,
        backup_scope: backupScope
      })
    })
    .then(async r => ({ ok: r.ok, body: await r.json().catch(() => ({})) }))
    .then(({ ok, body }) => {
      if (!ok || body.success === false || body.error) {
        showProgressError(body.error || 'Gagal menjalankan backup.');
      }
    })
    .catch(() => {
      showProgressError('Tidak dapat menghubungi server saat menjalankan backup.');
    });

    pollProgress(data.token, () => {
      if (isCloud) {
        setProgressDone(isStructure
          ? 'Backup struktur selesai! File berhasil disimpan ke Google Drive.'
          : 'Backup selesai! File berhasil disimpan ke Google Drive.');
        return;
      }

      setProgressDone(isStructure
        ? 'Backup struktur selesai! File siap diunduh.'
        : 'Backup selesai! File siap diunduh.');
    });
  } catch (err) {
    showProgressError(err.message);
  }
}

// ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ RESTORE ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
let preparedRestore = null;

function resetRestoreButtons() {
  const restoreInput = document.getElementById('restore-file-input');
  const restoreButton = document.getElementById('btn-restore');
  if (restoreInput) restoreInput.disabled = false;
  if (restoreButton) restoreButton.disabled = !(restoreInput && restoreInput.files.length);

  const pickButton = document.getElementById('btn-select-drive-file');
  const restoreDriveButton = document.getElementById('btn-restore-drive');
  if (pickButton) pickButton.disabled = false;
  if (restoreDriveButton) restoreDriveButton.disabled = !selectedDriveRestoreFile;
}

function showRestoreConfirm(data) {
  const mode = data.restore_mode || 'full';
  preparedRestore = { token: data.token, source_type: data.source_type, file_name: data.file_name, restore_mode: mode };

  document.getElementById('rc-filename').textContent = data.file_name || '-';
  document.getElementById('rc-filesize').textContent = data.file_size_human || formatBytes(data.file_size || 0);

  const fullSection  = document.getElementById('rc-full-section');
  const tableSection = document.getElementById('rc-table-section');
  const dialog       = document.getElementById('rc-dialog');
  const titleEl      = document.getElementById('rc-title');
  const warningEl    = document.getElementById('rc-warning');
  const confirmBtn   = document.getElementById('btn-confirm-restore');

  if (mode === 'table') {
    titleEl.textContent = 'Konfirmasi Restore per Tabel';
    warningEl.innerHTML = 'Tindakan ini akan <strong>menghapus isi tabel yang dipilih</strong> dan menggantinya dengan data dari file backup.';
    fullSection.classList.add('d-none');
    tableSection.classList.remove('d-none');
    dialog.style.maxWidth = '720px';
    renderRestoreTableComparison(data.tables || []);
  } else {
    titleEl.textContent = 'Konfirmasi Restore Database';
    warningEl.innerHTML = 'Tindakan ini akan <strong>menghapus semua data yang ada</strong> dan menggantinya dengan data dari file backup.';
    tableSection.classList.add('d-none');
    fullSection.classList.remove('d-none');
    dialog.style.maxWidth = '480px';
    confirmBtn.disabled = false;

    const logWrap = document.getElementById('rc-log-wrap');
    const logEl = document.getElementById('rc-log');
    const logEmpty = document.getElementById('rc-log-empty');

    if (data.log && String(data.log).trim() !== '') {
      logEl.textContent = data.log;
      logWrap.classList.remove('d-none');
      logEmpty.classList.add('d-none');
    } else {
      logEl.textContent = '';
      logWrap.classList.add('d-none');
      logEmpty.classList.remove('d-none');
    }
  }

  if (window.feather) { feather.replace(); }
  bsConfirm.show();
}

function renderRestoreTableComparison(tables) {
  const body       = document.getElementById('rc-tables-body');
  const emptyEl    = document.getElementById('rc-tables-empty');
  const selectAll  = document.getElementById('rc-select-all-tables');
  const confirmBtn = document.getElementById('btn-confirm-restore');

  body.innerHTML = '';

  if (!tables.length) {
    emptyEl.classList.remove('d-none');
    selectAll.checked = false;
    selectAll.disabled = true;
    confirmBtn.disabled = true;
    document.getElementById('rc-selected-count').textContent = '0';
    return;
  }

  emptyEl.classList.add('d-none');
  selectAll.disabled = false;
  selectAll.checked = false;

  tables.forEach((t, i) => {
    const cur = (t.current_records === null || t.current_records === undefined)
      ? '<span class="text-warning" title="Tabel tidak ada di database saat ini">tidak ada</span>'
      : Number(t.current_records).toLocaleString('id-ID');
    const bak = Number(t.backup_records || 0).toLocaleString('id-ID');

    const tr = document.createElement('tr');
    tr.innerHTML =
      '<td class="text-center">' +
        '<input type="checkbox" class="form-check-input rc-tbl-check" value="' + escapeHtml(t.name) + '" id="rc-tbl-' + i + '">' +
      '</td>' +
      '<td><label class="mb-0" for="rc-tbl-' + i + '" style="cursor:pointer;font-family:monospace;font-size:.8rem;">' + escapeHtml(t.name) + '</label></td>' +
      '<td class="text-end" style="font-size:.8rem;">' + cur + '</td>' +
      '<td class="text-end fw-semibold" style="font-size:.8rem;">' + bak + '</td>';
    body.appendChild(tr);
  });

  body.querySelectorAll('.rc-tbl-check').forEach(cb => {
    cb.addEventListener('change', updateRestoreTableSelection);
  });
  selectAll.onchange = function() {
    body.querySelectorAll('.rc-tbl-check').forEach(cb => { cb.checked = selectAll.checked; });
    updateRestoreTableSelection();
  };

  updateRestoreTableSelection();
}

function updateRestoreTableSelection() {
  const checks = Array.from(document.querySelectorAll('#rc-tables-body .rc-tbl-check'));
  const selected = checks.filter(cb => cb.checked);
  document.getElementById('rc-selected-count').textContent = String(selected.length);
  document.getElementById('btn-confirm-restore').disabled = selected.length === 0;

  const selectAll = document.getElementById('rc-select-all-tables');
  selectAll.checked = checks.length > 0 && selected.length === checks.length;
  selectAll.indeterminate = selected.length > 0 && selected.length < checks.length;
}

function getSelectedRestoreTables() {
  return Array.from(document.querySelectorAll('#rc-tables-body .rc-tbl-check:checked')).map(cb => cb.value);
}

// Local: upload + periksa arsip (baca log.txt) sebelum konfirmasi restore.
function prepareRestoreLocal() {
  const file = document.getElementById('restore-file-input').files[0];
  if (!file) return;

  const restoreButton = document.getElementById('btn-restore');
  const restoreInput = document.getElementById('restore-file-input');
  restoreButton.disabled = true;
  restoreInput.disabled = true;

  openProgressModal('Menyiapkan Restore', 'search', '#dc3545');
  updateProgress(2, 'Mengupload & memeriksa file backup...');

  const formData = new FormData();
  formData.append('backup_file', file);
  formData.append('source_type', 'local');
  formData.append('restore_mode', getSelectedRestoreMode());
  formData.append('_token', CSRF);

  const xhr = new XMLHttpRequest();

  xhr.upload.addEventListener('progress', (e) => {
    if (e.lengthComputable) {
      const pct = Math.min(Math.round(e.loaded / e.total * 90), 90);
      updateProgress(pct, 'Mengupload file (' + Math.round(e.loaded / e.total * 100) + '%)...');
    }
  });

  xhr.addEventListener('load', function() {
    try {
      const data = JSON.parse(this.responseText);
      if (data.error) {
        resetRestoreButtons();
        showProgressError(data.error);
        return;
      }

      updateProgress(100, 'File backup valid. Menampilkan detail...');
      bsProgress.hide();
      stopProgressTimers();
      showRestoreConfirm(data);
    } catch (e) {
      resetRestoreButtons();
      showProgressError('Response tidak valid dari server.');
    }
  });

  xhr.addEventListener('error', () => {
    resetRestoreButtons();
    showProgressError('Gagal menghubungi server.');
  });

  xhr.open('POST', URL_RESTORE_PREPARE);
  xhr.send(formData);
}

// Cloud: ambil file dari Google Drive + periksa arsip (baca log.txt) sebelum konfirmasi.
function prepareRestoreCloud() {
  if (!selectedDriveRestoreFile) return;

  const restoreButton = document.getElementById('btn-restore-drive');
  const pickButton = document.getElementById('btn-select-drive-file');
  restoreButton.disabled = true;
  pickButton.disabled = true;

  openProgressModal('Menyiapkan Restore', 'search', '#dc3545');
  updateProgress(10, 'Mengambil file dari Google Drive & memeriksa isinya...');

  fetch(URL_RESTORE_PREPARE, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': CSRF,
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      source_type: 'cloud',
      restore_mode: getSelectedRestoreMode(),
      drive_file_id: selectedDriveRestoreFile.file_id,
      drive_file_name: selectedDriveRestoreFile.filename,
      drive_file_size: selectedDriveRestoreFile.size || 0
    })
  })
  .then(async res => ({ ok: res.ok, body: await res.json() }))
  .then(({ ok, body }) => {
    if (!ok || body.error) {
      resetRestoreButtons();
      showProgressError(body.error || 'Gagal menyiapkan restore.');
      return;
    }

    updateProgress(100, 'File backup valid. Menampilkan detail...');
    bsProgress.hide();
    stopProgressTimers();
    showRestoreConfirm(body);
  })
  .catch(() => {
    resetRestoreButtons();
    showProgressError('Gagal menghubungi server.');
  });
}

// Jalankan restore yang sudah disiapkan (sinkron + polling, tanpa worker queue).
function runPreparedRestore() {
  if (!preparedRestore || !preparedRestore.token) return;

  const mode = preparedRestore.restore_mode || 'full';
  let selectedTables = [];
  if (mode === 'table') {
    selectedTables = getSelectedRestoreTables();
    if (!selectedTables.length) {
      alert('Pilih minimal satu tabel untuk direstore.');
      return;
    }
  }

  bsConfirm.hide();
  openProgressModal(mode === 'table' ? 'Restore Tabel' : 'Restore Database', 'upload-cloud', '#dc3545');
  updateProgress(8, 'Memulai restore...');

  fetch(URL_RESTORE_RUN, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': CSRF,
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ token: preparedRestore.token, restore_mode: mode, tables: selectedTables })
  })
  .then(async res => ({ ok: res.ok, body: await res.json().catch(() => ({})) }))
  .then(({ ok, body }) => {
    if (!ok || body.success === false || body.error) {
      showProgressError(body.error || 'Gagal menjalankan restore.');
    }
  })
  .catch(() => {
    showProgressError('Tidak dapat menghubungi server saat menjalankan restore.');
  });

  pollProgress(preparedRestore.token, () => {
    setProgressDone('Restore berhasil! Memuat ulang halaman...');
    setTimeout(() => window.location.reload(), 2500);
  });
}

// ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ POLLING ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
function pollProgress(token, onComplete) {
  if (pollTimer) clearInterval(pollTimer);
  activeProgressToken = token;
  pollTimer = setInterval(async () => {
    try {
      const res  = await fetch(URL_PROGRESS + '/' + token, { headers: { 'Accept': 'application/json' } });
      const data = await res.json();

      if (data.error && data.error !== null) {
        clearInterval(pollTimer);
        showProgressError(data.error);
        return;
      }

      const mergedMeta = mergeProgressMeta(data.meta || {});
      updateProgress(Math.max(data.progress, 0), data.message);
      renderProgressMeta(mergedMeta);
      toggleProgressDownload(mergedMeta, token);

      if (data.progress >= 100) {
        clearInterval(pollTimer);
        onComplete();
      }
    } catch (e) { /* ignore transient network errors */ }
  }, 500);
}

// ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ MODAL HELPERS ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
function openProgressModal(title, icon, color) {
  if (pollTimer) clearInterval(pollTimer);
  if (progressElapsedTimer) clearInterval(progressElapsedTimer);
  if (progressDotsTimer) clearInterval(progressDotsTimer);
  activeProgressToken = null;
  progressStartAt = Date.now();
  progressDots = 0;
  lastMigrationMeta = {};
  document.getElementById('progress-modal-title').textContent = title;
  const ico = document.getElementById('progress-modal-icon');
  ico.setAttribute('data-feather', icon);
  ico.style.color = color;
  if (window.feather) { feather.replace(); }

  updateProgress(0, 'Memulai...');
  const liveStatus = document.getElementById('progress-live-status');
  if (liveStatus) {
    delete liveStatus.dataset.fixed;
    liveStatus.textContent = 'Menyiapkan proses...';
  }
  setProgressLiveStatus('Menyiapkan proses...');
  setProgressElapsed('00:00');
  renderProgressMeta({});
  toggleProgressDownload({}, null);
  document.getElementById('progress-modal-close').classList.add('d-none');
  document.getElementById('progress-modal-error').classList.add('d-none');

  const bar = document.getElementById('progress-bar');
  bar.classList.remove('bg-danger', 'bg-success');
  bar.classList.add('progress-bar-striped', 'progress-bar-animated');

  progressElapsedTimer = setInterval(() => {
    if (!progressStartAt) return;
    setProgressElapsed(formatDurationMs(Date.now() - progressStartAt));
  }, 1000);

  progressDotsTimer = setInterval(() => {
    progressDots = (progressDots + 1) % 4;
    const liveStatus = document.getElementById('progress-live-status');
    if (liveStatus && !liveStatus.dataset.fixed) {
      liveStatus.textContent = 'Proses masih berjalan' + '.'.repeat(progressDots);
    }
  }, 500);

  bsProgress.show();
}

function updateProgress(pct, msg) {
  const bar = document.getElementById('progress-bar');
  const p   = Math.max(0, Math.min(100, pct));
  bar.style.width         = p + '%';
  bar.setAttribute('aria-valuenow', p);
  bar.textContent         = p + '%';
  document.getElementById('progress-message').textContent = msg || '';

  const liveStatus = document.getElementById('progress-live-status');
  if (liveStatus && !liveStatus.dataset.fixed) {
    liveStatus.textContent = p <= 1
      ? 'Menunggu antrian diproses worker...'
      : 'Proses berjalan...';
  }
}

function setProgressMessage(msg) {
  document.getElementById('progress-message').textContent = msg;
}

function setProgressDone(msg) {
  updateProgress(100, msg);
  const bar = document.getElementById('progress-bar');
  bar.classList.remove('progress-bar-animated', 'bg-danger');
  bar.classList.add('bg-success');
  document.getElementById('progress-modal-close').classList.remove('d-none');
  setProgressLiveStatus('Proses selesai');
  stopProgressTimers();
}

function stopProgressTimers() {
  if (progressElapsedTimer) clearInterval(progressElapsedTimer);
  if (progressDotsTimer) clearInterval(progressDotsTimer);
  progressElapsedTimer = null;
  progressDotsTimer = null;
}

function setProgressLiveStatus(text) {
  const el = document.getElementById('progress-live-status');
  if (!el) return;
  el.textContent = text;
  el.dataset.fixed = '1';
}

function setProgressElapsed(text) {
  const el = document.getElementById('progress-elapsed');
  if (!el) return;
  el.textContent = text;
}

function formatDurationMs(ms) {
  const totalSeconds = Math.max(0, Math.floor(ms / 1000));
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
}

function openMigrationConfirmModal() {
  const mode = getSelectedMigrationMode();
  document.getElementById('migration-confirm-mode').textContent = mode === 'structure'
    ? 'DB Structure Only'
    : 'Full Migration (Structure + data)';
  const input = document.getElementById('migration-confirm-input');
  input.value = '';
  document.getElementById('migration-execution-status').classList.add('d-none');
  bsMigrationConfirm.show();
  setTimeout(() => input.focus(), 150);
}

function getSelectedMigrationMode() {
  return document.getElementById('migration-mode')?.value || 'full';
}

function getSelectedMigrationModeLabel() {
  return getSelectedMigrationMode() === 'structure'
    ? 'DB Structure Only'
    : 'Full Migration (Structure + data)';
}

function setMigrationExecutionStatus(type, message) {
  const el = document.getElementById('migration-execution-status');
  if (!el) return;

  el.classList.remove('d-none', 'alert-info', 'alert-success', 'alert-warning', 'alert-danger');
  el.classList.add(type === 'success' ? 'alert-success' : type === 'warning' ? 'alert-warning' : type === 'danger' ? 'alert-danger' : 'alert-info');
  el.textContent = message;
}

function runMigrationExecution() {
  const input = document.getElementById('migration-confirm-input');
  if (!input) return;

  if (input.value.trim() !== 'MIGRATION') {
    setMigrationExecutionStatus('warning', 'Konfirmasi tidak valid. Ketik MIGRATION untuk melanjutkan.');
    input.focus();
    return;
  }

  const mode = getSelectedMigrationMode();
  const modeLabel = getSelectedMigrationModeLabel();
  const source = collectSourceConfigPayload();
  const destination = collectDestinationConfigPayload();

  if (!source.host || !source.database || !source.username || !destination.host || !destination.database || !destination.username) {
    setMigrationExecutionStatus('warning', 'Lengkapi konfigurasi source dan destination sebelum migration.');
    return;
  }

  bsMigrationConfirm.hide();
  setMigrationExecutionStatus('info', 'Migration dimulai di server tujuan. Menyiapkan progress...');
  openProgressModal('Migration Execution', 'shuffle', '#ffc107');
  updateProgress(2, 'Menyiapkan migration ' + modeLabel + '...');

  fetch(URL_MIGRATION_START, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': CSRF,
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      mode,
      confirmation: 'MIGRATION',
      source,
      destination
    })
  })
  .then(async res => ({ ok: res.ok, body: await res.json() }))
  .then(({ ok, body }) => {
    if (!ok || body.success === false || body.error) {
      showProgressError(body.error || 'Gagal memulai migration.');
      setMigrationExecutionStatus('danger', body.error || 'Gagal memulai migration.');
      return;
    }

    setMigrationExecutionStatus('success', 'Migration dimulai di server tujuan.');

    // Jalankan proses migration pada request terpisah agar polling progress tetap realtime.
    fetch(URL_MIGRATION_RUN, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        token: body.token,
        mode,
        confirmation: 'MIGRATION',
        source,
        destination
      })
    })
    .then(async res => ({ ok: res.ok, body: await res.json().catch(() => ({})) }))
    .then(({ ok, body: runBody }) => {
      if (!ok || runBody.success === false || runBody.error) {
        const msg = runBody.error || 'Gagal menjalankan migration.';
        showProgressError(msg);
        setMigrationExecutionStatus('danger', msg);
      }
    })
    .catch(() => {
      showProgressError('Tidak dapat menghubungi server saat menjalankan migration.');
      setMigrationExecutionStatus('danger', 'Tidak dapat menghubungi server saat menjalankan migration.');
    });

    pollProgress(body.token, () => {
      setProgressDone('Migration selesai! Memuat ulang halaman...');
      setTimeout(() => window.location.reload(), 2500);
    });
  })
  .catch(() => {
    showProgressError('Tidak dapat menghubungi server saat memulai migration.');
    setMigrationExecutionStatus('danger', 'Tidak dapat menghubungi server saat memulai migration.');
  });
}

async function runSwitchServer() {
  const switchButton = document.getElementById('btn-switch-server');
  if (!switchButton) return;

  const originalLabel = switchButton.textContent;
  switchButton.disabled = true;
  switchButton.textContent = 'Menyiapkan...';
  setMigrationExecutionStatus('info', 'Mengambil preview switch server...');

  try {
    const previewRes = await fetch(URL_MIGRATION_SWITCH_PREVIEW, {
      headers: { 'Accept': 'application/json' }
    });
    const previewBody = await previewRes.json();

    if (!previewRes.ok || previewBody.success === false) {
      setMigrationExecutionStatus('danger', previewBody.error || 'Gagal mengambil preview switch server.');
      return;
    }

    const currentDb = previewBody.preview?.current?.db_url || '-';
    const destinationDb = previewBody.preview?.destination?.db_url || '-';
    const typed = window.prompt(
      'Switch server akan mengubah DB_URL aktif ke destination.\n\nCurrent DB_URL:\n'
      + currentDb
      + '\n\nDestination DB_URL:\n'
      + destinationDb
      + '\n\nKetik SWITCH untuk konfirmasi.',
      ''
    );

    if (typed === null) {
      setMigrationExecutionStatus('warning', 'Switch dibatalkan user.');
      return;
    }
    if (typed.trim() !== 'SWITCH') {
      setMigrationExecutionStatus('warning', 'Konfirmasi tidak valid. Ketik SWITCH secara tepat.');
      return;
    }

    switchButton.textContent = 'Switching...';

    const execRes = await fetch(URL_MIGRATION_SWITCH_EXECUTE, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ confirmation: 'SWITCH' })
    });
    const execBody = await execRes.json();

    if (!execRes.ok || execBody.success === false) {
      setMigrationExecutionStatus('danger', execBody.error || 'Switch server gagal.');
      return;
    }

    setMigrationExecutionStatus('success', (execBody.message || 'Switch server berhasil.') + ' Jalankan php artisan config:clear.');
  } catch (error) {
    setMigrationExecutionStatus('danger', 'Gagal menghubungi server saat switch.');
  } finally {
    switchButton.disabled = false;
    switchButton.textContent = originalLabel;
  }
}

async function runRollbackSwitchServer() {
  const rollbackButton = document.getElementById('btn-rollback-server');
  if (!rollbackButton) return;

  const originalLabel = rollbackButton.textContent;
  rollbackButton.disabled = true;
  rollbackButton.textContent = 'Menyiapkan...';
  setMigrationExecutionStatus('info', 'Mengambil preview rollback switch...');

  try {
    const previewRes = await fetch(URL_MIGRATION_SWITCH_ROLLBACK_PREVIEW, {
      headers: { 'Accept': 'application/json' }
    });
    const previewBody = await previewRes.json();

    if (!previewRes.ok || previewBody.success === false) {
      setMigrationExecutionStatus('danger', previewBody.error || 'Gagal mengambil preview rollback switch.');
      return;
    }

    const currentDb = previewBody.preview?.current?.db_url || '-';
    const backupDb = previewBody.preview?.backup?.db_url || '-';
    const typed = window.prompt(
      'Rollback akan mengembalikan DB_URL aktif dari backup switch.\n\nCurrent DB_URL:\n'
      + currentDb
      + '\n\nBackup DB_URL:\n'
      + backupDb
      + '\n\nKetik ROLLBACK untuk konfirmasi.',
      ''
    );

    if (typed === null) {
      setMigrationExecutionStatus('warning', 'Rollback switch dibatalkan user.');
      return;
    }
    if (typed.trim() !== 'ROLLBACK') {
      setMigrationExecutionStatus('warning', 'Konfirmasi tidak valid. Ketik ROLLBACK secara tepat.');
      return;
    }

    rollbackButton.textContent = 'Rollback...';

    const execRes = await fetch(URL_MIGRATION_SWITCH_ROLLBACK_EXECUTE, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ confirmation: 'ROLLBACK' })
    });
    const execBody = await execRes.json();

    if (!execRes.ok || execBody.success === false) {
      setMigrationExecutionStatus('danger', execBody.error || 'Rollback switch gagal.');
      return;
    }

    setMigrationExecutionStatus('success', (execBody.message || 'Rollback switch berhasil.') + ' Jalankan php artisan config:clear.');
  } catch (error) {
    setMigrationExecutionStatus('danger', 'Gagal menghubungi server saat rollback switch.');
  } finally {
    rollbackButton.disabled = false;
    rollbackButton.textContent = originalLabel;
  }
}

function showProgressError(msg) {
  const errEl = document.getElementById('progress-modal-error');
  errEl.textContent = 'ÃƒÂ¢Ã…Â¡Ã‚Â  ' + msg;
  errEl.classList.remove('d-none');
  toggleProgressDownload({}, null);
  document.getElementById('progress-modal-close').classList.remove('d-none');
  const bar = document.getElementById('progress-bar');
  bar.classList.remove('progress-bar-animated', 'bg-success');
  bar.classList.add('bg-danger');
  setProgressLiveStatus('Terjadi kendala');
  stopProgressTimers();
}

function collectSourceConfigPayload() {
  const port = (document.getElementById('migration-source-port')?.value || '').trim();

  return {
    driver: document.getElementById('migration-source-driver')?.value || 'mysql',
    host: (document.getElementById('migration-source-host')?.value || '').trim(),
    port: port === '' ? null : Number(port),
    database: (document.getElementById('migration-source-database')?.value || '').trim(),
    username: (document.getElementById('migration-source-username')?.value || '').trim(),
    password: document.getElementById('migration-source-password')?.value || ''
  };
}

function collectDestinationConfigPayload() {
  const port = (document.getElementById('migration-destination-port')?.value || '').trim();

  return {
    driver: document.getElementById('migration-destination-driver')?.value || 'mysql',
    host: (document.getElementById('migration-destination-host')?.value || '').trim(),
    port: port === '' ? null : Number(port),
    database: (document.getElementById('migration-destination-database')?.value || '').trim(),
    username: (document.getElementById('migration-destination-username')?.value || '').trim(),
    password: document.getElementById('migration-destination-password')?.value || ''
  };
}

async function saveSourceConfig() {
  const button = document.getElementById('btn-save-source-config');
  const originalLabel = button.textContent;
  button.disabled = true;
  button.textContent = 'Saving...';

  renderSourceTestResult('info', 'Menyimpan konfigurasi Source Server ke .env...');

  try {
    const response = await fetch(URL_MIGRATION_SOURCE_SAVE_CONFIG, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(collectSourceConfigPayload())
    });

    const data = await response.json();
    if (!response.ok || data.success === false) {
      renderSourceTestResult('danger', data.error || 'Gagal menyimpan konfigurasi Source Server.');
      return;
    }

    renderSourceTestResult('success', escapeHtml(data.message || 'Konfigurasi Source Server berhasil disimpan.'));
  } catch (error) {
    renderSourceTestResult('danger', 'Tidak dapat menghubungi server saat menyimpan konfigurasi source.');
  } finally {
    button.disabled = false;
    button.textContent = originalLabel;
  }
}

async function loadSourceConfig() {
  const button = document.getElementById('btn-load-source-config');
  const originalLabel = button.textContent;
  button.disabled = true;
  button.textContent = 'Loading...';

  renderSourceTestResult('info', 'Memuat konfigurasi dari database yang aktif sekarang...');

  try {
    const response = await fetch(URL_MIGRATION_SOURCE_LOAD_CONFIG, {
      headers: {
        'Accept': 'application/json'
      }
    });

    const data = await response.json();
    if (!response.ok || data.success === false || !data.config) {
      renderSourceTestResult('danger', data.error || 'Gagal memuat konfigurasi Source Server.');
      return;
    }

    applySourceConfigToForm(data.config);
    renderSourceTestResult('success', 'Konfigurasi Source Server dimuat dari database aktif sekarang.');
  } catch (error) {
    renderSourceTestResult('danger', 'Tidak dapat menghubungi server saat memuat konfigurasi source.');
  } finally {
    button.disabled = false;
    button.textContent = originalLabel;
  }
}

function applySourceConfigToForm(config) {
  if (!config || typeof config !== 'object') return;

  document.getElementById('migration-source-driver').value = config.driver || 'mysql';
  document.getElementById('migration-source-host').value = config.host || '';
  document.getElementById('migration-source-port').value = config.port || '';
  document.getElementById('migration-source-database').value = config.database || '';
  document.getElementById('migration-source-username').value = config.username || '';
  document.getElementById('migration-source-password').value = config.password || '';
}

async function saveDestinationConfig() {
  const button = document.getElementById('btn-save-destination-config');
  const originalLabel = button.textContent;
  button.disabled = true;
  button.textContent = 'Saving...';

  renderDestinationTestResult('info', 'Menyimpan konfigurasi Destination Server ke .env...');

  try {
    const response = await fetch(URL_MIGRATION_DESTINATION_SAVE_CONFIG, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(collectDestinationConfigPayload())
    });

    const data = await response.json();
    if (!response.ok || data.success === false) {
      renderDestinationTestResult('danger', data.error || 'Gagal menyimpan konfigurasi Destination Server.');
      return;
    }

    renderDestinationTestResult('success', escapeHtml(data.message || 'Konfigurasi Destination Server berhasil disimpan.'));
  } catch (error) {
    renderDestinationTestResult('danger', 'Tidak dapat menghubungi server saat menyimpan konfigurasi destination.');
  } finally {
    button.disabled = false;
    button.textContent = originalLabel;
  }
}

async function loadDestinationConfig() {
  const button = document.getElementById('btn-load-destination-config');
  const originalLabel = button.textContent;
  button.disabled = true;
  button.textContent = 'Loading...';

  renderDestinationTestResult('info', 'Memuat konfigurasi Destination Server dari .env...');

  try {
    const response = await fetch(URL_MIGRATION_DESTINATION_LOAD_CONFIG, {
      headers: {
        'Accept': 'application/json'
      }
    });

    const data = await response.json();
    if (!response.ok || data.success === false || !data.config) {
      renderDestinationTestResult('danger', data.error || 'Gagal memuat konfigurasi Destination Server.');
      return;
    }

    applyDestinationConfigToForm(data.config);
    renderDestinationTestResult('success', 'Konfigurasi Destination Server berhasil dimuat dari .env.');
  } catch (error) {
    renderDestinationTestResult('danger', 'Tidak dapat menghubungi server saat memuat konfigurasi destination.');
  } finally {
    button.disabled = false;
    button.textContent = originalLabel;
  }
}

function applyDestinationConfigToForm(config) {
  if (!config || typeof config !== 'object') return;

  document.getElementById('migration-destination-driver').value = config.driver || 'mysql';
  document.getElementById('migration-destination-host').value = config.host || '';
  document.getElementById('migration-destination-port').value = config.port || '';
  document.getElementById('migration-destination-database').value = config.database || '';
  document.getElementById('migration-destination-username').value = config.username || '';
  document.getElementById('migration-destination-password').value = config.password || '';
}

function formatNumber(value) {
  const num = Number(value || 0);
  return Number.isFinite(num) ? num.toLocaleString('id-ID') : '0';
}

function updateAnalysisCard(type, data = {}) {
  const prefix = type === 'destination' ? 'analysis-destination' : 'analysis-source';
  const panelEl = document.getElementById(prefix + '-panel');

  const tablesEl = document.getElementById(prefix + '-tables');
  const recordsEl = document.getElementById(prefix + '-records');
  const sizeEl = document.getElementById(prefix + '-size');
  const statusEl = document.getElementById(prefix + '-status');
  if (!tablesEl || !recordsEl || !sizeEl || !statusEl) return;

  if (panelEl) {
    panelEl.classList.remove('d-none');
  }

  if (data.failed) {
    tablesEl.textContent = '-';
    recordsEl.textContent = '-';
    sizeEl.textContent = '-';
    statusEl.textContent = data.status_label || 'Koneksi gagal';
    return;
  }

  tablesEl.textContent = formatNumber(data.tables ?? 0);
  recordsEl.textContent = formatNumber(data.total_records ?? 0);
  sizeEl.textContent = data.size_human || '-';

  if (type === 'destination') {
    statusEl.textContent = data.database_status_label || 'Connected';
  } else {
    statusEl.textContent = 'Connected';
  }
}

async function testSourceConnection() {
  const button = document.getElementById('btn-test-source');
  const payload = collectSourceConfigPayload();
  const driver = payload.driver;
  const host = payload.host;
  const port = payload.port;
  const database = payload.database;
  const username = payload.username;
  const password = payload.password;

  if (!host || !database || !username) {
    renderSourceTestResult('warning', 'Host, Database, dan Username wajib diisi.');
    updateAnalysisCard('source', { failed: true, status_label: 'Input belum lengkap' });
    return;
  }

  button.disabled = true;
  const originalLabel = button.textContent;
  button.textContent = 'Testing...';
  renderSourceTestResult('info', 'Menguji koneksi source server...');

  try {
    const response = await fetch(URL_MIGRATION_SOURCE_TEST, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        driver,
        host,
        port,
        database,
        username,
        password
      })
    });

    const data = await response.json();
    if (!response.ok || data.connected === false) {
      renderSourceTestResult('danger', data.error || 'Gagal konek ke source server.');
      updateAnalysisCard('source', { failed: true });
      return;
    }

    updateAnalysisCard('source', data);
    renderSourceTestResult(
      'success',
      'Connected (' + escapeHtml(data.driver || driver.toUpperCase()) + ') - Database: ' +
      escapeHtml(data.database || database) +
      ' - Tables: ' + escapeHtml(String(data.tables ?? 0)) +
      ' - Records: ' + escapeHtml(formatNumber(data.total_records ?? 0)) +
      ' - Size: ' + escapeHtml(data.size_human || '-')
    );
  } catch (error) {
    renderSourceTestResult('danger', 'Tidak dapat menghubungi server.');
    updateAnalysisCard('source', { failed: true });
  } finally {
    button.disabled = false;
    button.textContent = originalLabel;
  }
}

function renderSourceTestResult(type, message) {
  const el = document.getElementById('migration-source-test-result');
  if (!el) return;

  el.classList.remove('d-none', 'alert-info', 'alert-success', 'alert-warning', 'alert-danger');
  if (type === 'success') {
    el.classList.add('alert-success');
  } else if (type === 'warning') {
    el.classList.add('alert-warning');
  } else if (type === 'danger') {
    el.classList.add('alert-danger');
  } else {
    el.classList.add('alert-info');
  }

  el.innerHTML = message;
}

async function testDestinationConnection() {
  const button = document.getElementById('btn-test-destination');
  const payload = collectDestinationConfigPayload();
  const driver = payload.driver;
  const host = payload.host;
  const port = payload.port;
  const database = payload.database;
  const username = payload.username;
  const password = payload.password;

  if (!host || !database || !username) {
    renderDestinationTestResult('warning', 'Host, Database, dan Username destination wajib diisi.');
    updateAnalysisCard('destination', { failed: true, status_label: 'Input belum lengkap' });
    return;
  }

  button.disabled = true;
  const originalLabel = button.textContent;
  button.textContent = 'Testing...';
  renderDestinationTestResult('info', 'Menguji koneksi destination server...');

  try {
    const response = await fetch(URL_MIGRATION_DESTINATION_TEST, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        driver,
        host,
        port,
        database,
        username,
        password
      })
    });

    const data = await response.json();
    if (!response.ok || data.connected === false) {
      renderDestinationTestResult('danger', data.error || 'Gagal konek ke destination server.');
      updateAnalysisCard('destination', { failed: true });
      return;
    }

    const statusLabel = data.database_status_label || (data.database_empty ? 'Database Empty' : 'Database sudah berisi data');
    const resultType = data.database_empty ? 'success' : 'warning';
    const showClearButton = !data.database_empty && (data.driver || '').toUpperCase() === 'PGSQL';

    updateAnalysisCard('destination', data);
    renderDestinationTestResult(
      resultType,
      'Connected (' + escapeHtml(data.driver || driver.toUpperCase()) + ') - Database: ' +
      escapeHtml(data.database || database) +
      ' - Tables: ' + escapeHtml(String(data.tables ?? 0)) +
      ' - Records: ' + escapeHtml(formatNumber(data.total_records ?? 0)) +
      ' - Size: ' + escapeHtml(data.size_human || '-') +
      ' - Status: ' + escapeHtml(statusLabel),
      showClearButton
    );
  } catch (error) {
    renderDestinationTestResult('danger', 'Tidak dapat menghubungi server destination.');
    updateAnalysisCard('destination', { failed: true });
  } finally {
    button.disabled = false;
    button.textContent = originalLabel;
  }
}

function renderDestinationTestResult(type, message, showClearButton = false) {
  const el = document.getElementById('migration-destination-test-result');
  if (!el) return;

  el.classList.remove('d-none', 'alert-info', 'alert-success', 'alert-warning', 'alert-danger');
  if (type === 'success') {
    el.classList.add('alert-success');
  } else if (type === 'warning') {
    el.classList.add('alert-warning');
  } else if (type === 'danger') {
    el.classList.add('alert-danger');
  } else {
    el.classList.add('alert-info');
  }

  const clearButtonHtml = showClearButton
    ? '<div class="mt-2"><button id="btn-clear-destination-data" type="button" class="btn btn-danger btn-sm">Hapus Data Tujuan</button></div>'
    : '';

  el.innerHTML = '<div>' + message + '</div>' + clearButtonHtml;

  if (showClearButton) {
    const clearButton = document.getElementById('btn-clear-destination-data');
    if (clearButton) {
      clearButton.addEventListener('click', clearDestinationData);
    }
  }
}

async function clearDestinationData() {
  const payload = collectDestinationConfigPayload();
  const driver = payload.driver;
  const host = payload.host;
  const port = payload.port;
  const database = payload.database;
  const username = payload.username;
  const password = payload.password;

  const confirmation = window.prompt('Ketik CLEAR DATA untuk konfirmasi pembersihan data destination.');
  if (confirmation === null) {
    return;
  }

  if (confirmation !== 'CLEAR DATA') {
    renderDestinationTestResult('warning', 'Konfirmasi dibatalkan. Anda harus mengetik CLEAR DATA secara tepat.');
    return;
  }

  renderDestinationTestResult('info', 'Menjalankan pembersihan schema destination...');

  try {
    const response = await fetch(URL_MIGRATION_DESTINATION_CLEAR, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        driver,
        host,
        port,
        database,
        username,
        password,
        confirmation
      })
    });

    const data = await response.json();
    if (!response.ok || data.success === false) {
      renderDestinationTestResult('danger', data.error || 'Gagal menghapus data destination.');
      return;
    }

    renderDestinationTestResult('success', escapeHtml(data.message || 'Pembersihan data destination berhasil.'));
    await testDestinationConnection();
  } catch (error) {
    renderDestinationTestResult('danger', 'Tidak dapat menghubungi server saat pembersihan data destination.');
  }
}

function toggleProgressDownload(meta, token) {
  const link = document.getElementById('progress-modal-download');
  const effectiveToken = token || activeProgressToken;

  if (meta.operation === 'backup' && effectiveToken && (meta.file_name || meta.download_url || meta.stage === 'completed')) {
    if (meta.storage_type === 'cloud') {
      link.href = meta.cloud_url || URL_GOOGLE_DRIVE;
      link.innerHTML = '<i data-feather="external-link" style="width:13px;height:13px;"></i> Buka Google Drive';
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
    } else {
      link.href = URL_BACKUP_DL + '/' + encodeURIComponent(effectiveToken);
      link.innerHTML = '<i data-feather="download" style="width:13px;height:13px;"></i> Download File';
      link.removeAttribute('target');
      link.removeAttribute('rel');
    }

    link.classList.remove('d-none');
    if (window.feather) { feather.replace(); }
    return;
  }

  link.setAttribute('href', '#');
  link.innerHTML = '<i data-feather="download" style="width:13px;height:13px;"></i> Download File';
  link.removeAttribute('target');
  link.removeAttribute('rel');
  link.classList.add('d-none');
}

function mergeProgressMeta(meta) {
  if (!meta || meta.operation !== 'migration') {
    return meta || {};
  }

  const merged = Object.assign({}, lastMigrationMeta, meta);
  if ((!merged.source_tables_text || merged.source_tables_text === '') && Array.isArray(merged.source_tables)) {
    merged.source_tables_text = merged.source_tables.join(', ');
  }

  lastMigrationMeta = merged;
  return merged;
}

function renderProgressMeta(meta) {
  const box = document.getElementById('progress-detail');
  const content = document.getElementById('progress-detail-content');
  const lines = [];

  if (meta.stage_label) lines.push(['Tahap', meta.stage_label]);
  if (meta.migration_mode_label) lines.push(['Mode migration', meta.migration_mode_label]);
  if (meta.storage_label) lines.push(['Storage', meta.storage_label]);
  if (meta.backup_scope_label) lines.push(['Mode backup', meta.backup_scope_label]);
  if (meta.operation === 'migration') {
    if (meta.source_label) lines.push(['Source', meta.source_label]);
    if (meta.destination_label) lines.push(['Destination', meta.destination_label]);
  } else if (meta.source_label) {
    lines.push(['Sumber restore', meta.source_label]);
  }
  if (meta.queued_at) lines.push(['Waktu mulai', meta.queued_at]);
  if (meta.queue_description) lines.push(['Status proses', meta.queue_description]);
  if (meta.user) lines.push(['User', meta.user]);
  if (meta.backup_date) lines.push(['Tanggal proses', meta.backup_date]);
  if (meta.total_tables) {
    const processed = meta.processed_tables ?? 0;
    lines.push(['Tabel diproses', processed + ' / ' + meta.total_tables]);
  }
  if (meta.current_table) lines.push(['Tabel aktif', meta.current_table]);
  if (meta.source_tables_text) lines.push(['Daftar tabel source', meta.source_tables_text]);
  if (meta.total_tables || typeof meta.table_transfer_pct !== 'undefined') {
    const transferPctRaw = typeof meta.table_transfer_pct !== 'undefined'
      ? Number(meta.table_transfer_pct)
      : Math.round((Number(meta.processed_tables ?? 0) / Math.max(Number(meta.total_tables ?? 1), 1)) * 100);
    const transferPct = Math.min(100, Math.max(0, transferPctRaw));
    lines.push(['Progress transfer tabel', transferPct + '%']);
  }
  if (typeof meta.table_rows_total !== 'undefined') {
    const rowsTotal = Number(meta.table_rows_total ?? 0);
    const rowsProcessed = Number(meta.table_rows_processed ?? 0);
    const rowPct = rowsTotal > 0 ? Math.min(100, Math.max(0, Math.round((rowsProcessed / rowsTotal) * 100))) : 100;
    lines.push(['Progress tabel aktif', rowsProcessed + ' / ' + rowsTotal + ' (' + rowPct + '%)']);
  }
  if (meta.applied_statements) lines.push(['Statement SQL diterapkan', String(meta.applied_statements)]);
  if (meta.current_action) lines.push(['Aksi SQL', meta.current_action]);
  if (meta.total_records) {
    const processedRecords = meta.processed_records ?? 0;
    lines.push(['Record diproses', processedRecords + ' / ' + meta.total_records]);
  }
  if (meta.total_lines) {
    const processedLines = meta.processed_lines ?? 0;
    lines.push(['Baris SQL', processedLines + ' / ' + meta.total_lines]);
  }
  if (meta.total_files) {
    const processedFiles = meta.processed_files ?? 0;
    lines.push(['File arsip', processedFiles + ' / ' + meta.total_files]);
  }
  if (meta.file_name) lines.push(['Nama file', meta.file_name]);
  if (meta.file_size_human) lines.push(['Ukuran file', meta.file_size_human]);
  if (meta.cloud_provider) lines.push(['Provider cloud', meta.cloud_provider === 'google_drive' ? 'Google Drive' : meta.cloud_provider]);
  if (meta.finished_at) lines.push(['Selesai pada', meta.finished_at]);
  if (meta.duration_label) lines.push(['Durasi', meta.duration_label]);

  let recentHtml = '';
  if (Array.isArray(meta.recent_tables) && meta.recent_tables.length) {
    const items = meta.recent_tables.map((t, idx) => {
      const isLast = idx === meta.recent_tables.length - 1;
      const marker = isLast ? '&#9654;' : '&#10003;';
      const cls = isLast ? 'fw-bold text-primary' : 'text-muted';
      return '<div class="' + cls + '">' + marker + ' ' + escapeHtml(String(t)) + '</div>';
    }).join('');
    recentHtml =
      '<div class="mt-1">' +
        '<div class="text-muted" style="font-size:.72rem;">Tabel yang diproses (terbaru):</div>' +
        '<div id="progress-tables-log" class="border rounded px-2 py-1 mt-1 bg-white" style="max-height:104px;overflow-y:auto;font-size:.74rem;line-height:1.5;">' +
          items +
        '</div>' +
      '</div>';
  }

  if (!lines.length && !recentHtml) {
    content.innerHTML = '';
    box.classList.add('d-none');
    return;
  }

  content.innerHTML = lines.map(([label, value]) =>
    '<div><span class="text-muted">' + escapeHtml(label) + ':</span> <strong>' + escapeHtml(String(value)) + '</strong></div>'
  ).join('') + recentHtml;
  box.classList.remove('d-none');

  // Auto-scroll log ke baris terbaru.
  const logEl = document.getElementById('progress-tables-log');
  if (logEl) {
    logEl.scrollTop = logEl.scrollHeight;
  }
}

function escapeHtml(text) {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function formatBytes(bytes) {
  if (bytes < 1024)     return bytes + ' B';
  if (bytes < 1048576)  return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
}

function getSelectedBackupStorageType() {
  return document.querySelector('input[name="backup-storage-type"]:checked')?.value || 'local';
}

function getSelectedBackupScope() {
  return document.querySelector('input[name="backup-scope"]:checked')?.value || 'full';
}

function getSelectedRestoreSourceType() {
  return document.querySelector('input[name="restore-source-type"]:checked')?.value || 'local';
}

function getSelectedRestoreMode() {
  return document.querySelector('input[name="restore-mode"]:checked')?.value || 'full';
}

function syncRestoreSourceUI() {
  const sourceType = getSelectedRestoreSourceType();
  const localWrap = document.getElementById('restore-local-source');
  const driveWrap = document.getElementById('restore-drive-source');

  if (sourceType === 'cloud') {
    localWrap.classList.add('d-none');
    driveWrap.classList.remove('d-none');
    return;
  }

  localWrap.classList.remove('d-none');
  driveWrap.classList.add('d-none');
}

function loadGoogleDriveBackupFiles() {
  const loading = document.getElementById('drive-picker-loading');
  const error = document.getElementById('drive-picker-error');
  const wrap = document.getElementById('drive-picker-table-wrap');
  const body = document.getElementById('drive-picker-body');

  loading.classList.remove('d-none');
  wrap.classList.add('d-none');
  error.classList.add('d-none');
  body.innerHTML = '';

  fetch(URL_GOOGLE_DRIVE_FILES, { headers: { 'Accept': 'application/json' } })
    .then(async res => ({ status: res.status, body: await res.json() }))
    .then(({ body: files }) => {
      loading.classList.add('d-none');

      if (files.error) {
        error.textContent = files.error;
        error.classList.remove('d-none');
        return;
      }

      const backupFiles = (files || []).filter(file => /\.(tar\.gz|gz|tar)$/i.test(file.filename || ''));
      if (!backupFiles.length) {
        error.textContent = 'Tidak ada file backup .tar.gz di Google Drive.';
        error.classList.remove('d-none');
        return;
      }

      body.innerHTML = backupFiles.map(file => {
        const active = selectedDriveRestoreFile && selectedDriveRestoreFile.file_id === file.file_id ? 'active' : '';
        return '<tr class="drive-picker-row ' + active + '" data-file-id="' + escapeHtml(file.file_id) + '">' +
          '<td>' + escapeHtml(file.filename) + '</td>' +
          '<td class="text-muted">' + escapeHtml(file.size_human || '-') + '</td>' +
          '<td class="text-muted">' + escapeHtml(file.modified || '-') + '</td>' +
          '<td class="text-center"><button type="button" class="btn btn-outline-primary btn-sm btn-choose-drive-file">Pilih</button></td>' +
        '</tr>';
      }).join('');

      wrap.classList.remove('d-none');
      body.querySelectorAll('.btn-choose-drive-file').forEach((button, index) => {
        button.addEventListener('click', () => {
          selectedDriveRestoreFile = backupFiles[index];
          document.getElementById('selected-drive-file').classList.remove('d-none');
          document.getElementById('selected-drive-file').innerHTML = '<strong>File terpilih:</strong> ' + escapeHtml(selectedDriveRestoreFile.filename) + ' <span class="text-muted">(' + escapeHtml(selectedDriveRestoreFile.size_human || '-') + ')</span>';
          document.getElementById('btn-restore-drive').disabled = false;
          bsDrivePicker.hide();
        });
      });
    })
    .catch(() => {
      loading.classList.add('d-none');
      error.textContent = 'Gagal memuat file Google Drive.';
      error.classList.remove('d-none');
    });
}
</script>
    @endpush
</x-app-layout>