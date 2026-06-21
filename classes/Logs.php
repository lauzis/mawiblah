<?php

namespace Mawiblah;

class Logs
{
    /** Returns true when file logging is enabled in Settings. */
    public static function enabled(): bool
    {
        return get_option('mawiblah-debug', false) === 'enable-db-log';
    }

    /** Returns the absolute path to today's log file. */
    private static function filePath(): string
    {
        return MAWIBLAH_LOG_PATH . 'mawiblah-' . gmdate('Y-m-d') . '.log';
    }

    /**
     * Appends a log entry to today's log file if logging is enabled.
     *
     * Each entry is a single line: [timestamp] [action] message | {json context}
     *
     * @param string $action            Short label.
     * @param string $message           Human-readable message.
     * @param array  $additionalObjects Key-value context to append as JSON.
     * @return bool True on success, false if logging is disabled or write fails.
     */
    public static function addLog(string $action, string $message = '', array $additionalObjects = []): bool
    {
        if (!self::enabled()) {
            return false;
        }

        $dir = MAWIBLAH_LOG_PATH;
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        $timestamp = gmdate('Y-m-d H:i:s');
        $line      = "[{$timestamp}] [{$action}] {$message}";

        if (!empty($additionalObjects)) {
            $line .= ' | ' . wp_json_encode($additionalObjects);
        }

        return (bool) file_put_contents(self::filePath(), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Deletes all daily log files from the log directory.
     *
     * @return bool True on success, false if logging is disabled.
     */
    public static function clearLogs(): bool
    {
        if (!self::enabled()) {
            return false;
        }

        $files = glob(MAWIBLAH_LOG_PATH . 'mawiblah-*.log');
        if ($files) {
            array_map('unlink', $files);
        }

        return true;
    }

    /**
     * Returns the total number of log entries across all daily log files.
     *
     * @return int Total log entry count.
     */
    public static function getLogCount(): int
    {
        if (!self::enabled()) {
            return 0;
        }

        $count = 0;
        $files = glob(MAWIBLAH_LOG_PATH . 'mawiblah-*.log');

        if ($files) {
            foreach ($files as $file) {
                $count += count(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
            }
        }

        return $count;
    }

    /**
     * Returns a list of available daily log files with their dates and entry counts.
     *
     * @return array[] Each item: ['file' => string, 'date' => string, 'count' => int]
     */
    public static function getLogFiles(): array
    {
        $files  = glob(MAWIBLAH_LOG_PATH . 'mawiblah-*.log') ?: [];
        $result = [];

        rsort($files);

        foreach ($files as $file) {
            $date     = preg_replace('/^.*mawiblah-(.+)\.log$/', '$1', $file);
            $lines    = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $result[] = [
                'file'  => $file,
                'date'  => $date,
                'count' => count($lines ?: []),
            ];
        }

        return $result;
    }
}
