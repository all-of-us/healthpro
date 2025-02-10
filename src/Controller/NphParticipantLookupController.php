<?php

namespace App\Controller;

use App\Drc\Exception\ParticipantSearchExceptionInterface;
use App\Form\ParticipantLookupBiobankIdType;
use App\Form\ParticipantLookupEmailType;
use App\Form\ParticipantLookupIdType;
use App\Form\ParticipantLookupSearchType;
use App\Form\ParticipantLookupTelephoneType;
use App\Service\Nph\NphParticipantSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class NphParticipantLookupController extends BaseController
{
    protected $nphParticipantSummaryService;

    public function __construct(EntityManagerInterface $em, NphParticipantSummaryService $nphParticipantSummaryService)
    {
        parent::__construct($em);
        $this->nphParticipantSummaryService = $nphParticipantSummaryService;
    }

    #[Route(path: '/nph/participants', name: 'nph_participants')]
    public function participantsAction(Request $request, ParameterBagInterface $params)
    {
        $idForm = $this->createForm(ParticipantLookupIdType::class, null, ['placeholder' => '10000000000000']);
        $idForm->handleRequest($request);

        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $id = $idForm->get('participantId')->getData();
            $participant = $this->nphParticipantSummaryService->getParticipantById($id);
            if ($participant) {
                return $this->redirectToRoute('nph_participant_summary', ['participantId' => $id]);
            }
            $this->addFlash('error', 'Participant ID not found');
        }

        $bioBankIdPrefix = $params->has('nph_biobank_id_prefix') ? $params->get('nph_biobank_id_prefix') : null;
        $biobankIdForm = $this->createForm(ParticipantLookupBiobankIdType::class, null, ['bioBankIdPrefix' =>
            $bioBankIdPrefix]);
        $biobankIdForm->handleRequest($request);
        if ($biobankIdForm->isSubmitted() && $biobankIdForm->isValid()) {
            $searchParameters = $biobankIdForm->getData();
            $searchResults = $this->nphParticipantSummaryService->search($searchParameters);
            if (!empty($searchResults)) {
                return $this->redirectToRoute('nph_participant_summary', [
                    'participantId' => $searchResults[0]->id
                ]);
            }
            $this->addFlash('error', 'Biobank ID not found');
        }

        $emailForm = $this->createForm(ParticipantLookupEmailType::class, null);
        $emailForm->handleRequest($request);

        if ($emailForm->isSubmitted() && $emailForm->isValid()) {
            $searchParameters = $emailForm->getData();
            try {
                $searchResults = $this->nphParticipantSummaryService->search($searchParameters);
                if (count($searchResults) == 1) {
                    return $this->redirectToRoute('nph_participant_summary', [
                        'participantId' => $searchResults[0]->id
                    ]);
                }
                return $this->render('program/nph/participantlookup/participants-list.html.twig', [
                    'participants' => $searchResults
                ]);
            } catch (ParticipantSearchExceptionInterface $e) {
                $emailForm->addError(new FormError($e->getMessage()));
            }
        }

        $phoneForm = $this->createForm(ParticipantLookupTelephoneType::class, null);
        $phoneForm->handleRequest($request);

        if ($phoneForm->isSubmitted() && $phoneForm->isValid()) {
            $searchParameters = $phoneForm->getData();
            try {
                $searchResults = $this->nphParticipantSummaryService->search($searchParameters);
                if (count($searchResults) == 1) {
                    return $this->redirectToRoute('nph_participant_summary', [
                        'participantId' => $searchResults[0]->id
                    ]);
                }
                return $this->render('program/nph/participantlookup/participants-list.html.twig', [
                    'participants' => $searchResults
                ]);
            } catch (ParticipantSearchExceptionInterface $e) {
                $emailForm->addError(new FormError($e->getMessage()));
            }
        }

        $searchForm = $this->createForm(ParticipantLookupSearchType::class, null);
        $searchForm->handleRequest($request);

        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchParameters = $searchForm->getData();
            try {
                $searchResults = $this->nphParticipantSummaryService->search($searchParameters);
                return $this->render('program/nph/participantlookup/participants-list.html.twig', [
                    'participants' => $searchResults
                ]);
            } catch (ParticipantSearchExceptionInterface $e) {
                $searchForm->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('program/nph/participantlookup/participants.html.twig', [
            'searchForm' => $searchForm->createView(),
            'idForm' => $idForm->createView(),
            'emailForm' => $emailForm->createView(),
            'phoneForm' => $phoneForm->createView(),
            'biobankIdForm' => $biobankIdForm->createView(),
        ]);
    }
}
