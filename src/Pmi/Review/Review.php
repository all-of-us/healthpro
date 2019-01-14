<?php
namespace Pmi\Review;

use Pmi\Order\Order;
use Pmi\Evaluation\Evaluation;

class Review
{
    protected static $orderStatus = [
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
        'order' => null,
        'orderCount' => 0,
        'orderStatus' => '',
        'finalizedSamples' => null,
        'physicalMeasurement' => null,
        'physicalMeasurementCount' => 0,
        'physicalMeasurementStatus' => ''
    ];

    protected function getTodayRows($db, $today, $site)
    {
        $ordersQuery = 'SELECT o.participant_id, \'order\' as type, o.id, null as parent_id, o.order_id, o.rdr_id, o.created_ts, o.collected_ts, o.processed_ts, o.finalized_ts, o.finalized_samples, ' .
            'greatest(coalesce(o.created_ts, 0), coalesce(o.collected_ts, 0), coalesce(o.processed_ts, 0), coalesce(o.finalized_ts, 0), coalesce(oh.created_ts, 0)) AS latest_ts, ' .
            'oh.type as h_type ' .
            'FROM orders o ' .
            'LEFT JOIN orders_history oh ' .
            'ON o.history_id = oh.id WHERE ' .
            '(o.created_ts >= :today OR o.collected_ts >= :today OR o.processed_ts >= :today OR o.finalized_ts >= :today OR oh.created_ts >= :today) ' .
            'AND (o.site = :site OR o.collected_site = :site OR o.processed_site = :site OR o.finalized_site = :site) ';
        $measurementsQuery = 'SELECT e.participant_id, \'measurement\' as type, e.id, e.parent_id, null, e.rdr_id, e.created_ts, null, null, e.finalized_ts, null, ' .
            'greatest(coalesce(e.created_ts, 0), coalesce(e.finalized_ts, 0), coalesce(eh.created_ts, 0)) as latest_ts, ' .
            'eh.type as h_type ' .
            'FROM evaluations e ' .
            'LEFT JOIN evaluations_history eh ' .
            'ON e.history_id = eh.id WHERE ' .
            'e.id NOT IN (SELECT parent_id FROM evaluations WHERE parent_id IS NOT NULL) ' .
            'AND (e.created_ts >= :today OR e.finalized_ts >= :today OR eh.created_ts >= :today) ' .
            'AND (e.site = :site OR e.finalized_site = :site)';
        $query = "($ordersQuery) UNION ($measurementsQuery) ORDER BY latest_ts DESC";

        return $db->fetchAll($query, [
            'today' => $today,
            'site' => $site
        ]);
    }

    public function getTodayParticipants($db, $today, $site = null)
    {
        $participants = [];
        foreach ($this->getTodayRows($db, $today, $site) as $row) {
            $participantId = $row['participant_id'];
            if (!array_key_exists($participantId, $participants)) {
                $participants[$participantId] = self::$emptyParticipant;
            }
            switch ($row['type']) {
                case 'order':
                    if (is_null($participants[$participantId]['order'])) {
                        $participants[$participantId]['order'] = $row;
                        $participants[$participantId]['orderCount'] = 1;
                        // Get order status
                        foreach (self::$orderStatus as $field => $status) {
                            if ($row[$field]) {
                                $participants[$participantId]['orderStatus'] = $this->getOrderStatus($row, $status);
                            }
                        }
                        // Get number of finalized samples
                        if ($row['finalized_samples'] && ($samples = json_decode($row['finalized_samples'])) && is_array($samples)) {
                            $participants[$participantId]['finalizedSamples'] = count($samples);
                        }
                    } else {
                        $participants[$participantId]['orderCount']++;
                    }
                    break;
                case 'measurement':
                    if (is_null($participants[$participantId]['physicalMeasurement'])) {
                        $participants[$participantId]['physicalMeasurement'] = $row;
                        $participants[$participantId]['physicalMeasurementCount'] = 1;
                        // Get physical measurements status
                        foreach (self::$measurementsStatus as $field => $status) {
                            if ($row[$field]) {
                                $participants[$participantId]['physicalMeasurementStatus'] = $this->getEvaluationStatus($row, $status);
                            }
                        }
                    } else {
                        $participants[$participantId]['physicalMeasurementCount']++;
                    }
                    break;
            }
        }

        return $participants;
    }

    protected function getTodayOrderRows($db, $today)
    {
        $ordersQuery = 'SELECT o.participant_id, \'order\' as type, o.id, null as parent_id, o.order_id, o.biobank_id, o.rdr_id, o.created_ts, o.collected_ts, o.processed_ts, o.finalized_ts, o.finalized_samples, ' .
            'greatest(coalesce(o.created_ts, 0), coalesce(o.collected_ts, 0), coalesce(o.processed_ts, 0), coalesce(o.finalized_ts, 0), coalesce(oh.created_ts, 0)) AS latest_ts, ' .
            'oh.type as h_type ' .
            'FROM orders o ' .
            'LEFT JOIN orders_history oh ' .
            'ON o.history_id = oh.id WHERE ' .
            '(o.created_ts >= :today OR o.collected_ts >= :today OR o.processed_ts >= :today OR o.finalized_ts >= :today OR oh.created_ts >= :today) ';

        return $db->fetchAll($ordersQuery, [
            'today' => $today
        ]);
    }

    public function getTodayOrderParticipants($db, $today)
    {
        $participants = [];
        foreach ($this->getTodayOrderRows($db, $today) as $row) {
            $participantId = $row['participant_id'];
            if (!array_key_exists($participantId, $participants)) {
                $participants[$participantId] = self::$emptyParticipant;
            }
            if (is_null($participants[$participantId]['order'])) {
                $participants[$participantId]['order'] = $row;
                $participants[$participantId]['orderCount'] = 1;
                // Get order status
                foreach (self::$orderStatus as $field => $status) {
                    if ($row[$field]) {
                        $participants[$participantId]['orderStatus'] = $this->getOrderStatus($row, $status);
                    }
                }
                // Get number of finalized samples
                if ($row['finalized_samples'] && ($samples = json_decode($row['finalized_samples'])) && is_array($samples)) {
                    $participants[$participantId]['finalizedSamples'] = count($samples);
                }
            } else {
                $participants[$participantId]['orderCount']++;
            }
        }

        return $participants;
    }

    public function getOrderStatus($row, $status)
    {
        $order = new Order;
        $type = $row['h_type'];
        if ($type === $order::ORDER_CANCEL) {
            $status = 'Cancelled';
        } elseif ($type === $order::ORDER_UNLOCK) {
            $status = 'Unlocked';
        } elseif ($type === $order::ORDER_EDIT) {
            $status = 'Edited & Finalized';
        } elseif (!empty($row['finalized_ts']) && empty($row['rdr_id'])) {
            $status = 'Processed';
        }
        return $status;
    }

    public function getEvaluationStatus($row, $status)
    {
        $evaluation = new Evaluation();
        if ($row['h_type'] === $evaluation::EVALUATION_CANCEL) {
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
