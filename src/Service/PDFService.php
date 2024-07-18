<?php

namespace App\Service;

use App\Helper\NphParticipant;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class PDFService
{
    private $mpdf;
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];
        $this->mpdf = new Mpdf([
            'orientation' => 'L',
            'format' => [60.96, 101.6],
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 5,
            'margin_bottom' => 2,
            'tempDir' => '/tmp',
            'default_font' => 'notomono',
            'fontDir' => array_merge($fontDirs, [
                __DIR__ . '/../../web/assets/fonts',
            ]),
            'fontdata' => $fontData + [
                'notomono' => [
                    'R' => 'NotoMono-Regular.ttf',
                ],
                ],
            ]);
        $this->twig = $twig;
    }

    public function batchPDF(array $OrderSummary, NphParticipant $participant, string $module, string $visit): string
    {
        $stoolPrinted = false;
        foreach ($OrderSummary as $timePointOrder) {
            foreach ($timePointOrder as $sampleType => $sampleInfo) {
                foreach (array_keys($sampleInfo) as $sampleCode) {
                    foreach ($sampleInfo[$sampleCode] as $sample) {
                        try {
                            $participantFullName = $participant->firstName . ' ' . $participant->lastName;
                            if (strlen($participantFullName) > 18) {
                                $participantFullName = substr(
                                    $participant->firstName[0] . '. ' . $participant->lastName,
                                    0,
                                    18
                                );
                            }
                            $sampleId = $sample['sampleId'];
                            if (($sampleType === 'stool'|| $sampleType === 'stool2') && $stoolPrinted === false) {
                                $sample['identifier'] = 'ST-KIT';
                                $sampleId = $sample['orderId'];
                                $stoolPrinted = true;
                            } elseif (($sampleType === 'stool' || $sampleType === 'stool2') && $stoolPrinted === true) {
                                continue;
                            }
                            $visit = $sample['visitDisplayName'];
                            if ($module > 1) {
                                $visit = str_replace('Diet ', '', $visit);
                            }
                            $this->renderPDF(
                                $participantFullName,
                                $sampleType,
                                $participant->dob,
                                $sampleId,
                                $module,
                                $sample['timepointDisplayName'],
                                $sample['identifier'],
                                $visit,
                                $sample['sampleCollectionVolume']
                            );
                        } catch (MpdfException|LoaderError|RuntimeError|SyntaxError $e) {
                            return 'Unable to render PDF';
                        }
                    }
                }
            }
        }
        try {
            return $this->mpdf->Output($participant->id . '.pdf', Destination::STRING_RETURN);
        } catch (MpdfException $exception) {
            return 'Unable to render PDF';
        }
    }

    /**
     * @throws MpdfException
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    private function renderPDF(string $name, string $sampleType, ?\DateTime $DOB, string $specimenID, string $moduleNum, string $timePoint, string $sampleCode, string $VisitType, string $collectionVolume): void
    {
        $barcode = new \TCPDF2DBarcode($specimenID, 'DATAMATRIX');
        $pngData = $barcode->getBarcodePngData(5, 5);
        $this->mpdf->imageVars['datamatrix'] = $pngData;
        $this->mpdf->WriteHTML(
            $this->twig->render('program/nph/pdf/biospecimen-label.html.twig', [
                    'PatientName' => $name,
                    'sampleType' => $sampleType,
                    'dob' => $DOB ? $DOB->format('Y-m-d') : null,
                    'SpecimenID' => $specimenID,
                    'ModuleNum' => $moduleNum,
                    'TimePoint' => $timePoint,
                    'SampleCode' => $sampleCode,
                    'VisitType' => $VisitType,
                    'CollectionVolume' => $collectionVolume,
                ])
        );
    }
}
