<?php

namespace Code\Web;

use SessionHandlerInterface;

class SessionHandler implements SessionHandlerInterface
{


    public function open($s, $n)
    {
        return true;
    }

    // IMPORTANT: if we read the session and it doesn't exist, create an empty record.
    // We rely on this due to differing PHP implementation of session_regenerate_id()
    // some which call read explicitly and some that do not. So we call it explicitly
    // just after sid regeneration to force a record to exist.

    public function read($id)
    {

        if ($id) {
            $r = q("SELECT sess_data FROM session WHERE sid= '%s'", dbesc($id));

            if ($r) {
                return $r[0]['sess_data'];
            } else {
                q(
                    "INSERT INTO session (sess_data, sid, expire) values ('%s', '%s', '%s')",
                    dbesc(''),
                    dbesc($id),
                    dbesc(time() + 1800)
                );
            }
        }

        return '';
    }


    public function write($id, $data)
    {

        // Pretend everything is hunky-dory, even though it isn't. There probably isn't anything
        // we can do about it in any event.

        if (!$id) {
            return true;
        }

        // Unless we authenticate somehow, only keep a session for 30 minutes
        // The viewer can extend this by performing any web action using the
        // original cookie, but this allows us to cleanup the hundreds or
        // thousands of empty sessions left around from web crawlers which are
        // assigned cookies on each page that they never use.

        $expire = time() + 1800;

        if ($_SESSION) {
            if (array_key_exists('remember_me', $_SESSION) && intval($_SESSION['remember_me'])) {
                $expire = time() + (60 * 60 * 24 * 365);
            } elseif (local_channel()) {
                $expire = time() + (60 * 60 * 24 * 3);
            } elseif (remote_channel()) {
                $expire = time() + (60 * 60 * 24 * 1);
            }
        }

        q(
            "UPDATE session
			SET sess_data = '%s', expire = '%s' WHERE sid = '%s'",
            dbesc($data),
            dbesc($expire),
            dbesc($id)
        );

        return true;
    }


    public function close()
    {
        return true;
    }


    public function destroy($id)
    {
        q("DELETE FROM session WHERE sid = '%s'", dbesc($id));
        return true;
    }


    public function gc($expire)
    {
        q("DELETE FROM session WHERE expire < %d", dbesc(time()));
        return true;
    }
}
