<?php

namespace Blueways\BwGuild\OperationHandler;

use Blueways\BwGuild\Domain\Model\User;
use Psr\Http\Message\ResponseInterface;
use SourceBroker\T3api\Domain\Model\ItemOperation;
use SourceBroker\T3api\Domain\Model\OperationInterface;
use SourceBroker\T3api\OperationHandler\ItemPatchOperationHandler;
use Symfony\Component\HttpFoundation\Request;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class UserPatchOperationHandler extends ItemPatchOperationHandler
{
    public static function supports(OperationInterface $operation, Request $request): bool
    {
        return $operation->getApiResource()->getEntity() === User::class && $operation->getKey() === 'patch_current';
    }

    public function handle(
        OperationInterface $operation,
        Request $request,
        array $route,
        ?ResponseInterface &$response
    ): AbstractDomainObject {
        if (empty($GLOBALS['TSFE']->fe_user->user['uid'])) {
            throw new \RuntimeException('Unknown current user ID. Are you logged in?', 1592570206680);
        }

        $route['id'] = $GLOBALS['TSFE']->fe_user->user['uid'];

        $repository = $this->getRepositoryForOperation($operation);
        $object = parent::handle($operation, $request, $route, $response);
        $this->deserializeOperation($operation, $request, $object);
        $this->validationService->validateObject($object);
        $repository->update($object);
        $this->objectManager->get(PersistenceManager::class)->persistAll();

        return $object;
    }
}

