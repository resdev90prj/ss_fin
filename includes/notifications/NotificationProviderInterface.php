<?php

interface NotificationProviderInterface
{
    public function channel(): string;

    public function providerName(): string;

    public function isEnabled(): bool;

    /**
     * @return array{status:string,provider:string,error:string}
     */
    public function send(NotificationMessage $message): array;
}

