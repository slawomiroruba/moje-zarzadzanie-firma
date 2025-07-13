<?php

/**
 * File validation utility class
 *
 * @package WPMZF
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_File_Validator {

    /**
     * Allowed file types
     */
    const ALLOWED_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed'
    ];

    /**
     * Maximum file size (50MB)
     */
    const MAX_FILE_SIZE = 52428800; // 50MB in bytes

    /**
     * Validate uploaded file
     *
     * @param array $file $_FILES array item
     * @return array|true Array of errors or true if valid
     */
    public static function validate_file($file) {
        $errors = [];

        // Check if file was uploaded
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $errors[] = 'Nie wybrano pliku do przesłania.';
            return $errors;
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = self::get_upload_error_message($file['error']);
            return $errors;
        }

        // Check file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            $errors[] = 'Plik jest za duży. Maksymalny rozmiar to 50MB.';
        }

        // Check file type
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!array_key_exists($file_extension, self::ALLOWED_TYPES)) {
            $errors[] = 'Nieprawidłowy typ pliku. Dozwolone typy: ' . implode(', ', array_keys(self::ALLOWED_TYPES));
        }

        // Additional MIME type check
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $file['tmp_name']);
        finfo_close($file_info);

        if (!in_array($mime_type, self::ALLOWED_TYPES) && 
            $mime_type !== self::ALLOWED_TYPES[$file_extension]) {
            $errors[] = 'Nieprawidłowy typ MIME pliku.';
        }

        // Check file name for security
        if (!self::validate_filename($file['name'])) {
            $errors[] = 'Nieprawidłowa nazwa pliku.';
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Validate filename for security
     *
     * @param string $filename
     * @return bool
     */
    private static function validate_filename($filename) {
        // Remove dangerous characters
        $sanitized = sanitize_file_name($filename);
        
        // Check if filename is reasonable length
        if (strlen($filename) > 255) {
            return false;
        }

        // Check for suspicious patterns
        $dangerous_patterns = [
            '/\.php$/i',
            '/\.phtml$/i',
            '/\.php\d+$/i',
            '/\.js$/i',
            '/\.html?$/i',
            '/\.exe$/i',
            '/\.bat$/i',
            '/\.cmd$/i',
            '/\.com$/i',
            '/\.scr$/i'
        ];

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get human-readable upload error message
     *
     * @param int $error_code
     * @return string
     */
    private static function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'Plik przekracza maksymalny rozmiar określony w php.ini.';
            case UPLOAD_ERR_FORM_SIZE:
                return 'Plik przekracza maksymalny rozmiar określony w formularzu.';
            case UPLOAD_ERR_PARTIAL:
                return 'Plik został przesłany tylko częściowo.';
            case UPLOAD_ERR_NO_FILE:
                return 'Nie przesłano żadnego pliku.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Brak folderu tymczasowego.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Nie można zapisać pliku na dysk.';
            case UPLOAD_ERR_EXTENSION:
                return 'Rozszerzenie PHP zatrzymało przesyłanie pliku.';
            default:
                return 'Nieznany błąd przesyłania pliku.';
        }
    }

    /**
     * Sanitize filename for storage
     *
     * @param string $filename
     * @return string
     */
    public static function sanitize_filename($filename) {
        // Use WordPress function
        return sanitize_file_name($filename);
    }
}
