<?php

namespace App\Controller;

use App\Audit\Log;
use App\Entity\Problem;
use App\Entity\ProblemComment;
use App\Form\ProblemCommentType;
use App\Form\ProblemType;
use App\Repository\ProblemCommentRepository;
use App\Repository\ProblemRepository;
use App\Service\LoggerService;
use App\Service\ParticipantSummaryService;
use App\Service\ProblemNotificationService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProblemController extends BaseController
{
    protected $logger;
    protected $params;
    protected $problemNotificationService;
    protected $siteService;

    public function __construct(
        LoggerService $loggerService,
        ParameterBagInterface $parameterBag,
        EntityManagerInterface $em,
        ProblemNotificationService $problemNotificationService,
        SiteService $siteService
    ) {
        parent::__construct($em);
        $this->logger = $loggerService;
        $this->params = $parameterBag;
        $this->problemNotificationService = $problemNotificationService;
        $this->siteService = $siteService;
    }

    /**
     * @Route("/problem/reports", name="problem_reports")
     * IsGranted("ROLE_DV_ADMIN")
     */
    public function reports(Request $request, ProblemRepository $problemRepository): Response
    {
        $problems = $problemRepository->getProblemsWithCommentsCount();

        return $this->render('problem/problem-reports.html.twig', [
            'problems' => $problems,
            'optionsValue' => [Problem::RELATED_BASELINE, Problem::UNRELATED_BASELINE, Problem::OTHER],
            'optionsText' => Problem::PROBLEM_TYPE_OPTIONS
        ]);
    }

    /**
     * @Route("/problem/details/{problemId}", name="problem_details")
     * IsGranted("ROLE_DV_ADMIN")
     */
    public function detail($problemId, Request $request, ProblemRepository $problemRepository, ProblemCommentRepository $problemCommentRepository): Response
    {
        $problem = $problemRepository->find($problemId);
        if (!$problem) {
            throw $this->createNotFoundException('Problem report not found.');
        }
        if ($problem->getProblemType() === Problem::RELATED_BASELINE) {
            $problem->setProblemType(Problem::PROBLEM_TYPE_OPTIONS[0]);
        } elseif ($problem->getProblemType() === Problem::UNRELATED_BASELINE) {
            $problem->setProblemType(Problem::PROBLEM_TYPE_OPTIONS[1]);
        } else {
            $problem->setProblemType(Problem::PROBLEM_TYPE_OPTIONS[2]);
        }

        $comments = $problemCommentRepository->findBy(['problem' => $problem], ['createdTs' => 'ASC']);

        return $this->render('problem/problem-details.html.twig', [
            'problem' => $problem,
            'comments' => $comments
        ]);
    }

    /**
     * @Route("/participant/{participantId}/problem", name="problem_form_new")
     * @Route("/participant/{participantId}/problem/{problemId}", name="problem_form")
     */
    public function problemForm($participantId, $problemId = null, Request $request, ParticipantSummaryService $participantSummaryService, ProblemRepository $problemRepository, ProblemCommentRepository $problemCommentRepository): Response
    {
        $formDisabled = false;
        $enableConstraints = false;

        if (!$this->siteService->isDvType()) {
            throw $this->createAccessDeniedException('Site is not a DV.');
        }
        $participant = $participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        if (!$participant->status || $this->siteService->isTestSite()) {
            throw $this->createAccessDeniedException('Participant ineligible for problem report.');
        }
        if ($problemId) {
            $problem = $problemRepository->findOneBy(['id' => $problemId, 'participantId' => $participantId]);
            if (!$problem) {
                throw $this->createNotFoundException('Problem report not found.');
            }
            if (!empty($problem->getFinalizedTs())) {
                $formDisabled = true;
            }
        } else {
            $problem = new Problem();
        }
        if ($request->request->has('reportable_finalize')) {
            $enableConstraints = true;
        }

        $problemForm = $this->createForm(ProblemType::class, $problem, ['formDisabled' => $formDisabled, 'enableConstraints' => $enableConstraints, 'user' => $this->getSecurityUser()]);
        $problemForm->handleRequest($request);
        if ($problemForm->isSubmitted()) {
            if ($problemForm->isValid()) {
                $problem = $problemForm->getData();
                $now = new \DateTime();
                $problem->setUpdatedTs($now);
                if ($request->request->has('reportable_finalize') && (!$problem || empty($problem->getFinalizedTs()))) {
                    $problem->setFinalizedUserId($this->getSecurityUser()->getId());
                    $problem->setFinalizedSite($this->siteService->getSiteId());
                    $problem->setFinalizedTs($now);
                }
                // Finalize an existing report
                if ($problem->getId() && empty($problem->getFinalizedTs())) {
                    try {
                        $this->em->persist($problem);
                        $this->em->flush();
                        $this->logger->log(Log::PROBLEM_EDIT, $problem->getId());
                        if ($request->request->has('reportable_finalize')) {
                            $this->addFlash('notice', 'Report finalized.');
                            $this->problemNotificationService->sendProblemReportEmail($problem->getId());
                        } else {
                            $this->addFlash('notice', 'Report updated.');
                        }
                    } catch (\Exception $e) {
                        $this->addFlash('error', 'Failed to finalize report.');
                    }
                    // Create a new report (optionally finalize at creation)
                } else {
                    $problem->setUserId($this->getSecurityUser()->getId());
                    $problem->setSite($this->siteService->getSiteId());
                    $problem->setParticipantId($participantId);
                    try {
                        $this->em->persist($problem);
                        $this->em->flush();
                        $this->logger->log(Log::PROBLEM_CREATE, $problem->getId());
                        if ($request->request->has('reportable_finalize')) {
                            $this->addFlash('notice', 'Report finalized.');
                            $this->problemNotificationService->sendProblemReportEmail($problem->getId());
                        } else {
                            $this->addFlash('notice', 'Report saved.');
                        }
                    } catch (\Exception $e) {
                        $this->addFlash('error', 'Failed to create new report.');
                    }
                }
                return $this->redirectToRoute('participant', [
                    'id' => $participantId
                ]);
            }
            if (count($problemForm->getErrors()) == 0) {
                $problemForm->addError(new FormError('Please correct the errors below.'));
            }
        }
        if (!empty($problem->getFinalizedTs())) {
            $problemComment = new ProblemComment();
            $problemCommentForm = $this->createForm(ProblemCommentType::class, $problemComment);
            $problemCommentForm = $problemCommentForm->createView();
            $problemComments = $problemCommentRepository->findBy(['problem' => $problem], ['createdTs' => 'ASC']);
        } else {
            $problemCommentForm = null;
            $problemComments = null;
        }

        return $this->render('problem/problem.html.twig', [
            'problem' => $problem,
            'participant' => $participant,
            'problemForm' => $problemForm->createView(),
            'problemCommentForm' => $problemCommentForm,
            'problemComments' => $problemComments
        ]);
    }

    /**
     * @Route("/participant/{participantId}/problem/{problemId}/comment", name="problem_comment", methods={"POST"})
     */
    public function problemComment($participantId, $problemId, Request $request, LoggerService $loggerService, ParticipantSummaryService $participantSummaryService, ProblemRepository $problemRepository): Response
    {
        if (!$this->siteService->isDvType()) {
            throw $this->createAccessDeniedException('Site is not a DV.');
        }
        $participant = $participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        if (!$participant->status || $this->siteService->isTestSite()) {
            throw $this->createAccessDeniedException('Participant ineligible for problem report.');
        }
        $problem = $problemRepository->findOneBy(['id' => $problemId, 'participantId' => $participantId]);
        if (is_null($problem)) {
            throw $this->createNotFoundException('Problem report not found.');
        }

        $problemComment = new ProblemComment();
        $problemCommentForm = $this->createForm(ProblemCommentType::class, $problemComment);
        $problemCommentForm->handleRequest($request);
        if ($problemCommentForm->isSubmitted()) {
            if ($problemCommentForm->isValid()) {
                $problemComment = $problemCommentForm->getData();
                $problemComment->setProblem($problem);
                $problemComment->setUserId($this->getSecurityUser()->getId());
                $problemComment->setSite($this->siteService->getSiteId());
                try {
                    $this->em->persist($problemComment);
                    $this->em->flush();
                    $loggerService->log(Log::PROBLEM_COMMENT_CREATE, $problemComment->getId());
                    $this->addFlash('notice', 'Comment saved.');
                    $this->problemNotificationService->sendProblemReportEmail($problem->getId());
                    return $this->redirectToRoute('participant', [
                        'id' => $participantId
                    ]);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Failed to add comment to problem report.');
                }
            }
        }
        $this->addFlash('error', 'Failed to create new comment.');
        return $this->redirectToRoute('problem_form', ['participantId' => $participantId, 'problemId' => $problem->getId()]);
    }
}
