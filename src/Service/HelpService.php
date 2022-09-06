<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class HelpService
{
    protected $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    public static $documentGroups = [
        [
            'title' => 'Physical Measurements for DVs and HPOs',
            'documents' => [
                'SOP-012' => [
                    'title' => 'SOP-012 Blood Pressure Measurement',
                    'filename' => 'SOP-012 Blood Pressure Measurement.pdf'
                ],
                'SOP-013' => [
                    'title' => 'SOP-013 Heart Rate Measurement',
                    'filename' => 'SOP-013 Heart Rate Measurement.pdf'
                ],
                'SOP-014' => [
                    'title' => 'SOP-014 Height and Weight Measurement',
                    'filename' => 'SOP-014 Height and Weight Measurement.pdf'
                ],
                'SOP-015' => [
                    'title' => 'SOP-015 Waist-Hip Circumference Measurement',
                    'filename' => 'SOP-015 Waist-Hip Circumference Measurement.pdf'
                ]
            ]
        ],
        [
            'title' => 'Biobank Standard Operating Procedures for HPO',
            'documents' => [
                'SOP-001' => [
                    'title' => 'SOP-001 HPO Creating a PMI Biobank Order and Printing Specimen Label and Test Requisition',
                    'filename' => 'SOP-001 HPO Creating a PMI Biobank Order and Printing Specimen Labels and Test Requisition.pdf'
                ],
                'SOP-003' => [
                    'title' => 'SOP-003 HPO Blood Specimens Collection and Processing',
                    'filename' => 'SOP-003 HPO Blood Specimens Collection and Processing.pdf'
                ],
                'SOP-005' => [
                    'title' => 'SOP-005 HPO Urine Specimen Collection',
                    'filename' => 'SOP-005 HPO Urine Specimen Collection.pdf'
                ],
                'SOP-007' => [
                    'title' => 'SOP-007 HPO Saliva Oragene Collection',
                    'filename' => 'SOP-007 HPO Saliva Oragene Collection.pdf'
                ],
                'SOP-009' => [
                    'title' => 'SOP-009 HPO Preparing Biobank Orders for Courier Pick-up',
                    'filename' => 'SOP-009 HPO Preparing Biobank Orders for Courier Pick-up.pdf'
                ],
                'SOP-010' => [
                    'title' => 'SOP-010 HPO Ordering Supplies from MML',
                    'filename' => 'SOP-010 HPO Ordering Supplies from MML.pdf'
                ],
                'SOP-018' => [
                    'title' => 'SOP-018 HPO Specimen Rejection Criteria',
                    'filename' => 'SOP-018 HPO Specimen Rejection Criteria.pdf'
                ],
                'SOP-020' => [
                    'title' => 'SOP-020 HPO Biobank Order Downtime Procedure',
                    'filename' => 'SOP-020 HPO Biobank Order Downtime Procedure.pdf'
                ],
                'SOP-022' => [
                    'title' => 'SOP-022 HPO Cancelling, Editing, & Restoring Biobank Orders',
                    'filename' => 'SOP-022 HPO Cancelling, Editing, & Restoring Biobank Orders.pdf'
                ],
                'SOP-026' => [
                    'title' => 'SOP-026 HPO Biobank Order Review',
                    'filename' => 'SOP-026 HPO Biobank Order Review.pdf'
                ]
            ]
        ],
        [
            'title' => 'Biobank Standard Operating Procedures for DV',
            'documents' => [
                'SOP-002' => [
                    'title' => 'SOP-002 DV Registering a PMI Kit and Creating a PMI Biobank Order',
                    'filename' => 'SOP-002 DV Registering a PMI Kit and Creating a PMI Biobank Order.pdf'
                ],
                'SOP-004' => [
                    'title' => 'SOP-004 DV Blood Specimens Collection and Processing',
                    'filename' => 'SOP-004 DV Blood Specimens Collection and Processing.pdf'
                ],
                'SOP-006' => [
                    'title' => 'SOP-006 DV Urine Specimen Collection',
                    'filename' => 'SOP-006 DV Urine Specimen Collection.pdf'
                ],
                'SOP-011' => [
                    'title' => 'SOP-011 DV Packaging and Shipping PMI Specimens',
                    'filename' => 'SOP-011 DV Packaging and Shipping PMI Specimens.pdf'
                ],
                'SOP-019' => [
                    'title' => 'SOP-019 DV Specimen Rejection Criteria',
                    'filename' => 'SOP-019 DV Specimen Rejection Criteria.pdf'
                ],
                'SOP-021' => [
                    'title' => 'SOP-021 DV Biobank Order Downtime Procedure',
                    'filename' => 'SOP-021 DV Biobank Order Downtime Procedure.pdf'
                ],
                'SOP-023' => [
                    'title' => 'SOP-023 DV Cancelling, Editing, & Restoring Biobank Orders',
                    'filename' => 'SOP-023 DV Cancelling, Editing, & Restoring Biobank Orders .pdf'
                ],
                'SOP-025' => [
                    'title' => 'SOP-025 DV Biobank Order Review',
                    'filename' => 'SOP-025 DV Biobank Order Review.pdf'
                ]
            ]
        ],
        [
            'title' => 'HealthPro Application SOPs',
            'documents' => [
                'HPRO SOP-001' => [
                    'title' => 'HPRO SOP-001 Report of Death in HealthPro',
                    'filename' => 'HPRO SOP-001 Report of Death in HealthPro.pdf'
                ],
                'HPRO SOP-002' => [
                    'title' => 'HPRO SOP-002 HealthPro Incentive Tracking Feature',
                    'filename' => 'HPRO SOP-002 HealthPro Incentive Tracking Feature.pdf'
                ],
                'HPRO SOP-003' => [
                    'title' => 'HPRO SOP-003 On-Site ID Verification Feature',
                    'filename' => 'HPRO SOP-003 On-Site ID Verification Feature.pdf'
                ],
                'HPRO SOP-004' => [
                    'title' => 'HPRO SOP-004 Patient Status Flag',
                    'filename' => 'HPRO SOP-004 Patient Status Flag.pdf'
                ],
                'HPRO SOP-005' => [
                    'title' => 'HPRO SOP-005 HealthPro Imports Feature',
                    'filename' => 'HPRO SOP-005 HealthPro Imports Feature.pdf'
                ]
            ]
        ]
    ];

    public static $videoPlaylists = [
        'biobank-hpo' => [
            'tab_title' => 'HPO Biobank',
            'title' => 'Biobank Video Tutorials for Healthcare Provider Organizations (HPO)',
            'type' => 'kaltura',
            'widget' => 'https://cdnapisec.kaltura.com/p/1825021/sp/182502100/embedIframeJs/uiconf_id/26524131/partner_id/1825021/widget_id/0_7biqpuyo',
            'player_id' => 'kaltura_player_5d13d51bcc56d',
            'playlist_id' => '0_j87i59jj'
        ],
        'biobank-dv' => [
            'tab_title' => 'DV Biobank',
            'title' => 'Biobank Video Tutorials for Direct Volunteers (DV)',
            'type' => 'kaltura',
            'widget' => 'https://cdnapisec.kaltura.com/p/1825021/sp/182502100/embedIframeJs/uiconf_id/26524131/partner_id/1825021/widget_id/0_6rm30l9d',
            'player_id' => 'kaltura_player_5d13dbf851c3b',
            'playlist_id' => '0_7mrmfvqa'
        ],
        'other' => [
            'tab_title' => 'Other',
            'type' => 'youtube',
            'groups' => [
                [
                    'title' => 'Physical Measurements (HPO/DV)',
                    'videos' => [
                        [
                            'title' => 'Physical Measurements Video Tutorial (HPO/DV)',
                            'youtube_id' => 'wE-sZoPCfdk',
                            'filename' => '20180327_NIH Scripps_EXAM V12_FINAL_COMPRESSED.mp4'
                        ]
                    ]
                ],
                [
                    'title' => 'General (HPO/DV)',
                    'videos' => [
                        [
                            'title' => 'All of Us Research Program FAQs',
                            'youtube_id' => 'TE-IyOxazvo',
                            'filename' => '20180327_NIH Scripps_FAQ V6_FINAL_COMPRESSED.mp4'
                        ]
                    ]
                ]
            ]
        ]
    ];

    public static $faqs = [
        'web_link' => 'https://docs.google.com/document/d/e/2PACX-1vTA0q5gjIvLjX23Dj-XaXBgMxRVFeyQEhzvo5JcfAa61fbvXO9wflZnttN-EXhzyrE1SW5ht97frxwA/pub'
    ];

    public static $confluenceResources = [
        'ops_data_api' => 'https://www.aoucollaborations.net/x/sQB4G',
        'data_dictionaries' => 'https://www.aoucollaborations.net/x/a8U_/',
        'release_notes' => 'https://www.aoucollaborations.net/x/MQElvw'
    ];

    public function getStoragePath()
    {
        return $this->params->has('help_storage_path') ? $this->params->get('help_storage_path') : 'https://docsallofus.atlassian.net/wiki/download/attachments/44357';
    }

    public function getDocumentInfo($id)
    {
        foreach (self::$documentGroups as $documentGroup) {
            if (array_key_exists($id, $documentGroup['documents'])) {
                return $documentGroup['documents'][$id];
            }
        }
        return false;
    }

    public static function getFeedbackUrl(): string
    {
        return 'https://redcap.pmi-ops.org/surveys/?s=JN33K7PKWC';
    }

    public static function getReportTechnicalIssueUrl(): string
    {
        return 'https://redcap.pmi-ops.org/surveys/?s=ND8RJL78X4YWTKRX';
    }
}
