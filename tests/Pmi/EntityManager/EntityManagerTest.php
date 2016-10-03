<?php
use Pmi\EntityManager\EntityManager;

class EntityManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateTableAndInsert()
    {
        $db = \Doctrine\DBAL\DriverManager::getConnection([
            'url' => 'sqlite:///:memory:'
        ]);
        $db->query('CREATE TABLE orders(
            id INTEGER PRIMARY KEY NOT NULL,
            participant_id,
            created_ts,
            updated_ts,
            version,
            data
        )');

        $em = new EntityManager();
        $em->setDbal($db);
        $repo = $em->getRepository('orders');
        $insertId = $repo->insert(['participant_id' => 'first']);
        $this->assertEquals(1, $insertId);
        $insertId = $repo->insert(['participant_id' => 'second']);
        $this->assertEquals(2, $insertId);

        return $repo;
    }

    /**
     * @depends testCreateTableAndInsert
     */
    public function testFetch($repo)
    {
        $all = $repo->fetchBy([]);
        $this->assertEquals(2, count($all));
        $this->assertEquals('first', $all[0]['participant_id']);
        $this->assertEquals('second', $all[1]['participant_id']);

        $reverse = $repo->fetchBy([], ['id' => 'desc']);
        $this->assertEquals(2, count($reverse));
        $this->assertEquals('second', $reverse[0]['participant_id']);
        $this->assertEquals('first', $reverse[1]['participant_id']);

        $filter = $repo->fetchBy(['participant_id' => 'second']);
        $this->assertEquals(1, count($filter));
        $this->assertEquals('second', $filter[0]['participant_id']);

        $second = $repo->fetchOneBy(['participant_id' => 'second']);
        $this->assertEquals('second', $second['participant_id']);

        $limited = $repo->fetchBy([], [], 1);
        $this->assertEquals(1, count($limited));
    }

    /**
     * @depends testCreateTableAndInsert
     */
    public function testUpdate($repo)
    {
        $repo->update(2, ['data' => 'hello']);
        $order = $repo->fetchOneBy(['id' => 2]);
        $this->assertEquals('hello', $order['data']);
    }

    /**
     * @depends testCreateTableAndInsert
     */
    public function testDelete($repo)
    {
        $repo->delete(1);
        $order = $repo->fetchOneBy(['id' => 1]);
        $this->assertFalse($order);
        $order = $repo->fetchOneBy(['id' => 2]);
        $this->assertNotFalse($order);
    }
}
