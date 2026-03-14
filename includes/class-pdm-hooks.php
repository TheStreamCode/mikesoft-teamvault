<?php

defined('ABSPATH') || exit;

class PDM_Hooks
{
    public const FILE_UPLOADED = 'pdm_file_uploaded';
    public const FILE_DELETED = 'pdm_file_deleted';
    public const FILE_RENAMED = 'pdm_file_renamed';
    public const FILE_MOVED = 'pdm_file_moved';
    public const FILE_DOWNLOADED = 'pdm_file_downloaded';
    public const FILE_PREVIEWED = 'pdm_file_previewed';

    public const FOLDER_CREATED = 'pdm_folder_created';
    public const FOLDER_DELETED = 'pdm_folder_deleted';
    public const FOLDER_RENAMED = 'pdm_folder_renamed';
    public const FOLDER_MOVED = 'pdm_folder_moved';

    public const EXPORT_STARTED = 'pdm_export_started';
    public const EXPORT_COMPLETED = 'pdm_export_completed';

    public const FILTER_ALLOWED_EXTENSIONS = 'pdm_allowed_extensions';
    public const FILTER_MAX_FILE_SIZE = 'pdm_max_file_size';
    public const FILTER_STORAGE_PATH = 'pdm_storage_path';
    public const FILTER_FILE_NAME = 'pdm_file_name';
    public const FILTER_UPLOAD_VALIDATION = 'pdm_upload_validation';

    public static function do_file_uploaded(int $fileId, array $fileData): void
    {
        do_action(self::FILE_UPLOADED, $fileId, $fileData);
    }

    public static function do_file_deleted(int $fileId, array $fileData): void
    {
        do_action(self::FILE_DELETED, $fileId, $fileData);
    }

    public static function do_file_renamed(int $fileId, string $oldName, string $newName): void
    {
        do_action(self::FILE_RENAMED, $fileId, $oldName, $newName);
    }

    public static function do_file_moved(int $fileId, ?int $oldFolderId, ?int $newFolderId): void
    {
        do_action(self::FILE_MOVED, $fileId, $oldFolderId, $newFolderId);
    }

    public static function do_file_downloaded(int $fileId, array $fileData): void
    {
        do_action(self::FILE_DOWNLOADED, $fileId, $fileData);
    }

    public static function do_file_previewed(int $fileId, array $fileData): void
    {
        do_action(self::FILE_PREVIEWED, $fileId, $fileData);
    }

    public static function do_folder_created(int $folderId, array $folderDate): void
    {
        do_action(self::FOLDER_CREATED, $folderId, $folderDate);
    }

    public static function do_folder_deleted(int $folderId, string $folderName): void
    {
        do_action(self::FOLDER_DELETED, $folderId, $folderName);
    }

    public static function do_folder_renamed(int $folderId, string $oldName, string $newName): void
    {
        do_action(self::FOLDER_RENAMED, $folderId, $oldName, $newName);
    }

    public static function do_folder_moved(int $folderId, ?int $oldParentId, ?int $newParentId): void
    {
        do_action(self::FOLDER_MOVED, $folderId, $oldParentId, $newParentId);
    }

    public static function do_export_started(?int $folderId, string $zipPath): void
    {
        do_action(self::EXPORT_STARTED, $folderId, $zipPath);
    }

    public static function do_export_completed(?int $folderId, string $zipPath, int $filesCount): void
    {
        do_action(self::EXPORT_COMPLETED, $folderId, $zipPath, $filesCount);
    }

    public static function filter_allowed_extensions(array $extensions): array
    {
        return apply_filters(self::FILTER_ALLOWED_EXTENSIONS, $extensions);
    }

    public static function filter_max_file_size(int $bytes): int
    {
        return apply_filters(self::FILTER_MAX_FILE_SIZE, $bytes);
    }

    public static function filter_storage_path(string $path): string
    {
        return apply_filters(self::FILTER_STORAGE_PATH, $path);
    }

    public static function filter_file_name(string $name, string $originalName): string
    {
        return apply_filters(self::FILTER_FILE_NAME, $name, $originalName);
    }

    public static function filter_upload_validation(array $result, array $files): array
    {
        return apply_filters(self::FILTER_UPLOAD_VALIDATION, $result, $files);
    }
}