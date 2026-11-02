<?php

namespace VeraTag\Features\FrontendServing\DisclosuresRendering;

/** Writes one backend-provided renderer file per sanitized file name under uploads/veratag/render-scripts/. */
final class RenderScriptFileWriter
{
    private const UPLOADS_SUBDIRECTORY        = 'veratag/render-scripts';
    private const RESERVED_FILE_NAMES         = array( '.htaccess', 'web.config' );
    private const SERVER_SIDE_FILE_EXTENSIONS = array( 'asp', 'aspx', 'cgi', 'phtml', 'phar', 'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'pl', 'py', 'shtml' );
    private bool $unsafe_files_purged         = false;

    public function write(string $file_name, string $script_data): bool
    {
        if (! $this->purgeUnsafeFiles()) {
            return false;
        }
        $target_path = $this->resolveFilePath($file_name);
        if (null === $target_path) {
            return false;
        }
        if (! wp_mkdir_p(dirname($target_path))) {
            return false;
        }
        return $this->replaceFileAtomically($target_path, $script_data);
    }
    public function getPublicUrl(string $file_name): ?string
    {
        if (! $this->purgeUnsafeFiles()) {
            return null;
        }
        $target_path = $this->resolveFilePath($file_name);
        if (null === $target_path || ! file_exists($target_path)) {
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
        if (null === $target_path || ! file_exists($target_path)) {
            return null;
        }
        $value = filemtime($target_path);
        return false === $value ? null : $value;
    }
    public function delete(string $file_name): bool
    {
        if (! $this->purgeUnsafeFiles()) {
            return false;
        }
        $target_path = $this->resolveFilePath($file_name);
        if (null === $target_path || ! file_exists($target_path)) {
            return true;
        }
        return unlink($target_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
    }
    /** Removes every renderer file owned by this plugin, along with its otherwise-empty directory. */
    public function deleteAll(): bool
    {
        $directory_path = $this->resolveDirectoryPath();
        if (null === $directory_path) {
            return false;
        }
        if (! is_dir($directory_path)) {
            return $this->removePluginDirectoryIfEmpty(dirname($directory_path));
        }

        $directory_entries = scandir($directory_path);
        if (false === $directory_entries) {
            return false;
        }
        foreach ($directory_entries as $directory_entry) {
            if ('.' === $directory_entry || '..' === $directory_entry) {
                continue;
            }
            $file_path = $directory_path . '/' . $directory_entry;
            if (! is_file($file_path) || ! unlink($file_path)) {
                return false;
            } // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
        }

        if (! rmdir($directory_path)) {
            return false;
        } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
        return $this->removePluginDirectoryIfEmpty(dirname($directory_path));
    }
    /** Removes uploads/veratag only when it has no entries left after renderer cleanup. */
    private function removePluginDirectoryIfEmpty(string $plugin_directory_path): bool
    {
        if (! is_dir($plugin_directory_path)) {
            return true;
        }
        $directory_entries = scandir($plugin_directory_path);
        if (false === $directory_entries) {
            return false;
        }
        if (array( '.', '..' ) !== $directory_entries) {
            return true;
        }
        return rmdir($plugin_directory_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
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
        if (! is_dir($directory_path)) {
            $this->unsafe_files_purged = true;
            return true;
        }

        $directory_entries = scandir($directory_path);
        if (false === $directory_entries) {
            return false;
        }
        foreach ($directory_entries as $directory_entry) {
            if ('.' === $directory_entry || '..' === $directory_entry) {
                continue;
            }
            $file_path = $directory_path . '/' . $directory_entry;
            if (! is_file($file_path)) {
                return false;
            }
            if ('' !== $this->sanitizeFileName($directory_entry)) {
                continue;
            }
            if (! unlink($file_path)) {
                return false;
            } // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
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
    /** Writes the complete renderer before replacing its public file in one rename operation. */
    private function replaceFileAtomically(string $target_path, string $script_data): bool
    {
        $temporary_path = $target_path . '.tmp-render-' . wp_generate_uuid4() . '.js';
        $temporary_file = fopen($temporary_path, 'x+b'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

        if (false === $temporary_file) {
            return false;
        }

        $expected_bytes = strlen($script_data);
        $written_bytes  = 0;

        while ($written_bytes < $expected_bytes) {
            $written = fwrite($temporary_file, substr($script_data, $written_bytes)); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

            if (false === $written || 0 === $written) {
                fclose($temporary_file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
                unlink($temporary_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                return false;
            }

            $written_bytes += $written;
        }

        if (! fflush($temporary_file)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fflush
            fclose($temporary_file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            unlink($temporary_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            return false;
        }

        if (! fclose($temporary_file)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            unlink($temporary_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            return false;
        }

        if (! rename($temporary_path, $target_path)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
            unlink($temporary_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            return false;
        }

        return true;
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
