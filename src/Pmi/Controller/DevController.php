<?php
namespace Pmi\Controller;

use Pmi\Application\AbstractApplication as Application;
use Pmi\Entities\Configuration;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use google\appengine\api\users\UserService;

class DevController extends AbstractController
{
    protected static $name = '_dev';

    protected static $routes = [
        ['datastoreInit', '/datastore-init'],
        ['createParticipant', '/create-participant', ['method' => 'GET|POST']]
    ];

    public function datastoreInitAction(Application $app, Request $request)
    {
        if ($app['env'] !== Application::ENV_DEV && $app['env'] !== Application::ENV_TEST) {
            return $app->abort(404);
        } elseif (!UserService::isCurrentUserAdmin()) {
            $app->addFlashError('Access denied!');
        } else {
            $keys = ['test'];
            foreach ($keys as $key) {
                $value = $app->getConfig($key);
                if ($value === null) {
                    $config = new Configuration();
                    $config->setKey($key);
                    $config->setValue('');
                    $config->save();
                }
            }
            $app->addFlashNotice('Configuration initialized!');
        }
        return $app->redirectToRoute('home');
    }

    public function createParticipantAction(Application $app, Request $request)
    {
        if (!$app->isDev()) {
            return $app->abort(404);
        } elseif (!UserService::isCurrentUserAdmin()) {
            return $app->abort(403);
        } else {
            $form = $app['form.factory']->createBuilder(FormType::class)
                ->add('last_name', TextType::class)
                ->add('first_name', TextType::class)
                ->add('zip_code', TextType::class)
                ->add('date_of_birth', TextType::class)
                ->add('membership_tier', ChoiceType::class, [
                    'choices' => [
                        'CONSENTED' => 'CONSENTED',
                        'INTERESTED' => 'INTERESTED'
                    ]
                ])
                ->add('gender_identity', ChoiceType::class, [
                    'choices' => [
                        'FEMALE' => 'FEMALE',
                        'MALE' => 'MALE',
                        'OTHER' => 'OTHER'
                    ]
                ])
                ->getForm();
            $form->handleRequest($request);
            if ($form->isValid()) {
                $data = $form->getData();
                $participantsApi = new \Pmi\Drc\RdrParticipants($app['pmi.drc.rdrhelper']);
                $result = $participantsApi->createParticipant($data);
                if ($result) {
                    $app->addFlashSuccess('Participant created: ' . $result);
                } else {
                    $app->addFlashError('Error creating participant');
                }
                return $app->redirectToRoute('_dev_createParticipant');
            }
            return $app['twig']->render('dev/participant.html.twig', [
                'form' => $form->createView()
            ]);
        }
    }
}
