<?php

namespace App\Service;

use App\Helper\Participant;
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
        $this->mpdf = new Mpdf(['orientation' => 'L', 'format' => [60.96, 101.6], 'margin_left' => 5, 'margin_right' => 5, 'margin_top' => 5, 'margin_bottom' => 2]);
        $this->twig = $twig;
    }

    /**
     * @throws MpdfException
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    private function renderPDF(string $name, string $orderID, \DateTime $DOB, string $specimenID, string $moduleNum, string $timePoint, string $sampleCode, string $VisitType, string $collectionVolume): void
    {
        $this->mpdf->WriteHTML(
            $this->twig->render('program/nph/PDF/biospecimenLabel.html.twig', [
                    'PatientName' => $name,
                    'OrderID' => $orderID,
                    'dob' => $DOB->format('Y-m-d'),
                    'SpecimenID' => $specimenID,
                    'ModuleNum' => $moduleNum,
                    'TimePoint' => $timePoint,
                    'SampleCode' => $sampleCode,
                    'VisitType' => $VisitType,
                    'CollectionVolume' => $collectionVolume
                ])
        );
    }

    //TODO: Refactor to work off shyams NPHOrderService->getExistingOrdersData
    public function batchPDF(array $OrderSummary, Participant $participant, string $module, string $visit): string
    {
        foreach ($OrderSummary as $timePoint => $timePointSample) {
            foreach ($timePointSample as $sampleCode => $sampleInfo) {
                $timePoint = str_replace('minus', '-', $timePoint);
                try {
                    $this->renderPDF(
                        $participant->firstName . ' ' . $participant->lastName,
                        $sampleInfo['OrderID'],
                        $participant->dob,
                        $sampleInfo['SampleID'],
                        $module,
                        $timePoint,
                        $sampleCode,
                        $visit,
                        $sampleInfo['SampleCollectionVolume']
                    );
                } catch (MpdfException | LoaderError | RuntimeError | SyntaxError $e) {
                    return "Unable to render PDF";
                }
            }
        }
        try {
            return $this->mpdf->Output($participant->id.'.pdf', Destination::STRING_RETURN);
        } catch (MpdfException $exception) {
            return "Unable to render PDF";
        }
    }
}
