<?php
namespace Pmi\Service;

use Pmi\Mail\Message;
use Pmi\Audit\Log;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Pmi\Evaluation\Evaluation;

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

    public function generateEvaluationsQueueTable()
    {
        $queueFinalizeTime = $this->app->getConfig('queue_finalize_ts');
        $now = new \DateTime();
        $now = $now->format('Y-m-d H:i:s');
        if (!$this->db->query("INSERT INTO evaluations_queue (evaluation_id, evaluation_parent_id, old_rdr_id, queued_ts) SELECT id, parent_id, rdr_id, '{$now}' FROM evaluations WHERE id NOT IN (SELECT parent_id FROM evaluations WHERE parent_id IS NOT NULL) AND rdr_id IS NOT NULL AND finalized_ts < '{$queueFinalizeTime}'")) {
        	syslog(LOG_ERR, 'Failed generating evaluations queue table');
        }
    }

    public function resendEvaluationsToRdr()
    {
        $evaluationsQueue = $this->em->getRepository('evaluations_queue')->fetchBySql('sent_ts is null limit 0,20');
        foreach ($evaluationsQueue as $queue) {
            $evalId = $queue['evaluation_id'];
            $evaluationService = new Evaluation();
            $this->em->setTimezone('America/Chicago');
            $evaluation = $this->em->getRepository('evaluations')->fetchOneBy(['id' => $evalId]);
            if (!$evaluation) {
                continue;
            }
            $evaluationService->loadFromArray($evaluation, $this->app);
            $fhir = $evaluationService->getFhir($evaluation['finalized_ts'], $queue['old_rdr_id']);
            if ($rdrEvalId = $this->rdr->createEvaluation($evaluation['participant_id'], $fhir)) {
                $now = new \DateTime();
                $this->em->getRepository('evaluations')->update($evaluation['id'], ['rdr_id' => $rdrEvalId]);
                $this->em->getRepository('evaluations_queue')->update($queue['id'], ['new_rdr_id' => $rdrEvalId, 'sent_ts' => $now]);
                $this->app->log(Log::QUEUE_RESEND_EVALUATION, [
                	'id' => $queue['id'],
                    'old_rdr_id' => $queue['old_rdr_id'],
                    'new_rdr_id' => $rdrEvalId
                ]);
            } else {
            	syslog(LOG_ERR, "#{$evalId} failed sending to RDR: " .$this->rdr->getLastError());
            }
        }
    }
}