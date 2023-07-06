<?php

namespace App\Controller;

use App\Audit\Log;
use App\Form\NphSiteType;
use App\Repository\NphSiteRepository;
use App\Service\LoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/nph/admin')]
class NphAdminController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    #[Route(path: '/', name: 'nph_admin_home')]
    public function index()
    {
        return $this->render('program/nph/admin/index.html.twig');
    }

    #[Route(path: '/sites', name: 'nph_admin_sites')]
    public function sitesAction(NphSiteRepository $nphSiteRepository, ParameterBagInterface $params)
    {
        $sites = $nphSiteRepository->findBy(['deleted' => 0], ['name' => 'asc']);
        return $this->render('program/nph/admin/sites/index.html.twig', [
            'sites' => $sites,
            'sync' => $params->has('nph_sites_use_rdr') ? $params->get('nph_sites_use_rdr') : false,
            'siteChoices' => NphSiteType::$siteChoices
        ]);
    }

    #[Route(path: '/sites/site/{id}', name: 'nph_admin_site')]
    public function edit(
        NphSiteRepository $nphSiteRepository,
        LoggerService $loggerService,
        Request $request,
        $id = null
    ) {
        if ($id) {
            $site = $nphSiteRepository->find($id);
            if (!$site) {
                throw $this->createNotFoundException('Site not found.');
            }
            if ($request->request->has('delete')) {
                $this->em->remove($site);
                $this->em->flush();
                $loggerService->log(Log::NPH_SITE_DELETE, $site->getId());
                $this->addFlash('success', 'Site removed.');
                return $this->redirectToRoute('nph_admin_sites');
            }
        } else {
            $site = null;
        }
        $form = $this->createForm(NphSiteType::class, $site);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                if ($site) {
                    $duplicateGoogleGroup = $nphSiteRepository->getDuplicateGoogleGroup($form['google_group']->getData(), $id);
                } else {
                    $duplicateGoogleGroup = $nphSiteRepository->getDuplicateGoogleGroup($form['google_group']->getData());
                }
                if ($duplicateGoogleGroup) {
                    $form['google_group']->addError(new FormError('This Google Group has already been used for another Site.'));
                }
            }
            if ($form->isValid()) {
                if ($site) {
                    $this->em->persist($site);
                    $this->em->flush();
                    $loggerService->log(Log::NPH_SITE_EDIT, $site->getId());
                    $this->addFlash('success', 'Site updated.');
                } else {
                    $site = $form->getData();
                    $this->em->persist($site);
                    $this->em->flush();
                    $loggerService->log(Log::NPH_SITE_ADD, $site->getId());
                    $this->addFlash('success', 'Site added.');
                }
                return $this->redirectToRoute('nph_admin_sites');
            }
            if (count($form->getErrors()) == 0) {
                $form->addError(new FormError('Please correct the errors below.'));
            }
        }
        return $this->render('program/nph/admin/sites/edit.html.twig', [
            'site' => $site,
            'siteForm' => $form->createView()
        ]);
    }
}
