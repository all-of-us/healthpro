<?php

namespace App\Service;

use App\Mail\Mandrill;
use App\Mail\Sendgrid;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

class Message
{
    public const MANDRILL = 2;
    public const LOG_ONLY = 3;
    public const SENDGRID = 4;
    public const TEST_SUB_PREFIX = '[TEST] ';

    protected EnvironmentService $env;
    protected LoggerService $logger;
    protected Environment $twig;
    protected ParameterBagInterface $params;
    protected string $from;
    /** @var list<string> */
    protected array $to = [];
    protected string $subject = '';
    protected string $content = '';
    protected int $method;
    protected ?string $template = null;

    public function __construct(EnvironmentService $env, LoggerService $logger, Environment $twig, ParameterBagInterface $params)
    {
        $this->env = $env;
        $this->logger = $logger;
        $this->twig = $twig;
        $this->params = $params;
        $mailMethod = $this->params->has('mail_method') ? $this->params->get('mail_method') : null;
        $this->method = match ($mailMethod) {
            'mandrill' => self::MANDRILL,
            'sendgrid' => self::SENDGRID,
            default => self::LOG_ONLY,
        };
        $this->from = $this->getDefaultSender();
    }

    /** @param string|list<string> $to */
    public function setTo(string|array $to): self
    {
        if (!is_array($to)) {
            $to = [$to];
        }
        $this->to = array_values($to);

        return $this;
    }

    /** @return list<string> */
    public function getTo(): array
    {
        return $this->to;
    }

    public function setSubject(string $subject): self
    {
        if (!$this->env->isProd()) {
            $subject = self::TEST_SUB_PREFIX . $subject;
        }
        $this->subject = $subject;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        $this->template = null;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function send(): self
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
                    error_log($e->getMessage());
                }
                break;

            case self::SENDGRID:
                $this->localLog('Sendgrid');
                $tags = [
                    'healthpro',
                    $this->env->determineEnv()
                ];
                if ($this->template) {
                    $tags[] = $this->template;
                }
                $sendgrid = new Sendgrid((string) $this->params->get('sendgrid_key'));
                try {
                    $sendgrid->send($this->to, $this->from, $this->subject, $this->content, $tags);
                } catch (\Exception $e) {
                    error_log($e->getMessage());
                }
                break;

            default:
                throw new \Exception('Unexpected mail message method: ' . $this->method);
        }

        return $this;
    }

    /** @param array<string, mixed> $parameters */
    public function render(string $template, array $parameters): self
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

    protected function getDefaultSender(): string
    {
        return 'donotreply@pmi-ops.org';
    }

    protected function localLog(string $method): void
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
