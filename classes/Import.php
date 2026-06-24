<?php

namespace Mawiblah;

/**
 * Handles CSV subscriber import: file parsing, preview, and row processing.
 */
class Import
{
    /** Transient key prefix for storing uploaded CSV data between the preview and confirm steps. */
    const TRANSIENT_PREFIX = 'mawiblah_import_';

    /**
     * Parses a CSV file and returns an array of rows (each row is an array of strings).
     * Accepts comma, semicolon, or tab delimiters (auto-detected from the first line).
     *
     * @param string $filePath Absolute path to the uploaded CSV file.
     * @param int    $limit    Maximum rows to return (0 = all).
     * @return array<int, array<int, string>>
     */
    public static function parseFile(string $filePath, int $limit = 0): array
    {
        $rows      = [];
        $handle    = @fopen($filePath, 'r');
        if (!$handle) {
            return [];
        }

        $firstLine = fgets($handle);
        rewind($handle);

        // Auto-detect delimiter
        $delimiter = ',';
        $counts    = [
            ','  => substr_count($firstLine, ','),
            ';'  => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
        ];
        arsort($counts);
        $delimiter = array_key_first($counts);

        $count = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === [null]) {
                continue; // skip empty lines
            }
            $rows[] = array_map('trim', $row);
            $count++;
            if ($limit > 0 && $count >= $limit) {
                break;
            }
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Processes an import: reads the stored CSV data from the transient and imports each row.
     *
     * @param string $transientKey  Key returned by storeFile().
     * @param int    $emailColumn   Zero-based column index that contains the email address.
     * @param bool   $hasHeaders    True if the first row is a header row (should be skipped).
     * @param array  $audienceIds   Term IDs of audiences to assign each imported subscriber.
     * @param string $duplicateMode 'skip' | 'overwrite' | 'merge'
     * @return array{imported:int, skipped:int, updated:int, errors:array<string>}
     */
    public static function processImport(
        string $transientKey,
        int    $emailColumn,
        bool   $hasHeaders,
        array  $audienceIds,
        string $duplicateMode
    ): array {
        $rows = get_transient($transientKey);
        if (!$rows || !is_array($rows)) {
            return ['imported' => 0, 'skipped' => 0, 'updated' => 0, 'errors' => ['Session expired — please upload the file again.']];
        }
        delete_transient($transientKey);

        $unsubedAudience = Subscribers::unsubedAudience();

        $result = ['imported' => 0, 'skipped' => 0, 'updated' => 0, 'errors' => []];
        $startRow = $hasHeaders ? 1 : 0;

        for ($i = $startRow; $i < count($rows); $i++) {
            $row = $rows[$i];

            if (!isset($row[$emailColumn])) {
                continue;
            }

            $email = sanitize_email(trim($row[$emailColumn]));
            if (!is_email($email)) {
                $result['errors'][] = 'Row ' . ($i + 1) . ': invalid email "' . esc_html($row[$emailColumn]) . '"';
                continue;
            }

            $existing = Subscribers::getSubscriber($email);

            if ($existing) {
                if ($duplicateMode === 'skip') {
                    $result['skipped']++;
                    continue;
                }

                if ($duplicateMode === 'overwrite') {
                    // Replace all existing audience memberships with only the selected ones.
                    wp_set_post_terms($existing->id, [], Subscribers::postType() . '_category', false);
                }

                foreach ($audienceIds as $audienceId) {
                    $audienceId = (int) $audienceId;
                    if ($audienceId > 0) {
                        Subscribers::addSubscriberToAudience($existing->id, $audienceId);
                    }
                }
                $result['updated']++;
            } else {
                $subscriber = Subscribers::addSubscriber($email);
                if (!$subscriber || !$subscriber->id) {
                    $result['errors'][] = 'Row ' . ($i + 1) . ': could not create subscriber for "' . esc_html($email) . '"';
                    continue;
                }
                foreach ($audienceIds as $audienceId) {
                    $audienceId = (int) $audienceId;
                    if ($audienceId > 0) {
                        Subscribers::addSubscriberToAudience($subscriber->id, $audienceId);
                    }
                }
                $result['imported']++;
            }
        }

        return $result;
    }

    /**
     * Stores all CSV rows in a short-lived transient and returns the transient key.
     *
     * @param array $rows All rows from the parsed CSV.
     * @return string Transient key.
     */
    public static function storeRows(array $rows): string
    {
        $key = self::TRANSIENT_PREFIX . wp_generate_password(12, false);
        set_transient($key, $rows, 30 * MINUTE_IN_SECONDS);
        return $key;
    }
}
