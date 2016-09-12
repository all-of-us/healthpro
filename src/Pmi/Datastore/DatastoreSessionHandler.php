<?php
namespace Pmi\Datastore;

use Pmi\Entities\Session;
use DateTime;
use SessionHandlerInterface;

class DatastoreSessionHandler implements SessionHandlerInterface
{
    public function close()
    {
        return true;
    }

    public function destroy($id)
    {
        $session = Session::fetchOneByName($id);
        if ($session) {
            $session->delete();
        }
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

    public function open($savePath, $name)
    {
        return true;
    }

    public function read($id)
    {
        $session = Session::fetchOneByName($id);
        if ($session) {
            return $session->getData();
        } else {
            return '';
        }
    }

    public function write($id, $session_data)
    {
        $session = Session::fetchOneByName($id);
        if (!$session) {
            $session = new Session();
            $session->setKeyName($id);
        }
        $session->setData($session_data);
        $session->setModified(new DateTime());
        $session->save();
    }
}
