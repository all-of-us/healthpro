<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;
use Pmi\Audit\Log;
use Pmi\Site\Site;


class AdminController extends AbstractController
{
    protected static $routes = [
        ['siteIndex', '/sites'],
        ['editSite', '/site/edit/{siteId}', ['method' => 'GET|POST']],
        ['addSite', '/site/add', ['method' => 'GET|POST']]
    ];

    public function siteIndexAction(Application $app, Request $request)
    {
        $sites = $app['db']->fetchAll("SELECT * FROM sites");
        return $app['twig']->render('site-index.html.twig', ['sites' => $sites]);
    }


    public function editSiteAction($siteId, Application $app, Request $request)
    {

        $site = $app['em']->getRepository('sites')->fetchOneBy([
            'id' => $siteId
        ]);
        if (!$site) {
            $app->abort(404);;
        }

        $siteEditForm = $site->createEditForm($app['form.factory']);

        $siteEditForm->handleRequest($request);
        if ($siteEditForm->isValid()) {
            if ($app['em']->getRepository('sites')->update($siteId, [
                'name' => $siteEditForm['name']->getData(),
                'google_group' => $siteEditForm['google_group']->getData(),
                'mayolink_account' => $siteEditForm['mayolink_account']->getData()
            ])) {
                $app->log(Log::SITE_EDIT, $siteId);
                $app->addFlashNotice('Site updated');

                return $app->redirectToRoute('siteIndex');
            }
        }

        return $app['twig']->render('site-edit.html.twig', [
            'site' => $site,
            'siteForm' => $siteEditForm->createView()
        ]);
    }

    public function addSiteAction(Application $app, Request $request)
    {

        $site = new Site;
        $siteAddForm = $site->createEditForm($app['form.factory']);

        $siteAddForm->handleRequest($request);
        if ($siteAddForm->isValid()) {

            if ($siteId = $app['em']->getRepository('sites')->insert([
                'name' => $siteAddForm['name']->getData(),
                'google_group' => $siteAddForm['google_group']->getData(),
                'mayolink_account' => $siteAddForm['mayolink_account']->getData()
            ])) {
                $app->log(Log::SITE_ADD, $siteId);
                $app->addFlashNotice('Site added');

                return $app->redirectToRoute('siteIndex');
            }
        }
        if(!$site) {
            $site = new Site;
        }
        return $app['twig']->render('site-edit.html.twig', [
            'site' => $site,
            'siteForm' => $siteAddForm->createView()
        ]);
    }
}

