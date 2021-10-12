<?php

namespace App\Controller;

use App\Form\ParticipantLookupEmailType;
use App\Form\ParticipantLookupIdType;
use App\Form\ParticipantLookupSearchType;
use App\Form\ParticipantLookupTelephoneType;
use App\Service\ParticipantSummaryService;
use App\Service\ReviewService;
use App\Drc\Exception\FailedRequestException;
use App\Drc\Exception\ParticipantSearchExceptionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints;

/**
 * @Route("/s/participants")
 */
class ParticipantLookupController extends AbstractController
{
    protected $participantSummaryService;

    public function __construct(ParticipantSummaryService $participantSummaryService)
    {
        $this->participantSummaryService = $participantSummaryService;
    }

    /**
     * @Route("/", name="participants")
     */
    public function participantsAction(Request $request)
    {
        $idForm = $this->createForm(ParticipantLookupIdType::class, null);
        $idForm->handleRequest($request);

        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $id = $idForm->get('participantId')->getData();
            $participant = $this->participantSummaryService->getParticipantById($id);
            if ($participant) {
                return $this->redirectToRoute('participant', ['id' => $id]);
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
                    return $this->redirectToRoute('participant', [
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
