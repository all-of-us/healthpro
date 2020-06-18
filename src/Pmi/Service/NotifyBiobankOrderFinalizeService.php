<?php

namespace Pmi\Service;

use Pmi\Mail\Message;
use Pmi\Audit\Log;

class NotifyBiobankOrderFinalizeService
{
    protected $app;
    protected $db;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function sendEmails($info)
    {
        $site = $this->app['em']->getRepository('sites')->fetchOneBy([
            'site_id' => $info['siteId']
        ]);
        if (!empty($site['email'])) {
            $emails = explode(',', $site['email']);
            $message = new Message($this->app);
            $message
                ->setTo($emails)
                ->render('biobank-order-finalize', ['info' => $info])
                ->send();
            $this->app->log(Log::BIOBANK_ORDER_FINALIZE_NOTIFY, [
                'status' => 'Notifications sent',
                'notified' => $emails
            ]);
        } else {
            $this->app->log(Log::BIOBANK_ORDER_FINALIZE_NOTIFY, [
                'status' => 'No email address to notify'
            ]);
        }
    }
}
