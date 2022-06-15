<?php

namespace App\Service;

use App\Audit\Log;
use App\Entity\IdVerificationImport;
use App\Entity\IdVerificationImportRow;
use App\Form\IdVerificationType;
use App\Helper\Import;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;

class IdVerificationImportService
{
    protected $userService;
    protected $em;
    protected $params;
    protected $loggerService;
    protected $session;
    protected $rdrApiService;
    protected $logger;
    protected $idVerificationService;
    protected $siteService;

    public function __construct(
        UserService $userService,
        EntityManagerInterface $em,
        ParameterBagInterface $params,
        LoggerService $loggerService,
        RequestStack $requestStack,
        RdrApiService $rdrApiService,
        LoggerInterface $logger,
        IdVerificationService $idVerificationService,
        SiteService $siteService
    ) {
        $this->userService = $userService;
        $this->em = $em;
        $this->params = $params;
        $this->loggerService = $loggerService;
        $this->session = $requestStack->getSession();
        $this->rdrApiService = $rdrApiService;
        $this->logger = $logger;
        $this->idVerificationService = $idVerificationService;
        $this->siteService = $siteService;
    }

    public function extractCsvFileData($file, $form)
    {
        $idVerifications = [];
        $fileHandle = fopen($file->getPathname(), 'r');
        $headers = fgetcsv($fileHandle, 0, ",");
        // Guess file format using headers
        if (count($headers) < 2) {
            $form['id_verification_csv']->addError(new FormError("Invalid file format"));
            return;
        }
        $rowsLimit = $this->params->has('csv_rows_limit') ? intval($this->params->get('csv_rows_limit')) : Import::DEFAULT_CSV_ROWS_LIMIT;
        $csvFile = file($file->getPathname(), FILE_SKIP_EMPTY_LINES);
        if (count($csvFile) > $rowsLimit + 1) {
            $form['id_verification_csv']->addError(new FormError("CSV file rows should not be greater than {$rowsLimit}"));
            return;
        }
        $row = 2;
        while (($data = fgetcsv($fileHandle, 0, ",")) !== false) {
            $idVerification = [];
            if (!Import::isValidParticipantId($data[0])) {
                $form['id_verification_csv']->addError(new FormError("Invalid participant ID Format {$data[0]} in line {$row}, column 1"));
            }
            if (Import::hasDuplicateParticipantId($idVerifications, $data[0])) {
                $form['id_verification_csv']->addError(new FormError("Duplicate participant ID {$data[0]} in line {$row}, column 1"));
            }
            if ($data[1] && !Import::isValidEmail($data[1])) {
                $form['id_verification_csv']->addError(new FormError("Invalid User {$data[1]} in line {$row}, column 2"));
            }
            if (!empty($data[2])) {
                if (!Import::isValidDate($data[2])) {
                    $form['id_verification_csv']->addError(new FormError("Invalid date in line {$row}, column 3"));
                }
            } else {
                $form['id_verification_csv']->addError(new FormError("Please enter date in line {$row}, column 3"));
            }
            if ($data[3] && !in_array($data[3], array_values(IdVerificationType::$idVerificationChoices['verificationType']))) {
                $form['id_verification_csv']->addError(new FormError("Invalid verification type {$data[3]} in line {$row}, column 4"));
            }
            if ($data[4] && !in_array($data[4], array_values(IdVerificationType::$idVerificationChoices['visitType']))) {
                $form['id_verification_csv']->addError(new FormError("Invalid visit type {$data[4]} in line {$row}, column 5"));
            }
            $idVerification['participant_id'] = $data[0];
            $idVerification['user_email'] = $data[1];
            $idVerification['idVerification_date'] = $data[2];
            $idVerification['verification_type'] = $data[3];
            $idVerification['visit_type'] = $data[4];
            $idVerifications[] = $idVerification;
            $row++;
        }
        return $idVerifications;
    }

    public function createIdVerifications($fileName, $idVerifications): int
    {
        $idVerificationImport = new IdVerificationImport();
        $idVerificationImport
            ->setFileName($fileName)
            ->setUser($this->userService->getUserEntity())
            ->setSite($this->session->get('site')->id)
            ->setCreatedTs(new \DateTime());
        $this->em->persist($idVerificationImport);
        $batchSize = 50;
        foreach ($idVerifications as $key => $idVerification) {
            $idVerificationImportRow = new IdVerificationImportRow();
            $idVerificationImportRow
                ->setParticipantId($idVerification['participant_id'])
                ->setUserEmail($idVerification['user_email'])
                ->setVerifiedDate(new \DateTime($idVerification['idVerification_date']))
                ->setVerificationType($idVerification['verification_type'])
                ->setVisitType($idVerification['visit_type'])
                ->setImport($idVerificationImport);
            $this->em->persist($idVerificationImportRow);
            if (($key % $batchSize) === 0) {
                $this->em->flush();
                $this->em->clear(IdVerificationImportRow::class);
            }
        }
        $this->em->flush();
        $id = $idVerificationImport->getId();
        $this->loggerService->log(Log::ID_VERIFICATION_ADD, $id);
        $this->em->clear();
        return $id;
    }

    public function getAjaxData($idVerificationImportRows, $createdTs): array
    {
        $rows = [];
        foreach ($idVerificationImportRows as $idVerificationImportRow) {
            $row = [];
            $row['participantId'] = $idVerificationImportRow->getParticipantId();
            $row['userEmail'] = $idVerificationImportRow->getUserEmail();
            $row['verifiedDate'] = $idVerificationImportRow->getVerifiedDate() ? $idVerificationImportRow->getVerifiedDate()->format('n/j/Y') : '';
            $row['verificationType'] = $idVerificationImportRow->getVerificationType();
            $row['visitType'] = $idVerificationImportRow->getVisitType();
            $row['createdTs'] = $createdTs->setTimezone(new \DateTimeZone($this->userService->getUser()->getTimezone()))->format('n/j/Y g:ia');
            $row['status'] = $idVerificationImportRow->getRdrStatus();
            array_push($rows, $row);
        }
        return $rows;
    }

    public function sendIdVerificationsToRdr(): void
    {
        $limit = $this->params->has('patient_status_queue_limit') ? intval($this->params->get('patient_status_queue_limit')) : 0;
        $importRows = $this->em->getRepository(IdVerificationImportRow::class)->getIdVerificationImportRows($limit);
        $importIds = [];
        foreach ($importRows as $importRow) {
            $importRowData = $importRow[0];
            $importRowData['site'] = $importRow['site'];
            if (!in_array($importRowData['import_id'], $importIds)) {
                $importIds[] = $importRowData['import_id'];
            }
            $idVerificationImport = $this->em->getRepository(IdVerificationImport::class)->find($importRowData['import_id']);
            $idVerification = $this->getIdVerificationFromImportData($importRowData);
            $validUser = true;
            $user = null;
            if ($importRowData['userEmail']) {
                $user = $this->userService->getUserEntityFromEmail($importRowData['userEmail']);
                if ($user === null) {
                    $validUser = false;
                }
            }
            $idVerificationImportRow = $this->em->getRepository(IdVerificationImportRow::class)->find($importRowData['id']);
            if ($idVerificationImportRow) {
                if ($validUser) {
                    if ($this->sendIdVerification($idVerification)) {
                        $idVerificationImportRow->setRdrStatus(Import::STATUS_SUCCESS);
                    } else {
                        $this->logger->error("#{$importRowData['id']} failed sending to RDR: " . $this->rdrApiService->getLastError());
                        $rdrStatus = Import::STATUS_OTHER_RDR_ERRORS;
                        if ($this->rdrApiService->getLastErrorCode() === 404) {
                            $rdrStatus = Import::STATUS_INVALID_PARTICIPANT_ID;
                        } elseif ($this->rdrApiService->getLastErrorCode() === 500) {
                            $rdrStatus = Import::STATUS_RDR_INTERNAL_SERVER_ERROR;
                        }
                        $idVerificationImportRow->setRdrStatus($rdrStatus);
                    }
                } else {
                    $idVerificationImportRow->setRdrStatus(Import::STATUS_INVALID_USER);
                }
                $this->em->persist($idVerificationImportRow);
                $this->em->flush();
                $this->em->clear();
            }
        }
    }

    public function getIdVerificationFromImportData($importData): array
    {
        $idVerification = [];
        $idVerification['participantId'] = $importData['participantId'];
        $idVerification['verifiedTime'] = $importData['verifiedDate']->format('Y-m-d\TH:i:s\Z');
        $idVerification['siteGoogleGroup'] = $this->siteService->getSiteWithPrefix($importData['site']);
        if ($importData['userEmail']) {
            $idVerification['userEmail'] = $importData['userEmail'];
        }
        if ($importData['verificationType']) {
            $idVerification['verificationType'] = $importData['verificationType'];
        }
        if ($importData['visitType']) {
            $idVerification['visitType'] = $importData['visitType'];
        }
        return $idVerification;
    }

    private function sendIdVerification($idVerification): bool
    {
        $postData = $this->idVerificationService->getRdrObject($idVerification);
        return $this->idVerificationService->sendToRdr($postData);
    }
}
