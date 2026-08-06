<?php

namespace App\Http\Controllers;

use App\Services\GoogleDriveStorageService;
use Illuminate\Http\Request;

class GoogleDriveController extends Controller
{
    private $googleDriveStorage;

    public function __construct(GoogleDriveStorageService $googleDriveStorage)
    {
        $this->googleDriveStorage = $googleDriveStorage;
    }

    // GET /tools/google-drive
    public function index()
    {
        $configured   = $this->isConfigured();
        $oauthConnected = $this->isOAuthConnected();
        $files = [];

        if ($configured) {
            try {
                $files = $this->getFiles();
            } catch (\Throwable $e) {
                $configured = false;
                session()->flash('gdrive_error', 'Gagal terhubung ke Google Drive: ' . $e->getMessage());
            }
        }

        return view('tools.google-drive', compact('configured', 'oauthConnected', 'files'));
    }

    // GET /tools/google-drive/oauth  → redirect to Google consent screen
    public function oauthRedirect()
    {
        $clientId     = env('GOOGLE_OAUTH_CLIENT_ID');
        $clientSecret = env('GOOGLE_OAUTH_CLIENT_SECRET');

        if (empty($clientId) || empty($clientSecret)) {
            return redirect()->route('tools.google-drive.index')
                ->with('gdrive_error', 'GOOGLE_OAUTH_CLIENT_ID dan GOOGLE_OAUTH_CLIENT_SECRET belum diset di .env');
        }

        $client = new \Google\Client();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($this->googleDriveStorage->oauthRedirectUri());
        $client->addScope(\Google\Service\Drive::DRIVE);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent'); // force refresh_token on every auth

        return redirect($client->createAuthUrl());
    }

    // GET /tools/google-drive/oauth/callback
    public function oauthCallback(Request $request)
    {
        if ($request->has('error')) {
            return redirect()->route('tools.google-drive.index')
                ->with('gdrive_error', 'OAuth dibatalkan: ' . $request->get('error'));
        }

        $clientId     = env('GOOGLE_OAUTH_CLIENT_ID');
        $clientSecret = env('GOOGLE_OAUTH_CLIENT_SECRET');

        $client = new \Google\Client();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($this->googleDriveStorage->oauthRedirectUri());

        $token = $client->fetchAccessTokenWithAuthCode($request->get('code'));

        if (isset($token['error'])) {
            return redirect()->route('tools.google-drive.index')
                ->with('gdrive_error', 'Gagal mendapatkan token: ' . ($token['error_description'] ?? $token['error']));
        }

        file_put_contents($this->oauthTokenPath(), json_encode($token));

        return redirect()->route('tools.google-drive.index')
            ->with('gdrive_success', '✅ Google Account berhasil dihubungkan! Upload sekarang siap digunakan.');
    }

    // POST /tools/google-drive/oauth/disconnect
    public function oauthDisconnect()
    {
        $tokenPath = $this->googleDriveStorage->oauthTokenPath();
        if (file_exists($tokenPath)) {
            unlink($tokenPath);
        }

        return redirect()->route('tools.google-drive.index')
            ->with('gdrive_success', 'Google Account berhasil diputus.');
    }

    // POST /tools/google-drive/upload
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:204800', // max 200MB
        ]);

        if (!$this->isConfigured()) {
            return response()->json(['error' => 'Google Drive belum dikonfigurasi.'], 422);
        }

        if (!$this->isOAuthConnected()) {
            return response()->json(['error' => 'Hubungkan Google Account terlebih dahulu untuk bisa upload. Klik tombol "Hubungkan Google Account".'], 422);
        }

        try {
            $file       = $request->file('file');
            $remoteName = date('Ymd_His') . '_' . $file->getClientOriginalName();
            $created = $this->googleDriveStorage->uploadFile($file->getRealPath(), $remoteName, $file->getMimeType() ?: 'application/octet-stream');

            return response()->json([
                'success'  => true,
                'message'  => 'File berhasil diupload ke Google Drive.',
                'filename' => $created['filename'],
                'file_id'  => $created['file_id'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Upload gagal: ' . $e->getMessage()], 500);
        }
    }

    // GET /tools/google-drive/files
    public function listFiles()
    {
        if (!$this->isConfigured()) {
            return response()->json(['error' => 'Google Drive belum dikonfigurasi.'], 422);
        }

        try {
            return response()->json($this->googleDriveStorage->getFiles());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // DELETE /tools/google-drive/delete
    public function deleteFile(Request $request)
    {
        $request->validate(['file_id' => 'required|string']);

        if (!$this->isConfigured()) {
            return response()->json(['error' => 'Google Drive belum dikonfigurasi.'], 422);
        }

        try {
            $this->googleDriveStorage->deleteFile($request->file_id);

            return response()->json(['success' => true, 'message' => 'File berhasil dihapus.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Hapus gagal: ' . $e->getMessage()], 500);
        }
    }

    // GET /tools/google-drive/debug
    public function debug()
    {
        $credPath     = $this->googleDriveStorage->credPath();
        $folderId     = env('GOOGLE_DRIVE_FOLDER_ID');
        $credJson     = ($credPath && file_exists($credPath)) ? json_decode(file_get_contents($credPath), true) : null;
        $serviceEmail = $credJson['client_email'] ?? null;

        $result = [
            'folder_id'     => $folderId,
            'service_email' => $serviceEmail,
            'oauth_connected' => $this->isOAuthConnected(),
            'share_url'     => "https://drive.google.com/drive/folders/{$folderId}",
            'steps'         => [],
            'raw_contents'  => [],
            'error'         => null,
        ];

        try {
            $service = $this->googleDriveStorage->getDriveService();

            $response = $service->files->listFiles([
                'q'        => "'{$folderId}' in parents and trashed = false",
                'fields'   => 'files(id, name, size, mimeType, createdTime, modifiedTime)',
                'pageSize' => 50,
            ]);

            $files = $response->getFiles();
            $result['steps'][] = '✅ Koneksi ke Google Drive API berhasil';
            $result['steps'][] = count($files) === 0
                ? '⚠️ Folder kosong'
                : '✅ Ditemukan ' . count($files) . ' file di folder';

            foreach ($files as $f) {
                $result['raw_contents'][] = [
                    'id'       => $f->getId(),
                    'name'     => $f->getName(),
                    'size'     => $f->getSize(),
                    'mime'     => $f->getMimeType(),
                    'modified' => $f->getModifiedTime(),
                ];
            }

            try {
                $folder = $service->files->get($folderId, ['fields' => 'id,name,owners,shared']);
                $result['folder_name']   = $folder->getName();
                $result['folder_shared'] = $folder->getShared();
                $result['steps'][]       = '✅ Metadata folder berhasil diambil: "' . $folder->getName() . '"';
            } catch (\Throwable $e2) {
                $result['steps'][]      = '❌ Tidak bisa baca metadata folder: ' . $e2->getMessage();
                $result['folder_error'] = $e2->getMessage();
            }
        } catch (\Throwable $e) {
            $result['error']   = $e->getMessage();
            $result['steps'][] = '❌ Koneksi gagal: ' . $e->getMessage();
        }

        return response()->json($result, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    // POST /tools/google-drive/upload-credentials
    public function uploadCredentials(Request $request)
    {
        $request->validate([
            'credentials_file' => 'required|file|mimes:json|max:512',
        ]);

        try {
            $file    = $request->file('credentials_file');
            $content = file_get_contents($file->getRealPath());
            $json    = json_decode($content, true);

            if (!is_array($json) || ($json['type'] ?? '') !== 'service_account') {
                return response()->json(['error' => 'File bukan service account credentials yang valid. Pastikan file didownload dari Google Cloud Console → Service Accounts.'], 422);
            }

            $targetPath = storage_path('app/google-credentials.json');
            file_put_contents($targetPath, $content);

            return response()->json([
                'success'       => true,
                'message'       => 'Credentials berhasil disimpan.',
                'service_email' => $json['client_email'] ?? '-',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Upload gagal: ' . $e->getMessage()], 500);
        }
    }

    // GET /tools/google-drive/check-config
    public function checkConfig()
    {
        $checks = [];

        // 1. Package installed
        $packageInstalled = class_exists(\Masbug\Flysystem\GoogleDriveAdapter::class);
        $checks[] = [
            'key'    => 'package',
            'label'  => 'Package <code>masbug/flysystem-google-drive-ext</code> terinstall',
            'pass'   => $packageInstalled,
            'detail' => $packageInstalled ? null : 'Jalankan: <code>composer require masbug/flysystem-google-drive-ext</code>',
        ];

        // 2. GOOGLE_DRIVE_CREDENTIALS env set
        $credEnv    = env('GOOGLE_DRIVE_CREDENTIALS');
        $credEnvSet = !empty($credEnv);
        $checks[] = [
            'key'    => 'env_credentials',
            'label'  => 'ENV <code>GOOGLE_DRIVE_CREDENTIALS</code> diset',
            'pass'   => $credEnvSet,
            'detail' => $credEnvSet
                ? 'Nilai: <code>' . $credEnv . '</code>'
                : 'Tambahkan <code>GOOGLE_DRIVE_CREDENTIALS=storage/app/google-credentials.json</code> ke file <code>.env</code>',
        ];

        // 3. Credentials file exists
        $credPath       = $this->credPath();
        $credFileExists = $credPath && file_exists($credPath);
        $checks[] = [
            'key'            => 'cred_file',
            'label'          => 'File credentials JSON (Service Account) ada di disk',
            'pass'           => $credFileExists,
            'upload_allowed' => !$credFileExists,
            'detail'         => $credFileExists
                ? 'Path: <code>' . $credPath . '</code>'
                : 'File tidak ditemukan. Upload file credentials JSON dari Google Cloud Console.',
        ];

        // 4. Credentials JSON valid
        $credValid  = false;
        $credDetail = null;
        if ($credFileExists) {
            $json      = json_decode(file_get_contents($credPath), true);
            $credValid = is_array($json) && isset($json['type']) && $json['type'] === 'service_account';
            $credDetail = $credValid
                ? 'Service Account: <code>' . ($json['client_email'] ?? '-') . '</code>'
                : 'File JSON tidak valid atau bukan tipe <code>service_account</code>.';
        } else {
            $credDetail = 'File belum ada, tidak dapat divalidasi.';
        }
        $checks[] = [
            'key'    => 'cred_valid',
            'label'  => 'Credentials JSON valid (service_account)',
            'pass'   => $credValid,
            'detail' => $credDetail,
        ];

        // 5. GOOGLE_DRIVE_FOLDER_ID env set
        $folderIdEnv = env('GOOGLE_DRIVE_FOLDER_ID');
        $folderIdSet = !empty($folderIdEnv);
        $checks[] = [
            'key'    => 'env_folder_id',
            'label'  => 'ENV <code>GOOGLE_DRIVE_FOLDER_ID</code> diset',
            'pass'   => $folderIdSet,
            'detail' => $folderIdSet
                ? 'Folder ID: <code>' . $folderIdEnv . '</code>'
                : 'Tambahkan <code>GOOGLE_DRIVE_FOLDER_ID=xxx</code> ke file <code>.env</code>.',
        ];

        // 6. OAuth Client ID / Secret
        $oauthClientId     = env('GOOGLE_OAUTH_CLIENT_ID');
        $oauthClientSecret = env('GOOGLE_OAUTH_CLIENT_SECRET');
        $oauthEnvSet       = !empty($oauthClientId) && !empty($oauthClientSecret);
        $checks[] = [
            'key'    => 'oauth_env',
            'label'  => 'ENV <code>GOOGLE_OAUTH_CLIENT_ID</code> & <code>GOOGLE_OAUTH_CLIENT_SECRET</code> diset',
            'pass'   => $oauthEnvSet,
            'detail' => $oauthEnvSet
                ? 'Client ID: <code>' . substr($oauthClientId, 0, 20) . '...</code>'
                : 'Buat OAuth 2.0 Client ID di Google Cloud Console → APIs & Services → Credentials → Create Credentials → OAuth Client ID (Web Application). Tambahkan redirect URI: <code>' . route('tools.google-drive.oauth.callback') . '</code>',
        ];

        // 7. OAuth token (connected account)
        $oauthConnected = $this->isOAuthConnected();
        $oauthDetail    = null;
        if ($oauthConnected) {
            $tokenData   = json_decode(file_get_contents($this->oauthTokenPath()), true);
            $oauthDetail = isset($tokenData['refresh_token'])
                ? '✅ Refresh token tersedia — koneksi persisten.'
                : '⚠️ Hanya access token — akan expired. Disconnect dan hubungkan ulang.';
        } else {
            $oauthDetail = $oauthEnvSet
                ? 'Klik tombol <strong>"Hubungkan Google Account"</strong> di bawah untuk otorisasi.'
                : 'Selesaikan langkah OAuth Client ID terlebih dahulu.';
        }
        $checks[] = [
            'key'           => 'oauth_connected',
            'label'         => 'Google Account dihubungkan (OAuth2)',
            'pass'          => $oauthConnected,
            'oauth_connect' => !$oauthConnected && $oauthEnvSet,
            'detail'        => $oauthDetail,
        ];

        // 8. Live connection test
        $canConnect    = false;
        $connectDetail = null;
        $allCriticalPass = $packageInstalled && $credEnvSet && $credFileExists && $credValid && $folderIdSet;

        if ($allCriticalPass) {
            try {
                $service  = $this->getDriveService();
                $response = $service->files->listFiles([
                    'q'        => "'{$folderIdEnv}' in parents and trashed = false",
                    'fields'   => 'files(id)',
                    'pageSize' => 1,
                ]);
                $canConnect    = true;
                $connectDetail = 'Koneksi ke Google Drive berhasil! ' . ($this->isOAuthConnected() ? '(via OAuth)' : '(via Service Account)');
            } catch (\Throwable $e) {
                $connectDetail = $e->getMessage();
            }
        } else {
            $connectDetail = 'Tidak dapat diuji — selesaikan langkah sebelumnya terlebih dahulu.';
        }

        $checks[] = [
            'key'    => 'connection',
            'label'  => 'Tes koneksi live ke Google Drive API',
            'pass'   => $canConnect,
            'detail' => $connectDetail,
        ];

        $totalPass  = collect($checks)->whereNotNull('pass')->where('pass', true)->count();
        $totalCheck = collect($checks)->whereNotNull('pass')->count();

        return response()->json([
            'checks'      => $checks,
            'total_pass'  => $totalPass,
            'total_check' => $totalCheck,
            'all_pass'    => $totalPass === $totalCheck,
        ]);
    }


    private function isConfigured(): bool
    {
        $credPath = $this->googleDriveStorage->credPath();
        return !empty(env('GOOGLE_DRIVE_CREDENTIALS')) &&
               !empty(env('GOOGLE_DRIVE_FOLDER_ID')) &&
               $credPath !== null &&
               file_exists($credPath);
    }

    private function getFiles(): array
    {
        return $this->googleDriveStorage->getFiles();
    }

    private function isOAuthConnected(): bool
    {
        return $this->googleDriveStorage->isOAuthConnected();
    }

    private function oauthTokenPath(): string
    {
        return $this->googleDriveStorage->oauthTokenPath();
    }

    private function credPath(): ?string
    {
        return $this->googleDriveStorage->credPath();
    }

    private function getDriveService(): \Google\Service\Drive
    {
        return $this->googleDriveStorage->getDriveService();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024)       return $bytes . ' B';
        if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }
}
