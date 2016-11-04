<?php
use Pmi\Order\Mayolink\MayolinkOrder;

class MayolinkOrderTest extends \PHPUnit_Framework_TestCase
{
    public function testLogin()
    {
        $order = new MayolinkOrder();
        // TODO: retrieve test credentials from... datastore?
        $loginResult = $order->login('username', 'password');
        $this->assertTrue($loginResult, 'Failed to authenticate with MayoLINK');
        return $order;
    }

    /**
     * @depends testLogin
     */
    public function testOrder($order)
    {
        $options = [
            'test_code' => 'ACE',
            'specimen' => 'Serum',
            'temperature' => 'Ambient',
            'first_name' => 'First',
            'last_name' => 'Last',
            'gender' => 'M',
            'birth_date' => new \DateTime('1990-01-01'),
            'physician_name' => 'None',
            'physician_phone' => 'None',
            'collected_at' => new \DateTime('-1 day')
        ];
        $orderResult = $order->create($options);
        $this->assertNotEmpty($orderResult);
    }
}
