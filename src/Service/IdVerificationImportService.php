<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;

class IdVerificationImportService
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
        $rowsLimit = $this->params->has('csv_rows_limit') ? intval($this->params->get('csv_rows_limit')) : self::DEFAULT_CSV_ROWS_LIMIT;
        $csvFile = file($file->getPathname(), FILE_SKIP_EMPTY_LINES);
        if (count($csvFile) > $rowsLimit + 1) {
            $form['id_verification_csv']->addError(new FormError("CSV file rows should not be greater than {$rowsLimit}"));
            return;
        }
        $row = 2;
        while (($data = fgetcsv($fileHandle, 0, ",")) !== false) {
            $idVerification = [];
            if (!preg_match("/^P\d{9}+$/", $data[0])) {
                $form['id_verification_csv']->addError(new FormError("Invalid participant ID Format {$data[0]} in line {$row}, column 1"));
            }
            if ($this->hasDuplicateParticipantId($idVerifications, $data[0])) {
                $form['id_verification_csv']->addError(new FormError("Duplicate participant ID {$data[0]} in line {$row}, column 1"));
            }
            if ($data[1] && !$this->isValidEmail($data[1])) {
                $form['id_verification_csv']->addError(new FormError("Invalid User {$data[1]} in line {$row}, column 2"));
            }
            if (empty($data[2])) {
                $form['id_verification_csv']->addError(new FormError("Please enter date in line {$row}, column 3"));
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

    private function hasDuplicateParticipantId($idVerifications, $participantId): bool
    {
        foreach ($idVerifications as $idVerification) {
            if ($idVerification['participant_id'] === $participantId) {
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
}
