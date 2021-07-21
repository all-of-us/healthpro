<?php

namespace App\Controller;

use App\Form\GroupMemberType;
use App\Form\RemoveGroupMemberType;
use App\Service\GoogleGroupsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/s/access/manage")
 */
class AccessManagementController extends AbstractController
{
    const MEMBER_DOMAIN = '@pmi-ops.org';

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
    public function userGroup($groupId, GoogleGroupsService $googleGroupsService)
    {
        $group = $this->getUser()->getSiteFromId($groupId);
        if (empty($group)) {
            throw $this->createNotFoundException();
        }
        $members = $googleGroupsService->getMembers($group->email);
        return $this->render('accessmanagement/group-members.html.twig', [
            'group' => $group,
            'members' => $members
        ]);
    }

    /**
     * @Route("/user/group/{groupId}/member", name="access_manage_user_group_member")
     */
    public function member($groupId, Request $request, GoogleGroupsService $googleGroupsService)
    {
        $group = $this->getUser()->getSiteFromId($groupId);
        if (empty($group)) {
            throw $this->createNotFoundException();
        }
        $groupMemberForm = $this->createForm(GroupMemberType::class);
        $groupMemberForm->handleRequest($request);
        if ($groupMemberForm->isSubmitted()) {
            if ($groupMemberForm->isValid()) {
                $email = $groupMemberForm->get('email')->getData() . self::MEMBER_DOMAIN;
                $result = $googleGroupsService->addMember($group->email, $email);
                if ($result['status'] === 'success') {
                    $this->addFlash('success', $result['message']);
                    return $this->redirectToRoute('access_manage_user_group', ['groupId' => $groupId]);
                }
                $this->addFlash('error', $result['message']);
            } else {
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
    public function removeMember($groupId, $memberId, Request $request, GoogleGroupsService $googleGroupsService)
    {
        $group = $this->getUser()->getSiteFromId($groupId);
        if (empty($group)) {
            throw $this->createNotFoundException();
        }
        $member = $googleGroupsService->getMemberById($group->email, $memberId);
        if (empty($member)) {
            throw $this->createNotFoundException();
        }
        $removeGoupMemberForm = $this->createForm(RemoveGroupMemberType::class);
        $removeGoupMemberForm->handleRequest($request);
        if ($removeGoupMemberForm->isSubmitted()) {
            if ($removeGoupMemberForm->isValid()) {
                $confirm = $removeGoupMemberForm->get('confirm')->getData();
                if ($confirm === 'yes') {
                    $result = $googleGroupsService->removeMember($group->email, $member->email);
                    if ($result['status'] === 'success') {
                        $this->addFlash('success', $result['message']);
                        return $this->redirectToRoute('access_manage_user_group', ['groupId' => $groupId]);
                    }
                    $this->addFlash('error', $result['message']);
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
