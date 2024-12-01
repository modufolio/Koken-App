<?php

class DDI_Email extends KokenPlugin implements KokenEmail
{
    private $stamp;
    private $base_path;

    public function __construct()
    {
        $this->register_email_handler('Built-in PHP mailer');
    }

    #[\Override]
    public function send($fromEmail, $fromName, $toEmail, $subject, $message)
    {
        require_once 'swift/swift_required.php';

        $transport = Swift_MailTransport::newInstance();
        $mailer = Swift_Mailer::newInstance($transport);

        $message = Swift_Message::newInstance($subject)
            ->setFrom([$toEmail])
            ->setReplyTo([$fromEmail => $fromName])
            ->setTo([$toEmail])
            ->setBody($message);

        $result = $mailer->send($message);
    }
}
