<?php

namespace App\Controller;

use App\Form\NphSiteType;
use App\Repository\NphSiteRepository;
use App\Service\EnvironmentService;
use App\Service\LoggerService;
use App\Service\SiteSyncService;
use Doctrine\ORM\EntityManagerInterface;
use App\Audit\Log;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/nph/admin/sites")
 */
class NphAdminController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    /**
     * @Route("/", name="nph_admin_sites")
     */
    public function index(NphSiteRepository $nphSiteRepository, ParameterBagInterface $params)
    {
        return '';
    }

    /**
     * @Route("/site/{id}", name="nph_admin_site")
     */
    public function edit(
        NphSiteRepository $nphSiteRepository,
        LoggerService $loggerService,
        Request $request,
        ParameterBagInterface $params,
        EnvironmentService $env,
        $id = null)
    {
        if ($id) {
            $site = $nphSiteRepository->find($id);
            if (!$site) {
                throw $this->createNotFoundException('Site not found.');
            }
        } else {
            $site = null;
        }
        $form = $this->createForm(NphSiteType::class, $site, ['isDisabled' => false, 'isProd' => $env->isProd()]);
        return $this->render('nphadmin/sites/edit.html.twig', [
            'site' => $site,
            'siteForm' => $form->createView()
        ]);
    }
}
