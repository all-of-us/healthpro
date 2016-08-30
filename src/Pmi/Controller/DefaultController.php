<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class DefaultController extends AbstractController
{
    protected static $routes = [
        ['home', '/'],
        ['participants', '/participants', ['method' => 'GET|POST']]
    ];

    public function homeAction(Application $app, Request $request)
    {
        return $app['twig']->render('index.html.twig');
    }

    public function participantsAction(Application $app, Request $request)
    {
        $form = $app['form.factory']->createBuilder(FormType::class)
            ->add('lastName', TextType::class)
            ->add('firstName', TextType::class, ['required' => false])
            ->add('dob', TextType::class)
            ->getForm();

        $form->handleRequest($request);

        if ($form->isValid()) {
            $searchParameters = $form->getData();
            $searchResults = $app['pmi.drc.participantsearch']->search($searchParameters);
            return $app['twig']->render('participants-list.html.twig', [
                'participants' => $searchResults
            ]);
        }

        return $app['twig']->render('participants.html.twig', [
            'form' => $form->createView()
        ]);
    }
}
