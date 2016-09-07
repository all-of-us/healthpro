<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class GoogleGroupsController extends AbstractController
{
    protected static $name = 'googlegroups';
    
    protected static $routes = [
        ['home', '/']
    ];
    
    public function homeAction(Application $app, Request $request)
    {
        $token = $app['security.token_storage']->getToken();
        $user = $token->getUser();
        $groups = $app['pmi.drc.appsclient']->getGroups($user->getEmail());
        
        $groupNames = [];
        foreach ($groups as $group) {
            $groupNames[] = $group->getName();
        }
        return $app['twig']->render('googlegroups.html.twig', [
            'groupNames' => $groupNames
        ]);
    }
}
