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
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Form\FormError;
use Pmi\Audit\Log;
use Pmi\Mayolink\Order as MayoLinkOrder;
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
        ['orderFinalize', '/participant/{participantId}/order/{orderId}/finalize', ['method' => 'GET|POST']]
    ];

    protected $order;
    protected $participant;
    protected static $samples = [
        '(1) Whole Blood EDTA 4 mL [1ED04]' => '1ED04',
        '(2) Whole Blood EDTA 10 mL [1ED10]' => '1ED10',
        '(3) Serum SST 8.5 mL [1SST8]' => '1SST8',
        '(4) Plasma PST 8 mL [1PST8]' => '1PST8',
        '(5) Whole Blood EDTA 10 mL [2ED10]' => '2ED10',
        '(6) WB Sodium Heparin 4 mL [1HEP4]' => '1HEP4',
        '(7) Urine 10 mL [1UR10]' => '1UR10'
    ];
    protected static $salivaSamples = [
        'Saliva [1SAL]' => '1SAL'
    ];
    protected static $samplesRequiringProcessing = ['1SST8', '1PST8', '1SAL'];

    protected function loadOrder($participantId, $orderId, Application $app)
    {
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        $order = $app['em']->getRepository('orders')->fetchOneBy([
            'id' => $orderId,
            'participant_id' => $participantId
        ]);
        if (!$order) {
            $app->abort(404);
        }
        $this->order = $order;
        $this->participant = $participant;
    }

    protected function getOrderFormData($set)
    {
        $formData = [];
        if ($this->order["{$set}_notes"]) {
            $formData["{$set}_notes"] = $this->order["{$set}_notes"];
        };
        if ($this->order["{$set}_ts"]) {
            $formData["{$set}_ts"] = new \DateTime($this->order["{$set}_ts"]);
        } else {
            $formData["{$set}_ts"] = new \DateTime();
        }
        if ($this->order["{$set}_samples"]) {
            $samples = json_decode($this->order["{$set}_samples"]);
            if (is_array($samples) && count($samples) > 0) {
                $formData["{$set}_samples"] = $samples;
            }
        }
        return $formData;
    }

    protected function getOrderUpdateFromForm($set, $form)
    {
        $updateArray = [];
        $formData = $form->getData();
        if ($formData["{$set}_notes"]) {
            $updateArray["{$set}_notes"] = $formData["{$set}_notes"];
        } else {
            $updateArray["{$set}_notes"] = null;
        }
        if ($formData["{$set}_ts"]) {
            $updateArray["{$set}_ts"] = $formData["{$set}_ts"]->format('Y-m-d H:i:s');
        } else {
            $updateArray["{$set}_ts"] = null;
        }
        if ($formData["{$set}_samples"] && is_array($formData["{$set}_samples"])) {
            $updateArray["{$set}_samples"] = json_encode(array_values($formData["{$set}_samples"]));
        } else {
            $updateArray["{$set}_samples"] = json_encode([]);
        }
        return $updateArray;
    }

    protected function getRequestedSamples()
    {
        if ($this->order['type'] == 'saliva') {
            return self::$salivaSamples;
        }
        if ($this->order['requested_samples'] &&
            ($requestedArray = json_decode($this->order['requested_samples'])) &&
            is_array($requestedArray) &&
            count($requestedArray) > 0
        ) {
            return array_intersect(self::$samples, $requestedArray);
        } else {
            return self::$samples;
        }
    }

    protected function getEnabledSamples($set)
    {
        if ($this->order['collected_samples'] &&
            ($collectedArray = json_decode($this->order['collected_samples'])) &&
            is_array($collectedArray)
        ) {
            $collected = $collectedArray;
        } else {
            $collected = [];
        }

        if ($this->order['processed_samples'] &&
            ($processedArray = json_decode($this->order['processed_samples'])) &&
            is_array($processedArray)
        ) {
            $processed = $processedArray;
        } else {
            $processed = [];
        }

        switch ($set) {
            case 'processed':
                return array_intersect($collected, self::$samplesRequiringProcessing, $this->getRequestedSamples());
            case 'finalized':
                $enabled = array_intersect($collected, $this->getRequestedSamples());
                foreach ($enabled as $key => $sample) {
                    if (in_array($sample, self::$samplesRequiringProcessing) &&
                        !in_array($sample, $processed)
                    ) {
                        unset($enabled[$key]);
                    }
                }
                return array_values($enabled);
            default:
                return array_values($this->getRequestedSamples());
        }
    }

    protected function createOrderForm($set, $formFactory)
    {
        switch ($set) {
            case 'collected':
                $verb = 'collected';
                $noun = 'collection';
                break;
            case 'processed':
                $verb = 'processed';
                $noun = 'processing';
                break;
            case 'finalized':
                $verb = 'finalized';
                $noun = 'finalization';
                break;
            default:
                $verb = $set;
                $adjective = $verb;
                $noun = "$adjective samples";
        }
        $tsLabel = ucfirst($verb) . ' time';
        $samplesLabel = "Which samples were successfully {$verb}?";
        $notesLabel = "Additional notes on {$noun}";
        if ($set == 'finalized') {
            $samplesLabel = "Which samples are being shipped to the PMI Biobank?";
        }
        if ($set == 'processed') {
            $tsLabel = 'Time of blood processing completion';
        }

        $formData = $this->getOrderFormData($set);
        if ($set == 'processed') {
            $samples = array_intersect($this->getRequestedSamples(), self::$samplesRequiringProcessing);
        } else {
            $samples = $this->getRequestedSamples();
        }
        $enabledSamples = $this->getEnabledSamples($set);
        $form = $formFactory->createBuilder(FormType::class, $formData)
            ->add("{$set}_ts", DateTimeType::class, [
                'label' => $tsLabel,
                'date_widget' => 'single_text',
                'time_widget' => 'single_text',
                'required' => false,
                'constraints' => [
                    new Constraints\LessThanOrEqual([
                        'value' => new \DateTime(),
                        'message' => 'Timestamp cannot be in the future'
                    ])
                ]
            ])
            ->add("{$set}_samples", ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => $samplesLabel,
                'choices' => $samples,
                'required' => false,
                'choice_attr' => function($val, $key, $index) use ($enabledSamples) {
                    if (in_array($val, $enabledSamples)) {
                        return [];
                    } else {
                        return ['disabled' => true, 'class' => 'sample-disabled'];
                    }
                }
            ])
            ->add("{$set}_notes", TextareaType::class, [
                'label' => $notesLabel,
                'required' => false
            ])
            ->getForm();
        return $form;
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
            ->add('kitId', RepeatedType::class, [
                'type' => TextType::class,
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
                ]
            ])
            ->add('samples', ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => 'Select requested samples',
                'choices' => self::$samples,
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
                } else {
                    $existing = $app['em']->getRepository('orders')->fetchOneBy(['order_id' => $confirmForm['kitId']->getData()]);
                    if ($existing) {
                        $confirmForm['kitId']['first']->addError(new FormError('This order ID already exists'));
                    } else {
                        $orderData['order_id'] = $confirmForm['kitId']->getData();
                        $orderData['mayo_id'] = $confirmForm['kitId']->getData();
                        $orderData['type'] = 'kit';
                    }
                }
            } else {
                $orderData['order_id'] = Util::generateShortUuid();
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
                if ($confirmForm->isValid()) {
                    if ($app->getConfig('ml_mock_order')) {
                        $orderData['mayo_id'] = $app->getConfig('ml_mock_order');
                    } else {
                        $order = new MayoLinkOrder();
                        $options = [
                            'type' => $orderData['type'],
                            'patient_id' => $participant->getShortId(),
                            'gender' => $participant->gender,
                            'birth_date' => $participant->dob,
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
                }
            }
            if ($confirmForm->isValid()) {
                if ($orderData['mayo_id']) {
                    $orderData['participant_id'] = $participant->id;
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
        $this->loadOrder($participantId, $orderId, $app);

        $columns = [
            'print' => 'printed',
            'collect' => 'collected',
            'process' => 'processed',
            'finalize' => 'finalized'
        ];
        if ($this->order['type'] === 'kit') {
            unset($columns['print']);
        }
        foreach ($columns as $name => $column) {
            if (!$this->order["{$column}_ts"]) {
                $action = ucfirst($name);
                break;
            }
        }
        if (!isset($action)) {
            $action = 'Finalize';
        }
        return $app->redirectToRoute("order{$action}", [
            'participantId' => $this->order['participant_id'],
            'orderId' => $this->order['id']
        ]);
    }

    public function orderPdfAction($type, $participantId, $orderId, Application $app, Request $request)
    {
        if (!in_array($type, ['labels', 'requisition'])) {
            $app->abort(404);
        }
        $this->loadOrder($participantId, $orderId, $app);

        if ($app->getConfig('ml_mock_order')) {
            if ($type == 'labels') {
                return $app->redirect($request->getBaseUrl() . '/assets/SampleLabels.pdf');
            } else {
                return $app->redirect($request->getBaseUrl() . '/assets/SampleRequisition.pdf');
            }
        } else {
            $mlOrder = new MayoLinkOrder();
            $pdf = $mlOrder->loginAndGetPdf(
                $app->getConfig('ml_username'),
                $app->getConfig('ml_password'),
                $this->order['mayo_id'],
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
        $this->loadOrder($participantId, $orderId, $app);
        if (!$this->order['printed_ts']) {
            $app->log(Log::ORDER_EDIT, $orderId);
            $app['em']->getRepository('orders')->update($orderId, [
                'printed_ts' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
        }
        return $app['twig']->render('order-print.html.twig', [
            'participant' => $this->participant,
            'order' => $this->order,
            'real' => $request->query->has('real')
        ]);
    }

    public function orderCollectAction($participantId, $orderId, Application $app, Request $request)
    {
        $this->loadOrder($participantId, $orderId, $app);
        $collectForm = $this->createOrderForm('collected', $app['form.factory']);
        $collectForm->handleRequest($request);
        if ($collectForm->isValid()) {
            $updateArray = $this->getOrderUpdateFromForm('collected', $collectForm);
            if ($app['em']->getRepository('orders')->update($orderId, $updateArray)) {
                $app->log(Log::ORDER_EDIT, $orderId);
                $app->addFlashNotice('Order collection updated');

                return $app->redirectToRoute('orderCollect', [
                    'participantId' => $this->participant->id,
                    'orderId' => $orderId
                ]);
            }
        }

        return $app['twig']->render('order-collect.html.twig', [
            'participant' => $this->participant,
            'order' => $this->order,
            'collectForm' => $collectForm->createView()
        ]);
    }

    public function orderProcessAction($participantId, $orderId, Application $app, Request $request)
    {
        $this->loadOrder($participantId, $orderId, $app);
        $processForm = $this->createOrderForm('processed', $app['form.factory']);
        $processForm->handleRequest($request);
        if ($processForm->isValid()) {
            $updateArray = $this->getOrderUpdateFromForm('processed', $processForm);
            if ($app['em']->getRepository('orders')->update($orderId, $updateArray)) {
                $app->log(Log::ORDER_EDIT, $orderId);
                $app->addFlashNotice('Order processing updated');

                return $app->redirectToRoute('orderProcess', [
                    'participantId' => $this->participant->id,
                    'orderId' => $orderId
                ]);
            }
        }

        return $app['twig']->render('order-process.html.twig', [
            'participant' => $this->participant,
            'order' => $this->order,
            'processForm' => $processForm->createView()
        ]);
    }

    public function orderFinalizeAction($participantId, $orderId, Application $app, Request $request)
    {
        $this->loadOrder($participantId, $orderId, $app);
        $finalizeForm = $this->createOrderForm('finalized', $app['form.factory']);
        $finalizeForm->handleRequest($request);
        if ($finalizeForm->isValid()) {
            $updateArray = $this->getOrderUpdateFromForm('finalized', $finalizeForm);
            if ($app['em']->getRepository('orders')->update($orderId, $updateArray)) {
                $app->log(Log::ORDER_EDIT, $orderId);
                $app->addFlashNotice('Order finalization updated');

                return $app->redirectToRoute('orderFinalize', [
                    'participantId' => $this->participant->id,
                    'orderId' => $orderId
                ]);
            }
        }

        return $app['twig']->render('order-finalize.html.twig', [
            'participant' => $this->participant,
            'order' => $this->order,
            'finalizeForm' => $finalizeForm->createView()
        ]);
    }
}
