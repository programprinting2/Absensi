<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Settings</h2>
    </x-slot>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <style>      a, a:hover, a:focus, a:active { text-decoration: none !important; }
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

  /* â”€â”€ Database Migration Wizard â”€â”€ */
  .dm-wizard { max-width: 1100px; margin: 0 auto; }
  .dm-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 24px;
  }
  .dm-header-icon {
    width: 44px; height: 44px; border-radius: 10px;
    background: linear-gradient(135deg, #0d6efd, #4dabf7);
    display: flex; align-items: center; justify-content: center;
    color: #fff; flex-shrink: 0;
  }
  .dm-header-title { font-size: 1.35rem; font-weight: 700; color: #1a1a2e; margin: 0; }
  .dm-header-sub { font-size: .85rem; color: #6c757d; margin: 2px 0 0; }

  .dm-stepper {
    display: flex; align-items: center; justify-content: center;
    gap: 0; margin-bottom: 28px; padding: 0 12px;
  }
  .dm-stepper-item {
    display: flex; flex-direction: column; align-items: center;
    flex: 1; position: relative; text-align: center;
  }
  .dm-stepper-item:not(:last-child)::after {
    content: ''; position: absolute; top: 16px; left: calc(50% + 20px);
    width: calc(100% - 40px); height: 2px; background: #dee2e6; z-index: 0;
  }
  .dm-stepper-item.done:not(:last-child)::after { background: #0d6efd; }
  .dm-stepper-circle {
    width: 32px; height: 32px; border-radius: 50%;
    border: 2px solid #dee2e6; background: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: .8rem; font-weight: 700; color: #adb5bd;
    position: relative; z-index: 1; transition: all .2s;
  }
  .dm-stepper-item.active .dm-stepper-circle {
    border-color: #0d6efd; background: #0d6efd; color: #fff;
  }
  .dm-stepper-item.done .dm-stepper-circle {
    border-color: #0d6efd; background: #0d6efd; color: #fff;
  }
  .dm-stepper-label { font-size: .78rem; font-weight: 600; color: #495057; margin-top: 6px; }
  .dm-stepper-desc { font-size: .68rem; color: #adb5bd; }

  .dm-section {
    background: #fff; border: 1px solid #e9ecef; border-radius: 12px;
    padding: 20px 24px; margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
  }
  .dm-section-header {
    display: flex; align-items: center; gap: 10px; margin-bottom: 18px;
  }
  .dm-section-num {
    width: 28px; height: 28px; border-radius: 50%;
    background: #0d6efd; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: .78rem; font-weight: 700; flex-shrink: 0;
  }
  .dm-section-num.purple { background: #6f42c1; }
  .dm-section-num.green { background: #198754; }
  .dm-section-title { font-size: .95rem; font-weight: 700; color: #1a1a2e; margin: 0; }
  .dm-section-sub { font-size: .78rem; color: #6c757d; margin: 0; }

  .dm-server-card {
    border: 1px solid #e9ecef; border-radius: 10px; padding: 18px;
    height: 100%; background: #fafbfc;
  }
  .dm-server-card-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    margin-bottom: 14px;
  }
  .dm-server-icon {
    width: 36px; height: 36px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  }
  .dm-server-icon.source { background: rgba(13,110,253,.12); color: #0d6efd; }
  .dm-server-icon.dest { background: rgba(111,66,193,.12); color: #6f42c1; }
  .dm-server-name { font-size: .88rem; font-weight: 700; color: #1a1a2e; }
  .dm-server-desc { font-size: .72rem; color: #6c757d; }
  .dm-verified-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: .68rem; font-weight: 600; color: #198754;
    background: rgba(25,135,84,.1); border-radius: 20px; padding: 3px 10px;
  }
  .dm-swap-icon {
    width: 36px; height: 36px; border-radius: 50%; border: 1px solid #dee2e6;
    background: #fff; display: flex; align-items: center; justify-content: center;
    color: #6c757d; flex-shrink: 0; align-self: center;
  }
  .dm-test-result {
    font-size: .78rem; margin-top: 8px; display: flex; align-items: center; gap: 6px;
  }
  .dm-test-result.success { color: #198754; }
  .dm-test-result.danger { color: #dc3545; }
  .dm-test-result.warning { color: #fd7e14; }
  .dm-test-result.info { color: #0d6efd; }

  .dm-mode-card {
    border: 2px solid #e9ecef; border-radius: 10px; padding: 14px 16px;
    cursor: pointer; transition: border-color .15s, background .15s;
    height: 100%;
  }
  .dm-mode-card:hover { border-color: #b6d4fe; }
  .dm-mode-card.active { border-color: #0d6efd; background: rgba(13,110,253,.04); }
  .dm-mode-card .dm-mode-title { font-size: .85rem; font-weight: 700; color: #1a1a2e; }
  .dm-mode-card .dm-mode-desc { font-size: .74rem; color: #6c757d; margin: 0; }

  .dm-table-list {
    border: 1px solid #e9ecef; border-radius: 8px;
    max-height: 200px; overflow-y: auto;
  }
  .dm-table-item {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 12px; border-bottom: 1px solid #f1f3f5;
    font-size: .8rem; cursor: pointer; margin: 0;
  }
  .dm-table-item:last-child { border-bottom: none; }
  .dm-table-item:hover { background: #f8f9fa; }
  .dm-table-name { flex: 1; font-family: monospace; font-size: .78rem; }
  .dm-table-rows { font-size: .72rem; color: #6c757d; white-space: nowrap; }

  .dm-progress-bar-wrap {
    height: 10px; border-radius: 6px; background: #e9ecef; overflow: hidden;
  }
  .dm-progress-bar {
    height: 100%; border-radius: 6px; background: linear-gradient(90deg, #198754, #20c997);
    transition: width .4s ease; width: 0%;
  }
  .dm-progress-pct { font-size: .85rem; font-weight: 700; color: #198754; }
  .dm-log-panel {
    border: 1px solid #e9ecef; border-radius: 8px; background: #f8f9fa;
    max-height: 160px; overflow-y: auto; font-size: .74rem;
    font-family: monospace; padding: 10px 12px; color: #495057;
  }
  .dm-log-line { margin-bottom: 2px; }
  .dm-done-icon {
    width: 48px; height: 48px; border-radius: 50%;
    background: rgba(25,135,84,.12); color: #198754;
    display: flex; align-items: center; justify-content: center; margin-bottom: 12px;
  }
  .dm-advanced-toggle { font-size: .78rem; }

  /* â”€â”€ Backup & Restore â”€â”€ */
  .br-wrap { max-width: 1100px; margin: 0 auto; }
  .br-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
  .br-header-icon {
    width: 44px; height: 44px; border-radius: 10px;
    background: linear-gradient(135deg, #0d6efd, #4dabf7);
    display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0;
  }
  .br-header-title { font-size: 1.35rem; font-weight: 700; color: #1a1a2e; margin: 0; }
  .br-header-sub { font-size: .85rem; color: #6c757d; margin: 2px 0 0; }
  .br-section {
    background: #fff; border: 1px solid #e9ecef; border-radius: 12px;
    padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,.04);
  }
  .br-section-title {
    font-size: .82rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .04em; color: #6c757d; margin-bottom: 12px;
    display: flex; align-items: center; gap: 6px;
  }
  .br-stat-card {
    background: #fff; border: 1px solid #e9ecef; border-radius: 10px;
    padding: 14px 16px; display: flex; align-items: center; gap: 12px; height: 100%;
  }
  .br-stat-icon {
    width: 40px; height: 40px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  }
  .br-stat-icon.blue { background: rgba(13,110,253,.12); color: #0d6efd; }
  .br-stat-icon.green { background: rgba(25,135,84,.12); color: #198754; }
  .br-stat-icon.orange { background: rgba(253,126,20,.12); color: #fd7e14; }
  .br-stat-icon.purple { background: rgba(111,66,193,.12); color: #6f42c1; }
  .br-stat-label { font-size: .72rem; color: #6c757d; }
  .br-stat-value { font-size: 1rem; font-weight: 700; color: #1a1a2e; }
  .br-action-card {
    border: 1px solid #e9ecef; border-radius: 12px; padding: 20px; background: #fff;
  }
  .br-action-card.backup { border-top: 3px solid #0d6efd; }
  .br-action-card.restore { border-top: 3px solid #198754; }
  .br-action-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
  .br-action-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  }
  .br-action-icon.backup { background: rgba(13,110,253,.12); color: #0d6efd; }
  .br-action-icon.restore { background: rgba(25,135,84,.12); color: #198754; }
  .br-action-title { font-size: .95rem; font-weight: 700; color: #1a1a2e; }
  .br-action-desc { font-size: .76rem; color: #6c757d; }
  .br-field-label { font-size: .74rem; font-weight: 600; color: #495057; margin-bottom: 6px; }
  .br-option {
    border: 1px solid #dee2e6; border-radius: 10px; padding: 10px 12px;
    background: #fff; cursor: pointer; transition: border-color .15s, background .15s; margin: 0;
  }
  .br-option-title { font-size: .84rem; font-weight: 600; color: #1a1a2e; }
  .br-option-desc { font-size: .72rem; color: #6c757d; }
  .backup-option:has(input:checked) { border-color: #0d6efd; background: rgba(13,110,253,.04); }
  .restore-option:has(input:checked) { border-color: #198754; background: rgba(25,135,84,.04); }
  .br-dropzone {
    border: 2px dashed #b8dfc8; border-radius: 10px; padding: 24px 16px;
    text-align: center; cursor: pointer; background: rgba(25,135,84,.03);
    transition: border-color .15s, background .15s;
  }
  .br-dropzone:hover, .br-dropzone.dragover { border-color: #198754; background: rgba(25,135,84,.06); }
  .br-dropzone-title { font-size: .82rem; font-weight: 600; color: #198754; margin-top: 8px; }
  .br-dropzone-desc { font-size: .72rem; color: #6c757d; margin-top: 4px; }
  .br-dropzone-file { font-size: .78rem; color: #495057; margin-top: 8px; font-weight: 600; }
  .br-schedule-badge { font-size: .68rem; font-weight: 600; }
  .br-schedule-preview {
    background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px;
    padding: 10px 12px; font-size: .74rem; font-family: monospace; word-break: break-all;
  }
    </style>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('settings._settings-tabs')

            <div class="db-tools-scope mt-4">
  <div class="row">
    <div class="col-md-12 grid-margin stretch-card">
      <div class="card">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between border-bottom gap-2">
            <ul class="nav nav-tabs nav-tabs-line border-bottom-0 flex-grow-1" id="databaseToolTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="backup-restore-tab" data-bs-toggle="tab" data-bs-target="#backup-restore-pane" type="button" role="tab" aria-controls="backup-restore-pane" aria-selected="true">
                  <i data-feather="hard-drive" class="icon-sm me-2"></i>
                  Backup / Restore
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="migration-server-tab" data-bs-toggle="tab" data-bs-target="#migration-server-pane" type="button" role="tab" aria-controls="migration-server-pane" aria-selected="false">
                  <i data-feather="repeat" class="icon-sm me-2"></i>
                  Migration Server
                </button>
              </li>
            </ul>
          </div>

          <div class="tab-content mt-4" id="databaseToolTabContent">
            <div class="tab-pane fade show active" id="backup-restore-pane" role="tabpanel" aria-labelledby="backup-restore-tab" tabindex="0">
              @include('tools.partials.database-backup-restore')
            </div>

            <div class="tab-pane fade" id="migration-server-pane" role="tabpanel" aria-labelledby="migration-server-tab" tabindex="0">
              @include('tools.partials.database-migration')
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
      <div class="modal-content border border-success">
        <div class="modal-header border-0 pb-1">
          <h6 class="modal-title fw-bold text-success d-flex align-items-center gap-2">
            <i data-feather="alert-triangle" style="width:16px;height:16px;"></i>
            <span id="rc-title">Konfirmasi Restore Database</span>
          </h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body py-2">
          <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:.82rem;">
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
          <button type="button" class="btn btn-success btn-sm" id="btn-confirm-restore">
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
  </div>            </div>
        </div>
    </div>

    @push('scripts')<script>
feather.replace();

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const URL_BACKUP_START   = "{{ route('tools.database.backup.start') }}";
const URL_BACKUP_RUN     = "{{ route('tools.database.backup.run') }}";
const URL_BACKUP_SCHEDULE_APPLY = "{{ route('tools.database.backup.schedule.apply') }}";
const URL_BACKUP_SCHEDULE_DISABLE = "{{ route('tools.database.backup.schedule.disable') }}";
const SCHEDULE_BOOT = @json($backupSchedule ?? []);
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

function createBootstrapModal(id, options = {}) {
  const el = document.getElementById(id);
  if (!el || typeof bootstrap === 'undefined') {
    return null;
  }
  return new bootstrap.Modal(el, options);
}

function initDatabaseTools() {
  // Backup/restor — pasang dulu agar tetap jalan walau init lain gagal.
  document.getElementById('btn-backup')?.addEventListener('click', startBackup);
  document.getElementById('btn-restore')?.addEventListener('click', prepareRestoreLocal);
  document.getElementById('btn-confirm-restore')?.addEventListener('click', () => runPreparedRestore());

  try {
    bsProgress = createBootstrapModal('modal-progress', { backdrop: 'static', keyboard: false });
    bsConfirm = createBootstrapModal('modal-confirm-restore');
    bsDrivePicker = createBootstrapModal('modal-select-drive-file');
    bsMigrationConfirm = createBootstrapModal('modal-confirm-migration');
    bsClearTables = createBootstrapModal('modal-clear-tables');

    initClearTables();
    initMigrationWizard();
    initBackupRestoreUI();
    initScheduledBackupUI();

    syncRestoreSourceUI();

    document.querySelectorAll('input[name="restore-source-type"]').forEach(input => {
      input.addEventListener('change', syncRestoreSourceUI);
    });

    const restoreFileInput = document.getElementById('restore-file-input');
    if (restoreFileInput) {
      restoreFileInput.addEventListener('change', function() {
        const restoreBtn = document.getElementById('btn-restore');
        if (restoreBtn) restoreBtn.disabled = !this.files.length;
        updateRestoreSelectedFileLabel(this.files[0]);
      });
    }

    document.getElementById('btn-select-drive-file')?.addEventListener('click', () => {
      bsDrivePicker?.show();
      loadGoogleDriveBackupFiles();
    });

    document.getElementById('btn-refresh-drive-picker')?.addEventListener('click', loadGoogleDriveBackupFiles);

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
    loadSourceConfigButton.addEventListener('click', () => loadSourceConfig(loadSourceConfigButton));
  }

  const fetchSourceButton = document.getElementById('btn-fetch-source');
  if (fetchSourceButton) {
    fetchSourceButton.addEventListener('click', () => loadSourceConfig(fetchSourceButton));
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
    loadDestinationConfigButton.addEventListener('click', () => loadDestinationConfig(loadDestinationConfigButton));
  }

  const fetchDestinationButton = document.getElementById('btn-fetch-destination');
  if (fetchDestinationButton) {
    fetchDestinationButton.addEventListener('click', () => loadDestinationConfig(fetchDestinationButton));
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

    document.getElementById('btn-restore-drive')?.addEventListener('click', prepareRestoreCloud);
  } catch (err) {
    console.error('[database-tools] init error:', err);
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initDatabaseTools);
} else {
  initDatabaseTools();
}

let migrationInlineTimer = null;
let migrationIsRunning = false;

function initMigrationWizard() {
  document.querySelectorAll('.dm-mode-card').forEach(card => {
    card.addEventListener('click', () => {
      document.querySelectorAll('.dm-mode-card').forEach(c => c.classList.remove('active'));
      card.classList.add('active');
      const mode = card.dataset.mode || 'full';
      const select = document.getElementById('migration-mode');
      if (select) select.value = mode;
    });
  });

  document.querySelectorAll('.dm-toggle-pw').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      if (!input) return;
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      const icon = btn.querySelector('[data-feather]');
      if (icon) icon.setAttribute('data-feather', isPassword ? 'eye-off' : 'eye');
      feather.replace();
    });
  });

  const selectAll = document.getElementById('migration-select-all-tables');
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('.migration-tbl-check').forEach(cb => { cb.checked = this.checked; });
    });
  }

  document.getElementById('migration-table-list')?.addEventListener('change', (e) => {
    if (!e.target.classList.contains('migration-tbl-check')) return;
    const checks = document.querySelectorAll('.migration-tbl-check');
    const checked = document.querySelectorAll('.migration-tbl-check:checked');
    if (selectAll) {
      selectAll.checked = checks.length > 0 && checked.length === checks.length;
      selectAll.indeterminate = checked.length > 0 && checked.length < checks.length;
    }
  });
}

function setDmStepperStep(step) {
  document.querySelectorAll('.dm-stepper-item').forEach(item => {
    const s = Number(item.dataset.step);
    item.classList.remove('active', 'done');
    if (s < step) item.classList.add('done');
    if (s === step) item.classList.add('active');
  });
}

function showMigrationStep(step) {
  setDmStepperStep(step);
  const step3 = document.getElementById('dm-step-3');
  const step4 = document.getElementById('dm-step-4');
  if (step3) step3.classList.toggle('d-none', step < 3);
  if (step4) step4.classList.toggle('d-none', step < 4);
}

function setVerifiedBadge(type, visible) {
  const el = document.getElementById(type === 'destination' ? 'migration-destination-verified' : 'migration-source-verified');
  if (el) el.classList.toggle('d-none', !visible);
}

function renderMigrationTableList(tables) {
  const list = document.getElementById('migration-table-list');
  const selectAll = document.getElementById('migration-select-all-tables');
  if (!list || !Array.isArray(tables)) return;

  list.innerHTML = tables.map(tbl => {
    const name = escapeHtml(tbl.name || '');
    const rows = formatNumber(tbl.row_count ?? 0);
    return '<label class="dm-table-item">' +
      '<input type="checkbox" class="form-check-input migration-tbl-check mt-0" value="' + name + '" checked>' +
      '<span class="dm-table-name">' + name + '</span>' +
      '<span class="dm-table-rows">' + rows + ' rows</span>' +
      '</label>';
  }).join('');

  if (selectAll) {
    selectAll.checked = tables.length > 0;
    selectAll.indeterminate = false;
  }
}

function updateInlineMigrationProgress(pct, msg) {
  const p = Math.max(0, Math.min(100, pct));
  const bar = document.getElementById('migration-inline-bar');
  const pctEl = document.getElementById('migration-inline-pct');
  const statusEl = document.getElementById('migration-inline-status');
  if (bar) bar.style.width = p + '%';
  if (pctEl) pctEl.textContent = p + '%';
  if (statusEl && msg) statusEl.textContent = msg;
}

function appendMigrationLog(line) {
  const logs = document.getElementById('migration-inline-logs');
  if (!logs || !line) return;
  const firstMuted = logs.querySelector('.text-muted');
  if (firstMuted && firstMuted.textContent.includes('Menunggu')) firstMuted.remove();
  const div = document.createElement('div');
  div.className = 'dm-log-line';
  div.textContent = line;
  logs.appendChild(div);
  logs.scrollTop = logs.scrollHeight;
}

function startMigrationInlineTimer() {
  if (migrationInlineTimer) clearInterval(migrationInlineTimer);
  const startAt = Date.now();
  migrationInlineTimer = setInterval(() => {
    const el = document.getElementById('migration-inline-elapsed');
    if (el) el.textContent = formatDurationMs(Date.now() - startAt);
  }, 1000);
}

function stopMigrationInlineTimer() {
  if (migrationInlineTimer) clearInterval(migrationInlineTimer);
  migrationInlineTimer = null;
}

function initBackupRestoreUI() {
  const dropzone = document.getElementById('restore-dropzone');
  const fileInput = document.getElementById('restore-file-input');
  const tableSearch = document.getElementById('br-table-search');

  if (dropzone && fileInput) {
    dropzone.addEventListener('click', () => fileInput.click());

    dropzone.addEventListener('dragover', (e) => {
      e.preventDefault();
      dropzone.classList.add('dragover');
    });

    dropzone.addEventListener('dragleave', () => {
      dropzone.classList.remove('dragover');
    });

    dropzone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropzone.classList.remove('dragover');
      const file = e.dataTransfer?.files?.[0];
      if (!file) return;
      fileInput.files = e.dataTransfer.files;
      document.getElementById('btn-restore').disabled = false;
      updateRestoreSelectedFileLabel(file);
    });
  }

  if (tableSearch) {
    tableSearch.addEventListener('input', function () {
      const q = (this.value || '').trim().toLowerCase();
      document.querySelectorAll('#br-tables-table tbody tr[data-table-name]').forEach(row => {
        row.classList.toggle('d-none', q !== '' && !row.dataset.tableName.includes(q));
      });
    });
  }
}

function updateRestoreSelectedFileLabel(file) {
  const el = document.getElementById('restore-selected-file');
  if (!el) return;
  if (!file) {
    el.classList.add('d-none');
    el.textContent = '';
    return;
  }
  el.classList.remove('d-none');
  el.textContent = file.name;
}

function collectSchedulePayload() {
  return {
    enabled: document.getElementById('schedule-enabled')?.checked ?? false,
    frequency: document.getElementById('schedule-frequency')?.value || 'daily',
    time: document.getElementById('schedule-time')?.value || '02:00',
    weekday: Number(document.getElementById('schedule-weekday')?.value || 1),
    storage_type: document.getElementById('schedule-storage')?.value || 'local',
    backup_scope: document.getElementById('schedule-scope')?.value || 'full',
  };
}

function isScheduleActive(data) {
  return !!(data?.installed);
}

function updateScheduleStoragePath() {
  const storage = document.getElementById('schedule-storage')?.value || 'local';
  const pathEl = document.getElementById('schedule-storage-path');
  if (!pathEl) return;

  if (storage === 'cloud') {
    pathEl.textContent = SCHEDULE_BOOT.cloud_storage_label || 'Google Drive';
    return;
  }

  const dir = (SCHEDULE_BOOT.local_backup_dir || 'storage/app').replace(/\//g, '\\');
  const pattern = SCHEDULE_BOOT.local_backup_pattern || 'backup_*.tar.gz';
  pathEl.textContent = dir + '\\' + pattern.replace('*.tar.gz', 'YYYYMMDD_HHMMSS.tar.gz');
}

function updateSchedulePreview() {
  const payload = collectSchedulePayload();
  const os = SCHEDULE_BOOT.os || 'unknown';
  const previewEl = document.getElementById('schedule-preview-text');
  const weekdayWrap = document.getElementById('schedule-weekday-wrap');
  if (!previewEl) return;

  updateScheduleStoragePath();

  if (weekdayWrap) {
    weekdayWrap.classList.toggle('d-none', payload.frequency !== 'weekly');
  }

  const timeLabel = payload.time;
  let scheduleLabel = 'Setiap hari pukul ' + timeLabel;
  if (payload.frequency === 'hourly') {
    const minute = (timeLabel.split(':')[1] || '00');
    scheduleLabel = 'Setiap jam pada menit ke-' + minute;
  } else if (payload.frequency === 'weekly') {
    const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    scheduleLabel = 'Setiap ' + (days[payload.weekday] || 'Senin') + ' pukul ' + timeLabel;
  }

  if (os === 'windows') {
    previewEl.textContent = 'Task Scheduler: Absensi_DatabaseBackup — ' + scheduleLabel;
  } else if (os === 'linux') {
    const [h, m] = timeLabel.split(':').map(v => parseInt(v, 10) || 0);
    let expr = m + ' ' + h + ' * * *';
    if (payload.frequency === 'hourly') expr = m + ' * * * *';
    if (payload.frequency === 'weekly') expr = m + ' ' + h + ' * * ' + payload.weekday;
    previewEl.textContent = expr + ' storage/app/run-scheduled-backup.sh # absensi-scheduled-backup';
  } else {
    previewEl.textContent = 'OS tidak didukung untuk auto-install scheduler.';
  }
}

function setScheduleStatusAlert(type, message) {
  const el = document.getElementById('schedule-status-alert');
  if (!el) return;
  el.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-info', 'alert-warning');
  el.classList.add(type === 'success' ? 'alert-success' : type === 'danger' ? 'alert-danger' : type === 'warning' ? 'alert-warning' : 'alert-info');
  el.textContent = message;
}

function updateScheduleStatusUI(data) {
  const active = isScheduleActive(data);
  const config = data?.config || {};
  const badge = document.getElementById('schedule-installed-badge');
  if (badge) {
    badge.textContent = active ? 'Aktif' : 'Nonaktif';
    badge.classList.toggle('bg-success', active);
    badge.classList.toggle('bg-secondary', !active);
  }

  const enabled = document.getElementById('schedule-enabled');
  if (enabled) {
    enabled.checked = active;
    enabled.setAttribute('aria-checked', active ? 'true' : 'false');
  }

  const lastRun = document.getElementById('schedule-last-run-at');
  const lastStatus = document.getElementById('schedule-last-status');
  const lastFile = document.getElementById('schedule-last-file');
  if (lastRun) lastRun.textContent = config.last_run_at ? new Date(config.last_run_at).toLocaleString('id-ID') : '-';
  if (lastStatus) lastStatus.textContent = config.last_status || '-';
  if (lastFile) lastFile.textContent = config.last_file || '-';

  if (data) {
    SCHEDULE_BOOT.installed = active;
    if (data.preview) SCHEDULE_BOOT.preview = data.preview;
    if (data.os) SCHEDULE_BOOT.os = data.os;
    if (data.local_backup_dir) SCHEDULE_BOOT.local_backup_dir = data.local_backup_dir;
    if (data.local_backup_pattern) SCHEDULE_BOOT.local_backup_pattern = data.local_backup_pattern;
    if (data.cloud_storage_label) SCHEDULE_BOOT.cloud_storage_label = data.cloud_storage_label;
  }
  updateSchedulePreview();
}

function initScheduledBackupUI() {
  const frequency = document.getElementById('schedule-frequency');
  const time = document.getElementById('schedule-time');
  const weekday = document.getElementById('schedule-weekday');
  const storage = document.getElementById('schedule-storage');
  const scope = document.getElementById('schedule-scope');
  const enabled = document.getElementById('schedule-enabled');
  const applyBtn = document.getElementById('btn-schedule-apply');
  const disableBtn = document.getElementById('btn-schedule-disable');

  [frequency, time, weekday, storage, scope].forEach(el => {
    if (el) el.addEventListener('change', updateSchedulePreview);
  });

  if (enabled) {
    enabled.addEventListener('change', () => {
      const pending = enabled.checked;
      const badge = document.getElementById('schedule-installed-badge');
      if (badge && !isScheduleActive(SCHEDULE_BOOT)) {
        badge.textContent = pending ? 'Belum diterapkan' : 'Nonaktif';
        badge.classList.toggle('bg-success', false);
        badge.classList.toggle('bg-warning', pending);
        badge.classList.toggle('bg-secondary', !pending);
      }
      updateSchedulePreview();
    });
  }

  updateScheduleStatusUI(SCHEDULE_BOOT);

  if (applyBtn) {
    applyBtn.addEventListener('click', async () => {
      const payload = collectSchedulePayload();
      applyBtn.disabled = true;
      setScheduleStatusAlert('info', 'Menerapkan scheduled backup...');

      try {
        const res = await fetch(URL_BACKUP_SCHEDULE_APPLY, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (!res.ok || data.success === false) {
          setScheduleStatusAlert('danger', data.error || 'Gagal menerapkan scheduled backup.');
          if (data.data) updateScheduleStatusUI(data.data);
          return;
        }

        setScheduleStatusAlert('success', data.message || 'Scheduled backup berhasil diterapkan.');
        if (data.data) updateScheduleStatusUI(data.data);
      } catch (e) {
        setScheduleStatusAlert('danger', 'Tidak dapat menghubungi server.');
      } finally {
        applyBtn.disabled = false;
      }
    });
  }

  if (disableBtn) {
    disableBtn.addEventListener('click', async () => {
      disableBtn.disabled = true;
      setScheduleStatusAlert('info', 'Menonaktifkan scheduled backup...');

      try {
        const res = await fetch(URL_BACKUP_SCHEDULE_DISABLE, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({}),
        });
        const data = await res.json();

        if (!res.ok || data.success === false) {
          setScheduleStatusAlert('danger', data.error || 'Gagal menonaktifkan scheduled backup.');
          return;
        }

        if (enabled) enabled.checked = false;
        setScheduleStatusAlert('success', data.message || 'Scheduled backup dinonaktifkan.');
        if (data.data) updateScheduleStatusUI(data.data);
      } catch (e) {
        setScheduleStatusAlert('danger', 'Tidak dapat menghubungi server.');
      } finally {
        disableBtn.disabled = false;
      }
    });
  }
}

// â”€â”€ CLEAR TABLES â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
      feather.replace();
      return;
    }

    confirmBtn.innerHTML = 'Berhasil! Memuat ulang...';
    setTimeout(() => window.location.reload(), 1200);
  } catch (e) {
    errEl.textContent = 'Tidak dapat menghubungi server.';
    errEl.classList.remove('d-none');
    confirmBtn.disabled = false;
    confirmBtn.innerHTML = originalHtml;
    feather.replace();
  }
}

// â”€â”€ BACKUP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

// â”€â”€ RESTORE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

  feather.replace();
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

  openProgressModal('Menyiapkan Restore', 'search', '#198754');
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

  openProgressModal('Menyiapkan Restore', 'search', '#198754');
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
  openProgressModal(mode === 'table' ? 'Restore Tabel' : 'Restore Database', 'upload-cloud', '#198754');
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

// â”€â”€ POLLING â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

// â”€â”€ MODAL HELPERS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
  feather.replace();

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

  if (bsProgress) {
    bsProgress.show();
    return;
  }

  alert('Progress modal tidak tersedia. Pastikan Bootstrap JS termuat, lalu refresh halaman.');
}

function updateProgress(pct, msg) {
  const bar = document.getElementById('progress-bar');
  const p   = Math.max(0, Math.min(100, pct));
  bar.style.width         = p + '%';
  bar.setAttribute('aria-valuenow', p);
  bar.textContent         = p + '%';
  document.getElementById('progress-message').textContent = msg || '';

  if (migrationIsRunning) {
    updateInlineMigrationProgress(p, msg);
    if (msg) appendMigrationLog(msg);
  }

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

  if (migrationIsRunning) {
    migrationIsRunning = false;
    stopMigrationInlineTimer();
    updateInlineMigrationProgress(100, msg || 'Migrasi selesai!');
    showMigrationStep(4);
    const summary = document.getElementById('migration-done-summary');
    if (summary) summary.textContent = msg || 'Semua tabel berhasil dimigrasikan ke destination server.';
    feather.replace();
  }
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
  migrationIsRunning = true;
  showMigrationStep(3);
  startMigrationInlineTimer();
  const logs = document.getElementById('migration-inline-logs');
  if (logs) logs.innerHTML = '<div class="dm-log-line text-muted">Memulai migrasi...</div>';
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
      setProgressDone('Migration selesai! Semua tabel berhasil dimigrasikan.');
      setTimeout(() => {
        bsProgress.hide();
      }, 1500);
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
  errEl.textContent = 'âš  ' + msg;
  errEl.classList.remove('d-none');
  toggleProgressDownload({}, null);
  document.getElementById('progress-modal-close').classList.remove('d-none');
  const bar = document.getElementById('progress-bar');
  bar.classList.remove('progress-bar-animated', 'bg-success');
  bar.classList.add('bg-danger');
  setProgressLiveStatus('Terjadi kendala');
  stopProgressTimers();

  if (migrationIsRunning) {
    migrationIsRunning = false;
    stopMigrationInlineTimer();
    updateInlineMigrationProgress(0, 'Migrasi gagal: ' + msg);
    appendMigrationLog('ERROR: ' + msg);
    setMigrationExecutionStatus('danger', msg);
  }
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

async function loadSourceConfig(triggerButton) {
  const button = triggerButton || document.getElementById('btn-load-source-config');
  if (!button) return;

  const originalLabel = button.innerHTML;
  button.disabled = true;
  button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

  setVerifiedBadge('source', false);
  renderSourceTestResult('info', 'Mengambil konfigurasi dari .env...');

  try {
    const response = await fetch(URL_MIGRATION_SOURCE_LOAD_CONFIG, {
      headers: {
        'Accept': 'application/json'
      }
    });

    const data = await response.json();
    if (!response.ok || data.success === false || !data.config) {
      renderSourceTestResult('danger', '<i data-feather="x-circle" style="width:13px;height:13px"></i> ' + escapeHtml(data.error || 'Gagal mengambil konfigurasi Source Server dari .env.'));
      feather.replace();
      return;
    }

    applySourceConfigToForm(data.config);
    const sourceLabel = data.source_label || 'DB_*';
    renderSourceTestResult('success', '<i data-feather="check-circle" style="width:13px;height:13px"></i> Data berhasil diambil dari .env (' + escapeHtml(sourceLabel) + ').');
    feather.replace();
  } catch (error) {
    renderSourceTestResult('danger', 'Tidak dapat menghubungi server saat mengambil konfigurasi source.');
  } finally {
    button.disabled = false;
    button.innerHTML = originalLabel;
    feather.replace();
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

async function loadDestinationConfig(triggerButton) {
  const button = triggerButton || document.getElementById('btn-load-destination-config');
  if (!button) return;

  const originalLabel = button.innerHTML;
  button.disabled = true;
  button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

  setVerifiedBadge('destination', false);
  renderDestinationTestResult('info', 'Mengambil konfigurasi dari .env...');

  try {
    const response = await fetch(URL_MIGRATION_DESTINATION_LOAD_CONFIG, {
      headers: {
        'Accept': 'application/json'
      }
    });

    const data = await response.json();
    if (!response.ok || data.success === false || !data.config) {
      renderDestinationTestResult('danger', '<i data-feather="x-circle" style="width:13px;height:13px"></i> ' + escapeHtml(data.error || 'Gagal mengambil konfigurasi Destination Server dari .env.'));
      feather.replace();
      return;
    }

    applyDestinationConfigToForm(data.config);
    const sourceLabel = data.source_label || 'MIGRATION_DESTINATION_DB_*';
    renderDestinationTestResult('success', '<i data-feather="check-circle" style="width:13px;height:13px"></i> Data berhasil diambil dari .env (' + escapeHtml(sourceLabel) + ').');
    feather.replace();
  } catch (error) {
    renderDestinationTestResult('danger', 'Tidak dapat menghubungi server saat mengambil konfigurasi destination.');
  } finally {
    button.disabled = false;
    button.innerHTML = originalLabel;
    feather.replace();
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
      renderSourceTestResult('danger', '<i data-feather="x-circle" style="width:13px;height:13px"></i> ' + escapeHtml(data.error || 'Gagal konek ke source server.'));
      setVerifiedBadge('source', false);
      updateAnalysisCard('source', { failed: true });
      feather.replace();
      return;
    }

    updateAnalysisCard('source', data);
    if (data.table_list) renderMigrationTableList(data.table_list);
    setVerifiedBadge('source', true);
    setDmStepperStep(2);
    renderSourceTestResult(
      'success',
      '<i data-feather="check-circle" style="width:13px;height:13px"></i> Koneksi berhasil!'
    );
    feather.replace();
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

  el.classList.remove('d-none', 'success', 'danger', 'warning', 'info');
  el.classList.add(type === 'success' ? 'success' : type === 'warning' ? 'warning' : type === 'danger' ? 'danger' : 'info');
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
      renderDestinationTestResult('danger', '<i data-feather="x-circle" style="width:13px;height:13px"></i> ' + escapeHtml(data.error || 'Gagal konek ke destination server.'));
      setVerifiedBadge('destination', false);
      updateAnalysisCard('destination', { failed: true });
      feather.replace();
      return;
    }

    const statusLabel = data.database_status_label || (data.database_empty ? 'Database Empty' : 'Database sudah berisi data');
    const resultType = data.database_empty ? 'success' : 'warning';
    const showClearButton = !data.database_empty && (data.driver || '').toUpperCase() === 'PGSQL';

    updateAnalysisCard('destination', data);
    setVerifiedBadge('destination', true);
    renderDestinationTestResult(
      resultType,
      '<i data-feather="check-circle" style="width:13px;height:13px"></i> Koneksi berhasil!',
      showClearButton
    );
    feather.replace();
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

  el.classList.remove('d-none', 'success', 'danger', 'warning', 'info');
  el.classList.add(type === 'success' ? 'success' : type === 'warning' ? 'warning' : type === 'danger' ? 'danger' : 'info');

  const clearButtonHtml = showClearButton
    ? '<div class="mt-2 w-100"><button id="btn-clear-destination-data" type="button" class="btn btn-danger btn-sm">Hapus Data Tujuan</button></div>'
    : '';

  el.innerHTML = '<div class="d-flex align-items-center flex-wrap gap-2">' + message + clearButtonHtml + '</div>';

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
    feather.replace();
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
  if (migrationIsRunning && meta.current_table) {
    let statusText = 'Memigrasi tabel: ' + meta.current_table;
    if (typeof meta.table_rows_total !== 'undefined' && Number(meta.table_rows_total) > 0) {
      statusText += ' (' + (meta.table_rows_processed ?? 0) + ' / ' + meta.table_rows_total + ' rows)';
    }
    const statusEl = document.getElementById('migration-inline-status');
    if (statusEl) statusEl.textContent = statusText;
  }
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
</script>    @endpush
</x-app-layout>
