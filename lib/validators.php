<?php
/**
 * Validation & Integrity Library
 * SIA 2027 Party Agent & Command Portal
 */

/**
 * Validates Form EC8A vote tallies server-side
 * Supports dynamic associative party votes array or positional arguments.
 */
function validate_ec8a_tallies(
    array|int $party_votes_or_lp,
    int $apc = 0,
    int $pdp = 0,
    int $apm = 0,
    int $ndc = 0,
    int $rejected = 0,
    int $accredited = 0
): array {
    $party_votes = [];
    $rejected_votes = 0;
    $total_accredited = 0;

    if (is_array($party_votes_or_lp)) {
        $rejected_votes = (int)($party_votes_or_lp['rejected'] ?? $party_votes_or_lp['rejected_votes'] ?? $apc);
        $total_accredited = (int)($party_votes_or_lp['accredited'] ?? $party_votes_or_lp['total_accredited'] ?? $party_votes_or_lp['total_accredited_voters'] ?? $pdp);

        foreach ($party_votes_or_lp as $k => $v) {
            if (in_array($k, ['rejected', 'rejected_votes', 'accredited', 'total_accredited', 'total_accredited_voters', 'total_valid', 'total_valid_votes'], true)) {
                continue;
            }
            $party_votes[$k] = (int)$v;
        }
    } else {
        $party_votes = [
            'lp' => (int)$party_votes_or_lp,
            'apc' => (int)$apc,
            'pdp' => (int)$pdp,
            'apm' => (int)$apm,
            'ndc' => (int)$ndc,
        ];
        $rejected_votes = (int)$rejected;
        $total_accredited = (int)$accredited;
    }

    // 1. Numbers cannot be negative
    if ($rejected_votes < 0 || $total_accredited < 0) {
        return [
            'valid' => false,
            'error' => 'Voter and rejected numbers cannot be negative.',
            'flag_reason' => null,
            'is_overvoting' => false,
            'total_valid' => 0
        ];
    }

    $total_valid = 0;
    foreach ($party_votes as $party_key => $votes) {
        if ($votes < 0) {
            return [
                'valid' => false,
                'error' => 'Party vote counts cannot be negative.',
                'flag_reason' => null,
                'is_overvoting' => false,
                'total_valid' => 0
            ];
        }
        $total_valid += $votes;
    }

    // 2. Recompute total valid votes strictly on server (never trust client)
    $total_cast = $total_valid + $rejected_votes;

    $is_overvoting = false;
    $flag_reason = null;

    if ($total_accredited > 0 && $total_cast > $total_accredited) {
        $is_overvoting = true;
        $flag_reason = "Over-voting detected: Total ballots cast ({$total_cast}) exceeds accredited voters ({$total_accredited}) by " . ($total_cast - $total_accredited);
    } elseif ($total_accredited === 0 && $total_cast > 0) {
        $flag_reason = "Ballots cast ({$total_cast}) recorded with 0 accredited voters entered.";
    }

    return [
        'valid' => true,
        'total_valid' => $total_valid,
        'total_cast' => $total_cast,
        'is_overvoting' => $is_overvoting,
        'flag_reason' => $flag_reason
    ];
}

/**
 * Verifies uploaded file using real MIME inspection (finfo), extension allowlist, and size cap
 */
function validate_uploaded_ec8a_file(array $file, int $max_mb = 15): array {
    if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['valid' => true, 'has_file' => false, 'filename' => null, 'sha256' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'Upload error code: ' . $file['error']];
    }

    // Check size cap (MB to bytes)
    $max_bytes = $max_mb * 1024 * 1024;
    if ($file['size'] > $max_bytes) {
        return ['valid' => false, 'error' => "File exceeds maximum size limit of {$max_mb}MB."];
    }

    // Allowed extensions and corresponding MIME types
    $allowed_mime_map = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf'
    ];

    $path_info = pathinfo($file['name']);
    $ext = strtolower($path_info['extension'] ?? '');

    if (!isset($allowed_mime_map[$ext])) {
        return ['valid' => false, 'error' => 'Invalid file extension. Only JPG, PNG, WEBP, and PDF documents are accepted.'];
    }

    // Inspect real MIME via finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $real_mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $expected_mime = $allowed_mime_map[$ext];
    // Allow jpeg variation
    if ($ext === 'jpg' && $real_mime === 'image/jpeg') {
        $expected_mime = 'image/jpeg';
    }

    if ($real_mime !== $expected_mime) {
        return ['valid' => false, 'error' => "File content does not match extension (detected: {$real_mime}, expected: {$expected_mime})."];
    }

    // Compute cryptographic SHA256 hash
    $sha256 = hash_file('sha256', $file['tmp_name']);

    return [
        'valid' => true,
        'has_file' => true,
        'ext' => $ext,
        'mime' => $real_mime,
        'sha256' => $sha256,
        'tmp_name' => $file['tmp_name']
    ];
}

