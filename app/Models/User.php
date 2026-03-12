<?php

namespace App\Models;

class User
{
    public int $id;
    public string $name;
    public string $email;
    public string $password;
    public ?string $phone;
    public string $role;
    public string $created_at;
}
