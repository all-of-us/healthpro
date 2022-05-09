<?php

namespace App\Controller;

use App\Entity\IncentiveImport;
use App\Form\IncentiveImportFormType;
use App\Service\IncentiveImportService;
use App\Service\LoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class IncentiveImportController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    /**
     * @Route("/incentive/import", name="incentiveImport", methods={"GET","POST"})
     */
    public function incentiveImport(
        Request $request
    ) {
        $form = $this->createForm(IncentiveImportFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $file = $form['incentive']->getData();
            $fileName = $file->getClientOriginalName();
            $incentives = [];
            if ($form->isValid()) {
                // Handle CSV Import
            }
        }
        $incentiveImports = $this->em->getRepository(IncentiveImport::class)->findBy([
            'user' => $this->getUserEntity(),
            'confirm' => 1
        ], ['id' => 'DESC']);
        return $this->render('incentive/import.html.twig', [
            'importForm' => $form->createView(),
            'imports' => $incentiveImports
        ]);
    }
}
