<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PDMBinaryStreamRangeTest extends TestCase
{
    private object $streamer;

    protected function setUp(): void
    {
        $this->streamer = new class {
            use MSTV_Binary_Stream;

            public function parse(string $header, int $fileSize): array|false|null
            {
                return $this->parse_range_header($header, $fileSize);
            }

            public function streamRange(string $path, int $start, int $length): bool
            {
                return $this->stream_absolute_file_range($path, $start, $length);
            }
        };
    }

    public function test_no_range_header_returns_null(): void
    {
        self::assertNull($this->streamer->parse('', 1000));
    }

    public function test_open_ended_range_reads_to_last_byte(): void
    {
        self::assertSame([500, 999], $this->streamer->parse('bytes=500-', 1000));
    }

    public function test_closed_range_is_parsed(): void
    {
        self::assertSame([0, 499], $this->streamer->parse('bytes=0-499', 1000));
    }

    public function test_range_end_beyond_size_is_clamped(): void
    {
        self::assertSame([900, 999], $this->streamer->parse('bytes=900-5000', 1000));
    }

    public function test_suffix_range_returns_last_n_bytes(): void
    {
        self::assertSame([900, 999], $this->streamer->parse('bytes=-100', 1000));
    }

    public function test_suffix_larger_than_file_returns_whole_file(): void
    {
        self::assertSame([0, 999], $this->streamer->parse('bytes=-5000', 1000));
    }

    public function test_start_beyond_size_is_unsatisfiable(): void
    {
        self::assertFalse($this->streamer->parse('bytes=1000-1100', 1000));
    }

    public function test_inverted_range_is_unsatisfiable(): void
    {
        self::assertFalse($this->streamer->parse('bytes=800-500', 1000));
    }

    public function test_multipart_or_unknown_unit_falls_back_to_full_response(): void
    {
        self::assertNull($this->streamer->parse('bytes=0-100,200-300', 1000));
        self::assertNull($this->streamer->parse('items=0-100', 1000));
    }

    public function test_stream_range_writes_exact_bytes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mstv-range');
        file_put_contents($path, '0123456789');

        try {
            ob_start();
            $ok = $this->streamer->streamRange($path, 2, 4);
            $output = ob_get_clean();

            self::assertTrue($ok);
            self::assertSame('2345', $output);
        } finally {
            unlink($path);
        }
    }
}
