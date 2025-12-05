<?php
/**
 * Video Upload Handler
 * Handles trainer intro video uploads with thumbnail generation
 */

if (!defined('ABSPATH')) exit;

class PTP_Video {
    
    private static $max_size = 104857600; // 100MB
    private static $allowed_types = array('video/mp4', 'video/quicktime', 'video/webm');
    private static $max_duration = 120; // 2 minutes
    
    /**
     * Handle video upload
     */
    public static function handle_upload($trainer_id, $file) {
        // Validate file
        $validation = self::validate_file($file);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Set up upload directory
        $upload_dir = wp_upload_dir();
        $trainer_dir = $upload_dir['basedir'] . '/ptp-training/videos/' . $trainer_id;
        
        if (!file_exists($trainer_dir)) {
            wp_mkdir_p($trainer_dir);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'intro-' . time() . '.' . $extension;
        $filepath = $trainer_dir . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return new WP_Error('upload_failed', 'Failed to save video file');
        }
        
        // Generate thumbnail
        $thumbnail_path = self::generate_thumbnail($filepath, $trainer_dir);
        
        // Build URLs
        $video_url = $upload_dir['baseurl'] . '/ptp-training/videos/' . $trainer_id . '/' . $filename;
        $thumbnail_url = $thumbnail_path 
            ? $upload_dir['baseurl'] . '/ptp-training/videos/' . $trainer_id . '/' . basename($thumbnail_path)
            : null;
        
        // Update trainer record
        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}ptp_trainers",
            array(
                'intro_video_url' => $video_url,
                'intro_video_thumbnail' => $thumbnail_url
            ),
            array('id' => $trainer_id),
            array('%s', '%s'),
            array('%d')
        );
        
        // Delete old video if exists
        self::cleanup_old_videos($trainer_id, $filename);
        
        return array(
            'url' => $video_url,
            'thumbnail' => $thumbnail_url
        );
    }
    
    /**
     * Validate uploaded file
     */
    private static function validate_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = array(
                UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload blocked by server extension'
            );
            return new WP_Error('upload_error', $errors[$file['error']] ?? 'Unknown upload error');
        }
        
        // Check file size
        if ($file['size'] > self::$max_size) {
            return new WP_Error('file_too_large', 'Video file must be under 100MB');
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, self::$allowed_types)) {
            return new WP_Error('invalid_type', 'Invalid video format. Allowed: MP4, MOV, WebM');
        }
        
        // Check duration if ffprobe is available
        $duration = self::get_video_duration($file['tmp_name']);
        if ($duration && $duration > self::$max_duration) {
            return new WP_Error('too_long', 'Video must be under 2 minutes');
        }
        
        return true;
    }
    
    /**
     * Get video duration using ffprobe
     */
    private static function get_video_duration($filepath) {
        if (!self::ffprobe_available()) {
            return null;
        }
        
        $cmd = sprintf(
            'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1',
            escapeshellarg($filepath)
        );
        
        $output = shell_exec($cmd);
        
        return $output ? floatval(trim($output)) : null;
    }
    
    /**
     * Generate video thumbnail using ffmpeg
     */
    private static function generate_thumbnail($video_path, $output_dir) {
        if (!self::ffmpeg_available()) {
            return null;
        }
        
        $thumbnail_filename = 'thumb-' . time() . '.jpg';
        $thumbnail_path = $output_dir . '/' . $thumbnail_filename;
        
        // Extract frame at 1 second
        $cmd = sprintf(
            'ffmpeg -i %s -ss 00:00:01 -vframes 1 -vf "scale=640:-1" %s 2>&1',
            escapeshellarg($video_path),
            escapeshellarg($thumbnail_path)
        );
        
        shell_exec($cmd);
        
        return file_exists($thumbnail_path) ? $thumbnail_path : null;
    }
    
    /**
     * Check if ffmpeg is available
     */
    private static function ffmpeg_available() {
        $output = shell_exec('which ffmpeg 2>&1');
        return !empty(trim($output));
    }
    
    /**
     * Check if ffprobe is available
     */
    private static function ffprobe_available() {
        $output = shell_exec('which ffprobe 2>&1');
        return !empty(trim($output));
    }
    
    /**
     * Delete old videos when new one is uploaded
     */
    private static function cleanup_old_videos($trainer_id, $current_filename) {
        $upload_dir = wp_upload_dir();
        $trainer_dir = $upload_dir['basedir'] . '/ptp-training/videos/' . $trainer_id;
        
        if (!is_dir($trainer_dir)) {
            return;
        }
        
        // Extract timestamp from current filename (e.g., "intro-1234567890.mp4" -> "1234567890")
        $current_timestamp = '';
        if (preg_match('/^intro-(\d+)\./', $current_filename, $matches)) {
            $current_timestamp = $matches[1];
        }
        
        // Get current thumbnail filename
        $current_thumb = $current_timestamp ? 'thumb-' . $current_timestamp . '.jpg' : '';
        
        $files = scandir($trainer_dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if ($file === $current_filename) continue;
            if ($file === $current_thumb) continue;
            
            @unlink($trainer_dir . '/' . $file);
        }
    }
    
    /**
     * Delete trainer's video
     */
    public static function delete_video($trainer_id) {
        $upload_dir = wp_upload_dir();
        $trainer_dir = $upload_dir['basedir'] . '/ptp-training/videos/' . $trainer_id;
        
        if (is_dir($trainer_dir)) {
            $files = scandir($trainer_dir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    @unlink($trainer_dir . '/' . $file);
                }
            }
            @rmdir($trainer_dir);
        }
        
        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}ptp_trainers",
            array(
                'intro_video_url' => null,
                'intro_video_thumbnail' => null
            ),
            array('id' => $trainer_id)
        );
        
        return true;
    }
    
    /**
     * Handle video URL from external source (YouTube, Vimeo)
     */
    public static function set_external_video($trainer_id, $url) {
        // Validate URL
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return new WP_Error('invalid_url', 'Invalid video URL');
        }
        
        $allowed_hosts = array(
            'youtube.com', 'www.youtube.com', 'youtu.be',
            'vimeo.com', 'player.vimeo.com',
            'loom.com', 'www.loom.com'
        );
        
        if (!in_array($parsed['host'], $allowed_hosts)) {
            return new WP_Error('invalid_host', 'Video must be from YouTube, Vimeo, or Loom');
        }
        
        // Get thumbnail for YouTube
        $thumbnail = null;
        if (strpos($parsed['host'], 'youtube') !== false || $parsed['host'] === 'youtu.be') {
            $video_id = self::extract_youtube_id($url);
            if ($video_id) {
                $thumbnail = "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg";
            }
        }
        
        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}ptp_trainers",
            array(
                'intro_video_url' => $url,
                'intro_video_thumbnail' => $thumbnail
            ),
            array('id' => $trainer_id)
        );
        
        return array(
            'url' => $url,
            'thumbnail' => $thumbnail
        );
    }
    
    /**
     * Extract YouTube video ID from URL
     */
    private static function extract_youtube_id($url) {
        $patterns = array(
            '/youtube\.com\/watch\?v=([^&]+)/',
            '/youtube\.com\/embed\/([^?]+)/',
            '/youtu\.be\/([^?]+)/'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
}
