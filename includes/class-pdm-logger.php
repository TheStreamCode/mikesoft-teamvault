<?php

defined('ABSPATH') || exit;

class PDM_Logger
{
    private ?PDM_Repository_Logs $repo = null;

    public function __construct(?PDM_Repository_Logs $repo = null)
    {
        $this->repo = $repo;
    }

    public function log(
        string $action,
        string $targetType,
        ?int $targetId = null,
        array $context = []
    ): int {
        if (!$this->repo) {
            $this->repo = new PDM_Repository_Logs();
        }

        $settings = new PDM_Settings();
        if (!$settings->is_log_enabled()) {
            return 0;
        }

        return $this->repo->create([
            'user_id' => get_current_user_id(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'context' => $context,
        ]);
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
