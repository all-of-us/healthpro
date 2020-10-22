<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Pmi\Evaluation\Evaluation;
use Pmi\Order\Order;

class AdminController extends AbstractController
{
    protected static $name = 'admin';

    const FIXED_ANGLE = 'fixed_angle';
    const SWINGING_BUCKET = 'swinging_bucket';
    const FULL_DATA_ACCESS = 'full_data';
    const LIMITED_DATA_ACCESS = 'limited_data';
    const DOWNLOAD_DISABLED = 'disabled';

    protected static $routes = [
        ['home', '/'],
        ['missingMeasurements', '/missing/measurements', ['method' => 'GET|POST']],
        ['missingOrders', '/missing/orders', ['method' => 'GET|POST']],
        ['patientStatusRdrJson', '/patientstatus/{participantId}/organization/{organizationId}/rdr.json', ['method' => 'GET']],
        ['patientStatusHistoryRdrJson', '/patientstatus/{participantId}/organization/{organizationId}/history/rdr.json', ['method' => 'GET']]
    ];

    public function homeAction(Application $app)
    {
        return $app['twig']->render('admin/index.html.twig');
    }

    public function missingMeasurementsAction(Application $app, Request $request, $_route)
    {
        $query = "
            SELECT e.*
            FROM evaluations e
            LEFT JOIN evaluations_history eh ON e.history_id = eh.id
            WHERE e.finalized_ts is not null
              AND e.rdr_id is null
              AND (eh.type != 'cancel'
              OR eh.type is null)
        ";
        $missing = $app['db']->fetchAll($query);
        $choices = [];
        foreach ($missing as $physicalMeasurements) {
            $choices[$physicalMeasurements['id']] = $physicalMeasurements['id'];
        }
        $form = $app['form.factory']->createBuilder(FormType::class)
            ->add('ids', Type\ChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'choices' => $choices,
                'choice_label' => false
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $ids = $form->get('ids')->getData();
            if (!empty($ids) && $form->isValid()) {
                $repository = $app['em']->getRepository('evaluations');
                foreach ($ids as $id) {
                    $evaluationService = new Evaluation();
                    $evaluation = $repository->fetchOneBy(['id' => $id]);
                    if (!$evaluation) {
                        continue;
                    }
                    $evaluationService->loadFromArray($evaluation, $app);
                    $parentRdrId = null;
                    if ($evaluation['parent_id']) {
                        $parentEvaluation = $repository->fetchOneBy(['id' => $evaluation['parent_id']]);
                        if ($parentEvaluation) {
                            $parentRdrId = $parentEvaluation['rdr_id'];
                        }
                    }
                    $fhir = $evaluationService->getFhir($evaluation['finalized_ts'], $parentRdrId);
                    if ($rdrEvalId = $app['pmi.drc.participants']->createEvaluation($evaluation['participant_id'], $fhir)) {
                        $repository->update($evaluation['id'], ['rdr_id' => $rdrEvalId, 'fhir_version' => \Pmi\Evaluation\Fhir::CURRENT_VERSION]);
                        $app->addFlashSuccess("#{$id} successfully sent to RDR");
                    } else {
                        $app->addFlashError("#{$id} failed sending to RDR: " . $app['pmi.drc.participants']->getLastError());
                    }
                }
                return $app->redirectToRoute($_route);
            } else {
                $app->addFlashError('Please select at least one physical measurements');
            }
        }
        return $app['twig']->render('admin/missing/measurements.html.twig', [
            'missing' => $missing,
            'form' => $form->createView()
        ]);
    }

    public function missingOrdersAction(Application $app, Request $request, $_route)
    {
        $query = "
            SELECT o.*
            FROM orders o
            LEFT JOIN orders_history oh ON o.history_id = oh.id
            WHERE o.finalized_ts is not null
              AND o.mayo_id is not null
              AND o.rdr_id is null
              AND (oh.type != 'cancel'
              OR oh.type is null)
        ";
        $missing = $app['db']->fetchAll($query);
        $choices = [];
        foreach ($missing as $orders) {
            $choices[$orders['id']] = $orders['id'];
        }
        $form = $app['form.factory']->createBuilder(FormType::class)
            ->add('ids', Type\ChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'choices' => $choices,
                'choice_label' => false
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $ids = $form->get('ids')->getData();
            if (!empty($ids) && $form->isValid()) {
                $repository = $app['em']->getRepository('orders');
                foreach ($ids as $id) {
                    $orderService = new Order($app);
                    $order = $repository->fetchOneBy(['id' => $id]);
                    if (!$order) {
                        continue;
                    }
                    $orderRdrObject = $orderService->getRdrObject($order);
                    if ($rdrId = $app['pmi.drc.participants']->createOrder($order['participant_id'], $orderRdrObject)) {
                        $repository->update($order['id'], ['rdr_id' => $rdrId]);
                        $app->addFlashSuccess("#{$id} successfully sent to RDR");
                    } elseif ($app['pmi.drc.participants']->getLastErrorCode() === 409) {
                        $rdrOrder = $app['pmi.drc.participants']->getOrder($order['participant_id'], $order['mayo_id']);
                        // Check if order exists in RDR
                        if (!empty($rdrOrder) && $rdrOrder->id === $order['mayo_id']) {
                            $repository->update($order['id'], ['rdr_id' => $order['mayo_id']]);
                            $app->addFlashSuccess("#{$id} successfully reconciled");
                        } else {
                            $app->addFlashError("#{$id} failed to finalize: " . $app['pmi.drc.participants']->getLastError());
                        }
                    } else {
                        $app->addFlashError("#{$id} failed sending to RDR: " . $app['pmi.drc.participants']->getLastError());
                    }
                }
                return $app->redirectToRoute($_route);
            } else {
                $app->addFlashError('Please select at least one order');
            }
        }
        return $app['twig']->render('admin/missing/orders.html.twig', [
            'missing' => $missing,
            'form' => $form->createView()
        ]);
    }

    public function patientStatusRdrJsonAction($participantId, $organizationId, Application $app)
    {
        $object = $app['pmi.drc.participants']->getPatientStatus($participantId, $organizationId);
        return $app->jsonPrettyPrint($object);
    }

    public function patientStatusHistoryRdrJsonAction($participantId, $organizationId, Application $app)
    {
        $object = $app['pmi.drc.participants']->getPatientStatusHistory($participantId, $organizationId);
        return $app->jsonPrettyPrint($object);
    }
}
