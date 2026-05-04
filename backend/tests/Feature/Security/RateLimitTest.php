<?php

declare(strict_types=1);

describe('Rate limiting', function () {
    it('blocks login after 5 failed attempts in 15 minutes (per email+IP)', function () {
        // Five wrong-password attempts each return 401 INVALID_CREDENTIALS
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'limited@user.local',
                'password' => "wrong-{$i}",
            ])->assertStatus(401);
        }

        // Sixth attempt is rejected by the throttle:login middleware before
        // it ever reaches the handler, so the body is Laravel's standard
        // 429 with a Retry-After header — not our DomainException envelope.
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'limited@user.local',
            'password' => 'wrong-6',
        ])->assertStatus(429);

        expect($response->headers->has('Retry-After'))->toBeTrue();
    });

    it('different email + IP combos do not share the bucket', function () {
        // Drain the bucket for one email
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'first-bucket@user.local',
                'password' => "x{$i}",
            ])->assertStatus(401);
        }

        // A different email is unaffected
        $this->postJson('/api/v1/auth/login', [
            'email' => 'second-bucket@user.local',
            'password' => 'whatever',
        ])->assertStatus(401);  // 401 not 429
    });

    it('register endpoint allows 3 attempts per IP per hour', function () {
        // Three valid registrations from same IP — all succeed
        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/api/v1/auth/register', [
                'email' => "rl-reg-{$i}@user.local",
                'password' => 'secret123',
                'fullName' => "RL Reg {$i}",
            ])->assertCreated();
        }

        // Fourth → 429
        $this->postJson('/api/v1/auth/register', [
            'email' => 'rl-reg-4@user.local',
            'password' => 'secret123',
            'fullName' => 'RL Reg 4',
        ])->assertStatus(429);
    });
});
