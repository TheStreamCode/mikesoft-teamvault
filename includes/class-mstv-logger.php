<?php

defined('ABSPATH') || exit;

class MSTV_Logger
{
    private ?MSTV_Repository_Logs $repo = null;
    private ?MSTV_Settings $settings = null;

    public function __construct(?MSTV_Repository_Logs $repo = null, ?MSTV_Settings $settings = null)
    {
        $this->repo = $repo;
        $this->settings = $settings;
    }

    public function log(
        string $action,
        string $targetType,
        ?int $targetId = null,
        array $context = []
    ): int {
        if (!$this->repo) {
            $this->repo = new MSTV_Repository_Logs();
        }

        if (!$this->settings) {
            $this->settings = new MSTV_Settings();
        }

        if (!$this->settings->is_log_enabled()) {
            return 0;
        }

        return $this->repo->create([
            'user_id' => get_current_user_id(),
            'action' => $action,
            'target_type' => self::normalize_target_type($targetType),
            'target_id' => $targetId,
            'context' => $context,
        ]);
    }

    public static function normalize_target_type(string $targetType): string
    {
        $targetType = strtolower(trim($targetType));

        if ($targetType === 'files') {
            return 'file';
        }

        return $targetType === 'folder' ? 'folder' : 'file';
    }

    public function log_upload(int $fileId, string $filename): int
    {
        return $this->log('upload', 'file', $fileId, ['filename' => $filename]);
    }

    public function log_download(int $fileId, string $filename): int
    {
        return $this->log('download', 'file', $fileId, ['filename' => $filename]);
    }

    public function log_delete(string $type, int $id, string $name): int
    {
        return $this->log('delete', $type, $id, ['name' => $name]);
    }

    public function log_move(int $fileId, string $filename, ?int $fromFolder, ?int $toFolder): int
    {
        return $this->log('move', 'file', $fileId, [
            'filename' => $filename,
            'from_folder' => $fromFolder,
            'to_folder' => $toFolder,
        ]);
    }

    public function log_rename(string $type, int $id, string $oldName, string $newName): int
    {
        return $this->log('rename', $type, $id, [
            'old_name' => $oldName,
            'new_name' => $newName,
        ]);
    }

    public function log_folder_create(int $folderId, string $name): int
    {
        return $this->log('create', 'folder', $folderId, ['name' => $name]);
    }
}
