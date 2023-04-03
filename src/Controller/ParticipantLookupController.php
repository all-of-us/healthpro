<?php

namespace App\Controller;

use App\Drc\Exception\ParticipantSearchExceptionInterface;
use App\Form\ParticipantLookupEmailType;
use App\Form\ParticipantLookupIdType;
use App\Form\ParticipantLookupSearchType;
use App\Form\ParticipantLookupTelephoneType;
use App\Service\ParticipantSummaryService;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ParticipantLookupController extends BaseController
{
    protected $participantSummaryService;

    public function __construct(ParticipantSummaryService $participantSummaryService)
    {
        $this->participantSummaryService = $participantSummaryService;
    }

    /**
     * @Route("/participants", name="participants")
     * @Route("/read/participants", name="read_participants")
     */
    public function participantsAction(Request $request)
    {
        $redirectRoute = $this->isReadOnly() ? 'read_participant' : 'participant';
        $idForm = $this->createForm(ParticipantLookupIdType::class, null);
        $idForm->handleRequest($request);

        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $id = $idForm->get('participantId')->getData();
            $participant = $this->participantSummaryService->getParticipantById($id);
            if ($participant) {
                return $this->redirectToRoute($redirectRoute, ['id' => $id]);
            }
            $this->addFlash('error', 'Participant ID not found');
        }

        $emailForm = $this->createForm(ParticipantLookupEmailType::class, null);
        $emailForm->handleRequest($request);

        if ($emailForm->isSubmitted() && $emailForm->isValid()) {
            $searchParameters = $emailForm->getData();
            try {
                $searchResults = $this->participantSummaryService->search($searchParameters);
                if (count($searchResults) == 1) {
                    return $this->redirectToRoute($redirectRoute, [
                        'id' => $searchResults[0]->id
                    ]);
                }
                return $this->render('participantlookup/participants-list.html.twig', [
                    'participants' => $searchResults
                ]);
            } catch (ParticipantSearchExceptionInterface $e) {
                $emailForm->addError(new FormError($e->getMessage()));
            }
        }

        $phoneForm = $this->createForm(ParticipantLookupTelephoneType::class, null);
        $phoneForm->handleRequest($request);

        if ($phoneForm->isSubmitted() && $phoneForm->isValid()) {
            $searchFields = ['loginPhone', 'phone'];
            $searchResults = [];

            foreach ($searchFields as $field) {
                try {
                    $results = $this->participantSummaryService->search([$field => $phoneForm['phone']->getData()]);
                    if (!empty($results)) {
                        foreach ($results as $result) {
                            // Check for duplicates
                            if (isset($searchResults[$result->id])) {
                                continue;
                            }
                            // Set search field type
                            $result->searchField = $field;
                            $searchResults[$result->id] = $result;
                        }
                    }
                } catch (ParticipantSearchExceptionInterface $e) {
                    $phoneForm->addError(new FormError($e->getMessage()));
                }
            }
            return $this->render('participantlookup/participants-list.html.twig', [
                'participants' => $searchResults,
                'searchType' => 'phone'
            ]);
        }

        $searchForm = $this->createForm(ParticipantLookupSearchType::class, null);
        $searchForm->handleRequest($request);

        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchParameters = $searchForm->getData();
            try {
                $searchResults = $this->participantSummaryService->search($searchParameters);
                return $this->render('participantlookup/participants-list.html.twig', [
                    'participants' => $searchResults
                ]);
            } catch (ParticipantSearchExceptionInterface $e) {
                $searchForm->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('participantlookup/participants.html.twig', [
            'searchForm' => $searchForm->createView(),
            'idForm' => $idForm->createView(),
            'emailForm' => $emailForm->createView(),
            'phoneForm' => $phoneForm->createView()
        ]);
    }
}
