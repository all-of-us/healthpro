<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Pmi\Audit\Log;


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

        $siteEditForm = $this->getSiteEditForm($app, $site);

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
            'verb' => 'Edit',
            'siteForm' => $siteEditForm->createView()
        ]);
    }

    public function addSiteAction(Application $app, Request $request)
    {
        $site = array();
        $siteAddForm = $this->getSiteEditForm($app);

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

        return $app['twig']->render('site-edit.html.twig', [
            'site' => $site,
            'verb' => 'Add',
            'siteForm' => $siteAddForm->createView()
        ]);
    }

    protected function getSiteEditForm(Application $app, $site = null)
    {
        $form = $app['form.factory']->createBuilder(FormType::class, $site)
            ->add('name', Type\TextType::class, [
                'label' => 'Name',
                'required' => true
            ])
            ->add('google_group', Type\TextType::class, [
                'label' => 'Google Group',
                'required' => true
            ])
            ->add('mayolink_account', Type\TextType::class, [
                'label' => 'MayLink Account',
                'required' => true
            ])
            ->getForm();
        return $form;
    }
}

