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
        ['participants', '/participants', ['method' => 'GET|POST']],
        ['participant', '/participant/{id}']
    ];

    public function homeAction(Application $app, Request $request)
    {
        return $app['twig']->render('index.html.twig');
    }

    public function participantsAction(Application $app, Request $request)
    {
        $idForm = $app['form.factory']->createNamedBuilder('id', FormType::class)
            ->add('participantId', TextType::class)
            ->getForm();

        $idForm->handleRequest($request);

        if ($idForm->isValid()) {
            $id = $idForm->get('participantId')->getData();
            $participant = $app['pmi.drc.participantsearch']->getById($id);
            if ($participant) {
                return $app->redirectToRoute('participant', ['id' => $id]);
            }
            $app->addFlashError('Participant ID not found');
        }

        $searchForm = $app['form.factory']->createNamedBuilder('search', FormType::class)
            ->add('lastName', TextType::class)
            ->add('firstName', TextType::class, ['required' => false])
            ->add('dob', TextType::class)
            ->getForm();

        $searchForm->handleRequest($request);

        if ($searchForm->isValid()) {
            $searchParameters = $searchForm->getData();
            $searchResults = $app['pmi.drc.participantsearch']->search($searchParameters);
            return $app['twig']->render('participants-list.html.twig', [
                'participants' => $searchResults
            ]);
        }

        return $app['twig']->render('participants.html.twig', [
            'searchForm' => $searchForm->createView(),
            'idForm' => $idForm->createView()
        ]);
    }

    public function participantAction($id, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participantsearch']->getById($id);
        if (!$participant) {
            $app->abort(404);
        }
        return $app['twig']->render('participant.html.twig', [
            'participant' => $participant
        ]);
    }
}
