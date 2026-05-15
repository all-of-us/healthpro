<?php

namespace App\Service\Nph;

class NphParticipantReviewService
{
    private NphParticipantSummaryService $nphParticipantSummaryService;

    public function __construct(NphParticipantSummaryService $nphParticipantSummaryService)
    {
        $this->nphParticipantSummaryService = $nphParticipantSummaryService;
    }

    /**
     * @param array<int, array<string, mixed>> $samples
     *
     * @return array{
     *   samples: array<int, array<string, mixed>>,
     *   rowCounts: array<string, array<string, int>>
     * }
     */
    public function getTodaysSamples(array $samples, bool $biobankView = false): array
    {
        $count = 0;
        $rowCounts = [];
        foreach (array_keys($samples) as $key) {
            if (!array_key_exists($samples[$key]['participantId'], $rowCounts)) {
                $rowCounts[$samples[$key]['participantId']]['participantRow'] = 0;
            }
            if (!array_key_exists('module' . $samples[$key]['module'], $rowCounts[$samples[$key]['participantId']])) {
                $rowCounts[$samples[$key]['participantId']]['module' . $samples[$key]['module']] = 0;
            }
            $rowCounts[$samples[$key]['participantId']]['participantRow'] += $samples[$key]['createdCount'] + 1;
            $rowCounts[$samples[$key]['participantId']]['module' . $samples[$key]['module']] += $samples[$key]['createdCount'] + 1;
            if (!$biobankView && $count <= 5) {
                $samples[$key]['participant'] = $this->nphParticipantSummaryService->getParticipantById($samples[$key]['participantId']);
            }
            $samples[$key]['email'] = explode(',', $samples[$key]['email']);
            $samples[$key]['sampleId'] = explode(',', $samples[$key]['sampleId']);
            $samples[$key]['sampleCode'] = explode(',', $samples[$key]['sampleCode']);
            $samples[$key]['createdTs'] = explode(',', $samples[$key]['createdTs']);
            $samples[$key]['collectedTs'] = explode(',', $samples[$key]['collectedTs']);
            $samples[$key]['finalizedTs'] = explode(',', $samples[$key]['finalizedTs']);
            $count++;
        }
        return ['samples' => $samples, 'rowCounts' => $rowCounts];
    }
}
