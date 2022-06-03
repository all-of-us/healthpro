<?php

namespace App\Service;

use App\Audit\Log;
use App\Entity\Incentive;
use App\Entity\IncentiveImport;
use App\Entity\IncentiveImportRow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;

class IncentiveImportService
{
    public const DEFAULT_CSV_ROWS_LIMIT = 5000;
    public const EMAIL_DOMAIN = 'pmi-ops.org';

    protected $userService;
    protected $em;
    protected $params;
    protected $loggerService;
    protected $session;
    protected $rdrApiService;
    protected $logger;
    protected $incentiveService;
    protected $siteService;

    public function __construct(
        UserService $userService,
        EntityManagerInterface $em,
        ParameterBagInterface $params,
        LoggerService $loggerService,
        RequestStack $requestStack,
        RdrApiService $rdrApiService,
        LoggerInterface $logger,
        IncentiveService $incentiveService,
        SiteService $siteService
    ) {
        $this->userService = $userService;
        $this->em = $em;
        $this->params = $params;
        $this->loggerService = $loggerService;
        $this->session = $requestStack->getSession();
        $this->rdrApiService = $rdrApiService;
        $this->logger = $logger;
        $this->incentiveService = $incentiveService;
        $this->siteService = $siteService;
    }

    public function extractCsvFileData($file, $form)
    {
        $incentives = [];
        $fileHandle = fopen($file->getPathname(), 'r');
        $headers = fgetcsv($fileHandle, 0, ",");
        // Guess file format using headers
        if (count($headers) < 2) {
            $form['incentive_csv']->addError(new FormError("Invalid file format"));
            return;
        }
        $rowsLimit = $this->params->has('csv_rows_limit') ? intval($this->params->get('csv_rows_limit')) : self::DEFAULT_CSV_ROWS_LIMIT;
        $csvFile = file($file->getPathname(), FILE_SKIP_EMPTY_LINES);
        if (count($csvFile) > $rowsLimit + 1) {
            $form['incentive_csv']->addError(new FormError("CSV file rows should not be greater than {$rowsLimit}"));
            return;
        }
        $row = 2;
        while (($data = fgetcsv($fileHandle, 0, ",")) !== false) {
            $incentive = [];
            if (!preg_match("/^P\d{9}+$/", $data[0])) {
                $form['incentive_csv']->addError(new FormError("Invalid participant ID Format {$data[0]} in line {$row}, column 1"));
            }
            if ($this->hasDuplicateParticipantId($incentives, $data[0])) {
                $form['incentive_csv']->addError(new FormError("Duplicate participant ID {$data[0]} in line {$row}, column 1"));
            }
            if ($data[1] && !$this->isValidEmail($data[1])) {
                $form['incentive_csv']->addError(new FormError("Invalid User {$data[1]} in line {$row}, column 2"));
            }
            if (!in_array($data[3], array_values(Incentive::$incentiveOccurrenceChoices))) {
                $form['incentive_csv']->addError(new FormError("Invalid Occurrence {$data[3]} in line {$row}, column 4"));
            }
            if (!in_array($data[5], array_values(Incentive::$incentiveTypeChoices))) {
                $form['incentive_csv']->addError(new FormError("Invalid Type {$data[5]} in line {$row}, column 6"));
            }
            if (!in_array($data[8], array_values(Incentive::$incentiveAmountChoices))) {
                $form['incentive_csv']->addError(new FormError("Invalid Amount {$data[8]} in line {$row}, column 9"));
            }
            if (empty($data[2])) {
                $form['incentive_csv']->addError(new FormError("Please enter date in line {$row}, column 3"));
            }
            if ($data[3] === 'other' && empty($data[4])) {
                $form['incentive_csv']->addError(new FormError("Please enter other occurrence in line {$row}, column 5"));
            }
            if ($data[5] === 'other' && empty($data[7])) {
                $form['incentive_csv']->addError(new FormError("Please enter other type in line {$row}, column 8"));
            }
            if ($data[5] === 'gift_card' && empty($data[6])) {
                $form['incentive_csv']->addError(new FormError("Please enter gift card type in line {$row}, column 7"));
            }
            if ($data[8] === 'other' && empty($data[9])) {
                $form['incentive_csv']->addError(new FormError("Please enter other incentive amount in line {$row}, column 10"));
            }
            $incentive['participant_id'] = $data[0];
            $incentive['user_email'] = $data[1];
            $incentive['incentive_date_given'] = $data[2];
            $incentive['incentive_occurrence'] = $data[3];
            $incentive['other_incentive_occurrence'] = $data[4];
            $incentive['incentive_type'] = $data[5];
            $incentive['gift_card_type'] = $data[6];
            $incentive['other_incentive_type'] = $data[7];
            $incentive['incentive_amount'] = $data[8];
            if ($data[8] === 'other') {
                $incentive['incentive_amount'] = $data[9];
            }
            if ($data[5] === 'promotional') {
                $incentive['incentive_amount'] = 0;
            }
            if ($data[10] === 'yes') {
                $incentive['declined'] = true;
            } else {
                $incentive['declined'] = false;
            }
            $incentive['notes'] = $data[11];
            $incentives[] = $incentive;
            $row++;
        }
        return $incentives;
    }

    private function hasDuplicateParticipantId($incentives, $participantId): bool
    {
        foreach ($incentives as $incentive) {
            if ($incentive['participant_id'] === $participantId) {
                return true;
            }
        }
        return false;
    }

    public function isValidEmail($email): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $email);
            $domain = array_pop($parts);
            return $domain === self::EMAIL_DOMAIN;
        }
        return false;
    }

    public function createIncentives($fileName, $incentives): int
    {
        $incentiveImport = new IncentiveImport();
        $incentiveImport
            ->setFileName($fileName)
            ->setUser($this->userService->getUserEntity())
            ->setSite($this->session->get('site')->id)
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
        $this->loggerService->log(Log::INCENTIVE_IMPORT_ADD, $id);
        $this->em->clear();
        return $id;
    }

    public function getAjaxData($incentiveImport, $incentiveImportRows)
    {
        $rows = [];
        foreach ($incentiveImportRows as $incentiveImportRow) {
            $row = [];
            $row['participantId'] = $incentiveImportRow->getParticipantId();
            $row['userEmail'] = $incentiveImportRow->getUserEmail();
            $row['incentiveDateGiven'] = $incentiveImportRow->getIncentiveDateGiven() ? $incentiveImportRow->getIncentiveDateGiven()->format('n/j/Y') : '';
            $row['incentiveType'] = $incentiveImportRow->getIncentiveType();
            $row['otherIncentiveType'] = $incentiveImportRow->getOtherIncentiveType();
            $row['incentiveOccurrence'] = $incentiveImportRow->getIncentiveOccurrence();
            $row['otherIncentiveOccurrence'] = $incentiveImportRow->getOtherIncentiveOccurrence();
            $row['incentiveAmount'] = $incentiveImportRow->getIncentiveAmount();
            $row['giftCardType'] = $incentiveImportRow->getGiftCardType();
            $row['notes'] = $incentiveImportRow->getNotes();
            $row['declined'] = $incentiveImportRow->getDeclined() ? 'yes' : 'no';
            $createdTs = $incentiveImport->getCreatedTs();
            $row['createdTs'] = $createdTs->setTimezone(new \DateTimeZone($this->userService->getUser()->getTimezone()))->format('n/j/Y g:ia');
            $row['status'] = $incentiveImportRow->getRdrStatus();
            array_push($rows, $row);
        }
        return $rows;
    }

    public function sendIncentive($participantId, $incentive, $user): bool
    {
        $postData = $this->getRdrObject($incentive, $user);
        try {
            $response = $this->rdrApiService->post("rdr/v1/Participant/{$participantId}/Incentives", $postData);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->incentiveId)) {
                $incentive->setUser($user);
                $incentive->setRdrId($result->incentiveId);
                $this->em->persist($incentive);
                $this->loggerService->log(Log::INCENTIVE_ADD, $incentive->getId());
                return true;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    public function sendIncentivesToRdr(): void
    {
        $limit = $this->params->has('patient_status_queue_limit') ? intval($this->params->get('patient_status_queue_limit')) : 0;
        $importRows = $this->em->getRepository(IncentiveImportRow::class)->getIncentiveImportRows($limit);
        $importIds = [];
        foreach ($importRows as $importRow) {
            if (!in_array($importRow['import_id'], $importIds)) {
                $importIds[] = $importRow['import_id'];
            }
            $incentiveImport = $this->em->getRepository(IncentiveImport::class)->find($importRow['import_id']);
            $incentive = $this->getIncentiveFromImportData($importRow, $incentiveImport);
            $validUser = true;
            $user = null;
            if ($importRow['user_email']) {
                $user = $this->userService->getUserEntityFromEmail($importRow['user_email']);
                if ($user === null) {
                    $validUser = false;
                }
            }
            $incentiveImportRow = $this->em->getRepository(IncentiveImportRow::class)->find($importRow['id']);
            if ($incentiveImportRow) {
                if ($validUser) {
                    if ($this->sendIncentive($importRow['participant_id'], $incentive, $user)) {
                        $incentiveImportRow->setRdrStatus(IncentiveImportRow::STATUS_SUCCESS);
                    } else {
                        $this->logger->error("#{$importRow['id']} failed sending to RDR: " . $this->rdrApiService->getLastError());
                        $rdrStatus = IncentiveImportRow::STATUS_OTHER_RDR_ERRORS;
                        if ($this->rdrApiService->getLastErrorCode() === 404) {
                            $rdrStatus = IncentiveImportRow::STATUS_INVALID_PARTICIPANT_ID;
                        } elseif ($this->rdrApiService->getLastErrorCode() === 500) {
                            $rdrStatus = IncentiveImportRow::STATUS_RDR_INTERNAL_SERVER_ERROR;
                        }
                        $incentiveImportRow->setRdrStatus($rdrStatus);
                    }
                } else {
                    $incentiveImportRow->setRdrStatus(IncentiveImportRow::STATUS_INVALID_USER);
                }
                $this->em->persist($incentiveImportRow);
                $this->em->flush();
                $this->em->clear();
            }
        }
        $this->updateImportStatus($importIds);
    }

    public function getIncentiveFromImportData($importData, $incentiveImport): Incentive
    {
        $incentive = new Incentive();
        if ($importData['incentive_date_given']) {
            $incentiveGivenDate = new \DateTime($importData['incentive_date_given']);
            $incentive->setIncentiveDateGiven($incentiveGivenDate);
        }
        if ($importData['incentive_type']) {
            $incentive->setIncentiveType($importData['incentive_type']);
        }
        if ($importData['other_incentive_type']) {
            $incentive->setOtherIncentiveType($importData['other_incentive_type']);
        }
        if ($importData['incentive_occurrence']) {
            $incentive->setIncentiveOccurrence($importData['incentive_occurrence']);
        }
        if ($importData['other_incentive_occurrence']) {
            $incentive->setOtherIncentiveOccurrence($importData['other_incentive_occurrence']);
        }
        if ($importData['incentive_amount']) {
            $incentive->setIncentiveAmount($importData['incentive_amount']);
        }
        if ($importData['gift_card_type']) {
            $incentive->setGiftCardType($importData['gift_card_type']);
        }
        if ($importData['notes']) {
            $incentive->setNotes($importData['notes']);
        }
        $incentive->setDeclined($importData['declined']);
        $incentive->setImport($incentiveImport);
        $now = new \DateTime();
        $incentive->setParticipantId($importData['participant_id']);
        $incentive->setCreatedTs($now);
        $incentive->setSite($importData['site']);
        return $incentive;
    }

    private function updateImportStatus($importIds): void
    {
        foreach ($importIds as $importId) {
            $incentiveImport = $this->em->getRepository(IncentiveImport::class)->find($importId);
            if (!empty($incentiveImport)) {
                $incentiveImportRows = $this->em->getRepository(IncentiveImportRow::class)->findBy([
                    'import' => $incentiveImport,
                    'rdrStatus' => 0
                ]);
                if (empty($incentiveImportRows)) {
                    $incentiveImportRows = $this->em->getRepository(IncentiveImportRow::class)->findBy([
                        'import' => $incentiveImport,
                        'rdrStatus' => [2, 3, 4, 5]
                    ]);
                    if (!empty($incentiveImportRows)) {
                        $incentiveImport->setImportStatus(IncentiveImport::COMPLETE_WITH_ERRORS);
                    } else {
                        $incentiveImport->setImportStatus(IncentiveImport::COMPLETE);
                    }
                    $this->em->persist($incentiveImport);
                    $this->em->flush();
                    $this->loggerService->log(Log::PATIENT_STATUS_IMPORT_EDIT, $importId);
                }
            }
        }
    }

    public function deleteUnconfirmedImportData(): void
    {
        $date = (new \DateTime('UTC'))->modify('-1 hours');
        $date = $date->format('Y-m-d H:i:s');
        $this->em->getRepository(IncentiveImportRow::class)->deleteUnconfirmedImportData($date);
    }

    public function getRdrObject($incentive, $email)
    {
        $obj = new \StdClass();
        $obj->createdBy = $email;
        $obj->site = $this->siteService->getSiteWithPrefix($incentive->getSite());
        $obj->dateGiven = $incentive->getIncentiveDateGiven();
        $obj->occurrence = $incentive->getOtherIncentiveOccurrence() ?? $incentive->getIncentiveOccurrence();
        $obj->incentiveType = $incentive->getOtherIncentiveType() ?: $incentive->getIncentiveType();
        if ($incentive->getGiftCardType()) {
            $obj->giftcardType = $incentive->getGiftCardType();
        }
        $obj->amount = $incentive->getIncentiveAmount();
        $obj->notes = $incentive->getNotes();
        $obj->declined = $incentive->getDeclined();
        return $obj;
    }
}
