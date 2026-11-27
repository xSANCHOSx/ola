<?php

declare(strict_types=1);

final class PreorderRequestData
{
    private string $productExternalId;
    private string $name;
    private string $phone;
    private string $email;

    public function __construct(string $productExternalId, string $name, string $phone, string $email = '')
    {
        $this->productExternalId = $productExternalId;
        $this->name = $name;
        $this->phone = $phone;
        $this->email = $email;
    }

    public function productExternalId(): string
    {
        return $this->productExternalId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function phone(): string
    {
        return $this->phone;
    }

    public function email(): string
    {
        return $this->email;
    }
}
