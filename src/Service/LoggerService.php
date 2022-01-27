<?php

namespace App\Service;

use App\Datastore\Entities\AuditLog;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Audit\Log;

class LoggerService
{
    private const PMI_AUDIT_PREFIX = 'PMI_AUDIT_';

    protected $logger;
    protected $requestStack;
    protected $userService;
    protected $env;
    protected $action;
    protected $data;

    public function __construct(
        LoggerInterface $logger,
        UserService $userService,
        RequestStack $requestStack,
        EnvironmentService $env
    ) {
        $this->logger = $logger;
        $this->userService = $userService;
        $this->requestStack = $requestStack;
        $this->env = $env;
    }

    public function log($action, $data = null)
    {
        $this->action = $action;
        $this->data = $data;
        $this->logSyslog();
        if (!$this->env->values['isUnitTest'] && !$this->env->isPhpDevServer() && $action != Log::REQUEST) {
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
            if (($siteObj = $this->requestStack->getSession()->get('site')) && isset($siteObj->id)) {
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
