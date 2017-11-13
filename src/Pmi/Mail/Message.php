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
    protected $to;
    protected $subject;
    protected $content;
    protected $method;

    public function __construct(AbstractApplication $app, $method = self::GOOGLE_MESSAGE)
    {
        $this->app = $app;
        $this->method = $method;
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

        return $this;
    }
    
    public function getContent()
    {
        return $this->content;
    }

    public function send()
    {
        $from = $this->getDefaultSender();

        switch ($this->method) {
            case self::GOOGLE_MESSAGE:
                $googleMessage = new GoogleMessage();
                $googleMessage->setSender($from);
                foreach ($this->to as $address) {
                    $googleMessage->addTo($address);
                }
                $googleMessage->setSubject($this->subject);
                $googleMessage->setTextBody($this->content);
                $googleMessage->send();
                break;

            case self::PHP_MAIL:
                $to = join(', ', $this->to);
                mail($to, $this->subject, $this->content, "From: {$from}");
                break;

            case self::MANDRILL:
                $mandrill = new Mandrill($this->app->getConfig('mandrill_key'));
                $mandrill->send($this->to, $from, $this->subject, $this->content);
                break;

            default:
                throw new \Exception('Unexpected mail message method: ' . $this->method);
        }

        if ($this->app->isLocal()) {
            syslog(LOG_INFO, "Message contents:\n---\n{$this->content}\n---\n");
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
}
