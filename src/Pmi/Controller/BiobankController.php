<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Form\FormError;
use Pmi\Drc\Exception\ParticipantSearchExceptionInterface;

class BiobankController extends AbstractController
{
    protected static $name = 'biobank';

    protected static $routes = [
        ['participants', '/participants', ['method' => 'GET|POST']],
        ['orders', '/orders', ['method' => 'GET|POST']]
    ];

    public function participantsAction(Application $app, Request $request)
    {
        $idForm = $app['form.factory']->createNamedBuilder('id', Type\FormType::class)
            ->add('biobankId', Type\TextType::class, [
                'label' => 'Biobank ID',
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'placeholder' => 'Y000000000'
                ]
            ])
            ->getForm();

        $idForm->handleRequest($request);

        if ($idForm->isValid()) {
            $searchParameters = $idForm->getData();
            try {
                $searchResults = $app['pmi.drc.participants']->search($searchParameters);
                if (count($searchResults) == 1) {
                    return $app->redirectToRoute('participant', [
                        'id' => $searchResults[0]->id
                    ]);
                }
                return $app['twig']->render('participants-list.html.twig', [
                    'participants' => $searchResults
                ]);
            } catch (ParticipantSearchExceptionInterface $e) {
                $idForm->addError(new FormError($e->getMessage()));
            }
        }

        return $app['twig']->render('biobank/participants.html.twig', [
            'idForm' => $idForm->createView()
        ]);
    }
}