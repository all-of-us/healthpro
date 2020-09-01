<?php
namespace Pmi\Review;

use Pmi\Order\Order;
use Pmi\Evaluation\Evaluation;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Validator\Constraints;

class Review
{
    public function __construct($db)
    {
        $this->db = $db;
    }

    public static $orderStatus = [
        'created_ts' => 'Created',
        'collected_ts' => 'Collected',
        'processed_ts' => 'Processed',
        'finalized_ts' => 'Finalized'
    ];

    protected static $measurementsStatus = [
        'created_ts' => 'Created',
        'finalized_ts' => 'Finalized'
    ];

    protected static $emptyParticipant = [
        'orders' => null,
        'ordersCount' => 0,
        'physicalMeasurements' => null,
        'physicalMeasurementsCount' => 0,
    ];

    protected function getTodayRows($startTime, $endTime, $site)
    {
        $ordersQuery = 'SELECT o.participant_id, \'order\' as type, o.id, null as parent_id, o.order_id, o.rdr_id, o.biobank_id, o.created_ts, o.collected_ts, o.processed_ts, o.finalized_ts, o.finalized_samples, o.biobank_finalized, ' .
            'greatest(coalesce(o.created_ts, 0), coalesce(o.collected_ts, 0), coalesce(o.processed_ts, 0), coalesce(o.finalized_ts, 0), coalesce(oh.created_ts, 0)) AS latest_ts, ' .
            'oh.type as h_type, ' .
            'u.email as created_by ' .
            'FROM orders o ' .
            'LEFT JOIN orders_history oh ' .
            'ON o.history_id = oh.id ' .
            'LEFT JOIN users u ' .
            'ON o.user_id = u.id WHERE ' .
            '(o.created_ts >= :startTime OR o.collected_ts >= :startTime OR o.processed_ts >= :startTime OR o.finalized_ts >= :startTime OR oh.created_ts >= :startTime) ' .
            'AND (o.created_ts < :endTime OR o.collected_ts < :endTime OR o.processed_ts < :endTime OR o.finalized_ts < :endTime OR oh.created_ts < :endTime) ' .
            'AND (o.site = :site OR o.collected_site = :site OR o.processed_site = :site OR o.finalized_site = :site) ';
        $measurementsQuery = 'SELECT e.participant_id, \'evaluation\' as type, e.id, e.parent_id, null, e.rdr_id, null, e.created_ts, null, null, e.finalized_ts, null, null, ' .
            'greatest(coalesce(e.created_ts, 0), coalesce(e.finalized_ts, 0), coalesce(eh.created_ts, 0)) as latest_ts, ' .
            'eh.type as h_type, ' .
            'null ' .
            'FROM evaluations e ' .
            'LEFT JOIN evaluations_history eh ' .
            'ON e.history_id = eh.id WHERE ' .
            'e.id NOT IN (SELECT parent_id FROM evaluations WHERE parent_id IS NOT NULL) ' .
            'AND (e.created_ts >= :startTime OR e.finalized_ts >= :startTime OR eh.created_ts >= :startTime) ' .
            'AND (e.created_ts < :endTime OR e.finalized_ts < :endTime OR eh.created_ts < :endTime) ' .
            'AND (e.site = :site OR e.finalized_site = :site)';
        $query = "($ordersQuery) UNION ($measurementsQuery) ORDER BY latest_ts DESC";

        return $this->db->fetchAll($query, [
            'startTime' => $startTime,
            'endTime' => $endTime,
            'site' => $site
        ]);
    }

    public function getTodayParticipants($startTime, $endTime, $site = null)
    {
        $participants = [];
        foreach ($this->getTodayRows($startTime, $endTime, $site) as $row) {
            $participantId = $row['participant_id'];
            if (!array_key_exists($participantId, $participants)) {
                $participants[$participantId] = self::$emptyParticipant;
            }
            switch ($row['type']) {
                case 'order':
                    // Get order status
                    foreach (self::$orderStatus as $field => $status) {
                        if ($row[$field]) {
                            $row['status'] = self::getOrderStatus($row, $status);
                        }
                    }
                    // Get number of finalized samples
                    if ($row['finalized_samples'] && ($samples = json_decode($row['finalized_samples'])) && is_array($samples)) {
                        $row['finalizedSamplesCount'] = count($samples);
                    }
                    $participants[$participantId]['orders'][] = $row;
                    $participants[$participantId]['ordersCount']++;
                    break;
                case 'evaluation':
                    // Get physical measurements status
                    foreach (self::$measurementsStatus as $field => $status) {
                        if ($row[$field]) {
                            $row['status'] = $this->getEvaluationStatus($row, $status);
                        }
                    }
                    $participants[$participantId]['physicalMeasurements'][] = $row;
                    $participants[$participantId]['physicalMeasurementsCount']++;
                    break;
            }
        }

        return $participants;
    }

    public function getTodayFilterForm($formFactory, $timeZone)
    {
        $formBuilder = $formFactory->createBuilder(FormType::class);
        $constraintDate = new \DateTime('today', new \DateTimeZone($timeZone));
        $formBuilder
            ->add('start_date', Type\DateTimeType::class, [
                'required' => true,
                'label' => 'Start Date',
                'widget' => 'single_text',
                'format' => 'M/d/yyyy',
                'model_timezone' => $timeZone,
                'view_timezone' => $timeZone,
                'constraints' => [
                    new Constraints\DateTime(),
                    new Constraints\LessThanOrEqual([
                        'value' => $constraintDate,
                        'message' => 'Date cannot be in the future'
                    ])
                ]
            ])
            ->add('end_date', Type\DateTimeType::class, [
                'required' => false,
                'label' => 'End Date',
                'widget' => 'single_text',
                'format' => 'M/d/yyyy',
                'model_timezone' => $timeZone,
                'view_timezone' => $timeZone,
                'constraints' => [
                    new Constraints\DateTime(),
                    new Constraints\GreaterThanOrEqual([
                        'propertyPath' => 'parent.all[start_date].data',
                        'message' => 'End date should be greater than start date'
                    ]),
                    new Constraints\LessThanOrEqual([
                        'value' => $constraintDate,
                        'message' => 'Date cannot be in the future'
                    ])
                ]
            ])
            ->add('Submit', Type\SubmitType::class, [
                'attr' => ['class' => 'btn btn-primary'],
            ]);
        return $formBuilder->getForm();
    }

    protected function getTodayOrderRows($today)
    {
        $ordersQuery = 'SELECT o.*, ' .
            'oh.type as h_type, ' .
            's.name as created_site_name, ' .
            'sc.name as collected_site_name, ' .
            'sp.name as processed_site_name, ' .
            'sf.name as finalized_site_name ' .
            'FROM orders o ' .
            'LEFT JOIN orders_history oh ON o.history_id = oh.id ' .
            'LEFT JOIN sites s ON s.site_id = o.site ' .
            'LEFT JOIN sites sc ON sc.site_id = o.collected_site ' .
            'LEFT JOIN sites sp ON sp.site_id = o.processed_site ' .
            'LEFT JOIN sites sf ON sf.site_id = o.finalized_site ' .
            'WHERE ' .
            '(o.created_ts >= :today OR o.collected_ts >= :today OR o.processed_ts >= :today OR o.finalized_ts >= :today OR oh.created_ts >= :today) ' .
            'ORDER BY o.created_ts DESC';

        return $this->db->fetchAll($ordersQuery, [
            'today' => $today
        ]);
    }

    public function getTodayOrders($today)
    {
        $orders = [];
        foreach ($this->getTodayOrderRows($today) as $row) {
            // Get order status
            foreach (self::$orderStatus as $field => $status) {
                if ($row[$field]) {
                    $row['orderStatus'] = self::getOrderStatus($row, $status);
                }
            }
            // Get number of finalized samples
            if ($row['finalized_samples'] && ($samples = json_decode($row['finalized_samples'])) && is_array($samples)) {
                $row['finalizedSamples'] = count($samples);
            }
            $orders[] = $row;
        }

        return $orders;
    }

    public static function getOrderStatus($row, $status)
    {
        $type = $row['h_type'];
        if ($type === Order::ORDER_CANCEL) {
            $status = 'Cancelled';
        } elseif ($type === Order::ORDER_UNLOCK) {
            $status = 'Unlocked';
        } elseif ($type === Order::ORDER_EDIT) {
            $status = 'Edited & Finalized';
        } elseif (!empty($row['finalized_ts']) && empty($row['rdr_id'])) {
            $status = 'Processed';
        } elseif (!empty($row['finalized_ts']) && $row['biobank_finalized']) {
            $status = 'Biobank Finalized';
        }
        return $status;
    }

    public function getEvaluationStatus($row, $status)
    {
        if ($row['h_type'] === Evaluation::EVALUATION_CANCEL) {
            $status = 'Cancelled';
        } elseif (!empty($row['parent_id']) && empty($row['rdr_id'])){
            $status = 'Unlocked';
        } elseif (!empty($row['parent_id']) && !empty($row['rdr_id'])) {
            $status = 'Edited & Finalized';
        } elseif (!empty($row['finalized_ts']) && empty($row['rdr_id'])) {
            $status = 'Created';
        }
        return $status;
    }
}
