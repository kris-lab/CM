<?php

class CM_Mail_MailableTest extends CMTest_TestCase {

    public function tearDown() {
        CMTest_TH::clearEnv();
    }

    public function testWithTemplate() {
        $user = $this->getMockUser();
        $templateVariables = array('foo' => 'bar');
        $msg = new CM_Mail_ExampleMailable($user, $templateVariables);
        list($subject, $html, $text) = $msg->render();
        $this->assertNotEmpty($subject);
        $this->assertNotEmpty($html);
        $this->assertNotEmpty($text);
    }

    public function testNoTemplate() {
        $msg = new CM_Mail_Mailable('foo@example.com');

        $exception = $this->catchException(function () use ($msg) {
            $msg->send();
        });
        $this->assertInstanceOf('CM_Exception_Invalid', $exception);
        /** @var CM_Exception_Invalid $exception */
        $this->assertSame('Trying to render mail with neither subject nor template', $exception->getMessage());

        $msg->setSubject('blabla');
        $exception = $this->catchException(function () use ($msg) {
            $msg->send();
        });
        $this->assertInstanceOf('CM_Exception_Invalid', $exception);
        /** @var CM_Exception_Invalid $exception */
        $this->assertSame('Mail has neither text nor html content', $exception->getMessage());

        $msg->getMessage()->setBodyWithAlternative('Hello (http://www.foo.bar)', '<a href="http://www.foo.bar">Hello</a>');

        list($subject, $html, $text) = $msg->render();
        $this->assertEquals('blabla', $subject);
        $this->assertEquals('<a href="http://www.foo.bar">Hello</a>', $html);
        $this->assertEquals('Hello (http://www.foo.bar)', $text);
    }

    public function testRenderTranslated() {
        $site = $this->getMockSite(null, null, [
            'url' => 'http://www.foo.com',
        ]);
        $recipient = $this->getMockUser(null, $site);
        $mail = new CM_Mail_ExampleMailable($recipient);
        $language = CM_Model_Language::create('Test language', 'foo', true);
        $language->setTranslation('Welcome to {$siteName}!', 'foo');

        list($subject, $html, $text) = $mail->render();
        $nodeList = new CM_Dom_NodeList($html);

        $this->assertContains('foo', $nodeList->getText());

        $nodeLink = $nodeList->find('a');
        $this->assertSame(1, $nodeLink->count());
        $this->assertSame('http://www.foo.com/example', $nodeLink->getAttribute('href'));
        $this->assertSame('Example Page', $nodeLink->getText());
        $this->assertContains('border-style:solid;', $nodeLink->getAttribute('style'));
    }

    public function testSend() {
        $transport = $this->mockInterface('Swift_Transport')->newInstance();
        $sendMethod = $transport->mockMethod('send')->set(1);
        $mailer = new CM_Mail_Mailer($transport);

        $mail = new CM_Mail_Mailable('foo@example.com', null, null, $mailer);
        $mail->setSender('sender@example.com', 'Sender');
        $mail->setSubject('testSubject');
        $mail->addReplyTo('foo@bar.com');
        $mail->addCc('foo@bar.org', 'foobar');
        $mail->addBcc('foo@bar.net');
        $mail->addCustomHeader('X-Foo', 'bar');
        $mail->addCustomHeader('X-Bar', 'foo');
        $mail->addCustomHeader('X-Foo', 'foo');
        $mail->getMessage()->setBodyWithAlternative('content', '<b>content</b>');

        $message = $mail->getMessage();
        $this->assertSame(['sender@example.com' => 'Sender'], $message->getSender());
        $this->assertSame(['sender@example.com' => 'Sender'], $message->getFrom());
        $this->assertSame('testSubject', $message->getSubject());
        $this->assertSame('content', $message->getText());
        $this->assertSame('<b>content</b>', $message->getHtml());
        $this->assertSame(['foo@bar.com' => null], $message->getReplyTo());
        $this->assertSame(['foo@bar.org' => 'foobar'], $message->getCc());
        $this->assertSame(['foo@bar.net' => null], $message->getBcc());
        $this->assertSame('foo', $message->getHeaders()->get('X-Bar')->getFieldBody());
        $this->assertSame('bar', $message->getHeaders()->get('X-Foo', 0)->getFieldBody());
        $this->assertSame('foo', $message->getHeaders()->get('X-Foo', 1)->getFieldBody());

        $mail->send();
        // TODO: https://github.com/cargomedia/cm/pull/2305 needed
        // $this->assertSame(1, $sendMethod->getCallCount());
    }

    public function testGetRender() {
        $site = $this->getMockSite();
        $mail = new CM_Mail_Mailable(null, null, $site);
        $this->assertEquals($site, $mail->getRender()->getSite());
    }

    public function testGetRenderRecipient() {
        $site = $this->getMockSite();
        $recipient = $this->getMockUser('foo@example.com', $site);
        $mail = new CM_Mail_Mailable($recipient);
        $this->assertEquals($site, $mail->getRender()->getSite());
    }

    public function testGetRenderDefault() {
        $mail = new CM_Mail_Mailable();
        $this->assertEquals(CM_Site_Abstract::factory(), $mail->getRender()->getSite());
    }

    public function testGetSite() {
        $site = $this->getMockSite();
        $mail = new CM_Mail_Mailable(null, null, $site);
        $this->assertEquals($site, $mail->getSite());
    }

    public function testGetSiteDefault() {
        $mail = new CM_Mail_Mailable();
        $this->assertEquals(CM_Site_Abstract::factory(), $mail->getSite());
    }

    public function testGetSiteRecipient() {
        $site = $this->getMockSite();
        $recipient = $this->getMockUser('foo@example.com', $site);
        $mail = new CM_Mail_Mailable($recipient);
        $this->assertEquals($site, $mail->getSite());
    }

    /**
     * @param string|null           $email
     * @param CM_Site_Abstract|null $site
     * @return CM_Model_User|\Mocka\AbstractClassTrait
     */
    public function getMockUser($email = null, CM_Site_Abstract $site = null) {
        $email = null === $email ? 'foo@example.com' : $email;
        $site = null === $site ? $this->getMockSite() : $site;
        $user = $this->getMock('CM_Model_User', array('getEmail', 'getSite'), array(CMTest_TH::createUser()->getId()));
        $user->expects($this->any())->method('getEmail')->will($this->returnValue($email));
        $user->expects($this->any())->method('getSite')->will($this->returnValue($site));
        return $user;
    }
}