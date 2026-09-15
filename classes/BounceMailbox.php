<?php

namespace Mawiblah;

use DirectoryTree\ImapEngine\Connection\Streams\ImapStream;
use DirectoryTree\ImapEngine\Enums\ImapFlag;
use DirectoryTree\ImapEngine\Folder;
use DirectoryTree\ImapEngine\FolderInterface;
use DirectoryTree\ImapEngine\Mailbox;

/**
 * The bounce mailbox: connecting, reading headers and messages, deleting a report.
 *
 * Everything asked of the IMAP library goes through here, so Bounces deals in
 * UIDs and raw messages only -- and a test can hand in imapengine's fake folder
 * through the mawiblah_bounce_folder filter.
 */
class BounceMailbox
{
    private const TEST_ACTION = 'mawiblah_bounce_test';

    /** @var Mailbox|null The open connection, reused for the rest of the request. */
    private static $mailbox = null;

    /** @var FolderInterface|null */
    private static $folder = null;

    /**
     * The connection settings.
     *
     * @return array{host: string, port: int, encryption: string, username: string, password: string, folder: string}
     */
    public static function config(): array
    {
        return (array) apply_filters('mawiblah_bounce_mailbox', Settings::bounceMailbox());
    }

    /** True when there is enough to connect with and the IMAP library is installed. */
    public static function configured(): bool
    {
        $config = self::config();

        return class_exists(BounceImapConnection::class)
            && $config['host'] !== ''
            && $config['username'] !== ''
            && $config['password'] !== '';
    }

    /**
     * Names the mailbox a report was read from.
     *
     * Stored with every row and with the read cursor: a UID means one message in
     * one folder of one account, and nothing anywhere else.
     */
    public static function key(): string
    {
        $config = self::config();

        return mb_substr(strtolower($config['username'] . '@' . $config['host']) . '/' . $config['folder'], 0, 191);
    }

    /**
     * The folder bounces are read from, connecting on first use.
     *
     * @throws \RuntimeException When the mailbox is not configured or the folder does not exist.
     */
    public static function folder(): FolderInterface
    {
        $injected = apply_filters('mawiblah_bounce_folder', null);

        if ($injected instanceof FolderInterface) {
            return $injected;
        }

        if (self::$folder) {
            return self::$folder;
        }

        if (!self::configured()) {
            throw new \RuntimeException(__('The bounce mailbox is not configured.', 'mawiblah'));
        }

        $config        = self::config();
        self::$mailbox = self::open($config);
        $folder        = self::$mailbox->folders()->find($config['folder']);

        if (!$folder) {
            self::disconnect();

            throw new \RuntimeException(sprintf(
                /* translators: %s: folder name */
                __('The mailbox has no folder "%s".', 'mawiblah'),
                $config['folder']
            ));
        }

        return self::$folder = $folder;
    }

    /** Closes the connection, if one is open. */
    public static function disconnect(): void
    {
        if (self::$mailbox) {
            self::$mailbox->disconnect();
        }

        self::$mailbox = null;
        self::$folder  = null;
    }

    /**
     * The folder's UIDVALIDITY: while it stays the same, a UID keeps naming the
     * same message. A fake folder has none, and answers 0.
     */
    public static function uidValidity(FolderInterface $folder): int
    {
        return (int) ($folder->status()['UIDVALIDITY'] ?? 0);
    }

    /**
     * Header blocks of the oldest messages above a UID.
     *
     * @return array<int, string> UID => headers, oldest first, at most $limit.
     */
    public static function headersAfter(FolderInterface $folder, int $after, int $limit): array
    {
        $messages = $folder->messages()
            ->withHeaders()
            ->uid($after + 1, INF)
            ->oldest()
            ->limit($limit)
            ->get();

        $heads = [];

        foreach ($messages as $message) {
            $uid = (int) $message->uid();

            // "n:*" always matches the newest message, even when its UID is
            // below n -- and a fake folder ignores the search altogether.
            if ($uid <= $after) {
                continue;
            }

            $heads[$uid] = self::headerBlock((string) $message);
        }

        ksort($heads);

        return array_slice($heads, 0, $limit, true);
    }

    /** The whole message, headers and body, fetched without marking it read. */
    public static function raw(FolderInterface $folder, int $uid): string
    {
        $message = $folder->messages()->withHeaders()->withBody()->find($uid);

        return $message ? (string) $message : '';
    }

    /**
     * Deletes one message and nothing else.
     *
     * Flags it \Deleted and, where the server supports UIDPLUS, removes it with
     * UID EXPUNGE. A server without UIDPLUS is left with the flag: a plain
     * EXPUNGE would also remove whatever else is flagged in the folder.
     */
    public static function delete(FolderInterface $folder, int $uid): void
    {
        if (!$folder instanceof Folder) {
            // imapengine's fake folder in tests; it has no connection to talk to.
            $folder->messages()->destroy([$uid]);

            return;
        }

        $mailbox    = $folder->mailbox();
        $connection = $mailbox->connection();

        $folder->select();
        $connection->store([ImapFlag::Deleted->value], [$uid], mode: '+');

        if ($connection instanceof BounceImapConnection && in_array('UIDPLUS', $mailbox->capabilities(), true)) {
            $connection->uidExpunge([$uid]);
        }
    }

    /** True when a header block says the message is a report (RFC 6522). */
    public static function looksLikeReport(string $head): bool
    {
        return (bool) preg_match('/^Content-Type:[ \t]*multipart\/report\b/im', self::unfold($head));
    }

    /** One header's value from a header block, unfolded; '' when absent. */
    public static function headerValue(string $head, string $name): string
    {
        $pattern = '/^' . preg_quote($name, '/') . ':[ \t]*(.*)$/im';

        return preg_match($pattern, self::unfold($head), $match) ? trim($match[1]) : '';
    }

    /** Registers the Test connection endpoint. Called on every admin request. */
    public static function boot(): void
    {
        add_action('wp_ajax_' . self::TEST_ACTION, [self::class, 'handleTest']);
    }

    /**
     * The "Test connection" button under the Bounced Emails settings.
     *
     * Tests what is in the fields, saved or not: filling them in and pressing
     * Test before saving is the obvious order to do it in.
     */
    public static function renderTestButton(): string
    {
        ob_start();
        ?>
        <p class="mawiblah-bounce-test">
            <button type="button" class="button" data-mawiblah-bounce-test="<?php echo esc_attr(self::TEST_ACTION); ?>"
                    data-nonce="<?php echo esc_attr(wp_create_nonce(self::TEST_ACTION)); ?>"
                    data-testing="<?php echo esc_attr__('Connecting…', 'mawiblah'); ?>">
                <?php esc_html_e('Test connection', 'mawiblah'); ?>
            </button>
            <span class="mawiblah-bounce-test-result" role="status" aria-live="polite" style="margin-left:8px;"></span>
        </p>
        <script>
        ( function () {
            if ( window.mawiblahBounceTest ) {
                return;
            }

            window.mawiblahBounceTest = true;

            // Matched on a suffix: the field names carry Carbon Fields' own wrapping.
            var value = function ( id ) {
                var field = document.querySelector( '[name*="bounce-' + id + '"]' );

                return field ? field.value : '';
            };

            document.addEventListener( 'click', function ( event ) {
                var button = event.target.closest( '[data-mawiblah-bounce-test]' );

                if ( ! button ) {
                    return;
                }

                event.preventDefault();

                var result = button.parentNode.querySelector( '.mawiblah-bounce-test-result' );
                var body   = new URLSearchParams();

                body.append( 'action', button.getAttribute( 'data-mawiblah-bounce-test' ) );
                body.append( 'nonce', button.getAttribute( 'data-nonce' ) );
                [ 'host', 'port', 'encryption', 'username', 'password', 'folder' ].forEach( function ( id ) {
                    body.append( id, value( id ) );
                } );

                button.disabled    = true;
                result.style.color = '';
                result.textContent = button.getAttribute( 'data-testing' );

                fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
                    .then( function ( response ) { return response.json(); } )
                    .then( function ( payload ) {
                        result.style.color = payload.success ? '#008a20' : '#d63638';
                        result.textContent = payload.data;
                    } )
                    .catch( function ( error ) {
                        result.style.color = '#d63638';
                        result.textContent = String( error );
                    } )
                    .then( function () { button.disabled = false; } );
            } );
        }() );
        </script>
        <?php

        return (string) ob_get_clean();
    }

    /** Answers the Test connection button. */
    public static function handleTest(): void
    {
        check_ajax_referer(self::TEST_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You are not allowed to do that.', 'mawiblah'), 403);
        }

        $saved = Settings::bounceMailbox();
        $field = static function (string $key): string {
            return isset($_POST[$key]) ? trim(sanitize_text_field(wp_unslash($_POST[$key]))) : '';
        };

        // Not sanitized: a password may hold anything. An untouched field posts
        // the stored ciphertext, which decrypt() turns back into the password.
        $password   = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $encryption = $field('encryption');

        // wp-config.php constants win here too, as they do for the real checks.
        $config = [
            'host'       => defined('MAWIBLAH_BOUNCE_HOST') ? $saved['host'] : ($field('host') ?: $saved['host']),
            'port'       => (int) ($field('port') ?: $saved['port']),
            'encryption' => in_array($encryption, ['ssl', 'starttls', 'none'], true) ? $encryption : $saved['encryption'],
            'username'   => defined('MAWIBLAH_BOUNCE_USER') ? $saved['username'] : ($field('username') ?: $saved['username']),
            'password'   => defined('MAWIBLAH_BOUNCE_PASS') || $password === '' ? $saved['password'] : Secrets::decrypt($password),
            'folder'     => $field('folder') ?: $saved['folder'],
        ];

        if ($config['host'] === '' || $config['username'] === '' || $config['password'] === '') {
            wp_send_json_error(__('Fill in the server, username and password first.', 'mawiblah'));
        }

        if (!class_exists(BounceImapConnection::class)) {
            wp_send_json_error(__('The IMAP library is missing: run composer install in the plugin folder.', 'mawiblah'));
        }

        $mailbox = null;
        $success = false;

        try {
            $mailbox = self::open($config);
            $folder  = $mailbox->folders()->find($config['folder']);

            if (!$folder) {
                $names   = $mailbox->folders()->get()->map(fn (FolderInterface $f) => $f->path())->all();
                $message = sprintf(
                    /* translators: 1: folder name, 2: comma-separated folder names */
                    __('Connected, but there is no folder "%1$s". The mailbox has: %2$s', 'mawiblah'),
                    $config['folder'],
                    implode(', ', $names)
                );
            } else {
                $status  = $folder->status();
                $success = true;
                $message = sprintf(
                    /* translators: 1: folder name, 2: number of messages */
                    __('Connected. %1$s holds %2$d messages.', 'mawiblah'),
                    $folder->path(),
                    (int) ($status['MESSAGES'] ?? 0)
                ) . ' ' . (in_array('UIDPLUS', $mailbox->capabilities(), true)
                    ? __('Approved reports can be deleted one at a time (UIDPLUS).', 'mawiblah')
                    : __('The server lacks UIDPLUS, so an approved report is only flagged as deleted, not removed.', 'mawiblah'));
            }
        } catch (\Throwable $e) {
            $message = sprintf(__('Could not connect: %s', 'mawiblah'), $e->getMessage());
        } finally {
            if ($mailbox) {
                $mailbox->disconnect();
            }
        }

        $success ? wp_send_json_success($message) : wp_send_json_error($message);
    }

    /** Opens a connection with our own connection class, which can UID EXPUNGE. */
    private static function open(array $config): Mailbox
    {
        $mailbox = new Mailbox([
            'host'       => $config['host'],
            'port'       => (int) $config['port'],
            'encryption' => $config['encryption'] === 'none' ? '' : $config['encryption'],
            'username'   => $config['username'],
            'password'   => $config['password'],
            'timeout'    => 20,
        ]);

        $mailbox->connect(new BounceImapConnection(new ImapStream()));

        return $mailbox;
    }

    /** The header block of a message: everything before the first empty line. */
    private static function headerBlock(string $raw): string
    {
        return preg_split('/\r?\n\r?\n/', $raw, 2)[0];
    }

    /** Joins folded header lines onto the line they continue. */
    private static function unfold(string $head): string
    {
        return preg_replace('/\r?\n[ \t]+/', ' ', $head);
    }
}
