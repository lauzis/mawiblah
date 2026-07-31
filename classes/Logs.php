<?php

namespace Mawiblah;

/**
 * Mawiblah's logging entry point.
 *
 * The implementation lives in the shared lauzis/wp-logs package; this class is
 * a thin facade that keeps Mawiblah's own API and settings semantics, so the
 * call sites throughout the plugin are unchanged.
 */
class Logs
{
    /** Log stream name — also the log filename prefix. */
    private const SLUG = 'mawiblah';

    /**
     * Returns the shared logger, or null when the wp-logs package is not
     * installed (e.g. a build shipped without vendor/). Logging then becomes a
     * silent no-op rather than a fatal.
     *
     * @return \Lauzis\WpLogs\Logger|null
     */
    private static function logger()
    {
        if (!class_exists('WpLogs_Registry')) {
            return null;
        }

        return \WpLogs_Registry::logger(
            self::SLUG,
            [
                'dir'     => MAWIBLAH_LOG_PATH,
                'enabled' => [self::class, 'enabled'],
            ]
        );
    }

    /**
     * Returns true when file logging is enabled in Settings.
     *
     * 'enable-db-log' is a legacy option value: it is not offered by the
     * settings page and there has never been a database log target, but it is
     * still honoured here so an option left over from an older version keeps
     * logging switched on rather than silently turning it off.
     */
    public static function enabled(): bool
    {
        return in_array(get_option('mawiblah-debug', false), ['enable-file-log', 'enable-db-log'], true);
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
        $logger = self::logger();

        return $logger ? $logger->add($action, $message, $additionalObjects) : false;
    }

    /**
     * Logs an error unconditionally — always writes to PHP's error_log, and
     * additionally to the Mawiblah log file when logging is enabled.
     *
     * Use this for failures that should never be silent.
     *
     * @param string $action            Short label.
     * @param string $message           Human-readable message.
     * @param array  $additionalObjects Key-value context to append as JSON.
     */
    public static function addError(string $action, string $message = '', array $additionalObjects = []): void
    {
        $logger = self::logger();

        if ($logger) {
            $logger->error($action, $message, $additionalObjects);
        }
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

        $logger = self::logger();
        if ($logger) {
            $logger->clear();
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

        $logger = self::logger();

        return $logger ? $logger->count() : 0;
    }

    /**
     * Returns a list of available daily log files with their dates and entry counts.
     *
     * @return array[] Each item: ['file' => string, 'date' => string, 'count' => int]
     */
    public static function getLogFiles(): array
    {
        $logger = self::logger();
        if (!$logger) {
            return [];
        }

        $result = [];

        foreach ($logger->files() as $file) {
            $result[] = [
                'file'  => $file['file'],
                'date'  => $file['date'],
                'count' => $file['count'],
            ];
        }

        return $result;
    }
}
