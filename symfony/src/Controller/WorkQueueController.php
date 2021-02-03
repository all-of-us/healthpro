<?php

namespace App\Controller;

use App\Service\SiteService;
use App\Service\WorkQueueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Helper\WorkQueue;

/**
 * @Route("/s/workqueue")
 */
class WorkQueueController extends AbstractController
{
    /**
     * @Route("/", name="workqueue_home")
     */
    public function index(Request $request, SessionInterface $session, SiteService $siteService, WorkQueueService $workQueueService)
    {
        if ($this->isGranted('ROLE_USER')) {
            $awardee = $siteService->getSiteAwardee();
        }
        if ($this->isGranted('ROLE_AWARDEE')) {
            $awardees = $siteService->getSuperUserAwardees();
            if (!empty($awardees)) {
                if (($sessionAwardee = $session->get('awardeeOrganization')) && in_array($sessionAwardee, $awardees)) {
                    $awardee = $sessionAwardee;
                } else {
                    // Default to first organization
                    $awardee = $awardees[0];
                }
            }
        }
        if (empty($awardee)) {
            return $this->render('workqueue/no-organization.html.twig');
        }

        $params = array_filter($request->query->all());
        $filters = WorkQueue::$filters;

        if ($this->isGranted('ROLE_AWARDEE')) {
            // Add awardees to filters
            $awardeesList = [];
            $awardeesList['awardee']['label'] = 'Awardee';
            foreach ($awardees as $awardee) {
                $awardeesList['awardee']['options'][$siteService->getAwardeeDisplayName($awardee)] = $awardee;
            }
            $awardeesList['awardee']['options']['Salivary Pilot'] = 'salivary_pilot';
            $filters = array_merge($filters, $awardeesList);

            // Set to selected awardee
            if (isset($params['awardee'])) {
                // Check if the super user has access to this awardee
                if ($params['awardee'] !== 'salivary_pilot' && !in_array($params['awardee'], $siteService->getSuperUserAwardees())) {
                    throw $this->createAccessDeniedException();
                }
                $awardee = $params['awardee'];
                unset($params['awardee']);
            }
            // Save selected (or default) organization in session
            $session->set('awardeeOrganization', $awardee);

            // Remove patient status filter for awardee
            unset($filters['patientStatus']);
        }

        // Display current organization in the default patient status filter drop down label
        if (isset($filters['patientStatus'])) {
            $filters['patientStatus']['label'] = 'Patient Status at ' . $siteService->getOrganizationDisplayName($siteService->getSiteOrganization());
        }

        $sites = $siteService->getSitesFromOrganization($awardee);
        if (!empty($sites)) {
            //Add sites filter
            $sitesList = [];
            $sitesList['site']['label'] = 'Paired Site';
            foreach ($sites as $site) {
                if (!empty($site['google_group'])) {
                    $sitesList['site']['options'][$site['name']] = $site['google_group'];
                }
            }
            $sitesList['site']['options']['Unpaired'] = 'UNSET';
            $filters = array_merge($filters, $sitesList);

            //Add organization filter
            $awardeesList = [];
            $awardeesList['organization_id']['label'] = 'Paired Organization';
            foreach ($sites as $site) {
                if (!empty($site['organization_id'])) {
                    $awardeesList['organization_id']['options'][$app->getOrganizationDisplayName($site['organization_id'])] = $site['organization_id'];
                }
            }
            $awardeesList['organization_id']['options']['Unpaired'] = 'UNSET';
            $filters = array_merge($filters, $awardeesList);
        }

        //For ajax requests
        if ($request->isXmlHttpRequest()) {
            $params = array_merge($params, array_filter($request->request->all()));
            if (!empty($params['patientStatus'])) {
                $params['siteOrganizationId'] = $siteService->getSiteOrganization();
            }
            $participants = $workQueueService->participantSummarySearch($awardee, $params, $app, $type = 'wQTable');
            $ajaxData = [];
            $ajaxData['recordsTotal'] = $ajaxData['recordsFiltered'] = $app['pmi.drc.participants']->getTotal();
            $WorkQueue = new WorkQueue;
            $ajaxData['data'] = $WorkQueue->generateTableRows($participants, $app);
            $responseCode = 200;
            if ($this->rdrError) {
                $responseCode = 500;
            }
            return new JsonResponse($ajaxData, $responseCode);
        } else {
            return $this->render('workqueue/index.html.twig', [
                'filters' => $filters,
                'surveys' => WorkQueue::$surveys,
                'samples' => WorkQueue::$samples,
                'participants' => [],
                'params' => $params,
                'organization' => $awardee,
                'isRdrError' => $this->rdrError,
                'samplesAlias' => WorkQueue::$samplesAlias,
                'canExport' => $this->canExport($app),
                'exportConfiguration' => $this->getExportConfiguration($app)
            ]);
        }
    }
}