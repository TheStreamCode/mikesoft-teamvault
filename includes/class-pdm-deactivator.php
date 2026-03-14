<?php

defined('ABSPATH') || exit;

class PDM_Deactivator
{
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
