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

class OrderController extends AbstractController
{
    protected static $routes = [
        ['order', '/participant/{participantId}/order/{orderId}'],
        ['orderPdf', '/participant/{participantId}/order/{orderId}.pdf'],
        ['orderPrint', '/participant/{participantId}/order/{orderId}/print'],
        ['orderCollect', '/participant/{participantId}/order/{orderId}/collect', ['method' => 'GET|POST']],
        ['orderProcess', '/participant/{participantId}/order/{orderId}/process', ['method' => 'GET|POST']],
        ['orderFinalize', '/participant/{participantId}/order/{orderId}/finalize', ['method' => 'GET|POST']]
    ];

    protected $order;
    protected $participant;

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
            $updateArray["{$set}_samples"] = json_encode($formData["{$set}_samples"]);
        } else {
            $updateArray["{$set}_samples"] = null;
        }
        return $updateArray;
    }

    protected function createOrderForm($set, $formFactory)
    {
        $formData = $this->getOrderFormData($set);
        $collectForm = $formFactory->createBuilder(FormType::class, $formData)
            ->add("{$set}_ts", DateTimeType::class, [
                'label' => 'Collected time',
                'date_widget' => 'single_text',
                'time_widget' => 'single_text',
                'required' => false
            ])
            ->add("{$set}_samples", ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => 'Which samples were successfully collected?',
                'choices' => array_combine(range(1,7), range(1,7)),
                'required' => false
            ])
            ->add("{$set}_notes", TextareaType::class, [
                'label' => 'Additional notes on collection',
                'required' => false
            ])
            ->getForm();
        return $collectForm;
    }

    public function orderAction($participantId, $orderId, Application $app, Request $request)
    {
        $this->loadOrder($participantId, $orderId, $app);

        // TODO: redirect to current step based on order status
        return $app->redirectToRoute('orderPrint', [
            'participantId' => $this->order['participant_id'],
            'orderId' => $this->order['id']
        ]);
    }

    public function orderPdfAction($participantId, $orderId, Application $app, Request $request)
    {
        $this->loadOrder($participantId, $orderId, $app);

        $mlOrder = new Order();
        $pdf = $mlOrder->loginAndGetPdf(
            $app->getConfig('ml_username'),
            $app->getConfig('ml_password'),
            $this->order['mayo_id']
        );

        return new Response($pdf, 200, array('Content-Type' => 'application/pdf'));
    }

    public function orderPrintAction($participantId, $orderId, Application $app, Request $request)
    {
        $this->loadOrder($participantId, $orderId, $app);

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
