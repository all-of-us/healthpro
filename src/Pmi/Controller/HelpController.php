<?php
namespace Pmi\Controller;

use Silex\Application;

class HelpController extends AbstractController
{
    protected static $name = 'help';
    protected static $routes = [
        ['home', '/'],
        ['videos', '/videos'],
        ['faq', '/faq'],
        ['sop', '/sop'],
        ['sopPdf', '/sop/viewer/{filename}']
    ];

    public function homeAction(Application $app)
    {
        return $app['twig']->render('help/index.html.twig');
    }

    public function videosAction(Application $app)
    {
        return $app['twig']->render('help/videos.html.twig');
    }

    public function faqAction(Application $app)
    {
        return $app['twig']->render('help/faq.html.twig');
    }

    public function sopAction(Application $app)
    {
        $documentGroups = [
            [
                'title' => 'Physical Measurements for DVs and HPOs',
                'documents' => [
                    [
                        'title' => 'SOP-012 HPO Blood Pressure Measurement',
                        'filename' => 'SOP-012.01 HPO Blood Pressure Measurement.pdf'
                    ],
                    [
                        'title' => 'SOP-013 HPO Heart Rate Measurement',
                        'filename' => 'SOP-013.01 HPO Heart Rate Measurement.pdf'
                    ],
                    [
                        'title' => 'SOP-014 HPO Height and Weight Measurement',
                        'filename' => 'SOP-014.01 HPO Height and Weight Measurement.pdf'
                    ],
                    [
                        'title' => 'SOP-015 HPO Waist-Hip Circumference Measurement',
                        'filename' => 'SOP-015.01 HPO Waist-Hip Circumference Measurement.pdf'
                    ]
                ]
            ],
            [
                'title' => 'Biobank Standard Operating Procedures for HPO',
                'documents' => [
                    [
                        'title' => 'SOP-001 HPO Creating a PMI Biobank Order and Printing Specimen Label and Test Requisition',
                        'filename' => 'SOP-001.01 HPO Creating a PMI Biobank Order and Printing Specimen Labels and Test Requisition.pdf'
                    ],
                    [
                        'title' => 'SOP-003 HPO Blood Specimens Collection and Processing',
                        'filename' => 'SOP-003.01 HPO Blood Specimens Collection and Processing.pdf'
                    ],
                    [
                        'title' => 'SOP-005 HPO Urine Specimen Collection',
                        'filename' => 'SOP-005.01 HPO Urine Specimen Collection.pdf'
                    ],
                    [
                        'title' => 'SOP-007 HPO Saliva Oragene Collection',
                        'filename' => 'SOP-007.01 HPO Saliva Oragene Collection.pdf'
                    ],
                    [
                        'title' => 'SOP-009 HPO Packaging and Shipping PMI Specimens',
                        'filename' => 'SOP-009.01 HPO Packaging and Shipping PMI Specimens.pdf'
                    ],
                    [
                        'title' => 'SOP-010 HPO Ordering Supplies from MML',
                        'filename' => 'SOP-010.01 HPO Ordering Supplies from MML.pdf'
                    ]
                ]
            ],
            [
                'title' => 'Biobank Standard Operating Procedures for DV',
                'documents' => [
                    [
                        'title' => 'SOP-002 DV Registering a PMI Kit and Creating a PMI Biobank Order',
                        'filename' => 'SOP-002.01 DV Registering a PMI Kit and Creating a PMI Biobank Order.pdf'
                    ],
                    [
                        'title' => 'SOP-004 DV Blood Specimens Collection and Processing',
                        'filename' => 'SOP-004.01 DV Blood Specimens Collection and Processing.pdf'
                    ],
                    [
                        'title' => 'SOP-006 DV Urine Specimen Collection',
                        'filename' => 'SOP-006.01 DV Urine Specimen Collection.pdf'
                    ],
                    [
                        'title' => 'SOP-011 DV Packaging and Shipping PMI Specimens',
                        'filename' => 'SOP-011.01 DV Packaging and Shipping PMI Specimens.pdf'
                    ]
                ]
            ]
        ];

        return $app['twig']->render('help/sop.html.twig', [
            'documentGroups' => $documentGroups
        ]);
    }

    public function sopPdfAction(Application $app, $filename)
    {
        if (!preg_match('/^(SOP-\d+)\.\d+ (.+)\.pdf$/', $filename, $m)) {
            $app->abort(404);
        }
        $sop = $m[1];
        $title = $m[2];
        return $app['twig']->render('help/sop-pdf.html.twig', [
            'filename' => $filename,
            'sop' => $sop,
            'title' => $title
        ]);
    }
}
