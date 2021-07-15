<?php

namespace App\Controller;

use App\Service\HelpService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Pmi\HttpClient;

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
}
