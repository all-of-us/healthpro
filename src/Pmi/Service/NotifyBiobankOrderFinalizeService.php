<?php

namespace Pmi\Service;

use Pmi\Mail\Message;
use Pmi\Audit\Log;

class NotifyBiobankOrderFinalizeService
{
    protected $app;
    protected $db;

    public function __construct($app, $info)
    {
        $this->app = $app;
        $this->info = $info;
    }

    public function sendEmails()
    {
        $site = $this->app['em']->getRepository('sites')->fetchOneBy([
            'site_id' => $this->info['siteId']
        ]);
        if (empty($site['email'])) {
            $this->app->log(Log::BIOBANK_ORDER_FINALIZE_NOTIFY, [
                'status' => 'No email address to notify'
            ]);
        }
        $emails = explode(',', $site['email']);
        $message = new Message($this->app);
        $message
            ->setTo($emails)
            ->render('biobank-order-finalize', ['info' => $this->info])
            ->send();
        $this->app->log(Log::BIOBANK_ORDER_FINALIZE_NOTIFY, [
            'status' => 'Notifications sent',
            'notified' => $emails
        ]);
    }
}
