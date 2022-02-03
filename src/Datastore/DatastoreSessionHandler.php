<?php

namespace App\Datastore;

use App\Datastore\Entities\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;
use DateTime;

class DatastoreSessionHandler extends AbstractSessionHandler
{
    public function close(): bool
    {
        return true;
    }

    public function doDestroy($id): bool
    {
        try {
            $session = Session::fetchOneById($id);
            if ($session) {
                $session = new Session();
                $session->setKeyName($id);
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
    public function updateTimestamp($id, $data): bool
    {
        return true;
    }

    public function gc($maxlifetime): bool
    {
        $modified = new DateTime("-{$maxlifetime} seconds");
        $session = new Session();
        $results = $session->getBatch('modified', $modified, '<');
        $session->deleteBatch($results);
        return true;
    }

    public function doRead($id): string
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

    public function doWrite($id, $sessionData): bool
    {
        try {
            $data = [
                'data' => $sessionData,
                'modified' => new DateTime()
            ];
            $session = new Session();
            $session->setKeyName($id);
            $session->setData($data);
            $session->update();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
