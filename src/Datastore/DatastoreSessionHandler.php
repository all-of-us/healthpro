<?php

namespace App\Datastore;

use App\Datastore\Entities\Session;
use DateTime;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;

class DatastoreSessionHandler extends AbstractSessionHandler
{
    public function close(): bool
    {
        return true;
    }

    public function doDestroy(string $id): bool
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
    public function updateTimestamp(string $id, string $data): bool
    {
        return true;
    }

    public function gc(int $maxlifetime): int|false
    {
        $modified = new DateTime("-{$maxlifetime} seconds");
        $session = new Session();
        $results = $session->getBatch('modified', $modified, '<');
        $session->deleteBatch($results);
        return 1;
    }

    public function doRead(string $id): string
    {
        try {
            $session = Session::fetchOneById($id);
            if ($session) {
                $data = $session->get();

                return is_string($data['data'] ?? null) ? $data['data'] : '';
            }
            return '';
        } catch (\Exception $e) {
            // Destroy session if session id is invalid
            $this->destroy($id);
            return '';
        }
    }

    public function doWrite(string $id, string $sessionData): bool
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
