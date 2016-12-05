<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Form\FormError;
use Pmi\Audit\Log;
use Pmi\Order\Order;
use Pmi\Order\Mayolink\MayolinkOrder;
use Pmi\Util;

class OrderController extends AbstractController
{
    protected static $routes = [
        ['orderCreate', '/participant/{participantId}/order/create', ['method' => 'GET|POST']],
        ['orderPdf', '/participant/{participantId}/order/{orderId}-{type}.pdf'],
        ['order', '/participant/{participantId}/order/{orderId}'],
        ['orderPrint', '/participant/{participantId}/order/{orderId}/print'],
        ['orderCollect', '/participant/{participantId}/order/{orderId}/collect', ['method' => 'GET|POST']],
        ['orderProcess', '/participant/{participantId}/order/{orderId}/process', ['method' => 'GET|POST']],
        ['orderFinalize', '/participant/{participantId}/order/{orderId}/finalize', ['method' => 'GET|POST']],
        ['orderJson', '/participant/{participantId}/order/{orderId}/order.json'],
        ['orderExport', '/orders/export.csv']
    ];

    protected function loadOrder($participantId, $orderId, Application $app)
    {
        $order = new Order();
        $order->loadOrder($participantId, $orderId, $app);
        if ($order->isValid()) {
            return $order;
        } else {
            $app->abort(404);
        }
    }

    public function orderCreateAction($participantId, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        if (!$participant->consentComplete) {
            $app->abort(403);
        }
        $confirmForm = $app['form.factory']->createBuilder(FormType::class)
            ->add('kitId', Type\RepeatedType::class, [
                'type' => Type\TextType::class,
                'invalid_message' => 'The kit order ID fields must match.',
                'first_options' => [
                    'label' => 'Kit order ID'
                ],
                'second_options' => [
                    'label' => 'Verify kit order ID',
                ],
                'options' => [
                    'attr' => ['placeholder' => 'Scan barcode']
                ],
                'required' => false,
                'error_mapping' => [
                    '.' => 'second' // target the second (repeated) field for non-matching error
                ],
                'constraints' => [
                    new Constraints\Regex([
                        'pattern' => '/^KIT-\d{8}$/',
                        'message' => 'Must be in the format of KIT-12345678 ("KIT-" followed by 8 digits)'
                    ])
                ]
            ])
            ->add('samples', Type\ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => 'Select requested samples',
                'choices' => Order::$samples,
                'required' => false
            ])
            ->getForm();
        $showCustom = false;
        $confirmForm->handleRequest($request);
        if ($confirmForm->isValid()) {
            $orderData = ['type' => null];
            if ($request->request->has('existing')) {
                if (empty($confirmForm['kitId']->getData())) {
                    $confirmForm['kitId']['first']->addError(new FormError('Please enter a kit order ID'));
                } elseif ($app['em']->getRepository('orders')->fetchOneBy(['order_id' => $confirmForm['kitId']->getData()])) {
                    $confirmForm['kitId']['first']->addError(new FormError('This order ID already exists'));
                } else {
                    $orderData['order_id'] = $confirmForm['kitId']->getData();
                    $orderData['type'] = 'kit';
                }
            } else {
                $orderData['order_id'] = Util::generateShortUuid(12);
                if ($request->request->has('custom')) {
                    $showCustom = true;
                    $requestedSamples = $confirmForm['samples']->getData();
                    if (empty($requestedSamples) || !is_array($requestedSamples)) {
                        $confirmForm['samples']->addError(new FormError('Please select at least one sample'));
                    } else {
                        $orderData['requested_samples'] = json_encode($requestedSamples);
                    }
                } elseif ($request->request->has('saliva')) {
                    $orderData['type'] = 'saliva';
                }
            }
            if ($confirmForm->isValid()) {
                if ($app->getConfig('ml_mock_order')) {
                    $orderData['mayo_id'] = $app->getConfig('ml_mock_order');
                } else {
                    $order = new MayolinkOrder();
                    $options = [
                        'type' => $orderData['type'],
                        'patient_id' => $participant->biobankId,
                        'gender' => $participant->gender,
                        'birth_date' => $participant->getMayolinkDob($orderData['type']),
                        'order_id' => $orderData['order_id'],
                        'collected_at' => new \DateTime('today') // set to today at midnight since time won't be accurate
                    ];
                    if ($app['session']->get('site') && !empty($app['session']->get('site')->id)) {
                        $options['site'] = $app['session']->get('site')->id;
                    }
                    if (isset($requestedSamples) && is_array($requestedSamples)) {
                        $options['tests'] = $requestedSamples;
                    }
                    $orderData['mayo_id'] = $order->loginAndCreateOrder(
                        $app->getConfig('ml_username'),
                        $app->getConfig('ml_password'),
                        $options
                    );
                }
                if ($orderData['mayo_id']) {
                    $orderData['user_id'] = $app->getUser()->getId();
                    $orderData['site'] = $app->getSiteId();
                    $orderData['participant_id'] = $participant->id;
                    $orderData['biobank_id'] = $participant->biobankId;
                    $orderData['created_ts'] = (new \DateTime())->format('Y-m-d H:i:s');

                    $orderId = $app['em']->getRepository('orders')->insert($orderData);
                    if ($orderId) {
                        $app->log(Log::ORDER_CREATE, $orderId);
                        return $app->redirectToRoute('order', [
                            'participantId' => $participant->id,
                            'orderId' => $orderId
                        ]);
                    }
                }
                $app->addFlashError('Failed to create order.');
            }
        }

        return $app['twig']->render('order-create.html.twig', [
            'participant' => $participant,
            'confirmForm' => $confirmForm->createView(),
            'showCustom' => $showCustom
        ]);
    }

    public function orderAction($participantId, $orderId, Application $app, Request $request)
    {
        $order = $this->loadOrder($participantId, $orderId, $app);
        $action = ucfirst($order->getCurrentStep());
        return $app->redirectToRoute("order{$action}", [
            'participantId' => $participantId,
            'orderId' => $orderId
        ]);
    }

    public function orderPdfAction($type, $participantId, $orderId, Application $app, Request $request)
    {
        if (!in_array($type, ['labels', 'requisition'])) {
            $app->abort(404);
        }
        $order = $this->loadOrder($participantId, $orderId, $app);

        if ($app->getConfig('ml_mock_order')) {
            if ($type == 'labels') {
                return $app->redirect($request->getBaseUrl() . '/assets/SampleLabels.pdf');
            } else {
                return $app->redirect($request->getBaseUrl() . '/assets/SampleRequisition.pdf');
            }
        } else {
            $mlOrder = new MayolinkOrder();
            $pdf = $mlOrder->loginAndGetPdf(
                $app->getConfig('ml_username'),
                $app->getConfig('ml_password'),
                $order->get('mayo_id'),
                $type
            );

            if ($pdf) {
                return new Response($pdf, 200, array('Content-Type' => 'application/pdf'));
            } else {
                $app->abort(500, 'Failed to load PDF');
            }
        }
    }

    public function orderPrintAction($participantId, $orderId, Application $app, Request $request)
    {
        $order = $this->loadOrder($participantId, $orderId, $app);
        if (!$order->get('printed_ts')) {
            $app->log(Log::ORDER_EDIT, $orderId);
            $app['em']->getRepository('orders')->update($orderId, [
                'printed_ts' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
        }
        return $app['twig']->render('order-print.html.twig', [
            'participant' => $order->getParticipant(),
            'order' => $order->toArray()
        ]);
    }

    public function orderCollectAction($participantId, $orderId, Application $app, Request $request)
    {
        $order = $this->loadOrder($participantId, $orderId, $app);
        $collectForm = $order->createOrderForm('collected', $app['form.factory']);
        $collectForm->handleRequest($request);
        if ($collectForm->isValid()) {
            $updateArray = $order->getOrderUpdateFromForm('collected', $collectForm);
            if ($app['em']->getRepository('orders')->update($orderId, $updateArray)) {
                $app->log(Log::ORDER_EDIT, $orderId);
                $app->addFlashNotice('Order collection updated');

                return $app->redirectToRoute('orderCollect', [
                    'participantId' => $participantId,
                    'orderId' => $orderId
                ]);
            }
        }
        return $app['twig']->render('order-collect.html.twig', [
            'participant' => $order->getParticipant(),
            'order' => $order->toArray(),
            'collectForm' => $collectForm->createView()
        ]);
    }

    public function orderProcessAction($participantId, $orderId, Application $app, Request $request)
    {
        $order = $this->loadOrder($participantId, $orderId, $app);
        $processForm = $order->createOrderForm('processed', $app['form.factory']);
        $processForm->handleRequest($request);
        if ($processForm->isValid()) {
            $processedSampleTimes = $processForm->get('processed_samples_ts')->getData();
            foreach ($processForm->get('processed_samples')->getData() as $sample) {
                if (empty($processedSampleTimes[$sample])) {
                    $processForm->get('processed_samples')->addError(new FormError('Please specify time of blood processing completion for each sample'));
                    break;
                }
            }
            if ($processForm->isValid()) {
                $updateArray = $order->getOrderUpdateFromForm('processed', $processForm);
                if (!$order->get('processed_ts')) {
                    $updateArray['processed_ts'] = (new \DateTime())->format('Y-m-d H:i:s');
                }
                if ($app['em']->getRepository('orders')->update($orderId, $updateArray)) {
                    $app->log(Log::ORDER_EDIT, $orderId);
                    $app->addFlashNotice('Order processing updated');

                    return $app->redirectToRoute('orderProcess', [
                        'participantId' => $participantId,
                        'orderId' => $orderId
                    ]);
                }
            }
        }
        return $app['twig']->render('order-process.html.twig', [
            'participant' => $order->getParticipant(),
            'order' => $order->toArray(),
            'processForm' => $processForm->createView()
        ]);
    }

    public function orderFinalizeAction($participantId, $orderId, Application $app, Request $request)
    {
        $order = $this->loadOrder($participantId, $orderId, $app);
        $finalizeForm = $order->createOrderForm('finalized', $app['form.factory']);
        $finalizeForm->handleRequest($request);
        if ($finalizeForm->isValid()) {
            $updateArray = $order->getOrderUpdateFromForm('finalized', $finalizeForm);
            if ($app['em']->getRepository('orders')->update($orderId, $updateArray)) {
                $app->log(Log::ORDER_EDIT, $orderId);
                $order = $this->loadOrder($participantId, $orderId, $app);
                $order->sendToRdr();
                $app->addFlashNotice('Order finalization updated');

                return $app->redirectToRoute('orderFinalize', [
                    'participantId' => $participantId,
                    'orderId' => $orderId
                ]);
            }
        }
        return $app['twig']->render('order-finalize.html.twig', [
            'participant' => $order->getParticipant(),
            'order' => $order->toArray(),
            'finalizeForm' => $finalizeForm->createView()
        ]);
    }

    /* For debugging generated JSON representation - only allowed in local dev */
    public function orderJsonAction($participantId, $orderId, Application $app, Request $request)
    {
        if (!$app->isLocal()) {
            $app->abort(404);
        }
        $order = $this->loadOrder($participantId, $orderId, $app);
        if ($request->query->has('rdr')) {
            if ($order->get('rdr_id')) {
                $object = $app['pmi.drc.participants']->getOrder($participantId, $order->get('rdr_id'));
            } else {
                $object = ['error' => 'Order does not have rdr_id'];
            }
        } else {
            $object = $order->getRdrObject();
        }

        return $app->json($object);
    }

    /* For dry-run testing reconciliation  */
    public function orderExportAction(Application $app)
    {
        if ($app->isProd()) {
            $app->abort(404);
        }
        $orders = $app['db']->fetchAll("SELECT finalized_ts, site, biobank_id, mayo_id FROM orders WHERE finalized_ts is not null and site != '' and biobank_id !=''");
        $skipSites = ['a', 'b'];
        $noteSites = ['7035702', '7035703', '7035704', '7035705', '7035707'];
        $stream = function() use ($orders, $skipSites, $noteSites) {
            $output = fopen('php://output', 'w');
            fputcsv($output, array('Biobank ID', 'ML Order ID', 'ML Client ID', 'Finalized (CT)', 'Notes'));
            foreach ($orders as $order) {
                $finalized = date('Y-m-d H:i:s', strtotime($order['finalized_ts']));
                if (in_array($order['site'], $skipSites)) {
                    continue;
                }
                if (!array_key_exists($order['site'], MayolinkOrder::$siteAccounts)) {
                    continue;
                }
                $clientId = MayolinkOrder::$siteAccounts[$order['site']];
                fputcsv($output, [
                    $order['biobank_id'],
                    $order['mayo_id'],
                    $clientId,
                    $finalized,
                    in_array($clientId, $noteSites) ? 'Client account was not added to our account which resulted in the client id 7035500 being used (will be fixed some time after 11/29)' : ''
                ]);
            }
            fclose($output);
        };

        $filename = 'orders_' . date('Ymd-His') . '.csv';
        return $app->stream($stream, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }
}
