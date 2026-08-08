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
      .db-tools-scope .btn-primary { background-color:#111827; border-color:#111827; }
.gdrive-upload-area {
    border: 2px dashed #dee2e6;
    border-radius: 10px;
    padding: 32px 20px;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    position: relative;
    background: #fafbfc;
  }
  .gdrive-upload-area:hover,
  .gdrive-upload-area.dragover {
    border-color: #0d6efd;
    background: #f0f5ff;
  }
  .gdrive-upload-area input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    width: 100%;
    height: 100%;
  }
  .gdrive-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(13,110,253,.1);
    border-radius: 12px;
    color: #0d6efd;
  }
  .gdrive-file-row td { vertical-align: middle; font-size: .855rem; }
  .gdrive-status-badge { font-size: .72rem; font-weight: 600; }
  .config-alert { border-left: 4px solid #fd7e14; }
  #uploadProgressWrap { display: none; }

  /* Checklist modal */
  .check-item { display: flex; align-items: flex-start; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
  .check-item:last-child { border-bottom: none; }
  .check-icon { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 11px; font-weight: 700; margin-top: 1px; }
  .check-icon.pass  { background: #d1fae5; color: #065f46; }
  .check-icon.fail  { background: #fee2e2; color: #991b1b; }
  .check-icon.info  { background: #fef3c7; color: #92400e; }
  .check-label { font-size: .875rem; font-weight: 600; margin-bottom: 2px; }
  .check-detail { font-size: .78rem; color: #6c757d; margin: 0; line-height: 1.5; }
  .check-detail code { background: #f3f4f6; padding: 1px 4px; border-radius: 3px; font-size: .75rem; }
  .config-score { font-size: 1.1rem; font-weight: 700; }

    </style>
    <div class="h-[calc(100vh-8rem)] flex flex-col">
        <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-3">
            <div class="bg-white shadow-sm rounded-lg overflow-hidden flex-1 flex flex-col min-h-0">
                @include('settings._settings-tabs')
                <div class="db-tools-scope flex-1 min-h-0 overflow-y-auto p-4 sm:p-6">
<nav class="page-breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('tools.database') }}">Tools</a></li>
    <li class="breadcrumb-item active">Google Drive Storage</li>
  </ol>
</nav>

{{-- ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ MODAL CEK KONFIGURASI ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ --}}
<div class="modal fade" id="configCheckModal" tabindex="-1" aria-labelledby="configCheckModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold" id="configCheckModalLabel">
          <i data-feather="check-square" style="width:16px;height:16px;margin-right:6px;"></i>
          Cek Konfigurasi Google Drive
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        {{-- Loading state --}}
        <div id="checkLoading" class="text-center py-4">
          <div class="spinner-border text-primary mb-3" style="width:2rem;height:2rem;"></div>
          <div class="text-muted">Memeriksa konfigurasi...</div>
          <small class="text-muted">Tes koneksi live mungkin memerlukan beberapa detik</small>
        </div>

        {{-- Result --}}
        <div id="checkResult" style="display:none;">
          {{-- Score bar --}}
          <div class="d-flex align-items-center justify-content-between mb-3 p-3 rounded" id="scoreBar" style="background:#f8f9fa;">
            <div>
              <div class="config-score" id="scoreText">-</div>
              <div class="text-muted" style="font-size:.78rem;">item konfigurasi terpenuhi</div>
            </div>
            <div id="scoreIcon" style="font-size:2rem;"></div>
          </div>

          {{-- Checklist items --}}
          <div id="checkItems"></div>
        </div>

        {{-- Error state --}}
        <div id="checkError" style="display:none;" class="alert alert-danger py-2 mb-0"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecheck">
          <i data-feather="refresh-cw" style="width:13px;height:13px;margin-right:4px;"></i>Cek Ulang
        </button>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

@if(session('gdrive_error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
  <i data-feather="alert-circle" style="width:16px;height:16px;margin-right:6px;"></i>
  {{ session('gdrive_error') }}
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if(session('gdrive_success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <i data-feather="check-circle" style="width:16px;height:16px;margin-right:6px;"></i>
  {{ session('gdrive_success') }}
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- OAuth Connection Banner --}}
@if($configured)
@if($oauthConnected)
<div class="alert alert-success d-flex align-items-center justify-content-between py-2 mb-3" role="alert">
  <div>
    <i data-feather="check-circle" style="width:15px;height:15px;margin-right:6px;"></i>
    <strong>Google Account Terhubung</strong> ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Upload siap digunakan via OAuth2.
  </div>
  <form method="POST" action="{{ route('tools.google-drive.oauth.disconnect') }}" class="mb-0">
    @csrf
    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirmSubmit(event, 'Putus koneksi Google Account?')">
      <i data-feather="log-out" style="width:13px;height:13px;margin-right:3px;"></i>Putus Koneksi
    </button>
  </form>
</div>
@else
<div class="alert alert-warning d-flex align-items-center justify-content-between py-2 mb-3" role="alert">
  <div>
    <i data-feather="alert-triangle" style="width:15px;height:15px;margin-right:6px;"></i>
    <strong>Google Account Belum Dihubungkan</strong> ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â Upload memerlukan koneksi OAuth2 (bukan Service Account).
  </div>
  <a href="{{ route('tools.google-drive.oauth') }}" class="btn btn-sm btn-warning">
    <i data-feather="link" style="width:13px;height:13px;margin-right:3px;"></i>Hubungkan Google Account
  </a>
</div>
@endif
@endif

@if(!$configured)
{{-- ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ BELUM DIKONFIGURASI ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ --}}
<div class="row">
  <div class="col-12">
    <div class="card config-alert">
      <div class="card-body">
        <h6 class="card-title mb-3">
          <i data-feather="alert-triangle" style="width:18px;height:18px;color:#fd7e14;margin-right:6px;"></i>
          Google Drive Belum Dikonfigurasi
        </h6>
        <p class="text-muted mb-3">Ikuti langkah-langkah berikut untuk mengaktifkan Google Drive Storage:</p>

        <ol class="mb-4" style="line-height: 2;">
          <li>Buka <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a> ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ buat project baru</li>
          <li>Aktifkan <strong>Google Drive API</strong> (APIs &amp; Services ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ Enable APIs)</li>
          <li>Buat <strong>Service Account</strong> (IAM &amp; Admin ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ Service Accounts ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ Create)</li>
          <li>Download <strong>credentials JSON</strong> dari service account</li>
          <li>Simpan file tersebut sebagai <code>storage/app/google-credentials.json</code></li>
          <li>Di Google Drive: buat folder khusus ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ klik kanan ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ Share ke email service account (<em>xxx@project.iam.gserviceaccount.com</em>) dengan permission <strong>Editor</strong></li>
          <li>Catat <strong>Folder ID</strong> dari URL folder: <code>drive.google.com/drive/folders/<strong>[FOLDER_ID]</strong></code></li>
          <li>Tambahkan ke file <code>.env</code>:
            <pre class="bg-light p-2 rounded mt-1"><code>GOOGLE_DRIVE_CREDENTIALS=storage/app/google-credentials.json
GOOGLE_DRIVE_FOLDER_ID=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx</code></pre>
          </li>
          <li>Jalankan: <code>composer require masbug/flysystem-google-drive-ext</code></li>
          <li>Refresh halaman ini</li>
        </ol>

        <a href="{{ route('tools.database') }}" class="btn btn-sm btn-outline-secondary">
          <i data-feather="arrow-left" style="width:14px;height:14px;"></i> Kembali ke Tools
        </a>
        <button class="btn btn-sm btn-warning ms-2" id="btnCheckConfig" data-bs-toggle="modal" data-bs-target="#configCheckModal">
          <i data-feather="check-square" style="width:14px;height:14px;margin-right:4px;"></i>Cek Konfigurasi
        </button>
      </div>
    </div>
  </div>
</div>

@else
{{-- ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ SUDAH DIKONFIGURASI ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ --}}
<div class="row g-3">

  {{-- Upload Card --}}
  <div class="col-12 col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="card-title mb-1">Upload File</h6>
        <p class="text-muted mb-3" style="font-size:.82rem">Klik atau drag &amp; drop file ke area di bawah.</p>

        <div class="gdrive-upload-area" id="uploadArea">
          <input type="file" id="fileInput" name="file">
          <div class="gdrive-icon">
            <i data-feather="upload-cloud"></i>
          </div>
          <div id="uploadAreaText">
            <div class="fw-semibold mb-1" style="font-size:.9rem">Pilih atau Seret File</div>
            <div class="text-muted" style="font-size:.78rem">Semua tipe file, maks. 200 MB</div>
          </div>
          <div id="selectedFileName" class="text-primary fw-semibold mt-2" style="font-size:.85rem;display:none;"></div>
        </div>

        <div id="uploadProgressWrap" class="mt-3">
          <div class="d-flex justify-content-between mb-1" style="font-size:.78rem">
            <span id="uploadStatusText">Mengupload...</span>
            <span id="uploadPct">0%</span>
          </div>
          <div class="progress" style="height:6px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated" id="uploadProgressBar" style="width:0%"></div>
          </div>
        </div>

        <div class="mt-3 d-flex gap-2">
          <button class="btn btn-primary btn-sm flex-fill" id="btnUpload" disabled>
            <i data-feather="upload" style="width:14px;height:14px;margin-right:4px;"></i>Upload
          </button>
          <button class="btn btn-outline-secondary btn-sm" id="btnClearFile" style="display:none;">
            <i data-feather="x" style="width:14px;height:14px;"></i>
          </button>
        </div>

        <div id="uploadAlert" class="mt-2" style="font-size:.82rem;"></div>
      </div>
    </div>
  </div>

  {{-- File List Card --}}
  <div class="col-12 col-lg-8">
    <div class="card h-100">
      <div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h6 class="card-title mb-0">File di Google Drive</h6>
            <small class="text-muted">Folder tujuan upload</small>
          </div>
          <button class="btn btn-sm btn-outline-secondary" id="btnRefresh" title="Refresh daftar">
            <i data-feather="refresh-cw" style="width:14px;height:14px;"></i>
          </button>
          <button class="btn btn-sm btn-outline-warning ms-1" data-bs-toggle="modal" data-bs-target="#configCheckModal" title="Cek Konfigurasi">
            <i data-feather="check-square" style="width:14px;height:14px;"></i>
          </button>
        </div>

        <div id="fileListWrap" class="flex-fill" style="overflow-x:auto;">
          <table class="table table-sm table-hover align-middle mb-0" id="fileTable">
            <thead class="table-light">
              <tr>
                <th style="width:40px">#</th>
                <th>Nama File</th>
                <th style="width:90px">Ukuran</th>
                <th style="width:130px">Tanggal</th>
                <th style="width:70px" class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody id="fileListBody">
              @if(count($files) === 0)
              <tr id="emptyRow">
                <td colspan="5" class="text-center text-muted py-4">
                  <i data-feather="folder" style="width:24px;height:24px;display:block;margin:0 auto 8px;"></i>
                  Belum ada file di Drive
                </td>
              </tr>
              @else
              @foreach($files as $i => $f)
              <tr class="gdrive-file-row" data-filename="{{ $f['filename'] }}" data-fileid="{{ $f['file_id'] }}">
                <td class="text-muted">{{ $i + 1 }}</td>
                <td>
                  <i data-feather="file" style="width:13px;height:13px;margin-right:4px;color:#6c757d;"></i>
                  {{ $f['filename'] }}
                </td>
                <td class="text-muted">{{ $f['size_human'] }}</td>
                <td class="text-muted">{{ $f['modified'] }}</td>
                <td class="text-center">
                  <button class="btn btn-sm btn-outline-danger btn-hapus py-0 px-2" style="font-size:.75rem;" title="Hapus">
                    <i data-feather="trash-2" style="width:12px;height:12px;"></i>
                  </button>
                </td>
              </tr>
              @endforeach
              @endif
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>
@endif
    </div></div></div></div>
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<script>
if (window.feather) { feather.replace(); }

const CSRF = '{{ csrf_token() }}';

@if($configured)
const UPLOAD_URL  = '{{ route("tools.google-drive.upload") }}';
const FILES_URL   = '{{ route("tools.google-drive.files") }}';
const DELETE_URL  = '{{ route("tools.google-drive.delete") }}';

// ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ FILE PICKER ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
const fileInput      = document.getElementById('fileInput');
const uploadArea     = document.getElementById('uploadArea');
const selectedName   = document.getElementById('selectedFileName');
const uploadAreaText = document.getElementById('uploadAreaText');
const btnUpload      = document.getElementById('btnUpload');
const btnClearFile   = document.getElementById('btnClearFile');
const uploadAlert    = document.getElementById('uploadAlert');

fileInput.addEventListener('change', () => handleFileSelected(fileInput.files[0]));

uploadArea.addEventListener('dragover', e => { e.preventDefault(); uploadArea.classList.add('dragover'); });
uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
uploadArea.addEventListener('drop', e => {
  e.preventDefault();
  uploadArea.classList.remove('dragover');
  if (e.dataTransfer.files.length) handleFileSelected(e.dataTransfer.files[0]);
});

function handleFileSelected(file) {
  if (!file) return;
  uploadAreaText.style.display = 'none';
  selectedName.style.display   = 'block';
  selectedName.textContent     = 'ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã¢â‚¬Å¾ ' + file.name + ' (' + formatBytes(file.size) + ')';
  btnUpload.disabled            = false;
  btnClearFile.style.display    = 'inline-flex';
  uploadAlert.innerHTML         = '';
  if (window.feather) { feather.replace(); }
}

btnClearFile.addEventListener('click', () => {
  fileInput.value              = '';
  uploadAreaText.style.display = 'block';
  selectedName.style.display   = 'none';
  selectedName.textContent     = '';
  btnUpload.disabled            = true;
  btnClearFile.style.display    = 'none';
  uploadAlert.innerHTML         = '';
});

// ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ UPLOAD ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
btnUpload.addEventListener('click', () => {
  const file = fileInput.files[0];
  if (!file) return;

  const fd   = new FormData();
  fd.append('_token', CSRF);
  fd.append('file', file);

  const progressWrap = document.getElementById('uploadProgressWrap');
  const progressBar  = document.getElementById('uploadProgressBar');
  const pctLabel     = document.getElementById('uploadPct');
  const statusText   = document.getElementById('uploadStatusText');

  progressWrap.style.display = 'block';
  btnUpload.disabled          = true;
  btnClearFile.style.display  = 'none';
  uploadAlert.innerHTML       = '';

  const xhr = new XMLHttpRequest();
  xhr.open('POST', UPLOAD_URL);

  xhr.upload.addEventListener('progress', e => {
    if (e.lengthComputable) {
      const pct = Math.round((e.loaded / e.total) * 90);
      progressBar.style.width = pct + '%';
      pctLabel.textContent    = pct + '%';
    }
  });

  xhr.addEventListener('load', () => {
    progressBar.style.width = '100%';
    pctLabel.textContent    = '100%';
    progressBar.classList.remove('progress-bar-animated');

    const res = JSON.parse(xhr.responseText);
    if (xhr.status === 200 && res.success) {
      statusText.textContent  = 'Upload selesai!';
      progressBar.classList.replace('bg-primary', 'bg-success');
      uploadAlert.innerHTML   = '<div class="alert alert-success py-2 mb-0">ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ ' + res.message + '</div>';
      btnClearFile.click();
      setTimeout(() => { progressWrap.style.display = 'none'; }, 2000);
      refreshFiles();
    } else {
      statusText.textContent  = 'Upload gagal.';
      progressBar.classList.replace('bg-primary', 'bg-danger');
      uploadAlert.innerHTML   = '<div class="alert alert-danger py-2 mb-0">ÃƒÂ¢Ã‚ÂÃ…â€™ ' + (res.error || 'Terjadi kesalahan.') + '</div>';
      btnUpload.disabled       = false;
    }
  });

  xhr.addEventListener('error', () => {
    uploadAlert.innerHTML = '<div class="alert alert-danger py-2 mb-0">ÃƒÂ¢Ã‚ÂÃ…â€™ Koneksi gagal.</div>';
    btnUpload.disabled     = false;
  });

  xhr.send(fd);
});

// ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ REFRESH FILES ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
document.getElementById('btnRefresh').addEventListener('click', refreshFiles);

function refreshFiles() {
  const tbody = document.getElementById('fileListBody');
  tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-2"></span>Memuat...</td></tr>';

  fetch(FILES_URL)
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">ÃƒÂ¢Ã‚ÂÃ…â€™ ' + data.error + '</td></tr>';
        return;
      }
      if (!data.length) {
        tbody.innerHTML = '<tr id="emptyRow"><td colspan="5" class="text-center text-muted py-4">Belum ada file di Drive</td></tr>';
        if (window.feather) { feather.replace(); } return;
      }
      let html = '';
      data.forEach((f, i) => {
        html += `<tr class="gdrive-file-row" data-filename="${escHtml(f.filename)}" data-fileid="${escHtml(f.file_id)}">
          <td class="text-muted">${i+1}</td>
          <td><i data-feather="file" style="width:13px;height:13px;margin-right:4px;color:#6c757d;"></i>${escHtml(f.filename)}</td>
          <td class="text-muted">${f.size_human}</td>
          <td class="text-muted">${f.modified}</td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-danger btn-hapus py-0 px-2" style="font-size:.75rem;" title="Hapus">
              <i data-feather="trash-2" style="width:12px;height:12px;"></i>
            </button>
          </td>
        </tr>`;
      });
      tbody.innerHTML = html;
      if (window.feather) { feather.replace(); }
      bindDeleteButtons();
    })
    .catch(() => {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">ÃƒÂ¢Ã‚ÂÃ…â€™ Gagal memuat daftar.</td></tr>';
    });
}

// ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ DELETE ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
function bindDeleteButtons() {
  document.querySelectorAll('.btn-hapus').forEach(btn => {
    btn.addEventListener('click', async function() {
      const row      = this.closest('tr');
      const filename = row.dataset.filename;
      const fileId   = row.dataset.fileid;
      if (! await window.appConfirm('Hapus file "' + filename + '" dari Google Drive?')) return;

      btn.disabled  = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

      fetch(DELETE_URL, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ file_id: fileId, filename }),
      })
      .then(r => r.json())
      .then(async res => {
        if (res.success) {
          row.remove();
          const tbody = document.getElementById('fileListBody');
          if (!tbody.querySelector('tr')) {
            tbody.innerHTML = '<tr id="emptyRow"><td colspan="5" class="text-center text-muted py-4">Belum ada file di Drive</td></tr>';
            if (window.feather) { feather.replace(); }
          } else {
            tbody.querySelectorAll('tr').forEach((r, i) => {
              const td = r.querySelector('td');
              if (td) td.textContent = i + 1;
            });
          }
        } else {
          await window.appAlert('Gagal menghapus: ' + (res.error || ''), { danger: true });
          btn.disabled = false;
          if (window.feather) { feather.replace(); }
        }
      })
      .catch(async () => {
        await window.appAlert('Koneksi gagal.', { danger: true });
        btn.disabled = false;
        if (window.feather) { feather.replace(); }
      });
    });
  });
}

// Bind on page load
bindDeleteButtons();

// ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ UTILS ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬
function formatBytes(bytes) {
  if (bytes < 1024)       return bytes + ' B';
  if (bytes < 1048576)    return (bytes/1024).toFixed(1) + ' KB';
  if (bytes < 1073741824) return (bytes/1048576).toFixed(1) + ' MB';
  return (bytes/1073741824).toFixed(2) + ' GB';
}
function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
@endif

const CHECK_URL       = '{{ route("tools.google-drive.check-config") }}';
const UPLOAD_CRED_URL = '{{ route("tools.google-drive.upload-credentials") }}';

const configModal = document.getElementById('configCheckModal');
configModal.addEventListener('show.bs.modal', () => runConfigCheck());
document.getElementById('btnRecheck').addEventListener('click', runConfigCheck);

function runConfigCheck() {
  document.getElementById('checkLoading').style.display = 'block';
  document.getElementById('checkResult').style.display  = 'none';
  document.getElementById('checkError').style.display   = 'none';

  fetch(CHECK_URL)
    .then(r => r.json())
    .then(data => {
      document.getElementById('checkLoading').style.display = 'none';
      document.getElementById('checkResult').style.display  = 'block';

      // Score bar
      const scoreBar  = document.getElementById('scoreBar');
      const scoreText = document.getElementById('scoreText');
      const scoreIcon = document.getElementById('scoreIcon');
      scoreText.textContent = data.total_pass + ' / ' + data.total_check;
      if (data.all_pass) {
        scoreBar.style.background = '#d1fae5';
        scoreIcon.textContent = 'ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦';
      } else if (data.total_pass === 0) {
        scoreBar.style.background = '#fee2e2';
        scoreIcon.textContent = 'ÃƒÂ¢Ã‚ÂÃ…â€™';
      } else {
        scoreBar.style.background = '#fef3c7';
        scoreIcon.textContent = 'ÃƒÂ¢Ã…Â¡Ã‚Â ÃƒÂ¯Ã‚Â¸Ã‚Â';
      }

      // Render items
      let html = '';
      data.checks.forEach((item, idx) => {
        let iconClass, iconChar;
        if (item.pass === null) {
          iconClass = 'info'; iconChar = '!';
        } else if (item.pass) {
          iconClass = 'pass'; iconChar = 'ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“';
        } else {
          iconClass = 'fail'; iconChar = 'ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¢';
        }

        // Upload button for cred_file item
        let extra = '';
        if (item.key === 'cred_file' && item.upload_allowed) {
          extra = `
            <div class="mt-2 d-flex align-items-center gap-2 flex-wrap" id="credUploadArea">
              <label class="btn btn-sm btn-outline-primary mb-0" style="cursor:pointer;">
                <i data-feather="upload" style="width:13px;height:13px;margin-right:4px;"></i>Upload credentials.json
                <input type="file" id="credFileInput" accept=".json,application/json" style="display:none;">
              </label>
              <span id="credFileName" class="text-muted" style="font-size:.78rem;"></span>
              <button class="btn btn-sm btn-success" id="btnSaveCred" style="display:none;">
                <i data-feather="save" style="width:13px;height:13px;margin-right:4px;"></i>Simpan
              </button>
              <span id="credUploadMsg" style="font-size:.78rem;"></span>
            </div>`;
        }
        // OAuth connect button
        if (item.key === 'oauth_connected' && item.oauth_connect) {
          extra = `
            <div class="mt-2">
              <a href="{{ route('tools.google-drive.oauth') }}" class="btn btn-sm btn-warning">
                <i data-feather="link" style="width:13px;height:13px;margin-right:4px;"></i>Hubungkan Google Account
              </a>
            </div>`;
        }

        html += `<div class="check-item">
          <div class="check-icon ${iconClass}">${iconChar}</div>
          <div class="flex-fill">
            <div class="check-label">${idx + 1}. ${item.label}</div>
            ${item.detail ? `<p class="check-detail">${item.detail}</p>` : ''}
            ${extra}
          </div>
        </div>`;
      });
      document.getElementById('checkItems').innerHTML = html;
      if (window.feather) { feather.replace(); }
      bindCredUpload();
    })
    .catch(err => {
      document.getElementById('checkLoading').style.display = 'none';
      document.getElementById('checkError').style.display   = 'block';
      document.getElementById('checkError').textContent     = 'Gagal menjalankan pengecekan: ' + err.message;
    });
}

function bindCredUpload() {
  const credInput = document.getElementById('credFileInput');
  if (!credInput) return;

  credInput.addEventListener('change', () => {
    const f = credInput.files[0];
    if (!f) return;
    document.getElementById('credFileName').textContent = f.name;
    document.getElementById('btnSaveCred').style.display = 'inline-flex';
    if (window.feather) { feather.replace(); }
  });

  document.getElementById('btnSaveCred').addEventListener('click', () => {
    const f = credInput.files[0];
    if (!f) return;

    const fd = new FormData();
    fd.append('_token', CSRF);
    fd.append('credentials_file', f);

    const msgEl  = document.getElementById('credUploadMsg');
    const btnSave = document.getElementById('btnSaveCred');
    btnSave.disabled  = true;
    btnSave.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    msgEl.textContent = '';

    fetch(UPLOAD_CRED_URL, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          msgEl.innerHTML = '<span class="text-success fw-semibold">ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Tersimpan! Service Account: ' + res.service_email + '</span>';
          document.getElementById('credUploadArea').innerHTML = '<span class="text-success fw-semibold" style="font-size:.82rem;">ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Credentials berhasil disimpan. Klik <strong>Cek Ulang</strong> untuk verifikasi.</span>';
        } else {
          msgEl.innerHTML = '<span class="text-danger">' + (res.error || 'Gagal.') + '</span>';
          btnSave.disabled  = false;
          btnSave.innerHTML = '<i data-feather="save" style="width:13px;height:13px;margin-right:4px;"></i>Simpan';
          if (window.feather) { feather.replace(); }
        }
      })
      .catch(() => {
        msgEl.innerHTML   = '<span class="text-danger">Koneksi gagal.</span>';
        btnSave.disabled  = false;
        btnSave.innerHTML = '<i data-feather="save" style="width:13px;height:13px;margin-right:4px;"></i>Simpan';
        if (window.feather) { feather.replace(); }
      });
  });
}
</script>
    @endpush
</x-app-layout>