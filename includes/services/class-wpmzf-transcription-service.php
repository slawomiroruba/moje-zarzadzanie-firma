<?php

/**
 * Transcription Service for audio files
 *
 * @package WPMZF
 * @subpackage Services
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Transcription_Service {

    /**
     * Initialize the service
     */
    public static function init() {
        add_action('wpmzf_process_transcription', array(__CLASS__, 'process_transcription'));
        add_action('wp_ajax_wpmzf_get_transcription_status', array(__CLASS__, 'get_transcription_status'));
        add_action('wp_ajax_wpmzf_download_transcription', array(__CLASS__, 'download_transcription'));
    }

    /**
     * Process transcription for an audio file
     *
     * @param int $attachment_id
     */
    public static function process_transcription($attachment_id) {
        try {
            if (!WPMZF_File_Validator::is_audio_attachment($attachment_id)) {
                WPMZF_Logger::warning('Attempted to transcribe non-audio file', [
                    'attachment_id' => $attachment_id
                ]);
                return;
            }

            // Update status to processing
            update_post_meta($attachment_id, '_wpmzf_transcription_status', 'processing');
            update_post_meta($attachment_id, '_wpmzf_transcription_started', current_time('mysql'));

            $file_path = get_attached_file($attachment_id);
            if (!$file_path || !file_exists($file_path)) {
                throw new Exception('File not found: ' . $file_path);
            }

            // Try different transcription methods
            $transcription_text = null;
            $transcription_method = null;

            // Method 1: Try OpenAI Whisper API if configured
            if (self::is_openai_configured()) {
                try {
                    $transcription_text = self::transcribe_with_openai($file_path);
                    $transcription_method = 'openai';
                } catch (Exception $e) {
                    WPMZF_Logger::warning('OpenAI transcription failed', [
                        'error' => $e->getMessage(),
                        'attachment_id' => $attachment_id
                    ]);
                }
            }

            // Method 2: Try Google Speech-to-Text if OpenAI failed
            if (!$transcription_text && self::is_google_configured()) {
                try {
                    $transcription_text = self::transcribe_with_google($file_path);
                    $transcription_method = 'google';
                } catch (Exception $e) {
                    WPMZF_Logger::warning('Google transcription failed', [
                        'error' => $e->getMessage(),
                        'attachment_id' => $attachment_id
                    ]);
                }
            }

            // Method 3: Try local transcription with speech recognition
            if (!$transcription_text) {
                try {
                    $transcription_text = self::transcribe_locally($file_path);
                    $transcription_method = 'local';
                } catch (Exception $e) {
                    WPMZF_Logger::warning('Local transcription failed', [
                        'error' => $e->getMessage(),
                        'attachment_id' => $attachment_id
                    ]);
                }
            }

            if ($transcription_text) {
                // Save transcription
                $transcription_file = self::save_transcription($attachment_id, $transcription_text, $transcription_method);
                
                update_post_meta($attachment_id, '_wpmzf_transcription_status', 'completed');
                update_post_meta($attachment_id, '_wpmzf_transcription_completed', current_time('mysql'));
                update_post_meta($attachment_id, '_wpmzf_transcription_file', $transcription_file);
                update_post_meta($attachment_id, '_wpmzf_transcription_method', $transcription_method);
                update_post_meta($attachment_id, '_wpmzf_transcription_text', $transcription_text);

                WPMZF_Logger::info('Transcription completed successfully', [
                    'attachment_id' => $attachment_id,
                    'method' => $transcription_method,
                    'text_length' => strlen($transcription_text)
                ]);
            } else {
                throw new Exception('All transcription methods failed');
            }

        } catch (Exception $e) {
            update_post_meta($attachment_id, '_wpmzf_transcription_status', 'failed');
            update_post_meta($attachment_id, '_wpmzf_transcription_error', $e->getMessage());
            
            WPMZF_Logger::error('Transcription failed', [
                'attachment_id' => $attachment_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if OpenAI is configured
     */
    private static function is_openai_configured() {
        $api_key = get_option('wpmzf_openai_api_key');
        return !empty($api_key);
    }

    /**
     * Check if Google Speech-to-Text is configured
     */
    private static function is_google_configured() {
        $credentials = get_option('wpmzf_google_credentials');
        return !empty($credentials);
    }

    /**
     * Transcribe using OpenAI Whisper API
     */
    private static function transcribe_with_openai($file_path) {
        $api_key = get_option('wpmzf_openai_api_key');
        if (empty($api_key)) {
            throw new Exception('OpenAI API key not configured');
        }

        // Convert file if necessary
        $converted_file = self::convert_audio_for_openai($file_path);
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.openai.com/v1/audio/transcriptions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api_key,
            ],
            CURLOPT_POSTFIELDS => [
                'file' => new CURLFile($converted_file),
                'model' => 'whisper-1',
                'language' => 'pl', // Polish language
                'response_format' => 'text'
            ],
            CURLOPT_TIMEOUT => 300 // 5 minutes timeout
        ]);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        if (curl_error($curl)) {
            curl_close($curl);
            throw new Exception('cURL error: ' . curl_error($curl));
        }
        
        curl_close($curl);

        // Clean up converted file if it's different from original
        if ($converted_file !== $file_path && file_exists($converted_file)) {
            unlink($converted_file);
        }

        if ($http_code !== 200) {
            throw new Exception('OpenAI API error: HTTP ' . $http_code . ' - ' . $response);
        }

        return trim($response);
    }

    /**
     * Convert audio file for OpenAI (if needed)
     */
    private static function convert_audio_for_openai($file_path) {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // OpenAI supports: mp3, mp4, mpeg, mpga, m4a, wav, webm
        $supported_formats = ['mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'wav', 'webm'];
        
        if (in_array($extension, $supported_formats)) {
            return $file_path; // No conversion needed
        }

        // Convert unsupported formats (like opus) to mp3 using ffmpeg if available
        if (self::is_ffmpeg_available()) {
            $upload_dir = wp_upload_dir();
            $temp_file = $upload_dir['path'] . '/' . uniqid('temp_audio_') . '.mp3';
            
            $command = sprintf(
                'ffmpeg -i %s -acodec mp3 -ab 128k %s 2>&1',
                escapeshellarg($file_path),
                escapeshellarg($temp_file)
            );
            
            exec($command, $output, $return_code);
            
            if ($return_code === 0 && file_exists($temp_file)) {
                return $temp_file;
            } else {
                WPMZF_Logger::warning('FFmpeg conversion failed', [
                    'command' => $command,
                    'output' => implode("\n", $output),
                    'return_code' => $return_code
                ]);
            }
        }

        // If conversion failed, try with original file anyway
        return $file_path;
    }

    /**
     * Check if ffmpeg is available
     */
    private static function is_ffmpeg_available() {
        exec('ffmpeg -version 2>&1', $output, $return_code);
        return $return_code === 0;
    }

    /**
     * Transcribe using Google Speech-to-Text
     */
    private static function transcribe_with_google($file_path) {
        // This would require Google Cloud Speech-to-Text API implementation
        // For now, throw an exception indicating it's not implemented
        throw new Exception('Google Speech-to-Text not implemented yet');
    }

    /**
     * Local transcription (fallback method)
     */
    private static function transcribe_locally($file_path) {
        // This would require local speech recognition tools
        // For now, return a placeholder
        throw new Exception('Local transcription not implemented yet');
    }

    /**
     * Save transcription to file
     */
    private static function save_transcription($attachment_id, $transcription_text, $method) {
        $upload_dir = wp_upload_dir();
        $transcription_dir = $upload_dir['basedir'] . '/transcriptions';
        
        if (!file_exists($transcription_dir)) {
            wp_mkdir_p($transcription_dir);
        }

        $original_file = get_attached_file($attachment_id);
        $original_name = pathinfo($original_file, PATHINFO_FILENAME);
        
        $transcription_file = $transcription_dir . '/' . $original_name . '_transcription_' . $attachment_id . '.txt';
        
        $content = "Transkrypcja pliku: " . basename($original_file) . "\n";
        $content .= "Data: " . current_time('Y-m-d H:i:s') . "\n";
        $content .= "Metoda: " . $method . "\n";
        $content .= "----------------------------------------\n\n";
        $content .= $transcription_text;
        
        if (file_put_contents($transcription_file, $content) === false) {
            throw new Exception('Failed to save transcription file');
        }

        return $transcription_file;
    }

    /**
     * Get transcription status via AJAX
     */
    public static function get_transcription_status() {
        check_ajax_referer('wpmzf_person_view_nonce', 'security');
        
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        
        if (!$attachment_id) {
            wp_send_json_error(['message' => 'Invalid attachment ID']);
            return;
        }

        $status = get_post_meta($attachment_id, '_wpmzf_transcription_status', true);
        $error = get_post_meta($attachment_id, '_wpmzf_transcription_error', true);
        
        $response = [
            'status' => $status ?: 'unknown',
            'attachment_id' => $attachment_id
        ];
        
        if ($error) {
            $response['error'] = $error;
        }
        
        if ($status === 'completed') {
            $transcription_file = get_post_meta($attachment_id, '_wpmzf_transcription_file', true);
            $transcription_text = get_post_meta($attachment_id, '_wpmzf_transcription_text', true);
            $method = get_post_meta($attachment_id, '_wpmzf_transcription_method', true);
            
            if ($transcription_file && file_exists($transcription_file)) {
                $response['download_url'] = wp_nonce_url(
                    admin_url('admin-ajax.php?action=wpmzf_download_transcription&attachment_id=' . $attachment_id),
                    'download_transcription_' . $attachment_id
                );
            }
            
            $response['text_preview'] = mb_substr($transcription_text, 0, 200) . (strlen($transcription_text) > 200 ? '...' : '');
            $response['method'] = $method;
        }

        wp_send_json_success($response);
    }

    /**
     * Download transcription file
     */
    public static function download_transcription() {
        $attachment_id = isset($_GET['attachment_id']) ? intval($_GET['attachment_id']) : 0;
        
        if (!$attachment_id || !wp_verify_nonce($_GET['_wpnonce'], 'download_transcription_' . $attachment_id)) {
            wp_die('Invalid request');
        }

        $transcription_file = get_post_meta($attachment_id, '_wpmzf_transcription_file', true);
        
        if (!$transcription_file || !file_exists($transcription_file)) {
            wp_die('Transcription file not found');
        }

        $filename = basename($transcription_file);
        
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($transcription_file));
        
        readfile($transcription_file);
        exit;
    }

    /**
     * Get transcription text for an attachment
     */
    public static function get_transcription_text($attachment_id) {
        return get_post_meta($attachment_id, '_wpmzf_transcription_text', true);
    }

    /**
     * Check if attachment has completed transcription
     */
    public static function has_transcription($attachment_id) {
        $status = get_post_meta($attachment_id, '_wpmzf_transcription_status', true);
        return $status === 'completed';
    }
}

// Initialize the service
WPMZF_Transcription_Service::init();
