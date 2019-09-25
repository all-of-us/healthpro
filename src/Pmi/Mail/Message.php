<?php
namespace Pmi\Mail;

use google\appengine\api\mail\Message as GoogleMessage;
use google\appengine\api\app_identity\AppIdentityService;
use Pmi\Application\AbstractApplication;
use Pmi\Mail\Mandrill;

class Message
{
    const PHP_MAIL = 0;
    const GOOGLE_MESSAGE = 1;
    const MANDRILL = 2;
    const TEST_SUB_PREFIX = '[TEST] ';

    protected $app;
    protected $from;
    protected $to;
    protected $subject;
    protected $content;
    protected $method;
    protected $template;

    public function __construct(AbstractApplication $app)
    {
        switch ($app->getConfig('mail_method')) {
            case 'mandrill':
                $this->method = self::MANDRILL;
                break;
            case 'php_mail':
                $this->method = self::PHP_MAIL;
                break;
            default:
                $this->method = self::GOOGLE_MESSAGE;
        }
        $this->app = $app;
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
        if (!$this->app->isProd()) {
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
            case self::GOOGLE_MESSAGE:
                $googleMessage = new GoogleMessage();
                $googleMessage->setSender($this->from);
                foreach ($this->to as $address) {
                    $googleMessage->addTo($address);
                }
                $googleMessage->setSubject($this->subject);
                $googleMessage->setTextBody($this->content);
                $googleMessage->send();
                break;

            case self::PHP_MAIL:
                $to = join(', ', $this->to);
                mail($to, $this->subject, $this->content, "From: {$this->from}");
                break;

            case self::MANDRILL:
                $this->localLog();
                $tags = [
                    'healthpro',
                    $this->app['env']
                ];
                if ($this->template) {
                    $tags[] = $this->template;
                }
                $mandrill = new Mandrill($this->app->getConfig('mandrill_key'));
                try {
                    $mandrill->send($this->to, $this->from, $this->subject, $this->content, $tags);
                } catch (\Exception $e) {
                    $this->app['logger']->error("Error sending Mandrill message");
                    $this->app['logger']->error($e->getMessage());
                }
                break;

            default:
                throw new \Exception('Unexpected mail message method: ' . $this->method);
        }

        if ($this->app->isLocal()) {
            $this->app['logger']->info("Message contents:\n---\n{$this->content}\n---\n");
        }

        return $this;
    }

    public function render($template, $parameters)
    {
        $templateFile = "emails/{$template}.txt.twig";
        $content = $this->app['twig']->render($templateFile, $parameters);
        $regex = '/^Subject:\s*(.*)\n/';
        if (preg_match($regex, $content, $m)) {
            $content = trim(preg_replace($regex, '', $content));
            $subject = trim($m[1]);
        } else{
            $subject = '';
        }
        $this->setSubject($subject);
        $this->setContent($content);
        $this->template = $template;

        return $this;
    }

    protected function getDefaultSender()
    {
        if ($this->method === self::MANDRILL) {
            return 'donotreply@pmi-ops.org';
        } else {
            $applicationId = AppIdentityService::getApplicationId();
            return "donotreply@{$applicationId}.appspotmail.com";
        }
    }

    protected function localLog()
    {
        // Add informational log to mimic GAE mail service logging
        if ($this->app->isLocal()) {
            $this->app['logger']->info("Sending via Mandrill:\n" . 
                "\tFrom: {$this->from}\n" . 
                "\tTo: " . implode(', ', $this->to) . "\n" .
                "\tSubject: {$this->subject}\n" .
                "\tBody data length: " . strlen($this->content)
            );
        }
    }
}
