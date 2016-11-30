<?php
namespace Pmi\Order;

use Silex\Application;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Validator\Constraints;

class Order
{
    protected $app;
    protected $order;
    protected $participant;

    public static $samples = [
        '(1) Whole Blood EDTA 4 mL [1ED04]' => '1ED04',
        '(2) Whole Blood EDTA 10 mL [1ED10]' => '1ED10',
        '(3) Serum SST 8.5 mL [1SST8]' => '1SST8',
        '(4) Plasma PST 8 mL [1PST8]' => '1PST8',
        '(5) Whole Blood EDTA 10 mL [2ED10]' => '2ED10',
        '(6) WB Sodium Heparin 4 mL [1HEP4]' => '1HEP4',
        '(7) Urine 10 mL [1UR10]' => '1UR10'
    ];
    public static $salivaSamples = [
        'Saliva [1SAL]' => '1SAL'
    ];
    public static $samplesRequiringProcessing = ['1SST8', '1PST8', '1SAL'];

    public function loadOrder($participantId, $orderId, Application $app)
    {
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            return;
        }
        $order = $app['em']->getRepository('orders')->fetchOneBy([
            'id' => $orderId,
            'participant_id' => $participantId
        ]);
        if (!$order) {
            return;
        }
        $this->app = $app;
        $this->order = $order;
        $this->participant = $participant;
    }

    public function isValid()
    {
        return $this->order && $this->participant;
    }

    public function getParticipant()
    {
        return $this->participant;
    }

    public function setParticipant($participant)
    {
        $this->participant = $participant;
    }

    public function get($key)
    {
        if (isset($this->order[$key])) {
            return $this->order[$key];
        } else {
            return null;
        }
    }

    public function toArray()
    {
        return $this->order;
    }

    public function setOrder($order)
    {
        $this->order = $order;
    }

    public function getCurrentStep()
    {
        $columns = [
            'print' => 'printed',
            'collect' => 'collected',
            'process' => 'processed',
            'finalize' => 'finalized'
        ];
        if ($this->order['type'] === 'kit') {
            unset($columns['print']);
        }
        $step = 'finalize';
        foreach ($columns as $name => $column) {
            if (!$this->order["{$column}_ts"]) {
                $step = $name;
                break;
            }
        }
        return $step;
    }

    public function getOrderUpdateFromForm($set, $form)
    {
        $updateArray = [];
        $formData = $form->getData();
        if ($formData["{$set}_notes"]) {
            $updateArray["{$set}_notes"] = $formData["{$set}_notes"];
        } else {
            $updateArray["{$set}_notes"] = null;
        }
        if ($set != 'processed') {
            if ($formData["{$set}_ts"]) {
                // TODO: system setting for db timezone
                $formData["{$set}_ts"]->setTimezone(new \DateTimeZone($this->app->getUser()->getInfo()['timezone']));
                $updateArray["{$set}_ts"] = $formData["{$set}_ts"]->format('Y-m-d H:i:s');
            } else {
                $updateArray["{$set}_ts"] = null;
            }
        }
        $hasSampleArray = $formData["{$set}_samples"] && is_array($formData["{$set}_samples"]);
        if ($hasSampleArray) {
            $updateArray["{$set}_samples"] = json_encode(array_values($formData["{$set}_samples"]));
        } else {
            $updateArray["{$set}_samples"] = json_encode([]);
        }
        if ($set == 'processed') {
            $hasSampleTimeArray = $formData['processed_samples_ts'] && is_array($formData['processed_samples_ts']);
            if ($hasSampleArray && $hasSampleTimeArray) {
                $processedSampleTimes = [];
                foreach ($formData['processed_samples_ts'] as $sample => $dateTime) {
                    if ($dateTime && in_array($sample, $formData["{$set}_samples"])) {
                        $processedSampleTimes[$sample] = $dateTime->getTimestamp();
                    }
                }
                $updateArray['processed_samples_ts'] = json_encode($processedSampleTimes);
            } else {
                $updateArray['processed_samples_ts'] = json_encode([]);
            }
        }
        return $updateArray;
    }

    public function createOrderForm($set, $formFactory)
    {
        switch ($set) {
            case 'collected':
                $verb = 'collected';
                $noun = 'collection';
                break;
            case 'processed':
                $verb = 'processed';
                $noun = 'processing';
                break;
            case 'finalized':
                $verb = 'finalized';
                $noun = 'finalization';
                break;
            default:
                $verb = $set;
                $adjective = $verb;
                $noun = "$adjective samples";
        }
        $tsLabel = ucfirst($verb) . ' time';
        $samplesLabel = "Which samples were successfully {$verb}?";
        $notesLabel = "Additional notes on {$noun}";
        if ($set == 'finalized') {
            $samplesLabel = "Which samples are being shipped to the PMI Biobank?";
        }
        if ($set == 'processed') {
            $tsLabel = 'Time of blood processing completion';
        }

        $formData = $this->getOrderFormData($set);
        if ($set == 'processed') {
            $samples = array_intersect($this->getRequestedSamples(), self::$samplesRequiringProcessing);
        } else {
            $samples = $this->getRequestedSamples();
        }
        $enabledSamples = $this->getEnabledSamples($set);
        $formBuilder = $formFactory->createBuilder(FormType::class, $formData);
        if ($set != 'processed') {
            $formBuilder->add("{$set}_ts", Type\DateTimeType::class, [
                'label' => $tsLabel,
                'widget' => 'single_text',
                'format' => 'M/d/yyyy h:mm a',
                'required' => false,
                'constraints' => [
                    new Constraints\LessThanOrEqual([
                        'value' => new \DateTime('+1 hour'),
                        'message' => 'Timestamp cannot be in the future'
                    ])
                ]
            ]);
        }
        $formBuilder->add("{$set}_samples", Type\ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => $samplesLabel,
                'choices' => $samples,
                'required' => false,
                'choice_attr' => function($val, $key, $index) use ($enabledSamples) {
                    if (in_array($val, $enabledSamples)) {
                        return [];
                    } else {
                        return ['disabled' => true, 'class' => 'sample-disabled'];
                    }
                }
            ])
            ->add("{$set}_notes", Type\TextareaType::class, [
                'label' => $notesLabel,
                'required' => false
            ]);
        if ($set == 'processed') {
            $formBuilder->add('processed_samples_ts', Type\CollectionType::class, [
                'entry_type' => Type\DateTimeType::class,
                'label' => false,
                'entry_options' => [
                    'date_widget' => 'single_text',
                    'time_widget' => 'single_text',
                    'widget' => 'single_text',
                    'format' => 'M/d/yyyy h:mm a',
                    'label' => false,
                    'constraints' => [
                        new Constraints\LessThanOrEqual([
                            'value' => new \DateTime('+1 hour'),
                            'message' => 'Timestamp cannot be in the future'
                        ])
                    ]
                ],
                'required' => false
            ]);
        }
        $form = $formBuilder->getForm();
        return $form;
    }

    public function getRdrObject()
    {
        $created = new \DateTime($this->order['created_ts']);
        $created->setTimezone(new \DateTimeZone('UTC'));

        $obj = new \StdClass();
        $obj->subject = 'Patient/' . $this->order['participant_id'];
        $identifiers = [];
        $identifiers[] = [
            'system' => 'https://www.pmi-ops.org',
            'value' => $this->order['order_id']
        ];
        if ($this->app && !$this->app->getConfig('ml_mock_order') && $this->order['mayo_id'] != 'pmitest') {
            $identifiers[] =[
            'system' => 'https://orders.mayomedicallaboratories.com',
                'value' => $this->order['mayo_id']
            ];
        }
        $obj->identifier = $identifiers;
        $obj->created = $created->format('Y-m-d\TH:i:s\Z');

        $obj->samples = $this->getRdrSamples();

        $notes = [];
        foreach (['collected', 'processed', 'finalized'] as $step) {
            if ($this->order[$step . '_notes']) {
                $notes[$step] = $this->order[$step . '_notes'];
            }
        }
        if (!empty($notes)) {
            $obj->notes = $notes;
        }
        return $obj;
    }

    public function sendToRdr()
    {
        if (!$this->order['finalized_ts']) {
            return false;;
        }
        $order = $this->getRdrObject();
        if ($this->order['rdr_id']) {
            // TODO: update endpoint for participants is not yet available
            /*
            $this->app['pmi.drc.participants']->updateOrder(
                $this->participant->id,
                $this->order['rdr_id'],
                $order
            );
            */
        } else {
            $rdrId = $this->app['pmi.drc.participants']->createOrder($this->participant->id, $order);
            if ($rdrId) {
                $this->app['em']->getRepository('orders')->update(
                    $this->order['id'],
                    ['rdr_id' => $rdrId]
                );
            }
        }
    }

    protected function getSampleTime($set, $sample)
    {
        var_dump('hi!'); die;
        $samples = json_decode($this->order["{$set}_samples"]);
        if (!is_array($samples) || !in_array($sample, $samples)) {
            return false;
        }
        if ($set == 'processed') {
            $processedSampleTimes = json_decode($this->order['processed_samples_ts'], true);
            if (!empty($processedSampleTimes[$sample])) {
                try {
                    $time = new \DateTime();
                    $time->setTimestamp($processedSampleTimes[$sample]);
                    $time->setTimezone(new \DateTimeZone($this->app->getUser()->getInfo()['timezone']));
                    return $time->format('Y-m-d\TH:i:s\Z');
                } catch (\Exception $e) {
                }
            }
        } else {
            if ($this->order["{$set}_ts"]) {
                $time = new \DateTime($this->order["{$set}_ts"]);
                $time->setTimezone(new \DateTimeZone($this->app->getUser()->getInfo()['timezone']));
                return $time->format('Y-m-d\TH:i:s\Z');
            }
        }
    }

    protected function getRdrSamples()
    {
        $samples = [];
        foreach ($this->getRequestedSamples() as $description => $test) {
            $sample = [
                'test' => $test,
                'description' => $description,
                'processingRequired' => in_array($test, self::$samplesRequiringProcessing)
            ];
            if ($collected = $this->getSampleTime('collected', $test)) {
                $sample['collected'] = $collected;
            }
            if ($sample['processingRequired']) {
                $processed = $this->getSampleTime('processed', $test);
                if ($processed) {
                    $sample['processed'] = $processed;
                }
            }
            if ($finalized = $this->getSampleTime('finalized', $test)) {
                $sample['finalized'] = $finalized;
            }
            $samples[] = $sample;
        }
        return $samples;
    }

    protected function getOrderFormData($set)
    {
        $formData = [];
        if ($this->order["{$set}_notes"]) {
            $formData["{$set}_notes"] = $this->order["{$set}_notes"];
        };
        if ($set != 'processed') {
            if ($this->order["{$set}_ts"]) {
                $formData["{$set}_ts"] = new \DateTime($this->order["{$set}_ts"]);
            }
        }
        if ($this->order["{$set}_samples"]) {
            $samples = json_decode($this->order["{$set}_samples"]);
            if (is_array($samples) && count($samples) > 0) {
                $formData["{$set}_samples"] = $samples;
            }
        }
        if ($set == 'processed') {
            $processedSampleTimes = [];
            if (isset($this->order['processed_samples_ts'])) {
                $processedSampleTimes = json_decode($this->order['processed_samples_ts'], true);
            }
            foreach (self::$samplesRequiringProcessing as $sample) {
                if (!empty($processedSampleTimes[$sample])) {
                    try {
                        $sampleTs = new \DateTime();
                        $sampleTs->setTimestamp($processedSampleTimes[$sample]);
                        $sampleTs->setTimezone(new \DateTimeZone($this->app->getUser()->getInfo()['timezone']));
                        $formData['processed_samples_ts'][$sample] = $sampleTs;
                    } catch (\Exception $e) {
                        $formData['processed_samples_ts'][$sample] = null;
                    }
                } else {
                    $formData['processed_samples_ts'][$sample] = null;
                }
            }
        }
        return $formData;
    }

    protected function getRequestedSamples()
    {
        if ($this->order['type'] == 'saliva') {
            return self::$salivaSamples;
        }
        if ($this->order['requested_samples'] &&
            ($requestedArray = json_decode($this->order['requested_samples'])) &&
            is_array($requestedArray) &&
            count($requestedArray) > 0
        ) {
            return array_intersect(self::$samples, $requestedArray);
        } else {
            return self::$samples;
        }
    }

    protected function getEnabledSamples($set)
    {
        if ($this->order['collected_samples'] &&
            ($collectedArray = json_decode($this->order['collected_samples'])) &&
            is_array($collectedArray)
        ) {
            $collected = $collectedArray;
        } else {
            $collected = [];
        }

        if ($this->order['processed_samples'] &&
            ($processedArray = json_decode($this->order['processed_samples'])) &&
            is_array($processedArray)
        ) {
            $processed = $processedArray;
        } else {
            $processed = [];
        }

        switch ($set) {
            case 'processed':
                return array_intersect($collected, self::$samplesRequiringProcessing, $this->getRequestedSamples());
            case 'finalized':
                $enabled = array_intersect($collected, $this->getRequestedSamples());
                foreach ($enabled as $key => $sample) {
                    if (in_array($sample, self::$samplesRequiringProcessing) &&
                        !in_array($sample, $processed)
                    ) {
                        unset($enabled[$key]);
                    }
                }
                return array_values($enabled);
            default:
                return array_values($this->getRequestedSamples());
        }
    }
}
