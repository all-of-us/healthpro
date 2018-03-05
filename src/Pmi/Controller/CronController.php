<?php
namespace Pmi\Controller;

use google\appengine\api\users\UserService;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Pmi\Service\WithdrawalService;

/**
 * NOTE: all /cron routes should be protected by `login: admin` in app.yaml
 */
class CronController extends AbstractController
{
    protected static $name = 'cron';

    protected static $routes = [
        ['pingTest', '/ping-test'],
        ['withdrawal', '/withdrawal'],
        ['sites', '/sites']
    ];
    
    /**
     * Provides a second layer of protection for cron actions beyond the
     * `login: admin` config that should exist in app.yaml for /cron routes.
     */
    private function isAdmin(Request $request)
    {
        return UserService::isCurrentUserAdmin() ||
            $request->headers->get('X-Appengine-Cron') === 'true';
    }

    public function withdrawalAction(Application $app, Request $request)
    {
        if (!$this->isAdmin($request)) {
            throw new AccessDeniedHttpException();
        }

        $withdrawal = new WithdrawalService($app);
        $withdrawal->sendWithdrawalEmails();

        return (new JsonResponse())->setData(true);
    }
    
    public function pingTestAction(Application $app, Request $request)
    {
        if (!$this->isAdmin($request)) {
            throw new AccessDeniedHttpException();
        }

        $user = UserService::getCurrentUser();
        if ($user) {
            $email = $user->getEmail();
            error_log("Cron ping test requested by $email [" . $request->getClientIp() . "]");
        }
        if ($request->headers->get('X-Appengine-Cron') === 'true') {
            error_log('Cron ping test requested by Appengine-Cron');
        }
        
        return (new JsonResponse())->setData(true);
    }

    public function sitesAction(Application $app, Request $request)
    {
        $rdrHelper = $app['pmi.drc.rdrhelper'];
        $client = $rdrHelper->getClient();
        $response = $client->request('GET', 'rdr/v1/Awardee');
        $responseObject = json_decode($response->getBody()->getContents());
        $siteRepository = $app['em']->getRepository('sites');
        foreach ($responseObject->entry as $entry) {
            $awardee = $entry->resource;
            if (!isset($awardee->organizations) || !is_array($awardee->organizations)) {
                break;
            }
            foreach ($awardee->organizations as $organization) {
                if (!isset($organization->sites) || !is_array($organization->sites)) {
                    break;
                }
                foreach ($organization->sites as $site) {
                    $googleGroup = str_replace(\Pmi\Security\User::SITE_PREFIX, '', $site->id);
                    $existing = $siteRepository->fetchOneBy([
                        'google_group' => $googleGroup
                    ]);
                    if ($existing) {
                        $siteData = $existing;
                    } else {
                        $siteData = [];
                    }
                    $siteData['name'] = $site->displayName;
                    $siteData['google_group'] = $googleGroup;
                    $siteData['mayolink_account'] = $site->mayolinkClientNumber;
                    $siteData['timezone'] = $site->timeZoneId;
                    $siteData['organization'] = $awardee->id;
                    $siteData['type'] = $awardee->type;
                    // $siteData['awardee'] = ?? not sure what this should be since organization is actually awardee. i think to be backwards-compatible, this might be for dv's only
                    // $siteData['email'] = ?? data doesn't seem to be correct and is an array but not properly separated?

                    if (empty($siteData['workqueue_download'])) {
                        $siteData['workqueue_download'] = 'full_data';
                    }
                    if ($existing) {
                        $siteRepository->update($existing['id'], $siteData);
                    } else {
                        $siteRepository->insert($siteData);
                    }
                }
            }
        }
        return (new JsonResponse())->setData(true);
    }
}
