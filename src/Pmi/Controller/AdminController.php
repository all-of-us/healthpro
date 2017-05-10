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
    protected static $name = 'admin';

    protected static $routes = [
        ['home', '/'],
        ['sites', '/sites'],
        ['siteEdit', '/site/edit/{siteId}', ['method' => 'GET|POST']],
        ['siteCreate', '/site/add', ['method' => 'GET|POST']]
    ];

    public function homeAction(Application $app)
    {
        return $app['twig']->render('admin/index.html.twig');
    }

    public function sitesAction(Application $app)
    {
        $sites = $app['em']->getRepository('sites')->fetchBy([], ['name' => 'asc']);
        return $app['twig']->render('admin/sites/index.html.twig', ['sites' => $sites]);
    }

    public function siteEditAction($siteId, Application $app, Request $request)
    {
        $site = $app['em']->getRepository('sites')->fetchOneBy([
            'id' => $siteId
        ]);
        if (!$site) {
            $app->abort(404);;
        }

        if ($request->request->has('delete')) {
            $app['em']->getRepository('sites')->delete($siteId);
            $app->log(Log::SITE_DELETE, $siteId);
            $app->addFlashNotice('Site removed.');
            return $app->redirectToRoute('admin_sites');
        }

        $siteEditForm = $this->getSiteEditForm($app, $site);
        $siteEditForm->handleRequest($request);
        if ($siteEditForm->isValid()) {
            if ($app['em']->getRepository('sites')->update($siteId, [
                'name' => $siteEditForm['name']->getData(),
                'google_group' => $siteEditForm['google_group']->getData(),
                'mayolink_account' => $siteEditForm['mayolink_account']->getData(),
                'organization' => $siteEditForm['organization']->getData()
            ])) {
                $app->log(Log::SITE_EDIT, $siteId);
                $app->addFlashNotice('Site updated');

                return $app->redirectToRoute('admin_sites');
            }
        }

        return $app['twig']->render('admin/sites/edit.html.twig', [
            'site' => $site,
            'verb' => 'Edit',
            'siteForm' => $siteEditForm->createView()
        ]);
    }

    public function siteCreateAction(Application $app, Request $request)
    {
        $site = array();
        $siteAddForm = $this->getSiteEditForm($app);
        $siteAddForm->handleRequest($request);
        if ($siteAddForm->isValid()) {
            if ($siteId = $app['em']->getRepository('sites')->insert([
                'name' => $siteAddForm['name']->getData(),
                'google_group' => $siteAddForm['google_group']->getData(),
                'mayolink_account' => $siteAddForm['mayolink_account']->getData(),
                'organization' => $siteAddForm['organization']->getData()
            ])) {
                $app->log(Log::SITE_ADD, $siteId);
                $app->addFlashNotice('Site added');

                return $app->redirectToRoute('admin_sites');
            }
        }

        return $app['twig']->render('admin/sites/edit.html.twig', [
            'site' => $site,
            'verb' => 'Add',
            'siteForm' => $siteAddForm->createView()
        ]);
    }

    protected function getSiteEditForm(Application $app, $site = null)
    {
        return $app['form.factory']->createBuilder(FormType::class, $site)
            ->add('name', Type\TextType::class, [
                'label' => 'Name',
                'required' => true
            ])
            ->add('google_group', Type\TextType::class, [
                'label' => 'Google Group',
                'required' => true
            ])
            ->add('mayolink_account', Type\TextType::class, [
                'label' => 'MayoLink Account',
                'required' => false
            ])
            ->add('organization', Type\TextType::class, [
                'label' => 'Organization',
                'required' => false
            ])
            ->getForm();
    }
}
