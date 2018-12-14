<?php
namespace Pmi\Datastore;

use Pmi\Entities\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;
use DateTime;

class DatastoreSessionHandler extends AbstractSessionHandler
{
    public function close()
    {
        return true;
    }

    public function doDestroy($id)
    {
        try {
            $session = Session::fetchOneByName($id);
            if ($session) {
                $session->delete();
            }
        } catch (\Exception $e) {
        }

        return true;
    }

    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     *
     * This noop method is required since the AbstractSessionHandler
     * class implements SessionUpdateTimestampHandlerInterface
     */
    public function updateTimestamp($id, $data)
    {
        return true;
    }

    public function gc($maxlifetime)
    {
        $query = 'SELECT * FROM Session WHERE modified < @modified';
        $modified = new DateTime("-{$maxlifetime} seconds");
        $sessionStore = new \GDS\Store('Session');
        $results = $sessionStore->fetchAll($query, ['modified' => $modified]);
        $sessionStore->delete($results);

        return true;
    }

    public function doRead($id)
    {
        try {
            $session = Session::fetchOneById($id);
            if ($session) {
                return $session->data;
            } else {
                return '';
            }
        } catch (\Exception $e) {
            // Destroy session if session id is invalid
            $this->destroy($id);
            return '';
        }
    }

    public function doWrite($id, $session_data)
    {
        try {
            $session = Session::fetchOneByName($id);
            if (!$session) {
                $session = new Session();
                $session->setKeyName($id);
            }
            $session->setData($session_data);
            $session->setModified(new DateTime());
            $session->save();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
