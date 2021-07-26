<?php

namespace App\Controller;

use App\Service\GoogleGroupsService;
use App\Service\SiteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
}
