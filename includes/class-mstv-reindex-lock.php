<?php

defined('ABSPATH') || exit;

/**
 * Atomic claim that serialises the automatic storage reindex.
 *
 * The previous implementation used a transient as a best-effort rate limiter:
 * two concurrent requests could both miss the transient and run the full scan,
 * and a request that died mid-scan left no ownership record. This claim is
 * created with add_option(), which is atomic at the database level (a second
 * writer gets a duplicate-key failure), and the owner releases it when the scan
 * fails so a transient error cannot block the self-healing path.
 */
class MSTV_Reindex_Lock
{
    public const TTL = 300;

    private const OPTION_PREFIX = 'mstv_auto_reindex_claim_';

    private int $blogId;

    public function __construct(?int $blogId = null)
    {
        $this->blogId = $blogId !== null && $blogId > 0 ? $blogId : (int) get_current_blog_id();
    }

    public function option_name(): string
    {
        return self::OPTION_PREFIX . $this->blogId;
    }

    /**
     * Try to become the reindex owner.
     *
     * Returns false while another request holds a fresh claim. A stale claim
     * (older than TTL) is taken over so a crashed request cannot block the
     * self-healing path forever; add_option() still admits exactly one owner.
     */
    public function acquire(): bool
    {
        $now = time();

        if (add_option($this->option_name(), (string) $now, '', false)) {
            return true;
        }

        $claimedAt = (int) get_option($this->option_name(), 0);

        if ($claimedAt > 0 && ($now - $claimedAt) < self::TTL) {
            return false;
        }

        delete_option($this->option_name());

        return (bool) add_option($this->option_name(), (string) $now, '', false);
    }

    /**
     * Release the claim so the next request can retry immediately. A successful
     * scan keeps the claim until it goes stale, which throttles repeated scans
     * of a storage directory whose files are all rejected by the upload policy.
     */
    public function release(): void
    {
        delete_option($this->option_name());
    }
}
