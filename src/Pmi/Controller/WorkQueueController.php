<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Pmi\Order\Order;

class WorkQueueController extends AbstractController
{
    protected static $name = 'workqueue';
    protected static $routes = [
        ['participant', '/participant/{id}']
    ];

    public function participantAction($id, Application $app, Request $request)
    {
        $refresh = $request->query->get('refresh');
        $participant = $app['pmi.drc.participants']->getById($id, $refresh);
        if ($refresh) {
            return $app->redirectToRoute('workqueue_participant', [
                'id' => $id
            ]);
        }
        if (!$participant) {
            $app->abort(404);
        }

        if (!$app->hasRole('ROLE_AWARDEE_SCRIPPS')) {
            $app->abort(403);
        }

        // Deny access if participant awardee does not belong to the allowed awardees or not a salivary participant (awardee = UNSET and sampleStatus1SAL2 = RECEIVED)
        if (!(in_array($participant->awardee, $app->getAwardeeOrganization()) || (empty($participant->awardee) && $participant->sampleStatus1SAL2 === 'RECEIVED'))) {
            $app->abort(403);
        }

        $evaluations = $app['em']->getRepository('evaluations')->getEvaluationsWithHistory($id);

        // Internal Orders
        $orders = $app['em']->getRepository('orders')->getParticipantOrdersWithHistory($id);

        // Quanum Orders
        $quanumOrders = $app['pmi.drc.participants']->getOrdersByParticipant($participant->id);
        foreach ($quanumOrders as $quanumOrder) {
            if (in_array($quanumOrder->origin, ['careevolution'])) {
                $orders[] = (new Order($app))->loadFromJsonObject($quanumOrder)->toArray();
            }
        }

        $problems = $app['em']->getRepository('problems')->getParticipantProblemsWithCommentsCount($id);

        return $app['twig']->render('workqueue/participant.html.twig',[
            'participant' => $participant,
            'cacheEnabled' => $app['pmi.drc.participants']->getCacheEnabled(),
            'orders' => $orders,
            'evaluations' => $evaluations,
            'problems' => $problems,
            'displayPatientStatusBlock' => false,
            'readOnly' => true,
            'biobankView' => true
        ]);
    }

}
