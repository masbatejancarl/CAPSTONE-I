<?php
// Profile photo storage — uploads/deletes objects in the Supabase Storage
// "avatars" bucket via its REST API using the service_role key. This app
// authenticates its own PHP sessions rather than Supabase Auth, so RLS
// policies tied to auth.uid() would otherwise block every request from here;
// service_role bypasses that. The key must stay server-side only.

const AVATAR_MAX_BYTES    = 3 * 1024 * 1024; // 3 MB
const AVATAR_ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

function avatarObjectUrl(string $filename): string {
    return rtrim(SUPABASE_URL, '/') . '/storage/v1/object/' . SUPABASE_AVATAR_BUCKET . '/' . $filename;
}

function avatarPublicUrl(string $filename): string {
    // Cache-busted so the browser fetches the new photo instead of a stale
    // cached copy at the same path (upsert overwrites the file in place).
    return rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/' . SUPABASE_AVATAR_BUCKET . '/' . $filename . '?v=' . time();
}

function avatarPathFromUrl(?string $url): ?string {
    if (!$url) return null;
    $marker = '/storage/v1/object/public/' . SUPABASE_AVATAR_BUCKET . '/';
    $pos = strpos($url, $marker);
    if ($pos === false) return null;
    return strtok(substr($url, $pos + strlen($marker)), '?');
}

function deleteAvatarObject(string $filename): void {
    $ch = curl_init(avatarObjectUrl($filename));
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
            'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch); // best-effort cleanup; a failure here isn't user-facing
    curl_close($ch);
}

// Uploads $file (an entry from $_FILES) as the given user's avatar and
// returns ['ok'=>true,'url'=>...] or ['ok'=>false,'error'=>...].
function uploadAvatar(int $userId, array $file, ?string $oldAvatarUrl = null): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Please choose an image first.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
    }
    if ($file['size'] > AVATAR_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Image must be 3MB or smaller.'];
    }

    // Trust the file's actual contents, not its extension or client-sent MIME type.
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false || !isset(AVATAR_ALLOWED_MIME[$imageInfo['mime']])) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, or WEBP images are allowed.'];
    }
    $ext      = AVATAR_ALLOWED_MIME[$imageInfo['mime']];
    $filename = "user-{$userId}.{$ext}";

    $bytes = file_get_contents($file['tmp_name']);
    if ($bytes === false) {
        return ['ok' => false, 'error' => 'Could not read the uploaded file.'];
    }

    $ch = curl_init(avatarObjectUrl($filename));
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $bytes,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
            'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
            'Content-Type: ' . $imageInfo['mime'],
            'x-upsert: true',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        return ['ok' => false, 'error' => 'Could not upload image right now. Please try again later.'];
    }

    // Clean up a previous photo saved under a different extension — upsert
    // only overwrites when the filename (including extension) matches exactly.
    $oldPath = avatarPathFromUrl($oldAvatarUrl);
    if ($oldPath && $oldPath !== $filename) {
        deleteAvatarObject($oldPath);
    }

    return ['ok' => true, 'url' => avatarPublicUrl($filename)];
}

function removeAvatar(?string $avatarUrl): void {
    $path = avatarPathFromUrl($avatarUrl);
    if ($path) {
        deleteAvatarObject($path);
    }
}
