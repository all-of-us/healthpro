<?php

namespace App\Service\Nph;

use App\Entity\NphDlw;
use Doctrine\ORM\EntityManagerInterface;

class NphDlwBackfillService
{
    protected EntityManagerInterface $em;
    protected NphOrderService $nphOrderService;

    public function __construct(EntityManagerInterface $em, NphOrderService $nphOrderService)
    {
        $this->em = $em;
        $this->nphOrderService = $nphOrderService;
    }

    public function backfillNphDlw()
    {
        $dlws = $this->em->getRepository(NphDlw::class)->getDlwWithMissingRdrId(25);
        foreach ($dlws as $dlw) {
            $this->nphOrderService->saveDlwCollection($dlw, $dlw->getNphParticipant(), $dlw->getModule(), $dlw->getVisitPeriod(), false);
        }
    }
}
