<?php
// config/GoogleDriveHelper.php
require_once __DIR__ . '/config.php';

class GoogleDriveHelper {
    
    /**
     * Obtains a short-lived Access Token using OAuth2 Refresh Token
     */
    private static function getAccessToken() {
        if (GD_CLIENT_ID === 'YOUR_GOOGLE_DRIVE_CLIENT_ID' || GD_REFRESH_TOKEN === 'YOUR_GOOGLE_REFRESH_TOKEN') {
            return false;
        }

        $url = 'https://oauth2.googleapis.com/token';
        $params = [
            'client_id'     => GD_CLIENT_ID,
            'client_secret' => GD_CLIENT_SECRET,
            'refresh_token' => GD_REFRESH_TOKEN,
            'grant_type'    => 'refresh_token'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['access_token'] ?? false;
        }

        return false;
    }

    /**
     * Finds or creates a folder inside Google Drive
     */
    public static function getOrCreateFolder($name, $parentId = null) {
        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            return 'mock_folder_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name);
        }

        // Search for existing folder
        $q = "name = '" . str_replace("'", "\\'", $name) . "' and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
        if ($parentId) {
            $q .= " and '{$parentId}' in parents";
        }
        
        $url = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($q) . '&fields=files(id)';
        $headers = [
            'Authorization: Bearer ' . $accessToken
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (!empty($data['files'])) {
                return $data['files'][0]['id'];
            }
        }

        // Not found, create it
        $createUrl = 'https://www.googleapis.com/drive/v3/files?fields=id';
        $metadata = [
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder'
        ];
        if ($parentId) {
            $metadata['parents'] = [$parentId];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $createUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($metadata));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $folderId = $data['id'] ?? '';
            if ($folderId) {
                self::makeFilePublic($folderId, $accessToken);
                return $folderId;
            }
        }

        return 'mock_folder_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name);
    }

    /**
     * Resolves the target Task Folder ID on Google Drive based on hierarchy
     */
    public static function resolveTaskFolder($collegeName, $departmentName) {
        $rootDirId = (defined('GD_PARENT_FOLDER_ID') && GD_PARENT_FOLDER_ID !== '' && GD_PARENT_FOLDER_ID !== 'YOUR_GOOGLE_DRIVE_FOLDER_ID') 
            ? GD_PARENT_FOLDER_ID 
            : self::getOrCreateFolder('College Management System');
        $collegeFolderId = self::getOrCreateFolder($collegeName, $rootDirId);
        $deptFolderId = self::getOrCreateFolder($departmentName, $collegeFolderId);
        
        $dateStr = date('d-m-Y');
        $dateFolderId = self::getOrCreateFolder($dateStr, $deptFolderId);
        
        return $dateFolderId;
    }

    /**
     * Uploads file to Google Drive under a specific folder and returns [file_id, web_view_link]
     */
    public static function uploadFile($filePath, $fileName, $mimeType, $folderId = null) {
        $accessToken = self::getAccessToken();

        // Graceful fallback for local development without active credentials
        if (!$accessToken) {
            $mockFileId = 'mock_gd_' . uniqid();
            $mockUrl = BASE_URL . '/uploads/' . basename($filePath);
            return [
                'id' => $mockFileId,
                'webViewLink' => $mockUrl
            ];
        }

        // Google Drive Multipart Upload URL
        $url = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,webViewLink';

        $metadata = [
            'name' => $fileName
        ];
        if ($folderId) {
            $metadata['parents'] = [$folderId];
        }

        $boundary = '-------CWMBoundary' . microtime(true);
        $multipartBody = "";
        
        // Metadata Part
        $multipartBody .= "--" . $boundary . "\r\n";
        $multipartBody .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $multipartBody .= json_encode($metadata) . "\r\n";
        
        // Media/File Part
        $multipartBody .= "--" . $boundary . "\r\n";
        $multipartBody .= "Content-Type: " . $mimeType . "\r\n\r\n";
        $multipartBody .= file_get_contents($filePath) . "\r\n";
        $multipartBody .= "--" . $boundary . "--\r\n";

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: multipart/related; boundary=' . $boundary,
            'Content-Length: ' . strlen($multipartBody)
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $multipartBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $fileData = json_decode($response, true);
            $fileId = $fileData['id'] ?? '';

            if ($fileId) {
                self::makeFilePublic($fileId, $accessToken);
            }

            return [
                'id' => $fileId,
                'webViewLink' => $fileData['webViewLink'] ?? ''
            ];
        }

        // Fallback if HTTP call fails
        $mockFileId = 'mock_gd_failed_' . uniqid();
        $mockUrl = BASE_URL . '/uploads/' . basename($filePath);
        return [
            'id' => $mockFileId,
            'webViewLink' => $mockUrl
        ];
    }

    /**
     * Helper to make Google Drive file viewable by anyone
     */
    private static function makeFilePublic($fileId, $accessToken) {
        $url = "https://www.googleapis.com/drive/v3/files/{$fileId}/permissions";
        $params = [
            'role' => 'reader',
            'type' => 'anyone'
        ];

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Deletes a file from Google Drive
     */
    public static function deleteFile($fileId) {
        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            return false;
        }

        $url = "https://www.googleapis.com/drive/v3/files/{$fileId}";
        $headers = [
            'Authorization: Bearer ' . $accessToken
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 204 || $httpCode === 200);
    }
}
