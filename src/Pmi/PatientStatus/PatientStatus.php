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

    public function getPatientStatusForm()
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

    public function save($participantId, $patientStatusId, $form)
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
}