<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Pmi\Evaluation\Evaluation;
use Pmi\Mayolink\Order;

class DefaultController extends AbstractController
{
    protected static $routes = [
        ['home', '/'],
        ['participants', '/participants', ['method' => 'GET|POST']],
        ['orders', '/orders', ['method' => 'GET|POST']],
        ['participant', '/participant/{id}'],
        ['orderCreate', '/participant/{participantId}/order/create', [
            'method' => 'GET|POST'
        ]],
        ['order', '/participant/{participantId}/order/{orderId}'],
        ['orderPdf', '/participant/{participantId}/order/{orderId}.pdf'],
        ['orderPrint', '/participant/{participantId}/order/{orderId}/print'],
        ['orderCollect', '/participant/{participantId}/order/{orderId}/collect', ['method' => 'GET|POST']],
        ['orderProcess', '/participant/{participantId}/order/{orderId}/process', ['method' => 'GET|POST']],
        ['orderFinalize', '/participant/{participantId}/order/{orderId}/finalize', ['method' => 'GET|POST']],
        ['participantEval', '/participant/{participantId}/eval/{evalId}', [
            'method' => 'GET|POST',
            'defaults' => ['evalId' => null]
        ]]
    ];

    public function homeAction(Application $app, Request $request)
    {
        return $app['twig']->render('index.html.twig');
    }

    public function participantsAction(Application $app, Request $request)
    {
        $idForm = $app['form.factory']->createNamedBuilder('id', FormType::class)
            ->add('participantId', TextType::class, ['label' => 'Participant ID'])
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
            ->add('lastName', TextType::class, ['required' => false])
            ->add('firstName', TextType::class, ['required' => false])
            ->add('dob', TextType::class, ['label' => 'Date of birth', 'required' => false])
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

    public function ordersAction(Application $app, Request $request)
    {
        $idForm = $app['form.factory']->createNamedBuilder('id', FormType::class)
            ->add('mayoId', TextType::class, ['label' => 'MayoLINK order ID', 'attr' => ['placeholder' => 'Scan barcode']])
            ->getForm();

        $idForm->handleRequest($request);

        if ($idForm->isValid()) {
            $id = $idForm->get('mayoId')->getData();
            $order = $app['db']->fetchAssoc('SELECT * FROM orders WHERE mayo_id=?', [$id]);
            if ($order) {
                return $app->redirectToRoute('order', [
                    'participantId' => $order['participant_id'],
                    'orderId' => $order['id']
                ]);
            }
            $app->addFlashError('Participant ID not found');
        }

        $recentOrders = $app['db']->fetchAll('SELECT * FROM orders ORDER BY created_ts DESC, id DESC LIMIT 5');
        foreach ($recentOrders as &$order) {
            $order['participant'] = $app['pmi.drc.participantsearch']->getById($order['participant_id']);
        }
        return $app['twig']->render('orders.html.twig', [
            'idForm' => $idForm->createView(),
            'recentOrders' => $recentOrders
        ]);
    }

    public function participantAction($id, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participantsearch']->getById($id);
        if (!$participant) {
            $app->abort(404);
        }
        $orders = $app['db']->fetchAll('SELECT * FROM orders WHERE participant_id = ? ORDER BY created_ts DESC, id DESC', [$id]);
        $evaluations = $app['db']->fetchAll('SELECT * FROM evaluations WHERE participant_id = ? ORDER BY updated_ts DESC, id DESC', [$id]);
        return $app['twig']->render('participant.html.twig', [
            'participant' => $participant,
            'orders' => $orders,
            'evaluations' => $evaluations
        ]);
    }

    public function orderCreateAction($participantId, Application $app, Request $request)
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
                    return $app->redirectToRoute('order', [
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

    public function orderPdfAction($participantId, $orderId, Application $app, Request $request)
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

    public function orderAction($participantId, $orderId, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participantsearch']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        $order = $app['db']->fetchAssoc('SELECT * FROM orders WHERE id = ? AND participant_id = ?', [$orderId, $participantId]);
        if (!$order) {
            $app->abort(404);
        }
        // TODO: redirect to current step based on order status
        return $app->redirectToRoute('orderPrint', [
            'participantId' => $order['participant_id'],
            'orderId' => $order['id']
        ]);
    }

    public function orderPrintAction($participantId, $orderId, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participantsearch']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        $order = $app['db']->fetchAssoc('SELECT * FROM orders WHERE id = ? AND participant_id = ?', [$orderId, $participantId]);
        if (!$order) {
            $app->abort(404);
        }
        return $app['twig']->render('order-print.html.twig', [
            'participant' => $participant,
            'order' => $order,
            'real' => $request->query->has('real')
        ]);
    }

    public function orderCollectAction($participantId, $orderId, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participantsearch']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        $order = $app['db']->fetchAssoc('SELECT * FROM orders WHERE id = ? AND participant_id = ?', [$orderId, $participantId]);
        if (!$order) {
            $app->abort(404);
        }

        $formData = [];
        if ($order['collected_notes']) {
            $formData['collected_notes'] = $order['collected_notes'];
        };
        if ($order['collected_ts']) {
            $formData['collected_ts'] = new \DateTime($order['collected_ts']);
        }
        if ($order['collected_samples']) {
            $samples = json_decode($order['collected_samples']);
            if (is_array($samples) && count($samples) > 0) {
                $formData['collected_samples'] = $samples;
            }
        }
        $collectForm = $app['form.factory']->createBuilder(FormType::class, $formData)
            ->add('collected_ts', DateTimeType::class, [
                'label' => 'Collected time',
                'date_widget' => 'single_text',
                'time_widget' => 'single_text',
                'required' => false
            ])
            ->add('collected_samples', ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => 'Which samples were successfully collected?',
                'choices' => array_combine(range(1,7), range(1,7)),
                'required' => false
            ])
            ->add('collected_notes', TextareaType::class, [
                'label' => 'Additional notes on collection',
                'required' => false
            ])
            ->getForm();

        $collectForm->handleRequest($request);
        if ($collectForm->isValid()) {
            $updateArray = [];
            $formData = $collectForm->getData();
            if ($formData['collected_notes']) {
                $updateArray['collected_notes'] = $formData['collected_notes'];
            } else {
                $updateArray['collected_notes'] = null;
            }
            if ($formData['collected_ts']) {
                $updateArray['collected_ts'] = $formData['collected_ts']->format('Y-m-d H:i:s');
            } else {
                $updateArray['collected_ts'] = null;
            }
            if ($formData['collected_samples'] && is_array($formData['collected_samples'])) {
                $updateArray['collected_samples'] = json_encode($formData['collected_samples']);
            } else {
                $updateArray['collected_samples'] = null;
            }
            if ($app['db']->update('orders', $updateArray, ['id' => $orderId])) {
                $app->addFlashNotice('Order collection updated');
                return $app->redirectToRoute('orderCollect', [
                    'participantId' => $participant->id,
                    'orderId' => $orderId
                ]);
            }
        }

        return $app['twig']->render('order-collect.html.twig', [
            'participant' => $participant,
            'order' => $order,
            'collectForm' => $collectForm->createView()
        ]);
    }

    public function participantEvalAction($participantId, $evalId, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participantsearch']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        $evaluationService = new Evaluation();
        if ($evalId) {
            $evaluation = $app['db']->fetchAssoc('SELECT * FROM evaluations WHERE id = ? AND participant_id = ?', [$evalId, $participantId]);
            if (!$evaluation) {
                $app->abort(404);
            }
            $evaluationService->loadFromArray($evaluation);
        } else {
            $evaluation = null;
        }
        $evaluationForm = $evaluationService->getForm($app['form.factory']);
        $evaluationForm->handleRequest($request);
        if ($evaluationForm->isValid()) {
            $evaluationService->setData($evaluationForm->getData());
            $dbArray = $evaluationService->toArray();
            $dbArray['updated_ts'] = (new \DateTime())->format('Y-m-d H:i:s');
            if (!$evaluation) {
                $dbArray['participant_id'] = $participant->id;
                $dbArray['created_ts'] = $dbArray['updated_ts'];
                if ($app['db']->insert('evaluations', $dbArray) && ($evalId = $app['db']->lastInsertId())) {
                    $app->addFlashNotice('Evaluation saved');
                    return $app->redirectToRoute('participantEval', [
                        'participantId' => $participant->id,
                        'evalId' => $evalId
                    ]);
                } else {
                    $app->addFlashError('Failed to create new evaluation');
                }
            } else {
                if ($app['db']->update('evaluations', $dbArray, ['id' => $evalId])) {
                    $app->addFlashNotice('Evaluation saved');
                    return $app->redirectToRoute('participantEval', [
                        'participantId' => $participant->id,
                        'evalId' => $evalId
                    ]);
                } else {
                    $app->addFlashError('Failed to update evaluation');
                }
            }
        }

        return $app['twig']->render('evaluation.html.twig', [
            'participant' => $participant,
            'evaluation' => $evaluation,
            'evaluationForm' => $evaluationForm->createView()
        ]);
    }
}
