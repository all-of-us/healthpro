<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class HelpService
{
    public const ENGLISH_LANGUAGE_CODE = 'en';
    public static $SupportedLanguages = [
        'en',
        'es'
    ];

    public static $documentGroups = [
        [
            'title' => 'Physical Measurements for DVs and HPOs',
            'documents' => [
                'SOP-012' => [
                    'title' => 'SOP-012 Blood Pressure Measurement',
                    'filename' => 'SOP-012 Blood Pressure Measurement.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 012 Medicion de la presión arterial'
                ],
                'SOP-013' => [
                    'title' => 'SOP-013 Heart Rate Measurement',
                    'filename' => 'SOP-013 Heart Rate Measurement.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 013 Medición de la frecuencia cardíaca'
                ],
                'SOP-014' => [
                    'title' => 'SOP-014 Height and Weight Measurement',
                    'filename' => 'SOP-014 Height and Weight Measurement.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 014 Medición de altura y peso'
                ],
                'SOP-015' => [
                    'title' => 'SOP-015 Waist-Hip Circumference Measurement',
                    'filename' => 'SOP-015 Waist-Hip Circumference Measurement.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 015 Medición de la circunferencia de la cintura y la cadera'
                ]
            ]
        ],
        [
            'title' => 'Biobank Standard Operating Procedures for HPO',
            'documents' => [
                'SOP-001' => [
                    'title' => 'SOP-001 HPO Creating a PMI Biobank Order and Printing Specimen Label and Test Requisition',
                    'filename' => 'SOP-001 HPO Creating a PMI Biobank Order and Printing Specimen Labels and Test Requisition.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 001 Creación de una orden de biobanco PMI e impresión de etiqueta de la muestra y solicitud de prueba para las HPO'
                ],
                'SOP-003' => [
                    'title' => 'SOP-003 HPO Blood Specimens Collection and Processing',
                    'filename' => 'SOP-003 HPO Blood Specimens Collection and Processing.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 003 Recolección y el procesamiento de muestras de sangre de las HPO'
                ],
                'SOP-005' => [
                    'title' => 'SOP-005 HPO Urine Specimen Collection',
                    'filename' => 'SOP-005 HPO Urine Specimen Collection.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 005 Recolección de muestras de orina de las HPO'
                ],
                'SOP-007' => [
                    'title' => 'SOP-007 HPO Saliva Oragene Collection',
                    'filename' => 'SOP-007 HPO Saliva Oragene Collection.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 007 Recolección de saliva Oragene de las HPO'
                ],
                'SOP-009' => [
                    'title' => 'SOP-009 HPO Preparing Biobank Orders for Courier Pick-up',
                    'filename' => 'SOP-009 HPO Preparing Biobank Orders for Courier Pick-up.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 009 Preparación de las HPO de las órdenes de biobanco para su recogida por parte del servicio de mensajería'
                ],
                'SOP-010' => [
                    'title' => 'SOP-010 HPO Ordering Supplies from MML',
                    'filename' => 'SOP-010 HPO Ordering Supplies from MML.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 010 Pedido de suministros de las HPO a MCL'
                ],
                'SOP-018' => [
                    'title' => 'SOP-018 HPO Specimen Rejection Criteria',
                    'filename' => 'SOP-018 HPO Specimen Rejection Criteria.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 018 Criterios de rechazo de muestras de las HPO'
                ],
                'SOP-020' => [
                    'title' => 'SOP-020 HPO Biobank Order Downtime Procedure',
                    'filename' => 'SOP-020 HPO Biobank Order Downtime Procedure.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 020 Procedimiento de inactividad de orden de biobanco de las HPO'
                ],
                'SOP-022' => [
                    'title' => 'SOP-022 HPO Cancelling, Editing, & Restoring Biobank Orders',
                    'filename' => 'SOP-022 HPO Cancelling, Editing, & Restoring Biobank Orders.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 022 Cancelación, edición y restablecimiento de órdenes de biobanco de las HPO'
                ],
                'SOP-026' => [
                    'title' => 'SOP-026 HPO Biobank Order Review',
                    'filename' => 'SOP-026 HPO Biobank Order Review.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 026 Revisión de órdenes de biobanco de las HPO'
                ]
            ]
        ],
        [
            'title' => 'Biobank Standard Operating Procedures for DV',
            'documents' => [
                'SOP-002' => [
                    'title' => 'SOP-002 DV Registering a PMI Kit and Creating a PMI Biobank Order',
                    'filename' => 'SOP-002 DV Registering a PMI Kit and Creating a PMI Biobank Order.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 002 Registro de un kit de PMI y creación de una orden de biobanco de PMI para voluntarios directos'
                ],
                'SOP-004' => [
                    'title' => 'SOP-004 DV Blood Specimens Collection and Processing',
                    'filename' => 'SOP-004 DV Blood Specimens Collection and Processing.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 004 Recolección y procesamiento de especímenes de sangre para el DV Partner KIT Blood'
                ],
                'SOP-006' => [
                    'title' => 'SOP-006 DV Urine Specimen Collection',
                    'filename' => 'SOP-006 DV Urine Specimen Collection.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 006 Recolección de especímenes de orina para voluntarios directos'
                ],
                'SOP-011' => [
                    'title' => 'SOP-011 DV Packaging and Shipping PMI Specimens',
                    'filename' => 'SOP-011 DV Packaging and Shipping PMI Specimens.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 011 Embalaje y envío de especímenes de PMI para voluntarios directos'
                ],
                'SOP-019' => [
                    'title' => 'SOP-019 DV Specimen Rejection Criteria',
                    'filename' => 'SOP-019 DV Specimen Rejection Criteria.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 019 Criterios de rechazo de especímenes para voluntarios directos'
                ],
                'SOP-021' => [
                    'title' => 'SOP-021 DV Biobank Order Downtime Procedure',
                    'filename' => 'SOP-021 DV Biobank Order Downtime Procedure.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 021 Procedimiento de inactividad de orden de biobanco para voluntarios directos'
                ],
                'SOP-023' => [
                    'title' => 'SOP-023 DV Cancelling, Editing, & Restoring Biobank Orders',
                    'filename' => 'SOP-023 DV Cancelling, Editing, & Restoring Biobank Orders .pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 023 Cancelación, edición y restablecimiento de órdenes de biobanco para voluntarios directos'
                ],
                'SOP-025' => [
                    'title' => 'SOP-025 DV Biobank Order Review',
                    'filename' => 'SOP-025 DV Biobank Order Review.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 025 Revisión de órdenes de biobanco para voluntarios directos'
                ]
            ]
        ],
        [
            'title' => 'HealthPro Application SOPs',
            'documents' => [
                'HPRO SOP-001' => [
                    'title' => 'HPRO SOP-001 Report of Death in HealthPro',
                    'filename' => 'HPRO SOP-001 Report of Death in HealthPro.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 001 Informe de defunción en HealthPro'
                ],
                'HPRO SOP-002' => [
                    'title' => 'HPRO SOP-002 HealthPro Incentive Tracking Feature',
                    'filename' => 'HPRO SOP-002 HealthPro Incentive Tracking Feature.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 002 Función de seguimiento de incentivos de HealthPro'
                ],
                'HPRO SOP-003' => [
                    'title' => 'HPRO SOP-003 On-Site ID Verification Feature',
                    'filename' => 'HPRO SOP-003 On-Site ID Verification Feature.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 003 Verificación de la identidad en el centro'
                ],
                'HPRO SOP-004' => [
                    'title' => 'HPRO SOP-004 Patient Status Flag',
                    'filename' => 'HPRO SOP-004 Patient Status Flag.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 004 Indicador de estado del paciente'
                ],
                'HPRO SOP-005' => [
                    'title' => 'HPRO SOP-005 HealthPro Imports Feature',
                    'filename' => 'HPRO SOP-005 HealthPro Imports Feature.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 005 Importaciones de HealthPro'
                ],
                'HPRO SOP-006' => [
                    'title' => 'HPRO SOP-006 On-Site Details Reporting',
                    'filename' => 'HPRO SOP-006 On-Site Details Reporting.pdf',
                    'languages' => ['es', 'en'],
                    'es_title' => 'SOP Número 006 Informes detallados in situ'
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

    public static array $nphResources = [
        'program_mop' => 'https://nutritionforprecisionhealth.org/secure/documents/mops',
        'moodle_resources' => 'https://moodle.nutritionforprecisionhealth.org/course/view.php?id=14',
        'release_notes' => 'https://moodle.nutritionforprecisionhealth.org/mod/forum/view.php?id=264'
    ];

    protected $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

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

    public function getDocumentTitlesList(): array
    {
        $documentTitles = [];
        foreach (self::$documentGroups as $documentGroup) {
            foreach (array_keys($documentGroup['documents']) as $documentID) {
                $documentTitles[$documentID] = $documentGroup['documents'][$documentID]['title'];
            }
        }
        return $documentTitles;
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
