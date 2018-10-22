<?php
namespace Pmi\Evaluation;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Validator\Constraints;
use Pmi\Util;
use Pmi\Audit\Log;

class Evaluation
{
    const CURRENT_VERSION = '0.3.3';
    const LIMIT_TEXT_SHORT = 1000;
    const LIMIT_TEXT_LONG = 10000;
    const EVALUATION_ACTIVE = 'active';
    const EVALUATION_CANCEL = 'cancel';
    const EVALUATION_RESTORE = 'restore';

    protected $app;
    protected $version;
    protected $data;
    protected $schema;
    protected $participant;
    protected $createdUser;
    protected $createdSite;
    protected $finalizedUser;
    protected $finalizedSite;
    protected $locked = false;
    public $evaluation;

    public function __construct($app = null)
    {
        $this->app = $app;
        $this->version = self::CURRENT_VERSION;
        $this->data = new \StdClass();
        $this->loadSchema();
        $this->normalizeData();
    }

    public static $cancelReasons = [
        'Data entered for wrong participant' => 'PM_CANCEL_WRONG_PARTICIPANT',
        'Other' => 'OTHER'
    ];

    public static $restoreReasons = [
        'Physical Measurements cancelled for wrong participant' => 'PM_RESTORE_WRONG_PARTICIPANT',
        'Physical Measurements can be amended versus cancelled' => 'PM_RESTORE_AMEND',
        'Other' => 'OTHER'
    ];

    public function loadFromArray($array)
    {
        $this->evaluation = $array;
        if (array_key_exists('version', $array)) {
            $this->version = $array['version'];
        }
        if (array_key_exists('data', $array)) {
            if (is_object($array['data'])) {
                $this->data = $array['data'];
            } else {
                $this->data = json_decode($array['data']);
            }
        }
        if (!empty($array['finalized_ts'])) {
            $this->locked = true;
        }
        $this->participant = $array['participant_id'];
        if ($this->app) {
            $createdUser = $this->app['em']->getRepository('users')->fetchOneBy([
                'id' => $array['user_id']
            ]);
            if (!$array['finalized_user_id']) {
                $finalizedUserId = $array['finalized_ts'] ? $array['user_id'] : $this->app->getUser()->getId();
                $finalizedSite = $array['finalized_ts'] ? $array['site'] : $this->app->getSiteId();
            } else {
                $finalizedUserId = $array['finalized_user_id'];
                $finalizedSite = $array['finalized_site'];          
            }
            $finalizedUser = $this->app['em']->getRepository('users')->fetchOneBy([
                'id' => $finalizedUserId
            ]);
            $this->createdUser = $createdUser['email'];
            $this->createdSite = $array['site'];
            $this->finalizedUser = $finalizedUser['email'];
            $this->finalizedSite = $finalizedSite;
        }
        else {
            $this->createdUser = array_key_exists('created_user', $array) ? $array['created_user'] : null;
            $this->createdSite = array_key_exists('created_site', $array) ? $array['created_site'] : null;
            $this->finalizedUser = array_key_exists('finalized_user', $array) ? $array['finalized_user'] : null;
            $this->finalizedSite = array_key_exists('finalized_site', $array) ? $array['finalized_site'] : null;
        }
        $this->loadSchema();
        $this->normalizeData();
    }

    public function toArray($serializeData = true)
    {
        return [
            'version' => $this->version,
            'data' => $serializeData ? json_encode($this->data) : $this->data
        ];
    }

    public function getVersion()
    {
        return $this->version;
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

    public function getForm(FormFactory $formFactory)
    {
        $formBuilder = $formFactory->createBuilder(FormType::class, $this->data);
        foreach ($this->schema->fields as $field) {
            if (isset($field->type)) {
                $type = $field->type;
            } else {
                $type = null;
            }
            $constraints = [];
            $attributes = [];
            $options = [
                'required' => false,
                'scale' => 0
            ];
            if ($this->locked) {
                $options['disabled'] = true;
            }
            if (isset($field->label)) {
                $options['label'] = $field->label;
            }
            if (isset($field->decimals)) {
                $options['scale'] = $field->decimals;
            }
            if (isset($field->max)) {
                $constraints[] = new Constraints\LessThan($field->max);
                $attributes['data-parsley-lt'] = $field->max;
            }
            if (isset($field->min)) {
                $constraints[] = new Constraints\GreaterThanEqual($field->min);
                $attributes['data-parsley-gt'] = $field->min;
            } elseif (!isset($field->options) && !in_array($type, ['checkbox', 'text', 'textarea'])) {
                $constraints[] = new Constraints\GreaterThan(0);
                $attributes['data-parsley-gt'] = 0;
            }
            $form = $formBuilder->getForm();
            $bmiConstraint = function($value, $context) use ($form) {
                $bmi = round(self::calculateBmi($form->getData()->height, $form->getData()->weight), 1);
                if ($bmi != false && ($bmi < 5 || $bmi > 125)) {
                    $context->buildViolation('This height/weight combination has yielded an invalid BMI')->addViolation();
                }
            };

            if ($field->name === 'height') {
                $attributes['data-parsley-bmi-height'] = '#form_weight';
                $constraints[] = new Constraints\Callback($bmiConstraint);
            }
            if ($field->name === 'weight') {
                $attributes['data-parsley-bmi-weight'] = '#form_height';
                $constraints[] = new Constraints\Callback($bmiConstraint);
            }

            if (isset($field->options)) {
                $class = ChoiceType::class;
                unset($options['scale']);
                if (is_array($field->options)) {
                    $options['choices'] = array_combine($field->options, $field->options);
                } else {
                    $options['choices'] = (array)$field->options;
                }
                $options['placeholder'] = false;
            } elseif ($type == 'checkbox') {
                unset($options['scale']);
                $class = CheckboxType::class;
            } elseif ($type == 'textarea') {
                unset($options['scale']);
                $class = TextareaType::class;
                $attributes['rows'] = 4;
                $attributes['data-parsley-maxlength'] = self::LIMIT_TEXT_LONG;
                $constraints[] = new Constraints\Length(['max' => self::LIMIT_TEXT_LONG]);
                $constraints[] = new Constraints\Type('string');
            } elseif ($type == 'text') {
                unset($options['scale']);
                $class = TextType::class;
                $attributes['data-parsley-maxlength'] = self::LIMIT_TEXT_SHORT;
                $constraints[] = new Constraints\Length(['max' => self::LIMIT_TEXT_SHORT]);
                $constraints[] = new Constraints\Type('string');
            } else {
                $class = NumberType::class;
                $constraints[] = new Constraints\Type('numeric');
            }

            $options['constraints'] = $constraints;
            $options['attr'] = $attributes;

            if (isset($field->replicates)) {
                $collectionOptions = [
                    'entry_type' => $class,
                    'entry_options' => $options,
                    'required' => false,
                    'label' => isset($options['label']) ? $options['label'] : null
                ];
                if (isset($field->compare)) {
                    $compareType = $field->compare->type;
                    $compareField = $field->compare->field;
                    $compareMessage = $field->compare->message;
                    $callback = function($value, $context, $replicate) use ($form, $compareField, $compareType, $compareMessage) {
                        $compareTo = $form->getData()->$compareField;
                        if (!isset($compareTo[$replicate])) {
                            return;
                        }
                        if ($compareType == 'greater-than' && $value <= $compareTo[$replicate]) {
                            $context->buildViolation($compareMessage)->addViolation();
                        } elseif ($compareType == 'less-than' && $value >= $compareTo[$replicate]) {
                            $context->buildViolation($compareMessage)->addViolation();
                        }
                    };
                    $collectionConstraintFields = [];
                    for ($i = 0; $i < $field->replicates; $i++) {
                        $collectionConstraintFields[] = new Constraints\Callback(['callback' => $callback, 'payload' => $i]);
                    }
                    $compareConstraint = new Constraints\Collection($collectionConstraintFields);
                    $collectionOptions['constraints'] = [$compareConstraint];
                }
                $formBuilder->add($field->name, CollectionType::class, $collectionOptions);
            } else {
                $formBuilder->add($field->name, $class, $options);
            }
        }
        return $formBuilder->getForm();
    }

    public function loadSchema()
    {
        $file = __DIR__ . "/versions/{$this->version}.json";
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

    public function setData($data)
    {
        $this->data = $data;
        $this->normalizeData();
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
                }
                elseif (!is_null($this->data->$key) && !is_array($this->data->$key)) {
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

    protected function isMinVersion($minVersion)
    {
        return Util::versionIsAtLeast($this->version, $minVersion);
    }

    public function getFinalizeErrors()
    {
        $errors = [];

        if (!$this->isMinVersion('0.3.0')) {
            // prior to version 0.3.0, any state is valid
            return $errors;
        }

        foreach (['blood-pressure-systolic', 'blood-pressure-diastolic', 'heart-rate'] as $field) {
            foreach ($this->data->$field as $k => $value) {
                if (!$this->data->{'blood-pressure-protocol-modification'}[$k] && !$value) {
                    $errors[] = [$field, $k];
                }
            }
        }
        foreach (['height', 'weight'] as $field) {
            if (!$this->data->{$field . '-protocol-modification'} && !$this->data->$field) {
                $errors[] = $field;
            }
        }
        if (!$this->data->pregnant && !$this->data->wheelchair) {
            foreach (['hip-circumference', 'waist-circumference'] as $field) {
                foreach ($this->data->$field as $k => $value) {
                    if ($k == 2) {
                        // not an error on the third measurement if first two aren't completed
                        // or first two measurements are within 1 cm
                        if (!$this->data->{$field}[0] || !$this->data->{$field}[1]) {
                            break;
                        }
                        if (abs($this->data->{$field}[0] - $this->data->{$field}[1]) <= 1) {
                            break;
                        }
                    }
                    if (!$this->data->{$field . '-protocol-modification'}[$k] && !$value) {
                        $errors[] = [$field, $k];
                    }
                }
            }
        }

        return $errors;
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

    public function getEvaluationModifyForm($type)
    {
        $evaluationModifyForm = $this->app['form.factory']->createBuilder(FormType::class, null);
        $reasonType = $type . 'Reasons';
        $reasons = self::$$reasonType;
        $evaluationModifyForm->add('reason', ChoiceType::class, [
            'label' => 'Reason',
            'required' => true,
            'choices' => $reasons,
            'placeholder' => '-- Select ' . ucfirst($type) . ' Reason --',
            'multiple' => false,
            'constraints' => new Constraints\NotBlank([
                'message' => "Please select {$type} reason"
            ])
        ]);
        $evaluationModifyForm->add('other_text', TextareaType::class, [
            'label' => false,
            'required' => false,
            'constraints' => [
                new Constraints\Type('string')
            ]
        ]);
        if ($type == self::EVALUATION_CANCEL) {
            $evaluationModifyForm->add('confirm', TextType::class, [
                'label' => 'Confirm',
                'required' => true,
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'placeholder' => 'Type the word "CANCEL" to confirm',
                    'autocomplete' => 'off'
                ]
            ]);
        }
        return $evaluationModifyForm->getForm();
    }

    public function cancelRestoreRdrEvaluation($type, $reason)
    {
        $evaluation = $this->getCancelRestoreRdrObject($type, $reason);
        return $this->app['pmi.drc.participants']->cancelRestoreEvaluation($type, $this->evaluation['participant_id'], $this->evaluation['rdr_id'], $evaluation);
    }

    public function getCancelRestoreRdrObject($type, $reason)
    {
        $obj = new \StdClass();
        $statusType = $type === self::EVALUATION_CANCEL ? 'cancelled' : 'restored';
        $obj->status = $statusType;
        $obj->reason = $reason;
        $user = $this->app->getUserEmail();
        $site = $this->app->getSiteIdWithPrefix();
        $obj->{$statusType . 'Info'} = $this->getEvaluationUserSiteData($user, $site);
        return $obj;
    }

    protected function getEvaluationUserSiteData($user, $site)
    {
        return [
            'author' => [
                'system' => 'https://www.pmi-ops.org/healthpro-username',
                'value' => $user
            ],
            'site' => [
                'system' => 'https://www.pmi-ops.org/site-id',
                'value' => $site
            ]
        ];
    }

    public function getEvaluationWithHistory($evalId, $participantId)
    {
        $evaluationsQuery = "
            SELECT e.*,
                   eh.evaluation_id AS eh_evaluation_id,
                   eh.user_id AS eh_user_id,
                   eh.site AS eh_site,
                   eh.type AS eh_type,
                   eh.reason AS eh_reason,
                   eh.created_ts AS eh_created_ts
            FROM evaluations e
            LEFT JOIN evaluations_history eh ON e.history_id = eh.id
            WHERE e.id NOT IN (SELECT parent_id FROM evaluations WHERE parent_id IS NOT NULL)
              AND e.id = :evalId
              AND e.participant_id = :participant_id
            ORDER BY e.id DESC
        ";
        $evaluation = $this->app['em']->fetchAll($evaluationsQuery, [
            'evalId' => $evalId,
            'participant_id' => $participantId
        ]);
        return !empty($evaluation) ? $evaluation[0] : null;
    }

    public function getEvaluationsWithHistory($participantId)
    {
        $evaluationsQuery = "
            SELECT e.*,
                   eh.evaluation_id AS eh_evaluation_id,
                   eh.user_id AS eh_user_id,
                   eh.site AS eh_site,
                   eh.type AS eh_type,
                   eh.reason AS eh_reason,
                   eh.created_ts AS eh_created_ts
            FROM evaluations e
            LEFT JOIN evaluations_history eh ON e.history_id = eh.id
            WHERE e.id NOT IN (SELECT parent_id FROM evaluations WHERE parent_id IS NOT NULL)
              AND e.participant_id = :participant_id
            ORDER BY e.id DESC
        ";
        return $this->app['db']->fetchAll($evaluationsQuery, [
            'participant_id' => $participantId
        ]);
    }

    public function getSiteUnfinalizedEvaluations()
    {
        $evaluationsQuery = "
            SELECT e.*,
                   eh.evaluation_id AS eh_evaluation_id,
                   eh.user_id AS eh_user_id,
                   eh.site AS eh_site,
                   eh.type AS eh_type,
                   eh.reason AS eh_reason,
                   eh.created_ts AS eh_created_ts
            FROM evaluations e
            LEFT JOIN evaluations_history eh ON e.history_id = eh.id
            WHERE e.site = :site
              AND e.finalized_ts IS NULL
              AND (eh.type != :type
              OR eh.type IS NULL)
            ORDER BY e.created_ts DESC
        ";
        return $this->app['db']->fetchAll($evaluationsQuery, [
            'site' => $this->app->getSiteId(),
            'type' => self::EVALUATION_CANCEL
        ]);
    }

    public function getSiteRecentModifiedEvaluations()
    {
        $evaluationsQuery = "
            SELECT e.*,
                   eh.evaluation_id AS eh_order_id,
                   eh.user_id AS eh_user_id,
                   eh.site AS eh_site,
                   eh.type AS eh_type,
                   eh.created_ts AS eh_created_ts,
                   IFNULL (eh.created_ts, e.updated_ts) as modified_ts
            FROM evaluations e
            LEFT JOIN evaluations_history eh ON e.history_id = eh.id
            WHERE e.site = :site
              AND (eh.type = :type
              OR (eh.type IS NULL
              AND e.parent_id IS NOT NULL))
              AND e.id NOT IN (SELECT parent_id FROM evaluations WHERE parent_id IS NOT NULL)
              AND (eh.created_ts >= UTC_TIMESTAMP() - INTERVAL 7 DAY OR e.updated_ts >= UTC_TIMESTAMP() - INTERVAL 7 DAY)
            ORDER BY modified_ts DESC
        ";
        return $this->app['db']->fetchAll($evaluationsQuery, [
            'site' => $this->app->getSiteId(),
            'type' => self::EVALUATION_CANCEL
        ]);
    }

    public function createEvaluationHistory($type, $evalId, $reason = '')
    {
        $evaluationHistoryData = [
            'reason' => $reason,
            'evaluation_id' => $evalId,
            'user_id' => $this->app->getUser()->getId(),
            'site' => $this->app->getSiteId(),
            'type' => $type,
            'created_ts' => new \DateTime()
        ];
        $evaluationsHistoryRepository = $this->app['em']->getRepository('evaluations_history');
        $status = false;
        $evaluationsHistoryRepository->wrapInTransaction(function () use ($evaluationsHistoryRepository, $evaluationHistoryData, &$status) {
            $id = $evaluationsHistoryRepository->insert($evaluationHistoryData);
            $this->app->log(Log::EVALUATION_HISTORY_CREATE, [
                'id' => $id,
                'type' => $evaluationHistoryData['type']
            ]);
            //Update history id in evaluations table
            $this->app['em']->getRepository('evaluations')->update(
                $evaluationHistoryData['evaluation_id'],
                ['history_id' => $id]
            );
            $status = true;
        });
        return $status;
    }

    public function getEvaluationRevertForm()
    {
        $evaluationRevertForm = $this->app['form.factory']->createBuilder(FormType::class, null);
        $evaluationRevertForm->add('revert', SubmitType::class, [
            'label' => 'Revert',
            'attr' => [
                'class' => 'btn-warning'
            ]
        ]);
        return $evaluationRevertForm->getForm();
    }

    public function revertEvaluation($evalId)
    {
        if ($this->app['em']->getRepository('evaluations')->delete($evalId)) {
            $this->app->log(Log::EVALUATION_DELETE, $evalId);
            return true;
        }
        return false;
    }

    public function isEvaluationCancelled()
    {
        return $this->evaluation['eh_type'] === self::EVALUATION_CANCEL;
    }

    public function isEvaluationUnlocked()
    {
        return !empty($this->evaluation['parent_id']) && empty($this->evaluation['finalized_ts']);
    }

    public function isEvaluationFailedToReachRDR()
    {
        return !empty($this->evaluation['finalized_ts']) && empty($this->evaluation['rdr_id']);
    }

    public function canCancel()
    {
        return $this->evaluation['eh_type'] !== self::EVALUATION_CANCEL
            && !$this->isEvaluationUnlocked()
            && !$this->isEvaluationFailedToReachRDR();
    }

    public function canRestore()
    {
        return $this->evaluation['eh_type'] === self::EVALUATION_CANCEL
            && !$this->isEvaluationUnlocked()
            && !$this->isEvaluationFailedToReachRDR();
    }


    public function sendToRdr()
    {
        // Check if parent_id exists
        if ($this->evaluation['parent_id']) {
            $parentEvaluation = $this->app['em']->getRepository('evaluations')->fetchOneBy([
                'id' => $this->evaluation['parent_id']
            ]);
            if (!empty($parentEvaluation)) {
                $parentRdrId = $parentEvaluation['rdr_id'];
            }
        }
        $fhir = $this->getFhir($this->evaluation['finalized_ts'], $parentRdrId);
        $rdrId = $this->app['pmi.drc.participants']->createEvaluation($this->evaluation['participant_id'], $fhir);
        if (!empty($rdrId)) {
            $this->app['em']->getRepository('evaluations')->update(
                $this->evaluation['id'],
                ['rdr_id' => $rdrId]
            );
            return true;
        }
        return false;
    }
}
