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
        $this->mailketingApiCallback = $smailketingApiCallback;
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
        $smtpEmail = NULL;
        //$config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $this->apiKey);
        //$smtpApiInstance = new SMTPApi(new Client(), $config);
        $data = [];
        $data['api_token']=$this->apiKey;

        $this->message = $message;
        $metadata = $this->getMetadata();
        $mauticTokens = $mergeVars = $mergeVarPlaceholders = [];
        $tokens = [];

        // Mailketing uses {NAME} for tokens so Mautic's need to be converted.
        if (!empty($metadata)) {
            $metadataSet = reset($metadata);
            $tokens = (!empty($metadataSet['tokens'])) ? $metadataSet['tokens'] : [];
            $mauticTokens = array_keys($tokens);

            $mergeVars = $mergeVarPlaceholders = [];
            foreach ($mauticTokens as $token) {
                $mergeVars[$token] = strtoupper(preg_replace('/[^a-z0-9]+/i', '', $token));
                $mergeVarPlaceholders[$token] = '{'.$mergeVars[$token].'}';
            }
        }

        $message = $this->messageToArray($mauticTokens, $mergeVarPlaceholders, true);
        if (empty($message['subject'])) {
            throw new Exception($this->translator->trans('mautic.email.subject.notblank', [], 'validators'));
        }

        if (empty($message['html'])) {
            throw new Exception($this->translator->trans('mautic.email.html.notblank', [], 'validators'));

        }
        $data['subject'] = $message['subject'];
        $data['content'] = $message['html'];
        $data['from_name'] = $message['from']['name'];
        $data['from_email'] = $message['from']['email'];
        if (!empty($message['headers'])) {
            $data['headers'] = $message['headers'];
        }

        if (isset($message['headers']['X-MC-Tags'])) {
            $data['tags'] = explode(',', $message['headers']['X-MC-Tags']);
        }
        $attachments = $this->message->getAttachments();
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (stream_is_local($attachment['filePath'])) {
                    /**$fileContent = file_get_contents($attachment['filePath']);

                    // Breaks current iteration if content of the local file
                    // is wrong.
                    if (!$fileContent) {
                        continue;
                    }

                    $data['attachment'][] = new SendSmtpEmailAttachment([
                        'name' => $attachment['fileName'],
                        'content' => base64_encode($fileContent),
                    ]);**/
                }
                else {
                    $data['attachment'][] = new SendSmtpEmailAttachment([
                        'name' => $attachment['fileName'],
                        'url' => $attachment['filePath'],
                    ]);
                }
            }
        }


        foreach ($message['recipients']['to'] as $to) {
          $data['recipient']='';
          try {
            $data['recipient']=$to['email'];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,"https://app.mailketing.id/api/v1/send");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $output = curl_exec ($ch);
            //print_r($output);
            curl_close ($ch);
          $result++;
          } catch (Exception $e) {
              $this->throwException($e->getMessage());
          }
        }

        return $result;
    }

}
