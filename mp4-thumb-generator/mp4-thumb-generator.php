<?php
/*
Plugin Name: MP4 Thumb Generator (FFmpeg)
Description: Gera thumbnail de vídeo mp4 (URL externa ou local) via FFmpeg, registra na biblioteca e define como imagem destacada.
Version: 1.1.0
*/

if (!defined('ABSPATH')) exit;

function mtg_find_mp4_url_in_postmeta($post_id) {
    // Priority: check video_url first (TikSwipe standard field).
    $video_url = get_post_meta($post_id, 'video_url', true);
    if ($video_url && mtg_is_mp4_url($video_url)) {
        return mtg_abs_url_from_value($video_url);
    }

    // Fallback: scan all meta for any .mp4 URL.
    $all = get_post_meta($post_id);
    if (empty($all) || !is_array($all)) return '';

    foreach ($all as $key => $values) {
        if ($key === 'video_url') continue; // Already checked.
        if (!is_array($values)) continue;
        foreach ($values as $v) {
            if (!is_string($v)) continue;
            $v = trim($v);
            if ($v !== '' && mtg_is_mp4_url($v)) {
                return mtg_abs_url_from_value($v);
            }
        }
    }
    return '';
}

function mtg_is_mp4_url($v) {
    if (stripos($v, '.mp4') === false) return false;
    // Match .mp4 followed by /, ?, or end.
    return (bool) preg_match('~\.mp4([/\?#].*)?$~i', $v);
}

function mtg_abs_url_from_value($value) {
    $value = trim($value);
    if ($value === '') return '';
    if (preg_match('~^https?://~i', $value)) return $value;
    if (strpos($value, '/') === 0) return home_url($value);
    return '';
}

function mtg_log($msg) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[MP4Thumb] ' . $msg);
    }
}

/**
 * Generate thumbnail for a post. Returns array with status info.
 */
function mtg_generate_thumb_for_post($post_id, $force = false) {
    $result = array('success' => false, 'steps' => array());

    if (wp_is_post_revision($post_id)) {
        $result['steps'][] = 'Skipped: post is a revision.';
        return $result;
    }

    if (!$force && get_post_meta($post_id, '_mtg_thumb_done', true)) {
        $result['steps'][] = 'Skipped: _mtg_thumb_done already set. Use force mode.';
        return $result;
    }

    // Step 1: Find MP4 URL.
    $mp4 = mtg_find_mp4_url_in_postmeta($post_id);
    if ($mp4 === '') {
        $result['steps'][] = 'FAIL: No .mp4 URL found in post meta.';
        $result['steps'][] = 'video_url meta = "' . get_post_meta($post_id, 'video_url', true) . '"';
        return $result;
    }
    $result['steps'][] = 'Found MP4 URL: ' . $mp4;

    // Step 2: Check shell_exec.
    if (!function_exists('shell_exec')) {
        $result['steps'][] = 'FAIL: shell_exec is disabled on this server.';
        return $result;
    }
    $result['steps'][] = 'shell_exec: available';

    // Step 3: Find FFmpeg.
    $ffmpeg = '/usr/bin/ffmpeg';
    if (!file_exists($ffmpeg)) {
        $ffmpeg = trim(shell_exec('which ffmpeg 2>/dev/null'));
        if ($ffmpeg === '' || $ffmpeg === null) {
            $result['steps'][] = 'FAIL: ffmpeg not found on server.';
            return $result;
        }
    }
    $result['steps'][] = 'FFmpeg path: ' . $ffmpeg;

    // Check FFmpeg version.
    $version = shell_exec(escapeshellarg($ffmpeg) . ' -version 2>&1 | head -1');
    $result['steps'][] = 'FFmpeg version: ' . trim($version);

    // Step 4: Prepare output path.
    $upload = wp_upload_dir();
    if (!empty($upload['error'])) {
        $result['steps'][] = 'FAIL: upload_dir error: ' . $upload['error'];
        return $result;
    }

    $filename = 'thumb_post_' . $post_id . '_' . time() . '.jpg';
    $dest = trailingslashit($upload['path']) . $filename;
    $result['steps'][] = 'Output path: ' . $dest;

    // Step 5: Build and run FFmpeg command.
    $referer = home_url('/');
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

    $cmd = escapeshellarg($ffmpeg) .
        ' -y' .
        ' -headers ' . escapeshellarg("Referer: {$referer}\r\nUser-Agent: {$ua}\r\n") .
        ' -ss 00:00:02' .
        ' -i ' . escapeshellarg($mp4) .
        ' -frames:v 1 -update 1 -vf "scale=640:-1"' .
        ' ' . escapeshellarg($dest) .
        ' 2>&1';

    $result['steps'][] = 'Command: ' . $cmd;

    $out = shell_exec($cmd);
    $result['steps'][] = 'FFmpeg output: ' . mb_substr((string) $out, 0, 500);

    // Step 6: Verify output file.
    if (!file_exists($dest)) {
        $result['steps'][] = 'FAIL: Output file was not created.';
        return $result;
    }

    $filesize = filesize($dest);
    $result['steps'][] = 'Output file size: ' . $filesize . ' bytes';

    if ($filesize < 500) {
        $result['steps'][] = 'FAIL: Output file too small (probably empty/broken).';
        @unlink($dest);
        return $result;
    }

    // Step 7: Register in WP media library.
    $filetype = wp_check_filetype($filename, null);
    $attachment = array(
        'post_mime_type' => $filetype['type'],
        'post_title'     => 'Thumbnail post ' . $post_id,
        'post_content'   => '',
        'post_status'    => 'inherit'
    );

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $attach_id = wp_insert_attachment($attachment, $dest, $post_id);
    if (is_wp_error($attach_id)) {
        $result['steps'][] = 'FAIL: wp_insert_attachment error: ' . $attach_id->get_error_message();
        return $result;
    }

    $attach_data = wp_generate_attachment_metadata($attach_id, $dest);
    wp_update_attachment_metadata($attach_id, $attach_data);

    set_post_thumbnail($post_id, $attach_id);
    update_post_meta($post_id, '_mtg_thumb_done', 1);
    update_post_meta($post_id, '_mtg_mp4_source', $mp4);

    $result['success'] = true;
    $result['steps'][] = 'SUCCESS: Thumbnail set. Attachment ID: ' . $attach_id;
    mtg_log('Thumb ok. Post: ' . $post_id . ', Attachment: ' . $attach_id);

    return $result;
}

// Auto-generate on save_post (silent — logs to debug.log).
add_action('save_post', function ($post_id) {
    $result = mtg_generate_thumb_for_post($post_id);
    if (!$result['success']) {
        foreach ($result['steps'] as $step) {
            mtg_log($step);
        }
    }
}, 20);

// Admin page.
function mtg_admin_menu() {
    add_management_page('Gerar Thumb MP4', 'Gerar Thumb MP4', 'manage_options', 'mtg-generate', 'mtg_admin_page');
}
add_action('admin_menu', 'mtg_admin_menu');

function mtg_admin_page() {
    if (!current_user_can('manage_options')) return;

    echo '<div class="wrap"><h1>Gerar Thumb MP4</h1>';

    if (isset($_POST['post_id']) && check_admin_referer('mtg_generate')) {
        $post_id = intval($_POST['post_id']);
        delete_post_meta($post_id, '_mtg_thumb_done');

        $result = mtg_generate_thumb_for_post($post_id, true);

        echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:15px;margin:15px 0;border-radius:4px;">';
        echo '<h3 style="margin-top:0;">Resultado — Post #' . $post_id . '</h3>';
        echo '<ol style="font-family:monospace;font-size:13px;line-height:1.8;">';
        foreach ($result['steps'] as $step) {
            $color = '#333';
            if (strpos($step, 'FAIL') === 0) $color = '#d63638';
            if (strpos($step, 'SUCCESS') === 0) $color = '#00a32a';
            echo '<li style="color:' . $color . ';">' . esc_html($step) . '</li>';
        }
        echo '</ol>';

        if ($result['success']) {
            $thumb_url = get_the_post_thumbnail_url($post_id, 'medium');
            if ($thumb_url) {
                echo '<p><strong>Preview:</strong></p>';
                echo '<img src="' . esc_url($thumb_url) . '" style="max-width:300px;border-radius:4px;" />';
            }
        }
        echo '</div>';
    }

    echo '<form method="post">';
    wp_nonce_field('mtg_generate');
    echo '<p><input name="post_id" type="number" placeholder="ID do post" required style="width:200px" /> ';
    echo '<button class="button button-primary" type="submit">Gerar</button></p>';
    echo '</form></div>';
}
