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
        $this->mpdf = new Mpdf([
            'orientation' => 'L',
            'format' => [60.96, 101.6],
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 5,
            'margin_bottom' => 2,
            'tempDir' => '/tmp'
        ]);
        $this->twig = $twig;
    }

    public function batchPDF(array $OrderSummary, NphParticipant $participant, string $module, string $visit): string
    {
        $stoolPrinted = false;
        foreach ($OrderSummary as $timePointOrder) {
            foreach ($timePointOrder as $sampleType => $sampleInfo) {
                foreach ($sampleInfo as $sample) {
                    try {
                        $participantFullName = $participant->firstName . ' ' . $participant->lastName;
                        if (strlen($participantFullName) > 20) {
                            $participantFullName = substr(
                                $participant->firstName[0] . '. ' . $participant->lastName,
                                0,
                                20
                            );
                        }
                        $sampleId = $sample['sampleId'];
                        if ($sampleType === 'stool' && $stoolPrinted === false) {
                            $sample['identifier'] = 'ST-KIT';
                            $sampleId = $sample['orderId'];
                            $sampleId = preg_replace('/KIT-?/', '', $sampleId);
                            $stoolPrinted = true;
                        } elseif ($sampleType === 'stool' && $stoolPrinted === true) {
                            continue;
                        }
                        $this->renderPDF(
                            $participantFullName,
                            $sampleType,
                            $participant->dob,
                            $sampleId,
                            $module,
                            $sample['timepointDisplayName'],
                            $sample['identifier'],
                            $sample['visitDisplayName'],
                            $sample['sampleCollectionVolume']
                        );
                    } catch (MpdfException | LoaderError | RuntimeError | SyntaxError $e) {
                        return 'Unable to render PDF';
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
                    'CollectionVolume' => $collectionVolume
                ])
        );
    }
}
