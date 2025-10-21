<?php

namespace CB;

/**
 * CaseBox database session handling class
 *
 */

use CB\DB;
use CB\DataModel as DM;

class Session implements \SessionHandlerInterface
{
    private $lifetime = 0;

    /**
     * lifetime for previous sessions.
     *
     * We give them a timeout becouse client side can send requests
     * with parent/old session key, until result is received from current executing script.
     *
     * @var integer number of seconds
     */
    private $lifetime_pid_sessions = 3;

    /**
     * on session id regeneration the primary session id is saved in this variable
     * @var varchar
     */
    private $previous_session_id = null;

    /**
     * session close
     * @return bool
     */
    public function close()
    {
        $rez = true;
        $this->gc($this->lifetime);

        // close database-connection
        $rez = DB\close();

        return $rez;
    }

    /**
     * destroy session
     * @param  varchar $sessionId
     * @return bool
     */
    public function destroy($sessionId)
    {
        $rez = DM\Sessions::delete($sessionId);

        return $rez;
    }

    /**
     * garbage collector
     * @param  varchar $maxlifetime
     * @return bool
     */
    public function gc($maxlifetime)
    {
        if ($maxlifetime == 0) {
            $maxlifetime = null;
        }

        return DM\Sessions::cleanExpired();
    }

    /**
     * open session
     * @param  varchar $save_path
     * @param  varchar $name      session name
     * @return bool
     */
    public function open($savePath, $name)
    {
        $this->lifetime = ini_get('session.cookie_lifetime');

        return true;
    }

    /**
     * read session data
     * @param  varchar $sessionId
     * @return string
     */
    public function read($sessionId)
    {
        $rez = '';

        $r = DM\Sessions::read($sessionId);

        if (!empty($r)) {
            $rez = $r['data'];
        }

        $this->previous_session_id = $sessionId;

        return $rez;
    }

    /**
     * write session data
     * @param  varchar $session_id
     * @param  varchar $session_data
     * @return bool
     */
    public function write($session_id, $session_data)
    {
        $lifetime = ini_get('session.cookie_lifetime');
        $lifetime = empty($this->lifetime) ? null : $this->lifetime;

        /* when updating/creating a new session
        then parent session and all other child sessions shoould be marked as
        expiring in corresponding timeout */
        if (!empty($this->previous_session_id)) {
            DM\Sessions::updateExpiration(
                $session_id,
                $this->previous_session_id,
                $this->lifetime_pid_sessions
            );
        }

        // Extract user_id from session data
        // Session data is in PHP session format: key|serialized_value;key|serialized_value;...
        // We need to parse this format to extract the user ID, since $_SESSION may be
        // empty/modified by the time write() is called during shutdown
        $userId = 0;

        if (!empty($session_data)) {
            // Find and extract user array data
            // Format: ips|...; key|...; user|a:15:{s:2:"id";s:1:"1";...}
            if (preg_match('/user\|(.+)$/s', $session_data, $matches)) {
                $userSerialized = $matches[1];

                // Unserialize the user array to get the ID
                $user = @unserialize($userSerialized);

                if (is_array($user) && isset($user['id'])) {
                    $userId = (int)$user['id'];
                }
            }
        }

        $rez = DM\Sessions::replace(
            array(
                'id' => $session_id
                ,'pid' => $this->previous_session_id
                ,'lifetime' => $lifetime
                ,'user_id' => $userId
                ,'data' => $session_data
            )
        );

        return $rez;
    }

    /**
     * clear user sessions
     * @param  int     $userId
     * @return boolean
     */
    public static function clearUserSessions($userId)
    {
        if (!Security::canEditUser($userId)) {
            return false;
        }

        DM\Sessions::deleteByUserId($userId);

        return true;
    }
}
