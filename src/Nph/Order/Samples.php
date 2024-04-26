<?php

namespace App\Nph\Order;

class Samples
{
    public const NOT_STARTED = 'not_started';

    public static array $dietPeriodStatusMap = [
        'in_progress_unfinalized' => ['text' => 'In Progress', 'textClass' => 'text-warning-orange', 'badgeClass' => 'bg-warning-orange'],
        'in_progress_finalized' => ['text' => 'In Progress', 'textClass' => 'text-warning-orange', 'badgeClass' => 'bg-warning-orange'],
        'not_started' => ['text' => 'Not Started', 'textClass' => 'text-muted', 'badgeClass' => 'bg-secondary']
    ];

    public static $aliquotDocuments = [
        'blood' => [
            'title' => 'HPRO Blood Aliquoting Instructions',
            'filename' => 'HPRO Blood Aliquoting Instructions.pdf'
        ],
        'urine' => [
            'title' => 'HPRO Spot Urine Aliquoting Instructions',
            'filename' => 'HPRO Spot Urine Aliquoting Instructions.pdf'
        ],
        '24urine' => [
            'title' => 'HPRO 24 Hour Urine Aliquoting Instructions',
            'filename' => 'HPRO 24 Hour Urine Aliquoting Instructions.pdf'
        ],
        'urineDlw' => [
            'title' => 'HPRO Doubly Labeled Water Urine Aliquoting Instructions',
            'filename' => 'HPRO Doubly Labeled Water Urine Aliquoting Instructions.pdf'
        ],
        'hair' => [
            'title' => 'HPRO Hair Aliquoting Instructions',
            'filename' => 'HPRO Hair Aliquoting Instructions.pdf'
        ],
        'nail' => [
            'title' => 'HPRO Nail Aliquoting Instructions',
            'filename' => 'HPRO Nail Aliquoting Instructions.pdf'
        ],
        'stool' => [
            'title' => 'HPRO Stool Instructions',
            'filename' => 'HPRO Stool Instructions.pdf'
        ],
        'saliva' => [
            'title' => 'HPRO Module 1&2 Saliva Aliquoting Instructions',
            'filename' => 'HPRO Module 1&2 Saliva Aliquoting Instructions.pdf'
        ],
        'saliva3' => [
            'title' => 'HPRO Module 3 Saliva Aliquoting Instructions',
            'filename' => 'HPRO Module 3 Saliva Aliquoting Instructions.pdf'
        ]
    ];
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

    public function getRdrTimePoints(): array
    {
        return $this->visitObj->getRdrTimePoints();
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

    public function getSampleTypeDisplayName($sampleIdentifier): string
    {
        $samplesInfo = $this->getSamplesInformation();
        foreach ($samplesInfo as $sampleCode => $sample) {
            if ($sampleIdentifier === $sampleCode) {
                if (isset($sample['typeDisplayName'])) {
                    return $sample['typeDisplayName'];
                }
                return ucwords($sample['type']);
            }
        }
        return '';
    }

    public function getAliquots(string $sampleIdentifier): ?array
    {
        $samplesInfo = $this->getSamplesInformation();
        foreach ($samplesInfo as $sampleCode => $sample) {
            if ($sampleIdentifier === $sampleCode && isset($sample['aliquots'])) {
                return $sample['aliquots'];
            }
        }
        return null;
    }

    public function getSamplesInformation(): array
    {
        return $this->visitObj->getSamplesInformation();
    }

    public function getSampleLabelFromCode(string $sampleCode): string
    {
        $samplesInfo = $this->getSamplesInformation();
        return $samplesInfo[$sampleCode]['label'];
    }

    public function getSampleCollectionVolumeFromCode(string $sampleCode): string
    {
        $samplesInfo = $this->getSamplesInformation();
        if (key_exists('collectionVolume', $samplesInfo[$sampleCode])) {
            return $samplesInfo[$sampleCode]['collectionVolume'];
        }
        return '';
    }

    public function getSampleIdentifierFromCode(string $sampleCode): string
    {
        $samplesInfo = $this->getSamplesInformation();
        return $samplesInfo[$sampleCode]['identifier'];
    }
}
