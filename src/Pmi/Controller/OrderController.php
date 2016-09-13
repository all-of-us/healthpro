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
use Pmi\Mayolink\Order;

class OrderController extends AbstractController
{
    protected static $routes = [
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
        'Tube 1' => 1,
        'Tube 2' => 2,
        'Tube 3' => 3,
        'Tube 4' => 4,
        'Tube 5' => 5,
        'Tube 6' => 6,
        'Tube 7' => 7
    ];
    protected static $samplesRequiringProcessing = [6,7];

    protected function loadOrder($participantId, $orderId, Application $app)
    {
        $participant = $app['pmi.drc.participantsearch']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        $order = $app['db']->fetchAssoc('SELECT * FROM orders WHERE id = ? AND participant_id = ?', [$orderId, $participantId]);
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
        } else {
            $formData["{$set}_samples"] = array_values(self::$samples);
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
            $updateArray["{$set}_samples"] = json_encode($formData["{$set}_samples"]);
        } else {
            $updateArray["{$set}_samples"] = json_encode([]);
        }
        return $updateArray;
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
                return array_intersect($collected, self::$samplesRequiringProcessing);
            case 'finalized':
                $enabled = array_intersect($collected, self::$samples);
                foreach ($enabled as $key => $sample) {
                    if (in_array($sample, self::$samplesRequiringProcessing) &&
                        !in_array($sample, $processed)
                    ) {
                        unset($enabled[$key]);
                    }
                }
                return array_values($enabled);
            default:
                return array_values(self::$samples);
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

        $formData = $this->getOrderFormData($set);
        if ($set == 'processed') {
            $samples = array_intersect(self::$samples, self::$samplesRequiringProcessing);
        } else {
            $samples = self::$samples;
        }
        $enabledSamples = $this->getEnabledSamples($set);
        $form = $formFactory->createBuilder(FormType::class, $formData)
            ->add("{$set}_ts", DateTimeType::class, [
                'label' => $tsLabel,
                'date_widget' => 'single_text',
                'time_widget' => 'single_text',
                'required' => false
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

    public function orderAction($participantId, $orderId, Application $app, Request $request)
    {
        $this->loadOrder($participantId, $orderId, $app);

        $columns = [
            'print' => 'printed',
            'collect' => 'collected',
            'process' => 'processed',
            'finalize' => 'finalized'
        ];
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

        $mlOrder = new Order();
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

    public function orderPrintAction($participantId, $orderId, Application $app, Request $request)
    {
        $this->loadOrder($participantId, $orderId, $app);
        if (!$this->order['printed_ts']) {
            $app['db']->update(
                'orders',
                ['printed_ts' => (new \DateTime())->format('Y-m-d H:i:s')],
                ['id' => $orderId]
            );
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
            if ($app['db']->update('orders', $updateArray, ['id' => $orderId])) {
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
            if ($app['db']->update('orders', $updateArray, ['id' => $orderId])) {
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
            if ($app['db']->update('orders', $updateArray, ['id' => $orderId])) {
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
