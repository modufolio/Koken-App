<?php

interface KokenEmail
{
	public function send($fromEmail, $fromName, $toEmail, $subject, $message);
}