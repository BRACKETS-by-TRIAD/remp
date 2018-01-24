<?php

namespace Remp\MailerModule\Mailer;

use Mailgun\Mailgun;
use Nette\Mail\IMailer;
use Nette\Mail\Message;
use Nette\Utils\Json;
use Remp\MailerModule\Config\Config;
use Remp\MailerModule\Repository\ConfigsRepository;

class MailgunMailer extends Mailer implements IMailer
{
    private $mailer;

    protected $alias = 'remp-mailgun';

    protected $options = [ 'api_key', 'domain' ];

    public function __construct(
        Config $config,
        ConfigsRepository $configsRepository
    ) {
        parent::__construct($config, $configsRepository);
        $this->mailer = Mailgun::create($this->options['api_key']);
    }

    public function send(Message $message)
    {
        $from = null;
        foreach ($message->getFrom() as $email => $name) {
            $from = "$name <$email>";
        }

        $to = null;
        $first = true;
        foreach ($message->getHeader('To') as $email => $name) {
            $prefix = !$first ? "," : "";
            $to .= "{$prefix} {$name} <{$email}>";
            $first = false;
        }

        $attachments = [];
        foreach ($message->getAttachments() as $attachment) {
            preg_match('/(?<filename>\w+\.\w+)/', $attachment->getHeader('Content-Disposition'), $attachmentName);
            $attachments[] = [
                'fileContent' => $attachment->getBody(),
                'filename' => $attachmentName['filename'],
            ];
        }

        $mailVariables = Json::decode($message->getHeader('X-Mailer-Variables'), Json::FORCE_ARRAY);
        $tag = $message->getHeader('X-Mailer-Tag');

        $data = [
            'from' => $from,
            'to' => $to,
            'subject' => $message->getSubject(),
            'text' => $message->getBody(),
            'html' => $message->getHtmlBody(),
            'attachment' => $attachments,
            'recipient-variables' => $message->getHeader('X-Mailer-Template-Params'),
        ];
        if ($tag) {
            $data['o:tag'] = $tag;
        }
        foreach ($mailVariables as $key => $val) {
            $data["v:".$key] = $val;
        }

        $this->mailer->messages()->send($this->options['domain'], $data);
    }

    public function mailer()
    {
        return $this->mailer;
    }

    public function option($key)
    {
        return isset($this->options[$key]) ? $this->options[$key] : null;
    }

    public function transformTemplateParams($params)
    {
        $transformed = [];
        foreach ($params as $key => $value) {
            $prefix = '';
            if ($value[0] === '?') {
                $prefix = '?';
                $params[$key] = substr($value, 1);
            }
            $transformed[$key] = "{$prefix}%recipient.{$key}%";
        }
        return [$transformed, $params];
    }

    public function supportsBatch(): bool
    {
        return true;
    }
}
