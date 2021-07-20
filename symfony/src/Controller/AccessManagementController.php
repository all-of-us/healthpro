<?php

namespace App\Controller;

use App\Form\GroupMemberType;
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
                $email = $groupMemberForm->get('email')->getData();
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
}
