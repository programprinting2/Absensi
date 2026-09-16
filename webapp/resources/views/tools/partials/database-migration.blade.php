<div class="dm-wizard">

  {{-- Header --}}
  <div class="dm-header">
    <div class="d-flex align-items-center gap-3">
      <div class="dm-header-icon">
        <i data-feather="database" style="width:22px;height:22px"></i>
      </div>
      <div>
        <h4 class="dm-header-title">Database Migration</h4>
        <p class="dm-header-sub">Pindahkan database Anda dengan aman dan mudah</p>
      </div>
    </div>
    <button type="button" class="btn btn-outline-secondary btn-sm dm-advanced-toggle" data-bs-toggle="collapse" data-bs-target="#dm-advanced-options">
      <i data-feather="help-circle" style="width:13px;height:13px"></i> Help
    </button>
  </div>

  {{-- Stepper --}}
  <div class="dm-stepper" id="dm-stepper">
    <div class="dm-stepper-item active" data-step="1">
      <div class="dm-stepper-circle">1</div>
      <div class="dm-stepper-label">Koneksi</div>
      <div class="dm-stepper-desc">Source &amp; Destination</div>
    </div>
    <div class="dm-stepper-item" data-step="2">
      <div class="dm-stepper-circle">2</div>
      <div class="dm-stepper-label">Pengaturan</div>
      <div class="dm-stepper-desc">Pilih database &amp; opsi</div>
    </div>
    <div class="dm-stepper-item" data-step="3">
      <div class="dm-stepper-circle">3</div>
      <div class="dm-stepper-label">Migrasi</div>
      <div class="dm-stepper-desc">Proses transfer data</div>
    </div>
    <div class="dm-stepper-item" data-step="4">
      <div class="dm-stepper-circle">4</div>
      <div class="dm-stepper-label">Hasil</div>
      <div class="dm-stepper-desc">Verifikasi &amp; selesai</div>
    </div>
  </div>

  {{-- Advanced options (collapsed) --}}
  <div class="collapse mb-3" id="dm-advanced-options">
    <div class="dm-section" style="background:#f8f9fa">
      <div class="d-flex flex-wrap gap-2">
        <button id="btn-save-source-config" type="button" class="btn btn-outline-secondary btn-sm">Save Source</button>
        <button id="btn-load-source-config" type="button" class="btn btn-outline-secondary btn-sm">Load Source</button>
        <button id="btn-save-destination-config" type="button" class="btn btn-outline-secondary btn-sm">Save Destination</button>
        <button id="btn-load-destination-config" type="button" class="btn btn-outline-secondary btn-sm">Load Destination</button>
        <button type="button" class="btn btn-success btn-sm" id="btn-switch-server">Switch ke Destination</button>
        <button type="button" class="btn btn-outline-danger btn-sm" id="btn-rollback-server">Rollback Switch</button>
      </div>
    </div>
  </div>

  {{-- Step 1: Koneksi --}}
  <div class="dm-section" id="dm-step-1">
    <div class="row g-3 align-items-stretch">
      <div class="col-12 col-lg">
        <div class="dm-server-card">
          <div class="dm-server-card-header">
            <div class="d-flex align-items-center gap-2">
              <div class="dm-server-icon source">
                <i data-feather="server" style="width:16px;height:16px"></i>
              </div>
              <div>
                <div class="dm-server-name">Source Server</div>
                <div class="dm-server-desc">Server asal (database yang akan dipindahkan)</div>
              </div>
            </div>
            <span id="migration-source-verified" class="dm-verified-badge d-none">
              <i data-feather="check-circle" style="width:12px;height:12px"></i> Terverifikasi
            </span>
          </div>
          <div class="row g-2">
            <div class="col-12 col-md-4">
              <label class="migration-field-label" for="migration-source-driver">Driver</label>
              <select id="migration-source-driver" class="form-select form-select-sm">
                <option value="pgsql" selected>PostgreSQL</option>
                <option value="mysql">MySQL</option>
              </select>
            </div>
            <div class="col-12 col-md-5">
              <label class="migration-field-label" for="migration-source-host">Host / IP</label>
              <input id="migration-source-host" class="form-control form-control-sm" placeholder="127.0.0.1">
            </div>
            <div class="col-12 col-md-3">
              <label class="migration-field-label" for="migration-source-port">Port</label>
              <input id="migration-source-port" class="form-control form-control-sm" placeholder="5432" value="5432">
            </div>
            <div class="col-12">
              <label class="migration-field-label" for="migration-source-database">Database</label>
              <input id="migration-source-database" class="form-control form-control-sm" placeholder="nama_database">
            </div>
            <div class="col-12 col-md-6">
              <label class="migration-field-label" for="migration-source-username">Username</label>
              <input id="migration-source-username" class="form-control form-control-sm" placeholder="username">
            </div>
            <div class="col-12 col-md-6">
              <label class="migration-field-label" for="migration-source-password">Password</label>
              <div class="input-group input-group-sm">
                <input id="migration-source-password" type="password" class="form-control form-control-sm" placeholder="password">
                <button class="btn btn-outline-secondary dm-toggle-pw" type="button" data-target="migration-source-password" tabindex="-1">
                  <i data-feather="eye" style="width:13px;height:13px"></i>
                </button>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-center justify-content-between mt-3 flex-wrap gap-2">
            <div id="migration-source-test-result" class="dm-test-result d-none"></div>
            <div class="d-flex gap-2 ms-auto">
              <button id="btn-fetch-source" type="button" class="btn btn-outline-secondary btn-sm">
                <i data-feather="download" style="width:13px;height:13px"></i> Fetch
              </button>
              <button id="btn-test-source" type="button" class="btn btn-outline-primary btn-sm">
                <i data-feather="zap" style="width:13px;height:13px"></i> Test Koneksi
              </button>
            </div>
          </div>
          <div id="analysis-source-panel" class="d-none mt-2">
            <div class="migration-stats-grid">
              <div class="migration-stat"><span class="label">Total Tabel</span><span class="value" id="analysis-source-tables">-</span></div>
              <div class="migration-stat"><span class="label">Total Record</span><span class="value" id="analysis-source-records">-</span></div>
              <div class="migration-stat"><span class="label">Ukuran DB</span><span class="value" id="analysis-source-size">-</span></div>
              <div class="migration-stat"><span class="label">Status</span><span class="value" id="analysis-source-status">Belum dites</span></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-auto d-none d-lg-flex align-items-center px-0">
        <div class="dm-swap-icon">
          <i data-feather="repeat" style="width:16px;height:16px"></i>
        </div>
      </div>

      <div class="col-12 col-lg">
        <div class="dm-server-card">
          <div class="dm-server-card-header">
            <div class="d-flex align-items-center gap-2">
              <div class="dm-server-icon dest">
                <i data-feather="hard-drive" style="width:16px;height:16px"></i>
              </div>
              <div>
                <div class="dm-server-name">Destination Server</div>
                <div class="dm-server-desc">Server tujuan (database baru)</div>
              </div>
            </div>
            <span id="migration-destination-verified" class="dm-verified-badge d-none">
              <i data-feather="check-circle" style="width:12px;height:12px"></i> Terverifikasi
            </span>
          </div>
          <div class="row g-2">
            <div class="col-12 col-md-4">
              <label class="migration-field-label" for="migration-destination-driver">Driver</label>
              <select id="migration-destination-driver" class="form-select form-select-sm">
                <option value="pgsql" selected>PostgreSQL</option>
                <option value="mysql">MySQL</option>
              </select>
            </div>
            <div class="col-12 col-md-5">
              <label class="migration-field-label" for="migration-destination-host">Host / IP</label>
              <input id="migration-destination-host" class="form-control form-control-sm" placeholder="10.10.10.5">
            </div>
            <div class="col-12 col-md-3">
              <label class="migration-field-label" for="migration-destination-port">Port</label>
              <input id="migration-destination-port" class="form-control form-control-sm" placeholder="5432" value="5432">
            </div>
            <div class="col-12">
              <label class="migration-field-label" for="migration-destination-database">Database</label>
              <input id="migration-destination-database" class="form-control form-control-sm" placeholder="nama_database_baru">
            </div>
            <div class="col-12 col-md-6">
              <label class="migration-field-label" for="migration-destination-username">Username</label>
              <input id="migration-destination-username" class="form-control form-control-sm" placeholder="username">
            </div>
            <div class="col-12 col-md-6">
              <label class="migration-field-label" for="migration-destination-password">Password</label>
              <div class="input-group input-group-sm">
                <input id="migration-destination-password" type="password" class="form-control form-control-sm" placeholder="password">
                <button class="btn btn-outline-secondary dm-toggle-pw" type="button" data-target="migration-destination-password" tabindex="-1">
                  <i data-feather="eye" style="width:13px;height:13px"></i>
                </button>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-center justify-content-between mt-3 flex-wrap gap-2">
            <div id="migration-destination-test-result" class="dm-test-result d-none"></div>
            <div class="d-flex gap-2 ms-auto">
              <button id="btn-fetch-destination" type="button" class="btn btn-outline-secondary btn-sm">
                <i data-feather="download" style="width:13px;height:13px"></i> Fetch
              </button>
              <button id="btn-test-destination" type="button" class="btn btn-outline-primary btn-sm">
                <i data-feather="zap" style="width:13px;height:13px"></i> Test Koneksi
              </button>
            </div>
          </div>
          <div id="analysis-destination-panel" class="d-none mt-2">
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
  </div>

  {{-- Step 2: Pengaturan Migrasi --}}
  <div class="dm-section" id="dm-step-2">
    <div class="dm-section-header">
      <div class="dm-section-num">2</div>
      <div class="flex-grow-1">
        <div class="dm-section-title">Pengaturan Migrasi</div>
        <div class="dm-section-sub">Pilih data yang akan dimigrasikan dan atur opsi migrasi</div>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm dm-advanced-toggle" data-bs-toggle="collapse" data-bs-target="#dm-advanced-options">
        Opsi Lanjutan <i data-feather="chevron-down" style="width:13px;height:13px"></i>
      </button>
    </div>

    <div class="row g-3">
      <div class="col-12 col-md-5">
        <div class="migration-field-label mb-2">Mode Migrasi</div>
        <select id="migration-mode" class="d-none">
          <option value="full" selected>Full Migration</option>
          <option value="structure">Structure Only</option>
        </select>
        <div class="row g-2">
          <div class="col-12">
            <div class="dm-mode-card active" data-mode="full" id="dm-mode-full">
              <div class="d-flex align-items-start gap-2">
                <i data-feather="database" style="width:16px;height:16px;color:#0d6efd;margin-top:2px;flex-shrink:0"></i>
                <div>
                  <div class="dm-mode-title">Full Migration</div>
                  <p class="dm-mode-desc">Struktur + semua data (Cocok untuk sebagian besar kasus)</p>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12">
            <div class="dm-mode-card" data-mode="structure" id="dm-mode-structure">
              <div class="d-flex align-items-start gap-2">
                <i data-feather="layout" style="width:16px;height:16px;color:#6c757d;margin-top:2px;flex-shrink:0"></i>
                <div>
                  <div class="dm-mode-title">Structure Only</div>
                  <p class="dm-mode-desc">Hanya struktur tabel (Tanpa data)</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-7">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="migration-field-label mb-0">Pilih Tabel <span class="text-muted fw-normal">(opsional)</span></div>
          <label class="d-flex align-items-center gap-1 mb-0" style="font-size:.78rem;cursor:pointer">
            <input type="checkbox" class="form-check-input mt-0" id="migration-select-all-tables" checked> Pilih Semua
          </label>
        </div>
        <div class="dm-table-list" id="migration-table-list">
          @foreach($tableData as $tbl)
          <label class="dm-table-item">
            <input type="checkbox" class="form-check-input migration-tbl-check mt-0" value="{{ $tbl['name'] }}" checked>
            <span class="dm-table-name">{{ $tbl['name'] }}</span>
            <span class="dm-table-rows">{{ $tbl['row_count'] }} rows</span>
          </label>
          @endforeach
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end mt-3">
      <button type="button" class="btn btn-primary btn-sm" id="btn-start-migration">
        <i data-feather="play" style="width:13px;height:13px"></i> Mulai Migrasi
      </button>
    </div>
    <div id="migration-execution-status" class="alert alert-info py-2 px-3 mt-3 mb-0 d-none" style="font-size:.8rem;"></div>
  </div>

  {{-- Step 3: Proses Migrasi --}}
  <div class="dm-section d-none" id="dm-step-3">
    <div class="dm-section-header">
      <div class="dm-section-num purple">3</div>
      <div class="flex-grow-1">
        <div class="dm-section-title">Proses Migrasi</div>
        <div class="dm-section-sub">Transfer data sedang berjalan. Mohon tunggu...</div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-muted" style="font-size:.78rem">
          <i data-feather="clock" style="width:13px;height:13px"></i>
          Berjalan <strong id="migration-inline-elapsed">00:00</strong>
        </span>
      </div>
    </div>

    <div class="mb-2" style="font-size:.82rem" id="migration-inline-status">Menyiapkan migrasi...</div>
    <div class="d-flex align-items-center gap-3 mb-3">
      <div class="dm-progress-bar-wrap flex-grow-1">
        <div class="dm-progress-bar" id="migration-inline-bar"></div>
      </div>
      <span class="dm-progress-pct" id="migration-inline-pct">0%</span>
    </div>

    <button class="btn btn-link btn-sm text-decoration-none p-0 mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#migration-log-collapse" style="font-size:.78rem">
      <i data-feather="file-text" style="width:13px;height:13px"></i>
      Log proses (klik untuk melihat detail)
    </button>
    <div class="collapse" id="migration-log-collapse">
      <div class="dm-log-panel" id="migration-inline-logs">
        <div class="dm-log-line text-muted">Menunggu proses dimulai...</div>
      </div>
    </div>
  </div>

  {{-- Step 4: Selesai --}}
  <div class="dm-section d-none" id="dm-step-4">
    <div class="dm-section-header">
      <div class="dm-section-num green">4</div>
      <div class="flex-grow-1">
        <div class="dm-section-title">Selesai</div>
        <div class="dm-section-sub" id="migration-done-message">Migrasi berhasil diselesaikan.</div>
      </div>
    </div>
    <div class="d-flex align-items-center gap-3 mb-3">
      <div class="dm-done-icon">
        <i data-feather="check" style="width:24px;height:24px"></i>
      </div>
      <div style="font-size:.85rem;color:#495057" id="migration-done-summary">
        Semua tabel berhasil dimigrasikan ke destination server.
      </div>
    </div>
    <div class="d-flex justify-content-end gap-2">
      <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-view-migration-report" data-bs-toggle="collapse" data-bs-target="#migration-log-collapse">
        <i data-feather="file-text" style="width:13px;height:13px"></i> Lihat Laporan
      </button>
      <button type="button" class="btn btn-success btn-sm" id="btn-migration-done" onclick="window.location.reload()">
        <i data-feather="check-circle" style="width:13px;height:13px"></i> Selesai
      </button>
    </div>
  </div>

</div>
