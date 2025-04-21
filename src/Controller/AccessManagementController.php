<?php

namespace App\Controller;

use App\Audit\Log;
use App\Entity\NphOrder;
use App\Form\GroupMemberType;
use App\Form\Nph\NphOrderCollect;
use App\Form\OrderLookupIdType;
use App\Form\RemoveGroupMemberType;
use App\Service\AccessManagementService;
use App\Service\ContextTemplateService;
use App\Service\GoogleGroupsService;
use App\Service\LoggerService;
use App\Service\Nph\NphOrderService;
use App\Service\Nph\NphParticipantSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/nph/access/manage')]
class AccessManagementController extends BaseController
{
    public const MEMBER_DOMAIN = '@pmi-ops.org';
    public const GOOGLE_ADMIN_URL = 'https://admin.google.com';

    private $googleGroupsService;
    private $loggerService;
    private $contextTemplate;

    public function __construct(
        GoogleGroupsService $googleGroupsService,
        LoggerService $loggerService,
        EntityManagerInterface $em,
        ContextTemplateService $contextTemplate
    ) {
        parent::__construct($em);
        $this->googleGroupsService = $googleGroupsService;
        $this->loggerService = $loggerService;
        $this->contextTemplate = $contextTemplate;
    }

    #[Route(path: '/dashboard', name: 'access_manage_dashboard')]
    public function index()
    {
        return $this->render('accessmanagement/dashboard.html.twig');
    }

    #[Route(path: '/user/groups', name: 'access_manage_user_groups')]
    public function userGroups()
    {
        return $this->render($this->contextTemplate->GetProgramTemplate('accessmanagement/groups.html.twig'));
    }

    #[Route(path: '/user/group/{groupId}', name: 'access_manage_user_group')]
    public function userGroup($groupId): Response
    {
        if ($this->contextTemplate->isCurrentProgramHpo()) {
            $group = $this->getSecurityUser()->getGroupFromId($groupId);
        } elseif ($this->contextTemplate->isCurrentProgramNph()) {
            $group = $this->getSecurityUser()->getGroupFromId($groupId, 'nphSites');
        }
        if (empty($group)) {
            throw $this->createNotFoundException();
        }
        try {
            $members = $this->googleGroupsService->getMembers($group->email);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Unable to retrieve member list for group.');
            $members = [];
        }
        return $this->render('accessmanagement/group-members.html.twig', [
            'group' => $group,
            'members' => $members,
            'resetPasswordUrl' => self::GOOGLE_ADMIN_URL
        ]);
    }

    #[Route(path: '/user/group/{groupId}/member', name: 'access_manage_user_group_member')]
    public function member($groupId, Request $request)
    {
        if ($this->contextTemplate->isCurrentProgramHpo()) {
            $group = $this->getSecurityUser()->getGroupFromId($groupId);
        } elseif ($this->contextTemplate->isCurrentProgramNph()) {
            $group = $this->getSecurityUser()->getGroupFromId($groupId, 'nphSites');
        }
        if (empty($group)) {
            throw $this->createNotFoundException();
        }
        $groupMemberForm = $this->createForm(GroupMemberType::class);
        $groupMemberForm->handleRequest($request);
        if ($groupMemberForm->isSubmitted()) {
            if ($groupMemberForm->isValid()) {
                $email = $groupMemberForm->get('email')->getData() . self::MEMBER_DOMAIN;
                if ($this->googleGroupsService->getUser($email)) {
                    if (!$this->googleGroupsService->isMfaGroupUser($email)) {
                        $result = $this->googleGroupsService->addMember($group->email, $email);
                        if ($result['status'] === 'success') {
                            $this->addFlash('success', $result['message']);
                            $this->loggerService->log(Log::GROUP_MEMBER_ADD, [
                                'member' => $email,
                                'result' => 'success'
                            ]);
                            return $this->redirectToRoute('access_manage_user_group', ['groupId' => $groupId]);
                        }
                        $errorMessage = isset($result['code']) && $result['code'] === 409 ? 'Member already exists.' : 'Error occurred. Please try again.';
                    } else {
                        $errorMessage = 'User cannot be added until 2FA is enabled. Please check the user’s 2FA status in the Admin Console before contacting drcsupport@vumc.org for assistance.';
                    }
                } else {
                    $errorMessage = 'User does not exist.';
                }
                $groupMemberForm['email']->addError(new FormError($errorMessage));
                $this->loggerService->log(Log::GROUP_MEMBER_ADD, [
                    'member' => $email,
                    'result' => 'fail',
                    'errorMessage' => $result['message'] ?? $errorMessage
                ]);
            }
            if (!$groupMemberForm->isValid()) {
                $groupMemberForm->addError(new FormError('Please correct the errors below.'));
            }
        }
        return $this->render('accessmanagement/add-member.html.twig', [
            'group' => $group,
            'groupMemberForm' => $groupMemberForm->createView(),
            'adminConsoleUrl' => self::GOOGLE_ADMIN_URL
        ]);
    }

    #[Route(path: '/user/group/{groupId}/member/{memberId}/remove', name: 'access_manage_user_group_remove_member')]
    public function removeMember($groupId, $memberId, Request $request, AccessManagementService $accessManagementService)
    {
        if ($this->contextTemplate->isCurrentProgramHpo()) {
            $group = $this->getSecurityUser()->getGroupFromId($groupId);
        } elseif ($this->contextTemplate->isCurrentProgramNph()) {
            $group = $this->getSecurityUser()->getGroupFromId($groupId, 'nphSites');
        }
        if (empty($group)) {
            throw $this->createNotFoundException();
        }
        $member = $this->googleGroupsService->getMemberById($group->email, $memberId);
        if (empty($member) || $member->getRole() !== 'MEMBER') {
            throw $this->createNotFoundException();
        }
        $removeGoupMemberForm = $this->createForm(RemoveGroupMemberType::class, null, ['programDisplayText' => $this->contextTemplate->getCurrentProgramDisplayText()]);
        $removeGoupMemberForm->handleRequest($request);
        if ($removeGoupMemberForm->isSubmitted()) {
            if ($removeGoupMemberForm->isValid()) {
                $confirm = $removeGoupMemberForm->get('confirm')->getData();
                if ($confirm === 'yes') {
                    $result = $this->googleGroupsService->removeMember($group->email, $member->email);
                    if ($result['status'] === 'success') {
                        if ($removeGoupMemberForm->get('reason')->getData() === 'no') {
                            $currentTime = new \DateTime('now');
                            $attestation = array_search($removeGoupMemberForm->get('attestation')->getData(), RemoveGroupMemberType::EMAIL_ATTESTATIONS);
                            $accessManagementService->sendEmail($group->email, $member->email, $removeGoupMemberForm->get('memberLastDay')->getData(), $currentTime, $attestation);
                        }
                        $this->addFlash('success', $result['message']);
                        $this->loggerService->log(Log::GROUP_MEMBER_REMOVE, [
                            'member' => $member->email,
                            'result' => 'success'
                        ]);
                        return $this->redirectToRoute('access_manage_user_group', ['groupId' => $groupId]);
                    }
                    $this->addFlash('error', 'Error occurred. Please try again.');
                    $this->loggerService->log(Log::GROUP_MEMBER_REMOVE, [
                        'member' => $member->email,
                        'result' => 'fail',
                        'errorMessage' => $result['message']
                    ]);
                } else {
                    $this->addFlash('notice', 'Member not deleted.');
                    return $this->redirectToRoute('access_manage_user_group', ['groupId' => $groupId]);
                }
            } else {
                $removeGoupMemberForm->addError(new FormError('Please correct the errors below.'));
            }
        }
        return $this->render('accessmanagement/remove-member.html.twig', [
            'group' => $group,
            'member' => $member,
            'removeGoupMemberForm' => $removeGoupMemberForm->createView()
        ]);
    }

    #[Route(path: '/order/manage/{orderId}', name: 'order_manage', defaults: ['orderId' => null])]
    public function orderManageAction(?string $orderId, Request $request, NphParticipantSummaryService $nphParticipantSummaryService, NphOrderService $nphOrderService): Response
    {
        $idForm = $this->createForm(OrderLookupIdType::class, null);
        $idForm->handleRequest($request);
        if ($orderId) {
            $order = $this->em->getRepository(NphOrder::class)->findOneBy([
                'orderId' => $orderId
            ]);
            $participant = $nphParticipantSummaryService->getParticipantById($order->getParticipantId());
            if (!$participant) {
                throw $this->createNotFoundException('Participant not found.');
            }

            $nphOrderService->loadModules(
                $order->getModule(),
                $order->getVisitPeriod(),
                $participant->id,
                $participant->biobankId
            );
            $sampleLabelsIds = $nphOrderService->getSamplesWithLabelsAndIds($order->getNphSamples());
            $orderCollectionData = $nphOrderService->getExistingOrderCollectionData($order);
            $oderCollectForm = $this->createForm(
                NphOrderCollect::class,
                $orderCollectionData,
                ['samples' => $sampleLabelsIds, 'orderType' => $order->getOrderType(), 'timeZone' =>
                    $this->getSecurityUser()->getTimezone(), 'disableMetadataFields' => $order->isMetadataFieldDisabled()
                    , 'disableStoolCollectedTs' => $order->isStoolCollectedTsDisabled(), 'orderCreatedTs' => $order->getCreatedTs()]
            );
            $oderCollectForm->handleRequest($request);
            if ($oderCollectForm->isSubmitted()) {
                $formData = $oderCollectForm->getData();
                if ($nphOrderService->isAtLeastOneSampleChecked($formData, $order) === false) {
                    $oderCollectForm['samplesCheckAll']->addError(new FormError('Please select at least one sample'));
                }
                if ($oderCollectForm->isValid()) {
                    if ($nphOrderService->saveOrderCollection($formData, $order)) {
                        $this->addFlash('success', 'Order collection saved');
                    } else {
                        $this->addFlash('error', 'Order collection failed');
                    }
                } else {
                    $oderCollectForm->addError(new FormError('Please correct the errors below'));
                }
            }

            return $this->render('accessmanagement/order-manage.html.twig', [
                'idForm' => $idForm->createView(),
                'order' => $order,
                'participant' => $participant,
                'orderCollectForm' => $oderCollectForm->createView(),
                'timePoints' => $nphOrderService->getTimePoints(),
                'samples' => $nphOrderService->getSamples()
            ]);
        }
        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $orderId = $idForm->get('orderId')->getData();
            $order = $this->em->getRepository(NphOrder::class)->findOneBy([
                'orderId' => $orderId
            ]);
            if ($order) {
                return $this->redirectToRoute('order_manage', ['orderId' => $orderId]);
            }
            $this->addFlash('error', 'Order ID not found');
        }
        return $this->render('accessmanagement/order-manage.html.twig', ['idForm' => $idForm->createView()]);
    }
}
