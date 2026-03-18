<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_user_model_implements_email_verification_contract(): void
    {
        $this->assertInstanceOf(MustVerifyEmail::class, new User);
    }

    public function test_password_attribute_is_hashed_on_assignment(): void
    {
        $user = new User;
        $user->password = 'plain-password';

        $this->assertNotSame('plain-password', $user->password);
        $this->assertTrue(password_verify('plain-password', $user->password));
    }
}
