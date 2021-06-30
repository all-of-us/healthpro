<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Pmi\Audit\Log;

class DefaultController extends AbstractController
{
    protected static $routes = [
        ['dashSplash', '/splash'],
        ['logout', '/logout'],
        ['login', '/login'],
        ['loginReturn', '/login-return'],
        ['timeout', '/timeout'],
        ['keepAlive', '/keepalive', [ 'method' => 'POST' ]],
        ['clientTimeout', '/client-timeout', [ 'method' => 'GET' ]],
        ['agreeUsage', '/agree', ['method' => 'POST']],
        ['groups', '/groups'],
        ['hideTZWarning', '/hide-tz-warning', ['method' => 'POST']]
    ];

    public function dashSplashAction(Application $app)
    {
        return $app['twig']->render('dash-splash.html.twig');
    }

    public function logoutAction(Application $app, Request $request)
    {
        $timeout = $request->get('timeout');
        $app->log(Log::LOGOUT);
        $app->logout();
        return $app->redirect($app->getGoogleLogoutUrl($timeout ? 'timeout' : 'home'));
    }

    public function loginReturnAction(Application $app)
    {
        //$app['session']->set('isLoginReturn', true);
        $url = $app['session']->get('loginDestUrl', $app->generateUrl('home'));
        return $app->redirect($url);
    }

    public function timeoutAction(Application $app)
    {
        return $app['twig']->render('timeout.html.twig');
    }

    /** Dummy action that serves to extend the user's session. */
    public function keepAliveAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('keepAlive', $request->get('csrf_token')))) {
            return $app->abort(403);
        }

        $request->getSession()->set('pmiLastUsed', time());
        $response = new JsonResponse();
        $response->setData(array());
        return $response;
    }

    /**
     * Handles a clientside session timeout, which might not be a true session
     * timeout if the user is working in multiple tabs.
     */
    public function clientTimeoutAction(Application $app, Request $request) {
        // if we got to this point, then the beforeCallback() has
        // already checked the user's session is not expired - simply reload the page
        if ($request->headers->get('referer')) {
            return $app->redirect($request->headers->get('referer'));
        } else {
            return $app->redirect($app->generateUrl('home'));
        }
    }

    public function agreeUsageAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('agreeUsage', $request->get('csrf_token')))) {
            return $app->abort(403);
        }

        $request->getSession()->set('isUsageAgreed', true);
        return (new JsonResponse())->setData([]);
    }

    public function groupsAction(Application $app)
    {
        $token = $app['security.token_storage']->getToken();
        $user = $token->getUser();
        $groups = $user->getGroups();

        $groupNames = [];
        foreach ($groups as $group) {
            $groupNames[] = $group->getName();
        }
        return $app['twig']->render('googlegroups.html.twig', [
            'groupNames' => $groupNames
        ]);
    }

    public function hideTZWarningAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('hideTZWarning', $request->get('csrf_token')))) {
            return $app->abort(403);
        }

        $request->getSession()->set('hideTZWarning', true);
        return (new JsonResponse())->setData([]);
    }
}
