<?php

namespace App\Nph\Order;

class Samples
{
    private $visitObj;

    public function __construct($module, $visit)
    {
        $visitClass = 'App\Nph\Order\Visits\Visit' . $visit;
        $this->visitObj = new $visitClass($module);
    }

    public function getTimePoints(): array
    {
        return $this->visitObj->getTimePoints();
    }

    public function getTimePointSamples(): array
    {
        return $this->visitObj->getTimePointSamples();
    }

    public function getSamples(): array
    {
        $samplesInfo = $this->getSamplesInformation();
        $samples = [];
        foreach ($samplesInfo as $sampleCode => $sample) {
            $samples[$sampleCode] = $sample['label'];
        }
        return $samples;
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
        return $this->visitObj->getSamplesInformation();
    }

    public function getSampleLabelFromCode($sampleCode): string
    {
        return $this->visitObj->getSampleLabelFromCode($sampleCode);
    }

    public function getSampleCollectionVolumeFromCode(string $sampleCode): string
    {
        return $this->visitObj->getSampleCollectionVolumeFromCode($sampleCode);
    }
}
