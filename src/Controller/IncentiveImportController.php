<?php

namespace App\Controller;

use App\Entity\IncentiveImportRow;
use App\Entity\IncentiveImport;
use App\Form\IncentiveImportFormType;
use App\Service\IncentiveImportService;
use App\Service\LoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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
        SessionInterface $session,
        LoggerService $loggerService,
        IncentiveImportService $incentiveImportService
    ) {
        $form = $this->createForm(IncentiveImportFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $file = $form['incentive_csv']->getData();
            $fileName = $file->getClientOriginalName();
            $incentives = [];
            $incentiveImportService->extractCsvFileData($file, $form, $incentives);
            if ($form->isValid()) {
                if (!empty($incentives)) {
                    $incentiveImport = new IncentiveImport();
                    $incentiveImport
                        ->setFileName($fileName)
                        ->setUser($this->getUserEntity())
                        ->setSite($session->get('site')->id)
                        ->setCreatedTs(new \DateTime());
                    $this->em->persist($incentiveImport);
                    $batchSize = 50;
                    foreach ($incentives as $key => $incentive) {
                        $incentiveImportRow = new IncentiveImportRow();
                        $incentiveImportRow
                            ->setParticipantId($incentive['participant_id'])
                            ->setUserEmail($incentive['user_email'])
                            ->setIncentiveDateGiven(new \DateTime($incentive['incentive_date_given']))
                            ->setIncentiveOccurrence($incentive['incentive_occurrence'])
                            ->setOtherIncentiveOccurrence($incentive['other_incentive_occurrence'])
                            ->setIncentiveType($incentive['incentive_type'])
                            ->setGiftCardType($incentive['gift_card_type'])
                            ->setOtherIncentiveType($incentive['other_incentive_type'])
                            ->setIncentiveAmount($incentive['incentive_amount'])
                            ->setDeclined($incentive['declined'])
                            ->setNotes($incentive['notes'])
                            ->setImport($incentiveImport);
                        $this->em->persist($incentiveImportRow);
                        if (($key % $batchSize) === 0) {
                            $this->em->flush();
                            $this->em->clear(IncentiveImportRow::class);
                        }
                    }
                    $this->em->flush();
                    $id = $incentiveImport->getId();
                    $loggerService->log(Log::INCENTIVE_IMPORT_ADD, $id);
                    $this->em->clear();
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
        return '';
    }

    /**
     * @Route("/incentive/import/{id}", name="incentiveImportDetails", methods={"GET", "POST"})
     */
    public function patientStatusImportDetails(int $id, Request $request, IncentiveImportService $incentiveImportService)
    {
        return '';
    }
}
