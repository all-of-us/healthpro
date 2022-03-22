<?php

namespace App\Controller;

use App\Form\GroupMemberType;
use App\Form\RemoveGroupMemberType;
use App\Service\AccessManagementService;
use App\Service\GoogleGroupsService;
use App\Service\LoggerService;
use App\Audit\Log;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @Route("/access/manage")
 */
class AccessManagementController extends AbstractController
{
    public const MEMBER_DOMAIN = '@pmi-ops.org';
    public const RESET_PASSWORD_URL = 'https://admin.google.com';

    private $googleGroupsService;
    private $loggerService;

    public function __construct(GoogleGroupsService $googleGroupsService, LoggerService $loggerService)
    {
        $this->googleGroupsService = $googleGroupsService;
        $this->loggerService = $loggerService;
    }

    /**
     * @Route("/dashboard", name="access_manage_dashboard")
     */
    public function index()
    {
        return $this->render('accessmanagement/dashboard.html.twig');
    }

    /**
     * @Route("/user/groups", name="access_manage_user_groups")
     */
    public function userGroups()
    {
        return $this->render('accessmanagement/groups.html.twig');
    }

    /**
     * @Route("/user/group/{groupId}", name="access_manage_user_group")
     */
    public function userGroup($groupId)
    {
        $group = $this->getUser()->getGroupFromId($groupId);
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
            'resetPasswordUrl' => self::RESET_PASSWORD_URL
        ]);
    }

    /**
     * @Route("/user/group/{groupId}/member", name="access_manage_user_group_member")
     */
    public function member($groupId, Request $request)
    {
        $group = $this->getUser()->getGroupFromId($groupId);
        if (empty($group)) {
            throw $this->createNotFoundException();
        }
        $groupMemberForm = $this->createForm(GroupMemberType::class);
        $groupMemberForm->handleRequest($request);
        if ($groupMemberForm->isSubmitted()) {
            if ($groupMemberForm->isValid()) {
                $email = $groupMemberForm->get('email')->getData() . self::MEMBER_DOMAIN;
                if ($this->googleGroupsService->getUser($email)) {
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
            'groupMemberForm' => $groupMemberForm->createView()
        ]);
    }

    /**
     * @Route("/user/group/{groupId}/member/{memberId}/remove", name="access_manage_user_group_remove_member")
     */
    public function removeMember($groupId, $memberId, Request $request, AccessManagementService $accessManagementService)
    {
        $group = $this->getUser()->getGroupFromId($groupId);
        if (empty($group)) {
            throw $this->createNotFoundException();
        }
        $member = $this->googleGroupsService->getMemberById($group->email, $memberId);
        if (empty($member) || $member->getRole() !== 'MEMBER') {
            throw $this->createNotFoundException();
        }
        $removeGoupMemberForm = $this->createForm(RemoveGroupMemberType::class);
        $removeGoupMemberForm->handleRequest($request);
        if ($removeGoupMemberForm->isSubmitted()) {
            if ($removeGoupMemberForm->isValid()) {
                $confirm = $removeGoupMemberForm->get('confirm')->getData();
                if ($confirm === 'yes') {
                    $result = $this->googleGroupsService->removeMember($group->email, $member->email);
                    if ($result['status'] === 'success') {
                        if ($removeGoupMemberForm->get('reason')->getData() === 'no') {
                            $currentTime = new \DateTime("now");
                            $attestation = array_search($removeGoupMemberForm->get('attestation')->getData(), RemoveGroupMemberType::ATTESTATIONS);
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
}
