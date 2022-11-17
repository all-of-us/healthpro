<?php

namespace App\Nph\Order;

class TimePoints
{
    public $module;

    public $timePoints;

    public $timePointSampleTypes;

    public function getSamples(): array
    {
        $samplesInfo = $this->getSamplesInformation();
        $samples = [];
        foreach ($samplesInfo as $sampleCode => $sample) {
            $samples[$sampleCode] = $sample['label'];
        }
        return $samples;
    }

    public function getTimePointSamples(): array
    {
        $samples = $this->getSamplesInformation();
        $timePointSamples = [];
        foreach (array_keys($this->timePoints) as $key) {
            foreach ($samples as $sampleCode => $sample) {
                if (isset($this->timePointSampleTypes[$key])) {
                    if (in_array($sample['type'], $this->timePointSampleTypes[$key])) {
                        $timePointSamples[$key][$sampleCode] = $sample['label'];
                    }
                } elseif ($sample['type'] === 'blood') {
                    $timePointSamples[$key][$sampleCode] = $sample['label'];
                }
            }
        }
        return $timePointSamples;
    }

    public function getSampleType($sampleIdentifier): string
    {
        $samplesInfo = $this->getSamplesInformation();
        foreach ($samplesInfo as $sampleCode => $sample) {
            if ($sampleIdentifier === $sampleCode) {
                return $sample['type'];
            }
        }
        return '';
    }

    public function getSamplesInformation(): array
    {
        $module = 'module' . $this->module;
        $file = __DIR__ . "/Samples/{$module}.json";
        if (!file_exists($file)) {
            throw new \Exception('Samples version file not found');
        }
        $schema = json_decode(file_get_contents($file), true);
        return $schema['samplesInformation'];
    }

    public function getSamplesByType($type): array
    {
        $samplesInfo = $this->getSamplesInformation();
        $samples = [];
        foreach ($samplesInfo as $sampleCode => $sample) {
            if (empty($sample['placeholder']) && $sample['type'] === $type) {
                $samples[] = $sampleCode;
            }
        }
        return $samples;
    }
}
