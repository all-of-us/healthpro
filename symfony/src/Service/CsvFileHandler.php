<?php

namespace App\Service;

use Pmi\PatientStatus\PatientStatus;
use Symfony\Component\Form\FormError;

class CsvFileHandler
{

    public function extractCsvFileData($file, &$form, &$patientStatuses)
    {
        $fileHandle = fopen($file->getPathname(), 'r');
        $validStatus = array_values(PatientStatus::$patientStatus);
        $row = 1;
        while (($data = fgetcsv($fileHandle, 0, ",")) !== false) {
            if ($row === 1) {
                $row++;
                continue;
            }
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
}
