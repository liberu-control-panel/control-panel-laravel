<?php

namespace Liberu\Foundation\SessionsDevicesFilament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Liberu\Foundation\ApiAccess\Support\TokenPolicy;
use Liberu\Foundation\Sessions\Queries\SessionReader;

final class AccountSecurity extends Page
{
    protected string $view = 'sessions-devices-filament::pages.account-security';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Security & Preferences';

    protected static string|\UnitEnum|null $navigationGroup = 'Account & Security';

    public Collection $sessions;

    /** @var list<array<string, mixed>> */
    public array $tokens = [];

    public string $tokenName = '';

    /** @var list<string> */
    public array $tokenAbilities = ['read'];

    public ?string $newToken = null;

    public function mount(SessionReader $reader): void
    {
        $this->sessions = $reader->forActor(auth()->id(), session()->getId());
        $this->refreshTokens();
    }

    public function revoke(string $sessionId, SessionReader $reader): void
    {
        $reader->revoke(auth()->id(), $sessionId, session()->getId());
        $this->sessions = $reader->forActor(auth()->id(), session()->getId());
    }

    public function createToken(TokenPolicy $policy): void
    {
        $data = $this->validate([
            'tokenName' => ['required', 'string', 'max:255'],
            'tokenAbilities' => ['array', 'max:4'],
            'tokenAbilities.*' => ['string', 'in:read,create,update,delete'],
        ]);
        $abilities = $policy->scopes($data['tokenAbilities'] ?? [], ['read', 'create', 'update', 'delete']);
        $token = auth()->user()->createToken(
            trim($data['tokenName']),
            $abilities === [] ? ['read'] : $abilities,
            $policy->expiresAt(),
        );
        $this->newToken = $token->plainTextToken;
        $this->tokenName = '';
        $this->tokenAbilities = ['read'];
        $this->refreshTokens();
    }

    public function revokeToken(string $tokenId): void
    {
        auth()->user()->tokens()->whereKey($tokenId)->delete();
        $this->refreshTokens();
    }

    private function refreshTokens(): void
    {
        $this->tokens = auth()->user()->tokens()
            ->latest()
            ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'])
            ->map(static fn ($token): array => [
                'id' => (string) $token->getKey(),
                'name' => (string) $token->name,
                'abilities' => is_array($token->abilities) ? $token->abilities : (json_decode((string) $token->abilities, true) ?: []),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ])->all();
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
