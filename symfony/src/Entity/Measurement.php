<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Model\Measurement\Fhir;
use App\Exception\InvalidSchemaException;
use App\Exception\MissingSchemaException;
use App\Helper\Util;

/**
 * @ORM\Table(name="evaluations", indexes={
 *   @ORM\Index(
 *     columns={"participant_id"},
 *     name="participant_id")
 *   })
 * @ORM\Entity(repositoryClass="App\Repository\MeasurementRepository")
 */
class Measurement
{

    const CURRENT_VERSION = '0.3.3';
    const BLOOD_DONOR_CURRENT_VERSION = '0.3.3-blood-donor';
    const EHR_CURRENT_VERSION = '0.3.3-ehr';
    const LIMIT_TEXT_SHORT = 1000;
    const LIMIT_TEXT_LONG = 10000;
    const EVALUATION_ACTIVE = 'active';
    const EVALUATION_CANCEL = 'cancel';
    const EVALUATION_RESTORE = 'restore';
    const BLOOD_DONOR = 'blood-donor';
    const BLOOD_DONOR_PROTOCOL_MODIFICATION = 'blood-bank-donor';
    const BLOOD_DONOR_PROTOCOL_MODIFICATION_LABEL = 'Blood bank donor';
    const EHR_PROTOCOL_MODIFICATION = 'ehr';
    const EHR_PROTOCOL_MODIFICATION_LABEL = 'Observation obtained from EHR';
    const EVALUATION_CANCEL_STATUS = 'entered-in-error';
    const EVALUATION_RESTORE_STATUS = 'final';

    private $currentVersion;

    private $fieldData;

    private $schema;

    protected $finalizedUserEmail;

    protected $finalizedSiteInfo;

    public static $cancelReasons = [
        'Data entered for wrong participant' => 'PM_CANCEL_WRONG_PARTICIPANT',
        'Other' => 'OTHER'
    ];

    public static $restoreReasons = [
        'Physical Measurements cancelled for wrong participant' => 'PM_RESTORE_WRONG_PARTICIPANT',
        'Physical Measurements can be amended instead of cancelled' => 'PM_RESTORE_AMEND',
        'Other' => 'OTHER'
    ];

    public static $bloodPressureFields = [
        'blood-pressure-systolic',
        'blood-pressure-diastolic',
        'heart-rate'
    ];

    public static $protocolModificationNotesFields = [
        'blood-pressure-protocol-modification-notes',
        'height-protocol-modification-notes',
        'weight-protocol-modification-notes',
        'hip-circumference-protocol-modification-notes',
        'waist-circumference-protocol-modification-notes'
    ];

    public static $measurementSourceFields = [
        'blood-pressure-source',
        'height-source',
        'weight-source',
        'waist-circumference-source',
        'hip-circumference-source'
    ];

    public static $ehrProtocolDateFields = [
        'blood-pressure-source-ehr-date',
        'height-source-ehr-date',
        'weight-source-ehr-date',
        'waist-circumference-source-ehr-date',
        'hip-circumference-source-ehr-date'
    ];

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\User")
     */
    private $user;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $site;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $participantId;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $rdrId;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $parentId;

    /**
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    private $createdTs;

    /**
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    private $updatedTs;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\User")
     */
    private $finalizedUser;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $finalizedSite;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $finalizedTs;

    /**
     * @ORM\Column(type="string", nullable=false, length=10)
     */
    private $version;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $fhirVersion;

    /**
     * @ORM\Column(type="text")
     */
    private $data;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\MeasurementHistory", cascade={"persist", "remove"})
     */
    private $history;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getSite(): ?string
    {
        return $this->site;
    }

    public function setSite(string $site): self
    {
        $this->site = $site;

        return $this;
    }

    public function getParticipantId(): ?string
    {
        return $this->participantId;
    }

    public function setParticipantId(string $participantId): self
    {
        $this->participantId = $participantId;

        return $this;
    }

    public function getRdrId(): ?string
    {
        return $this->rdrId;
    }

    public function setRdrId(?string $rdrId): self
    {
        $this->rdrId = $rdrId;

        return $this;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function setParentId(?int $parentId): self
    {
        $this->parentId = $parentId;

        return $this;
    }

    public function getCreatedTs(): ?\DateTimeInterface
    {
        return $this->createdTs;
    }

    public function setCreatedTs(\DateTimeInterface $createdTs): self
    {
        $this->createdTs = $createdTs;

        return $this;
    }

    public function getUpdatedTs(): ?\DateTimeInterface
    {
        return $this->updatedTs;
    }

    public function setUpdatedTs(\DateTimeInterface $updatedTs): self
    {
        $this->updatedTs = $updatedTs;

        return $this;
    }

    public function getFinalizedUser(): ?User
    {
        return $this->finalizedUser;
    }

    public function setFinalizedUser(?User $finalizedUser): self
    {
        $this->finalizedUser = $finalizedUser;

        return $this;
    }

    public function getFinalizedSite(): ?string
    {
        return $this->finalizedSite;
    }

    public function setFinalizedSite(?string $finalizedSite): self
    {
        $this->finalizedSite = $finalizedSite;

        return $this;
    }

    public function getFinalizedTs(): ?\DateTimeInterface
    {
        return $this->finalizedTs;
    }

    public function setFinalizedTs(?\DateTimeInterface $finalizedTs): self
    {
        $this->finalizedTs = $finalizedTs;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getFhirVersion(): ?int
    {
        return $this->fhirVersion;
    }

    public function setFhirVersion(?int $fhirVersion): self
    {
        $this->fhirVersion = $fhirVersion;

        return $this;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setData(string $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getHistory(): ?MeasurementHistory
    {
        return $this->history;
    }

    public function setHistory(?MeasurementHistory $history): self
    {
        $this->history = $history;

        return $this;
    }

    public function setCurrentVersion(string $currentVersion): self
    {
        $this->currentVersion = $currentVersion;

        return $this;
    }

    public function loadFromAObject($finalizedUserEmail = null, $finalizedSite = null)
    {
        if (empty($this->currentVersion) && empty($this->version)) {
            $this->currentVersion = self::CURRENT_VERSION;
        }
        $data = empty($this->getData()) ? new \StdClass() : $this->getData();
        if (is_object($data)) {
            $this->fieldData = $data;
        } else {
            $this->fieldData = json_decode($data);
        }
        $this->formatEhrProtocolDateFields();
        $this->finalizedUserEmail = $finalizedUserEmail;
        $this->finalizedSiteInfo = $finalizedSite;
        $this->loadSchema();
        $this->normalizeData();
    }

    public function formatEhrProtocolDateFields()
    {
        foreach (self::$ehrProtocolDateFields as $ehrProtocolDateField) {
            if (!empty($this->fieldData->{$ehrProtocolDateField})) {
                $this->fieldData->{$ehrProtocolDateField}  = new \DateTime($this->fieldData->{$ehrProtocolDateField});
            }
        }
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
        $file = __DIR__ . "/../Model/Measurement/versions/{$this->getFormVersion()}.json";
        if (!file_exists($file)) {
            throw new MissingSchemaException();
        }
        $this->schema = json_decode(file_get_contents($file));
        if (!is_object($this->schema) || !is_array($this->schema->fields)) {
            throw new InvalidSchemaException();
        }
        foreach ($this->schema->fields as $field) {
            if (!isset($this->fieldData->{$field->name})) {
                $this->fieldData->{$field->name} = null;
            }
        }
    }

    protected function normalizeData($type = null)
    {
        foreach ($this->fieldData as $key => $value) {
            if ($value === 0) {
                $this->fieldData->$key = null;
            }
            if ($type === 'save' && !is_null($this->fieldData->$key) && in_array($key, self::$ehrProtocolDateFields)) {
                $this->fieldData->$key = $this->fieldData->$key->format('Y-m-d');
            }
        }
        foreach ($this->schema->fields as $field) {
            if (isset($field->replicates)) {
                $key = $field->name;
                if (!isset($this->fieldData->$key) || is_null($this->fieldData->$key)) {
                    $dataArray = array_fill(0, $field->replicates, null);
                    $this->fieldData->$key = $dataArray;
                } elseif (!is_null($this->fieldData->$key) && !is_array($this->fieldData->$key)) {
                    $dataArray = array_fill(0, $field->replicates, null);
                    $dataArray[0] = $this->fieldData->$key;
                    $this->fieldData->$key = $dataArray;
                }
            } else {
                $key = $field->name;
                if (!isset($this->fieldData->$key)) {
                    $this->fieldData->$key = null;
                }
            }
        }
        if ($this->isEhrProtocolForm()) {
            $this->addEhrProtocolModifications();
        }
    }

    public function getFhir($datetime, $parentRdr = null)
    {
        $fhir = new Fhir([
            'data' => $this->fieldData,
            'schema' => $this->getAssociativeSchema(),
            'patient' => $this->getParticipantId(),
            'version' => $this->getFormVersion(),
            'datetime' => $datetime,
            'parent_rdr' => $parentRdr,
            'created_user' => $this->getUser()->getEmail(),
            'created_site' => $this->getSite(),
            'finalized_user' => $this->finalizedUserEmail,
            'finalized_site' => $this->finalizedSiteInfo,
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
            $values = [$this->fieldData->{$field}[1], $this->fieldData->{$field}[2]];
        } else {
            $values = $this->fieldData->{$field};
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
        if ($this->fieldData->height) {
            $summary['height'] = [
                'cm' => $this->fieldData->height,
                'ftin' => self::cmToFtIn($this->fieldData->height)
            ];
        }
        if ($this->fieldData->weight) {
            $summary['weight'] = [
                'kg' => $this->fieldData->weight,
                'lb' => self::kgToLb($this->fieldData->weight)
            ];
        }
        if ($this->fieldData->weight && $this->fieldData->height) {
            $summary['bmi'] = self::calculateBmi($this->fieldData->height, $this->fieldData->weight);
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

    public function canCancel()
    {
        return $this->getHistoryType() !== self::EVALUATION_CANCEL
            && !$this->isEvaluationUnlocked()
            && !$this->isEvaluationFailedToReachRDR();
    }

    public function canRestore()
    {
        return $this->getHistoryType() === self::EVALUATION_CANCEL
            && !$this->isEvaluationUnlocked()
            && !$this->isEvaluationFailedToReachRDR();
    }

    public function getHistoryType()
    {
        if (!empty($this->getHistory())) {
            return $this->getHistory()->getType();
        }
        return null;
    }

    public function isEvaluationCancelled()
    {
        return $this->getHistoryType() === self::EVALUATION_CANCEL ? true : false;
    }

    public function isEvaluationUnlocked()
    {
        return !empty($this->getParentId()) && empty($this->getFinalizedTs());
    }

    public function isEvaluationFailedToReachRDR()
    {
        return !empty($this->getFinalizedTs()) && empty($this->getRdrId());
    }

    public function getReasonDisplayText()
    {
        if (empty($this->getHistory())) {
            return null;
        }
        // Check only cancel reasons
        $reasonDisplayText = array_search($this->getHistory()->getReason(), self::$cancelReasons);
        return !empty($reasonDisplayText) ? $reasonDisplayText : 'Other';
    }

    public function setFieldData($fieldData) {
        $this->fieldData = $fieldData;
        $this->normalizeData('save');
    }

    public function getFieldData() {
        return $this->fieldData;
    }

    public function isBloodDonorForm()
    {
        return strpos($this->getFormVersion(), self::BLOOD_DONOR) !== false;
    }

    public function isEhrProtocolForm()
    {
        return strpos($this->getFormVersion(), self::EHR_PROTOCOL_MODIFICATION) !== false;
    }

    public function getWarnings()
    {
        $warnings = [];
        foreach ($this->schema->fields as $metric) {
            if (!empty($metric->warnings) && is_array($metric->warnings)) {
                $warnings[$metric->name] = $metric->warnings;
            }
        }
        return $warnings;
    }

    public function getConversions()
    {
        $conversions = [];
        foreach ($this->schema->fields as $metric) {
            if (!empty($metric->convert)) {
                $conversions[$metric->name] = $metric->convert;
            }
        }
        return $conversions;
    }

    public function getLatestFormVersion()
    {
        if ($this->isBloodDonorForm()) {
            return self::BLOOD_DONOR_CURRENT_VERSION;
        }
        if ($this->isEhrProtocolForm()) {
            return self::EHR_CURRENT_VERSION;
        }
        return self::CURRENT_VERSION;
    }

    public function addBloodDonorProtocolModificationForRemovedFields()
    {
        $this->addBloodDonorProtocolModificationForWaistandHip();
        $this->addBloodDonorProtocolModificationForBloodPressure();
        $this->addBloodDonorProtocolModificationForHeight();
    }

    public function addEhrProtocolModifications()
    {
        if ($this->fieldData->{'blood-pressure-source'} === 'ehr') {
            $this->fieldData->{'blood-pressure-protocol-modification'}[0] = self::EHR_PROTOCOL_MODIFICATION;
            for ($reading = 1; $reading <= 2; $reading++) {
                foreach (self::$bloodPressureFields as $field) {
                    $this->fieldData->{$field}[$reading] = null;
                }
                foreach (['irregular-heart-rate', 'manual-blood-pressure', 'manual-heart-rate'] as $field) {
                    $this->fieldData->{$field}[$reading] = false;
                }
                $this->fieldData->{'blood-pressure-protocol-modification'}[$reading] = self::EHR_PROTOCOL_MODIFICATION;
            }
        }
        if ($this->fieldData->{'height-source'} === 'ehr') {
            $this->fieldData->{"height-protocol-modification"} = self::EHR_PROTOCOL_MODIFICATION;
        }
        if ($this->fieldData->{'weight-source'} === 'ehr') {
            $this->fieldData->{"weight-protocol-modification"} = self::EHR_PROTOCOL_MODIFICATION;
        }
        if ($this->fieldData->{'waist-circumference-source'} === 'ehr') {
            $this->fieldData->{'waist-circumference-protocol-modification'} = array_fill(0, 3, self::EHR_PROTOCOL_MODIFICATION);
        }
        if ($this->fieldData->{'hip-circumference-source'} === 'ehr') {
            $this->fieldData->{'hip-circumference-protocol-modification'} = array_fill(0, 3, self::EHR_PROTOCOL_MODIFICATION);
        }
    }

    public function addBloodDonorProtocolModificationForWaistandHip()
    {
        foreach (['waist-circumference-protocol-modification', 'hip-circumference-protocol-modification'] as $field) {
            $this->fieldData->{$field} = array_fill(0, 2, self::BLOOD_DONOR_PROTOCOL_MODIFICATION);
        }
    }

    public function addBloodDonorProtocolModificationForBloodPressure()
    {
        for ($reading = 1; $reading <= 2; $reading++) {
            foreach (self::$bloodPressureFields as $field) {
                $this->fieldData->{$field}[$reading] = null;
            }
            foreach (['irregular-heart-rate', 'manual-blood-pressure', 'manual-heart-rate'] as $field) {
                $this->fieldData->{$field}[$reading] = false;
            }
            $this->fieldData->{'blood-pressure-protocol-modification'}[$reading] = self::BLOOD_DONOR_PROTOCOL_MODIFICATION;
        }
    }

    public function addBloodDonorProtocolModificationForHeight()
    {
        $this->fieldData->{"height-protocol-modification"} = self::BLOOD_DONOR_PROTOCOL_MODIFICATION;
    }

    public function getFinalizeErrors()
    {
        $errors = [];

        if (!$this->isMinVersion('0.3.0')) {
            // prior to version 0.3.0, any state is valid
            return $errors;
        }

        // EHR protocol form
        if ($this->isEhrProtocolForm()) {
            foreach (self::$measurementSourceFields as $sourceField) {
                if ($this->fieldData->{$sourceField} === self::EHR_PROTOCOL_MODIFICATION && empty($this->fieldData->{$sourceField . '-ehr-date'})) {
                    $errors[] = $sourceField . '-ehr-date';
                }
            }
        }

        foreach (self::$bloodPressureFields as $field) {
            foreach ($this->fieldData->$field as $k => $value) {
                $displayError = false;
                // For EHR protocol form display error if first reading is empty and has ehr protocol modification
                if ($this->isEhrProtocolForm()) {
                    $displayError = $k === 0 && $this->fieldData->{'blood-pressure-protocol-modification'}[$k] === self::EHR_PROTOCOL_MODIFICATION;
                }

                if ((!$this->fieldData->{'blood-pressure-protocol-modification'}[$k] || $displayError) && !$value) {
                    $errors[] = [$field, $k];
                }
            }
        }
        foreach ($this->fieldData->{'blood-pressure-protocol-modification'} as $k => $value) {
            if ($value === 'other' && empty($this->fieldData->{'blood-pressure-protocol-modification-notes'}[$k])) {
                $errors[] = ['blood-pressure-protocol-modification-notes', $k];
            }
        }
        foreach (['height', 'weight'] as $field) {
            $displayError = false;
            // For EHR protocol form display error if first reading is empty and has ehr protocol modification
            if ($this->isEhrProtocolForm()) {
                $displayError = $this->fieldData->{$field . '-protocol-modification'} === self::EHR_PROTOCOL_MODIFICATION;
            }
            if ((!$this->fieldData->{$field . '-protocol-modification'} || $displayError) && !$this->fieldData->$field) {
                $errors[] = $field;
            }
            if ($this->fieldData->{$field . '-protocol-modification'} === 'other' && empty($this->fieldData->{$field . '-protocol-modification-notes'})) {
                $errors[] = $field . '-protocol-modification-notes';
            }
        }
        if (!$this->fieldData->pregnant && !$this->fieldData->wheelchair) {
            foreach (['hip-circumference', 'waist-circumference'] as $field) {
                foreach ($this->fieldData->$field as $k => $value) {
                    if ($k == 2) {
                        // not an error on the third measurement if first two aren't completed
                        // or first two measurements are within 1 cm
                        if (!$this->fieldData->{$field}[0] || !$this->fieldData->{$field}[1]) {
                            break;
                        }
                        if (abs($this->fieldData->{$field}[0] - $this->fieldData->{$field}[1]) <= 1) {
                            break;
                        }
                    }
                    $displayError = false;
                    // For EHR protocol form display error if first reading is empty and has ehr protocol modification
                    if ($this->isEhrProtocolForm()) {
                        $displayError = $k === 0 && $this->fieldData->{$field . '-protocol-modification'}[$k] === self::EHR_PROTOCOL_MODIFICATION;
                    }
                    if ((!$this->fieldData->{$field . '-protocol-modification'}[$k] || $displayError) && !$value) {
                        $errors[] = [$field, $k];
                    }
                    if ($this->fieldData->{$field . '-protocol-modification'}[$k] === 'other' && empty($this->fieldData->{$field . '-protocol-modification-notes'}[$k])) {
                        $errors[] = [$field . '-protocol-modification-notes', $k];
                    }
                }
            }
        }

        return $errors;
    }

    public function getFormFieldErrorMessage($field = null, $replicate = null)
    {
        if (($this->isBloodDonorForm() && in_array($field, self::$bloodPressureFields) && $replicate === 1) ||
            ($this->isEhrProtocolForm() && in_array($field, array_merge(self::$ehrProtocolDateFields, self::$measurementSourceFields))) ||
            (in_array($field, self::$protocolModificationNotesFields))
        ) {
            return 'Please complete';
        }
        return 'Please complete or add protocol modification.';
    }

    public function canAutoModify()
    {
        if ($this->isBloodDonorForm()) {
            return false;
        }
        if ($this->isEhrProtocolForm()) {
            foreach (self::$measurementSourceFields as $field) {
                if ($this->fieldData->{$field} === 'ehr') {
                    return false;
                }
            }
        }
        return true;
    }

    protected function isMinVersion($minVersion)
    {
        return Util::versionIsAtLeast($this->version, $minVersion);
    }

    public function getFormVersion()
    {
        return empty($this->version) ? $this->currentVersion : $this->version;
    }
}
