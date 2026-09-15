<?php

namespace Mawiblah;

use ZBateson\MailMimeParser\Message;

/**
 * Reads a bounce -- a delivery status notification -- out of a raw e-mail.
 *
 * Only a report carrying a message/delivery-status part (RFC 3464) counts. An
 * out-of-office reply, a spam challenge or a person answering the newsletter has
 * none, and is not a bounce however much its wording looks like one, so nothing
 * is guessed from subject lines or text. The Auto-Submitted header is no help
 * either way: Postfix puts `auto-replied` on its own bounces.
 *
 * Pure input to output -- no WordPress, no database, no mailbox.
 */
class BounceParser
{
    /** Permanent failure (5.x.x): the address does not exist or refuses us. */
    public const KIND_HARD = 'hard';

    /** Temporary failure (4.x.x): mailbox full, server down, greylisted. */
    public const KIND_SOFT = 'soft';

    /** Headers every campaign e-mail carries, quoted back by most bounces. */
    public const HEADER_CAMPAIGN   = 'X-Mawiblah-Campaign';
    public const HEADER_SUBSCRIBER = 'X-Mawiblah-Subscriber';

    /** Content types a delivery report arrives in. */
    private const REPORT_TYPES = ['message/delivery-status', 'message/global-delivery-status'];

    /** Content types the bounced message, or its headers, is quoted back in. */
    private const ORIGINAL_TYPES = ['message/rfc822', 'text/rfc822-headers', 'message/global', 'message/global-headers'];

    /**
     * The headers that let a bounce name its campaign and subscriber.
     *
     * Most bounce reports quote the original message's headers back, and the
     * address alone does not always find the subscriber -- a forward or an alias
     * rewrites it. Hashes rather than ids or addresses, so the headers tell
     * whoever reads them nothing.
     *
     * @return string[] Header lines. One whose hash is missing is left out.
     */
    public static function trackingHeaders(string $campaignHash, string $subscriberHash): array
    {
        $headers = [];

        if (preg_match('/^[a-f0-9]{32}$/', $campaignHash)) {
            $headers[] = self::HEADER_CAMPAIGN . ': ' . $campaignHash;
        }

        if (preg_match('/^[a-f0-9]{32}$/', $subscriberHash)) {
            $headers[] = self::HEADER_SUBSCRIBER . ': ' . $subscriberHash;
        }

        return $headers;
    }

    /**
     * Parses a raw message.
     *
     * @param string $raw The whole message, headers and body.
     * @return array|null Null when the message is not a bounce. Otherwise:
     *   - recipients:      list of ['email', 'status', 'action', 'kind', 'reason'],
     *                      one per recipient that failed or was delayed
     *   - campaignHash:    from the quoted original, '' when absent
     *   - subscriberHash:  from the quoted original, '' when absent
     *   - originalSubject: subject of the message that bounced, '' when absent
     *   - reportingMta:    the server that wrote the report, '' when absent
     */
    public static function parse(string $raw): ?array
    {
        if (trim($raw) === '') {
            return null;
        }

        try {
            $message = Message::from($raw, false);
        } catch (\Throwable $e) {
            return null;
        }

        $report   = null;
        $original = '';

        foreach ($message->getAllParts() as $part) {
            $type = strtolower($part->getContentType(''));

            if ($report === null && in_array($type, self::REPORT_TYPES, true)) {
                $report = (string) $part->getContent();
            } elseif ($original === '' && in_array($type, self::ORIGINAL_TYPES, true)) {
                $original = (string) $part->getContent();
            }
        }

        if ($report === null) {
            return null;
        }

        $recipients   = [];
        $reportingMta = '';

        foreach (self::fieldGroups($report) as $fields) {
            if ($reportingMta === '' && isset($fields['reporting-mta'])) {
                $reportingMta = self::stripType($fields['reporting-mta']);
            }

            // Original-Recipient is the address as we sent it; Final-Recipient
            // is where the server ended up, which an alias or forward can change.
            $email = self::address($fields['original-recipient'] ?? '')
                ?: self::address($fields['final-recipient'] ?? '');

            if ($email === '') {
                continue;
            }

            $action = strtolower(trim($fields['action'] ?? ''));
            $status = self::statusCode($fields['status'] ?? '');
            $kind   = self::kind($action, $status);

            if ($kind === null) {
                continue;
            }

            $recipients[] = [
                'email'  => $email,
                'status' => $status,
                'action' => $action,
                'kind'   => $kind,
                'reason' => self::reason($fields['diagnostic-code'] ?? ''),
            ];
        }

        if (!$recipients) {
            return null;
        }

        $quoted = self::quotedHeaders($original);

        return [
            'recipients'      => $recipients,
            'campaignHash'    => $quoted['campaignHash'],
            'subscriberHash'  => $quoted['subscriberHash'],
            'originalSubject' => $quoted['subject'],
            'reportingMta'    => $reportingMta,
        ];
    }

    /**
     * Splits a delivery-status body into its field groups.
     *
     * The first group describes the message, each further one a recipient.
     * Field names are lower-cased; a folded line continues the field above it.
     *
     * @param string $report
     * @return array[]
     */
    private static function fieldGroups(string $report): array
    {
        $report = str_replace(["\r\n", "\r"], "\n", $report);
        $groups = [];

        foreach (preg_split('/\n[ \t]*\n/', trim($report)) as $block) {
            $fields = [];
            $name   = null;

            foreach (explode("\n", $block) as $line) {
                if ($name !== null && preg_match('/^[ \t]+\S/', $line)) {
                    $fields[$name] .= ' ' . trim($line);
                    continue;
                }

                if (!preg_match('/^([A-Za-z0-9-]+)[ \t]*:[ \t]*(.*)$/', $line, $match)) {
                    $name = null;
                    continue;
                }

                $name = strtolower($match[1]);

                // A repeated field keeps its first value, and its continuation
                // lines are dropped with it.
                if (isset($fields[$name])) {
                    $name = null;
                    continue;
                }

                $fields[$name] = trim($match[2]);
            }

            if ($fields) {
                $groups[] = $fields;
            }
        }

        return $groups;
    }

    /** "rfc822; Someone@Example.com" -> "someone@example.com", or '' when there is no address. */
    private static function address(string $value): string
    {
        $address = strtolower(trim(self::stripType($value), " \t<>"));

        return str_contains($address, '@') ? $address : '';
    }

    /** Drops the "rfc822;" / "dns;" / "smtp;" type label a DSN field starts with. */
    private static function stripType(string $value): string
    {
        return trim(preg_replace('/^[A-Za-z0-9-]+[ \t]*;/', '', trim($value), 1));
    }

    /** The x.y.z status code, or '' when the field holds none. */
    private static function statusCode(string $value): string
    {
        return preg_match('/\b([245]\.\d{1,3}\.\d{1,3})\b/', $value, $match) ? $match[1] : '';
    }

    /**
     * Hard, soft, or null for a recipient that was not a failure at all.
     *
     * The status code decides when there is one: an action of "failed" with a
     * 4.x.x status is a message that expired in a queue, which says nothing
     * about the address itself.
     */
    private static function kind(string $action, string $status): ?string
    {
        if ($status !== '') {
            return match ($status[0]) {
                '5'     => self::KIND_HARD,
                '4'     => self::KIND_SOFT,
                default => null,
            };
        }

        return match ($action) {
            'failed'  => self::KIND_HARD,
            'delayed' => self::KIND_SOFT,
            default   => null,
        };
    }

    /** The diagnostic as a person needs to read it: type label gone, one line, bounded. */
    private static function reason(string $value): string
    {
        $reason = preg_replace('/\s+/', ' ', self::stripType($value));

        return mb_substr(trim($reason), 0, 1000);
    }

    /**
     * Reads Mawiblah's own headers and the subject from the quoted original.
     *
     * @param string $original The message/rfc822 or text/rfc822-headers content.
     * @return array{campaignHash: string, subscriberHash: string, subject: string}
     */
    private static function quotedHeaders(string $original): array
    {
        $empty = ['campaignHash' => '', 'subscriberHash' => '', 'subject' => ''];

        if (trim($original) === '') {
            return $empty;
        }

        try {
            $quoted = Message::from($original, false);
        } catch (\Throwable $e) {
            return $empty;
        }

        $hash = static function (?string $value): string {
            $value = strtolower(trim((string) $value));

            return preg_match('/^[a-f0-9]{32}$/', $value) ? $value : '';
        };

        return [
            'campaignHash'   => $hash($quoted->getHeaderValue(self::HEADER_CAMPAIGN)),
            'subscriberHash' => $hash($quoted->getHeaderValue(self::HEADER_SUBSCRIBER)),
            'subject'        => mb_substr(trim((string) $quoted->getHeaderValue('Subject')), 0, 255),
        ];
    }
}
