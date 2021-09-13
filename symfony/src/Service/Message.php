<?php

namespace App\Service;

use Pmi\Mail\Mandrill;

class Message
{
    public const MANDRILL = 2;
    public const LOG_ONLY = 3;
    public const TEST_SUB_PREFIX = '[TEST] ';

    protected $env;
    protected $logger;
    protected $twig;
    protected $params;
    protected $from;
    protected $to;
    protected $subject;
    protected $content;
    protected $method;
    protected $template;

    public function __construct($env, $logger, $twig, $params)
    {
        $this->env = $env;
        $this->logger = $logger;
        $this->twig = $twig;
        $this->params = $params;
        if ($this->params->has('mail_method') && $this->params->get('mail_method') === 'mandrill') {
            $this->method = self::MANDRILL;
        } else {
            $this->method = self::LOG_ONLY;
        }
        $this->from = $this->getDefaultSender();
    }

    public function setTo($to)
    {
        if (!is_array($to)) {
            $to = [$to];
        }
        $this->to = $to;

        return $this;
    }

    public function getTo()
    {
        return $this->to;
    }

    public function setSubject($subject)
    {
        if (!$this->env->isProd()) {
            $subject = self::TEST_SUB_PREFIX . $subject;
        }
        $this->subject = $subject;

        return $this;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function setContent($content)
    {
        $this->content = $content;
        $this->template = null;

        return $this;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function send()
    {
        switch ($this->method) {
            case self::LOG_ONLY:
                $this->localLog('[suppressed]');
                break;

            case self::MANDRILL:
                $this->localLog('Mandrill');
                $tags = [
                    'healthpro',
                    $this->env->determineEnv()
                ];
                if ($this->template) {
                    $tags[] = $this->template;
                }
                $mandrill = new Mandrill($this->params->get('mandrill_key'));
                try {
                    $mandrill->send($this->to, $this->from, $this->subject, $this->content, $tags);
                } catch (\Exception $e) {
                    $this->logger->error("Error sending Mandrill message");
                    $this->logger->error($e->getMessage());
                }
                break;

            default:
                throw new \Exception('Unexpected mail message method: ' . $this->method);
        }

        return $this;
    }

    public function render($template, $parameters)
    {
        $templateFile = "emails/{$template}.txt.twig";
        $content = $this->twig->render($templateFile, $parameters);
        $regex = '/^Subject:\s*(.*)\n/';
        if (preg_match($regex, $content, $m)) {
            $content = trim(preg_replace($regex, '', $content));
            $subject = trim($m[1]);
        } else {
            $subject = '';
        }
        $this->setSubject($subject);
        $this->setContent($content);
        $this->template = $template;

        return $this;
    }

    protected function getDefaultSender()
    {
        return 'donotreply@pmi-ops.org';
    }

    protected function localLog($method)
    {
        if ($this->env->isLocal()) {
            $this->logger->log(
                'Email',
                "Sending via {$method}:\n" .
                "\tFrom: {$this->from}\n" .
                "\tTo: " . implode(', ', $this->to) . "\n" .
                "\tSubject: {$this->subject}\n" .
                "\tBody data length: " . strlen($this->content)
            );
            $this->logger->log('Email', "Message contents:\n---\n{$this->content}\n---\n");
        }
    }
}
