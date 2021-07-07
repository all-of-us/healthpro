<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;

class PatientStatusImportService
{

    const DEFAULT_CSV_ROWS_LIMIT = 5000;

    public static $patientStatus = [
        'Yes: Confirmed in EHR system' => 'YES',
        'No: Not found in EHR system' => 'NO',
        'No Access: Unable to check EHR system' => 'NO_ACCESS',
        'Unknown: Inconclusive search results' => 'UNKNOWN'
    ];

    protected $userService;
    protected $em;
    protected $params;

    public function __construct(UserService $userService, EntityManagerInterface $em, ParameterBagInterface $params)
    {
        $this->userService = $userService;
        $this->em = $em;
        $this->params = $params;
    }

    public function extractCsvFileData($file, &$form, &$patientStatuses)
    {
        $fileHandle = fopen($file->getPathname(), 'r');
        $headers = fgetcsv($fileHandle, 0, ",");
        // Guess file format using headers
        if (count($headers) < 2) {
            $form['patient_status_csv']->addError(new FormError("Invalid file format"));
            return;
        }
        $validStatus = array_values(self::$patientStatus);
        $rowsLimit = $this->params->has('csv_rows_limit') ? intval($this->params->get('csv_rows_limit')) : self::DEFAULT_CSV_ROWS_LIMIT;
        $csvFile = file($file->getPathname(), FILE_SKIP_EMPTY_LINES);
        if (count($csvFile) > $rowsLimit + 1) {
            $form['patient_status_csv']->addError(new FormError("CSV file rows should not be greater than {$rowsLimit}"));
            return;
        }
        $row = 2;
        while (($data = fgetcsv($fileHandle, 0, ",")) !== false) {
            $patientStatus = [];
            if (!preg_match("/^P\d{9}+$/", $data[0])) {
                $form['patient_status_csv']->addError(new FormError("Invalid participant ID Format {$data[0]} in line {$row}, column 1"));
            }
            if ($this->hasDuplicateParticipantId($patientStatuses, $data[0])) {
                $form['patient_status_csv']->addError(new FormError("Duplicate participant ID {$data[0]} in line {$row}, column 1"));
            }
            $patientStatus['participantId'] = $data[0];
            if (!in_array($data[1], $validStatus)) {
                $form['patient_status_csv']->addError(new FormError("Invalid patient status {$data[1]} in line {$row}, column 2"));
            }
            $patientStatus['status'] = $data[1];
            $patientStatus['comments'] = !empty($data[2]) ? $data[2] : '';
            $patientStatuses[] = $patientStatus;
            $row++;
        }
    }

    public function getAjaxData($patientStatusImport, $patientStatusImportRows)
    {
        $rows = [];
        foreach ($patientStatusImportRows as $patientStatusImportRow) {
            $row = [];
            $row['participantId'] = $patientStatusImportRow->getParticipantId();
            $row['patientStatus'] = $patientStatusImportRow->getStatus();
            $row['comments'] = $patientStatusImportRow->getComments();
            $row['organizationName'] = $patientStatusImport->getOrganization()->getName() . " ({$patientStatusImport->getOrganization()->getId()})";
            $createdTs = $patientStatusImport->getCreatedTs();
            $row['createdTs'] = $createdTs->setTimezone(new \DateTimeZone($this->userService->getUser()->getTimezone()))->format('n/j/Y g:ia');
            $row['status'] = $patientStatusImportRow->getRdrStatus();
            array_push($rows, $row);
        }
        return $rows;
    }

    private function hasDuplicateParticipantId($patientStatuses, $participantId)
    {
        foreach ($patientStatuses as $patientStatus) {
            if ($patientStatus['participantId'] === $participantId) {
                return true;
            }
        }
        return false;
    }
}
