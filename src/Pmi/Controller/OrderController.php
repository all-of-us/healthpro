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
        ['orderCheck', '/participant/{participantId}/order/check'],
        ['orderCreate', '/participant/{participantId}/order/create', ['method' => 'GET|POST']],
        ['orderLabelsPdf', '/participant/{participantId}/order/{orderId}/labels.pdf'],
        ['orderRequisitionPdf', '/participant/{participantId}/order/{orderId}/requisition.pdf'],
        ['order', '/participant/{participantId}/order/{orderId}'],
        ['orderPrintLabels', '/participant/{participantId}/order/{orderId}/print/labels'],
        ['orderCollect', '/participant/{participantId}/order/{orderId}/collect', ['method' => 'GET|POST']],
        ['orderProcess', '/participant/{participantId}/order/{orderId}/process', ['method' => 'GET|POST']],
        ['orderFinalize', '/participant/{participantId}/order/{orderId}/finalize', ['method' => 'GET|POST']],
        ['orderPrintRequisition', '/participant/{participantId}/order/{orderId}/print/requisition'],
        ['orderJson', '/participant/{participantId}/order/{orderId}/order.json'],
        ['orderExport', '/orders/export.csv']
    ];

    protected function loadOrder($participantId, $orderId, Application $app)
    {
        $order = new Order();
        $order->loadOrder($participantId, $orderId, $app);
        if (!$order->isValid()) {
            $app->abort(404);
        }
        if (!$order->getParticipant()->status) {
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
        if (!$participant->status) {
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
        if (!$participant->status) {
            $app->abort(403);
        }
        if ($app->isDVType() && $request->request->has('saliva')) {
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
        if (!$app->isDVType()) {
            $formBuilder->add('samples', Type\ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => 'Select requested samples',
                'choices' => Order::$samples,
                'required' => false
            ]);
        }
        $confirmForm = $formBuilder->getForm();
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
                $orderData['user_id'] = $app->getUser()->getId();
                $orderData['site'] = $app->getSiteId();
                $orderData['participant_id'] = $participant->id;
                $orderData['biobank_id'] = $participant->biobankId;
                $orderData['created_ts'] = new \DateTime();
                if (!$app->isDVType()) {
                    $orderData['processed_centrifuge_type'] = Order::SWINGING_BUCKET;
                }
                $orderId = $app['em']->getRepository('orders')->insert($orderData);
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
            'samplesInfo' => Order::$samplesInformation
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
        if ($order->get('finalized_ts')) {
            $app->abort(403);
        }
        if (!in_array('printLabels', $order->getAvailableSteps())) {
            $app->abort(404);
        }
        if ($app->getConfig('ml_mock_order')) {
            return $app->redirect($request->getBaseUrl() . '/assets/SampleLabels.pdf');
        } else {
            $pdf = $this->getLabelsPdf($participantId, $orderId, $app);
            if ($pdf) {
                return new Response($pdf, 200, array('Content-Type' => 'application/pdf'));
            } else {
                $html = '<html><body style="font-family: Helvetica Neue,Helvetica,Arial,sans-serif"><strong>Labels pdf file could not be loaded</strong></body></html>';
                return new Response($html, 200, array('Content-Type' => 'text/html'));
            }
        }
    }

    public function orderPrintLabelsAction($participantId, $orderId, Application $app, Request $request)
    {
        $order = $this->loadOrder($participantId, $orderId, $app);
        if ($order->get('finalized_ts')) {
            $app->abort(403);
        }
        if (!in_array('printLabels', $order->getAvailableSteps())) {
            // 404 because print is not a valid route for kit orders regardless of state
            $app->abort(404);
        }
        if (!$order->get('printed_ts') && $this->getLabelsPdf($participantId, $orderId, $app)) {
            $app->log(Log::ORDER_EDIT, $orderId);
            $app['em']->getRepository('orders')->update($orderId, [
                'printed_ts' => new \DateTime()
            ]);
            $order = $this->loadOrder($participantId, $orderId, $app);
        }
        return $app['twig']->render('order-print-labels.html.twig', [
            'participant' => $order->getParticipant(),
            'order' => $order->toArray()
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
            if ($order->get('finalized_ts')) {
                $app->abort(403);
            }
            if ($type = $order->checkIdentifiers($collectForm['collected_notes']->getData())) {
                $label = Order::$identifierLabel[$type[0]];
                $collectForm['collected_notes']->addError(new FormError("Please remove participant $label \"$type[1]\""));
            }
            // Throw error if collected_ts is empty for the order which is already to sent to mayo
            if (empty($orderData['mayo_id']) && empty($collectForm['collected_ts']->getData())) {
                $collectForm['collected_ts']->addError(new FormError('Collected time cannot be empty for the order which is already sent'));
            }
            if ($collectForm->isValid()) {
                $orderData = $order->toArray();
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
                    // Send order to mayo
                    if ($request->request->has('send')){
                        $order = $this->loadOrder($participantId, $orderId, $app);
                        if ($app->getConfig('ml_mock_order')) {
                            $mayoId = $app->getConfig('ml_mock_order');
                        } else {
                            // Set collected time to user local time
                            $collectedAt = new \DateTime($order->get('collected_ts')->format('Y-m-d H:i:s'), new \DateTimeZone($app->getUserTimezone()));
                            $orderData = $order->toArray();
                            $participant = $app['pmi.drc.participants']->getById($participantId);
                            if ($site = $app['em']->getRepository('sites')->fetchOneBy(['google_group' => $app->getSiteId()])) {
                                $mayoClientId = $site['mayolink_account'];
                            } else {
                                $mayoClientId = null;
                            }
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
                                'siteId' => $app->getSiteId(),
                                'organizationId' => $app->getSiteOrganization(),
                                'collected_samples' => $orderData['collected_samples'],
                                'centrifugeType' => $orderData['processed_centrifuge_type']
                            ];
                            $mayoOrder = new MayolinkOrder($app);
                            $mayoId = $mayoOrder->createOrder(
                                $app->getConfig('ml_username'),
                                $app->getConfig('ml_password'),
                                $options
                            );                           
                        }
                        if ($mayoId) {
                            if ($app['em']->getRepository('orders')->update($orderId, ['mayo_id' => $mayoId])) {
                                $app->log(Log::ORDER_EDIT, $orderId);
                                $order = $this->loadOrder($participantId, $orderId, $app);
                                $order->sendToRdr();
                                $successMsg = 'Order collection updated and successfully sent';
                                // Redirect to print requisition
                                if ($order->get('type') !== 'kit') {
                                    $app->addFlashNotice($successMsg);
                                    return $app->redirectToRoute('orderPrintRequisition', [
                                        'participantId' => $participantId,
                                        'orderId' => $orderId
                                    ]);
                                }
                            }
                        } else {
                            $errorMsg = 'Failed to send order';
                        }
                    }
                } else {
                    // Save collected time and notes
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
            'samplesInfo' => Order::$samplesInformation
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
            if ($order->get('finalized_ts')) {
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
                if (!$order->get('processed_ts')) {
                    $updateArray['processed_ts'] = new \DateTime();
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
            'samplesInfo' => Order::$samplesInformation
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
            if ($order->get('finalized_ts')) {
                $app->abort(403);
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
                    $finalizeForm['fedex_tracking']['first']->addError(new FormError('This FedEx tracking number has already been used for another order.'));
                }
            }
            if ($finalizeForm->isValid()) {
                $updateArray = $order->getOrderUpdateFromForm('finalized', $finalizeForm);
                $updateArray['finalized_user_id'] = $app->getUser()->getId();
                $updateArray['finalized_site'] = $app->getSiteId();
                if ($app['em']->getRepository('orders')->update($orderId, $updateArray)) {
                    $app->log(Log::ORDER_EDIT, $orderId);
                    $order = $this->loadOrder($participantId, $orderId, $app);
                    $order->sendToRdr();
                    $app->addFlashSuccess('Order finalized');
                }
                return $app->redirectToRoute('orderFinalize', [
                    'participantId' => $participantId,
                    'orderId' => $orderId
                ]);
            } else {
                $finalizeForm->addError(new FormError('Please correct the errors below'));
            }
        }
        return $app['twig']->render('order-finalize.html.twig', [
            'participant' => $order->getParticipant(),
            'order' => $order->toArray(),
            'finalizeForm' => $finalizeForm->createView(),
            'samplesInfo' => Order::$samplesInformation
        ]);
    }

    public function orderPrintRequisitionAction($participantId, $orderId, Application $app, Request $request)
    {
        $order = $this->loadOrder($participantId, $orderId, $app);
        if ($order->get('finalized_ts')) {
            $app->abort(403);
        }
        if (!in_array('printRequisition', $order->getAvailableSteps())) {
            return $app->redirectToRoute('order', [
                'participantId' => $participantId,
                'orderId' => $orderId
            ]);
        }
        if (!in_array('printRequisition', $order->getAvailableSteps())) {
            // 404 because print is not a valid route for kit orders regardless of state
            $app->abort(404);
        }
        return $app['twig']->render('order-print-requisition.html.twig', [
            'participant' => $order->getParticipant(),
            'order' => $order->toArray()
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
        if ($order->get('finalized_ts')) {
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
                $pdf = $mlOrder->getRequisitionPdf(
                    $app->getConfig('ml_username'),
                    $app->getConfig('ml_password'),
                    $order->get('mayo_id')
                );
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
        if ($app->getConfig('ml_mock_order')) {
            return true;
        } else {
            $mlOrder = new MayolinkOrder($app);
            $participant = $app['pmi.drc.participants']->getById($participantId);
            $order = $this->loadOrder($participantId, $orderId, $app);
            $orderData = $order->toArray();
            // set collected time to created date at midnight local time
            $collectedAt = new \DateTime($orderData['created_ts']->format('Y-m-d'), new \DateTimeZone($app->getUserTimezone()));
            if ($site = $app['em']->getRepository('sites')->fetchOneBy(['google_group' => $app->getSiteId()])) {
                $mayoClientId = $site['mayolink_account'];
            } else {
                $mayoClientId = null;
            }
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
                'requested_samples' => $orderData['requested_samples']
            ];
            $pdf = $mlOrder->getLabelsPdf(
                $app->getConfig('ml_username'),
                $app->getConfig('ml_password'),
                $options
            );
            return $pdf;
        }
    }
}
