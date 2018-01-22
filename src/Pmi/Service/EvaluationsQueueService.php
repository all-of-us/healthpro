<?php
namespace Pmi\Service;

use Pmi\Mail\Message;
use Pmi\Audit\Log;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Pmi\Evaluation\Evaluation;
use Pmi\Evaluation\Fhir;

class EvaluationsQueueService
{
    protected $app;
    protected $db;
    protected $rdr;

    public function __construct($app)
    {
        $this->app = $app;
        $this->db = $app['db'];
        $this->em = $app['em'];
        $this->rdr = $app['pmi.drc.participants'];
    }

    public function resendEvaluationsToRdr()
    {
        $limit = $this->app->getConfig('evaluation_queue_limit');
        $evaluationsQueue = $this->em->getRepository('evaluations_queue')->fetchBySql("sent_ts is null limit 0, $limit");
        foreach ($evaluationsQueue as $queue) {
            $evalId = $queue['evaluation_id'];
            $evaluationService = new Evaluation();
            $this->em->setTimezone(date_default_timezone_get());
            $evaluation = $this->em->getRepository('evaluations')->fetchOneBy(['id' => $evalId]);
            if (!$evaluation) {
                continue;
            }
            $evaluationService->loadFromArray($evaluation, $this->app);
            $fhir = $evaluationService->getFhir($evaluation['finalized_ts'], $queue['old_rdr_id']);
            if ($rdrEvalId = $this->rdr->createEvaluation($evaluation['participant_id'], $fhir)) {
                $now = new \DateTime();
                $this->em->getRepository('evaluations')->update($evaluation['id'], ['rdr_id' => $rdrEvalId, 'fhir_version' => Fhir::CURRENT_VERSION]);
                $this->em->getRepository('evaluations_queue')->update($queue['id'], ['new_rdr_id' => $rdrEvalId, 'fhir_version' => Fhir::CURRENT_VERSION, 'sent_ts' => $now]);
                $this->app->log(Log::QUEUE_RESEND_EVALUATION, [
                	'id' => $queue['id'],
                    'old_rdr_id' => $queue['old_rdr_id'],
                    'new_rdr_id' => $rdrEvalId,
                    'fhir_version' => Fhir::CURRENT_VERSION
                ]);
            } else {
            	syslog(LOG_ERR, "#{$evalId} failed sending to RDR: " .$this->rdr->getLastError());
            }
        }
    }
}