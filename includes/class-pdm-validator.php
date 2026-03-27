<?php

defined('ABSPATH') || exit;

class PDM_Validator
{
    private const DANGEROUS_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar',
        'js', 'mjs',
        'sh', 'bash', 'zsh',
        'bat', 'cmd', 'com',
        'exe', 'msi',
        'dll', 'so', 'dylib',
        'cgi', 'pl', 'py', 'rb',
        'asp', 'aspx', 'jsp', 'cfm',
        'htaccess', 'htpasswd',
        'sql',
    ];

    private const DANGEROUS_MIMES = [
        'application/x-php',
        'application/x-httpd-php',
        'application/x-httpd-php-source',
        'text/x-php',
        'text/php',
        'application/javascript',
        'text/javascript',
        'application/x-sh',
        'application/x-bat',
        'application/x-msdos-program',
        'application/x-msdownload',
        'application/x-dosexec',
    ];

    private const DANGEROUS_PATTERNS = [
        '/<\?php/i',
        '/<\?=/i',
        '/<script\s+language\s*=\s*["\']?php["\']?/i',
        '/<%.*%>/i',
        '/<asp:/i',
        '/<jsp:/i',
        '/<cf/i',
    ];

    private PDM_Settings $settings;

    public function __construct(PDM_Settings $settings)
    {
        $this->settings = $settings;
    }

    public function validate_extension(string $extension): bool
    {
        $extension = strtolower(trim($extension));

        if (empty($extension)) {
            return false;
        }

        if (!preg_match('/^[a-z0-9]+$/i', $extension)) {
            return false;
        }

        if (in_array($extension, self::DANGEROUS_EXTENSIONS, true)) {
            return false;
        }

        $allowed = $this->settings->get_allowed_extensions();
        return in_array($extension, $allowed, true);
    }

    public function validate_mime_type(string $mimeType): bool
    {
        $mimeType = strtolower(trim($mimeType));

        if (in_array($mimeType, self::DANGEROUS_MIMES, true)) {
            return false;
        }

        return true;
    }

    public function validate_file_size(int $size): bool
    {
        $maxSize = $this->settings->get_max_file_size();
        return $size > 0 && $size <= $maxSize;
    }

    public function validate_folder_name(string $name): array
    {
        $errors = [];

        $name = trim($name);

        if (empty($name)) {
            $errors[] = __('The folder name cannot be empty.', 'private-document-manager');
        }

        if (mb_strlen($name) > 255) {
            $errors[] = __('The folder name is too long (maximum 255 characters).', 'private-document-manager');
        }

        $forbidden = ['..', '.', '/', '\\', "\0", "\t", "\n", "\r"];
        foreach ($forbidden as $char) {
            if (strpos($name, $char) !== false) {
                $errors[] = __('The folder name contains invalid characters.', 'private-document-manager');
                break;
            }
        }

        $reserved = ['con', 'prn', 'aux', 'nul', 'com1', 'com2', 'com3', 'com4', 'com5', 
                     'com6', 'com7', 'com8', 'com9', 'lpt1', 'lpt2', 'lpt3', 'lpt4', 
                     'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9'];
        if (in_array(strtolower($name), $reserved, true)) {
            $errors[] = __('This name is reserved by the system.', 'private-document-manager');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function validate_file_name(string $name): array
    {
        $errors = [];

        $name = trim($name);

        if (empty($name)) {
            $errors[] = __('The files name cannot be empty.', 'private-document-manager');
        }

        if (mb_strlen($name) > 255) {
            $errors[] = __('The files name is too long (maximum 255 characters).', 'private-document-manager');
        }

        $forbidden = ['..', '/', '\\', "\0", "\t", "\n", "\r"];
        foreach ($forbidden as $char) {
            if (strpos($name, $char) !== false) {
                $errors[] = __('The files name contains invalid characters.', 'private-document-manager');
                break;
            }
        }

        $reserved = ['con', 'prn', 'aux', 'nul', 'com1', 'com2', 'com3', 'com4', 'com5',
                     'com6', 'com7', 'com8', 'com9', 'lpt1', 'lpt2', 'lpt3', 'lpt4',
                     'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9'];
        if (in_array(strtolower($name), $reserved, true)) {
            $errors[] = __('This name is reserved by the system.', 'private-document-manager');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function validate_path_traversal(string $path): bool
    {
        $normalized = str_replace(['\\', '//'], '/', $path);
        
        if (strpos($normalized, '..') !== false) {
            return false;
        }

        if (strpos($normalized, "\0") !== false) {
            return false;
        }

        return true;
    }

    public function validate_upload(array $files): array
    {
        $errors = [];

        if (!isset($files['tmp_name']) || !is_uploaded_file($files['tmp_name'])) {
            $errors[] = __('Invalid file.', 'private-document-manager');
            return ['valid' => false, 'errors' => $errors];
        }

        if ($files['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->get_upload_error_message($files['error']);
            return ['valid' => false, 'errors' => $errors];
        }

        $extension = strtolower(pathinfo($files['name'], PATHINFO_EXTENSION));

        if ($this->has_dangerous_double_extension((string) ($files['name'] ?? ''))) {
            $errors[] = __('The files contains multiple disallowed extensions.', 'private-document-manager');
        }

        if (!$this->validate_extension($extension)) {
            $errors[] = sprintf(
                /* translators: %s: files extension. */
                __('Extension .%s is not allowed.', 'private-document-manager'),
                $extension
            );
        }

        if (!$this->validate_file_size($files['size'])) {
            $maxSize = PDM_Helpers::format_filesize($this->settings->get_max_file_size());
            $errors[] = sprintf(
                /* translators: %s: maximum allowed files size. */
                __('Size files superiore al limite consentito (%s).', 'private-document-manager'),
                $maxSize
            );
        }

        $detectedMime = 'application/octet-stream';
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo) {
            $detectedMime = finfo_file($finfo, $files['tmp_name']) ?: $detectedMime;
            finfo_close($finfo);
        }

        if (!$this->validate_mime_type($detectedMime)) {
            $errors[] = __('Type di files non consentito.', 'private-document-manager');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'extension' => $extension,
            'mime_type' => $detectedMime,
            'size' => $files['size'],
        ];
    }

    public function scan_file_content(string $filesPath): array
    {
        $errors = [];

        if (!file_exists($filesPath) || !is_readable($filesPath)) {
            return ['valid' => true, 'errors' => []];
        }

        $content = @file_get_contents($filesPath, false, null, 0, 65536);

        if ($content === false) {
            return ['valid' => true, 'errors' => []];
        }

        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $errors[] = __('The files contains potentially dangerous content.', 'private-document-manager');
                break;
            }
        }

        if (strpos($content, '<?php') !== false) {
            $errors[] = __('The files contains PHP code.', 'private-document-manager');
        }

        if (preg_match('/<\s*script\s+[^>]*language\s*=\s*["\']?\s*php\s*["\']?[^>]*>/i', $content)) {
            $errors[] = __('The files contains PHP scripts.', 'private-document-manager');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function validate_upload_full(array $files): array
    {
        $result = $this->validate_upload($files);

        if (!$result['valid']) {
            return $result;
        }

        if (isset($files['tmp_name']) && is_uploaded_file($files['tmp_name'])) {
            $scanResult = $this->scan_file_content($files['tmp_name']);

            if (!$scanResult['valid']) {
                $result['valid'] = false;
                $result['errors'] = array_merge($result['errors'] ?? [], $scanResult['errors']);
            }
        }

        if (class_exists('PDM_Hooks')) {
            $result = PDM_Hooks::filter_upload_validation($result, $files);
        }

        return $result;
    }

    private function has_dangerous_double_extension(string $filename): bool
    {
        $filename = strtolower(trim($filename));
        if ($filename === '' || strpos($filename, '.') === false) {
            return false;
        }

        $parts = array_values(array_filter(explode('.', $filename), static fn ($part) => $part !== ''));
        if (count($parts) < 3) {
            return false;
        }

        array_pop($parts);

        foreach ($parts as $part) {
            if (in_array($part, self::DANGEROUS_EXTENSIONS, true)) {
                return true;
            }
        }

        return false;
    }

    private function get_upload_error_message(int $errorCode): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => __('The files exceeds the maximum size configured on the server.', 'private-document-manager'),
            UPLOAD_ERR_FORM_SIZE => __('The files exceeds the maximum size configured in the form.', 'private-document-manager'),
            UPLOAD_ERR_PARTIAL => __('The files was only partially uploaded.', 'private-document-manager'),
            UPLOAD_ERR_NO_FILE => __('No file uploaded.', 'private-document-manager'),
            UPLOAD_ERR_NO_TMP_DIR => __('Folder temporanea mancante.', 'private-document-manager'),
            UPLOAD_ERR_CANT_WRITE => __('Unable to write the files to disk.', 'private-document-manager'),
            UPLOAD_ERR_EXTENSION => __('Upload stopped by a PHP extension.', 'private-document-manager'),
        ];

        return $messages[$errorCode] ?? __('Unknown upload error.', 'private-document-manager');
    }
}
