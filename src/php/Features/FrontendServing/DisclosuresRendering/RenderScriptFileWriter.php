<?php

namespace VeraTag\Features\FrontendServing\DisclosuresRendering;

use VeraTag\Filesystem\FileOperations;

/** Writes one backend-provided renderer file per sanitized file name under uploads/veratag/render-scripts/. */
final class RenderScriptFileWriter
{
    private const UPLOADS_SUBDIRECTORY        = 'veratag/render-scripts';
    private const RESERVED_FILE_NAMES         = array( '.htaccess', 'web.config' );
    private const SERVER_SIDE_FILE_EXTENSIONS = array( 'asp', 'aspx', 'cgi', 'phtml', 'phar', 'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'pl', 'py', 'shtml' );
    private bool $unsafe_files_purged         = false;

    public function __construct(private FileOperations $file_operations)
    {
    }

    public function write(string $file_name, string $script_data): bool
    {
        if (! $this->purgeUnsafeFiles()) {
            return false;
        }
        $target_path = $this->resolveFilePath($file_name);
        if (null === $target_path) {
            return false;
        }
        if (! $this->file_operations->createDirectory(dirname($target_path))) {
            return false;
        }
        return $this->file_operations->writeAtomically($target_path, $script_data);
    }
    public function getPublicUrl(string $file_name): ?string
    {
        if (! $this->purgeUnsafeFiles()) {
            return null;
        }
        $target_path = $this->resolveFilePath($file_name);
        if (null === $target_path || ! $this->file_operations->fileExists($target_path)) {
            return null;
        }
        $upload_dir = wp_upload_dir();
        if (! empty($upload_dir['error'])) {
            return null;
        }
        return trailingslashit((string) $upload_dir['baseurl']) . self::UPLOADS_SUBDIRECTORY . '/' . $this->sanitizeFileName($file_name);
    }
    public function getFileModifiedTime(string $file_name): ?int
    {
        if (! $this->purgeUnsafeFiles()) {
            return null;
        }
        $target_path = $this->resolveFilePath($file_name);
        if (null === $target_path || ! $this->file_operations->fileExists($target_path)) {
            return null;
        }
        return $this->file_operations->getModifiedTime($target_path);
    }
    public function delete(string $file_name): bool
    {
        if (! $this->purgeUnsafeFiles()) {
            return false;
        }
        $target_path = $this->resolveFilePath($file_name);
        if (null === $target_path || ! $this->file_operations->fileExists($target_path)) {
            return true;
        }
        return $this->file_operations->deleteFileWithinDirectory($target_path, dirname($target_path));
    }
    /** Removes every renderer file owned by this plugin while retaining its directory. */
    public function deleteAll(): bool
    {
        $directory_path = $this->resolveDirectoryPath();
        if (null === $directory_path) {
            return false;
        }
        if (! $this->file_operations->directoryExists($directory_path)) {
            return true;
        }

        $directory_entries = $this->file_operations->listDirectory($directory_path);
        if (null === $directory_entries) {
            return false;
        }
        foreach ($directory_entries as $directory_entry) {
            $file_path = $directory_path . '/' . $directory_entry;
            if (
                ! $this->file_operations->isRegularFile($file_path)
                || ! $this->file_operations->deleteFileWithinDirectory($file_path, $directory_path)
            ) {
                return false;
            }
        }
        return true;
    }
    /** Removes pre-existing non-JavaScript files left by earlier plugin versions. */
    private function purgeUnsafeFiles(): bool
    {
        if ($this->unsafe_files_purged) {
            return true;
        }
        $directory_path = $this->resolveDirectoryPath();
        if (null === $directory_path) {
            return false;
        }
        if (! $this->file_operations->directoryExists($directory_path)) {
            $this->unsafe_files_purged = true;
            return true;
        }

        $directory_entries = $this->file_operations->listDirectory($directory_path);
        if (null === $directory_entries) {
            return false;
        }
        foreach ($directory_entries as $directory_entry) {
            $file_path = $directory_path . '/' . $directory_entry;
            if (! $this->file_operations->isRegularFile($file_path)) {
                return false;
            }
            if ('' !== $this->sanitizeFileName($directory_entry)) {
                continue;
            }
            if (! $this->file_operations->deleteFileWithinDirectory($file_path, $directory_path)) {
                return false;
            }
        }

        $this->unsafe_files_purged = true;
        return true;
    }
    private function resolveFilePath(string $file_name): ?string
    {
        $sanitized = $this->sanitizeFileName($file_name);
        if ('' === $sanitized) {
            return null;
        }
        $directory_path = $this->resolveDirectoryPath();
        if (null === $directory_path) {
            return null;
        }
        return $directory_path . '/' . $sanitized;
    }
    private function resolveDirectoryPath(): ?string
    {
        $upload_dir = wp_upload_dir();
        if (! empty($upload_dir['error'])) {
            return null;
        }
        return untrailingslashit((string) $upload_dir['basedir']) . '/' . self::UPLOADS_SUBDIRECTORY;
    }
    private function sanitizeFileName(string $file_name): string
    {
        $base_name = basename(trim($file_name));
        if ('' === $base_name || in_array(strtolower($base_name), self::RESERVED_FILE_NAMES, true)) {
            return '';
        }
        $sanitized = sanitize_file_name($base_name);
        if ('' === $sanitized || 'js' !== strtolower(pathinfo($sanitized, PATHINFO_EXTENSION))) {
            return '';
        }
        foreach (explode('.', strtolower($sanitized)) as $file_name_part) {
            if (in_array($file_name_part, self::SERVER_SIDE_FILE_EXTENSIONS, true)) {
                return '';
            }
        }
        return $sanitized;
    }
}
