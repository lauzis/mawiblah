<?php

namespace Mawiblah;

use DirectoryTree\ImapEngine\Connection\ImapConnection;
use DirectoryTree\ImapEngine\Support\Str;

/**
 * An IMAP connection that can expunge exactly the messages it names.
 *
 * The library deletes by flagging \Deleted and running EXPUNGE, which removes
 * every flagged message in the folder -- including anything a person marked for
 * deletion in their own mail client and has not emptied yet. UID EXPUNGE
 * (RFC 4315, the UIDPLUS extension) takes a set of UIDs and removes only those.
 */
class BounceImapConnection extends ImapConnection
{
    /**
     * Permanently removes the given messages, which must already carry \Deleted.
     *
     * @param int[] $uids
     */
    public function uidExpunge(array $uids): void
    {
        $this->send('UID EXPUNGE', [Str::set($uids)], $tag);

        $this->assertTaggedResponse($tag);
    }
}
