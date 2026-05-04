<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Mappers;

use Domain\Identity\Entities\RefreshToken;
use Domain\Shared\Abstracts\AbstractMapper;
use Infrastructure\Identity\Models\RefreshTokenModel;

final class RefreshTokenMapper extends AbstractMapper
{
    public function toEntity(object $model): RefreshToken
    {
        assert($model instanceof RefreshTokenModel);

        return RefreshToken::fromArray([
            'id' => $model->id,
            'userId' => $model->user_id,
            'tokenHash' => $model->token_hash,
            'familyId' => $model->family_id,
            'expiresAt' => $model->expires_at->format(\DateTimeInterface::ATOM),
            'revokedAt' => $model->revoked_at?->format(\DateTimeInterface::ATOM),
            'replacedByTokenId' => $model->replaced_by_token_id,
            'revokeReason' => $model->revoke_reason,
            'userAgent' => $model->user_agent,
            'ipAddress' => $model->ip_address,
            'createdAt' => $model->created_at->format(\DateTimeInterface::ATOM),
            'updatedAt' => $model->updated_at?->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPersistence(object $entity): array
    {
        assert($entity instanceof RefreshToken);

        return [
            'id' => $entity->getId()->value(),
            'user_id' => $entity->getUserId()->value(),
            'token_hash' => $entity->getTokenHash(),
            'family_id' => $entity->getFamilyId(),
            'expires_at' => $entity->getExpiresAt(),
            'revoked_at' => $entity->getRevokedAt(),
            'replaced_by_token_id' => $entity->getReplacedByTokenId()?->value(),
            'revoke_reason' => $entity->getRevokeReason(),
            'user_agent' => $entity->getUserAgent(),
            'ip_address' => $entity->getIpAddress(),
            'created_at' => $entity->getCreatedAt(),
            'updated_at' => $entity->getUpdatedAt(),
        ];
    }
}
