<?php

namespace Mawiblah;

/**
 * Mawiblah's logging entry point.
 *
 * The implementation lives in the shared lauzis/wp-plugin-packages package; this class is
 * a thin facade that keeps Mawiblah's own API and settings semantics, so the
 * call sites throughout the plugin are unchanged.
 */
class Logs
{
    /** Log stream name — also the log filename prefix. */
    private const SLUG = 'mawiblah';

    /**
     * Returns the shared logger, or null when the wp-plugin-packages package is not
     * installed (e.g. a build shipped without vendor/). Logging then becomes a
     * silent no-op rather than a fatal.
     *
     * @return \Lauzis\WpPackages\Logs\Logger|null
     */
    private static function logger()
    {
        if (!class_exists('WpPackages_Registry')) {
            return null;
        }

        return \WpPackages_Registry::logger(
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
        return in_array(Settings::getOption('mawiblah-debug'), ['enable-file-log', 'enable-db-log'], true);
    }

    /**
     * Returns the Slack test-button component, or null when the package is
     * absent or too old to have one.
     *
     * @return \Lauzis\WpPackages\Logs\SlackTester|null
     */
    public static function slackTester()
    {
        static $tester = null;

        if (null !== $tester) {
            return $tester;
        }

        $logger = self::logger();

        if (!$logger || !class_exists('\\Lauzis\\WpPackages\\Logs\\SlackTester')) {
            return null;
        }

        $tester = new \Lauzis\WpPackages\Logs\SlackTester($logger);

        return $tester;
    }

    /**
     * Posts a test message to the configured Slack webhook and waits for the
     * answer. Used by the settings button and by the Tests page.
     *
     * @param string $url Webhook to test instead of the configured one.
     * @return true|string True on success, otherwise the reason it failed.
     */
    public static function slackTest(string $url = '')
    {
        if (!class_exists('WpPackages_Registry')) {
            return 'The shared logging package is not installed on this site. Run "composer install" in the plugin directory.';
        }

        $logger = self::logger();

        if (!$logger || !method_exists($logger, 'slackTest')) {
            // Two different numbers, and the difference is the diagnosis. The
            // library is loaded once per request by whichever plugin reaches it
            // first, so a sibling that logs during its own bootstrap can lock in
            // an old copy while a newer one sits installed and merely registered.
            // Reporting the installed version alone produced the nonsense
            // "in use is 1.16.0, and Slack needs 1.15.0 or newer".
            $installed = \WpPackages_Registry::active_version();
            $running   = defined('\Lauzis\WpPackages\Notices\Assets::VERSION')
                ? \Lauzis\WpPackages\Notices\Assets::VERSION
                : 'unknown';

            if ($installed && $running !== $installed && 'unknown' !== $running) {
                return sprintf(
                    'Slack needs 1.15.0 or newer of the shared package. %1$s is installed, but %2$s is the copy actually running: something loaded the library before plugins_loaded — a plugin logging during its own bootstrap is the usual cause — and PHP keeps whichever copy got there first. Update every lauzis plugin on this site so no copy older than 1.15.0 is left to win that race.',
                    $installed,
                    $running
                );
            }

            return sprintf(
                'The shared logging package running here is %s, and Slack needs 1.15.0 or newer. Run "composer install" for this plugin, and for any other lauzis plugin on the site — the copy that loads first is the one WordPress uses, whichever plugin it belongs to.',
                'unknown' !== $running ? $running : ($installed ? $installed : 'missing')
            );
        }

        return $logger->slackTest($url);
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
