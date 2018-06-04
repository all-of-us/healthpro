<?php
namespace Pmi\Service;

use Pmi\Mail\Message;
use Pmi\Audit\Log;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class NotifyMissingMeasurementsAndOrdersService
{
    protected $app;
    protected $db;

    const MEASUREMENT_TYPE = 'measurement';
    const ORDER_TYPE = 'order';

    public function __construct($app)
    {
        $this->app = $app;
        $this->db = $app['db'];
        $this->em = $app['em'];
    }

    public function sendEmails()
    {
        $emails = $this->app->getConfig('missing_notify_email_address');
        if (!empty($emails)) {
            $emails = explode(',', $emails);
        }

        $missingEvalutions = $this->db->fetchAll('select id from evaluations where id not in (select record_id from missing_notifications_log where type="measurement") and finalized_ts is not null and rdr_id is null');
        $missingEvalutionIds = [];
        foreach ($missingEvalutions as $evaluation) {
            $missingEvalutionIds[] = $evaluation['id'];
            $this->insertRecords($evaluation['id'], self::MEASUREMENT_TYPE);
        }

        $missingOrders = $this->db->fetchAll('select id from orders where id not in (select record_id from missing_notifications_log where type="order") and finalized_ts is not null and rdr_id is null');
        $missingOrderIds = [];
        foreach ($missingOrders as $order) {
            $missingOrderIds[] = $order['id'];
            $this->insertRecords($order['id'], self::ORDER_TYPE);
        }

        if (!empty($missingEvalutionIds) || !empty($missingOrderIds)) {
            $message = new Message($this->app);
            $message
                ->setTo($emails)
                ->render('missing-notify', [
                    'missingEvalutionIds' => implode(', ', $missingEvalutionIds),
                    'missingOrderIds' => implode(', ', $missingOrderIds)
                ])
                ->send();       
        }
    }

    public function insertRecords($id, $type)
    {
        $this->em->getRepository('missing_notifications_log')->insert(['record_id' => $id, 'type' => $type]);
    }
}