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

class OrderController extends AbstractController
{
    protected static $routes = [
        ['orderCheck', '/participant/{participantId}/order/check'],
        ['orderCreate', '/participant/{participantId}/order/create', ['method' => 'POST']],
        ['orderLabelsPdf', '/participant/{participantId}/order/{orderId}/labels.pdf'],
        ['orderRequisitionPdf', '/participant/{participantId}/order/{orderId}/requisition.pdf'],
        ['order', '/participant/{participantId}/order/{orderId}'],
        ['orderPrintLabels', '/participant/{participantId}/order/{orderId}/print/labels'],
        ['orderCollect', '/participant/{participantId}/order/{orderId}/collect', ['method' => 'GET|POST']],
        ['orderProcess', '/participant/{participantId}/order/{orderId}/process', ['method' => 'GET|POST']],
        ['orderFinalize', '/participant/{participantId}/order/{orderId}/finalize', ['method' => 'GET|POST']],
        ['orderPrintRequisition', '/participant/{participantId}/order/{orderId}/print/requisition'],
        ['orderJson', '/participant/{participantId}/order/{orderId}/order.json'],
        ['orderExport', '/orders/export.csv'],
        ['orderModify', '/participant/{participantId}/order/{orderId}/modify/{type}', ['method' => 'GET|POST']]
    ];

    protected function loadOrder($participantId, $orderId, Application $app)
    {
        $order = new Order($app);
        $order->loadOrder($participantId, $orderId);
        if (!$order->isValid()) {
            $app->abort(404);
        }
        if (!$order->getParticipant()->status || $app->isTestSite()) {
            $app->abort(403);
        }

        return $order;
    }

    public function orderCheckAction($participantId, Application $app)
    {
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        if (!$participant->status || $app->isTestSite()) {
            $app->abort(403);
        }
        return $app['twig']->render('order-check.html.twig', [
            'participant' => $participant
        ]);
    }

    public function orderCreateAction($participantId, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        if (!$participant->status || $app->isTestSite() || ($app->isDVType() && $request->request->has('saliva'))) {
            $app->abort(403);
        }
        $showBloodTubes = false;
        if ($request->request->has('donate') && $request->request->has('transfusion')) {
            if ($request->request->get('donate') === 'no' && $request->request->get('transfusion') === 'no') {
                $showBloodTubes = true;
            }
        } elseif (isset($request->request->get('form')['show-blood-tubes'])) {
            $showBloodTubes = $request->request->get('form')['show-blood-tubes']; 
        } else {
            $app->abort(403);
        }
        if ($app->isDVType() && !$showBloodTubes) {
            $app->abort(403);
        }
        $formBuilder = $app['form.factory']->createBuilder(FormType::class);
        if ($app->isDVType()) {
            $formBuilder->add('kitId', Type\RepeatedType::class, [
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
            ]);
        }
        $order = new Order($app);
        if (!$app->isDVType() && $showBloodTubes) {
            $formBuilder->add('samples', Type\ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => 'Select requested samples',
                'choices' => $order->samples,
                'required' => false
            ]);
        }
        $formBuilder->add('show-blood-tubes', Type\HiddenType::class, [
            'data' => $showBloodTubes
        ]);
        $showCustom = false;
        $ordersRepository = $app['em']->getRepository('orders');
        $confirmForm = $formBuilder->getForm();
        $confirmForm->handleRequest($request);
        if ($confirmForm->isValid()) {
            $orderData = ['type' => null];
            if ($request->request->has('existing')) {
                if (empty($confirmForm['kitId']->getData())) {
                    $confirmForm['kitId']['first']->addError(new FormError('Please enter a kit order ID'));
                } elseif ($ordersRepository->fetchOneBy(['order_id' => $confirmForm['kitId']->getData()])) {
                    $confirmForm['kitId']['first']->addError(new FormError('This order ID already exists'));
                } else {
                    $orderData['order_id'] = $confirmForm['kitId']->getData();
                    $orderData['type'] = 'kit';
                }
            } else {
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
                $orderData['user_id'] = $app->getUser()->getId();
                $orderData['site'] = $app->getSiteId();
                $orderData['participant_id'] = $participant->id;
                $orderData['biobank_id'] = $participant->biobankId;
                $orderData['created_ts'] = new \DateTime();
                $orderData['version'] = $order->version;
                if (!$app->isDVType()) {
                    $orderData['processed_centrifuge_type'] = Order::SWINGING_BUCKET;
                }
                $orderId = null;
                $ordersRepository->wrapInTransaction(function() use ($ordersRepository, $order, &$orderData, &$orderId) {
                    if (!isset($orderData['order_id'])) {
                        $orderData['order_id'] = $order->generateId();
                    }
                    $orderId = $ordersRepository->insert($orderData);
                });
                if ($orderId) {
                    $app->log(Log::ORDER_CREATE, $orderId);
                    return $app->redirectToRoute('order', [
                        'participantId' => $participant->id,
                        'orderId' => $orderId
                    ]);
                }
                $app->addFlashError('Failed to create order.');
            }
        }

        return $app['twig']->render('order-create.html.twig', [
            'participant' => $participant,
            'confirmForm' => $confirmForm->createView(),
            'showCustom' => $showCustom,
            'samplesInfo' => $order->samplesInformation,
            'showBloodTubes' => $showBloodTubes,
            'version' => $order->version,
            'salivaInstructions' => $order->salivaInstructions,
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

    public function orderLabelsPdfAction($participantId, $orderId, Application $app, Request $request)
    {
        $order = $this->loadOrder($participantId, $orderId, $app);
        if ($order->isOrderDisabled()) {
            $app->abort(403);
        }
        if (!in_array('printLabels', $order->getAvailableSteps())) {
            $app->abort(404);
        }
        if ($app->getConfig('ml_mock_order')) {
            return $app->redirect($request->getBaseUrl() . '/assets/SampleLabels.pdf');
        } else {
            $result = $this->getLabelsPdf($participantId, $orderId, $app);
            if ($result['status'] === 'success') {
                return new Response($result['pdf'], 200, array('Content-Type' => 'application/pdf'));
            } else {
                $html = '<html><body style="font-family: Helvetica Neue,Helvetica,Arial,sans-serif"><strong>' . $result['errorMessage'] . '</strong></body></html>';
                return new Response($html, 200, array('Content-Type' => 'text/html'));
            }
        }
    }

    public function orderPrintLabelsAction($participantId, $orderId, Application $app)
    {
        $order = $this->loadOrder($participantId, $orderId, $app);
        if ($order->isOrderDisabled()) {
            $app->abort(403);
        }
        if (!in_array('printLabels', $order->getAvailableSteps())) {
            // 404 because print is not a valid route for kit orders regardless of state
            $app->abort(404);
        }
        $result = $this->getLabelsPdf($participantId, $orderId, $app);
        if (!$order->get('printed_ts') && $result['status'] === 'success') {
            $app->log(Log::ORDER_EDIT, $orderId);
            $app['em']->getRepository('orders')->update($orderId, [
                'printed_ts' => new \DateTime()
            ]);
            $order = $this->loadOrder($participantId, $orderId, $app);
        }
        $errorMessage = !empty($result['errorMessage']) ? $result['errorMessage'] : '';
        return $app['twig']->render('order-print-labels.html.twig', [
            'participant' => $order->getParticipant(),
            'order' => $order->toArray(),
            'processTabClass' => $order->getProcessTabClass(),
            'errorMessage' => $errorMessage
        ]);
    }

    /**
     * Save order
     * When send request is received, send order to mayo and redirect to Print Requisition tab on success
     * Allow user to save collected_ts and notes fields when mayo_id is set
     */
    public function orderCollectAction($participantId, $orderId, Application $app, Request $request)
    {
        $order = $this->loadOrder($participantId, $orderId, $app);
        if (!in_array('collect', $order->getAvailableSteps())) {
            return $app->redirectToRoute('order', [
                'participantId' => $participantId,
                'orderId' => $orderId
            ]);
        }
        $collectForm = $order->createOrderForm('collected', $app['form.factory']);
        $collectForm->handleRequest($request);
        if ($collectForm->isSubmitted()) {
            if ($order->get('finalized_ts') || $order->isOrderExpired()) {
                $app->abort(403);
            }
            if ($type = $order->checkIdentifiers($collectForm['collected_notes']->getData())) {
                $label = Order::$identifierLabel[$type[0]];
                $collectForm['collected_notes']->addError(new FormError("Please remove participant $label \"$type[1]\""));
            }

            $orderData = $order->toArray();
            // Throw error if collected_ts is empty for the order which is already sent to mayo
            if (!empty($orderData['mayo_id']) && empty($collectForm['collected_ts']->getData())) {
                $collectForm['collected_ts']->addError(new FormError('Collected time cannot be empty for the order which is already sent'));
            }
            if ($collectForm->isValid()) {
                // Check if mayo id is set
                if (empty($orderData['mayo_id'])) {
                    $updateArray = $order->getOrderUpdateFromForm('collected', $collectForm);
                    $updateArray['collected_user_id'] = $app->getUser()->getId();
                    $updateArray['collected_site'] = $app->getSiteId();
                    // Save order
                    if ($app['em']->getRepository('orders')->update($orderId, $updateArray)) {
                        $app->log(Log::ORDER_EDIT, $orderId);
                        $successMsg = 'Order collection updated';
                    }
                } else {
                    // Save collected time and notes only
                    $collectedAt = $collectForm['collected_ts']->getData();
                    $notes = $collectForm['collected_notes']->getData();
                     if ($app['em']->getRepository('orders')->update($orderId, ['collected_ts' => $collectedAt, 'collected_notes' => $notes])) {
                        $app->log(Log::ORDER_EDIT, $orderId);
                        $successMsg = 'Order collection updated';
                    }
                }
                if (!empty($successMsg)) {
                    $app->addFlashNotice($successMsg);
                }
                if (!empty($errorMsg)) {
                    $app->addFlashError($errorMsg);
                }
                return $app->redirectToRoute('orderCollect', [
                    'participantId' => $participantId,
                    'orderId' => $orderId
                ]);
            } else {
                $collectForm->addError(new FormError('Please correct the errors below'));
            }
        }
        return $app['twig']->render('order-collect.html.twig', [
            'participant' => $order->getParticipant(),
            'order' => $order->toArray(),
            'collectForm' => $collectForm->createView(),
            'samplesInfo' => $order->samplesInformation,
            'version' => $order->version,
            'processTabClass' => $order->getProcessTabClass()
        ]);
    }

    public function orderProcessAction($participantId, $orderId, Application $app, Request $request)
    {
        $order = $this->loadOrder($participantId, $orderId, $app);
        if (!in_array('process', $order->getAvailableSteps())) {
            return $app->redirectToRoute('order', [
                'participantId' => $participantId,
                'orderId' => $orderId
            ]);
        }
        $processForm = $order->createOrderForm('processed', $app['form.factory']);
        $processForm->handleRequest($request);
        if ($processForm->isSubmitted()) {
            if ($order->get('finalized_ts') || $order->isOrderExpired()) {
                $app->abort(403);
            }
            if ($processForm->has('processed_samples')) {
                $processedSampleTimes = $processForm->get('processed_samples_ts')->getData();
                foreach ($processForm->get('processed_samples')->getData() as $sample) {
                    if (empty($processedSampleTimes[$sample])) {
                        $processForm->get('processed_samples')->addError(new FormError('Please specify time of blood processing completion for each sample'));
                        break;
                    }
                }
            }
            if ($type = $order->checkIdentifiers($processForm['processed_notes']->getData())) {
                $label = Order::$identifierLabel[$type[0]];
                $processForm['processed_notes']->addError(new FormError("Please remove participant $label \"$type[1]\""));
            }
            if ($processForm->isValid()) {
                $updateArray = $order->getOrderUpdateFromForm('processed', $processForm);
                $updateArray['processed_ts'] = empty($order->get('processed_ts')) ? new \DateTime() : $order->get('processed_ts');
                // Set processed_ts to the most recent processed sample time if exists
                if (!empty($updateArray['processed_samples_ts'])) {
                    $processedSamplesTs = json_decode($updateArray['processed_samples_ts'], true);
                    if (is_array($processedSamplesTs) && !empty($processedSamplesTs)) {
                        $processedTs = new \DateTime();
                        $processedTs->setTimestamp(max($processedSamplesTs));
                        $updateArray['processed_ts'] = $processedTs;
                    }
                }
                $updateArray['processed_user_id'] = $app->getUser()->getId();
                $updateArray['processed_site'] = $app->getSiteId();
                if ($order->get('type') !== 'saliva') {
                    $site = $app['em']->getRepository('sites')->fetchOneBy([
                        'google_group' => $app->getSiteId()
                    ]);
                    if ($processForm->has('processed_centrifuge_type')) {
                        $updateArray['processed_centrifuge_type'] = $processForm['processed_centrifuge_type']->getData();
                    } elseif (!empty($site['centrifuge_type'])) {
                        $updateArray['processed_centrifuge_type'] = $site['centrifuge_type'];
                    }
                }
                if ($app['em']->getRepository('orders')->update($orderId, $updateArray)) {
                    $app->log(Log::ORDER_EDIT, $orderId);
                    $app->addFlashNotice('Order processing updated');

                    return $app->redirectToRoute('orderProcess', [
                        'participantId' => $participantId,
                        'orderId' => $orderId
                    ]);
                }
            } else {
                $processForm->addError(new FormError('Please correct the errors below'));
            }
        }
        return $app['twig']->render('order-process.html.twig', [
            'participant' => $order->getParticipant(),
            'order' => $order->toArray(),
            'processForm' => $processForm->createView(),
            'samplesInfo' => $order->samplesInformation,
            'version' => $order->version,
            'processTabClass' => $order->getProcessTabClass()
        ]);
    }

    /* Save and send order to RDR */
    public function orderFinalizeAction($participantId, $orderId, Application $app, Request $request)
    {
        $order = $this->loadOrder($participantId, $orderId, $app);
        if (!in_array('finalize', $order->getAvailableSteps())) {
            return $app->redirectToRoute('order', [
                'participantId' => $participantId,
                'orderId' => $orderId
            ]);
        }
        $finalizeForm = $order->createOrderForm('finalized', $app['form.factory']);
        $finalizeForm->handleRequest($request);
        if ($finalizeForm->isSubmitted()) {
            if ($order->get('finalized_ts') || $order->isOrderExpired()) {
                $app->abort(403);
            }
            $errors = $order->getErrors();
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $finalizeForm['finalized_samples']->addError(new FormError($error));
                }
            }
            if ($type = $order->checkIdentifiers($finalizeForm['finalized_notes']->getData())) {
                $label = Order::$identifierLabel[$type[0]];
                $finalizeForm['finalized_notes']->addError(new FormError("Please remove participant $label \"$type[1]\""));
            }
            if ($order->get('type') === 'kit' && $finalizeForm->has('fedex_tracking') && !empty($finalizeForm['fedex_tracking']->getData())) {
                $duplicateFedexTracking = $app['em']->getRepository('orders')->fetchBySql('fedex_tracking = ? and id != ?', [
                    $finalizeForm['fedex_tracking']->getData(),
                    $orderId
                ]);
                if ($duplicateFedexTracking) {
                    $finalizeForm['fedex_tracking']['first']->addError(new FormError('This tracking number has already been used for another order.'));
                }
            }
            if ($finalizeForm->isValid()) {
                $updateArray = $order->getOrderUpdateFromForm('finalized', $finalizeForm);
                $updateArray['finalized_user_id'] = $app->getUser()->getId();
                $updateArray['finalized_site'] = $app->getSiteId();

                // Unset finalized_ts for all types of orders
                unset($updateArray['finalized_ts']);

                // Finalized time will not be saved at this point
                if ($app['em']->getRepository('orders')->update($orderId, $updateArray)) {
                    $app->log(Log::ORDER_EDIT, $orderId);
                }
                $orderData = $order->toArray();
                if (empty($orderData['mayo_id']) && !empty($finalizeForm['finalized_ts']->getData())) {
                    // Check for empty finalized samples
                    if (!empty($finalizeForm['finalized_samples']->getData())) {
                        //Send order to mayo
                        $result = $this->sendOrderToMayo($participantId, $orderId, $app, 'finalized');
                        if ($result['status'] === 'success' && !empty($result['mayoId'])) {
                            //Save mayo id and finalized time
                            $newUpdateArray = [
                                'finalized_ts' => $finalizeForm['finalized_ts']->getData(),
                                'mayo_id' => $result['mayoId']
                            ];
                            $app['em']->getRepository('orders')->update($orderId, $newUpdateArray);
                        } else {
                            $app->addFlashError($result['errorMessage']);
                        }
                    } else {
                        //Save finalized time
                        $app['em']->getRepository('orders')->update($orderId, ['finalized_ts' => $finalizeForm['finalized_ts']->getData()]);
                        $app->addFlashSuccess('Order finalized');
                    }
                }
                $order = $this->loadOrder($participantId, $orderId, $app);
                //Send order to RDR if finalized_ts and mayo_id exists
                if (!empty($order->get('finalized_ts')) && !empty($order->get('mayo_id'))) {
                    $order->sendToRdr();
                    $app->addFlashSuccess('Order finalized');
                } elseif (empty($finalizeForm['finalized_ts']->getData())) {
                    $app->addFlashNotice('Order updated but not finalized');
                }
                return $app->redirectToRoute('orderFinalize', [
                    'participantId' => $participantId,
                    'orderId' => $orderId
                ]);
            } else {
                $finalizeForm->addError(new FormError('Please correct the errors below'));
            }
        }
        $hasErrors = !empty($order->getErrors()) ? true : false;
        return $app['twig']->render('order-finalize.html.twig', [
            'participant' => $order->getParticipant(),
            'order' => $order->toArray(),
            'finalizeForm' => $finalizeForm->createView(),
            'samplesInfo' => $order->samplesInformation,
            'version' => $order->version,
            'hasErrors' => $hasErrors,
            'processTabClass' => $order->getProcessTabClass()
        ]);
    }

    public function orderModifyAction($participantId, $orderId, $type, Application $app, Request $request)
    {
        $order = $this->loadOrder($participantId, $orderId, $app);
        if (!in_array($type, [$order::ORDER_CANCEL, $order::ORDER_RESTORE])
            || ($type === $order::ORDER_RESTORE and $order->get('status') !== $order::ORDER_CANCEL)
            || ($type === $order::ORDER_CANCEL and $order->get('status') !== $order::ORDER_ACTIVE)) {
            $app->abort(404);
        }
        $orders = $app['em']->getRepository('orders')->fetchBy(
            ['participant_id' => $participantId],
            ['created_ts' => 'DESC', 'id' => 'DESC']
        );
        $orderModifyForm = $order->getOrderModifyForm($type);
        $orderModifyForm->handleRequest($request);
        if ($orderModifyForm->isSubmitted()) {
            $orderModifyData = $orderModifyForm->getData();
            if ($type === $order::ORDER_CANCEL && strtolower($orderModifyData['confirm']) !== $order::ORDER_CANCEL) {
                $orderModifyForm['confirm']->addError(new FormError('Please type the word "CANCEL" to confirm'));
            }
            if ($orderModifyForm->isValid()) {
                if (isset($request->request->get('form')['confirm'])) {
                    unset($orderModifyData['confirm']);
                }
                $orderModifyData['order_id'] = $orderId;
                $orderModifyData['user_id'] = $app->getUser()->getId();
                $orderModifyData['site'] = $app->getSiteId();
                $orderModifyData['type'] = $type;
                if ($orderHistoryId = $app['em']->getRepository('orders_history')->insert($orderModifyData)) {
                    $app->log(Log::ORDER_HISTORY_CREATE, $orderHistoryId);
                    $app->addFlashSuccess("Order {$type}ed");
                    return $app->redirectToRoute('participant', [
                        'id' => $participantId
                    ]);
                }
            } else {
                $app->addFlashError('Please correct the errors below');
            }
        }
        return $app['twig']->render('order-modify.html.twig', [
            'participant' => $order->getParticipant(),
            'order' => $order->toArray(),
            'samplesInfo' => $order->getSamplesInfo(),
            'orders' => $orders,
            'orderId' => $orderId,
            'orderModifyForm' => $orderModifyForm->createView(),
            'type' => $type
        ]);
    }

    public function orderPrintRequisitionAction($participantId, $orderId, Application $app)
    {
        $order = $this->loadOrder($participantId, $orderId, $app);
        if ($order->isOrderCancelled()) {
            $app->abort(403);
        }
        if ($app->isDVType() && !in_array('printRequisition', $order->getAvailableSteps())) {
            // 404 because print is not a valid route for kit orders regardless of state
            $app->abort(404);
        }
        if (!in_array('printRequisition', $order->getAvailableSteps())) {
            return $app->redirectToRoute('order', [
                'participantId' => $participantId,
                'orderId' => $orderId
            ]);
        }

        return $app['twig']->render('order-print-requisition.html.twig', [
            'participant' => $order->getParticipant(),
            'order' => $order->toArray(),
            'processTabClass' => $order->getProcessTabClass()
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

        return $app->jsonPrettyPrint($object);
    }

    /* For dry-run testing reconciliation  */
    public function orderExportAction(Application $app)
    {
        if ($app->isProd()) {
            $app->abort(404);
        }
        $siteAccounts = [];
        foreach ($app['em']->getRepository('sites')->fetchBy([]) as $site) {
            $siteAccounts[$site['google_group']] = $site['mayolink_account'];
        }
        $orders = $app['db']->fetchAll("SELECT finalized_ts, site, biobank_id, mayo_id FROM orders WHERE finalized_ts is not null and site != '' and biobank_id !=''");
        $skipSites = ['a', 'b'];
        $noteSites = ['7035702', '7035703', '7035704', '7035705', '7035707'];
        $stream = function() use ($orders, $skipSites, $noteSites, $siteAccounts) {
            $output = fopen('php://output', 'w');
            fputcsv($output, array('Biobank ID', 'ML Order ID', 'ML Client ID', 'Finalized (CT)', 'Notes'));
            foreach ($orders as $order) {
                $finalized = date('Y-m-d H:i:s', strtotime($order['finalized_ts']));
                if (in_array($order['site'], $skipSites)) {
                    continue;
                }
                if (!array_key_exists($order['site'], $siteAccounts)) {
                    continue;
                }
                $clientId = $siteAccounts[$order['site']];
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

    public function orderRequisitionPdfAction($participantId, $orderId, Application $app, Request $request)
    {
        $order = $this->loadOrder($participantId, $orderId, $app);
        if (empty($order->get('finalized_ts')) || empty($order->get('mayo_id')) || $order->isOrderCancelled()) {
            $app->abort(403);
        }
        if (!in_array('printRequisition', $order->getAvailableSteps())) {
            $app->abort(404);
        }
        if ($app->getConfig('ml_mock_order')) {
            return $app->redirect($request->getBaseUrl() . '/assets/SampleRequisition.pdf');
        } else {
            if ($order->get('mayo_id')) {
                $mlOrder = new MayolinkOrder($app);
                $pdf = $mlOrder->getRequisitionPdf($order->get('mayo_id'));
            }
            if (!empty($pdf)) {
                return new Response($pdf, 200, array('Content-Type' => 'application/pdf'));
            } else {
                $html = '<html><body style="font-family: Helvetica Neue,Helvetica,Arial,sans-serif"><strong>Requisition pdf file could not be loaded</strong></body></html>';
                return new Response($html, 200, array('Content-Type' => 'text/html'));
            }
        }
    }

    public function getLabelsPdf($participantId, $orderId, $app)
    {
        // Always return true for mock orders
        if ($app->getConfig('ml_mock_order')) {
            return ['status' => 'success'];
        }
        $result = ['status' => 'fail'];
        $mlOrder = new MayolinkOrder($app);
        $participant = $app['pmi.drc.participants']->getById($participantId);
        $order = $this->loadOrder($participantId, $orderId, $app);
        $orderData = $order->toArray();
        // Set collected time to created date at midnight local time
        $collectedAt = new \DateTime($orderData['created_ts']->format('Y-m-d'), new \DateTimeZone($app->getUserTimezone()));
        if ($site = $app['em']->getRepository('sites')->fetchOneBy(['google_group' => $app->getSiteId()])) {
            $mayoClientId = $site['mayolink_account'];
        }
        // Check if mayo account number exists
        if (!empty($mayoClientId)) {
            $birthDate = $app->getConfig('ml_real_dob') ? $participant->dob : $participant->getMayolinkDob();
            if ($birthDate) {
                $birthDate = $birthDate->format('Y-m-d');
            }
            $options = [
                'type' => $orderData['type'],
                'biobank_id' => $participant->biobankId,
                'first_name' => '*',
                'gender' => $participant->gender,
                'birth_date' => $birthDate,
                'order_id' => $orderData['order_id'],
                'collected_at' => $collectedAt->format('c'),
                'mayoClientId' => $mayoClientId,
                'requested_samples' => $orderData['requested_samples'],
                'version' => $orderData['version'],
                'tests' => $order->samplesInformation,
                'salivaTests' => $order->salivaSamplesInformation
            ];
            $pdf = $mlOrder->getLabelsPdf($options);
            if (!empty($pdf)) {
                $result['status'] = 'success';
                $result['pdf'] = $pdf;
            } else {
                $result['errorMessage'] = 'Error loading print labels.';
            }         
        } else {
            $result['errorMessage'] = 'Mayo account number is not set for this site. Please contact the administrator.';
        }
        return $result;
    }

    public function sendOrderToMayo($participantId, $orderId, $app, $type = 'collected')
    {
        // Return mock id for mock orders
        if ($app->getConfig('ml_mock_order')) {
            return ['status' => 'success', 'mayoId' => $app->getConfig('ml_mock_order')];
        }
        $result = ['status' => 'fail'];
        $order = $this->loadOrder($participantId, $orderId, $app);
        // Set collected time to user local time
        $collectedAt = new \DateTime($order->get('collected_ts')->format('Y-m-d H:i:s'), new \DateTimeZone($app->getUserTimezone()));
        $orderData = $order->toArray();
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if ($site = $app['em']->getRepository('sites')->fetchOneBy(['google_group' => $app->getSiteId()])) {
            $mayoClientId = $site['mayolink_account'];
        }
        // Check if mayo account number exists
        if (!empty($mayoClientId)) {
            $birthDate = $app->getConfig('ml_real_dob') ? $participant->dob : $participant->getMayolinkDob();
            if ($birthDate) {
                $birthDate = $birthDate->format('Y-m-d');
            }
            $options = [
                'type' => $orderData['type'],
                'biobank_id' => $participant->biobankId,
                'first_name' => '*',
                'gender' => $participant->gender,
                'birth_date' => $birthDate,
                'order_id' => $orderData['order_id'],
                'collected_at' => $collectedAt->format('c'),
                'mayoClientId' => $mayoClientId,
                'collected_samples' => $orderData["{$type}_samples"],
                'centrifugeType' => $orderData['processed_centrifuge_type'],
                'version' => $orderData['version'],
                'tests' => $order->samplesInformation,
                'salivaTests' => $order->salivaSamplesInformation
            ];
            $mayoOrder = new MayolinkOrder($app);
            $mayoId = $mayoOrder->createOrder($options);
            if (!empty($mayoId)) {
                $result['status'] = 'success';
                $result['mayoId'] = $mayoId;
            } else {
                $result['errorMessage'] = 'An error occurred while attempting to send this order. Please try again.';
            }
        } else {
            $result['errorMessage'] = 'Mayo account number is not set for this site. Please contact the administrator.';
        }
        return $result;
    }
}
