<?php

namespace MauticPlugin\MauticMailketingBundle\Swiftmailer\Transport;

use Exception;
use Mautic\EmailBundle\Swiftmailer\Transport\AbstractTokenArrayTransport;
use Mautic\EmailBundle\Swiftmailer\Transport\CallbackTransportInterface;
use Mautic\EmailBundle\Swiftmailer\Transport\TokenTransportInterface;
use MauticPlugin\MauticMailketingBundle\Swiftmailer\Callback\MailketingApiCallback;
use Swift_Message;
use Swift_Mime_Message;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Client;
use Symfony\Component\Translation\TranslatorInterface;
use Mautic\EmailBundle\Model\TransportCallback;
use Mautic\LeadBundle\Entity\DoNotContact;
use Psr\Log\LoggerInterface;

/**
 * Class MailketingApiTransport.
 */
class MailketingApiTransport extends AbstractTokenArrayTransport implements \Swift_Transport, TokenTransportInterface, CallbackTransportInterface
{

    /**
     * @var string|null
     */
    protected $apiKey;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var bool
     */
    protected $started = false;

    /**
     * @var MailketingApiCallback
     */
    protected $mailketingApiCallback;

    /**
     * MailketingApiTransport constructor.
     *
     * @param $apiKey
     * @param TranslatorInterface $translator
     * @param MailketingApiCallback $mailketingApiCallback
     */
    public function __construct($apiKey, TranslatorInterface $translator, MailketingApiCallback $mailketingApiCallback)
    {
        $this->apiKey = $apiKey;
        $this->translator = $translator;
        $this->mailketingApiCallback = $mailketingApiCallback;
    }

    /**
     * {@inheritdoc}
     */
    public function getCallbackPath()
    {
        return 'mailketing_api';
    }

    /**
     * {@inheritdoc}
     */
    public function processCallbackRequest(Request $request)
    {
        $this->mailketingApiCallback->processCallbackRequest($request);
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxBatchLimit()
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getBatchRecipientCount(Swift_Message $message, $toBeAdded = 1, $type = 'to')
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function start()
    {
        if (empty($this->apiKey)) {
            $this->throwException($this->translator->trans('mailketing.email.api_key_required', [], 'validators'));
        }

        $this->started = true;
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_Message $message, &$failedRecipients = null)
    {
        $result = 0;
        if ($event = $this->getDispatcher()->createSendEvent($this, $message)) {
            $this->getDispatcher()->dispatchEvent($event, 'beforeSendPerformed');
            if ($event->bubbleCancelled()) {
                return 0;
            }
        }
$mailketingMessage = $this->getMailketingMessage($message);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"https://app.mailketing.id/api/v2/send");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($mailketingMessage));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec ($ch);
        //print_r($output);
        curl_close ($ch);

      $result++;
      return $result;
    }


    public function getMailketingMessage(Swift_Mime_Message $message)
    {
        $tags      = [];
        $inlineCss = null;

        $this->message = $message;
        $metadata      = $this->getMetadata();
        $mauticTokens  = $mergeVars = $mergeVarPlaceholders = [];

        // mailketing uses {{ name }} for tokens so Mautic's need to be converted; although using their {{{ }}} syntax to prevent HTML escaping
        if (!empty($metadata)) {
            $metadataSet  = reset($metadata);
            $tokens       = (!empty($metadataSet['tokens'])) ? $metadataSet['tokens'] : [];
            $mauticTokens = array_keys($tokens);

            $mergeVars = $mergeVarPlaceholders = [];
            foreach ($mauticTokens as $token) {
                $mergeVars[$token]            = strtoupper(preg_replace('/[^a-z0-9]+/i', '', $token));
                $mergeVarPlaceholders[$token] = '{{{ '.$mergeVars[$token].' }}}';
            }
        }

        $message = $this->messageToArray($mauticTokens, $mergeVarPlaceholders, true);

        // mailketing requires a subject
        if (empty($message['subject'])) {
            throw new \Exception($this->translator->trans('mautic.email.subject.notblank', [], 'validators'));
        }

        if (isset($message['headers']['X-MC-InlineCSS'])) {
            $inlineCss = $message['headers']['X-MC-InlineCSS'];
        }
        if (isset($message['headers']['X-MC-Tags'])) {
            $tags = explode(',', $message['headers']['X-MC-Tags']);
        }

        $recipients = [];
        foreach ($message['recipients']['to'] as $to) {
            $recipient = [
                'address'           => $to,
                'substitution_data' => [],
                'metadata'          => [],
            ];

            if (isset($metadata[$to['email']]['tokens'])) {
                foreach ($metadata[$to['email']]['tokens'] as $token => $value) {
                    $recipient['substitution_data'][$mergeVars[$token]] = $value;
                }

                unset($metadata[$to['email']]['tokens']);
                $recipient['metadata'] = $metadata[$to['email']];
            }


            if (empty($recipient['substitution_data'])) {
                $recipient['substitution_data'] = new \stdClass();
            }

            // mailketing doesn't like empty metadata
            if (empty($recipient['metadata'])) {
                unset($recipient['metadata']);
            }

            $recipients[] = $recipient;


            foreach (['cc', 'bcc'] as $copyType) {
                if (!empty($message['recipients'][$copyType])) {
                    foreach ($message['recipients'][$copyType] as $email => $content) {
                        $copyRecipient = [
                            'address'   => ['email' => $email],
                            'header_to' => $to['email'],
                        ];

                        if (!empty($recipient['substitution_data'])) {
                            $copyRecipient['substitution_data'] = $recipient['substitution_data'];
                        }

                        $recipients[] = $copyRecipient;
                    }
                }
            }
        }

        $content = [
            'from_name'    => (!empty($message['from']['name'])) ? $message['from']['name'] : $message['from']['email'],
                'from_email'    => $message['from']['email'],
            'subject' => $message['subject'],
        ];

        if (!empty($message['headers'])) {
            $content['headers'] = $message['headers'];
        }

        // mailketing will set parts regardless if they are empty or not
        if (!empty($message['html'])) {
            $content['html'] = $message['html'];
        }

        if (!empty($message['text'])) {
            $content['text'] = $message['text'];
        }

        // Add Reply To
        if (isset($message['replyTo'])) {
            $content['reply_to'] = $message['replyTo']['email'];
        }

        $encoder = new \Swift_Mime_ContentEncoder_Base64ContentEncoder();
        foreach ($this->message->getChildren() as $child) {
            if ($child instanceof \Swift_Image) {
                $content['inline_images'][] = [
                    'type' => $child->getContentType(),
                    'name' => $child->getId(),
                    'data' => $encoder->encodeString($child->getBody()),
                ];
            }
        }

        $mailketingMessage = [
            'content'    => $content,
            'recipients' => $recipients,
            'inline_css' => $inlineCss,
            'tags'       => $tags,
        ];

        if (!empty($message['attachments'])) {
            foreach ($message['attachments'] as $key => $attachment) {
                $message['attachments'][$key]['data'] = $attachment['content'];
                unset($message['attachments'][$key]['content']);
            }
            $mailketingMessage['content']['attachments'] = $message['attachments'];
        }

        $mailketingMessage['api_token']=$this->apiKey;

        return $mailketingMessage;
    }

}
