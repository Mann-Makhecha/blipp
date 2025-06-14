<?php
require_once 'settings.php';

/**
 * Handle file upload with validation
 * 
 * @param array $file The $_FILES array element
 * @param string $target_dir The directory to save the file
 * @return array ['success' => bool, 'message' => string, 'filename' => string]
 */
function handle_file_upload($file, $target_dir) {
    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'message' => get_upload_error_message($file['error'])
        ];
    }
    
    // Get file info
    $file_info = pathinfo($file['name']);
    $extension = strtolower($file_info['extension']);
    
    // Validate file size
    $max_size = get_max_file_size() * 1024 * 1024; // Convert MB to bytes
    if ($file['size'] > $max_size) {
        return [
            'success' => false,
            'message' => "File size exceeds the maximum limit of " . get_max_file_size() . "MB."
        ];
    }
    
    // Validate file type
    if (!is_file_type_allowed($extension)) {
        return [
            'success' => false,
            'message' => "File type not allowed. Allowed types: " . implode(', ', get_allowed_file_types())
        ];
    }
    
    // Generate unique filename
    $filename = uniqid() . '.' . $extension;
    $target_path = $target_dir . '/' . $filename;
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return [
            'success' => true,
            'message' => "File uploaded successfully.",
            'filename' => $filename
        ];
    } else {
        return [
            'success' => false,
            'message' => "Failed to move uploaded file."
        ];
    }
}

/**
 * Get user-friendly error message for upload errors
 * 
 * @param int $error_code The upload error code
 * @return string Error message
 */
function get_upload_error_message($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
        case UPLOAD_ERR_FORM_SIZE:
            return "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.";
        case UPLOAD_ERR_PARTIAL:
            return "The uploaded file was only partially uploaded.";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded.";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing a temporary folder.";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk.";
        case UPLOAD_ERR_EXTENSION:
            return "A PHP extension stopped the file upload.";
        default:
            return "Unknown upload error.";
    }
}

/**
 * Delete a file
 * 
 * @param string $filepath The full path to the file
 * @return bool True if file was deleted successfully
 */
function delete_file($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
} 