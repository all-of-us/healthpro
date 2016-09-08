<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Pmi\Mayolink\Order;

class DefaultController extends AbstractController
{
    protected static $routes = [
        ['home', '/'],
        ['participants', '/participants', ['method' => 'GET|POST']],
        ['participant', '/participant/{id}'],
        ['participantOrderCreate', '/participant/{participantId}/order/create', [
            'method' => 'GET|POST'
        ]],
        ['participantOrderPdf', '/participant/{participantId}/order/{orderId}.pdf'],
        ['participantOrder', '/participant/{participantId}/order/{orderId}']
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
        $orders = $app['db']->fetchAll('SELECT * FROM orders WHERE participant_id = ? ORDER BY created_ts DESC, id DESC', [$id]);
        return $app['twig']->render('participant.html.twig', [
            'participant' => $participant,
            'orders' => $orders
        ]);
    }

    public function participantOrderCreateAction($participantId, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participantsearch']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        $confirmForm = $app['form.factory']->createBuilder(FormType::class)
            ->add('confirm', HiddenType::class)
            ->getForm();
        $confirmForm->handleRequest($request);
        if ($confirmForm->isValid()) {
            $order = new Order();
            $options = [
                // TODO: figure out test code, specimen, and temperature parameters
                'test_code' => 'ACE',
                'specimen' => 'Serum',
                'temperature' => 'Ambient',
                'first_name' => '*',
                'last_name' => $participant->id,
                'gender' => $participant->gender,
                'birth_date' => $participant->dob,
                'physician_name' => 'None',
                'physician_phone' => 'None',
                // TODO: not sure how ML is handling time zone. setting to yesterday for now
                'collected_at' => new \DateTime('-1 day')
            ];
            $mlOrderId = $order->loginAndCreateOrder(
                $app->getConfig('ml_username'),
                $app->getConfig('ml_password'),
                $options
            );
            if ($mlOrderId) {
                $success = $app['db']->insert('orders', [
                    'participant_id' => $participant->id,
                    'created_ts' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'mayo_id' => $mlOrderId
                ]);
                if ($success && ($orderId = $app['db']->lastInsertId())) {
                    return $app->redirectToRoute('participantOrder', [
                        'participantId' => $participant->id,
                        'orderId' => $orderId
                    ]);
                }
            }
            $app->addFlashError('Failed to create order.');
        }

        return $app['twig']->render('order-create.html.twig', [
            'participant' => $participant,
            'confirmForm' => $confirmForm->createView()
        ]);
    }

    public function participantOrderPdfAction($participantId, $orderId, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participantsearch']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        $order = $app['db']->fetchAssoc('SELECT * FROM orders WHERE id = ? AND participant_id = ?', [$orderId, $participantId]);
        if (!$order) {
            $app->abort(404);
        }
        $mlOrder = new Order();
        $pdf = $mlOrder->loginAndGetPdf(
            $app->getConfig('ml_username'),
            $app->getConfig('ml_password'),
            $order['mayo_id']
        );

        return new Response($pdf, 200, array('Content-Type' => 'application/pdf'));
    }

    public function participantOrderAction($participantId, $orderId, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participantsearch']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        $order = $app['db']->fetchAssoc('SELECT * FROM orders WHERE id = ? AND participant_id = ?', [$orderId, $participantId]);
        if (!$order) {
            $app->abort(404);
        }
        return $app['twig']->render('order.html.twig', [
            'participant' => $participant,
            'order' => $order
        ]);
    }
}
