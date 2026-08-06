<?php

namespace App\Services;

class GoogleDriveStorageService
{
    public function oauthRedirectUri(): string
    {
        return env('GOOGLE_OAUTH_REDIRECT_URI', route('tools.google-drive.oauth.callback'));
    }

    public function credPath(): ?string
    {
        $cred = env('GOOGLE_DRIVE_CREDENTIALS');
        if (empty($cred)) {
            return null;
        }

        if (file_exists($cred)) {
            return $cred;
        }

        $normalized = preg_replace('#^storage([/\\\\])#i', '', $cred);

        return storage_path($normalized);
    }

    public function oauthTokenPath(): string
    {
        return storage_path('app/google-oauth-token.json');
    }

    public function isOAuthConnected(): bool
    {
        return file_exists($this->oauthTokenPath())
            && !empty(env('GOOGLE_OAUTH_CLIENT_ID'))
            && !empty(env('GOOGLE_OAUTH_CLIENT_SECRET'));
    }

    public function isConfigured(): bool
    {
        $credPath = $this->credPath();

        return !empty(env('GOOGLE_DRIVE_CREDENTIALS'))
            && !empty(env('GOOGLE_DRIVE_FOLDER_ID'))
            && $credPath !== null
            && file_exists($credPath);
    }

    public function assertUploadReady(): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Google Drive belum dikonfigurasi.');
        }

        if (!$this->isOAuthConnected()) {
            throw new \RuntimeException('Hubungkan Google Account terlebih dahulu untuk menggunakan backup cloud.');
        }
    }

    public function getOAuthClient(): ?\Google\Client
    {
        $clientId = env('GOOGLE_OAUTH_CLIENT_ID');
        $clientSecret = env('GOOGLE_OAUTH_CLIENT_SECRET');
        $tokenPath = $this->oauthTokenPath();

        if (empty($clientId) || empty($clientSecret) || !file_exists($tokenPath)) {
            return null;
        }

        $client = new \Google\Client();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($this->oauthRedirectUri());
        $client->addScope(\Google\Service\Drive::DRIVE);
        $client->setAccessType('offline');

        $token = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $refreshToken = $client->getRefreshToken();
            if (empty($refreshToken)) {
                return null;
            }

            $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
            if (isset($newToken['error'])) {
                return null;
            }

            if (empty($newToken['refresh_token']) && !empty($token['refresh_token'])) {
                $newToken['refresh_token'] = $token['refresh_token'];
            }
            file_put_contents($tokenPath, json_encode($newToken));
        }

        return $client;
    }

    public function getDriveService(): \Google\Service\Drive
    {
        $oauthClient = $this->getOAuthClient();
        if ($oauthClient) {
            return new \Google\Service\Drive($oauthClient);
        }

        $credPath = $this->credPath();
        $client = new \Google\Client();
        $client->setAuthConfig($credPath);
        $client->addScope(\Google\Service\Drive::DRIVE);

        return new \Google\Service\Drive($client);
    }

    public function uploadFile(string $path, ?string $remoteName = null, ?string $mimeType = null): array
    {
        $this->assertUploadReady();

        if (!file_exists($path)) {
            throw new \RuntimeException('File backup tidak ditemukan untuk diupload ke Google Drive.');
        }

        // Upload WAJIB lewat OAuth (akun user) — Service Account tidak punya kuota penyimpanan
        // sehingga create file via Service Account selalu gagal dengan error storageQuotaExceeded.
        $oauthClient = $this->getOAuthClient();
        if (!$oauthClient) {
            throw new \RuntimeException('Koneksi Google Account kedaluwarsa atau belum memberi akses offline (tidak ada refresh token). Buka Tools → Google Drive, putuskan koneksi, lalu hubungkan ulang Google Account. Service Account tidak memiliki kuota penyimpanan untuk upload.');
        }

        $service = new \Google\Service\Drive($oauthClient);
        $folderId = env('GOOGLE_DRIVE_FOLDER_ID');
        $targetName = $remoteName ?: basename($path);

        $meta = new \Google\Service\Drive\DriveFile([
            'name' => $targetName,
            'parents' => [$folderId],
        ]);

        $created = $service->files->create($meta, [
            'data' => file_get_contents($path),
            'mimeType' => $mimeType ?: 'application/gzip',
            'uploadType' => 'multipart',
            'fields' => 'id,name,webViewLink,webContentLink',
            'supportsAllDrives' => true,
        ]);

        return [
            'file_id' => $created->getId(),
            'filename' => $created->getName(),
            'web_view_link' => $created->getWebViewLink(),
            'download_link' => $created->getWebContentLink(),
            'folder_id' => $folderId,
        ];
    }

    public function deleteFile(string $fileId): void
    {
        $this->getDriveService()->files->delete($fileId);
    }

    public function getFileMetadata(string $fileId): array
    {
        $file = $this->getDriveService()->files->get($fileId, [
            'fields' => 'id,name,size,mimeType,modifiedTime,webViewLink,webContentLink',
        ]);

        return [
            'file_id' => $file->getId(),
            'filename' => $file->getName(),
            'size' => (int) $file->getSize(),
            'size_human' => $this->formatBytes((int) $file->getSize()),
            'mime_type' => $file->getMimeType(),
            'modified' => $file->getModifiedTime() ? date('d/m/Y H:i', strtotime($file->getModifiedTime())) : '-',
            'web_view_link' => $file->getWebViewLink(),
            'download_link' => $file->getWebContentLink(),
        ];
    }

    public function downloadFileToPath(string $fileId, string $targetPath): array
    {
        $meta = $this->getFileMetadata($fileId);
        $service = $this->getDriveService();
        $response = $service->files->get($fileId, ['alt' => 'media']);
        $content = is_object($response) && method_exists($response, 'getBody')
            ? $response->getBody()->getContents()
            : (string) $response;

        file_put_contents($targetPath, $content);

        return $meta;
    }

    public function getFiles(): array
    {
        $folderId = env('GOOGLE_DRIVE_FOLDER_ID');
        $service = $this->getDriveService();

        $response = $service->files->listFiles([
            'q' => "'{$folderId}' in parents and trashed = false",
            'fields' => 'files(id, name, size, mimeType, modifiedTime)',
            'pageSize' => 100,
            'orderBy' => 'modifiedTime desc',
        ]);

        $files = [];
        foreach ($response->getFiles() as $file) {
            $modified = $file->getModifiedTime() ? strtotime($file->getModifiedTime()) : 0;

            $files[] = [
                'filename' => $file->getName(),
                'file_id' => $file->getId(),
                'size' => (int) $file->getSize(),
                'size_human' => $this->formatBytes((int) $file->getSize()),
                'modified' => $modified ? date('d/m/Y H:i', $modified) : '-',
                'modified_raw' => $modified,
            ];
        }

        return $files;
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return round($bytes / 1073741824, 2) . ' GB';
    }
}