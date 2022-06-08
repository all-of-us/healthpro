<?php

namespace App\Controller;

use App\Entity\IdVerificationImport;
use App\Form\IdVerificationImportFormType;
use App\Service\IdVerificationImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class IdVerificationImportController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    /**
     * @Route("/id-verification/import", name="idVerificationImport", methods={"GET","POST"})
     */
    public function idVerificationImport(
        Request $request,
        IdVerificationImportService $idVerificationImportService
    ) {
        $form = $this->createForm(IdVerificationImportFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $file = $form['id_verification_csv']->getData();
            $fileName = $file->getClientOriginalName();
            $idVerifications = $idVerificationImportService->extractCsvFileData($file, $form);
            if ($form->isValid()) {
                if (!empty($idVerifications)) {
                    // TODO create id verifications import rows and redirect to confirmation
                }
            } else {
                $form->addError(new FormError('Please correct the errors below'));
            }
        }
        $idVerificationImports = $this->em->getRepository(IdVerificationImport::class)->findBy([
            'user' => $this->getUserEntity(),
            'confirm' => 1
        ], ['id' => 'DESC']);
        return $this->render('idverification/import.html.twig', [
            'idVerificationForm' => $form->createView(),
            'idVerifications' => $idVerificationImports
        ]);
    }

    /**
     * @Route("/id-verification/import/{id}", name="idVerificationImportDetails", methods={"GET", "POST"})
     */
    public function idVerificationImportDetails(int $id, Request $request, IdVerificationImportService $idVerificationImportService)
    {
        return '';
    }
}
