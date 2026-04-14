<?php

namespace App\Nph\Order;

class TimePoints
{
    /** @var int */
    protected $module;

    /** @var array<string, string> */
    protected $timePoints;

    /** @var array<string, string> */
    protected $rdrTimePoints = [];

    /** @var array<string, list<string>> */
    protected $timePointSampleTypes;

    /**
     * @return array<string, string>
     */
    public function getTimePoints(): array
    {
        return $this->timePoints;
    }

    /**
     * @return array<string, string>
     */
    public function getRdrTimePoints(): array
    {
        return $this->rdrTimePoints;
    }

    /**
     * @return array<string, array<string, string>>
     */
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

    /**
     * @return array<string, array<string, mixed>>
     */
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
