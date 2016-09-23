<?php
namespace Pmi\Audit;

use Pmi\Entities\AuditLog;

class Log
{
    protected $app;
    protected $action;
    protected $data;

    const PMI_AUDIT_PREFIX = 'PMI_AUDIT_';

    // Actions
    const LOGIN_SUCCESS = 'LOGIN_SUCCESS';

    public function __construct($app, $action, $data)
    {
        $this->app = $app;
        $this->action = $action;
        $this->data = $data;
    }

    protected function buildLogArray()
    {
        $logArray = [];
        $logArray['action'] = $this->action;
        $logArray['data'] = $this->data;
        $logArray['ts'] = new \DateTime();
        if ($user = $this->app->getUser()) {
            $logArray['user'] = $user->getUsername();
        } else {
            $logArray['user'] = null;
        }

        if ($request = $this->app['request_stack']->getCurrentRequest()) {
            $logArray['ip'] = $request->getClientIp();
        } else {
            $logArray['ip'] = null;
        }
        return $logArray;
    }

    public function logSyslog()
    {
        $logArray = $this->buildLogArray();
        $logArray['action'] = self::PMI_AUDIT_PREFIX . $logArray['action'];
        $syslogData = [];
        $syslogData[] = $logArray['action'];
        if ($logArray['data']) {
            $syslogData[] = json_encode($logArray['data']);
        }
        syslog(LOG_INFO, implode(" ", $syslogData));
    }

    public function logDatastore()
    {
        $logArray = $this->buildLogArray();
        $entity = new AuditLog();
        $entity->setAction($logArray['action']);
        $entity->setTimestamp($logArray['ts']);
        $entity->setUser($logArray['user']);
        $entity->setIp($logArray['ip']);
        if ($logArray['data']) {
            $entity->setData(json_encode($logArray['data']));
        }
        $entity->save();
    }
}
