<?php
use Pmi\Order\Order;
use Pmi\Entities\Participant;

class OrderTest extends \PHPUnit_Framework_TestCase
{
    protected function createOrder($parameters = [])
    {
        $particpantId = 'P1234';
        $biobankId = 'B1234';
        $orderId = 'ABCDEF';

        $order = new Order();
        $participant = new Participant([
            'id' => $particpantId,
            'biobank_id' => $biobankId
        ]);
        $orderParameters = [
            'participant_id' => $particpantId,
            'bioank_id' => $biobankId,
            'order_id' => $orderId,
            'created_ts' => null,
            'printed_ts' => null,
            'collected_ts' => null,
            'processed_ts' => null,
            'finalized_ts' => null,
            'type' => null,
            'requested_samples' => null,
            'collected_samples' => null,
            'processed_samples' => null,
            'finalized_samples' => null,
            'collected_notes' => null,
            'processed_notes' => null,
            'finalized_notes' => null
        ];
        $orderParameters = array_merge($orderParameters, $parameters);
        $order->setOrder($orderParameters);
        return $order;
    }

    public function testOrderValidity()
    {
        $order = new Order();
        $this->assertFalse($order->isValid());

        $order = new Order();
        $order->setOrder([
            'participant_id' => 'P1234'
        ]);
        $this->assertFalse($order->isValid());

        $order = new Order();
        $order->setParticipant(new Participant());
        $this->assertFalse($order->isValid());

        $order = new Order();
        $order->setOrder([
            'participant_id' => 'P1234'
        ]);
        $order->setParticipant(new Participant());
        $this->assertTrue($order->isValid());
    }

    public function testOrderStep()
    {
        $order = $this->createOrder();
        $this->assertSame('print', $order->getCurrentStep());

        $order = $this->createOrder([
            'created_ts' => new \DateTime('2016-01-01 08:00:00'),
            'printed_ts' => new \DateTime('2016-01-01 09:00:00')
        ]);
        $this->assertSame('collect', $order->getCurrentStep());

        $order = $this->createOrder([
            'created_ts' => new \DateTime('2016-01-01 08:00:00'),
            'printed_ts' => new \DateTime('2016-01-01 09:00:00'),
            'collected_ts' => new \DateTime('2016-01-01 10:00:00')
        ]);
        $this->assertSame('process', $order->getCurrentStep());

        $order = $this->createOrder([
            'created_ts' => new \DateTime('2016-01-01 08:00:00'),
            'printed_ts' => new \DateTime('2016-01-01 09:00:00'),
            'collected_ts' => new \DateTime('2016-01-01 10:00:00'),
            'processed_ts' => new \DateTime('2016-01-01 11:00:00')
        ]);
        $this->assertSame('finalize', $order->getCurrentStep());

        $order = $this->createOrder([
            'created_ts' => new \DateTime('2016-01-01 08:00:00'),
            'printed_ts' => new \DateTime('2016-01-01 09:00:00'),
            'collected_ts' => new \DateTime('2016-01-01 10:00:00'),
            'processed_ts' => new \DateTime('2016-01-01 11:00:00'),
            'finalized_ts' => new \DateTime('2016-01-01 12:00:00')
        ]);
        $this->assertSame('finalize', $order->getCurrentStep());
    }

    public function testRdrObject()
    {
        $order = $this->createOrder([
            'created_ts' => new \DateTime('2016-01-01 08:00:00'),
            'printed_ts' => new \DateTime('2016-01-01 09:00:00'),
            'collected_ts' => new \DateTime('2016-01-01 10:00:00'),
            'processed_ts' => new \DateTime('2016-01-01 11:00:00'),
            'finalized_ts' => new \DateTime('2016-01-01 12:00:00')
        ]);
        $object = $order->getRdrObject();

        $this->assertSame('Patient/P1234', $object->subject);
        $this->assertSame('ABCDEF', $object->identifier[0]['value']);

        $created = new \DateTime('2016-01-01 08:00:00');
        $created->setTimezone(new \DateTimeZone('UTC'));
        $this->assertSame($created->format('Y-m-d\TH:i:s\Z'), $object->created);
        $this->assertSame(7, count($object->samples));
    }
}
