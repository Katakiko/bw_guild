<?php

namespace Blueways\BwGuild\OperationHandler;

use Blueways\BwGuild\Domain\Model\User;
use Psr\Http\Message\ResponseInterface;
use SourceBroker\T3api\Domain\Model\OperationInterface;
use SourceBroker\T3api\OperationHandler\ItemGetOperationHandler;
use Symfony\Component\HttpFoundation\Request;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;

class UserGetOperationHandler extends ItemGetOperationHandler
{
    public static function supports(OperationInterface $operation, Request $request): bool
    {
        return $operation->getApiResource()->getEntity() === User::class && $operation->getKey() === 'get_current';
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

        return parent::handle($operation, $request, $route, $response);
    }
}
