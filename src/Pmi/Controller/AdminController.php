<?php
namespace Pmi\Controller;

use Silex\Application;

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
        ['patientStatusRdrJson', '/patientstatus/{participantId}/organization/{organizationId}/rdr.json', ['method' => 'GET']],
        ['patientStatusHistoryRdrJson', '/patientstatus/{participantId}/organization/{organizationId}/history/rdr.json', ['method' => 'GET']]
    ];

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
