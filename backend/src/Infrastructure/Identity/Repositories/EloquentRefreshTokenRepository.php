<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Repositories;

use Domain\Identity\Contracts\RefreshTokenRepositoryInterface;
use Domain\Identity\Entities\RefreshToken;
use Domain\Identity\ValueObjects\RefreshTokenId;
use Domain\Shared\Contracts\ValueObjectInterface;
use Domain\Shared\ValueObjects\UserId;
use Infrastructure\Identity\Mappers\RefreshTokenMapper;
use Infrastructure\Identity\Models\RefreshTokenModel;
use Infrastructure\Shared\Abstracts\AbstractEloquentRepository;

/**
 * @extends AbstractEloquentRepository<RefreshToken, RefreshTokenId, RefreshTokenModel>
 */
final class EloquentRefreshTokenRepository extends AbstractEloquentRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(RefreshTokenMapper $mapper)
    {
        parent::__construct($mapper);
    }

    protected function getModelClass(): string
    {
        return RefreshTokenModel::class;
    }

    protected function createId(string $id): ValueObjectInterface
    {
        return RefreshTokenId::fromString($id);
    }

    public function findById(RefreshTokenId $id): ?RefreshToken
    {
        $model = $this->query()->where('id', $id->value())->first();
        return $model ? $this->mapper->toEntity($model) : null;
    }

    public function findByTokenHash(string $tokenHash): ?RefreshToken
    {
        $model = $this->query()->where('token_hash', $tokenHash)->first();
        return $model ? $this->mapper->toEntity($model) : null;
    }

    public function save(RefreshToken $token): void
    {
        $data = $this->mapper->toPersistence($token);
        RefreshTokenModel::query()->updateOrInsert(
            ['id' => $token->getId()->value()],
            $data,
        );
    }

    public function revokeFamily(string $familyId, string $reason): int
    {
        return $this->query()
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'revoke_reason' => $reason,
                'updated_at' => now(),
            ]);
    }

    public function revokeAllForUser(UserId $userId, string $reason): int
    {
        return $this->query()
            ->where('user_id', $userId->value())
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'revoke_reason' => $reason,
                'updated_at' => now(),
            ]);
    }

    public function deleteExpiredAndRevokedBefore(\DateTimeImmutable $beforeDate): int
    {
        return $this->query()
            ->where('expires_at', '<', $beforeDate)
            ->whereNotNull('revoked_at')
            ->delete();
    }
}
