<?php

namespace App\Controller;

use App\Entity\Site;
use App\Form\SiteType;
use App\Repository\SiteRepository;
use App\Service\EnvironmentService;
use App\Service\LoggerService;
use App\Service\SiteSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Pmi\Audit\Log;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/s/admin/sites")
 */
class SitesController extends AbstractController
{
    /**
     * @Route("/", name="admin_sites")
     */
    public function index(SiteRepository $siteRepository, ParameterBagInterface $params)
    {
        $sites = $siteRepository->findBy(['deleted' => 0], ['name' => 'asc']);
        return $this->render('admin/sites/index.html.twig', [
            'sites' => $sites,
            'sync' => $params->has('sites_use_rdr') ? $params->get('sites_use_rdr') : false
        ]);
    }

    /**
     * @Route("/site/{id}", name="admin_site")
     */
    public function edit(SiteRepository $siteRepository, EntityManagerInterface $em, LoggerService $loggerService, Request $request, ParameterBagInterface $params, EnvironmentService $env, $id = null)
    {
        $syncEnabled = $params->has('sites_use_rdr') ? $params->get('sites_use_rdr') : false;
        if ($id) {
            $site = $siteRepository->find($id);
            if (!$site) {
                throw $this->createNotFoundException('Page notice not found.');
            }

            if ($request->request->has('delete')) {
                $em->remove($site);
                $em->flush();
                $loggerService->log(Log::SITE_DELETE, $site->getId());
                $this->addFlash('success', 'Site removed');
                return $this->redirectToRoute('admin_sites');
            }
        } else {
            if ($syncEnabled) {
                // can't create new sites if syncing from rdr
                throw $this->createNotFoundException('Page notice not found.');
            }
            $site = null;
        }
        $disabled = $syncEnabled ? true : false;
        $form = $this->createForm(SiteType::class, $site, ['disabled' => $disabled, 'isProd' => $env->isProd()]);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid() && !$syncEnabled) {
                if ($site) {
                    $duplicateGoogleGroup = $siteRepository->getDuplicateSiteGoogleGroup($form['google_group']->getData(), $id);
                } else {
                    $duplicateGoogleGroup = $siteRepository->getDuplicateGoogleGroup($form['google_group']->getData());
                }
                if ($duplicateGoogleGroup) {
                    $form['google_group']->addError(new FormError('This google group has already been used for another site.'));
                }
            }
            if ($form->isValid()) {
                if ($site) {
                    $em->persist($site);
                    $em->flush();
                    $loggerService->log(Log::SITE_EDIT, $site->getId());
                    $this->addFlash('success', 'Notice added');
                } else {
                    $site = $form->getData();
                    $em->persist($site);
                    $em->flush();
                    $loggerService->log(Log::SITE_ADD, $site->getId());
                    $this->addFlash('success', 'Notice added');
                }
                return $this->redirectToRoute('admin_sites');
            } else {
                if (count($form->getErrors()) == 0) {
                    $form->addError(new FormError('Please correct the errors below'));
                }
            }
        }

        return $this->render('admin/sites/edit.html.twig', [
            'site' => $site,
            'siteForm' => $form->createView()
        ]);
    }

    /**
     * @Route("/sync", name="admin_siteSync")
     */
    public function siteSyncAction(SiteSyncService $siteSyncService, ParameterBagInterface $params, Request $request)
    {
        $preview = $siteSyncService->dryRun();

        if (!$params->has('sites_use_rdr')) {
            $formView = false;
        } else {
            $form = $this->createBuilder(FormType::class)->getForm();
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                if ($request->request->has('awardeeOrgSync')) {
                    $siteSyncService->syncAwardees();
                    $siteSyncService->syncOrganizations();
                } else {
                    $siteSyncService->sync();
                }
                $this->addFlashSuccess('Successfully synced');
                return $this->redirectToRoute('admin_sites');
            }
            $formView = $form->createView();
        }
        $canSync = !empty($preview['deleted']) || !empty($preview['modified']) || !empty($preview['created']);
        return $this->render('admin/sites/sync.html.twig', [
            'preview' => $preview,
            'form' => $formView,
            'canSync' => $canSync
        ]);
    }
}
