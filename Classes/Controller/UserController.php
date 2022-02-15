<?php

namespace Blueways\BwGuild\Controller;

use Blueways\BwGuild\Domain\Model\Dto\UserDemand;
use Blueways\BwGuild\Domain\Model\User;
use Blueways\BwGuild\Domain\Repository\CategoryRepository;
use Blueways\BwGuild\Domain\Repository\UserRepository;
use Blueways\BwGuild\Property\TypeConverter\UploadedFileReferenceConverter;
use Blueways\BwGuild\Service\AccessControlService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Exception\Page\PageNotFoundException;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\MetaTag\MetaTagManagerRegistry;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Validation\Validator\GenericObjectValidator;
use TYPO3\CMS\Frontend\Controller\ErrorController;
use TYPO3\CMS\Frontend\Page\PageAccessFailureReasons;

/**
 * Class UserController
 *
 * @package Blueways\BwGuild\Controller
 */
class UserController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    protected UserRepository $userRepository;

    protected CategoryRepository $categoryRepository;

    protected AccessControlService $accessControlService;

    public function __construct(
        UserRepository $userRepository,
        CategoryRepository $categoryRepository,
        AccessControlService $accessControlService
    ) {
        $this->userRepository = $userRepository;
        $this->categoryRepository = $categoryRepository;
        $this->accessControlService = $accessControlService;
    }

    public function initializeAction(): void
    {
        parent::initializeAction();

        $this->mergeTyposcriptSettings();
    }

    /**
     * Merges the typoscript settings with the settings from flexform
     */
    private function mergeTyposcriptSettings(): void
    {
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        try {
            $typoscript = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
            ArrayUtility::mergeRecursiveWithOverrule(
                $typoscript['plugin.']['tx_bwguild_userlist.']['settings.'],
                $typoscript['plugin.']['tx_bwguild.']['settings.'],
                true,
                false,
                false
            );
            ArrayUtility::mergeRecursiveWithOverrule(
                $typoscript['plugin.']['tx_bwguild_userlist.']['settings.'],
                $this->settings,
                true,
                false,
                false
            );
            $this->settings = $typoscript['plugin.']['tx_bwguild_userlist.']['settings.'];
        } catch (\TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException $exception) {
        }
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    public function listAction(): ResponseInterface
    {
        $demand = UserDemand::createFromSettings($this->settings);

        // override filter from form
        if ($this->request->hasArgument('demand')) {
            $demand->overrideFromRequest($this->request);
        }

        // redirect to search action to display another view
        if ($this->settings['mode'] === 'search') {
            return (new ForwardResponse('search'));
        }

        // find user by demand
        $users = $this->userRepository->findDemanded($demand);

        // create pagination
        $currentPage = $this->request->hasArgument('currentPage') ? (int)$this->request->getArgument('currentPage') : 1;
        $itemsPerPage = (int)$this->settings['itemsPerPage'];
        $paginator = new ArrayPaginator($users, $currentPage, $itemsPerPage);
        $pagination = new SimplePagination($paginator);

        // get categories by category settings in plugin
        $catConjunction = $this->settings['categoryConjunction'];
        if ($catConjunction === 'or' || $catConjunction === 'and') {
            $categories = $this->categoryRepository->findFromUidList($this->settings['categories']);
        } elseif ($catConjunction === 'notor' || $catConjunction === 'notand') {
            $categories = $this->categoryRepository->findFromUidListNot($this->settings['categories']);
        } else {
            $categories = $this->categoryRepository->findAll();
        }

        // disable indexing of list view
        $metaTagManager = GeneralUtility::makeInstance(MetaTagManagerRegistry::class);
        $metaTagManager->getManagerForProperty('robots')->addProperty('robots', 'noindex, follow');

        $this->view->assign('users', $users);
        $this->view->assign('demand', $demand);
        $this->view->assign('categories', $categories);
        $this->view->assign('pagination', [
            'currentPage' => $currentPage,
            'paginator' => $paginator,
            'pagination' => $pagination,
        ]);

        return $this->htmlResponse($this->view->render());
    }

    public function searchAction(): ResponseInterface
    {
        $demand = UserDemand::createFromSettings($this->settings);

        // override filter from form
        if ($this->request->hasArgument('demand')) {
            $demand->overrideFromRequest($this->request);
        }

        $this->view->assign('demand', $demand);

        return $this->htmlResponse($this->view->render());
    }

    /**
     * @throws \TYPO3\CMS\Core\Error\Http\PageNotFoundException|\JsonException
     */
    public function showAction(User $user): ResponseInterface
    {
        if (!$user->isPublicProfile()) {
            $response = GeneralUtility::makeInstance(ErrorController::class)->pageNotFoundAction(
                $GLOBALS['TYPO3_REQUEST'],
                'Profile not found',
                ['code' => PageAccessFailureReasons::PAGE_NOT_FOUND]
            );
            throw new PageNotFoundException($response);
        }

        $schema = $user->getJsonSchema($this->settings);

        if (isset($schema['logo'])) {
            $schema['logo'] = 'https://' . $_SERVER['SERVER_NAME'] . '/' . $schema['logo'];
            $schema['image'] = $schema['logo'];
        }

        if ((int)$this->settings['schema.']['enable']) {
            $json = json_encode($schema, JSON_THROW_ON_ERROR);
            $assetCollector = GeneralUtility::makeInstance(AssetCollector::class);
            $assetCollector->addInlineJavaScript('bwguild_json', $json, ['type' => 'application/ld+json']);
        }

        $GLOBALS['TSFE']->page['title'] = $schema['name'];
        $GLOBALS['TSFE']->page['description'] = $schema['description'];

        $metaTagManager = GeneralUtility::makeInstance(MetaTagManagerRegistry::class);
        $metaTagManager->getManagerForProperty('og:title')->addProperty('og:title', $schema['name']);
        $metaTagManager->getManagerForProperty('og:description')->addProperty('og:description', $schema['description']);
        $metaTagManager->getManagerForProperty('og:image')->addProperty('og:image', $schema['image']);

        $this->view->assign('user', $user);

        return $this->htmlResponse($this->view->render());
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException
     * @throws \TYPO3\CMS\Core\Http\PropagateResponseException
     */
    public function editAction(): ResponseInterface
    {
        if (!$this->accessControlService->hasLoggedInFrontendUser()) {
            $this->throwStatus(403, 'Not logged in');
        }

        $user = $this->userRepository->findByUid($this->accessControlService->getFrontendUserUid());
        $categories = $this->categoryRepository->findFromUidList($this->settings['categories']);

        $this->view->assign('user', $user);
        $this->view->assign('categories', $categories);

        return $this->htmlResponse($this->view->render());
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException
     */
    public function initializeUpdateAction(): void
    {
        if ($this->arguments->hasArgument('user')) {
            $this->setTypeConverterConfigurationForImageUpload('user');

            $deleteLog = $this->request->hasArgument('deleteLogo') && $this->request->getArgument('deleteLogo');

            // ignore logo parameter if empty
            if ($deleteLog || $_FILES['tx_bwguild_userlist']['name']['user']['logo'] === '') {
                // unset logo argument
                $userArgument = $this->request->getArgument('user');
                unset($userArgument['logo']);
                $this->request->setArgument('user', $userArgument);

                // unset logo validator
                $validator = $this->arguments->getArgument('user')->getValidator();
                foreach ($validator->getValidators() as $subValidator) {
                    /** @var GenericObjectValidator $subValidatorSub */
                    foreach ($subValidator->getValidators() as $subValidatorSub) {
                        $subValidatorSub->getPropertyValidators('logo')->removeAll(
                            $subValidatorSub->getPropertyValidators('logo')
                        );
                    }
                }
            }
        }
    }

    protected function setTypeConverterConfigurationForImageUpload($argumentName): void
    {
        \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\Container\Container::class)
            ->registerImplementation(
                \TYPO3\CMS\Extbase\Domain\Model\FileReference::class,
                \Blueways\BwGuild\Domain\Model\FileReference::class
            );

        $uploadFolder = $this->getTargetLogoStorageUid() . ':/' . $this->getTargetLogoFolderName();

        $uploadConfiguration = [
            UploadedFileReferenceConverter::CONFIGURATION_ALLOWED_FILE_EXTENSIONS => $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'],
            UploadedFileReferenceConverter::CONFIGURATION_UPLOAD_FOLDER => $uploadFolder,
        ];
        $newExampleConfiguration = $this->arguments[$argumentName]->getPropertyMappingConfiguration();
        $newExampleConfiguration->forProperty('logo')
            ->setTypeConverterOptions(
                UploadedFileReferenceConverter::class,
                $uploadConfiguration
            );
    }

    private function getTargetLogoStorageUid(): int
    {
        $targetParts = GeneralUtility::trimExplode(':', $this->settings['userLogoFolder']);
        if (count($targetParts) === 2) {
            return (int)$targetParts[0];
        }
        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = $this->objectManager->get(ResourceFactory::class);
        return $resourceFactory->getDefaultStorage() ? $resourceFactory->getDefaultStorage()->getUid() : 0;
    }

    private function getTargetLogoFolderName(): string
    {
        $targetParts = GeneralUtility::trimExplode(':', $this->settings['userLogoFolder']);
        return count($targetParts) === 2 ? $targetParts[1] : $targetParts[0];
    }

    public function injectCategoryRepository(\Blueways\BwGuild\Domain\Repository\CategoryRepository $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    public function injectUserRepository(\Blueways\BwGuild\Domain\Repository\UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     * @throws \TYPO3\CMS\Core\Http\PropagateResponseException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException
     */
    public function updateAction(User $user): void
    {
        if (!$this->accessControlService->isLoggedIn($user)) {
            $this->throwStatus(403, 'No access to edit this user');
        }

        // delete all logos
        if ($this->request->hasArgument('deleteLogo') && $this->request->getArgument('deleteLogo') === '1') {
            $this->userRepository->deleteAllUserLogos($user->getUid());
        }

        // delete existing logo(s) if new one is created
        $userArguments = $this->request->getArgument('user');
        if (isset($userArguments['logo']) && $logo = $user->getLogo()) {
            $this->userRepository->deleteAllUserLogos($user->getUid());
        }

        $user->geoCodeAddress();
        $this->userRepository->update($user);

        $this->addFlashMessage(
            $this->getLanguageService()->sL('LLL:EXT:bw_guild/Resources/Private/Language/locallang_fe.xlf:user.update.success.message'),
            $this->getLanguageService()->sL('LLL:EXT:bw_guild/Resources/Private/Language/locallang_fe.xlf:user.update.success.title'),
            \TYPO3\CMS\Core\Messaging\AbstractMessage::OK
        );

        $this->redirect('edit');
    }

    /**
     * @return \TYPO3\CMS\Core\Localization\LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'] ?? GeneralUtility::makeInstance(LanguageService::class);
    }

    /**
     * @param \Blueways\BwGuild\Domain\Model\User|null $user
     * @TYPO3\CMS\Extbase\Annotation\IgnoreValidation("user")
     */
    public function newAction(User $user = null): void
    {
        if (!$user) {
            $user = new User();
        }
        $this->view->assign('user', $user);
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Core\Crypto\PasswordHashing\InvalidPasswordHashException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     */
    public function createAction(User $user): void
    {
        if ($this->accessControlService->hasLoggedInFrontendUser()) {
            $this->addFlashMessage(
                $this->getLanguageService()->sL('LLL:EXT:bw_guild/Resources/Private/Language/locallang_fe.xlf:user.create.loggedin.message'),
                $this->getLanguageService()->sL('LLL:EXT:bw_guild/Resources/Private/Language/locallang_fe.xlf:user.create.loggedin.title'),
                \TYPO3\CMS\Core\Messaging\AbstractMessage::WARNING
            );
            $this->redirect('new');
        }

        if ($user->getUid()) {
            $this->addFlashMessage(
                $this->getLanguageService()->sL('LLL:EXT:bw_guild/Resources/Private/Language/locallang_fe.xlf:user.create.exists.message'),
                $this->getLanguageService()->sL('LLL:EXT:bw_guild/Resources/Private/Language/locallang_fe.xlf:user.create.exists.title'),
                \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR
            );
            $this->redirect('new');
        }

        if ($this->settings['useEmailAsUsername'] === "1") {
            $user->setUsername($user->getEmail());
        }

        $user->setPassword($this->encryptPassword($user->getPassword()));
        $user->geoCodeAddress();

        $this->userRepository->add($user);

        $this->addFlashMessage(
            $this->getLanguageService()->sL('LLL:EXT:bw_guild/Resources/Private/Language/locallang_fe.xlf:user.create.success.message'),
            $this->getLanguageService()->sL('LLL:EXT:bw_guild/Resources/Private/Language/locallang_fe.xlf:user.create.success.title'),
            \TYPO3\CMS\Core\Messaging\AbstractMessage::OK
        );

        $this->view->assign('user', $user);
    }

    /**
     * @param string $password
     * @return string
     * @throws \TYPO3\CMS\Core\Crypto\PasswordHashing\InvalidPasswordHashException
     */
    private function encryptPassword(string $password): string
    {
        $passwordHashFactory = GeneralUtility::makeInstance(PasswordHashFactory::class);
        return $passwordHashFactory->getDefaultHashInstance('FE')->getHashedPassword($password);
    }
}
