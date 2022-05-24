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

class IncentiveImportService
{
    public const DEFAULT_CSV_ROWS_LIMIT = 5000;
    public const EMAIL_DOMAIN = 'pmi-ops.org';

    protected $userService;
    protected $em;
    protected $params;
    protected $loggerService;
    protected $session;

    public function __construct(
        UserService $userService,
        EntityManagerInterface $em,
        ParameterBagInterface $params,
        LoggerService $loggerService,
        RequestStack $requestStack
    ) {
        $this->userService = $userService;
        $this->em = $em;
        $this->params = $params;
        $this->loggerService = $loggerService;
        $this->session = $requestStack->getSession();
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
            if (!$this->isValidEmail($data[1])) {
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
            $row['declined'] = $incentiveImportRow->getDeclined();
            $createdTs = $incentiveImport->getCreatedTs();
            $row['createdTs'] = $createdTs->setTimezone(new \DateTimeZone($this->userService->getUser()->getTimezone()))->format('n/j/Y g:ia');
            $row['status'] = $incentiveImportRow->getRdrStatus();
            array_push($rows, $row);
        }
        return $rows;
    }
}
