<?php

namespace App\Controller;

use App\Audit\Log;
use App\Entity\IdVerificationImport;
use App\Form\IdVerificationImportConfirmFormType;
use App\Form\IdVerificationImportFormType;
use App\Service\IdVerificationImportService;
use App\Service\LoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\SubmitButton;
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
                    $id = $idVerificationImportService->createIdVerifications($fileName, $idVerifications);
                    return $this->redirectToRoute('idVerificationImportConfirmation', ['id' => $id]);
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
     * @Route("/id-verification/confirmation/{id}", name="idVerificationImportConfirmation", methods={"GET", "POST"})
     */
    public function idVerificationImportConfirmation(int $id, Request $request, LoggerService $loggerService)
    {
        $idVerificationImport = $this->em->getRepository(IdVerificationImport::class)->findOneBy(['id' => $id, 'user' => $this->getUserEntity(), 'confirm' => 0]);
        if (empty($idVerificationImport)) {
            throw $this->createNotFoundException('Page Not Found!');
        }
        $form = $this->createForm(IdVerificationImportConfirmFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var SubmitButton $confirmButton */
            $confirmButton = $form->get('Confirm');
            if ($confirmButton->isClicked()) {
                // Update confirm status
                $idVerificationImport->setConfirm(1);
                $this->em->flush();
                $loggerService->log(Log::ID_VERIFICATION_IMPORT_EDIT, $idVerificationImport->getId());
                $this->addFlash('success', 'Successfully Imported!');
            } else {
                $this->addFlash('notice', 'Import canceled!');
                $this->em->remove($idVerificationImport);
                $this->em->flush();
            }
            return $this->redirectToRoute('idVerificationImport');
        }
        $importIdVerifications = $idVerificationImport->getIdVerificationImportRows()->slice(0, 100);
        return $this->render('idverification/confirmation.html.twig', [
            'importIdVerifications' => $importIdVerifications,
            'importConfirmForm' => $form->createView(),
            'rowsCount' => count($idVerificationImport->getIdVerificationImportRows())
        ]);
    }

    /**
     * @Route("/id-verification/import/{id}", name="idVerificationImportDetails", methods={"GET", "POST"})
     */
    public function idVerificationImportDetails(int $id, Request $request, IdVerificationImportService $idVerificationImportService)
    {
        $idVerificationImport = $this->em->getRepository(IdVerificationImport::class)->findOneBy([
            'id' => $id,
            'user' => $this->getUserEntity(),
            'confirm' => 1
        ]);
        if (empty($idVerificationImport)) {
            throw $this->createNotFoundException('Page Not Found!');
        }
        //For ajax requests
        if ($request->isXmlHttpRequest()) {
            $params = $request->request->all();
            $idVerificationImportRows = $idVerificationImport->getIdVerificationImportRows()->slice($params['start'], $params['length']);
            $ajaxData = [];
            $ajaxData['data'] = $idVerificationImportService->getAjaxData($idVerificationImportRows, $idVerificationImport->getCreatedTs());
            $ajaxData['recordsTotal'] = $ajaxData['recordsFiltered'] = count($idVerificationImport->getIdVerificationImportRows());
            return $this->json($ajaxData);
        }
        return $this->render('idverification/import-details.html.twig');
    }
}
