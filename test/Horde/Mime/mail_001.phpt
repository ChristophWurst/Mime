--TEST--
Horde_Mime_Mail constructor test
--FILE--
<?php

require_once 'Horde/String.php';
require_once 'Horde/Util.php';
require dirname(__FILE__) . '/mail_dummy.inc';

$mail = new Horde_Mime_Mail(array('subject' => 'My Subject',
                                  'body' => "This is\nthe body",
                                  'to' => 'recipient@example.com',
                                  'from' => 'sender@example.com',
                                  'charset' => 'iso-8859-15'));
echo $mail->send(array('type' => 'dummy'));

?>
--EXPECTF--
Subject: My Subject
To: recipient@example.com
From: sender@example.com
Message-ID: <%d.%s@mail.example.com>
User-Agent: Horde Application Framework 4.0
Date: %s, %d %s %d %d:%d:%d %s%d
Content-Type: text/plain; charset=iso-8859-15; format=flowed; DelSp=Yes
MIME-Version: 1.0
Content-Disposition: inline
Content-Transfer-Encoding: 7bit

This is
the body
