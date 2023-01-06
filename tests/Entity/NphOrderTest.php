<?php

namespace App\Tests\Entity;

use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class NphOrderTest extends KernelTestCase
{
    private $em;

    public function setup(): void
    {
        self::bootKernel();
        $this->em = static::$container->get(EntityManagerInterface::class);

    }

    protected function getUser(): User
    {
        $user = new User;
        $user->setEmail('test@example.com');
        $user->setGoogleId('12345');
        return $user;
    }

    protected function createNphOrder($params = []): NphOrder
    {
        $order = new NphOrder();
        foreach ($params as $key => $value) {
            $order->{'set' . ucfirst($key)}($value);
        }
        return $order;
    }

    protected function createNphSample($params = []): NphSample
    {
        $sample = new NphSample();
        foreach ($params as $key => $value) {
            $sample->{'set' . ucfirst($key)}($value);
        }
        return $sample;
    }

    protected function getOrderData(): array
    {
        return [
            'user' => $this->getUser(),
            'site' => 'test',
            'createdTs' => new \DateTime('2021-01-01 08:00:00'),
            'participantId' => 'P123456789',
            'orderId' => '0000000001',
            'module' => 1,
            'timepoint' => 'preLMT',
            'visitType' => 'LMT'
        ];
    }

    protected function getSampleData(): array
    {
        return [
            'sampleId' => '0000000002'
        ];
    }

    /**
     * @dataProvider canCancelRestoreDataProvider
     */

    public function testCanCancelRestore($samples, $canCancel, $canRestore)
    {
        $orderData = $this->getOrderData();
        $nphOrder = $this->createNphOrder($orderData);
        foreach ($samples as $sample) {
            $sample['nphOrder'] = $nphOrder;
            $this->createNphSample($sample);
        }
        $this->assertSame($canCancel, $nphOrder->canCancel());
        $this->assertSame($canRestore, $nphOrder->canRestore());
        $this->assertSame($canCancel, $nphOrder->canModify(NphSample::CANCEL));
        $this->assertSame($canRestore, $nphOrder->canModify(NphSample::RESTORE));
    }

    public function canCancelRestoreDataProvider(): array
    {
        return [
            [
                [
                    [
                        'sampleId' => '1000000000',
                        'sampleCode' => 'SST8P5',
                        'modifyType' => 'cancel',
                    ],
                    [
                        'sampleId' => '2000000000',
                        'sampleCode' => 'PST8',
                        'modifyType' => 'restore',
                    ],
                ],
                true,
                true
            ],
            [
                [
                    [
                        'sampleId' => '3000000000',
                        'sampleCode' => 'SST8P5',
                        'modifyType' => 'cancel',
                    ],
                    [
                        'sampleId' => '4000000000',
                        'sampleCode' => 'PST8',
                        'modifyType' => 'cancel',
                    ],
                ],
                false,
                true
            ],
            [
                [
                    [
                        'sampleId' => '5000000000',
                        'sampleCode' => 'SST8P5',
                        'modifyType' => 'restore',
                    ],
                    [
                        'sampleId' => '6000000000',
                        'sampleCode' => 'PST8',
                        'modifyType' => 'restore',
                    ],
                ],
                true,
                false
            ],
            [
                [
                    [
                        'sampleId' => '7000000000',
                        'sampleCode' => 'SST8P5',
                        'modifyType' => '',
                    ],
                    [
                        'sampleId' => '8000000000',
                        'sampleCode' => 'PST8',
                        'modifyType' => '',
                    ],
                ],
                true,
                false
            ]
        ];
    }
}
