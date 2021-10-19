<?php

namespace App\Service;

use App\Datastore\Entities\AuditLog;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LoggerService
{
    protected $logger;
    protected $session;
    protected $userService;
    protected $requestStack;
    protected $env;
    protected $action;
    protected $data;

    public const PMI_AUDIT_PREFIX = 'PMI_AUDIT_';

    // Actions
    public const REQUEST = 'REQUEST';
    public const LOGIN_SUCCESS = 'LOGIN_SUCCESS';
    public const LOGIN_FAIL = 'LOGIN_FAIL';
    public const LOGOUT = 'LOGOUT';
    public const INVALID_IP = 'INVALID_IP';
    public const ORDER_CREATE = 'ORDER_CREATE';
    public const ORDER_EDIT = 'ORDER_EDIT';
    public const ORDER_HISTORY_CREATE = 'ORDER_HISTORY_CREATE';
    public const EVALUATION_CREATE = 'EVALUATION_CREATE';
    public const EVALUATION_EDIT = 'EVALUATION_EDIT';
    public const EVALUATION_DELETE = 'EVALUATION_DELETE';
    public const EVALUATION_HISTORY_CREATE = 'EVALUATION_HISTORY_CREATE';
    public const SITE_EDIT = 'SITE_EDIT';
    public const SITE_ADD = 'SITE_ADD';
    public const SITE_DELETE = 'SITE_DELETE';
    public const WORKQUEUE_EXPORT = 'WORKQUEUE_EXPORT';
    public const CROSS_ORG_PARTICIPANT_ATTEMPT = 'CROSS_ORG_PARTICIPANT_ATTEMPT';
    public const CROSS_ORG_PARTICIPANT_AGREE = 'CROSS_ORG_PARTICIPANT_AGREE';
    public const CROSS_ORG_PARTICIPANT_VIEW = 'CROSS_ORG_PARTICIPANT_VIEW';
    public const WITHDRAWAL_NOTIFY = 'WITHDRAWAL_NOTIFY';
    public const DEACTIVATE_NOTIFY = 'DEACTIVATE_NOTIFY';
    public const PROBLEM_CREATE = 'PROBLEM_CREATE';
    public const PROBLEM_EDIT = 'PROBLEM_EDIT';
    public const PROBLEM_COMMENT_CREATE = 'PROBLEM_COMMENT_CREATE';
    public const PROBLEM_NOTIFIY = 'PROBLEM_NOTIFIY';
    public const QUEUE_RESEND_EVALUATION = 'QUEUE_RESEND_EVALUATION';
    public const MISSING_MEASUREMENTS_ORDERS_NOTIFY = 'MISSING_MEASUREMENTS_ORDERS_NOTIFY';
    public const NOTICE_EDIT = 'NOTICE_EDIT';
    public const NOTICE_ADD = 'NOTICE_ADD';
    public const NOTICE_DELETE = 'NOTICE_DELETE';
    public const PATIENT_STATUS_ADD = 'PATIENT_STATUS_ADD';
    public const PATIENT_STATUS_EDIT = 'PATIENT_STATUS_EDIT';
    public const PATIENT_STATUS_HISTORY_ADD = 'PATIENT_STATUS_HISTORY_ADD';
    public const PATIENT_STATUS_HISTORY_EDIT = 'PATIENT_STATUS_HISTORY_EDIT';
    public const AWARDEE_ADD = 'AWARDEE_ADD';
    public const ORGANIZATION_ADD = 'ORGANIZATION_ADD';
    public const CSRF_TOKEN_MISMATCH = 'CSRF_TOKEN_MISMATCH';

    public function __construct(
        LoggerInterface $logger,
        SessionInterface $session,
        UserService $userService,
        RequestStack $requestStack,
        EnvironmentService $env
    ) {
        $this->logger = $logger;
        $this->session = $session;
        $this->userService = $userService;
        $this->requestStack = $requestStack;
        $this->env = $env;
    }

    public function log($action, $data = null)
    {
        $this->action = $action;
        $this->data = $data;
        $this->logSyslog();
        if (!$this->env->values['isUnitTest'] && !$this->env->isPhpDevServer() && $action != self::REQUEST) {
            $this->logDatastore();
        }
    }

    protected function buildLogArray()
    {
        $logArray = [];
        $logArray['action'] = $this->action;
        $logArray['data'] = $this->data;
        $logArray['ts'] = new \DateTime();
        $logArray = array_merge($logArray, $this->getLogMetadata());
        return $logArray;
    }

    protected function logSyslog()
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
        $this->logger->info(implode(' ', $syslogData));
    }

    protected function logDatastore()
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

    public function getLogMetaData()
    {
        $user = $site = $ip = null;

        try {
            if (($userObj = $this->userService->getUser()) && is_object($userObj)) {
                $user = $userObj->getUsername();
            } elseif ($userObj = $this->userService->getGoogleUser()) {
                $user = $userObj->getEmail();
            }
        } catch (\Exception $e) {
        }

        try {
            if (($siteObj = $this->session->get('site')) && isset($siteObj->id)) {
                $site = $siteObj->id;
            }
        } catch (\Exception $e) {
        }

        try {
            if ($request = $this->requestStack->getCurrentRequest()) {
                // http://symfony.com/doc/3.4/deployment/proxies.html#but-what-if-the-ip-of-my-reverse-proxy-changes-constantly
                $trustedProxies = ['127.0.0.1', $request->server->get('REMOTE_ADDR')];
                $originalTrustedProxies = Request::getTrustedProxies();
                $originalTrustedHeaderSet = Request::getTrustedHeaderSet();
                // specififying HEADER_X_FORWARDED_FOR because App Engine 2nd Gen also adds a FORWARDED
                Request::setTrustedProxies($trustedProxies, Request::HEADER_X_FORWARDED_FOR);

                // getClientIps reverses the order, so we want the last ip which will be the user's origin ip
                $ips = $request->getClientIps();
                $ip = array_pop($ips);

                // reset trusted proxies
                Request::setTrustedProxies($originalTrustedProxies, $originalTrustedHeaderSet);

                // identify cron user
                if ($user === null && $request->headers->get('X-Appengine-Cron') === 'true') {
                    $user = 'Appengine-Cron';
                }
            }
        } catch (\Exception $e) {
        }

        return [
            'user' => $user,
            'site' => $site,
            'ip' => $ip
        ];
    }
}
