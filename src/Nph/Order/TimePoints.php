<?php

namespace App\Nph\Order;

class TimePoints
{
    protected $module;

    protected $timePoints;

    protected $rdrTimePoints = [];

    protected $timePointSampleTypes;

    public function getTimePoints(): array
    {
        return $this->timePoints;
    }

    public function getRdrTimePoints(): array
    {
        return $this->rdrTimePoints;
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

    public function getSamplesInformation(): array
    {
        $module = 'Module' . $this->module;
        $file = __DIR__ . "/Samples/{$module}.json";
        if (!file_exists($file)) {
            throw new \Exception('Samples version file not found');
        }
        $schema = json_decode(file_get_contents($file), true);
        return $schema['samplesInformation'];
    }
}
