<?php
use Pmi\Mayolink\Order;

class OrderTest extends \PHPUnit_Framework_TestCase
{
    public function testSearch()
    {
        $order = new Order();
        // TODO: retrieve test credentials from... datastore?
        $loginResult = $order->login('username', 'password');
        $this->assertTrue($loginResult, 'Failed to authenticate with MayoLINK');
    }
}
