<?php
namespace Pmi\PatientStatus;

use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Pmi\Audit\Log;

class PatientStatus
{
    protected $app;
    protected $participantId;
    protected $patientStatusId;
    protected $organizationId;
    protected $awardeeId;
    protected $userId;
    protected $userEmail;
    protected $siteId;
    protected $siteWithPrefix;
    protected $comments;
    protected $status;
    protected $createdTs;
    protected $importId;

    public static $patientStatus = [
        'Yes: Confirmed in EHR system' => 'YES',
        'No: Not found in EHR system' => 'NO',
        'No Access: Unable to check EHR system' => 'NO_ACCESS',
        'Unknown: Inconclusive search results' => 'UNKNOWN'
    ];

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function getForm($requireComment = false)
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
                'required' => $requireComment,
                'constraints' => new Constraints\Type('string')
            ]);
        return $patientStatusForm->getForm();
    }

    public function saveData()
    {
        $patientStatusHistoryData = [
            'user_id' => $this->userId,
            'site' => $this->siteId,
            'comments' => $this->comments,
            'status' => $this->status,
            'created_ts' => $this->createdTs,
            'rdr_ts' => $this->createdTs
        ];
        $patientStatusData = [
            'participant_id' => $this->participantId,
            'organization' => $this->organizationId,
            'awardee' => $this->awardeeId
        ];
        $patientStatusRepository = $this->app['em']->getRepository('patient_status');
        $patientStatusHistoryRepository = $this->app['em']->getRepository('patient_status_history');
        $status = false;
        $patientStatusRepository->wrapInTransaction(function () use (
            $patientStatusRepository,
            $patientStatusHistoryRepository,
            $patientStatusHistoryData,
            $patientStatusData,
            &$status
        ) {
            //Create patient status if not exists
            if (!empty($this->patientStatusId)) {
                $patientStatusHistoryData['patient_status_id'] = $this->patientStatusId;
            } else {
                $id = $patientStatusRepository->insert($patientStatusData);
                $this->app->log(Log::PATIENT_STATUS_ADD, [
                    'id' => $id
                ]);
                $patientStatusHistoryData['patient_status_id'] = $id;
            }
            // Set import id if exists
            if (!empty($this->importId)) {
                $patientStatusHistoryData['import_id'] = $this->importId;
            }
            //Create patient status history
            $id = $patientStatusHistoryRepository->insert($patientStatusHistoryData);
            $this->app->log(Log::PATIENT_STATUS_HISTORY_ADD, [
                'id' => $id
            ]);

            //Update history id in patient status table
            $this->app['em']->getRepository('patient_status')->update(
                $patientStatusHistoryData['patient_status_id'],
                ['history_id' => $id]
            );

            //Log if it's a patient status edit
            if (!empty($this->patientStatusId)) {
                $this->app->log(Log::PATIENT_STATUS_EDIT, [
                    'id' => $this->patientStatusId
                ]);
            }
            $status = true;
        });
        return $status;
    }

    public function getOrgPatientStatusData($participantId)
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
            $data[0]['display_status'] = array_search($data[0]['status'], self::$patientStatus);
            return $data[0];
        }
        return null;
    }

    public function getOrgPatientStatusHistoryData($participantId, $organization)
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
            LEFT JOIN patient_status_history psh ON ps.id = psh.patient_status_id
            LEFT JOIN sites s ON psh.site = s.site_id
            LEFT JOIN users u ON psh.user_id = u.id
            WHERE ps.participant_id = :participantId
              AND ps.organization = :organization
            ORDER BY psh.id DESC
        ";
        $results = $this->app['em']->fetchAll($query, [
            'participantId' => $participantId,
            'organization' => $organization
        ]);
        if (!empty($results)) {
            foreach ($results as $key => $result) {
                $results[$key]['display_status'] = array_search($result['status'], self::$patientStatus);
            }
        }
        return $results;
    }

    public function getAwardeePatientStatusData($participantId)
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
                   u.email as user_email,
                   o.name as organization_name,
                   a.name as awardee_name
            FROM patient_status ps
            LEFT JOIN patient_status_history psh ON ps.history_id = psh.id
            LEFT JOIN sites s ON psh.site = s.site_id
            LEFT JOIN users u ON psh.user_id = u.id
            LEFT JOIN organizations o ON ps.organization = o.id
            LEFT JOIN awardees a ON ps.awardee = a.id
            WHERE ps.participant_id = :participantId
              AND ps.organization != :organization
            ORDER BY ps.id DESC
        ";
        $results = $this->app['em']->fetchAll($query, [
            'participantId' => $participantId,
            'organization' => $this->app->getSiteOrganizationId()
        ]);
        if (!empty($results)) {
            foreach ($results as $key => $result) {
                $results[$key]['display_status'] = array_search($result['status'], self::$patientStatus);
            }
        }
        return $results;
    }

    public function hasAccess($participant)
    {
        return
            !$this->app->isDVType() &&
            $participant->statusReason !== 'withdrawal' &&
            $participant->statusReason !== 'test-participant' &&
            !$this->app->isTestSite() &&
            empty($this->app->getConfig('disable_patient_status_message'));
    }

    public function loadData($participantId, $patientStatusId, $formData)
    {
        $this->participantId = $participantId;
        $this->patientStatusId = $patientStatusId;
        $this->organizationId = $this->app->getSiteOrganizationId();
        $this->awardeeId = $this->app->getSiteAwardeeId();
        $this->userId = $this->app->getUser()->getId();
        $this->userEmail = $this->app->getUser()->getEmail();
        $this->siteId = $this->app->getSiteId();
        $this->siteWithPrefix = $this->app->getSiteIdWithPrefix();
        $this->comments = $formData['comments'];
        $this->status = $formData['status'];
        $this->createdTs = new \DateTime();
    }

    public function getRdrObject()
    {
        $obj = new \StdClass();
        $obj->subject = 'Patient/' . $this->participantId;
        $obj->awardee = $this->awardeeId;
        $obj->organization = $this->organizationId;
        $obj->patient_status = $this->status;
        $obj->user = $this->userEmail;
        $obj->site = $this->siteWithPrefix;
        $obj->authored = $this->createdTs->format('Y-m-d\TH:i:s\Z');
        $obj->comment = $this->comments;
        return $obj;
    }

    public function sendToRdr()
    {
        $postData = $this->getRdrObject();
        return $this->app['pmi.drc.participants']->createPatientStatus($this->participantId, $this->organizationId, $postData);
    }

    // Used to send imported patient statuses to rdr
    public function loadDataFromImport($patientStatusHistory)
    {
        $this->participantId = $patientStatusHistory['participant_id'];
        $this->organizationId = $patientStatusHistory['organization'];
        $this->awardeeId = $patientStatusHistory['awardee'];
        $this->userEmail = $patientStatusHistory['user_email'];
        $this->siteWithPrefix = \Pmi\Security\User::SITE_PREFIX . $patientStatusHistory['site'];
        $this->comments = $patientStatusHistory['comments'];
        $this->status = $patientStatusHistory['status'];
        $this->createdTs = new \DateTime($patientStatusHistory['authored']);
        $this->siteId = $patientStatusHistory['site'];
        $this->userId = $patientStatusHistory['user_id'];
        $patientStatusId = $patientStatusHistory['patient_status_id'];
        if (empty($patientStatusId)) {
            $patientStatus = $this->app['em']->getRepository('patient_status')->fetchOneBy([
                'participant_id' => $patientStatusHistory['participant_id'],
                'organization' => $patientStatusHistory['organization']
            ]);
            if (!empty($patientStatus)) {
                $patientStatusId = $patientStatus['id'];
            }
        }
        $this->patientStatusId = $patientStatusId;
        $this->importId = $patientStatusHistory['import_id'];
    }
}
