<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Pmi\Evaluation\Fhir;
use Pmi\Evaluation\InvalidSchemaException;
use Pmi\Evaluation\MissingSchemaException;

class MeasurementService
{
    const CURRENT_VERSION = '0.3.3';

    protected $em;
    protected $session;
    protected $loggerService;
    protected $userService;
    protected $rdrApiService;
    protected $version;
    protected $data;
    protected $schema;
    protected $participant;
    protected $createdUser;
    protected $createdSite;
    protected $finalizedUser;
    protected $finalizedSite;
    protected $locked = false;

    public function __construct(EntityManagerInterface $em, SessionInterface $session, LoggerService $loggerService, UserService $userService, RdrApiService $rdrApiService)
    {
        $this->em = $em;
        $this->session = $session;
        $this->loggerService = $loggerService;
        $this->userService = $userService;
        $this->rdrApiService = $rdrApiService;
        $this->version = self::CURRENT_VERSION;
        $this->data = new \StdClass();
        $this->loadSchema();
        $this->normalizeData();
    }

    public function loadFromAObject($measurement)
    {
        if (!empty($measurement->getVersion())) {
            $this->version = $measurement->getVersion();
        }
        if (is_object($measurement->getData())) {
            $this->data = $measurement->getData();
        } else {
            $this->data = json_decode($measurement->getData());
        }
        if (!empty($measurement->getFinalizedTs())) {
            $this->locked = true;
        }
        $this->participant = $measurement->getParticipantId();
        $createdUser = $this->em->getRepository(User::class)->findOneBy(['id' => $measurement->getUserId()]);
        if (!$measurement->getFinalizedUserId()) {
            $finalizedUserId = $measurement->getFinalizedTs() ? $measurement->getUserId() : $this->userService->getUser()->getId();
            $finalizedSite = $measurement->getFinalizedTs() ? $measurement->getSite() : $this->session->get('site')->id;
        } else {
            $finalizedUserId = $measurement->getFinalizedUserId();
            $finalizedSite = $measurement->getFinalizedSite();
        }
        $finalizedUser = $this->em->getRepository(User::class)->findOneBy(['id' => $finalizedUserId]);
        $this->createdUser = $createdUser->getEmail();
        $this->createdSite = $measurement->getSite();
        $this->finalizedUser = $finalizedUser->getEmail();
        $this->finalizedSite = $finalizedSite;
        $this->loadSchema();
        $this->normalizeData();
    }

    public function getSchema()
    {
        return $this->schema;
    }

    public function getAssociativeSchema()
    {
        $schema = clone $this->schema;
        $associativeFields = [];
        foreach ($schema->fields as $field) {
            $associativeFields[$field->name] = $field;
        }
        $schema->fields = $associativeFields;
        return $schema;
    }

    public function loadSchema()
    {
        $file = __DIR__ . "/../../../src/Pmi/Evaluation/versions/{$this->version}.json";
        if (!file_exists($file)) {
            throw new MissingSchemaException();
        }
        $this->schema = json_decode(file_get_contents($file));
        if (!is_object($this->schema) || !is_array($this->schema->fields)) {
            throw new InvalidSchemaException();
        }
        foreach ($this->schema->fields as $field) {
            if (!isset($this->data->{$field->name})) {
                $this->data->{$field->name} = null;
            }
        }
    }

    protected function normalizeData()
    {
        foreach ($this->data as $key => $value) {
            if ($value === 0) {
                $this->data->$key = null;
            }
        }
        foreach ($this->schema->fields as $field) {
            if (isset($field->replicates)) {
                $key = $field->name;
                if (is_null($this->data->$key)) {
                    $dataArray = array_fill(0, $field->replicates, null);
                    $this->data->$key = $dataArray;
                } elseif (!is_null($this->data->$key) && !is_array($this->data->$key)) {
                    $dataArray = array_fill(0, $field->replicates, null);
                    $dataArray[0] = $this->data->$key;
                    $this->data->$key = $dataArray;
                }
            }
        }
    }

    public function getFhir($datetime, $parentRdr = null)
    {
        $fhir = new Fhir([
            'data' => $this->data,
            'schema' => $this->getAssociativeSchema(),
            'patient' => $this->participant,
            'version' => $this->version,
            'datetime' => $datetime,
            'parent_rdr' => $parentRdr,
            'created_user' => $this->createdUser,
            'created_site' => $this->createdSite,
            'finalized_user' => $this->finalizedUser,
            'finalized_site' => $this->finalizedSite,
            'summary' => $this->getSummary()
        ]);
        return $fhir->toObject();
    }

    protected static function cmToFtIn($cm)
    {
        $inches = self::cmToIn($cm);
        $feet = floor($inches / 12);
        $inches = round(fmod($inches, 12));
        return "$feet ft $inches in";
    }

    protected static function cmToIn($cm)
    {
        return round($cm * 0.3937, 1);
    }

    protected static function kgToLb($kg)
    {
        return round($kg * 2.2046, 1);
    }

    protected function calculateMean($field)
    {
        $secondThirdFields = [
            'blood-pressure-systolic',
            'blood-pressure-diastolic',
            'heart-rate'
        ];
        $twoClosestFields = [
            'hip-circumference',
            'waist-circumference'
        ];
        if (in_array($field, $secondThirdFields)) {
            $values = [$this->data->{$field}[1], $this->data->{$field}[2]];
        } else {
            $values = $this->data->{$field};
        }
        $values = array_filter($values);
        if (count($values) > 0) {
            if (count($values) === 3 && in_array($field, $twoClosestFields)) {
                sort($values);
                if ($values[1] - $values[0] < $values[2] - $values[1]) {
                    array_pop($values);
                } elseif ($values[2] - $values[1] < $values[1] - $values[0]) {
                    array_shift($values);
                }
            }
            return array_sum($values) / count($values);
        } else {
            return null;
        }
    }

    protected static function calculateBmi($height, $weight)
    {
        if ($height && $weight) {
            return $weight / (($height / 100) * ($height / 100));
        }
        return false;
    }

    public function getSummary()
    {
        $summary = [];
        if ($this->data->height) {
            $summary['height'] = [
                'cm' => $this->data->height,
                'ftin' => self::cmToFtIn($this->data->height)
            ];
        }
        if ($this->data->weight) {
            $summary['weight'] = [
                'kg' => $this->data->weight,
                'lb' => self::kgToLb($this->data->weight)
            ];
        }
        if ($this->data->weight && $this->data->height) {
            $summary['bmi'] = self::calculateBmi($this->data->height, $this->data->weight);
        }
        if ($hip = $this->calculateMean('hip-circumference')) {
            $summary['hip'] = [
                'cm' => $hip,
                'in' => self::cmToIn($hip)
            ];
        }
        if ($waist = $this->calculateMean('waist-circumference')) {
            $summary['waist'] = [
                'cm' => $waist,
                'in' => self::cmToIn($waist)
            ];
        }
        $systolic = $this->calculateMean('blood-pressure-systolic');
        $diastolic = $this->calculateMean('blood-pressure-diastolic');
        if ($systolic && $diastolic) {
            $summary['bloodpressure'] = [
                'systolic' => $systolic,
                'diastolic' => $diastolic
            ];
        }
        if ($heartrate = $this->calculateMean('heart-rate')) {
            $summary['heartrate'] = $heartrate;
        }
        return $summary;
    }

    public function createMeasurement($participantId, $fhir)
    {
        try {
            $response = $this->rdrApiService->post("rdr/v1/Participant/{$participantId}/PhysicalMeasurements", $fhir);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result->id;
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return false;
        }
        return false;
    }
}
