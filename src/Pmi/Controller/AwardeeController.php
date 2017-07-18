<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;


class AwardeeController extends WorkQueueController
{
    protected static $name = 'awardee';
    protected static $routes = [
        ['workQueue', '/workqueue'],
        ['export', '/export.csv']
    ];

    public function workQueueAction(Application $app, Request $request)
    {
        $organizations = $this->getAwardeeOrganizations($app);
        if (!empty($organizations)) {
            $organization = $organizations[0];
            $app['session']->set('awardeeOrganization', $organization);
        } else {
            $organization = null;
        }
        if (!$organization) {
            return $app['twig']->render('workqueue/no-organization.html.twig');
        }
        $organizationsList = [];
        $organizationsList['organization']['label'] = 'Organization';
        foreach ($organizations as $org) {
            $organizationsList['organization']['options'][$org] = $org;
        }
        $params = array_filter($request->query->all());
        if (isset($params['organization'])) {
            $organization = $params['organization'];
            $app['session']->set('awardeeOrganization', $organization);
        }
        $participants = $this->participantSummarySearch($organization, $params, $app);
        $filters = self::$filters;
        $filters = array_merge($filters, $organizationsList);
        return $app['twig']->render('workqueue/index.html.twig', [
            'filters' => $filters,
            'surveys' => self::$surveys,
            'samples' => self::$samples,
            'participants' => $participants,
            'params' => $params,
            'organization' => $organization,
            'isRdrError' => $this->rdrError,
            'type' => 'awardee'
        ]);
    }

    public function getAwardeeOrganizations($app) {
        $token = $app['security.token_storage']->getToken();
        $user = $token->getUser();
        $awardees = $user->getAwardees();
        $sitesArray = [];
        foreach ($awardees as $awardee) {
            $sites = $app['em']->getRepository('sites')->fetchBy([
                'awardee' => $awardee->id
            ]);
            if (!empty($sites)) {
                $sitesArray = array_merge($sites, $sitesArray);
            }           
        }
        $organizations = [];
        foreach ($sitesArray as $site) {
            if (!empty($site['organization'])) {
                $organizations[] = $site['organization'];
            }
        }
        return $organizations;
    }
    
}
