<?php
namespace Pmi\Controller;

use Pmi\Application\AbstractApplication as Application;
use Pmi\Entities\Configuration;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;

class DevController extends AbstractController
{
    protected static $name = '_dev';

    protected static $routes = [
        ['createParticipant', '/create-participant', ['method' => 'GET|POST']],
        ['mockBiobankSampleProcess', '/mock-biobank-sample-process/{id}']
    ];

    public function createParticipantAction(Application $app, Request $request)
    {
        if ($app->isProd()) {
            return $app->abort(404);
        }

        $form = $app['form.factory']->createBuilder(FormType::class)
            ->add('last_name', TextType::class)
            ->add('first_name', TextType::class)
            ->add('zip_code', TextType::class)
            ->add('date_of_birth', TextType::class)
            ->add('membership_tier', ChoiceType::class, [
                'choices' => [
                    'REGISTERED' => 'REGISTERED',
                    'VOLUNTEER' => 'VOLUNTEER'
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

    public function mockBiobankSampleProcessAction($id, Application $app)
    {
        if ($app->isProd()) {
            return $app->abort(404);
        }
        return $app['pmi.drc.participants']->createMockBiobankSamples($id);
    }
}
