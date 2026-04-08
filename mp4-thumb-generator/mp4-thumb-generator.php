<?php
/*
Plugin Name: MP4 Thumb Generator (FFmpeg)
Description: Gera thumbnail de vídeo mp4 (URL externa ou local) via FFmpeg, registra na biblioteca e define como imagem destacada.
Version: 1.0.0
*/

if (!defined('ABSPATH')) exit;

function mtg_find_mp4_url_in_postmeta($post_id) {
    $all = get_post_meta($post_id);
    if (empty($all) || !is_array($all)) return '';

    foreach ($all as $key => $values) {
        if (!is_array($values)) continue;
        foreach ($values as $v) {
            if (!is_string($v)) continue;
            $v = trim($v);
            if ($v === '') continue;
            if (stripos($v, '.mp4') === false) continue;

            // Match .mp4 followed by /, ?, or end of string.
            if (preg_match('~^https?://.+\.mp4([/\?].*)?$~i', $v)) return $v;
            if (preg_match('~^/.+\.mp4([/\?].*)?$~i', $v)) return $v;
        }
    }
    return '';
}

function mtg_abs_url_from_value($value) {
    $value = trim($value);
    if ($value === '') return '';

    if (preg_match('~^https?://~i', $value)) return $value;

    if (strpos($value, '/') === 0) {
        return home_url($value);
    }

    return '';
}

function mtg_log($msg) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[MP4Thumb] ' . $msg);
    }
}

function mtg_generate_thumb_for_post($post_id) {
    if (wp_is_post_revision($post_id)) return;
    if (get_post_meta($post_id, '_mtg_thumb_done', true)) return;

    $mp4 = mtg_find_mp4_url_in_postmeta($post_id);
    $mp4 = mtg_abs_url_from_value($mp4);
    if ($mp4 === '') return;

    $ffmpeg = '/usr/bin/ffmpeg';
    if (!file_exists($ffmpeg)) {
        $ffmpeg = trim(shell_exec('which ffmpeg'));
        if ($ffmpeg === '') {
            mtg_log('ffmpeg não encontrado');
            return;
        }
    }

    $upload = wp_upload_dir();
    if (!empty($upload['error'])) {
        mtg_log('upload_dir erro: ' . $upload['error']);
        return;
    }

    $filename = 'thumb_post_' . $post_id . '_' . time() . '.jpg';
    $dest = trailingslashit($upload['path']) . $filename;

    // Build FFmpeg command with browser-like headers for Hotlink Protection.
    $referer = home_url('/');
    $ua      = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

    $cmd = escapeshellcmd($ffmpeg) .
        ' -y -ss 00:00:02' .
        ' -headers ' . escapeshellarg("Referer: {$referer}\r\nUser-Agent: {$ua}\r\n") .
        ' -i ' . escapeshellarg($mp4) .
        ' -frames:v 1 -update 1 -vf "scale=640:-1" ' . escapeshellarg($dest) .
        ' 2>&1';

    mtg_log('Rodando: ' . $cmd);
    $out = shell_exec($cmd);

    if (!file_exists($dest) || filesize($dest) < 1024) {
        mtg_log('Falhou gerar thumb. Output: ' . (string)$out);
        return;
    }

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
        mtg_log('wp_insert_attachment erro: ' . $attach_id->get_error_message());
        return;
    }

    $attach_data = wp_generate_attachment_metadata($attach_id, $dest);
    wp_update_attachment_metadata($attach_id, $attach_data);

    set_post_thumbnail($post_id, $attach_id);
    update_post_meta($post_id, '_mtg_thumb_done', 1);
    update_post_meta($post_id, '_mtg_mp4_source', $mp4);

    mtg_log('Thumb ok. Attachment ID: ' . $attach_id);
}

add_action('save_post', 'mtg_generate_thumb_for_post', 20);

function mtg_admin_menu() {
    add_management_page('Gerar Thumb MP4', 'Gerar Thumb MP4', 'manage_options', 'mtg-generate', 'mtg_admin_page');
}
add_action('admin_menu', 'mtg_admin_menu');

function mtg_admin_page() {
    if (!current_user_can('manage_options')) return;

    echo '<div class="wrap"><h1>Gerar Thumb MP4</h1>';
    echo '<p>Informe o ID do post para forçar a geração.</p>';

    if (isset($_POST['post_id']) && check_admin_referer('mtg_generate')) {
        $post_id = intval($_POST['post_id']);
        delete_post_meta($post_id, '_mtg_thumb_done');
        mtg_generate_thumb_for_post($post_id);
        echo '<p>Processo executado. Se WP_DEBUG estiver ativo, veja wp-content/debug.log</p>';
    }

    echo '<form method="post">';
    wp_nonce_field('mtg_generate');
    echo '<input name="post_id" type="number" placeholder="ID do post" required style="width:200px" />';
    echo '<button class="button button-primary" type="submit">Gerar</button>';
    echo '</form></div>';
}
