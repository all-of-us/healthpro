<?php

namespace App\Service;

use App\Entity\PatientStatusHistory;
use App\Entity\PatientStatus;
use Doctrine\ORM\EntityManagerInterface;
use Pmi\PatientStatus\PatientStatus as PmiPatientStatus;
use Symfony\Component\Form\FormError;

class PatientStatusImportService
{
    public function __construct(UserService $userService, EntityManagerInterface $em)
    {
        $this->userService = $userService;
        $this->em = $em;
    }

    public function extractCsvFileData($file, &$form, &$patientStatuses)
    {
        $fileHandle = fopen($file->getPathname(), 'r');
        $headers = fgetcsv($fileHandle, 0, ",");
        $validHeaders = ['participantid', 'status', 'comments'];
        if (count($headers) !== 3) {
            $form['patient_status_csv']->addError(new FormError("Invalid file format"));
            return;
        }
        foreach ($headers as $header) {
            // Handle bom
            $header = str_replace("\xEF\xBB\xBF", '', $header);
            if (!in_array(str_replace(' ', '', strtolower($header)), $validHeaders)) {
                $form['patient_status_csv']->addError(new FormError("Invalid column headers"));
                return;
            }
        }
        $validStatus = array_values(PmiPatientStatus::$patientStatus);
        $row = 1;
        $csvFile = file($file->getPathname(), FILE_SKIP_EMPTY_LINES);
        if (count($csvFile) > 5001) {
            $form['patient_status_csv']->addError(new FormError("CSV file rows should not be greater than 5000"));
            return;
        }
        while (($data = fgetcsv($fileHandle, 0, ",")) !== false) {
            $patientStatus = [];
            if (!preg_match("/^P\d{9}+$/", $data[0])) {
                $form['patient_status_csv']->addError(new FormError("Invalid participant ID Format {$data[0]} in line {$row}, column 1"));
            }
            $patientStatus['participantId'] = $data[0];
            if (!in_array($data[1], $validStatus)) {
                $form['patient_status_csv']->addError(new FormError("Invalid patient status {$data[1]} in line {$row}, column 2"));
            }
            $patientStatus['status'] = $data[1];
            $patientStatus['comments'] = $data[2];
            $patientStatuses[] = $patientStatus;
            $row++;
        }
    }

    public function getAjaxData($patientStatusImport, $patientStatuses)
    {
        $rows = [];
        foreach ($patientStatuses as $patientStatus) {
            $row = [];
            $row['participantId'] = $patientStatus->getParticipantId();
            $row['patientStatus'] = $patientStatus->getStatus();
            $row['comments'] = $patientStatus->getComments();
            $row['organizationName'] = $patientStatusImport->getOrganization()->getName() . " ({$patientStatusImport->getOrganization()->getId()})";
            $createdTs = $patientStatusImport->getCreatedTs();
            $row['createdTs'] = $createdTs->setTimezone(new \DateTimeZone($this->userService->getUser()->getInfo()['timezone']))->format('n/j/Y g:ia');
            $row['status'] = $patientStatus->getRdrStatus();
            array_push($rows, $row);
        }
        return $rows;
    }
}
