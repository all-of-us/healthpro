<?php

namespace App\Controller;

use App\Audit\Log;
use App\Form\SiteType;
use App\Repository\SiteRepository;
use App\Service\EnvironmentService;
use App\Service\LoggerService;
use App\Service\SiteSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/sites")
 */
class SitesController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    /**
     * @Route("/", name="admin_sites")
     */
    public function index(SiteRepository $siteRepository, ParameterBagInterface $params)
    {
        $sites = $siteRepository->findBy(['deleted' => 0], ['name' => 'asc']);
        return $this->render('admin/sites/index.html.twig', [
            'sites' => $sites,
            'sync' => $params->has('sites_use_rdr') ? $params->get('sites_use_rdr') : false, // @phpstan-ignore-line
            'siteChoices' => SiteType::$siteChoices
        ]);
    }

    /**
     * @Route("/site/{id}", name="admin_site")
     */
    public function edit(SiteRepository $siteRepository, LoggerService $loggerService, Request $request, ParameterBagInterface $params, EnvironmentService $env, $id = null)
    {
        $syncEnabled = $params->has('sites_use_rdr') ? $params->get('sites_use_rdr') : false; // @phpstan-ignore-line
        if ($id) {
            $site = $siteRepository->find($id);
            if (!$site) {
                throw $this->createNotFoundException('Site not found.');
            }

            if ($request->request->has('delete')) {
                $this->em->remove($site);
                $this->em->flush();
                $loggerService->log(Log::SITE_DELETE, $site->getId());
                $this->addFlash('success', 'Site removed.');
                return $this->redirectToRoute('admin_sites');
            }
        } else {
            if ($syncEnabled) {
                // can't create new sites if syncing from rdr
                throw $this->createNotFoundException('Sites cannot be created when the RDR Awardee API is enabled.');
            }
            $site = null;
        }
        $disabled = $syncEnabled ? true : false;
        $form = $this->createForm(SiteType::class, $site, ['isDisabled' => $disabled, 'isProd' => $env->isProd()]);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid() && !$syncEnabled) {
                if ($site) {
                    $duplicateGoogleGroup = $siteRepository->getDuplicateSiteGoogleGroup($form['google_group']->getData(), $id);
                } else {
                    $duplicateGoogleGroup = $siteRepository->getDuplicateGoogleGroup($form['google_group']->getData());
                }
                if ($duplicateGoogleGroup) {
                    $form['google_group']->addError(new FormError('This Google Group has already been used for another Site.'));
                }
            }
            if ($form->isValid()) {
                if ($site) {
                    $this->em->persist($site);
                    $this->em->flush();
                    $loggerService->log(Log::SITE_EDIT, $site->getId());
                    $this->addFlash('success', 'Site updated.');
                } else {
                    $site = $form->getData();
                    $this->em->persist($site);
                    $this->em->flush();
                    $loggerService->log(Log::SITE_ADD, $site->getId());
                    $this->addFlash('success', 'Site added.');
                }
                return $this->redirectToRoute('admin_sites');
            } else {
                if (count($form->getErrors()) == 0) {
                    $form->addError(new FormError('Please correct the errors below.'));
                }
            }
        }

        return $this->render('admin/sites/edit.html.twig', [
            'site' => $site,
            'siteForm' => $form->createView()
        ]);
    }

    /**
     * @Route("/site/{id}/emails", name="admin_site_emails")
     */
    public function siteAdminEmails(SiteRepository $siteRepository, EnvironmentService $env, SiteSyncService $siteSyncService, int $id)
    {
        $site = $siteRepository->find($id);
        if (!$site) {
            throw $this->createNotFoundException('Site not found.');
        }
        try {
            $emails = join(', ', $siteSyncService->getSiteAdminEmails($site));
        } catch (Exception $e) {
            $this->addFlash('error', sprintf(
                'Unable to retrieve email for %s (%s)',
                $site->getName(),
                $site->getSiteId(),
            ));
            return $this->redirectToRoute('admin_sites');
        }

        if (!$env->isProd() && !$env->isLocal()) {
            $this->addFlash('error', sprintf(
                'Cannot update emails in this environment. Value would have been: %s',
                $emails ? $emails : '(empty)'
            ));
            return $this->redirectToRoute('admin_sites');
        }

        $site->setEmail($emails);
        $this->em->persist($site);
        $this->em->flush();

        $this->addFlash('success', sprintf(
            '%s (%s) email updated to: %s',
            $site->getName(),
            $site->getSiteId(),
            $emails ? $emails : '(empty)'
        ));
        return $this->redirectToRoute('admin_sites');
    }

    /**
     * @Route("/sync", name="admin_siteSync")
     */
    public function siteSyncAction(SiteSyncService $siteSyncService, ParameterBagInterface $params, Request $request)
    {
        if (!$params->has('sites_use_rdr')) { // @phpstan-ignore-line
            $formView = false;
        } else {
            $form = $this->createForm(FormType::class);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                if ($request->request->has('awardeeOrgSync')) {
                    $siteSyncService->syncAwardees();
                    $siteSyncService->syncOrganizations();
                } else {
                    $siteSyncService->sync();
                }
                $this->addFlash('success', 'Successfully synced');
                return $this->redirectToRoute('admin_sites');
            }
            $formView = $form->createView();
        }
        $preview = $siteSyncService->dryRun();
        $canSync = !empty($preview['deleted']) || !empty($preview['modified']) || !empty($preview['created']);
        return $this->render('admin/sites/sync.html.twig', [
            'preview' => $preview,
            'form' => $formView,
            'canSync' => $canSync
        ]);
    }
}
