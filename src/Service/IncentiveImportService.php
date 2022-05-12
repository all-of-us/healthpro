<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;

class IncentiveImportService
{
    public const DEFAULT_CSV_ROWS_LIMIT = 5000;
    public const EMAIL_DOMAIN = 'pmi-ops.org';

    protected $userService;
    protected $em;
    protected $params;

    public function __construct(UserService $userService, EntityManagerInterface $em, ParameterBagInterface $params)
    {
        $this->userService = $userService;
        $this->em = $em;
        $this->params = $params;
    }

    public function extractCsvFileData($file, &$form, &$incentives): void
    {
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
                $form['incentive_csv']->addError(new FormError("Invalid email address {$data[1]} in line {$row}, column 2"));
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

    private function isValidEmail($email): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $email);
            $domain = array_pop($parts);
            return $domain === self::EMAIL_DOMAIN;
        }
        return false;
    }
}
