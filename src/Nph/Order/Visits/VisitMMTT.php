<?php

namespace App\Nph\Order\Visits;

use App\Biobank\Samples;

class VisitMMTT
{
    public static $timePoints = [
        'preMMTT' => 'Pre MMTT',
        'minus15min' => '-15 Min',
        'minus5min' => '-5 Min',
        '15min' => '15 Min',
        '30min' => '30 Min',
        '60min' => '60 Min',
        '90min' => '90 Min',
        '120min' => '120 Min',
        '240min' => '240 Min',
        'postMMTT' => 'Post MMTT'
    ];

    public static $timePointSampleTypes = [
        'preMMTT' => ['urine', 'saliva', 'stool', 'hair', 'nail'],
        'postMMTT' => ['urine', 'saliva']
    ];

    public static function getSamples($module): array
    {
        $module = 'module' . $module;
        $file = __DIR__ . "/../Samples/{$module}.json";
        if (!file_exists($file)) {
            throw new \Exception('Samples version file not found');
        }
        $schema = json_decode(file_get_contents($file), true);
        $samples = $schema['samplesInformation'];
        $timePointSamples = [];
        foreach (self::$timePoints as $key => $timePoint) {
            foreach ($samples as $sampleCode => $sample) {
                if (isset(self::$timePointSampleTypes[$key])) {
                    if (in_array($sample['type'], self::$timePointSampleTypes[$key])) {
                        $timePointSamples[$key][$sampleCode] = $sample['label'];
                    }
                } elseif ($sample['type'] === 'blood') {
                    $timePointSamples[$key][$sampleCode] = $sample['label'];
                }
            }
        }
        return $timePointSamples;
    }
}
