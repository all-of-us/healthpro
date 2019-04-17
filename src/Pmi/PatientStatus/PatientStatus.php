<?php
namespace Pmi\PatientStatus;

use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Pmi\Audit\Log;

class PatientStatus
{
    protected $app;

    public static $patientStatus = [
        'Yes' => 'YES',
        'No' => 'NO',
        'No Access' => 'NO_ACCESS',
        'Unknown' => 'UNKNOWN',
        'Not Applicable' => 'NOT_APPLICABLE'
    ];

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function getForm()
    {
        $patientStatusForm = $this->app['form.factory']->createBuilder(Type\FormType::class)
            ->add('status', Type\ChoiceType::class, [
                'label' => 'Is this participant a patient here?',
                'required' => true,
                'choices' => self::$patientStatus,
                'placeholder' => '-- Select patient status --',
                'multiple' => false,
                'constraints' => new Constraints\NotBlank([
                    'message' => 'Please select patient status'
                ])
            ])
            ->add("comments", Type\TextareaType::class, [
                'label' => 'Comments',
                'required' => false,
                'constraints' => new Constraints\Type('string')
            ]);
        return $patientStatusForm->getForm();
    }

    public function saveData($participantId, $patientStatusId, $form)
    {
        $formData = $form->getData();
        $patientStatusHistoryData = [
            'user_id' => $this->app->getUser()->getId(),
            'site' => $this->app->getSiteId(),
            'comments' => $formData['comments'],
            'status' => $formData['status'],
            'created_ts' => new \DateTime()
        ];
        $patientStatusData = [
            'participant_id' => $participantId,
            'organization' => $this->app->getSiteOrganizationId(),
            'awardee' => $this->app->getSiteAwardee()
        ];
        $patientStatusRepository = $this->app['em']->getRepository('patient_status');
        $patientStatusHistoryRepository = $this->app['em']->getRepository('patient_status_history');
        $status = false;
        $patientStatusRepository->wrapInTransaction(function () use (
            $patientStatusRepository,
            $patientStatusHistoryRepository,
            $patientStatusHistoryData,
            $patientStatusData,
            $participantId,
            $patientStatusId,
            &$status
        ) {
            if (!empty($patientStatusId)) {
                $patientStatusHistoryData['patient_status_id'] = $patientStatusId;
            } else {
                $id = $patientStatusRepository->insert($patientStatusData);
                $patientStatusHistoryData['patient_status_id'] = $id;
            }
            $id = $patientStatusHistoryRepository->insert($patientStatusHistoryData);
            $this->app->log(Log::ORDER_HISTORY_CREATE, [
                'id' => $id
            ]);

            //Update history id in patient status table
            $this->app['em']->getRepository('patient_status')->update(
                $patientStatusHistoryData['patient_status_id'],
                ['history_id' => $id]
            );
            $status = true;
        });
        return $status;
    }

    public function getData($participantId)
    {
        $query = "
            SELECT ps.id as ps_id,
                   ps.organization,
                   ps.awardee,
                   psh.id as psh_id,
                   psh.user_id,
                   psh.site,
                   psh.comments,
                   psh.status,
                   psh.created_ts,
                   s.name as site_name,
                   u.email as user_email
            FROM patient_status ps
            LEFT JOIN patient_status_history psh ON ps.history_id = psh.id
            LEFT JOIN sites s ON psh.site = s.site_id
            LEFT JOIN users u ON psh.user_id = u.id
            WHERE ps.participant_id = :participantId
              AND ps.organization = :organization
            ORDER BY ps.id DESC
        ";
        $data = $this->app['em']->fetchAll($query, [
            'participantId' => $participantId,
            'organization' => $this->app->getSiteOrganizationId()
        ]);
        if (!empty($data)) {
            $data[0]['status'] = array_search($data[0]['status'], self::$patientStatus);
        }
        return $data;
    }
}
