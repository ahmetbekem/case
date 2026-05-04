<?php

declare(strict_types=1);

namespace Application\Identity\Handlers\Queries;

use Application\Identity\DTOs\UserDTO;
use Application\Identity\Queries\GetCurrentUserQuery;
use Application\Shared\Abstracts\AbstractQueryHandler;
use Application\Shared\Contracts\QueryInterface;
use Domain\Identity\Contracts\UserRepositoryInterface;
use Domain\Identity\Exceptions\UserNotFoundException;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

final class GetCurrentUserHandler extends AbstractQueryHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    public static function getHandledQueryClass(): string
    {
        return GetCurrentUserQuery::class;
    }

    /**
     * @throws UserNotFoundException
     */
    protected function doHandle(QueryInterface $query): UserDTO
    {
        assert($query instanceof GetCurrentUserQuery);

        $userId = UserId::fromString($query->getUserId());
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            throw UserNotFoundException::withId($query->getUserId());
        }

        return UserDTO::fromEntity($user);
    }
}
