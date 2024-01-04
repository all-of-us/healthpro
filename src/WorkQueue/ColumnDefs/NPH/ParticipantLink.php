<?php

namespace App\WorkQueue\ColumnDefs\NPH;

use App\WorkQueue\ColumnDefs\DefaultColumn;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ParticipantLink extends DefaultColumn
{
    private UrlGeneratorInterface $urlGenerator;

    public function getColumnDisplay($data, $dataRow): string
    {
        return '<a href="' . $this->urlGenerator->generate('nph_participant_summary', ['participantId' => $dataRow['participantNphId']]) . '">' . $data . '</a>';
    }

    public function setRouteService($routeService): void
    {
        $this->urlGenerator = $routeService;
    }
}
