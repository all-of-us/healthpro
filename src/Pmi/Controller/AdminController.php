<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Validation;
use Pmi\Audit\Log;


class AdminController extends AbstractController
{
    protected static $name = 'admin';

    protected static $routes = [
        ['home', '/'],
        ['sites', '/sites'],
        ['site', '/site/{siteId}', [
            'method' => 'GET|POST',
            'defaults' => ['siteId' => null]
        ]]
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

    public function siteAction($siteId, Application $app, Request $request)
    {
        if ($siteId) {
            $site = $app['em']->getRepository('sites')->fetchOneBy([
                'id' => $siteId
            ]);
            if (!$site) {
                $app->abort(404);;
            }

            if ($request->request->has('delete')) {
                $app['em']->getRepository('sites')->delete($siteId);
                $app->log(Log::SITE_DELETE, $siteId);
                $app->addFlashNotice('Site removed');
                return $app->redirectToRoute('admin_sites');
            }
        } else {
            $site = null;
        }

        $siteEditForm = $this->getSiteEditForm($app, $site);
        $siteEditForm->handleRequest($request);
        if ($siteEditForm->isValid()) {
            if ($site) {
                if ($app['em']->getRepository('sites')->update($siteId, $siteEditForm->getData())) {
                    $app->log(Log::SITE_EDIT, $siteId);
                    $app->addFlashNotice('Site updated');
                }
            } else {
                if ($siteId = $app['em']->getRepository('sites')->insert($siteEditForm->getData())) {
                    $app->log(Log::SITE_ADD, $siteId);
                    $app->addFlashNotice('Site added');
                }
            }
            return $app->redirectToRoute('admin_sites');
        }

        return $app['twig']->render('admin/sites/edit.html.twig', [
            'site' => $site,
            'siteForm' => $siteEditForm->createView()
        ]);
    }

    protected function getSiteEditForm(Application $app, $site = null)
    {
        if ($site && isset($site['id'])) {
            unset($site['id']);
        }
        return $app['form.factory']->createBuilder(FormType::class, $site)
            ->add('name', Type\TextType::class, [
                'label' => 'Name',
                'required' => true,
                'constraints' => new Constraints\NotBlank()
            ])
            ->add('google_group', Type\TextType::class, [
                'label' => 'Google Group',
                'required' => true,
                'constraints' => new Constraints\NotBlank()
            ])
            ->add('mayolink_account', Type\TextType::class, [
                'label' => 'MayoLink Account',
                'required' => false
            ])
            ->add('organization', Type\TextType::class, [
                'label' => 'Organization',
                'required' => false
            ])
            ->add('email', Type\TextType::class, [
                'label' => 'Email address(es)',
                'required' => false,
                'constraints' => [
                    new Constraints\Length(['max' => 512]),
                    new Constraints\Callback(function($list, $context) {
                        $list = trim($list);
                        if (empty($list)) {
                            return;
                        }
                        $emails = explode(',', $list);
                        $validator = Validation::createValidator();
                        foreach ($emails as $email) {
                            $email = trim($email);
                            $errors = $validator->validate($email, new Constraints\Email());
                            if (count($errors) > 0) {
                                $context
                                    ->buildViolation('Must be a comma-separated list of valid email addresses')
                                    ->addViolation();
                                break;
                            }
                        }
                    })
                ]
            ])
            ->getForm();
    }
}
