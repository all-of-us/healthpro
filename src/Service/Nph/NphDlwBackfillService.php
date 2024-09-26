<?php

namespace App\Service\Nph;

use App\Entity\NphDlw;
use App\Repository\NphDlwRepository;
use Doctrine\ORM\EntityManagerInterface;

class NphDlwBackfillService
{
    private \App\Service\Nph\NphOrderService $nphOrderService;

    public function __construct(EntityManagerInterface $em, NphDlwRepository $dlwRepository, NphOrderService $nphOrderService)
    {
        $this->em = $em;
        $this->NphDlwRepository = $dlwRepository;
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
