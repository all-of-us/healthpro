<?php

namespace App\Controller;

use App\Entity\IncentiveImport;
use App\Form\IncentiveImportConfirmFormType;
use App\Form\IncentiveImportFormType;
use App\Service\IncentiveImportService;
use App\Service\LoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Audit\Log;

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
        Request $request,
        IncentiveImportService $incentiveImportService
    ) {
        $form = $this->createForm(IncentiveImportFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $file = $form['incentive_csv']->getData();
            $fileName = $file->getClientOriginalName();
            $incentives = $incentiveImportService->extractCsvFileData($file, $form);
            if ($form->isValid()) {
                if (!empty($incentives)) {
                    $id = $incentiveImportService->createIncentives($fileName, $incentives);
                    return $this->redirectToRoute('incentiveImportConfirmation', ['id' => $id]);
                }
            } else {
                $form->addError(new FormError('Please correct the errors below'));
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

    /**
     * @Route("/incentive/confirmation/{id}", name="incentiveImportConfirmation", methods={"GET", "POST"})
     */
    public function incentiveImportConfirmation(int $id, Request $request, LoggerService $loggerService)
    {
        $incentiveImport = $this->em->getRepository(IncentiveImport::class)->findOneBy(['id' => $id, 'user' => $this->getUserEntity(), 'confirm' => 0]);
        if (empty($incentiveImport)) {
            throw $this->createNotFoundException('Page Not Found!');
        }
        $form = $this->createForm(IncentiveImportConfirmFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var SubmitButton $confirmButton */
            $confirmButton = $form->get('Confirm');
            if ($confirmButton->isClicked()) {
                // Update confirm status
                $incentiveImport->setConfirm(1);
                $this->em->flush();
                $loggerService->log(Log::INCENTIVE_IMPORT_EDIT, $incentiveImport->getId());
                $this->addFlash('success', 'Successfully Imported!');
            } else {
                $this->addFlash('notice', 'Import canceled!');
                $this->em->remove($incentiveImport);
                $this->em->flush();
            }
            return $this->redirectToRoute('incentiveImport');
        }
        $importIncentives = $incentiveImport->getIncentiveImportRows()->slice(0, 100);
        return $this->render('incentive/confirmation.html.twig', [
            'importIncentives' => $importIncentives,
            'importConfirmForm' => $form->createView(),
            'rowsCount' => count($incentiveImport->getIncentiveImportRows())
        ]);
    }

    /**
     * @Route("/incentive/import/{id}", name="incentiveImportDetails", methods={"GET", "POST"})
     */
    public function incentiveImportDetails(int $id, Request $request, IncentiveImportService $incentiveImportService)
    {
        $incentiveImport = $this->em->getRepository(IncentiveImport::class)->findOneBy([
            'id' => $id,
            'user' => $this->getUserEntity(),
            'confirm' => 1
        ]);
        if (empty($incentiveImport)) {
            throw $this->createNotFoundException('Page Not Found!');
        }
        //For ajax requests
        if ($request->isXmlHttpRequest()) {
            $params = $request->request->all();
            $incentiveImportRows = $incentiveImport->getIncentiveImportRows()->slice($params['start'], $params['length']);
            $ajaxData = [];
            $ajaxData['data'] = $incentiveImportService->getAjaxData($incentiveImport, $incentiveImportRows);
            $ajaxData['recordsTotal'] = $ajaxData['recordsFiltered'] = count($incentiveImport->getIncentiveImportRows());
            return $this->json($ajaxData);
        }
        return $this->render('incentive/import-details.html.twig');
    }
}
