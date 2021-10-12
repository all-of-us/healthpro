<?php

namespace App\Audit;

use Pmi\Entities\AuditLog;
use Symfony\Component\HttpFoundation\Request;

class Log
{
    protected $app;
    protected $action;
    protected $data;

    const PMI_AUDIT_PREFIX = 'PMI_AUDIT_';

    // Actions
    const REQUEST = 'REQUEST';
    const LOGIN_SUCCESS = 'LOGIN_SUCCESS';
    const LOGIN_FAIL = 'LOGIN_FAIL';
    const LOGOUT = 'LOGOUT';
    const INVALID_IP = 'INVALID_IP';
    const ORDER_CREATE = 'ORDER_CREATE';
    const ORDER_EDIT = 'ORDER_EDIT';
    const ORDER_HISTORY_CREATE = 'ORDER_HISTORY_CREATE';
    const EVALUATION_CREATE = 'EVALUATION_CREATE';
    const EVALUATION_EDIT = 'EVALUATION_EDIT';
    const EVALUATION_DELETE = 'EVALUATION_DELETE';
    const EVALUATION_HISTORY_CREATE = 'EVALUATION_HISTORY_CREATE';
    const SITE_EDIT = 'SITE_EDIT';
    const SITE_ADD = 'SITE_ADD';
    const SITE_DELETE = 'SITE_DELETE';
    const WORKQUEUE_EXPORT = 'WORKQUEUE_EXPORT';
    const CROSS_ORG_PARTICIPANT_ATTEMPT = 'CROSS_ORG_PARTICIPANT_ATTEMPT';
    const CROSS_ORG_PARTICIPANT_AGREE = 'CROSS_ORG_PARTICIPANT_AGREE';
    const CROSS_ORG_PARTICIPANT_VIEW = 'CROSS_ORG_PARTICIPANT_VIEW';
    const WITHDRAWAL_NOTIFY = 'WITHDRAWAL_NOTIFY';
    const DEACTIVATE_NOTIFY = 'DEACTIVATE_NOTIFY';
    const DECEASED_PENDING_NOTIFY = 'DECEASED_PENDING_NOTIFY';
    const DECEASED_APPROVED_NOTIFY = 'DECEASED_APPROVED_NOTIFY';
    const EHR_WITHDRAWAL_NOTIFY = 'EHR_WITHDRAWAL_NOTIFY';
    const PROBLEM_CREATE = 'PROBLEM_CREATE';
    const PROBLEM_EDIT = 'PROBLEM_EDIT';
    const PROBLEM_COMMENT_CREATE = 'PROBLEM_COMMENT_CREATE';
    const PROBLEM_NOTIFIY = 'PROBLEM_NOTIFIY';
    const QUEUE_RESEND_EVALUATION = 'QUEUE_RESEND_EVALUATION';
    const MISSING_MEASUREMENTS_ORDERS_NOTIFY = 'MISSING_MEASUREMENTS_ORDERS_NOTIFY';
    const NOTICE_EDIT = 'NOTICE_EDIT';
    const NOTICE_ADD = 'NOTICE_ADD';
    const NOTICE_DELETE = 'NOTICE_DELETE';
    const PATIENT_STATUS_ADD = 'PATIENT_STATUS_ADD';
    const PATIENT_STATUS_EDIT = 'PATIENT_STATUS_EDIT';
    const PATIENT_STATUS_HISTORY_ADD = 'PATIENT_STATUS_HISTORY_ADD';
    const PATIENT_STATUS_HISTORY_EDIT = 'PATIENT_STATUS_HISTORY_EDIT';
    const AWARDEE_ADD = 'AWARDEE_ADD';
    const ORGANIZATION_ADD = 'ORGANIZATION_ADD';
    const BIOBANK_ORDER_FINALIZE_NOTIFY = 'BIOBANK_ORDER_FINALIZE_NOTIFY';
    const PATIENT_STATUS_IMPORT_ADD = 'PATIENT_STATUS_IMPORT_ADD';
    const PATIENT_STATUS_IMPORT_EDIT = 'PATIENT_STATUS_IMPORT_EDIT';
    const GROUP_MEMBER_ADD = 'GROUP_MEMBER_ADD';
    const GROUP_MEMBER_REMOVE = 'GROUP_MEMBER_REMOVE';
    const GROUP_MEMBER_REMOVE_NOTIFY = 'GROUP_MEMBER_REMOVE_NOTIFY';

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
        $logArray = array_merge($logArray, $this->app->getLogMetadata());
        return $logArray;
    }

    public function logSyslog()
    {
        $logArray = $this->buildLogArray();
        $syslogData = [];
        $syslogData[] = $logArray['ip'];
        $syslogData[] = $logArray['user'];
        $syslogData[] = $logArray['site'];
        $syslogData[] = '[' . self::PMI_AUDIT_PREFIX . $logArray['action'] . ']';
        if ($logArray['data']) {
            $syslogData[] = json_encode($logArray['data']);
        }
        $this->app['logger']->info(implode(' ', $syslogData));
    }

    public function logDatastore()
    {
        $logArray = $this->buildLogArray();
        $data = [
            'action' => $logArray['action'],
            'timestamp' => $logArray['ts'],
            'user' => $logArray['user'],
            'site' => $logArray['site'],
            'ip' => $logArray['ip']
        ];
        if ($logArray['data']) {
            $data['data'] = json_encode($logArray['data']);
        }
        $auditLog = new AuditLog();
        $auditLog->setData($data);
        $auditLog->save();
    }
}
