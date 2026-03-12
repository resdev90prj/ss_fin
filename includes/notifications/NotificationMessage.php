<?php

final class NotificationMessage
{
    public int $userId;
    public string $to;
    public string $subject;
    public string $bodyText;
    public array $meta;

    public function __construct(
        int $userId,
        string $to,
        string $subject,
        string $bodyText,
        array $meta = []
    ) {
        $this->userId = $userId;
        $this->to = $to;
        $this->subject = $subject;
        $this->bodyText = $bodyText;
        $this->meta = $meta;
    }
}

