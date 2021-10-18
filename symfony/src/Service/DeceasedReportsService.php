<?php

namespace App\Service;

use App\Entity\DeceasedReport;
use App\Security\User;

class DeceasedReportsService
{
    protected $api;

    public function __construct(RdrApiService $api)
    {
        $this->api = $api;
    }

    public function getDeceasedReports($organizationId = null, $status = null)
    {
        if ($status && !in_array($status, array_keys(DeceasedReport::STATUSES))) {
            throw new \Exception(sprintf('Invalid status "%s", must be one of %s', $status, json_encode(DeceasedReport::STATUSES)));
        }
        $response = $this->api->get('rdr/v1/DeceasedReports', [
            'query' => [
                'org_id' => $organizationId,
                'status' => $status
            ]
        ]);
        return json_decode($response->getBody());
    }

    public function getDeceasedReportsByParticipant($participantId)
    {
        $response = $this->api->get(sprintf('rdr/v1/Participant/%s/DeceasedReport', $participantId));
        return json_decode($response->getBody());
    }

    public function getDeceasedReportById($participantId, $reportId)
    {
        $response = $this->api->get(sprintf('rdr/v1/Participant/%s/DeceasedReport', $participantId));
        $reports = json_decode($response->getBody());
        foreach ($reports as $report) {
            if ($report->identifier[0]->value === $reportId) {
                return $report;
            }
        }
        return false;
    }

    public function createDeceasedReport($participantId, $fhirData = [])
    {
        $response = $this->api->post(sprintf('rdr/v1/Participant/%s/Observation', $participantId), $fhirData);
        return json_decode($response->getBody());
    }

    /**
     * @var array $data
     * @var User $actor
     */
    public function buildDeceasedReportFhir(DeceasedReport $deceasedReport, User $actor)
    {
        $report = [
            'status' => 'preliminary',
            'code' => [
                'text' => 'DeceasedReport'
            ],
            'encounter' => [
                'reference' => $deceasedReport->getReportMechanism()
            ],
            'performer' => [
                [
                    'type' => 'https://www.pmi-ops.org/healthpro-username',
                    'reference' => $actor->getEmail()
                ]
            ],
            'issued' => date('c')
        ];
        if ($deceasedReport->getDateOfDeath()) {
            $report['effectiveDateTime'] = $deceasedReport->getDateOfDeath()->format('Y-m-d');
        }

        if ($deceasedReport->getCauseOfDeath()) {
            $report['valueString'] = $deceasedReport->getCauseOfDeath();
        }

        if (!in_array($deceasedReport->getReportMechanism(), ['EHR', 'OTHER'])) {
            $report['extension'] = [
                [
                    'url' => 'https://www.pmi-ops.org/deceased-reporter',
                    'valueHumanName' => [
                        'text' => $deceasedReport->getNextOfKinName(),
                        'extension' => [
                            [
                                'url' => 'http://hl7.org/fhir/ValueSet/relatedperson-relationshiptype',
                                'valueCode' => $deceasedReport->getNextOfKinRelationship()
                            ],
                            [
                                'url' => 'https://www.pmi-ops.org/email-address',
                                'valueString' => $deceasedReport->getNextOfKinEmail()
                            ],
                            [
                                'url' => 'https://www.pmi-ops.org/phone-number',
                                'valueString' => $deceasedReport->getNextOfKinTelephoneNumber()
                            ]
                        ]
                    ]
                ]
            ];
        }
        if ($deceasedReport->getReportMechanism() === 'OTHER') {
            $report['encounter']['display'] = $deceasedReport->getReportMechanismOtherDescription();
        }
        return $report;
    }

    /**
     * @var array $data
     * @var User $actor
     */
    public function buildDeceasedReportReviewFhir(DeceasedReport $deceasedReport, User $actor)
    {
        $report = [
            'status' => $deceasedReport->getReportStatus(),
            'code' => [
                'text' => 'DeceasedReport'
            ],
            'issued' => date('c'),
            'performer' => [
                [
                    'type' => 'https://www.pmi-ops.org/healthpro-username',
                    'reference' => $actor->getEmail()
                ]
            ]
        ];
        if ($deceasedReport->getReportStatus() === 'cancelled') {
            $report['extension'][] = [
                'url' => 'https://www.pmi-ops.org/observation-denial-reason',
                'valueReference' => [
                    'reference' => $deceasedReport->getDenialReason()
                ]
            ];
            if ($deceasedReport->getDenialReason() === 'OTHER') {
                $report['extension'][0]['valueReference']['display'] = $deceasedReport->getDenialReasonOtherDescription();
            }
        }
        return $report;
    }

    public function updateDeceasedReport($participantId, $reportId, $fhirData = [])
    {
        $response = $this->api->post(sprintf('rdr/v1/Participant/%s/Observation/%d/Review', $participantId, $reportId), $fhirData);
        return json_decode($response->getBody());
    }
}
