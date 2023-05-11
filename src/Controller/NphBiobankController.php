<?php

namespace App\Controller;

use App\Form\ParticipantLookupBiobankIdType;
use App\Service\Nph\NphParticipantSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/nph/biobank")
 */
class NphBiobankController extends BaseController
{
    protected NphParticipantSummaryService $nphParticipantSummaryService;
    protected ParameterBagInterface $params;

    public function __construct(
        EntityManagerInterface $em,
        NphParticipantSummaryService $nphParticipantSummaryService,
        ParameterBagInterface $params
    ) {
        parent::__construct($em);
        $this->nphParticipantSummaryService = $nphParticipantSummaryService;
        $this->params = $params;
    }

    /**
     * @Route("/", name="nph_biobank_home")
     */
    public function indexAction(): Response
    {
        return $this->render('program/nph/biobank/index.html.twig');
    }

    /**
     * @Route("/participants", name="nph_biobank_participants")
     */
    public function participantsAction(Request $request): Response
    {
        $bioBankIdPrefix = $this->params->has('nph_biobank_id_prefix') ? $this->params->get('nph_biobank_id_prefix') : null;
        $idForm = $this->createForm(ParticipantLookupBiobankIdType::class, null, ['bioBankIdPrefix' => $bioBankIdPrefix]);
        $idForm->handleRequest($request);
        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $searchParameters = $idForm->getData();
            $searchResults = $this->nphParticipantSummaryService->search($searchParameters);
            if (!empty($searchResults)) {
                return $this->redirectToRoute('nph_biobank_participant', [
                    'biobankId' => $searchResults[0]->biobankId
                ]);
            }
            $this->addFlash('error', 'Biobank ID not found');
        }
        return $this->render('program/nph/biobank/participants.html.twig', [
            'idForm' => $idForm->createView()
        ]);
    }

    /**
     * @Route("/{biobankId}", name="nph_biobank_participant")
     */
    public function participantAction(string $biobankId): Response
    {
        //TODO
        return '';
    }
}
