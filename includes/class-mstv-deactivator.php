<?php

defined('ABSPATH') || exit;

class MSTV_Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
