<?php
namespace Tests\Pmi;

/** https://github.com/symfony/symfony/issues/13450 */
class MockFileSessionStorage extends \Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage
{
    public function setId($id)
    {
        if ($this->id !== $id) {
            parent::setId($id);
        }
    }
}
