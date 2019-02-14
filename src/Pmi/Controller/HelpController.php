<?php
namespace Pmi\Controller;

use Pmi\HttpClient;
use Silex\Application;

class HelpController extends AbstractController
{
    protected static $name = 'help';
    protected static $routes = [
        ['home', '/'],
        ['videos', '/videos'],
        ['faq', '/faq'],
        ['sop', '/sop'],
        ['sopView', '/sop/{id}'],
        ['sopFile', '/sop/file/{id}'],
        ['sopRedirect', '/sop/redirect/{id}']
    ];
    private static $documentGroups = [
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
                ],
                'CHANGES-20170412-HPO' => [
                    'download' => true,
                    'title' => 'HPO SOP Changes Presentation 04-12-2017',
                    'filename' => 'HPO SOP Changes Presentation 04-12-2017.pptx'
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
                    'filename' => 'SOP-023 DV Cancelling, Editing, & Restoring Biobank Orders.pdf'
                ],
                'SOP-025' => [
                    'title' => 'SOP-025 DV Biobank Order Review',
                    'filename' => 'SOP-025 DV Biobank Order Review.pdf'
                ],
                'CHANGES-20170412-DV' => [
                    'download' => true,
                    'title' => 'DV SOP Changes Presentation 04-12-2017',
                    'filename' => 'DV SOP Changes Presentation 04-12-2017.pptx'
                ]
            ]
        ]
    ];

    private static $videoGroups = [
        [
            'title' => 'Biobank Video Tutorials for Healthcare Provider Organizations (HPO)',
            'videos' => [
                [
                    'title' => 'Chapter 1: HPO Creating an Order',
                    'link' => ''
                ],
                [
                    'title' => 'Chapter 3: HPO Blood Collection',
                    'link' => ''
                ],
                [
                    'title' => 'Chapter 5: HPO Urine Collection',
                    'link' => 'https://www.youtube.com/embed/ANJs1_A_zLs'
                ],
                [
                    'title' => 'Chapter 7: HPO Saliva Collection',
                    'link' => 'https://www.youtube.com/embed/0WeQCxetXQk'
                ],
                [
                    'title' => 'Chapter 9: HPO Ordering Supplies from MML',
                    'link' => 'https://www.youtube.com/embed/6P4nuWNOAQA'
                ]
            ]
        ],
        [
            'title' => 'Biobank Video Tutorials for Direct Volunteers (DV)',
            'videos' => [
                [
                    'title' => 'Chapter 2: DV Registering a KIT',
                    'link' => 'https://www.youtube.com/embed/X9E6u44w8Ks'
                ],
                [
                    'title' => 'Chapter 4: DV Blood Collection & Processing',
                    'link' => 'https://www.youtube.com/embed/pNSndLIIHQA'
                ],
                [
                    'title' => 'Chapter 6: DV Urine Collection',
                    'link' => 'https://www.youtube.com/embed/wVcFsCiyqtA'
                ],
                [
                    'title' => 'Chapter 10: DV Packaging & Shipping Specimens',
                    'link' => 'https://www.youtube.com/embed/yAHGK979kJ0'
                ]
            ]
        ],
        [
            'title' => 'Physical Measurements (HPO/DV)',
            'videos' => [
                [
                    'title' => 'Physical Measurements Video Tutorial (HPO/DV)',
                    'link' => 'https://www.youtube.com/embed/wE-sZoPCfdk'
                ]
            ]
        ],
        [
            'title' => 'General (HPO/DV)',
            'videos' => [
                [
                    'title' => 'All of Us Research Program FAQs',
                    'link' => 'https://www.youtube.com/embed/TE-IyOxazvo'
                ]
            ]
        ]
    ];

    private function getStoragePath(Application $app)
    {
        return $app->getConfig('help_storage_path') ?: 'https://docsallofus.atlassian.net/wiki/download/attachments/44357';
    }

    private function getDocumentInfo($id)
    {
        foreach (self::$documentGroups as $documentGroup) {
            if (array_key_exists($id, $documentGroup['documents'])) {
                return $documentGroup['documents'][$id];
            }
        }
        return false;
    }

    public function homeAction(Application $app)
    {
        return $app['twig']->render('help/index.html.twig');
    }

    public function videosAction(Application $app)
    {
        return $app['twig']->render('help/videos.html.twig', [
            'videoGroups' => self::$videoGroups
        ]);
    }

    public function faqAction(Application $app)
    {
        return $app['twig']->render('help/faq.html.twig');
    }

    public function sopAction(Application $app)
    {
        return $app['twig']->render('help/sop.html.twig', [
            'documentGroups' => self::$documentGroups,
            'path' => $this->getStoragePath($app)
        ]);
    }

    public function sopViewAction(Application $app, $id)
    {
        $document = $this->getDocumentInfo($id);
        if (!$document) {
            $app->abort(404);
        }
        return $app['twig']->render('help/sop-pdf.html.twig', [
            'sop' => $id,
            'title' => trim(str_replace($id, '', $document['title'])),
            'document' => $document,
            'path' => $this->getStoragePath($app)
        ]);
    }

    public function sopFileAction(Application $app, $id)
    {
        $document = $this->getDocumentInfo($id);
        if (!$document) {
            $app->abort(404);
        }
        $url = $this->getStoragePath($app) . '/' . rawurlencode($document['filename']);
        try {
            $client = new HttpClient();
            $response = $client->get($url, ['stream' => true]);
            $responseBody = $response->getBody();
            $stream = function () use ($responseBody) {
                while (!$responseBody->eof()) {
                    echo $responseBody->read(1024); // phpcs:ignore WordPress.XSS.EscapeOutput
                }
            };
            return $app->stream($stream, 200, [
                'Content-Type' => 'application/pdf'
            ]);
        } catch (\Exception $e) {
            error_log('Failed to retrieve Confluence file ' . $url . ' (' . $id . ')');
            echo '<html><body style="font-family: Helvetica Neue,Helvetica,Arial,sans-serif"><strong>File could not be loaded</strong></body></html>';
            exit;
        }
    }

    public function sopRedirectAction(Application $app, $id)
    {
        $document = $this->getDocumentInfo($id);
        if (!$document) {
            $app->abort(404);
        }
        $url = $this->getStoragePath($app) . '/' . rawurlencode($document['filename']);
        return $app->redirect($url);
    }
}
