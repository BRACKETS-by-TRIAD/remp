<?php

namespace Remp\MailerModule\Mailer;

use Nette\Mail\IMailer;
use Nette\Mail\Message;
use Remp\MailerModule\Config\Config;
use Remp\MailerModule\Repository\ConfigsRepository;

class SmtpMailer extends Mailer implements IMailer
{
    private $mailer;

    protected $options = [ 'host', 'port', 'username', 'password', 'secure' ];

    public function __construct(
        Config $config,
        ConfigsRepository $configsRepository
    )
    {
        parent::__construct($config, $configsRepository);
        $this->mailer = new \Nette\Mail\SmtpMailer($this->options);
    }

    public function send(Message $mail)
    {
        $this->mailer->send($mail);
    }
}
