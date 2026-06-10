<?php

defined('ABSPATH') || exit;

/**
 * Provides chunked binary file streaming for authenticated download and preview handlers.
 *
 * Uses 1 MB chunks so large files are never loaded fully into memory. Must only be applied
 * to classes that have already verified the request (capability, nonce, path) before calling
 * stream_absolute_file().
 */
trait MSTV_Binary_Stream
{
    /**
     * Write a binary file to the output buffer in 1 MB chunks.
     *
     * @param string $path Verified absolute filesystem path (must pass verify_path() before calling).
     * @return bool True on success, false on any I/O error.
     */
    private function stream_absolute_file(string $path): bool
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Authenticated binary streaming needs chunked output; WP_Filesystem::get_contents() loads the full file into memory.
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        while (!feof($handle)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Chunked binary streaming.
            $chunk = fread($handle, 1048576);
            if ($chunk === false) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing a local stream opened only for chunked binary output.
                fclose($handle);
                return false;
            }

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary file stream output must not be escaped.
            echo $chunk;
            flush();
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing a local stream opened only for chunked binary output.
        fclose($handle);

        return true;
    }
}
