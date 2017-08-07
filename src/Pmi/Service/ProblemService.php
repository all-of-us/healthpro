<?php
namespace Pmi\Service;

use Pmi\Mail\Message;
use Pmi\Audit\Log;

class ProblemService
{
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    protected function getDvAdminEmail()
    {
        return $this->app->getConfig('gaDvAdminEmail');
    }

    public function sendProblemReportEmail($problemId, $type = 'report')
    {
        $email = $this->getDvAdminEmail();
        if (!empty($email)) {
            $message = new Message($this->app);
            $message
                ->setTo($email)
                ->render('problem', [
                    'problemId' => $problemId,
                    'type' => $type
                ])
                ->send();
            $this->app->log(Log::PROBLEM_NOTIFIY, [
                'problemId' => $problemId,
                'status' => 'Unactipated problem notification sent',
                'notified' => $email
            ]);
        } else {
            $this->app->log(Log::PROBLEM_NOTIFIY, [
                'problemId' => $problemId,
                'status' => 'Unactipated problem but no one to notify'
            ]);
        }
    }
}
