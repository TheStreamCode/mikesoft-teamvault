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
     * Stream a verified file honoring an optional HTTP Range request.
     *
     * Always advertises `Accept-Ranges: bytes`. When the client sends a single,
     * satisfiable `Range` header the response is `206 Partial Content` with the
     * matching `Content-Range`; an unsatisfiable range yields `416`; otherwise the
     * whole file is streamed with a `200`. The caller must have already sent
     * Content-Type / Content-Disposition and verified the path.
     *
     * @param string $path     Verified absolute filesystem path.
     * @param int    $fileSize Total size of the file in bytes.
     * @return bool True when the response was emitted, false on an I/O error.
     */
    private function stream_binary(string $path, int $fileSize): bool
    {
        header('Accept-Ranges: bytes');

        $rangeHeader = isset($_SERVER['HTTP_RANGE'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_RANGE']))
            : '';
        $range = $this->parse_range_header($rangeHeader, $fileSize);

        if ($range === false) {
            // Range present but unsatisfiable.
            http_response_code(416);
            header('Content-Range: bytes */' . $fileSize);
            header('Content-Length: 0');

            return true;
        }

        if ($range === null) {
            // No range: full-content response (prior behavior).
            header('Content-Length: ' . $fileSize);

            return $this->stream_absolute_file($path);
        }

        [$start, $end] = $range;
        $length = $end - $start + 1;

        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
        header('Content-Length: ' . $length);

        return $this->stream_absolute_file_range($path, $start, $length);
    }

    /**
     * Parse a single-range HTTP `Range` header against the file size.
     *
     * @return array{0:int,1:int}|false|null [start, end] for a satisfiable range,
     *                                        false when unsatisfiable, null when there
     *                                        is no usable single-range request.
     */
    private function parse_range_header(string $header, int $fileSize): array|false|null
    {
        $header = trim($header);

        if ($header === '' || $fileSize <= 0) {
            return null;
        }

        // Only a single "bytes=start-end" range is supported; anything else
        // (multipart ranges, other units) falls back to a full-content response.
        if (!preg_match('/^bytes=(\d*)-(\d*)$/', $header, $m)) {
            return null;
        }

        $startRaw = $m[1];
        $endRaw = $m[2];

        if ($startRaw === '' && $endRaw === '') {
            return null;
        }

        if ($startRaw === '') {
            // Suffix range: the final N bytes.
            $suffix = (int) $endRaw;
            if ($suffix <= 0) {
                return false;
            }

            $suffix = min($suffix, $fileSize);
            $start = $fileSize - $suffix;
            $end = $fileSize - 1;

            return [$start, $end];
        }

        $start = (int) $startRaw;
        $end = $endRaw === '' ? $fileSize - 1 : (int) $endRaw;

        if ($end >= $fileSize) {
            $end = $fileSize - 1;
        }

        if ($start > $end || $start >= $fileSize) {
            return false;
        }

        return [$start, $end];
    }

    /**
     * Write a byte range of a binary file to the output buffer in 1 MB chunks.
     *
     * @param string $path   Verified absolute filesystem path.
     * @param int    $start  First byte offset to send.
     * @param int    $length Number of bytes to send.
     * @return bool True on success, false on any I/O error.
     */
    private function stream_absolute_file_range(string $path, int $start, int $length): bool
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Authenticated binary streaming needs chunked output; WP_Filesystem::get_contents() loads the full file into memory.
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        if (fseek($handle, $start) !== 0) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing a local stream opened only for chunked binary output.
            fclose($handle);
            return false;
        }

        $remaining = $length;

        while ($remaining > 0 && !feof($handle)) {
            $read = $remaining > 1048576 ? 1048576 : $remaining;
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Chunked binary streaming.
            $chunk = fread($handle, $read);
            if ($chunk === false) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing a local stream opened only for chunked binary output.
                fclose($handle);
                return false;
            }

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary file stream output must not be escaped.
            echo $chunk;
            flush();
            $remaining -= strlen($chunk);
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing a local stream opened only for chunked binary output.
        fclose($handle);

        return true;
    }

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
