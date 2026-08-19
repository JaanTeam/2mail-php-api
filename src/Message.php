<?php

declare(strict_types=1);

namespace JaanBV\TwoMail;

use DateTimeInterface;
use JaanBV\TwoMail\Exception\MessageIncompleteException;

/**
 * A message to send, built up field by field.
 *
 * The one thing worth knowing: the sender address and the sender's display name are TWO
 * arguments of one call — from($email, $name). The API also accepts the combined
 * "Acme <news@acme.com>" spelling for older clients, but sending a name in both places
 * with different values is refused (ConflictingFromNameException). Going through this
 * builder that situation cannot arise.
 *
 * Every setter returns $this, so a message reads as one statement:
 *
 *     $message = Message::create()
 *         ->from('no-reply@acme.com', 'Acme')
 *         ->to('customer@example.com')
 *         ->subject('Your order')
 *         ->html('<p>Thanks!</p>');
 */
final class Message
{
    public const KEY_FOR_FROM = 'from';
    public const KEY_FOR_FROM_NAME = 'from_name';
    public const KEY_FOR_TO = 'to';
    public const KEY_FOR_CC = 'cc';
    public const KEY_FOR_BCC = 'bcc';
    public const KEY_FOR_REPLY_TO = 'reply_to';
    public const KEY_FOR_SUBJECT = 'subject';
    public const KEY_FOR_HTML = 'html';
    public const KEY_FOR_TEXT = 'text';
    public const KEY_FOR_SEND_AT = 'send_at';
    public const KEY_FOR_TRACKING = 'tracking';
    public const KEY_FOR_TEST = 'test';

    /**
     * Across to + cc + bcc. The API enforces this too; checking here turns a wasted
     * round-trip into an immediate, local error.
     */
    public const MAXIMUM_NUMBER_OF_RECIPIENTS = 50;

    private string $from = '';

    private string $fromName = '';

    /** @var string[] */
    private array $to = [];

    /** @var string[] */
    private array $cc = [];

    /** @var string[] */
    private array $bcc = [];

    private ?string $replyTo = null;

    private string $subject = '';

    private ?string $html = null;

    private ?string $text = null;

    private ?string $sendAt = null;

    private ?bool $tracking = null;

    private bool $test = false;

    public static function create() : self
    {
        return new self();
    }

    /**
     * Sender address, and optionally the display name shown before it.
     *
     * The address domain must be one the mailbox is allowed to send from. Leave $name
     * empty and the recipient sees the bare address — the mailbox's campaign sender name
     * is deliberately not inherited, so transactional mail never goes out under a
     * marketing identity.
     */
    public function from(string $email, string $name = '') : self
    {
        $this->from = trim($email);
        $this->fromName = trim($name);

        return $this;
    }

    /**
     * Add one or more "to" recipients. Call it repeatedly, or pass several at once.
     *
     * @param string|string[] $email
     */
    public function to($email) : self
    {
        $this->to = $this->appendRecipients($this->to, $email);

        return $this;
    }

    /**
     * @param string|string[] $email
     */
    public function cc($email) : self
    {
        $this->cc = $this->appendRecipients($this->cc, $email);

        return $this;
    }

    /**
     * Blind copy. The header is stripped before delivery, so the other recipients do not
     * see these addresses.
     *
     * @param string|string[] $email
     */
    public function bcc($email) : self
    {
        $this->bcc = $this->appendRecipients($this->bcc, $email);

        return $this;
    }

    /**
     * Where replies should go, when that is not the "from" address.
     */
    public function replyTo(string $email) : self
    {
        $this->replyTo = trim($email);

        return $this;
    }

    public function subject(string $subject) : self
    {
        $this->subject = $subject;

        return $this;
    }

    public function html(string $html) : self
    {
        $this->html = $html;

        return $this;
    }

    public function text(string $text) : self
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Deliver later instead of now. Maximum 30 days out; a time in the past means "send
     * ASAP". A scheduled message can still be withdrawn with TwoMail::cancelMessage().
     */
    public function sendAt(DateTimeInterface $sendAt) : self
    {
        $this->sendAt = $sendAt->format('c');

        return $this;
    }

    /**
     * Open-tracking override for this one message. Leave it unset to use the mailbox
     * default; only applies to HTML bodies.
     */
    public function tracking(bool $tracking) : self
    {
        $this->tracking = $tracking;

        return $this;
    }

    /**
     * Validate only: every check runs — key scope, from-domain, recipients, body,
     * schedule — but nothing is queued and nothing is sent.
     *
     * Because nothing is stored, the Result has no id to look up afterwards. Handy in a
     * test suite or a staging environment that must not email real people.
     */
    public function test(bool $test = true) : self
    {
        $this->test = $test;

        return $this;
    }

    /**
     * The request body, exactly as the API expects it.
     *
     * @throws MessageIncompleteException when a required field is missing
     */
    public function toArray() : array
    {
        if ('' === $this->from) {
            throw new MessageIncompleteException('A "from" address is required — call from($email, $name).');
        }

        if ([] === $this->to) {
            throw new MessageIncompleteException('At least one "to" recipient is required — call to($email).');
        }

        if ('' === trim($this->subject)) {
            throw new MessageIncompleteException('A "subject" is required — call subject($subject).');
        }

        if (null === $this->html && null === $this->text) {
            throw new MessageIncompleteException('A body is required — call html($html) and/or text($text).');
        }

        $numberOfRecipients = count($this->to) + count($this->cc) + count($this->bcc);

        if ($numberOfRecipients > self::MAXIMUM_NUMBER_OF_RECIPIENTS) {
            throw new MessageIncompleteException(
                sprintf(
                    'Too many recipients: %1$d across to + cc + bcc, the maximum is %2$d.',
                    $numberOfRecipients,
                    self::MAXIMUM_NUMBER_OF_RECIPIENTS
                )
            );
        }

        $data = [
            self::KEY_FOR_FROM => $this->from,
            self::KEY_FOR_TO => $this->to,
            self::KEY_FOR_SUBJECT => $this->subject,
        ];

        if ('' !== $this->fromName) {
            $data[self::KEY_FOR_FROM_NAME] = $this->fromName;
        }

        if ([] !== $this->cc) {
            $data[self::KEY_FOR_CC] = $this->cc;
        }

        if ([] !== $this->bcc) {
            $data[self::KEY_FOR_BCC] = $this->bcc;
        }

        if (null !== $this->replyTo) {
            $data[self::KEY_FOR_REPLY_TO] = $this->replyTo;
        }

        if (null !== $this->html) {
            $data[self::KEY_FOR_HTML] = $this->html;
        }

        if (null !== $this->text) {
            $data[self::KEY_FOR_TEXT] = $this->text;
        }

        if (null !== $this->sendAt) {
            $data[self::KEY_FOR_SEND_AT] = $this->sendAt;
        }

        if (null !== $this->tracking) {
            $data[self::KEY_FOR_TRACKING] = $this->tracking;
        }

        if (true === $this->test) {
            $data[self::KEY_FOR_TEST] = true;
        }

        return $data;
    }

    /**
     * @param string[]        $current
     * @param string|string[] $email
     * @return string[]
     */
    private function appendRecipients(array $current, $email) : array
    {
        foreach ((array) $email as $address) {
            $address = trim((string) $address);

            if ('' === $address) {
                continue;
            }

            $current[] = $address;
        }

        return $current;
    }
}
