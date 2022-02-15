<?php

defined('TYPO3_MODE') || die();

$typo3Version = TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionStringToArray(TYPO3\CMS\Core\Utility\VersionNumberUtility::getNumericTypo3Version());
$userControllerName = $typo3Version['version_main'] >= 10 ? \Blueways\BwGuild\Controller\UserController::class : 'User';
$offerControllerName = $typo3Version['version_main'] >= 10 ? \Blueways\BwGuild\Controller\OfferController::class : 'User';

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'Blueways.BwGuild',
    'Userlist',
    [
        $userControllerName => 'list, show, edit, update, new, search',
    ],
    // non-cacheable actions
    [
        $userControllerName => 'edit, update, list'
    ]
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'Blueways.BwGuild',
    'Offerlist',
    [
        $offerControllerName => 'list, show, edit, update, new, delete',
    ],
    // non-cacheable actions
    [
        $offerControllerName => 'edit, update, delete, new'
    ]
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'Blueways.BwGuild',
    'Offerlatest',
    [
        $offerControllerName => 'latest',
    ],
    [
    ]
);



// Define state cache, if not already defined
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['bwguild'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['bwguild'] = [
        'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
        'backend' => \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
    ];
}

// Register geo coding task
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Blueways\BwGuild\Task\GeocodingTask::class] = [
    'extension' => 'bw_guild',
    'title' => 'Geocoding of fe_user & offer records',
    'description' => 'Check all fe_user and offer records for geocoding information and write them into the fields'
];

// Register hook to set sorting field
$GLOBALS ['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['bw_guild'] = 'Blueways\\BwGuild\\Hooks\\TCEmainHook';
$GLOBALS ['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['bw_guild'] = 'Blueways\\BwGuild\\Hooks\\TCEmainHook';

// Register SlugUpdate Wizard
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['bwGuildSlugUpdater'] = Blueways\BwGuild\Updates\SlugUpdater::class;

// Register TypeConverter for logo upload via frontend
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerTypeConverter('Blueways\\BwGuild\\Property\\TypeConverter\\UploadedFileReferenceConverter');
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerTypeConverter('Blueways\\BwGuild\\Property\\TypeConverter\\ObjectStorageConverter');

// Register ke_search Hook
if(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('ke_search')) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['registerIndexerConfiguration'][] =
        \Blueways\BwGuild\Hooks\OfferIndexer::class;
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['customIndexer'][] =
        \Blueways\BwGuild\Hooks\OfferIndexer::class;
}
